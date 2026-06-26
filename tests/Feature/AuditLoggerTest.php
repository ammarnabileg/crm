<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Audit logger writes to activity_log and captures old/new values for changes
 * (docs/47 EAS-7, docs/38).
 */
return new class extends TestCase {
    protected bool $useDatabaseTransaction = true;

    public function setUp(): void
    {
        $companyId = (int) app('db')->table('companies')->orderBy('id')->value('id');
        tenant()->setById($companyId);
    }

    public function test_log_writes_an_entry(): void
    {
        $before = app('db')->table('activity_log')->where('action', '=', 'test.audited')->count();
        audit()->log('test.audited', [
            'user_id'     => 1,
            'description' => 'A test action',
            'subject_type' => 'thing',
            'subject_id'  => 99,
        ]);
        $after = app('db')->table('activity_log')->where('action', '=', 'test.audited')->count();

        $this->assertSame($before + 1, $after);
    }

    public function test_log_change_stores_only_changed_values(): void
    {
        audit()->logChange(
            'test.changed',
            ['name' => 'Old', 'unchanged' => 'same'],
            ['name' => 'New', 'unchanged' => 'same'],
            ['user_id' => 1]
        );

        $row = app('db')->table('activity_log')
            ->where('action', '=', 'test.changed')
            ->orderBy('id', 'desc')
            ->first();

        $this->assertNotNull($row);
        $old = json_decode((string) $row['old_values'], true);
        $new = json_decode((string) $row['new_values'], true);

        $this->assertSame(['name' => 'Old'], $old);
        $this->assertSame(['name' => 'New'], $new);
    }

    public function test_log_records_tenant_and_actor(): void
    {
        $userId = (int) app('db')->table('users')->orderBy('id')->value('id');
        audit()->log('test.context', ['user_id' => $userId]);
        $row = app('db')->table('activity_log')
            ->where('action', '=', 'test.context')
            ->orderBy('id', 'desc')
            ->first();

        $this->assertSame((int) tenant()->id(), (int) $row['company_id']);
        $this->assertSame($userId, (int) $row['user_id']);
    }
};
