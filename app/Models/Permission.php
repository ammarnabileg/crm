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

    protected static array $fillable = ['key', 'name', 'group', 'description'];

    /**
     * All permissions keyed by their group, for the role-editor matrix.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function grouped(): array
    {
        $rows = static::query()->orderBy('group')->orderBy('name')->get();
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['group']][] = $row;
        }

        return $grouped;
    }

    public static function idFor(string $key): ?int
    {
        $id = static::query()->where('key', '=', $key)->value('id');

        return $id !== null ? (int) $id : null;
    }
}
