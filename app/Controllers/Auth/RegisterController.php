<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Core\Controller;
use App\Core\Hash;
use App\Core\Request;
use App\Core\Response;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\Tenancy\CompanyService;

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
        $data = $this->validate($request, [
            'name'         => 'required|min:2|max:150',
            'email'        => 'required|email|max:190|unique:users,email',
            'password'     => 'required|min:8|confirmed',
            'company_name' => 'nullable|max:150',
        ], [
            'email.unique' => 'An account with this email already exists.',
        ]);

        $user = User::withoutTenantScope()->insertGetId([
            'name'       => $data['name'],
            'email'      => mb_strtolower($data['email']),
            'password'   => Hash::make($data['password']),
            'locale'     => locale(),
            'status'     => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::find($user);
        ActivityLog::record('user.registered', null, (int) $user->getKey(), 'Registered a new account');

        auth()->login($user);

        // If they named a company, provision it now and make it active.
        if (! empty($data['company_name'])) {
            $company = (new CompanyService())->create($user, $data['company_name']);
            tenant()->setTenant($company);
            $this->withSuccess('Welcome! Your workspace "' . $company->name . '" is ready.');

            return $this->redirect(url('dashboard'));
        }

        $this->withSuccess('Welcome to ' . config('app.name') . '! Create your first company to get started.');

        return $this->redirect(url('companies/create'));
    }
}
