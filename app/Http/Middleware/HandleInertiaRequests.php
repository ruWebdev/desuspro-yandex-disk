<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                // Provide a normalized user payload with role info for the frontend
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_blocked' => (bool)($user->is_blocked ?? false),
                    // Spatie roles as plain strings for easy checks on the FE
                    'roles' => $user->getRoleNames()->values(),
                    // Convenience booleans (both capitalized and lowercase role names supported)
                    'is_admin' => $user->hasAnyRole(['Administrator', 'admin']),
                    'is_manager' => $user->hasAnyRole(['Manager', 'manager']),
                    'is_performer' => $user->hasAnyRole(['Performer', 'performer']),
                ] : null,
            ],
        ];
    }
}
