<?php

declare(strict_types=1);

namespace App\Plugins\PersonalityAssessment;

use App\Contracts\Automation\AutomationAction;
use App\Services\Plugin\AbstractPlugin;
use App\Services\Plugin\PluginApi;

/**
 * Sample plugin (docs/51 Plugin SDK) — a Personality Assessment module. It shows how
 * a feature is added entirely through the PluginApi sandbox (a permission, an
 * interview type, a workflow node, a dashboard widget and an automation action)
 * without touching platform core. Real assessment plugins (Coding Challenges,
 * Psychometric/IQ/Language tests, Background Checks, HackerRank/LeetCode) follow the
 * same shape.
 */
final class PersonalityAssessmentPlugin extends AbstractPlugin
{
    public function key(): string
    {
        return 'personality_assessment';
    }

    public function manifest(): array
    {
        return [
            'name'                 => 'Personality Assessment',
            'version'              => '1.0.0',
            'author'               => 'HalaOps',
            'description'          => 'Adds a personality assessment stage and scoring.',
            'dependencies'         => [],
            'permissions'          => [
                ['key' => 'plugin.personality.manage', 'description' => 'Manage personality assessments'],
            ],
            'required_modules'     => [],
            'min_platform_version' => '0.1.0',
            'license'              => 'proprietary',
        ];
    }

    public function register(PluginApi $api): void
    {
        $api->addPermission('plugin.personality.manage', 'Manage personality assessments')
            ->addInterviewType('personality_assessment', 'Personality Assessment')
            ->addWorkflowNode('personality_test', 'Personality Test')
            ->addDashboardWidget(['key' => 'personality_summary', 'title' => 'Personality Insights'])
            ->addAutomationAction($this->scoreAction());
    }

    private function scoreAction(): AutomationAction
    {
        return new class implements AutomationAction {
            public function key(): string
            {
                return 'personality_score';
            }

            public function run(array $context, array $config): array
            {
                // Deterministic placeholder scoring; a real plugin would call its provider.
                return ['personality_scored' => true, 'traits' => $config['traits'] ?? []];
            }
        };
    }
}
