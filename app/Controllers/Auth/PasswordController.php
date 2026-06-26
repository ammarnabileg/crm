<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Core\Controller;
use App\Core\Hash;
use App\Core\Request;
use App\Core\Response;
use App\Models\ActivityLog;
use App\Models\User;

/**
 * The single forgot/reset password flow. Tokens are random, stored hashed, and
 * expire after a configurable TTL. Responses never reveal whether an email is
 * registered (anti-enumeration).
 */
final class PasswordController extends Controller
{
    public function showForgot(): Response
    {
        return $this->view('auth.forgot-password', ['title' => 'Forgot password']);
    }

    public function sendReset(Request $request): Response
    {
        $data = $this->validate($request, ['email' => 'required|email']);
        $email = mb_strtolower($data['email']);

        $user = User::withoutTenantScope()->where('email', '=', $email)->first();

        if ($user !== null) {
            $token = bin2hex(random_bytes(32));

            app('db')->table('password_resets')->where('email', '=', $email)->delete();
            app('db')->table('password_resets')->insert([
                'email'      => $email,
                'token'      => Hash::make($token),
                'created_at' => now(),
            ]);

            $link = url('reset-password?token=' . $token . '&email=' . urlencode($email));
            $this->sendResetEmail($email, (string) $user['name'], $link);
            ActivityLog::record('auth.password_reset_requested', null, (int) $user['id'], 'Requested a password reset');
        }

        $this->withSuccess('If that email is registered, a password reset link has been sent.');

        return $this->back();
    }

    public function showReset(Request $request): Response
    {
        return $this->view('auth.reset-password', [
            'title' => 'Reset password',
            'token' => (string) $request->query('token', ''),
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function reset(Request $request): Response
    {
        $data = $this->validate($request, [
            'token'    => 'required',
            'email'    => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $email = mb_strtolower($data['email']);
        $record = app('db')->table('password_resets')->where('email', '=', $email)->first();

        $ttl = (int) config('auth.reset_token_ttl', 60) * 60;
        $valid = $record !== null
            && Hash::verify($data['token'], (string) $record['token'])
            && (time() - strtotime((string) $record['created_at'])) <= $ttl;

        if (! $valid) {
            $this->fail('email', 'This password reset link is invalid or has expired.', ['email' => $email]);
        }

        User::withoutTenantScope()->where('email', '=', $email)->update([
            'password'   => Hash::make($data['password']),
            'updated_at' => now(),
        ]);

        app('db')->table('password_resets')->where('email', '=', $email)->delete();
        ActivityLog::record('auth.password_reset', null, null, 'Password reset completed for ' . $email);

        $this->withSuccess('Your password has been reset. You can now sign in.');

        return $this->redirect(url('login'));
    }

    private function sendResetEmail(string $email, string $name, string $link): void
    {
        $appName = e(config('app.name'));
        $safeName = e($name);
        $safeLink = e($link);
        $body = <<<HTML
<div style="font-family:sans-serif;max-width:480px;margin:auto">
  <h2>{$appName} — Password reset</h2>
  <p>Hi {$safeName},</p>
  <p>We received a request to reset your password. Click the button below to choose a new one. This link expires soon.</p>
  <p><a href="{$safeLink}" style="display:inline-block;background:#2457eb;color:#fff;padding:10px 18px;border-radius:8px;text-decoration:none">Reset password</a></p>
  <p>If you didn't request this, you can safely ignore this email.</p>
</div>
HTML;

        app('mailer')->send($email, config('app.name') . ' — Reset your password', $body);
    }
}
