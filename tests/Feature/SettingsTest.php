<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Settings + feature flags against a real tenant and the settings table. Runs in
 * a rolled-back DB transaction; the cache is flushed in setUp so values do not
 * leak between tests (docs/47 EAS-9).
 */
return new class extends TestCase {
    protected bool $useDatabaseTransaction = true;

    public function setUp(): void
    {
        // Use the first company as the active tenant for these tests.
        $companyId = (int) app('db')->table('companies')->orderBy('id')->value('id');
        tenant()->setById($companyId);
        app('cache')->flush();
    }

    public function test_get_returns_config_default_when_not_set(): void
    {
        $this->assertSame('en', settings()->get('locale'));
        $this->assertSame('SAR', settings()->get('currency'));
    }

    public function test_get_returns_caller_default_for_unknown_key(): void
    {
        $this->assertSame('xyz', settings()->get('nope.unknown', 'xyz'));
    }

    public function test_set_then_get_returns_stored_value(): void
    {
        settings()->set('brand.name', 'Acme Brand');
        $this->assertSame('Acme Brand', settings()->get('brand.name'));
        $this->assertTrue(settings()->has('brand.name'));
    }

    public function test_set_stores_array_value(): void
    {
        settings()->set('hiring.stages', ['screening', 'interview', 'offer']);
        $this->assertSame(['screening', 'interview', 'offer'], settings()->get('hiring.stages'));
    }

    public function test_forget_removes_value(): void
    {
        settings()->set('temp.key', 'v');
        settings()->forget('temp.key');
        $this->assertFalse(settings()->has('temp.key'));
    }

    public function test_feature_flag_defaults_from_config(): void
    {
        $this->assertTrue(app('features')->enabled('jobs'));
        $this->assertFalse(app('features')->enabled('api'));
    }

    public function test_feature_flag_can_be_toggled_per_tenant(): void
    {
        app('features')->disable('jobs');
        $this->assertFalse(app('features')->enabled('jobs'));
        app('features')->enable('api');
        $this->assertTrue(app('features')->enabled('api'));
    }

    public function test_settings_require_a_tenant(): void
    {
        tenant()->clear();
        $this->assertThrows(static fn () => settings()->get('locale'), \RuntimeException::class);
    }
};
