<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\ActivityLog;
use App\Support\RateLimiter;

/**
 * The single login for the entire platform. What the user can do afterwards is
 * decided by RBAC, not by any account "type".
 */
final class LoginController extends Controller
{
    public function show(): Response
    {
        return $this->view('auth.login', ['title' => 'Sign in']);
    }

    public function login(Request $request): Response
    {
        $data = $this->validate($request, [
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $limiter = new RateLimiter(storage_path('cache'));
        $key = 'login:' . sha1(mb_strtolower($data['email']) . '|' . $request->ip());
        $max = (int) config('auth.max_login_attempts', 5);

        if ($limiter->tooManyAttempts($key, $max)) {
            $seconds = $limiter->availableIn($key);
            $this->fail('email', "Too many login attempts. Try again in {$seconds} seconds.", ['email' => $data['email']]);
        }

        $user = auth()->attempt($data['email'], $data['password']);

        if ($user === null) {
            $limiter->hit($key, (int) config('auth.lockout_seconds', 900));
            ActivityLog::record('auth.login_failed', null, null, 'Failed login for ' . $data['email']);
            $this->fail('email', 'These credentials do not match our records.', ['email' => $data['email']]);
        }

        $limiter->clear($key);
        ActivityLog::record('auth.login', null, (int) $user->getKey(), 'Signed in');

        $intended = session()->pull('url.intended');

        return $this->redirect(is_string($intended) && $intended !== '' ? $intended : url('dashboard'));
    }

    public function logout(Request $request): Response
    {
        $userId = auth()->id();
        auth()->logout();
        ActivityLog::record('auth.logout', null, $userId, 'Signed out');

        return $this->redirect(url('login'));
    }
}
