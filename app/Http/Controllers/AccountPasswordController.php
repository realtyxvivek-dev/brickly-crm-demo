<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AccountPasswordController extends Controller
{
    public function edit(Request $request)
    {
        return view('account.password-change', [
            'currentPasswordRequired' => !$request->user()->mustChangePassword(),
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $currentPasswordRequired = !$user->mustChangePassword();

        $rules = [
            'password' => ['required', 'confirmed', Password::min(8)],
        ];

        if ($currentPasswordRequired) {
            $rules['current_password'] = ['required'];
        }

        $validated = $request->validate($rules);

        if ($currentPasswordRequired && !Hash::check($validated['current_password'], $user->password)) {
            return back()
                ->withErrors(['current_password' => 'Current password is incorrect.'])
                ->withInput();
        }

        $user->forceFill([
            'password' => Hash::make($validated['password']),
        ])->save();

        $user->clearPasswordChangeRequirement();
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'))->with('success', 'Password changed successfully.');
    }
}
