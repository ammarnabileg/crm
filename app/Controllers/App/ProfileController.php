<?php

declare(strict_types=1);

namespace App\Controllers\App;

use App\Core\Controller;
use App\Core\Hash;
use App\Core\Request;
use App\Core\Response;
use App\Models\User;

/**
 * The signed-in user's own profile (name, email, language, password). Available
 * to every authenticated user regardless of company context.
 */
final class ProfileController extends Controller
{
    public function show(): Response
    {
        return $this->view('app.profile', [
            'title' => 'Your profile',
            'user'  => auth()->user(),
        ]);
    }

    public function update(Request $request): Response
    {
        $user = auth()->user();
        $userId = (int) $user->getKey();

        $data = $this->validate($request, [
            'name'   => 'required|min:2|max:150',
            'email'  => 'required|email|max:190|unique:users,email,' . $userId,
            'locale' => 'required|in:en,ar',
        ]);

        $update = [
            'name'       => $data['name'],
            'email'      => mb_strtolower($data['email']),
            'locale'     => $data['locale'],
            'updated_at' => now(),
        ];

        // Optional password change.
        $newPassword = (string) $request->input('password', '');
        if ($newPassword !== '') {
            $this->validate($request, ['password' => 'min:8|confirmed']);
            $update['password'] = Hash::make($newPassword);
        }

        User::withoutTenantScope()->where('id', '=', $userId)->update($update);

        session()->put('locale', $data['locale']);
        $this->withSuccess('Your profile has been updated.');

        return $this->back();
    }
}
