<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Throwable;

class LoginController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('storefront');
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        try {
            if (!Schema::hasTable('users')) {
                return back()
                    ->withInput($request->only('email'))
                    ->withErrors([
                        'email' => 'No W68 user accounts are configured yet.',
                    ]);
            }
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'The login database is not available right now.',
                ]);
        }

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            return redirect()->intended(route('storefront'));
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors([
                'email' => 'The email or password is incorrect.',
            ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
