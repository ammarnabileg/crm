<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Infrastructure\Http\FakeHttpClient;
use App\Services\AI\AiGateway;
use App\Services\AI\AiPrompt;
use App\Services\AI\Providers\AzureOpenAiProvider;
use App\Services\AI\Providers\ClaudeProvider;
use App\Services\AI\Providers\DeepSeekProvider;
use App\Services\AI\Providers\GeminiProvider;
use App\Services\AI\Providers\OpenAiProvider;
use Tests\TestCase;

/**
 * AI provider integration (Phase 16) — the real OpenAI/Claude/Gemini/DeepSeek/Azure
 * adapters plug into the existing AiProvider contract + AiGateway. Every call goes
 * through a FakeHttpClient so the AI-validation matrix runs OFFLINE: success, request
 * shape, provider switching/fallback, invalid/expired key, rate limit, timeout,
 * network failure, large prompt/response. Adapters must NEVER throw — every failure
 * is an AiResult::failure() the gateway can fall back on.
 */
return new class extends TestCase {
    protected bool $useDatabaseTransaction = true;

    private function prompt(string $user = 'Hello'): AiPrompt
    {
        return new AiPrompt([
            ['role' => 'system', 'content' => 'You are a recruiter.', 'trusted' => true],
            ['role' => 'user', 'content' => $user],
        ], 'chat', ['model' => 'gpt-4o-mini']);
    }

    public function test_openai_success_parses_text_tokens_and_request_shape(): void
    {
        $http = (new FakeHttpClient())->pushResponse(200, (string) json_encode([
            'model' => 'gpt-4o-mini',
            'choices' => [['message' => ['content' => 'Hi there']]],
            'usage' => ['prompt_tokens' => 11, 'completion_tokens' => 3],
        ]));
        $r = (new OpenAiProvider($http))->complete($this->prompt(), ['api_key' => 'sk-test']);

        $this->assertTrue($r->ok);
        $this->assertSame('Hi there', $r->text);
        $this->assertSame('openai', $r->provider);
        $this->assertSame(11, $r->inputTokens);
        $this->assertSame(3, $r->outputTokens);

        $req = $http->lastRequest();
        $this->assertSame('https://api.openai.com/v1/chat/completions', $req['url']);
        $this->assertTrue(in_array('Authorization: Bearer sk-test', $req['headers'], true));
        $this->assertTrue(str_contains((string) $req['body'], '"model":"gpt-4o-mini"'));
    }

    public function test_missing_api_key_fails_without_an_http_call(): void
    {
        $http = new FakeHttpClient();
        $r = (new OpenAiProvider($http))->complete($this->prompt(), []);
        $this->assertFalse($r->ok);
        $this->assertSame('missing_api_key', $r->error);
        $this->assertCount(0, $http->requests);
    }

    public function test_invalid_or_expired_key_maps_401_403(): void
    {
        foreach ([401, 403] as $status) {
            $http = (new FakeHttpClient())->pushResponse($status, '{"error":{"message":"bad key"}}');
            $r = (new OpenAiProvider($http))->complete($this->prompt(), ['api_key' => 'x']);
            $this->assertSame('invalid_api_key', $r->error, "status {$status}");
        }
    }

    public function test_rate_limit_timeout_network_and_upstream_map_cleanly(): void
    {
        $r1 = (new OpenAiProvider((new FakeHttpClient())->pushResponse(429, '')))->complete($this->prompt(), ['api_key' => 'x']);
        $this->assertSame('rate_limited', $r1->error);

        $r2 = (new OpenAiProvider((new FakeHttpClient())->pushTransportError('timeout')))->complete($this->prompt(), ['api_key' => 'x']);
        $this->assertSame('timeout', $r2->error);

        $r3 = (new OpenAiProvider((new FakeHttpClient())->pushTransportError('network_error')))->complete($this->prompt(), ['api_key' => 'x']);
        $this->assertSame('network_error', $r3->error);

        $r4 = (new OpenAiProvider((new FakeHttpClient())->pushResponse(503, '')))->complete($this->prompt(), ['api_key' => 'x']);
        $this->assertTrue(str_contains((string) $r4->error, 'upstream_error'));
    }

    public function test_claude_success_and_request_shape(): void
    {
        $http = (new FakeHttpClient())->pushResponse(200, (string) json_encode([
            'model' => 'claude-3-5-sonnet-latest',
            'content' => [['type' => 'text', 'text' => 'Bonjour']],
            'usage' => ['input_tokens' => 5, 'output_tokens' => 2],
        ]));
        $r = (new ClaudeProvider($http))->complete($this->prompt(), ['api_key' => 'k']);

        $this->assertTrue($r->ok);
        $this->assertSame('Bonjour', $r->text);
        $this->assertSame('anthropic', $r->provider);
        $req = $http->lastRequest();
        $this->assertSame('https://api.anthropic.com/v1/messages', $req['url']);
        $this->assertTrue(in_array('x-api-key: k', $req['headers'], true));
        $this->assertTrue(str_contains((string) $req['body'], '"system":"You are a recruiter."'));
        $this->assertTrue(str_contains((string) $req['body'], '"max_tokens"'));
    }

    public function test_gemini_success_and_key_in_query(): void
    {
        $http = (new FakeHttpClient())->pushResponse(200, (string) json_encode([
            'candidates' => [['content' => ['parts' => [['text' => 'Hola']]]]],
            'usageMetadata' => ['promptTokenCount' => 4, 'candidatesTokenCount' => 1],
        ]));
        $r = (new GeminiProvider($http))->complete($this->prompt(), ['api_key' => 'gk']);

        $this->assertTrue($r->ok);
        $this->assertSame('Hola', $r->text);
        $this->assertSame('gemini', $r->provider);
        $this->assertTrue(str_contains($http->lastRequest()['url'], 'key=gk'));
        $this->assertTrue(str_contains($http->lastRequest()['url'], 'generateContent'));
    }

    public function test_deepseek_reuses_openai_shape_with_its_own_endpoint(): void
    {
        $http = (new FakeHttpClient())->pushResponse(200, (string) json_encode(['choices' => [['message' => ['content' => 'ok']]], 'usage' => []]));
        $r = (new DeepSeekProvider($http))->complete($this->prompt(), ['api_key' => 'd']);
        $this->assertTrue($r->ok);
        $this->assertSame('deepseek', $r->provider);
        $this->assertSame('https://api.deepseek.com/chat/completions', $http->lastRequest()['url']);
    }

    public function test_azure_builds_url_and_auth_from_credentials(): void
    {
        $http = (new FakeHttpClient())->pushResponse(200, (string) json_encode(['choices' => [['message' => ['content' => 'az']]], 'usage' => []]));
        $creds = ['api_key' => 'ak', 'endpoint' => 'https://my.openai.azure.com', 'deployment' => 'gpt4o'];
        $r = (new AzureOpenAiProvider($http))->complete($this->prompt(), $creds);
        $this->assertTrue($r->ok);
        $this->assertSame('azure_openai', $r->provider);
        $req = $http->lastRequest();
        $this->assertTrue(str_contains($req['url'], 'my.openai.azure.com/openai/deployments/gpt4o/chat/completions'));
        $this->assertTrue(str_contains($req['url'], 'api-version='));
        $this->assertTrue(in_array('api-key: ak', $req['headers'], true));
    }

    public function test_large_prompt_and_response_are_handled(): void
    {
        $big = str_repeat('word ', 5000);
        $http = (new FakeHttpClient())->pushResponse(200, (string) json_encode([
            'choices' => [['message' => ['content' => str_repeat('x', 20000)]]],
            'usage' => ['prompt_tokens' => 1250, 'completion_tokens' => 5000],
        ]));
        $r = (new OpenAiProvider($http))->complete($this->prompt($big), ['api_key' => 'k']);
        $this->assertTrue($r->ok);
        $this->assertSame(20000, mb_strlen($r->text));
        $this->assertSame(1250, $r->inputTokens);
    }

    public function test_empty_response_is_a_clean_failure(): void
    {
        $http = (new FakeHttpClient())->pushResponse(200, '{}');
        $r = (new OpenAiProvider($http))->complete($this->prompt(), ['api_key' => 'k']);
        $this->assertFalse($r->ok);
        $this->assertSame('empty_response', $r->error);
    }

    public function test_gateway_switches_provider_and_falls_back(): void
    {
        $db = app('db');
        tenant()->setById((int) $db->table('workspaces')->orderBy('id')->value('id'));

        $gw = AiGateway::make();
        // Replace the curl-backed openai adapter with one that fails, proving the
        // gateway switches to the next candidate (sandbox) on any provider failure.
        $gw->register(new OpenAiProvider((new FakeHttpClient())->pushResponse(500, '')));

        $r = $gw->complete($this->prompt(), ['candidates' => [
            ['provider' => 'openai', 'model' => 'gpt-4o-mini', 'key_id' => 0],
            ['provider' => 'sandbox', 'model' => 'sandbox-1', 'key_id' => 0],
        ]]);

        $this->assertTrue($r->ok);
        $this->assertSame('sandbox', $r->provider);
    }
};
