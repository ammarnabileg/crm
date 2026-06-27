<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Controllers\App\SettingsController;
use App\Core\Request;
use App\DTOs\RegisterUserData;
use App\Services\Auth\RegistrationService;
use Tests\TestCase;

/**
 * Workspace Settings web layer (docs/47 EAS-9) — the tabbed settings screen
 * renders end to end (controller -> SettingsManager -> view -> layout) and each
 * section persists through settings(). The screen is gated by settings.view /
 * settings.manage, so the test provisions a fresh Owner (the '*' role grants every
 * permission) and signs them in; without that, the controller's permission gate
 * would 403.
 *
 * Isolation mirrors AtsWebTest exactly: each test runs in a rolled-back DB
 * transaction, and tearDown resets the AuthManager's resolution + the
 * AccessControl per-request cache on the existing singletons (preserving policy
 * gates) so later session-auth tests re-resolve cleanly.
 */
return new class extends TestCase {
    protected bool $useDatabaseTransaction = true;

    public function setUp(): void
    {
        // A bound request is needed before login() (it records the sign-in IP) and
        // by the layout (it calls request()->path()).
        $this->request();

        // Provision a brand-new Owner + workspace, activate the tenant, and sign in
        // so can('settings.view'/'settings.manage') resolves true for this user.
        $result = app(RegistrationService::class)->register(RegisterUserData::fromArray([
            'name'           => 'Settings Owner',
            'email'          => 'settings.owner@example.com',
            'password'       => 'StrongPass!234',
            'workspace_name' => 'Settings Co',
            'locale'         => 'en',
        ]));

        tenant()->setTenant($result['workspace']);
        app('cache')->flush(); // settings cache must not carry across tests
        auth()->login($result['user']);
    }

    /**
     * Rendering touches auth() (the AuthManager resolves the user once and caches
     * "resolved"). Reset that resolution + the AccessControl per-request cache on
     * the EXISTING singletons (preserving registered policy gates) so later
     * session-auth tests re-resolve cleanly — test isolation, no leak.
     */
    public function tearDown(): void
    {
        auth()->logout(); // drop the signed-in user id from the session

        $auth = app('auth');
        $r = new \ReflectionObject($auth);
        foreach (['resolved' => false, 'user' => null] as $prop => $value) {
            if ($r->hasProperty($prop)) {
                $p = $r->getProperty($prop);
                $p->setAccessible(true);
                $p->setValue($auth, $value);
            }
        }

        $access = app('access');
        $ra = new \ReflectionObject($access);
        if ($ra->hasProperty('cache')) {
            $p = $ra->getProperty('cache');
            $p->setAccessible(true);
            $p->setValue($access, []);
        }
    }

    /**
     * @param array<string,mixed> $query
     * @param array<string,mixed> $post
     */
    private function request(array $query = [], array $post = []): Request
    {
        $method = $post === [] ? 'GET' : 'POST';
        $req = new Request($query, $post, ['REQUEST_METHOD' => $method, 'REQUEST_URI' => '/'], [], []);
        app()->instance('request', $req); // the layout calls request()->path()

        return $req;
    }

    public function test_index_renders_with_all_section_tabs(): void
    {
        $res = (new SettingsController())->index($this->request());
        $this->assertSame(200, $res->getStatus());

        $content = $res->getContent();
        $this->assertTrue(str_contains($content, 'Workspace Settings'));
        // Every section tab is present (the component('tabs') labels).
        foreach (['General', 'Company', 'Branding', 'Localization', 'Email', 'Security'] as $label) {
            $this->assertTrue(str_contains($content, $label), "Missing settings tab: {$label}");
        }
        // The graceful-degradation note is shown while mail is disabled by default.
        $this->assertTrue(str_contains($content, 'storage/logs'));
    }

    public function test_update_general_section_persists_via_settings(): void
    {
        $res = (new SettingsController())->update($this->request([], [
            'section'             => 'general',
            'general.app_name'    => 'Renamed Workspace',
            'general.timezone'    => 'Europe/London',
            'general.date_format' => 'd/m/Y',
        ]));

        // Thin controller flashes + redirects back to the settings screen.
        $this->assertSame(302, $res->getStatus());

        // Values are now stored against the tenant.
        $this->assertSame('Renamed Workspace', settings()->get('general.app_name'));
        $this->assertSame('Europe/London', settings()->get('general.timezone'));
        $this->assertSame('d/m/Y', settings()->get('general.date_format'));
    }

    public function test_submitted_smtp_password_is_not_echoed_in_rendered_html(): void
    {
        $secret = 'S3cr3t-SMTP-PaΣΣword-9090';

        $update = (new SettingsController())->update($this->request([], [
            'section'            => 'email',
            'mail.enabled'       => '1',
            'mail.from_address'  => 'ops@settings.test',
            'mail.from_name'     => 'Settings Ops',
            'mail.smtp_host'     => 'smtp.settings.test',
            'mail.smtp_port'     => '587',
            'mail.smtp_username' => 'mailer',
            'mail.smtp_password' => $secret,
        ]));
        $this->assertSame(302, $update->getStatus());

        // The secret was persisted (so it is genuinely stored)...
        $this->assertSame($secret, settings()->get('mail.smtp_password'));

        // ...but it must NEVER appear in the re-rendered settings HTML.
        $content = (new SettingsController())->index($this->request())->getContent();
        $this->assertFalse(
            str_contains($content, $secret),
            'The stored SMTP password leaked into the rendered settings HTML.'
        );
        // The page should instead advertise that a password is configured.
        $this->assertTrue(str_contains($content, 'configured'));
    }
};
