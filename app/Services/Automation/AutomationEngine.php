<?php

declare(strict_types=1);

namespace App\Services\Automation;

use App\Contracts\Automation\AutomationAction;
use App\Contracts\Automation\AutomationCondition;
use App\Models\AutomationRun;
use App\Models\AutomationRunStep;
use App\Services\Automation\Actions\EndAction;
use App\Services\Automation\Actions\LogAction;
use App\Services\Automation\Actions\NotifyAction;
use App\Services\Automation\Actions\SetContextAction;
use App\Services\Automation\Actions\WebhookAction;
use App\Services\Automation\Conditions\AlwaysCondition;
use App\Services\Automation\Conditions\ExpressionCondition;
use App\Core\Database;
use Throwable;

/**
 * Workflow Automation Engine (docs/51) — fires automations on a trigger event.
 *
 * On `trigger($event, $context)` it finds the tenant's active automations bound to
 * that event and runs each: every CONDITION step must pass (AND); if so, the ACTION
 * steps run in order, their outputs chaining into the context. Each run + step is
 * recorded (`automation_runs`/`automation_run_steps`) for the debugger/execution
 * log. Conditions and actions are resolved from a registry, so plugins add their own
 * (`register*`) without touching core (Open/Closed).
 */
final class AutomationEngine
{
    /** @var array<string, AutomationAction> */
    private array $actions = [];

    /** @var array<string, AutomationCondition> */
    private array $conditions = [];

    public function __construct(private readonly Database $db)
    {
    }

    public static function make(): self
    {
        $engine = new self(app('db'));
        // Built-in conditions + actions.
        $engine->registerCondition(new ExpressionCondition());
        $engine->registerCondition(new AlwaysCondition());
        $engine->register(new LogAction());
        $engine->register(new SetContextAction());
        $engine->register(new WebhookAction());
        $engine->register(new NotifyAction());
        $engine->register(new EndAction());

        return $engine;
    }

    public function register(AutomationAction $action): self
    {
        $this->actions[$action->key()] = $action;

        return $this;
    }

    public function registerCondition(AutomationCondition $condition): self
    {
        $this->conditions[$condition->key()] = $condition;

        return $this;
    }

    /** @return string[] registered action keys (built-in + plugin) */
    public function actionKeys(): array
    {
        return array_keys($this->actions);
    }

    /**
     * Fire all active automations bound to $event for the current tenant.
     *
     * @param array<string,mixed> $context the event payload
     * @return array<int, AutomationRun>
     */
    public function trigger(string $event, array $context = []): array
    {
        $rows = $this->db->table('automations')
            ->where('workspace_id', '=', tenant()->id())
            ->where('trigger_event', '=', $event)
            ->where('is_active', '=', 1)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get();

        $runs = [];
        foreach ($rows as $row) {
            $runs[] = $this->run((int) $row['id'], $context, $event);
        }

        return $runs;
    }

    /**
     * Execute one automation against $context. Returns the recorded run.
     *
     * @param array<string,mixed> $context
     */
    public function run(int $automationId, array $context = [], ?string $event = null): AutomationRun
    {
        $automation = $this->db->table('automations')->where('id', '=', $automationId)->first();
        if ($automation === null) {
            throw new \RuntimeException("Automation {$automationId} not found.");
        }
        $event ??= (string) $automation['trigger_event'];

        $run = AutomationRun::create([
            'automation_id' => $automationId,
            'trigger_event' => $event,
            'status'        => 'running',
            'context'       => $this->encode($context),
            'started_at'    => now(),
        ]);
        $runId = (int) $run->getKey();

        $steps = $this->db->table('automation_steps')
            ->where('automation_id', '=', $automationId)
            ->orderBy('sort_order')
            ->get();

        $working = $context;
        $status = 'completed';
        $error = null;
        $seq = 0;
        $startedAt = microtime(true);

        foreach ($steps as $step) {
            $seq++;
            $key = (string) $step['key'];
            $config = $this->decode($step['config'] ?? null);
            $stepStart = microtime(true);

            if ((string) $step['step_type'] === 'condition') {
                $condition = $this->conditions[$key] ?? null;
                $passed = $condition !== null && $condition->evaluate($working, $config);
                $this->recordStep($runId, 'condition', $key, $passed ? 'passed' : 'failed', $seq, $config, ['result' => $passed], null, $stepStart);
                if (! $passed) {
                    $status = 'skipped'; // conditions not met → actions do not run
                    break;
                }
                continue;
            }

            // Action step.
            $action = $this->actions[$key] ?? null;
            if ($action === null) {
                $this->recordStep($runId, 'action', $key, 'error', $seq, $config, null, "no action registered: {$key}", $stepStart);
                $status = 'failed';
                $error = "no_action:{$key}";
                break;
            }

            try {
                $output = $action->run($working, $config);
                $this->recordStep($runId, 'action', $key, 'completed', $seq, $config, $output, null, $stepStart);
                if (! empty($output['_stop'])) {
                    break;
                }
                $working = array_merge($working, $output);
            } catch (Throwable $e) {
                $this->recordStep($runId, 'action', $key, 'error', $seq, $config, null, $e->getMessage(), $stepStart);
                $status = 'failed';
                $error = $e->getMessage();
                break;
            }
        }

        $run->update([
            'status'      => $status,
            'context'     => $this->encode($working),
            'error'       => $error,
            'ended_at'    => now(),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        return $run;
    }

    /**
     * @param array<string,mixed> $config
     * @param array<string,mixed>|null $output
     */
    private function recordStep(int $runId, string $type, string $key, string $status, int $seq, array $config, ?array $output, ?string $error, float $stepStart): void
    {
        AutomationRunStep::create([
            'run_id'      => $runId,
            'step_type'   => $type,
            'step_key'    => $key,
            'status'      => $status,
            'sequence'    => $seq,
            'input'       => $this->encode($config),
            'output'      => $output !== null ? $this->encode($output) : null,
            'error'       => $error,
            'duration_ms' => (int) round((microtime(true) - $stepStart) * 1000),
            'created_at'  => now(),
        ]);
    }

    /** @param array<string,mixed> $value */
    private function encode(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
    }

    /** @return array<string,mixed> */
    private function decode(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }
}
