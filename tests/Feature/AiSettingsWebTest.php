<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Controllers\App\AiSettingsController;
use App\Core\Request;
use App\Models\AiCredential;
use App\Models\User;
use App\Services\Tenancy\WorkspaceService;
use Tests\TestCase;

/**
 * AI Settings web layer (docs/51) — each company manages its own provider keys
 * (stored encrypted in tenant_ai_keys via AiCredential) and the per-tenant engine
 * defaults (via the settings store). The page renders end to end (controller →
 * model/settings → view → layout) and, crucially, works with ZERO keys (the empty
 * state). Secrets are NEVER rendered — only a masked hint. Reads are gated by
 * ai.view, writes by ai.manage, enforced inside the controller via
 * abort_unless(can(...)).
 *
 * Fixtures are built fresh inside a rolled-back transaction (mirrors MembersWebTest):
 * a brand-new workspace gives its creator the Owner role (every tenant permission),
 * and the creator is authenticated via the session so the RBAC gates resolve for
 * real. Test isolation mirrors AtsWebTest exactly (reflection-reset of the auth
 * resolution + the AccessControl per-request cache on the existing singletons).
 */
return new class extends TestCase {
    protected bool $useDatabaseTransaction = true;

    private int $workspaceId = 0;
    private int $ownerId = 0;

    public function setUp(): void
    {
        $owner = User::create([
            'name'           => 'AI Settings Owner',
            'email'          => 'owner-' . uniqid() . '@ai.test',
            'password'       => 'x',
            'user_status_id' => lookup_id('user_status', 'active'),
        ]);
        $this->ownerId = (int) $owner->getKey();

        $workspace = (new WorkspaceService())->create($owner, 'AI Settings Co');
        $this->workspaceId = (int) $workspace->getKey();
        tenant()->setById($this->workspaceId);

        // Authenticate the owner so the controller's can() gates resolve.
        session()->put((string) config('auth.session_key', 'auth_user_id'), $this->ownerId);
    }

    /**
     * Rendering touches auth() (the AuthManager resolves the user once and caches
     * "resolved"). Reset that resolution + the AccessControl per-request cache on
     * the EXISTING singletons (preserving registered policy gates) so later
     * session-auth tests re-resolve cleanly — test isolation, no leak.
     */
    public function tearDown(): void
    {
        session()->forget((string) config('auth.session_key', 'auth_user_id'));

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

    private function request(array $query = [], array $body = []): Request
    {
        $method = $body === [] ? 'GET' : 'POST';
        $req = new Request($query, $body, ['REQUEST_METHOD' => $method, 'REQUEST_URI' => '/'], [], []);
        app()->instance('request', $req); // the layout calls request()->path()

        return $req;
    }

    public function test_index_renders_empty_state_with_zero_keys(): void
    {
        // No keys configured for this fresh tenant — the page must still render.
        $res = (new AiSettingsController())->index($this->request());

        $this->assertSame(200, $res->getStatus());
        $content = $res->getContent();
        $this->assertTrue(str_contains($content, 'AI Settings'));
        $this->assertTrue(str_contains($content, 'No AI providers connected'));
    }

    public function test_index_renders_with_a_configured_key(): void
    {
        $this->makeKey('anthropic', 'sk-secret-anthropic-123', true);

        $res = (new AiSettingsController())->index($this->request());

        $this->assertSame(200, $res->getStatus());
        $content = $res->getContent();
        // The provider row is shown with its masked-key hint…
        $this->assertTrue(str_contains($content, 'anthropic'));
        $this->assertTrue(str_contains($content, 'sk-s')); // masked hint prefix
        // …and the empty-state copy is gone.
        $this->assertFalse(str_contains($content, 'No AI providers connected'));
    }

    public function test_index_never_renders_the_decrypted_secret(): void
    {
        $secret = 'sk-supersecret-openai-value-987654321';
        $this->makeKey('openai', $secret, false);

        $res = (new AiSettingsController())->index($this->request());
        $content = $res->getContent();

        // The raw secret must NEVER reach the page — only a masked hint.
        $this->assertFalse(str_contains($content, $secret));
        // The masked form IS present (first 4 chars + bullets).
        $this->assertTrue(str_contains($content, 'sk-s'));
        $this->assertTrue(str_contains($content, '•'));
    }

    public function test_store_key_creates_an_encrypted_credential(): void
    {
        $res = (new AiSettingsController())->storeKey($this->request([], [
            'provider'   => 'openai',
            'api_key'    => 'sk-new-key-from-form',
            'label'      => 'Form OpenAI',
            'is_default' => '1',
        ]));

        $this->assertSame(302, $res->getStatus());

        $credential = AiCredential::findBy('provider', 'openai');
        $this->assertNotNull($credential);
        $this->assertTrue((bool) $credential->is_default);
        // The stored value decrypts back to the secret (so it was encrypted at rest).
        $this->assertSame('sk-new-key-from-form', $credential->secrets()['api_key'] ?? null);
    }

    public function test_delete_key_soft_deletes_it(): void
    {
        $key = $this->makeKey('gemini', 'sk-gemini-bye', false);
        $id = (string) $key->getKey();

        $res = (new AiSettingsController())->deleteKey($this->request([], ['id' => $id]));

        $this->assertSame(302, $res->getStatus());
        $this->assertNull(AiCredential::find($id)); // gone from the default (non-trashed) query
    }

    public function test_update_defaults_persists_via_settings(): void
    {
        $res = (new AiSettingsController())->updateDefaults($this->request([], [
            'default_provider'    => 'anthropic',
            'default_model'       => 'claude-3-7-sonnet',
            'temperature'         => '0.4',
            'max_tokens'          => '1500',
            'language'            => 'ar',
            'cost_limit'          => '250',
            'provider_priority'   => 'anthropic,openai',
            'interview_duration'  => '45',
            'interview_questions' => '10',
        ]));

        $this->assertSame(302, $res->getStatus());

        // Persisted through the per-tenant settings store.
        $this->assertSame('anthropic', settings()->get('ai.default_provider'));
        $this->assertSame('claude-3-7-sonnet', settings()->get('ai.default_model'));
        $this->assertSame('ar', settings()->get('ai.language'));
        $this->assertSame('anthropic,openai', settings()->get('ai.provider_priority'));
        // Numeric values round-trip as scalars.
        $this->assertSame('1500', (string) settings()->get('ai.max_tokens'));
        $this->assertSame('45', (string) settings()->get('ai.interview.duration'));
    }

    // --- helpers ------------------------------------------------------------

    private function makeKey(string $provider, string $apiKey, bool $default): AiCredential
    {
        return AiCredential::create([
            'provider'    => $provider,
            'label'       => $provider . ' key',
            'credentials' => AiCredential::encryptSecrets(['api_key' => $apiKey]),
            'is_active'   => 1,
            'is_default'  => $default ? 1 : 0,
        ]);
    }
};
