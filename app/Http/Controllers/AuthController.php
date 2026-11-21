<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class AuthController extends Controller
{
    public function form(string $mode = 'login'): View
    {
        return view('auth', ['mode' => $mode]);
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! Auth::attempt(['email' => strtolower($data['email']), 'password' => $data['password'], 'is_active' => true], $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'The email or password is incorrect.']);
        }
        $request->session()->regenerate();

        return redirect()->intended(route(auth()->user()->is_admin ? 'admin.dashboard' : 'library'));
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => 'required|string|max:100', 'email' => 'required|email|max:255|unique:users', 'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()]]);
        $user = User::create(['name' => $data['name'], 'email' => strtolower($data['email']), 'password' => $data['password']]);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('library');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    public function profile(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => 'required|string|max:100', 'current_password' => 'required|current_password', 'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()]]);
        $request->user()->update(['name' => $data['name'], 'password' => $data['password']]);
        $request->session()->regenerate();

        return back()->with('success', 'Your profile has been updated.');
    }
}
