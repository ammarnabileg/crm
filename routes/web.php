<?php

declare(strict_types=1);

/**
 * Web routes.
 *
 * $router is the application Router (provided by the kernel when this file is
 * loaded). Middleware is referenced by the aliases declared in
 * config/middleware.php. Every web route runs through security headers + CSRF +
 * maintenance gate; auth/guest/tenant/permission layer on top.
 *
 * @var \App\Core\Router $router
 */

use App\Controllers\App\WorkspaceController;
use App\Controllers\App\DashboardController;
use App\Controllers\App\DesignSystemController;
use App\Controllers\App\MemberController;
use App\Controllers\App\NotificationController;
use App\Controllers\App\AiSettingsController;
use App\Controllers\App\SettingsController;
use App\Controllers\App\SearchController;
use App\Controllers\App\RoleController;
use App\Controllers\App\FileController;
use App\Controllers\App\BillingController;
use App\Controllers\System\CronController;
use App\Controllers\Ats\ApplicationController;
use App\Controllers\Ats\BoardController;
use App\Controllers\Ats\JobController;
use App\Controllers\Ats\RecruiterDashboardController;
use App\Controllers\App\HomeController;
use App\Controllers\App\ProfileController;
use App\Controllers\Auth\LoginController;
use App\Controllers\Auth\PasswordController;
use App\Controllers\Auth\RegisterController;
use App\Controllers\Setup\InstallController;
use App\Controllers\System\BackupController;
use App\Controllers\System\HealthController;
use App\Controllers\System\DiagnosticsController;
use App\Controllers\System\EnvironmentController;
use App\Controllers\System\LogViewerController;
use App\Controllers\System\MaintenanceController;
use App\Controllers\System\PlatformController;
use App\Core\Response;

// Liveness probe — outside the maintenance gate so health checks stay green during
// maintenance windows. Unauthenticated, minimal, leaks nothing (just app+DB up).
$router->group(['middleware' => ['security']], function ($router): void {
    $router->get('up', [HealthController::class, 'up'])->name('health.up');

    // No-terminal cron/queue trigger — a server cron hits this tokenized URL to
    // drain background jobs + tick the scheduler. Unauthenticated (token-gated in
    // the controller, fail-closed 404 when no token is configured), no tenant, and
    // outside the maintenance gate so the queue keeps draining during maintenance.
    $router->get('cron/run', [CronController::class, 'run'])->name('cron.run');

    // Payment gateway webhook — an external server-to-server callback that cannot
    // carry our CSRF token (it authenticates via the gateway signature instead), so
    // it lives in this CSRF-exempt, unauthenticated group. It bypasses maintenance so
    // events are never lost; it is inert-but-safe with zero gateway keys.
    $router->post('billing/webhook', [BillingController::class, 'webhook'])->name('billing.webhook');
});

$router->group(['middleware' => ['security', 'csrf', 'maintenance']], function ($router): void {

    // --- Public ---------------------------------------------------------
    $router->get('/', [HomeController::class, 'index'])->name('home');

    // --- Installer / Setup (served only while not yet installed) --------
    // Canonical URL is /setup (Setup & Installer Bible); /install redirects to it.
    $router->group(['prefix' => 'setup'], function ($router): void {
        $router->get('/', [InstallController::class, 'index'])->name('setup');
        $router->post('requirements', [InstallController::class, 'requirements']);
        $router->post('database', [InstallController::class, 'database']);
        $router->post('environment', [InstallController::class, 'environment']);
        $router->post('storage', [InstallController::class, 'storage']);
        $router->post('permissions', [InstallController::class, 'permissions']);
        $router->post('permissions/fix', [InstallController::class, 'fixPermissions']);
        $router->post('migrate', [InstallController::class, 'migrate']);
        $router->post('seed', [InstallController::class, 'seed']);
        $router->post('mail', [InstallController::class, 'mail']);
        $router->post('mail/test', [InstallController::class, 'testMail']);
        $router->post('admin', [InstallController::class, 'admin']);
        $router->post('health', [InstallController::class, 'health']);
        $router->post('finalize', [InstallController::class, 'finalize']);
    });
    // Legacy alias.
    $router->get('install', fn (): Response => Response::redirect(url('setup')))->name('install');

    // --- Guest-only auth ------------------------------------------------
    $router->group(['middleware' => ['guest']], function ($router): void {
        $router->get('login', [LoginController::class, 'show'])->name('login');
        $router->post('login', [LoginController::class, 'login'])->middleware('throttle:10,60');

        $router->get('register', [RegisterController::class, 'show'])->name('register');
        $router->post('register', [RegisterController::class, 'register'])->middleware('throttle:10,60');

        $router->get('forgot-password', [PasswordController::class, 'showForgot'])->name('password.request');
        $router->post('forgot-password', [PasswordController::class, 'sendReset'])->middleware('throttle:5,60');

        $router->get('reset-password', [PasswordController::class, 'showReset'])->name('password.reset');
        $router->post('reset-password', [PasswordController::class, 'reset'])->middleware('throttle:5,60');
    });

    // --- Authenticated --------------------------------------------------
    $router->group(['middleware' => ['auth']], function ($router): void {
        $router->post('logout', [LoginController::class, 'logout'])->name('logout');

        // Workspace selection / creation (no active tenant required yet).
        $router->get('workspaces/create', [WorkspaceController::class, 'create'])->name('workspaces.create');
        $router->post('workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');
        $router->get('workspaces/select', [WorkspaceController::class, 'select'])->name('workspaces.select');
        $router->post('workspaces/switch', [WorkspaceController::class, 'switch'])->name('workspaces.switch');

        // Profile.
        $router->get('profile', [ProfileController::class, 'show'])->name('profile.show');
        $router->put('profile', [ProfileController::class, 'update'])->name('profile.update');

        // System operations (platform-level — SUPER ADMIN ONLY). Gated by the
        // super_admin middleware, NOT permission:system.manage: the Owner role's '*'
        // wildcard grants system.manage to every customer, which would leak the .env
        // editor, cross-tenant DB backups and the platform console to ordinary
        // tenants. No terminal: diagnostics, maintenance, backup/restore, env, logs.
        $router->group(['prefix' => 'system', 'middleware' => ['super_admin']], function ($router): void {
            $router->get('diagnostics', [DiagnosticsController::class, 'index'])->name('system.diagnostics');

            $router->get('maintenance', [MaintenanceController::class, 'index'])->name('system.maintenance');
            $router->post('maintenance', [MaintenanceController::class, 'update'])->name('system.maintenance.update');

            $router->get('backups', [BackupController::class, 'index'])->name('system.backups');
            $router->post('backups/database', [BackupController::class, 'createDatabase'])->name('system.backups.database');
            $router->post('backups/files', [BackupController::class, 'createFiles'])->name('system.backups.files');
            $router->get('backups/download', [BackupController::class, 'download'])->name('system.backups.download');
            $router->delete('backups/delete', [BackupController::class, 'destroy'])->name('system.backups.delete');
            $router->post('backups/restore', [BackupController::class, 'restore'])->name('system.backups.restore');

            $router->get('environment', [EnvironmentController::class, 'index'])->name('system.environment');
            $router->post('environment', [EnvironmentController::class, 'update'])->name('system.environment.update');

            $router->get('logs', [LogViewerController::class, 'index'])->name('system.logs');
            $router->post('logs/clear', [LogViewerController::class, 'clear'])->name('system.logs.clear');

            // Super-Admin Platform Console — manage every workspace + user across
            // tenants. Cross-tenant reads/writes are super-admin-only (system.manage)
            // via PlatformDirectory (raw connection, NOT tenant-scoped); all audited.
            $router->get('platform/workspaces', [PlatformController::class, 'workspaces'])->name('system.platform.workspaces');
            $router->get('platform/workspaces/show', [PlatformController::class, 'showWorkspace'])->name('system.platform.workspaces.show');
            $router->post('platform/workspaces/suspend', [PlatformController::class, 'suspendWorkspace'])->name('system.platform.workspaces.suspend');
            $router->post('platform/workspaces/activate', [PlatformController::class, 'activateWorkspace'])->name('system.platform.workspaces.activate');
            $router->get('platform/users', [PlatformController::class, 'users'])->name('system.platform.users');
            $router->post('platform/users/suspend', [PlatformController::class, 'suspendUser'])->name('system.platform.users.suspend');
            $router->post('platform/users/activate', [PlatformController::class, 'activateUser'])->name('system.platform.users.activate');
            $router->post('platform/impersonate', [PlatformController::class, 'impersonate'])->name('system.platform.impersonate');
        });

        // Ending an impersonation is authorised by the parked impersonator id, NOT by
        // system.manage (the active identity is the impersonated user, who may lack
        // it), so it lives in the auth group rather than the system.manage group.
        $router->post('system/platform/stop-impersonating', [PlatformController::class, 'stopImpersonating'])->name('system.platform.stop-impersonating');

        // Tenant-scoped application.
        $router->group(['middleware' => ['tenant']], function ($router): void {
            $router->get('dashboard', [DashboardController::class, 'index'])
                ->middleware('permission:dashboard.view')
                ->name('dashboard');

            // Design System catalog (docs/30) — an internal component reference for
            // the platform owner only; hidden from tenant users (no nav link) and
            // gated to super-admins via system.manage so it never shows to customers.
            $router->get('design', [DesignSystemController::class, 'index'])
                ->middleware('permission:system.manage')
                ->name('design');

            // Recruitment / ATS (docs/53). Reads: recruitment.view; writes: recruitment.manage.
            $router->get('recruiter', [RecruiterDashboardController::class, 'index'])->middleware('permission:recruitment.view')->name('recruiter.dashboard');
            $router->get('jobs', [JobController::class, 'index'])->middleware('permission:recruitment.view')->name('jobs.index');
            $router->post('jobs', [JobController::class, 'store'])->middleware('permission:recruitment.manage')->name('jobs.store');
            $router->post('jobs/publish', [JobController::class, 'publish'])->middleware('permission:recruitment.manage')->name('jobs.publish');
            $router->post('jobs/close', [JobController::class, 'close'])->middleware('permission:recruitment.manage')->name('jobs.close');
            $router->post('jobs/archive', [JobController::class, 'archive'])->middleware('permission:recruitment.manage')->name('jobs.archive');
            $router->get('jobs/board', [BoardController::class, 'show'])->middleware('permission:recruitment.view')->name('jobs.board');
            $router->post('jobs/board/move', [BoardController::class, 'move'])->middleware('permission:recruitment.manage')->name('jobs.board.move');
            $router->get('applications/show', [ApplicationController::class, 'show'])->middleware('permission:recruitment.view')->name('applications.show');

            // Global Search (docs/53) — keyword over jobs/applications + talent filter.
            $router->get('search', [SearchController::class, 'index'])->middleware('permission:recruitment.view')->name('search.index');

            // Files (docs/30) — workspace documents on local disk. Reads:
            // recruitment.view; writes: recruitment.manage (files are recruitment
            // artifacts — gated under the recruitment permissions, no new perm).
            $router->get('files', [FileController::class, 'index'])->middleware('permission:recruitment.view')->name('files.index');
            $router->post('files/upload', [FileController::class, 'upload'])->middleware('permission:recruitment.manage')->name('files.upload');
            $router->get('files/download', [FileController::class, 'download'])->middleware('permission:recruitment.view')->name('files.download');
            $router->post('files/delete', [FileController::class, 'delete'])->middleware('permission:recruitment.manage')->name('files.delete');

            // Billing (docs/14) — in-app subscription management. Reads: billing.view;
            // changes: billing.manage. The manual/in-app path always works with zero
            // gateway keys; online checkout layers on top only when a gateway is set.
            $router->get('billing', [BillingController::class, 'index'])->middleware('permission:billing.view')->name('billing.index');
            $router->post('billing/subscribe', [BillingController::class, 'subscribe'])->middleware('permission:billing.manage')->name('billing.subscribe');

            // Members (docs/47 RBAC). Reads: members.view; each write checks the
            // matching members.* permission.
            $router->get('members', [MemberController::class, 'index'])->middleware('permission:members.view')->name('members.index');
            $router->post('members/invite', [MemberController::class, 'invite'])->middleware('permission:members.invite')->name('members.invite');
            $router->post('members/update-roles', [MemberController::class, 'updateRoles'])->middleware('permission:members.update')->name('members.update-roles');
            $router->post('members/deactivate', [MemberController::class, 'deactivate'])->middleware('permission:members.remove')->name('members.deactivate');
            $router->post('members/reactivate', [MemberController::class, 'reactivate'])->middleware('permission:members.remove')->name('members.reactivate');

            // Roles & Permissions (docs/47 RBAC) — per-workspace roles + a module-
            // grouped permission matrix. Reads: roles.view; writes: roles.manage.
            $router->get('roles', [RoleController::class, 'index'])->middleware('permission:roles.view')->name('roles.index');
            $router->get('roles/create', [RoleController::class, 'create'])->middleware('permission:roles.manage')->name('roles.create');
            $router->post('roles', [RoleController::class, 'store'])->middleware('permission:roles.manage')->name('roles.store');
            $router->get('roles/edit', [RoleController::class, 'edit'])->middleware('permission:roles.manage')->name('roles.edit');
            $router->post('roles/update', [RoleController::class, 'update'])->middleware('permission:roles.manage')->name('roles.update');
            $router->post('roles/delete', [RoleController::class, 'destroy'])->middleware('permission:roles.manage')->name('roles.delete');

            // Notifications (docs/30) — the signed-in user's own feed. No permission
            // gate: the controller scopes every query to auth()->id() + tenant().
            $router->get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
            $router->post('notifications/read', [NotificationController::class, 'markRead'])->name('notifications.read');
            $router->post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

            // AI Settings (docs/51) — per-tenant provider keys + engine defaults.
            // Reads: ai.view; writes: ai.manage. No platform-held keys.
            $router->get('ai', [AiSettingsController::class, 'index'])->middleware('permission:ai.view')->name('ai.index');
            $router->post('ai/keys', [AiSettingsController::class, 'storeKey'])->middleware('permission:ai.manage')->name('ai.keys.store');
            $router->post('ai/keys/delete', [AiSettingsController::class, 'deleteKey'])->middleware('permission:ai.manage')->name('ai.keys.delete');
            $router->post('ai/defaults', [AiSettingsController::class, 'updateDefaults'])->middleware('permission:ai.manage')->name('ai.defaults.update');

            // Workspace Settings (docs/47 EAS-9) — tabbed settings, saved per section.
            $router->get('settings', [SettingsController::class, 'index'])->middleware('permission:settings.view')->name('settings.index');
            $router->post('settings', [SettingsController::class, 'update'])->middleware('permission:settings.manage')->name('settings.update');
        });
    });
});
