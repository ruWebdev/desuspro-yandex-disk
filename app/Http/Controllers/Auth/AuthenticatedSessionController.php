<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response|RedirectResponse
    {
        // If user is already authenticated, send to their dashboard based on role
        if (Auth::check()) {
            /** @var \App\Models\User $user */
            $user = Auth::user();
            if ($user && $user->hasRole('Administrator')) {
                return redirect()->to(route('admin.dashboard'));
            }
            if ($user && $user->hasRole('Manager')) {
                return redirect()->to(route('manager.dashboard'));
            }
            if ($user && $user->hasRole('Performer')) {
                return redirect()->to(route('performer.dashboard'));
            }
            return redirect()->to(route('admin.dashboard'));
        }

        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // Get the authenticated user
        $user = $request->user();

        // Redirect based on user role
        if ($user->hasRole('Administrator')) {
            return redirect()->intended(route('admin.dashboard'));
        } elseif ($user->hasRole('Manager')) {
            return redirect()->intended(route('manager.dashboard'));
        } elseif ($user->hasRole('Performer')) {
            return redirect()->intended(route('performer.dashboard'));
        }

        // Default fallback - redirect to admin dashboard if no specific role matches
        return redirect()->intended(route('admin.dashboard'));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
