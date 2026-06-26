<?php

declare(strict_types=1);

/**
 * Web routes.
 *
 * $router is the application Router (provided by the kernel when this file is
 * loaded). Middleware is referenced by the aliases declared in
 * config/middleware.php. Every web route runs through security headers + CSRF;
 * auth/guest/tenant/permission layer on top.
 *
 * @var \App\Core\Router $router
 */

use App\Controllers\App\CompanyController;
use App\Controllers\App\DashboardController;
use App\Controllers\App\HomeController;
use App\Controllers\App\ProfileController;
use App\Controllers\Auth\LoginController;
use App\Controllers\Auth\PasswordController;
use App\Controllers\Auth\RegisterController;
use App\Controllers\Setup\InstallController;

$router->group(['middleware' => ['security', 'csrf']], function ($router): void {

    // --- Public ---------------------------------------------------------
    $router->get('/', [HomeController::class, 'index'])->name('home');

    // --- Installer (served only while the app is not yet installed) ------
    $router->group(['prefix' => 'install'], function ($router): void {
        $router->get('/', [InstallController::class, 'index'])->name('install');
        $router->post('requirements', [InstallController::class, 'requirements']);
        $router->post('database', [InstallController::class, 'database']);
        $router->post('migrate', [InstallController::class, 'migrate']);
        $router->post('seed', [InstallController::class, 'seed']);
        $router->post('admin', [InstallController::class, 'admin']);
        $router->post('finalize', [InstallController::class, 'finalize']);
    });

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

        // Company selection / creation (no active tenant required yet).
        $router->get('companies/create', [CompanyController::class, 'create'])->name('companies.create');
        $router->post('companies', [CompanyController::class, 'store'])->name('companies.store');
        $router->get('companies/select', [CompanyController::class, 'select'])->name('companies.select');
        $router->post('companies/switch', [CompanyController::class, 'switch'])->name('companies.switch');

        // Profile.
        $router->get('profile', [ProfileController::class, 'show'])->name('profile.show');
        $router->put('profile', [ProfileController::class, 'update'])->name('profile.update');

        // Tenant-scoped application.
        $router->group(['middleware' => ['tenant']], function ($router): void {
            $router->get('dashboard', [DashboardController::class, 'index'])
                ->middleware('permission:dashboard.view')
                ->name('dashboard');
        });
    });
});
