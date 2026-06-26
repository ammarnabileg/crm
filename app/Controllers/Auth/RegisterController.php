<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\DTOs\RegisterUserData;
use App\Services\Auth\RegistrationService;

/**
 * The single registration flow. A new user may optionally name their company
 * up front (becoming its Owner) or create one later from inside the app.
 */
final class RegisterController extends Controller
{
    public function show(): Response
    {
        return $this->view('auth.register', ['title' => 'Create account']);
    }

    public function register(Request $request): Response
    {
        $validated = $this->validate($request, [
            'name'         => 'required|min:2|max:150',
            'email'        => 'required|email|max:190|unique:users,email',
            'password'     => 'required|min:8|confirmed',
            'company_name' => 'nullable|max:150',
        ], [
            'email.unique' => 'An account with this email already exists.',
        ]);

        // Hand a typed DTO to the application-layer use-case; the controller
        // stays thin (docs/47 EAS-1/EAS-4). User creation, the UserRegistered
        // event, and optional company provisioning happen there.
        $result = app(RegistrationService::class)->register(
            RegisterUserData::fromArray(array_merge($validated, ['locale' => locale()]))
        );

        auth()->login($result['user']);

        if ($result['company'] !== null) {
            tenant()->setTenant($result['company']);
            $this->withSuccess('Welcome! Your workspace "' . $result['company']->name . '" is ready.');

            return $this->redirect(url('dashboard'));
        }

        $this->withSuccess('Welcome to ' . config('app.name') . '! Create your first company to get started.');

        return $this->redirect(url('companies/create'));
    }
}
