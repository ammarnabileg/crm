<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * A single permission in the global catalogue. Grouped for the role editor UI.
 */
final class Permission extends Model
{
    protected static string $table = 'permissions';
    protected static bool $tenantScoped = false;

    protected static array $fillable = ['key', 'module_id', 'action', 'name', 'description'];

    /**
     * All permissions keyed by their MODULE label, for the role-editor matrix.
     * The module is the permission group (docs/database/12 R1-03); modules are
     * listed in their configured sort order and empty modules are omitted.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function grouped(): array
    {
        $db = app('db');

        $grouped = [];
        $moduleLabels = [];
        foreach ($db->table('system_modules')->orderBy('sort_order')->get() as $module) {
            $grouped[$module['label']] = [];
            $moduleLabels[(int) $module['id']] = $module['label'];
        }

        foreach (static::query()->orderBy('name')->get() as $row) {
            $label = $moduleLabels[(int) $row['module_id']] ?? 'General';
            $grouped[$label][] = $row;
        }

        return array_filter($grouped, static fn (array $rows): bool => $rows !== []);
    }

    public static function idFor(string $key): ?int
    {
        $id = static::query()->where('key', '=', $key)->value('id');

        return $id !== null ? (int) $id : null;
    }
}
