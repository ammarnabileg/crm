<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\AI\AiGateway;
use App\Services\AI\ModelRouter;
use App\Services\AI\PromptGuard;
use App\Services\AI\Providers\FakeProvider;
use App\Services\AI\TokenOptimizer;
use App\Models\User;
use App\Services\Cv\CvParser;
use App\Services\Tenancy\WorkspaceService;
use Tests\TestCase;

/**
 * CvParser — the "read the CV well" engine. Proves the primary AI path turns a CV
 * into structured JSON (driven by a fake provider so it's offline), and the
 * heuristic fallback still recovers the obvious fields when no AI is available.
 */
return new class extends TestCase {
    protected bool $useDatabaseTransaction = true; // the gateway audits each call

    private int $workspaceId = 0;

    public function setUp(): void
    {
        // A real tenant so the gateway's audit write (ai_requests/ai_responses) lands.
        $owner = User::create([
            'name'           => 'CV Parser Owner',
            'email'          => 'cvp-' . uniqid() . '@cv.test',
            'password'       => 'x',
            'user_status_id' => lookup_id('user_status', 'active'),
        ]);
        $this->workspaceId = (int) (new WorkspaceService())->create($owner, 'CV Parser Co')->getKey();
        tenant()->setById($this->workspaceId);
    }

    public function tearDown(): void
    {
        tenant()->clear();
    }

    /** A candidate routed to the offline fake provider returning the given text. */
    private function candidates(): array
    {
        // provider must be a real key (the audit resolves provider_id); no key_id so
        // the audit's tenant_ai_key_id stays null (no FK to a non-existent key).
        return ['candidates' => [['provider' => 'openai', 'model' => 'gpt-4o-mini']]];
    }

    private function gatewayReturning(string $text): AiGateway
    {
        $gw = new AiGateway(app('db'), new PromptGuard(), new TokenOptimizer(), ModelRouter::make());
        $gw->register(new FakeProvider('openai', false, $text));

        return $gw;
    }

    public function test_ai_path_structures_the_cv_into_fields(): void
    {
        $json = json_encode([
            'name' => 'Mona Hassan',
            'email' => 'mona@example.com',
            'phone' => '+20 100 000 0000',
            'headline' => 'Senior Backend Engineer',
            'summary' => 'Backend engineer with deep PHP experience.',
            'current_title' => 'Senior Backend Engineer',
            'city' => 'Cairo',
            'total_experience_years' => 9,
            'skills' => ['PHP', 'Laravel', 'MySQL'],
            'experiences' => [['title' => 'Engineer', 'company' => 'Acme', 'start' => '2018', 'end' => 'Present']],
            'educations' => [['degree' => 'BSc', 'institution' => 'Cairo University', 'field' => 'CS']],
        ]);

        $parser = new CvParser($this->gatewayReturning($json));
        $data = $parser->parse('… raw cv text …', $this->workspaceId, $this->candidates());

        $this->assertSame('ai', $data['source']);
        $this->assertSame('Mona Hassan', $data['name']);
        $this->assertSame('mona@example.com', $data['email']);
        $this->assertSame(9.0, $data['total_experience_years']);
        $this->assertTrue(in_array('Laravel', $data['skills'], true));
        $this->assertSame('Acme', $data['experiences'][0]['company']);
        $this->assertSame('Cairo University', $data['educations'][0]['institution']);
    }

    public function test_ai_path_tolerates_a_code_fence_around_the_json(): void
    {
        $fenced = "```json\n" . json_encode(['name' => 'Ali', 'skills' => ['Go']]) . "\n```";
        $parser = new CvParser($this->gatewayReturning($fenced));

        $data = $parser->parse('cv', $this->workspaceId, $this->candidates());
        $this->assertSame('ai', $data['source']);
        $this->assertSame('Ali', $data['name']);
        $this->assertTrue(in_array('Go', $data['skills'], true));
    }

    public function test_heuristic_fallback_when_no_ai_is_configured(): void
    {
        // No tenant → the router yields no provider → the parser falls back to
        // dependency-free heuristics (still recovers email/phone/skills/name).
        tenant()->clear();
        $cv = "Omar Khaled\nomar.khaled@example.com\n+20 111 222 3333\n"
            . "Senior Software Engineer with 12 years of experience.\n"
            . "Skills: PHP, Laravel, Docker, AWS, PostgreSQL";

        $data = (new CvParser())->parse($cv);

        $this->assertSame('heuristic', $data['source']);
        $this->assertSame('omar.khaled@example.com', $data['email']);
        $this->assertSame('Omar Khaled', $data['name']);
        $this->assertSame(12.0, $data['total_experience_years']);
        $this->assertTrue(in_array('PHP', $data['skills'], true));
        $this->assertTrue(in_array('AWS', $data['skills'], true));
    }

    public function test_empty_text_is_handled(): void
    {
        $data = (new CvParser())->parse('   ');
        $this->assertSame('none', $data['source']);
        $this->assertSame([], $data['skills']);
    }
};
