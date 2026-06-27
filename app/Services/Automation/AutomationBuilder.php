<?php

declare(strict_types=1);

namespace App\Services\Automation;

use App\Models\Automation;
use App\Models\AutomationStep;
use App\Models\AutomationVersion;
use RuntimeException;

/**
 * Builds and versions automations (docs/51). `create()` validates the trigger and
 * persists the rule + its ordered condition/action steps. `publish()` snapshots an
 * immutable version (compare/rollback/restore); `rollback()` restores a prior
 * version's snapshot as a new active version; `clone()` duplicates a rule.
 * Tenant-scoped through the Model layer.
 */
final class AutomationBuilder
{
    /**
     * @param array<int, array{type:string, key:string, config?:array<string,mixed>}> $steps
     */
    public function create(string $name, string $triggerEvent, array $steps, ?int $userId = null): Automation
    {
        $triggers = (array) config('automation.triggers', []);
        if (! in_array($triggerEvent, $triggers, true)) {
            throw new RuntimeException("Unknown trigger event '{$triggerEvent}'.");
        }
        foreach ($steps as $i => $step) {
            $type = $step['type'] ?? '';
            if ($type !== 'condition' && $type !== 'action') {
                throw new RuntimeException("Step #{$i} has an invalid type '{$type}'.");
            }
            if (($step['key'] ?? '') === '') {
                throw new RuntimeException("Step #{$i} is missing a key.");
            }
        }

        $automation = Automation::create([
            'name'          => $name,
            'slug'          => $this->uniqueSlug($name),
            'trigger_event' => $triggerEvent,
            'is_active'     => 1,
            'version'       => 0,
            'created_by'    => $userId,
        ]);
        $automationId = (int) $automation->getKey();

        $sort = 0;
        foreach ($steps as $step) {
            AutomationStep::create([
                'automation_id' => $automationId,
                'step_type'     => $step['type'],
                'key'           => $step['key'],
                'config'        => isset($step['config'])
                    ? json_encode($step['config'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                    : null,
                'sort_order'    => $sort++,
            ]);
        }

        return $automation;
    }

    /** Publish an immutable snapshot of the automation + its steps as the new active version. */
    public function publish(int $automationId, ?int $userId = null, ?string $notes = null): AutomationVersion
    {
        $automation = Automation::find($automationId);
        if ($automation === null) {
            throw new RuntimeException("Automation {$automationId} not found in this workspace.");
        }

        $snapshot = $this->snapshot($automation);

        return $this->writeVersion($automationId, $snapshot, $userId, $notes);
    }

    /**
     * Restore a prior version's snapshot as a new active version (rollback/restore).
     */
    public function rollback(int $automationId, int $targetVersion, ?int $userId = null): AutomationVersion
    {
        $target = AutomationVersion::query()
            ->where('automation_id', '=', $automationId)
            ->where('version', '=', $targetVersion)
            ->first();
        if ($target === null) {
            throw new RuntimeException("Version {$targetVersion} not found for automation {$automationId}.");
        }

        $snapshot = $target['snapshot'];
        if (is_string($snapshot)) {
            $snapshot = json_decode($snapshot, true) ?: [];
        }

        return $this->writeVersion($automationId, (array) $snapshot, $userId, "Rollback to v{$targetVersion}");
    }

    /** Duplicate an automation (and its steps) into a new inactive draft. */
    public function clone(int $automationId, ?string $newName = null, ?int $userId = null): Automation
    {
        $source = Automation::find($automationId);
        if ($source === null) {
            throw new RuntimeException("Automation {$automationId} not found in this workspace.");
        }

        $steps = [];
        foreach ($source->steps() as $row) {
            $config = $row['config'] ?? null;
            if (is_string($config)) {
                $config = json_decode($config, true);
            }
            $steps[] = [
                'type'   => (string) $row['step_type'],
                'key'    => (string) $row['key'],
                'config' => is_array($config) ? $config : [],
            ];
        }

        return $this->create(
            $newName ?? ((string) $source->name . ' (copy)'),
            (string) $source->trigger_event,
            $steps,
            $userId
        );
    }

    /** @return array<string,mixed> */
    private function snapshot(Automation $automation): array
    {
        $steps = [];
        foreach ($automation->steps() as $row) {
            $config = $row['config'] ?? null;
            if (is_string($config)) {
                $config = json_decode($config, true);
            }
            $steps[] = [
                'step_type' => (string) $row['step_type'],
                'key'       => (string) $row['key'],
                'config'    => is_array($config) ? $config : [],
                'sort_order' => (int) $row['sort_order'],
            ];
        }

        return [
            'name'          => (string) $automation->name,
            'trigger_event' => (string) $automation->trigger_event,
            'steps'         => $steps,
        ];
    }

    /** @param array<string,mixed> $snapshot */
    private function writeVersion(int $automationId, array $snapshot, ?int $userId, ?string $notes): AutomationVersion
    {
        $latest = AutomationVersion::query()
            ->where('automation_id', '=', $automationId)
            ->orderBy('version', 'desc')
            ->first();
        $next = $latest !== null ? ((int) $latest['version']) + 1 : 1;

        AutomationVersion::query()
            ->where('automation_id', '=', $automationId)
            ->where('is_active', '=', 1)
            ->update(['is_active' => 0, 'updated_at' => now()]);

        $version = AutomationVersion::create([
            'automation_id' => $automationId,
            'version'       => $next,
            'snapshot'      => json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'notes'         => $notes,
            'is_active'     => 1,
            'published_by'  => $userId,
            'published_at'  => now(),
        ]);

        $automation = Automation::find($automationId);
        if ($automation !== null) {
            $automation->update(['version' => $next]);
        }

        return $version;
    }

    private function uniqueSlug(string $name): string
    {
        $base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)) ?? '', '-');
        if ($base === '') {
            $base = 'automation';
        }
        $slug = $base;
        $n = 2;
        while (Automation::withTrashed()->where('slug', '=', $slug)->first() !== null) {
            $slug = $base . '-' . $n;
            $n++;
        }

        return $slug;
    }
}
