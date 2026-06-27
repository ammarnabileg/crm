<?php

declare(strict_types=1);

namespace App\Controllers\App;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\ActivityLog;
use App\Models\Automation;
use App\Services\Automation\AutomationBuilder;
use App\Services\Automation\AutomationDirectory;
use Throwable;

/**
 * Workflow Automation web layer (docs/51) — the reachable UI over the existing
 * Automation Engine. Build "when X happens → (conditions) → do Y" rules without a
 * terminal: a TRIGGER event bound to ordered condition + action steps.
 *
 * Reads are gated by `automation.view`, writes by `automation.manage`. Everything is
 * tenant-scoped (AutomationDirectory filters by the active workspace; a foreign id
 * 404s) and writes delegate to the existing AutomationBuilder (no raw step writes),
 * so the engine's validation/versioning stays the single source of truth.
 */
final class AutomationController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless(can('automation.view'), 403);

        return $this->view('app.automations.index', [
            'title'       => 'Automations',
            'automations' => (new AutomationDirectory((int) tenant()->id()))->all(),
            'triggers'    => (array) config('automation.triggers', []),
            'conditions'  => (array) config('automation.conditions', []),
            'actions'     => (array) config('automation.actions', []),
            'canManage'   => can('automation.manage'),
        ]);
    }

    public function show(Request $request): Response
    {
        abort_unless(can('automation.view'), 403);

        $automation = (new AutomationDirectory((int) tenant()->id()))->find((int) $request->query('id', 0));
        abort_unless($automation !== null, 404);

        return $this->view('app.automations.show', [
            'title'      => $automation['name'] . ' · Automation',
            'automation' => $automation,
            'canManage'  => can('automation.manage'),
        ]);
    }

    public function store(Request $request): Response
    {
        abort_unless(can('automation.manage'), 403);

        $data = $this->validate($request, [
            'name'          => 'required|max:120',
            'trigger_event' => 'required',
        ]);

        $errors = [];
        $triggers = (array) config('automation.triggers', []);
        if (! in_array($data['trigger_event'], $triggers, true)) {
            $errors[] = 'Choose a valid trigger event.';
        }

        [$steps, $stepErrors] = $this->parseSteps($request);
        $errors = array_merge($errors, $stepErrors);
        if ($steps === [] && $stepErrors === []) {
            $errors[] = 'Add at least one step (a condition or an action).';
        }

        if ($errors !== []) {
            session()->flash('errors', ['automation' => $errors]);
            session()->flashInput($request->all());

            return $this->redirect(url('automations'));
        }

        try {
            $automation = (new AutomationBuilder())->create(
                trim((string) $data['name']),
                (string) $data['trigger_event'],
                $steps,
                (int) auth()->id(),
            );
        } catch (Throwable $e) {
            session()->flash('errors', ['automation' => [$e->getMessage()]]);
            session()->flashInput($request->all());

            return $this->redirect(url('automations'));
        }

        ActivityLog::record(
            'automation.created',
            (int) tenant()->id(),
            (int) auth()->id(),
            'Created automation "' . $automation->getAttribute('name') . '".',
            ['trigger' => $data['trigger_event'], 'steps' => count($steps)],
            'automation',
            (int) $automation->getKey(),
        );
        $this->withSuccess('Automation created.');

        return $this->redirect(url('automations/show?id=' . (int) $automation->getKey()));
    }

    public function toggle(Request $request): Response
    {
        abort_unless(can('automation.manage'), 403);

        $automation = $this->ownedAutomation((int) $request->input('id'));
        $active = ! (bool) $automation->getAttribute('is_active');
        $automation->update(['is_active' => $active ? 1 : 0]);

        ActivityLog::record(
            'automation.' . ($active ? 'activated' : 'deactivated'),
            (int) tenant()->id(),
            (int) auth()->id(),
            ($active ? 'Activated' : 'Deactivated') . ' automation "' . $automation->getAttribute('name') . '".',
            [],
            'automation',
            (int) $automation->getKey(),
        );
        $this->withSuccess($active ? 'Automation activated.' : 'Automation deactivated.');

        return $this->redirect(url('automations/show?id=' . (int) $automation->getKey()));
    }

    public function publish(Request $request): Response
    {
        abort_unless(can('automation.manage'), 403);

        $automation = $this->ownedAutomation((int) $request->input('id'));
        try {
            $version = (new AutomationBuilder())->publish((int) $automation->getKey(), (int) auth()->id());
            $this->withSuccess('Published version ' . $version->getAttribute('version') . '.');
        } catch (Throwable $e) {
            $this->withError('Could not publish: ' . $e->getMessage());
        }

        return $this->redirect(url('automations/show?id=' . (int) $automation->getKey()));
    }

    public function destroy(Request $request): Response
    {
        abort_unless(can('automation.manage'), 403);

        $automation = $this->ownedAutomation((int) $request->input('id'));
        $name = (string) $automation->getAttribute('name');
        $id = (int) $automation->getKey();
        $automation->delete();

        ActivityLog::record('automation.deleted', (int) tenant()->id(), (int) auth()->id(), 'Deleted automation "' . $name . '".', [], 'automation', $id);
        $this->withSuccess('Automation deleted.');

        return $this->redirect(url('automations'));
    }

    /**
     * Load a tenant-owned automation or 404 (the cross-tenant guard — the model is
     * tenant-scoped, so a foreign id resolves to null).
     */
    private function ownedAutomation(int $id): Automation
    {
        $automation = $id > 0 ? Automation::find($id) : null;
        abort_unless($automation !== null, 404);

        return $automation;
    }

    /**
     * Parse the repeated step rows from the form into the shape AutomationBuilder
     * expects ([{type,key,config[]}]), validating each against the configured keys
     * and decoding the optional JSON config. Empty rows are skipped.
     *
     * @return array{0: array<int,array<string,mixed>>, 1: string[]}
     */
    private function parseSteps(Request $request): array
    {
        $rows = (array) $request->input('steps', []);
        $allowed = [
            'condition' => (array) config('automation.conditions', []),
            'action'    => (array) config('automation.actions', []),
        ];

        $steps = [];
        $errors = [];
        foreach (array_values($rows) as $i => $raw) {
            if (! is_array($raw)) {
                continue;
            }
            // Each row's operation is a single "type:key" value (e.g. "action:notify")
            // so the type and key can never be mismatched from the UI.
            $op = (string) ($raw['op'] ?? '');
            if ($op === '') {
                continue; // an empty row the user left blank
            }
            [$type, $key] = array_pad(explode(':', $op, 2), 2, '');

            $human = 'Step ' . ($i + 1);
            if (! isset($allowed[$type])) {
                $errors[] = "{$human}: choose a step type.";
                continue;
            }
            if (! in_array($key, $allowed[$type], true)) {
                $errors[] = "{$human}: choose a valid {$type}.";
                continue;
            }

            $config = [];
            $configRaw = trim((string) ($raw['config'] ?? ''));
            if ($configRaw !== '') {
                $decoded = json_decode($configRaw, true);
                if (! is_array($decoded)) {
                    $errors[] = "{$human}: the configuration must be valid JSON (e.g. {\"message\": \"Hi\"}).";
                    continue;
                }
                $config = $decoded;
            }

            $steps[] = ['type' => $type, 'key' => $key, 'config' => $config];
        }

        return [$steps, $errors];
    }
}
