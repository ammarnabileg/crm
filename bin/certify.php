<?php

declare(strict_types=1);

/*
 * Phase 16 — Production-Readiness Auditor.
 *
 * Boots the real kernel and runs a battery of architecture, security, data and
 * wiring checks, then prints a PASS/FAIL report. Exit 0 = certified.
 *
 *   php bin/certify.php
 *
 * See docs/RELEASE_CERTIFICATION.md.
 */

use HaHireAI\Core\Config\Environment;
use HaHireAI\Core\Config\Repository;
use HaHireAI\Core\Contracts\EventDispatcher;
use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Database\Migrations\MigrationRunner;
use HaHireAI\Core\Health\HealthChecker;
use HaHireAI\Core\Modules\ModuleRegistry;
use HaHireAI\Core\Routing\Router;
use HaHireAI\Modules\AiEngine\Application\ProviderRegistry;
use HaHireAI\Modules\Navigation\Application\SidebarBuilder;
use HaHireAI\Modules\Permissions\Application\RoleService;
use HaHireAI\Modules\Permissions\Domain\PermissionCatalog;

/** @var \HaHireAI\Core\Kernel $kernel */
$kernel = require dirname(__DIR__) . '/bootstrap/app.php';
$kernel->boot();
$c = $kernel->container();

$pass = 0;
$fail = 0;
$warn = 0;
$section = '';

$out = static fn (string $s) => fwrite(STDOUT, $s . PHP_EOL);
$head = static function (string $s) use (&$section, $out): void {
    $section = $s;
    $out("\n\033[1m{$s}\033[0m");
};
$check = static function (string $name, bool $ok, string $detail = '') use (&$pass, &$fail, $out): void {
    $ok ? $pass++ : $fail++;
    $tag = $ok ? "\033[32mPASS\033[0m" : "\033[31mFAIL\033[0m";
    $out(sprintf('  %s %s%s', $tag, $name, $detail !== '' ? " — {$detail}" : ''));
};
$warnCheck = static function (string $name, bool $ok, string $detail = '') use (&$pass, &$warn, $out): void {
    $ok ? $pass++ : $warn++;
    $tag = $ok ? "\033[32mPASS\033[0m" : "\033[33mWARN\033[0m";
    $out(sprintf('  %s %s%s', $tag, $name, $detail !== '' ? " — {$detail}" : ''));
};

$out("\033[1mHaHireAI — Production Readiness Certification\033[0m");
$out('Date: ' . gmdate('c'));

// ── 1. Boot & modules ───────────────────────────────────────────────────────
$head('1. Kernel & Modules');
$check('kernel boots', $kernel->isBooted());
$registry = $c->make(ModuleRegistry::class);
$moduleCount = $registry->count();
$check('all modules registered (>= 11)', $moduleCount >= 11, "{$moduleCount} modules");
try {
    $ordered = $registry->ordered();
    $check('module dependency order resolves (acyclic)', count($ordered) === $moduleCount);
} catch (Throwable $e) {
    $check('module dependency order resolves (acyclic)', false, $e->getMessage());
}

// ── 2. Security & configuration ─────────────────────────────────────────────
$head('2. Security & Configuration');
$env = $c->make(Environment::class);
$config = $c->make(Repository::class);
$appKey = (string) $env->get('APP_KEY', '');
$check('APP_KEY is set', $appKey !== '');
$check('APP_KEY is a 32-byte base64 key', str_starts_with($appKey, 'base64:') && strlen(base64_decode(substr($appKey, 7), true) ?: '') === 32);
$gitignore = @file_get_contents($kernel->basePath('.gitignore')) ?: '';
$check('.env is gitignored', str_contains($gitignore, '.env'));
$check('vendor/ is gitignored', str_contains($gitignore, 'vendor'));
$debug = (bool) $config->get('app.debug', false);
$appEnv = (string) $env->get('APP_ENV', 'production');
$warnCheck('debug disabled in production', ! ($appEnv === 'production' && $debug), $appEnv === 'production' ? 'prod' : "env={$appEnv}");

// ── 3. Database & migrations ────────────────────────────────────────────────
$head('3. Database & Migrations');
$dbOk = false;
try {
    $conn = $c->make(Connection::class);
    $conn->select('SELECT 1');
    $dbOk = true;
} catch (Throwable $e) {
    $check('database reachable', false, $e->getMessage());
}
if ($dbOk) {
    $check('database reachable', true);
    $runner = $c->make(MigrationRunner::class);
    $ran = $runner->ranMigrations();
    $files = glob($kernel->basePath('database/migrations') . '/*.php') ?: [];
    $names = array_map(static fn (string $p): string => basename($p, '.php'), $files);
    $pending = array_diff($names, $ran);
    $check('all migrations applied', $pending === [], $pending === [] ? count($ran) . ' applied' : count($pending) . ' pending');

    $required = ['users', 'workspaces', 'memberships', 'roles', 'permissions', 'jobs', 'applications',
        'candidate_profiles', 'interviews', 'interview_messages', 'candidate_assessments', 'interview_feedback',
        'job_questions', 'job_criteria', 'offers', 'application_status_history',
        'talent_pools', 'interview_invitations', 'ai_avatars', 'files', 'notifications',
        'ai_sessions', 'workflows', 'webhook_endpoints', 'subscriptions', 'plans', 'error_events', 'alerts'];
    $existing = array_map(static fn (array $r): string => (string) $r['t'], $conn->select('SELECT table_name AS t FROM information_schema.tables WHERE table_schema = DATABASE()'));
    $missing = array_diff($required, $existing);
    $check('core tables present', $missing === [], $missing === [] ? count($existing) . ' tables' : 'missing: ' . implode(', ', $missing));
}

// ── 4. User model & tenancy (the constitutional invariants) ─────────────────
$head('4. User Model & Tenancy');
if ($dbOk) {
    $userCols = array_map(static fn (array $r): string => (string) $r['Field'], $conn->select('SHOW COLUMNS FROM users'));
    $check('single User identity (one users table)', in_array('id', $userCols, true) && in_array('email', $userCols, true));
    $check('System Owner is a flag on User, not an account type', in_array('is_system_owner', $userCols, true));
    $check('no account_type column (contexts, not types)', ! in_array('account_type', $userCols, true) && ! in_array('role', $userCols, true));
    $check('Memberships link Users to Workspaces (many-to-many)', in_array('memberships', $existing, true));
    $check('roles are workspace DATA (roles table exists)', in_array('roles', $existing, true));
    $check('tenant boundary present (workspace_id on memberships)', in_array('workspace_id', array_map(static fn (array $r): string => (string) $r['Field'], $conn->select('SHOW COLUMNS FROM memberships')), true));
    // A candidate's profile is per-Workspace (Microsoft ≠ Google) — privacy isolation.
    if (in_array('candidate_profiles', $existing, true)) {
        $cp = array_map(static fn (array $r): string => (string) $r['Field'], $conn->select('SHOW COLUMNS FROM candidate_profiles'));
        $check('Candidate Profile is workspace-scoped (candidate_profiles.workspace_id + user_id)', in_array('workspace_id', $cp, true) && in_array('user_id', $cp, true));
    }
}

// ── 5. RBAC catalog integrity ───────────────────────────────────────────────
$head('5. Permissions & Sidebar Integrity');
$catalog = PermissionCatalog::all();
$keys = array_map(static fn (array $p): string => $p['key'], $catalog);
$check('permission keys are unique', count($keys) === count(array_unique($keys)), count($keys) . ' keys');
$catalogSet = array_flip($keys);
$sidebarKeys = SidebarBuilder::allPermissionKeys();
$drift = array_values(array_filter($sidebarKeys, static fn (string $k): bool => ! isset($catalogSet[$k])));
$check('every sidebar permission exists in the catalog', $drift === [], $drift === [] ? count($sidebarKeys) . ' sidebar keys' : 'drift: ' . implode(', ', $drift));
// Roles are runtime DATA (built per-workspace like Notion/Jira/GitHub), never hardcoded.
$check('roles are created at runtime (RoleService.createRole + assignPermissions), not hardcoded', method_exists(RoleService::class, 'createRole') && method_exists(RoleService::class, 'assignPermissions'));
if ($dbOk) {
    $roleCols = array_map(static fn (array $r): string => (string) $r['Field'], $conn->select('SHOW COLUMNS FROM roles'));
    $check('roles are workspace-scoped (roles.workspace_id) — no global reserved roles', in_array('workspace_id', $roleCols, true));
    // Catalog↔table parity: every catalog permission must exist in the DB, else
    // workspace owners silently lack newly added permissions after an upgrade.
    $dbKeys = array_flip(array_map(static fn (array $r): string => (string) $r['key'], $conn->select('SELECT `key` FROM permissions')));
    $missingPerms = array_values(array_filter($keys, static fn (string $k): bool => ! isset($dbKeys[$k])));
    $check('permission catalog is synced to the DB (run `console.php migrate`)', $missingPerms === [], $missingPerms === [] ? count($dbKeys) . ' in DB' : 'missing: ' . implode(', ', $missingPerms));
}

// ── 6. Routing surface ──────────────────────────────────────────────────────
$head('6. Routing Surface');
$router = $c->make(Router::class);
$paths = [];
foreach ($router->routes() as $r) {
    $paths[$r->method() . ' ' . $r->path()] = true;
}
foreach ([
    'GET /', 'GET /install', 'GET /login', 'GET /dashboard', 'GET /jobs', 'GET /workflows',
    'GET /workflows/new', 'GET /workflows/templates', 'POST /workflows/templates/{key}/use',
    'GET /workflows/{id}/edit', 'POST /workflows/save',
    'POST /workflows/{id}/run', 'POST /workflows/{id}/toggle',
    'GET /collections', 'POST /collections', 'GET /collections/{id}', 'GET /collections/{id}/export',
    'POST /collections/{id}/records', 'POST /collections/{id}/records/{recordId}/delete',
    'GET /integrations', 'GET /billing', 'GET /overview', 'GET /diagnostics',
    'GET /all-workspaces', 'GET /users', 'GET /subscriptions',
    'GET /interviews', 'GET /human-interviews', 'GET /files', 'GET /notifications',
    'GET /reports', 'GET /pipeline', 'GET /talent-pool', 'GET /avatars',
    'GET /my-workspaces', 'GET /ai/analytics', 'GET /workspaces/select',
    'GET /open-jobs', 'GET /my-applications', 'GET /my-profile',
    'POST /candidacy/{workspaceId}/switch',
    'GET /interview/{interviewId}', 'POST /interview/{interviewId}/answer',
    'POST /interview/{interviewId}/transcribe',
    'GET /interview-link/{token}', 'POST /interview-link/{token}/start', 'POST /interview-link/{token}/answer', 'POST /interview-link/{token}/feedback',
    'GET /offers', 'GET /reports/print', 'GET /jobs/{id}/edit',
    'GET /interviews/export', 'GET /interviews/{interviewId}',
    'POST /all-workspaces/{id}/suspend', 'POST /all-workspaces/{id}/archive',
    'POST /users/{id}/activate', 'POST /users/{id}/deactivate',
    'POST /users/{id}/workspace-creation', 'POST /users/{id}/plan', 'POST /users/{id}/grant-months',
    'GET /platform-settings', 'POST /platform-settings', 'GET /ai-providers', 'GET /payments',
    'GET /permissions', 'POST /permissions', 'POST /permissions/{id}/edit', 'POST /permissions/{id}/delete',
    'POST /permissions/{id}/assign', 'POST /permissions/{id}/unassign',
    'GET /account/plan', 'POST /account/plan',
    'GET /account/profile', 'POST /account/profile',
    'GET /plans', 'POST /plans', 'POST /plans/{id}/edit', 'POST /plans/{id}/delete',
    'POST /workspaces/transfer-ownership', 'POST /workspaces/archive',
    'POST /workspaces/{id}/deactivate', 'POST /workspaces/{id}/activate',
    'POST /pipeline/bulk-status', 'POST /talent-pool/bulk-add', 'GET /avatars/{id}/preview', 'GET /avatars/{id}/image',
    'POST /members/{membershipId}/suspend', 'POST /members/{membershipId}/activate', 'POST /members/{membershipId}/remove',
    'POST /roles/{roleId}/clone', 'POST /roles/{roleId}/delete',
    'GET /roles/{roleId}', 'POST /roles/{roleId}/edit',
    'GET /settings/logo',
    'GET /settings/maintenance', 'POST /settings/maintenance/enable', 'POST /settings/maintenance/disable',
    'POST /my-applications/{applicationId}/withdraw',
    'POST /notifications/{id}/archive', 'POST /notifications/{id}/unarchive',
    'POST /candidates/{userId}/parse-cv',
    'GET /tasks', 'POST /tasks', 'POST /tasks/{id}/complete', 'POST /tasks/{id}/delete',
    'GET /candidates/compare', 'GET /api/v1/ping', 'GET /api/v1/jobs',
    'GET /view/{slug}', 'GET /view/{slug}/logo',
    'GET /invites', 'POST /invites/{invitationId}/accept', 'POST /invites/{invitationId}/decline',
    'POST /members/invitations/{invitationId}/roles', 'POST /members/invitations/{invitationId}/revoke',
] as $route) {
    $check("route registered: {$route}", isset($paths[$route]));
}

// Every {param} in a route path must map to an identically-named handler argument
// (the dispatcher binds route params by name) — else the call 500s at runtime.
$paramMismatches = [];
foreach ($router->routes() as $r) {
    if (! preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $r->path(), $m)) {
        continue;
    }
    $handler = $r->handler();
    if (! is_array($handler)) {
        continue;
    }
    try {
        $names = array_map(static fn ($p) => $p->getName(), (new \ReflectionMethod($handler[0], $handler[1]))->getParameters());
    } catch (\Throwable) {
        $paramMismatches[] = $r->method() . ' ' . $r->path() . ' (no handler)';
        continue;
    }
    foreach ($m[1] as $p) {
        if (! in_array($p, $names, true)) {
            $paramMismatches[] = $r->method() . ' ' . $r->path() . ' missing $' . $p;
        }
    }
}
$check('every route {param} binds to a handler argument', $paramMismatches === [], implode('; ', $paramMismatches));

// ── 7. Engines & event-bus wiring ───────────────────────────────────────────
$head('7. Engines & Event Bus');
$providers = $c->make(ProviderRegistry::class);
$check('AI engine has the built-in echo provider', $providers->has('echo'));
$events = $c->make(EventDispatcher::class);
$check('Workflow + Webhooks react to application.submitted', $events->hasListeners('application.submitted'));
$check('Observability persists system.error', $events->hasListeners('system.error'));
$check('Billing seeds on platform.installed', $events->hasListeners('platform.installed'));
$candidateDir = $c->make(\HaHireAI\Core\Contracts\CandidateDirectory::class);
$check('Candidate directory contract resolves (Recruitment ↔ Workspaces decoupled)', $candidateDir instanceof \HaHireAI\Modules\Recruitment\Application\CandidateDirectoryAdapter);
$diagPanels = $c->make(\HaHireAI\Modules\Observability\Application\SystemDiagnostics::class)->panels();
$check('System diagnostics report 8 infrastructure panels', count($diagPanels) === 8);
$fileStorage = $c->make(\HaHireAI\Core\Contracts\FileStorage::class);
$check('File storage contract resolves (Files shared service decoupled)', $fileStorage instanceof \HaHireAI\Modules\Files\Application\FileService);
$accessControl = $c->make(\HaHireAI\Core\Contracts\AccessControl::class);
$check('Access control contract resolves (Permissions shared service decoupled)', $accessControl instanceof \HaHireAI\Modules\Permissions\Application\Authorizer);
$memberDir = $c->make(\HaHireAI\Core\Contracts\MemberDirectory::class);
$check('Member directory contract resolves (Memberships shared service decoupled)', $memberDir instanceof \HaHireAI\Modules\Memberships\Application\MembershipService);
$aiCaps = $c->make(\HaHireAI\Modules\AiEngine\Contracts\AiCapabilities::class);
$check('AI capabilities contract resolves (features gated on workspace keys)', $aiCaps instanceof \HaHireAI\Modules\AiEngine\Application\AiCapabilityService);
$stt = $c->make(\HaHireAI\Modules\AiEngine\Contracts\SpeechToText::class);
$check('Speech-to-text contract resolves (Whisper, per-workspace key)', $stt instanceof \HaHireAI\Modules\AiEngine\Infrastructure\OpenAiSpeechToText);
$allowance = $c->make(\HaHireAI\Core\Contracts\WorkspaceAllowance::class);
$check('Workspace allowance contract resolves (account caps / governance)', $allowance instanceof \HaHireAI\Modules\Platform\Application\AccountPlanService);
$support = $c->make(\HaHireAI\Core\Contracts\SupportInfo::class);
$check('Support info contract resolves (suspended-workspace contact)', $support instanceof \HaHireAI\Modules\Platform\Application\PlatformSettings);
$payments = $c->make(\HaHireAI\Core\Contracts\PaymentSettings::class);
$check('Payment settings contract resolves (platform payment switch)', $payments instanceof \HaHireAI\Modules\Platform\Application\PlatformSettings);
$notifFeed = $c->make(\HaHireAI\Core\Contracts\NotificationFeed::class);
$check('Notification feed contract resolves (header bell)', $notifFeed instanceof \HaHireAI\Modules\Notifications\Application\NotificationService);
$companyDir = $c->make(\HaHireAI\Core\Contracts\CompanyDirectory::class);
$check('Company directory contract resolves (public careers page)', $companyDir instanceof \HaHireAI\Modules\Workspaces\Application\CompanyDirectoryService);
$inviteInbox = $c->make(\HaHireAI\Core\Contracts\InvitationInbox::class);
$check('Invitation inbox contract resolves (accept-first invitations)', $inviteInbox instanceof \HaHireAI\Modules\Memberships\Application\InvitationService);
$taskWriter = $c->make(\HaHireAI\Core\Contracts\TaskWriter::class);
$check('Task writer contract resolves (workflow create-task action)', $taskWriter instanceof \HaHireAI\Modules\Tasks\Application\TaskWriterAdapter);
$notifWriter = $c->make(\HaHireAI\Core\Contracts\NotificationWriter::class);
$check('Notification writer contract resolves (workflow notify action)', $notifWriter instanceof \HaHireAI\Modules\Notifications\Application\NotificationWriterAdapter);
$recruitActions = $c->make(\HaHireAI\Core\Contracts\RecruitmentActions::class);
$check('Recruitment actions contract resolves (workflow pipeline actions)', $recruitActions instanceof \HaHireAI\Modules\Recruitment\Application\RecruitmentActionsAdapter);

// ── 8. Health ───────────────────────────────────────────────────────────────
$head('8. Health');
$report = $c->make(HealthChecker::class)->run();
$check('health probes registered (>= 3)', $c->make(HealthChecker::class)->count() >= 3);
$warnCheck('overall health is healthy', $report['status']->value === 'healthy', $report['status']->value);

// ── Summary ─────────────────────────────────────────────────────────────────
$out("\n" . str_repeat('─', 60));
$total = $pass + $fail;
if ($fail === 0) {
    $out("\033[32m✓ CERTIFIED\033[0m — {$pass}/{$total} checks passed" . ($warn > 0 ? ", {$warn} warning(s)" : ''));
} else {
    $out("\033[31m✗ NOT CERTIFIED\033[0m — {$fail} failed, {$pass} passed" . ($warn > 0 ? ", {$warn} warning(s)" : ''));
}

exit($fail === 0 ? 0 : 1);
