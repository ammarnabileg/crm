<?php

declare(strict_types=1);

namespace App\Controllers\App;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\AiCredential;

/**
 * AI Settings (docs/51) — each company manages its OWN provider keys and AI
 * defaults. The platform stores no keys: every secret is encrypted per-tenant in
 * `tenant_ai_keys` via the AiCredential model, and the engine defaults
 * (provider/model/temperature/…) live in the per-tenant settings store.
 *
 * Reads require ai.view; writes require ai.manage (enforced at the route layer,
 * re-checked here so a missing gate fails closed). The page is fully functional
 * with zero keys configured — it simply renders the empty state.
 */
final class AiSettingsController extends Controller
{
    /**
     * Provider catalogue used by the UI. Mirrors the `ai_providers` seed
     * (docs/database/26): key => [label, needsEndpoint?]. Azure additionally
     * needs an endpoint/deployment/api-version; the rest only need an API key.
     * Kept here as a safe fallback so the page renders even before the catalog
     * table is reachable (degrade gracefully).
     *
     * @var array<string, array{label:string, azure:bool}>
     */
    private const PROVIDERS = [
        'openai'       => ['label' => 'OpenAI', 'azure' => false],
        'anthropic'    => ['label' => 'Anthropic Claude', 'azure' => false],
        'gemini'       => ['label' => 'Google Gemini', 'azure' => false],
        'deepseek'     => ['label' => 'DeepSeek', 'azure' => false],
        'azure_openai' => ['label' => 'Azure OpenAI', 'azure' => true],
        'heygen'       => ['label' => 'HeyGen', 'azure' => false],
    ];

    /** Settings keys (with defaults) that the engine reads — all per-tenant. */
    private const DEFAULTS = [
        'ai.default_provider'  => '',
        'ai.default_model'     => '',
        'ai.temperature'       => '0.7',
        'ai.max_tokens'        => '2048',
        'ai.language'          => 'en',
        'ai.cost_limit'        => '',
        'ai.provider_priority' => 'anthropic,openai,gemini,deepseek,azure_openai',
        // Interview defaults (AI Interview Engine).
        'ai.interview.duration'  => '30',
        'ai.interview.questions' => '8',
    ];

    public function index(Request $request): Response
    {
        abort_unless(can('ai.view'), 403, 'You do not have access to AI settings.');

        // Configured keys for this tenant (tenant-scoped + soft-delete aware via
        // the model's default query). Secrets are never sent to the view — only a
        // masked hint + "configured" state.
        $keys = [];
        foreach (AiCredential::all() as $credential) {
            $provider = (string) $credential->provider;
            $keys[] = [
                'id'         => (string) $credential->getKey(),
                'provider'   => $provider,
                'label'      => (string) ($credential->label ?? '') !== ''
                    ? (string) $credential->label
                    : ($this->providerCatalog()[$provider]['label'] ?? $provider),
                'masked'     => $credential->maskedKey(),
                'is_active'  => (bool) $credential->is_active,
                'is_default' => (bool) $credential->is_default,
            ];
        }

        return $this->view('app.ai-settings.index', [
            'title'      => 'AI Settings',
            'keys'       => $keys,
            'providers'  => $this->providerCatalog(),
            'models'     => $this->modelCatalog(),
            'defaults'   => $this->effectiveDefaults(),
            'canManage'  => can('ai.manage'),
        ]);
    }

    public function storeKey(Request $request): Response
    {
        abort_unless(can('ai.manage'), 403, 'You cannot manage AI settings.');

        $data = $this->validate($request, [
            'provider' => 'required|in:' . implode(',', array_keys($this->providerCatalog())),
            'api_key'  => 'required|max:500',
        ]);

        $provider = $data['provider'];

        // Build the encrypted credential payload. Azure-style providers carry the
        // resource endpoint + deployment alongside the key (the adapter reads them).
        $secrets = ['api_key' => trim((string) $data['api_key'])];
        if (($this->providerCatalog()[$provider]['azure'] ?? false)) {
            foreach (['endpoint', 'deployment', 'api_version'] as $field) {
                $value = trim((string) $request->input($field, ''));
                if ($value !== '') {
                    $secrets[$field] = $value;
                }
            }
        }

        $makeDefault = (string) $request->input('is_default', '0') === '1';

        $attributes = [
            'provider'    => $provider,
            'label'       => trim((string) $request->input('label', '')) ?: null,
            'credentials' => AiCredential::encryptSecrets($secrets),
            'is_active'   => 1,
            'is_default'  => $makeDefault ? 1 : 0,
        ];

        // One key per provider per tenant (UNIQUE workspace+provider) — update the
        // existing row, otherwise create. A previously soft-deleted row still owns
        // the unique slot, so reuse it (restore + overwrite) instead of inserting a
        // duplicate. Either way the secret is encrypted.
        $row = AiCredential::withTrashed()->where('provider', '=', $provider)->first();
        if ($row !== null) {
            $existing = AiCredential::hydrate($row);
            $existing->restore();
            $existing->update($attributes);
            $keyId = (int) $existing->getKey();
        } else {
            $keyId = (int) AiCredential::create($attributes)->getKey();
        }

        // A single default: clear the flag on every other key when this one wins.
        if ($makeDefault) {
            $this->clearOtherDefaults($keyId);
        }

        $this->withSuccess('AI provider key saved.');

        return $this->redirect(url('ai'));
    }

    public function deleteKey(Request $request): Response
    {
        abort_unless(can('ai.manage'), 403, 'You cannot manage AI settings.');

        $data = $this->validate($request, ['id' => 'required']);

        $credential = AiCredential::find($data['id']);
        if ($credential !== null) {
            $credential->delete();
            $this->withSuccess('AI provider key removed.');
        } else {
            $this->withError('That key no longer exists.');
        }

        return $this->redirect(url('ai'));
    }

    public function updateDefaults(Request $request): Response
    {
        abort_unless(can('ai.manage'), 403, 'You cannot manage AI settings.');

        // All optional. NOTE: the Validator's `max` is numeric-aware — for a value
        // that looks numeric it caps the MAGNITUDE (not the string length), so the
        // numeric fields use realistic ceilings; the free-text fields use length
        // caps (they are not numeric).
        $data = $this->validate($request, [
            'default_provider'  => 'nullable|max:40',
            'default_model'     => 'nullable|max:120',
            'temperature'       => 'nullable|numeric|max:2',
            'max_tokens'        => 'nullable|integer|max:1000000',
            'language'          => 'nullable|in:en,ar',
            'cost_limit'        => 'nullable|numeric|max:100000000',
            'provider_priority' => 'nullable|max:255',
            'interview_duration'  => 'nullable|integer|max:100000',
            'interview_questions' => 'nullable|integer|max:1000',
        ]);

        // Persist each engine default through the per-tenant settings store. Blank
        // numeric inputs fall back to null so the config default applies again.
        settings()->set('ai.default_provider', (string) ($data['default_provider'] ?? ''));
        settings()->set('ai.default_model', (string) ($data['default_model'] ?? ''));
        settings()->set('ai.temperature', $this->floatOrNull($data['temperature'] ?? null));
        settings()->set('ai.max_tokens', $this->intOrNull($data['max_tokens'] ?? null));
        settings()->set('ai.language', (string) ($data['language'] ?? 'en'));
        settings()->set('ai.cost_limit', $this->floatOrNull($data['cost_limit'] ?? null));
        settings()->set('ai.provider_priority', (string) ($data['provider_priority'] ?? ''));
        settings()->set('ai.interview.duration', $this->intOrNull($data['interview_duration'] ?? null));
        settings()->set('ai.interview.questions', $this->intOrNull($data['interview_questions'] ?? null));

        $this->withSuccess('AI defaults updated.');

        return $this->redirect(url('ai'));
    }

    // -----------------------------------------------------------------
    // Internals
    // -----------------------------------------------------------------

    /**
     * Provider list for the UI. Prefer the live `ai_providers` catalog so new
     * providers appear without a code change; fall back to the constant when the
     * table is empty/unreachable (the page must always render).
     *
     * @return array<string, array{label:string, azure:bool}>
     */
    private function providerCatalog(): array
    {
        try {
            $rows = app('db')->table('ai_providers')
                ->where('is_active', '=', 1)
                ->orderBy('sort_order', 'asc')
                ->get();

            $catalog = [];
            foreach ($rows as $row) {
                $key = (string) ($row['key'] ?? '');
                if ($key === '') {
                    continue;
                }
                $catalog[$key] = [
                    'label' => (string) ($row['name'] ?? $key),
                    'azure' => $key === 'azure_openai'
                        || str_contains((string) ($row['auth_type'] ?? ''), 'endpoint'),
                ];
            }

            if ($catalog !== []) {
                return $catalog;
            }
        } catch (\Throwable) {
            // Fall through to the static catalogue.
        }

        return self::PROVIDERS;
    }

    /**
     * Active models grouped for the default-model dropdown: provider key => list
     * of [model key => name]. Empty when none are catalogued (graceful).
     *
     * @return array<string, array<string, string>>
     */
    private function modelCatalog(): array
    {
        try {
            $rows = app('db')->table('ai_models')
                ->select('ai_models.key AS model_key', 'ai_models.name AS model_name', 'ai_providers.key AS provider_key')
                ->join('ai_providers', 'ai_providers.id', '=', 'ai_models.provider_id')
                ->where('ai_models.is_active', '=', 1)
                ->whereNull('ai_models.deleted_at')
                ->orderBy('ai_models.sort_order', 'asc')
                ->get();

            $catalog = [];
            foreach ($rows as $row) {
                $provider = (string) ($row['provider_key'] ?? '');
                $modelKey = (string) ($row['model_key'] ?? '');
                if ($provider === '' || $modelKey === '') {
                    continue;
                }
                $catalog[$provider][$modelKey] = (string) ($row['model_name'] ?? $modelKey);
            }

            return $catalog;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Effective defaults for the form: the constant defaults overlaid with any
     * stored per-tenant values.
     *
     * @return array<string, string>
     */
    private function effectiveDefaults(): array
    {
        $values = [];
        foreach (self::DEFAULTS as $key => $fallback) {
            $stored = settings()->get($key, $fallback);
            $values[$key] = $stored === null ? '' : (string) $stored;
        }

        return $values;
    }

    /** Clear the default flag on every active key other than $keepId. */
    private function clearOtherDefaults(int $keepId): void
    {
        foreach (AiCredential::all() as $credential) {
            if ((int) $credential->getKey() !== $keepId && (bool) $credential->is_default) {
                $credential->update(['is_default' => 0]);
            }
        }
    }

    private function floatOrNull(mixed $value): ?float
    {
        $value = trim((string) $value);

        return $value === '' ? null : (float) $value;
    }

    private function intOrNull(mixed $value): ?int
    {
        $value = trim((string) $value);

        return $value === '' ? null : (int) $value;
    }
}
