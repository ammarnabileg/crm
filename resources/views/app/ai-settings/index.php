<?php $this->extends('layouts.app'); ?>
<?php $this->section('content'); ?>
<?php
/**
 * AI Settings (docs/51). Each company configures its OWN provider keys (stored
 * encrypted in tenant_ai_keys via AiCredential) and the per-tenant engine
 * defaults. Secrets are NEVER rendered — only a masked hint + status. The page is
 * fully functional with zero keys configured (it shows the empty state).
 *
 * @var array<int,array<string,mixed>>            $keys
 * @var array<string,array{label:string,azure:bool}> $providers
 * @var array<string,array<string,string>>        $models
 * @var array<string,string>                      $defaults
 * @var bool                                       $canManage
 */

// Provider <select> options (value => label) for the add-key form.
$providerOptions = [];
foreach ($providers as $key => $meta) {
    $providerOptions[$key] = (string) ($meta['label'] ?? $key);
}

// Default-provider options: only providers the tenant actually has keys for, so
// the engine default can resolve. Falls back to the full catalogue when empty.
$keyedProviders = [];
foreach ($keys as $k) {
    $keyedProviders[(string) $k['provider']] = (string) $k['label'];
}
$defaultProviderOptions = $keyedProviders !== [] ? $keyedProviders : $providerOptions;

// Flatten the model catalogue into a single value => "Provider · Model" map.
$modelOptions = [];
foreach ($models as $providerKey => $list) {
    $providerLabel = (string) ($providers[$providerKey]['label'] ?? $providerKey);
    foreach ($list as $modelKey => $modelName) {
        $modelOptions[$modelKey] = $providerLabel . ' · ' . $modelName;
    }
}

$statusBadge = static function (array $k): string {
    if ($k['is_default']) {
        return component('badge', ['label' => 'Default', 'variant' => 'brand', 'dot' => true]);
    }
    if ($k['is_active']) {
        return component('badge', ['label' => 'Active', 'variant' => 'green', 'dot' => true]);
    }

    return component('badge', ['label' => 'Inactive', 'variant' => 'slate']);
};
?>

<?= component('page-header', [
    'title'    => 'AI Settings',
    'subtitle' => 'Connect your own AI providers and set the defaults the engine uses. Your keys are encrypted and never shown in full.',
]) ?>

<div class="space-y-6">

    <?php // ---- Configured providers --------------------------------------- ?>
    <?php
    $keysBody = '';
    if ($keys === []) {
        // Zero keys: degrade gracefully with the unified empty state. The add-key
        // form below still lets a manager connect the first provider.
        $keysBody = component('state', [
            'variant' => 'empty',
            'title'   => 'No AI providers connected',
            'message' => $canManage
                ? 'Add your first provider key below to start using AI features. The platform stores no keys of its own.'
                : 'No AI providers have been connected for this workspace yet.',
        ]);
    } else {
        $rows = [];
        foreach ($keys as $k) {
            $actions = '';
            if ($canManage) {
                $actions =
                    '<form method="post" action="' . e(url('ai/keys/delete')) . '" class="inline">'
                    . csrf_field()
                    . '<input type="hidden" name="id" value="' . e((string) $k['id']) . '">'
                    . component('button', [
                        'label'      => 'Remove',
                        'type'       => 'submit',
                        'variant'    => 'danger',
                        'size'       => 'sm',
                        'confirm'    => 'Remove this provider key? AI features using it will stop working.',
                    ])
                    . '</form>';
            } else {
                $actions = '<span class="text-slate-400">—</span>';
            }

            $rows[] = [
                '<span class="font-medium text-slate-900 dark:text-white">' . e((string) $k['label']) . '</span>',
                '<span class="text-slate-600 dark:text-slate-300">' . e((string) $k['provider']) . '</span>',
                '<code class="rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-700 dark:bg-slate-800 dark:text-slate-200">' . e((string) $k['masked']) . '</code>',
                $statusBadge($k),
                '<div class="flex justify-end">' . $actions . '</div>',
            ];
        }

        $keysBody = component('table', [
            'columns' => [
                'Name',
                'Provider',
                'API key',
                'Status',
                ['label' => 'Actions', 'align' => 'end'],
            ],
            'rows'  => $rows,
            'empty' => 'No AI providers connected.',
        ]);
    }

    echo component('card', [
        'title' => 'Connected providers',
        'slot'  => $keysBody,
    ]);
    ?>

    <?php // ---- Add / update a provider key ---------------------------------- ?>
    <?php if ($canManage): ?>
        <?php
        $azureHint = component('alert', [
            'variant' => 'info',
            'message' => 'Azure OpenAI also needs the resource endpoint, deployment name and API version. Fill those fields below when the provider is Azure OpenAI.',
        ]);

        $formInner =
            '<form method="post" action="' . e(url('ai/keys')) . '" class="space-y-4">'
            . csrf_field()
            . '<div class="grid gap-4 sm:grid-cols-2">'
            . component('field', [
                'label'    => 'Provider',
                'for'      => 'provider',
                'name'     => 'provider',
                'required' => true,
                'control'  => component('select', [
                    'name'        => 'provider',
                    'id'          => 'provider',
                    'options'     => $providerOptions,
                    'placeholder' => 'Choose a provider',
                    'required'    => true,
                ]),
            ])
            . component('field', [
                'label'   => 'Label (optional)',
                'for'     => 'label',
                'name'    => 'label',
                'hint'    => 'A friendly name to recognise this key.',
                'control' => component('input', [
                    'name'        => 'label',
                    'id'          => 'label',
                    'placeholder' => 'e.g. Production OpenAI',
                ]),
            ])
            . '</div>'
            . component('field', [
                'label'    => 'API key',
                'for'      => 'api_key',
                'name'     => 'api_key',
                'required' => true,
                'hint'     => 'Stored encrypted. We only ever display a masked hint.',
                'control'  => component('input', [
                    'name'        => 'api_key',
                    'id'          => 'api_key',
                    'type'        => 'password',
                    'placeholder' => 'sk-…',
                    'required'    => true,
                    'attributes'  => ['autocomplete' => 'off'],
                ]),
            ])
            . '<div class="grid gap-4 sm:grid-cols-3">'
            . component('field', [
                'label'   => 'Azure endpoint',
                'for'     => 'endpoint',
                'name'    => 'endpoint',
                'hint'    => 'Azure OpenAI only.',
                'control' => component('input', [
                    'name'        => 'endpoint',
                    'id'          => 'endpoint',
                    'placeholder' => 'https://my-resource.openai.azure.com',
                ]),
            ])
            . component('field', [
                'label'   => 'Deployment',
                'for'     => 'deployment',
                'name'    => 'deployment',
                'hint'    => 'Azure OpenAI only.',
                'control' => component('input', [
                    'name'        => 'deployment',
                    'id'          => 'deployment',
                    'placeholder' => 'gpt-4o',
                ]),
            ])
            . component('field', [
                'label'   => 'API version',
                'for'     => 'api_version',
                'name'    => 'api_version',
                'hint'    => 'Azure OpenAI only.',
                'control' => component('input', [
                    'name'        => 'api_version',
                    'id'          => 'api_version',
                    'placeholder' => '2024-02-15-preview',
                ]),
            ])
            . '</div>'
            . '<div class="flex flex-wrap items-center justify-between gap-3 pt-2">'
            . component('switch', [
                'name'  => 'is_default',
                'label' => 'Make this the default provider',
            ])
            . component('button', [
                'label' => 'Save provider key',
                'type'  => 'submit',
            ])
            . '</div>'
            . '</form>';

        echo component('card', [
            'title' => 'Add or update a provider key',
            'slot'  => $azureHint . '<div class="mt-4">' . $formInner . '</div>',
        ]);
        ?>
    <?php endif; ?>

    <?php // ---- AI defaults -------------------------------------------------- ?>
    <?php
    $defaultsForm =
        '<form method="post" action="' . e(url('ai/defaults')) . '" class="space-y-4">'
        . csrf_field()
        . '<div class="grid gap-4 sm:grid-cols-2">'
        . component('field', [
            'label'   => 'Default provider',
            'for'     => 'default_provider',
            'name'    => 'default_provider',
            'hint'    => 'Preferred provider for AI requests.',
            'control' => component('select', [
                'name'        => 'default_provider',
                'id'          => 'default_provider',
                'options'     => $defaultProviderOptions,
                'selected'    => $defaults['ai.default_provider'] ?? '',
                'placeholder' => 'Auto (router decides)',
            ]),
        ])
        . component('field', [
            'label'   => 'Default model',
            'for'     => 'default_model',
            'name'    => 'default_model',
            'hint'    => $modelOptions === [] ? 'No models catalogued yet — leave blank for auto.' : 'Preferred model for AI requests.',
            'control' => $modelOptions === []
                ? component('input', [
                    'name'        => 'default_model',
                    'id'          => 'default_model',
                    'value'       => $defaults['ai.default_model'] ?? '',
                    'placeholder' => 'e.g. gpt-4o',
                ])
                : component('select', [
                    'name'        => 'default_model',
                    'id'          => 'default_model',
                    'options'     => $modelOptions,
                    'selected'    => $defaults['ai.default_model'] ?? '',
                    'placeholder' => 'Auto (router decides)',
                ]),
        ])
        . component('field', [
            'label'   => 'Temperature',
            'for'     => 'temperature',
            'name'    => 'temperature',
            'hint'    => '0 = deterministic, 2 = very creative.',
            'control' => component('input', [
                'name'       => 'temperature',
                'id'         => 'temperature',
                'type'       => 'number',
                'value'      => $defaults['ai.temperature'] ?? '',
                'attributes' => ['min' => '0', 'max' => '2', 'step' => '0.1'],
            ]),
        ])
        . component('field', [
            'label'   => 'Max tokens',
            'for'     => 'max_tokens',
            'name'    => 'max_tokens',
            'hint'    => 'Upper bound on a single response.',
            'control' => component('input', [
                'name'       => 'max_tokens',
                'id'         => 'max_tokens',
                'type'       => 'number',
                'value'      => $defaults['ai.max_tokens'] ?? '',
                'attributes' => ['min' => '1', 'step' => '1'],
            ]),
        ])
        . component('field', [
            'label'   => 'Language',
            'for'     => 'language',
            'name'    => 'language',
            'hint'    => 'Default language for AI output.',
            'control' => component('select', [
                'name'     => 'language',
                'id'       => 'language',
                'options'  => ['en' => 'English', 'ar' => 'Arabic'],
                'selected' => $defaults['ai.language'] ?? 'en',
            ]),
        ])
        . component('field', [
            'label'   => 'Monthly cost limit',
            'for'     => 'cost_limit',
            'name'    => 'cost_limit',
            'hint'    => 'Optional spend cap. Leave blank for no limit.',
            'control' => component('input', [
                'name'       => 'cost_limit',
                'id'         => 'cost_limit',
                'type'       => 'number',
                'value'      => $defaults['ai.cost_limit'] ?? '',
                'attributes' => ['min' => '0', 'step' => '0.01'],
            ]),
        ])
        . '</div>'
        . component('field', [
            'label'   => 'Provider priority',
            'for'     => 'provider_priority',
            'name'    => 'provider_priority',
            'hint'    => 'Comma-separated fallback order, e.g. anthropic,openai,gemini.',
            'control' => component('input', [
                'name'        => 'provider_priority',
                'id'          => 'provider_priority',
                'value'       => $defaults['ai.provider_priority'] ?? '',
                'placeholder' => 'anthropic,openai,gemini,deepseek,azure_openai',
            ]),
        ])
        . '<div class="border-t border-slate-100 pt-4 dark:border-slate-800">'
        . '<p class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200">Interview defaults</p>'
        . '<div class="grid gap-4 sm:grid-cols-2">'
        . component('field', [
            'label'   => 'Interview duration (minutes)',
            'for'     => 'interview_duration',
            'name'    => 'interview_duration',
            'control' => component('input', [
                'name'       => 'interview_duration',
                'id'         => 'interview_duration',
                'type'       => 'number',
                'value'      => $defaults['ai.interview.duration'] ?? '',
                'attributes' => ['min' => '1', 'step' => '1'],
            ]),
        ])
        . component('field', [
            'label'   => 'Questions per interview',
            'for'     => 'interview_questions',
            'name'    => 'interview_questions',
            'control' => component('input', [
                'name'       => 'interview_questions',
                'id'         => 'interview_questions',
                'type'       => 'number',
                'value'      => $defaults['ai.interview.questions'] ?? '',
                'attributes' => ['min' => '1', 'step' => '1'],
            ]),
        ])
        . '</div>'
        . '</div>'
        . '<div class="flex justify-end pt-2">'
        . component('button', ['label' => 'Save defaults', 'type' => 'submit'])
        . '</div>'
        . '</form>';

    if (! $canManage) {
        // Read-only viewers see the values but cannot submit; disable the controls
        // by replacing the editable form with a static note.
        $defaultsForm = component('alert', [
            'variant' => 'info',
            'message' => 'You can view AI settings but need the “Manage AI settings” permission to change them.',
        ]) . '<div class="pointer-events-none mt-4 opacity-60">' . $defaultsForm . '</div>';
    }

    echo component('card', [
        'title' => 'AI defaults',
        'slot'  => $defaultsForm,
    ]);
    ?>

</div>
<?php $this->endSection(); ?>
