<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * My profile (UX-FLOWS page 19): anyone signed in can change their own name and password.
 * Mobile, role and branch stay with the Owner on the Staff page, and so does "Reset password".
 */
class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'current_password' => ['nullable', 'required_with:password', 'string'],
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ], [
            'current_password.required_with' => 'Type your current password to set a new one.',
            'password.confirmed' => 'The two new passwords don’t match.',
            'password.min' => 'The new password needs at least 8 characters.',
        ]);

        $changingPassword = ! empty($data['password']);
        if ($changingPassword) {
            if (! Hash::check($data['current_password'], $user->password)) {
                throw ValidationException::withMessages(['current_password' => 'That is not your current password.']);
            }
            if (Hash::check($data['password'], $user->password)) {
                throw ValidationException::withMessages(['password' => 'The new password is the same as the old one.']);
            }
            $user->password = $data['password'];
        }
        $user->name = trim($data['name']);
        $user->save();

        if ($changingPassword) {
            // Sign out every other device that used the old password; keep this one signed in.
            Auth::logoutOtherDevices($data['password']);
            $request->session()->regenerate();
            activity('staff')->performedOn($user)->causedBy($user)->log('Changed own password');
        }

        return redirect()->route('profile.edit')->with('status', $changingPassword ? 'Password changed. Use the new one next time you sign in.' : 'Profile saved.');
    }
}
