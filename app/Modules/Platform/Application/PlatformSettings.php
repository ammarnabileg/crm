<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Platform\Application;

use HaHireAI\Core\Contracts\SupportInfo;
use HaHireAI\Core\Database\Connection;
use HaHireAI\Shared\Ulid;

/**
 * Platform-wide settings for the System Owner (e.g. support contact shown on
 * suspended workspaces). Stored as JSON key/value in the global `settings` table.
 * Exposes the support contact via the SupportInfo contract (ARCHITECTURE.md §4).
 */
final class PlatformSettings implements SupportInfo
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $row = $this->connection->selectOne('SELECT `value` FROM settings WHERE `key` = ?', [$key]);
        if ($row === null || $row['value'] === null) {
            return $default;
        }

        $decoded = json_decode((string) $row['value'], true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return is_scalar($decoded) ? (string) $decoded : $default;
        }

        return (string) $row['value'];
    }

    public function set(string $key, string $value): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $existing = $this->connection->selectOne('SELECT id FROM settings WHERE `key` = ?', [$key]);

        if ($existing !== null) {
            $this->connection->statement('UPDATE settings SET `value` = ?, updated_at = ? WHERE id = ?', [$encoded, $now, (string) $existing['id']]);

            return;
        }

        $this->connection->statement(
            'INSERT INTO settings (id, `key`, `value`, created_at, updated_at) VALUES (?, ?, ?, ?, ?)',
            [Ulid::generate(), $key, $encoded, $now, $now],
        );
    }

    /**
     * The support contact shown to members of a suspended workspace.
     *
     * @return array{email: string, url: string, phone: string, message: string}
     */
    public function support(): array
    {
        return [
            'email' => (string) $this->get('support.email', ''),
            'url' => (string) $this->get('support.url', ''),
            'phone' => (string) $this->get('support.phone', ''),
            'message' => (string) $this->get('support.message', ''),
        ];
    }
}
