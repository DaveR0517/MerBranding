<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Spatie\Permission\Models\Permission;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                'portal' => $request->user()?->homePortal(),
                'roles' => $request->user()?->getRoleNames() ?? [],
                'permissions' => $request->user()?->hasRole('Super Admin') ? Permission::pluck('name') : ($request->user()?->getAllPermissions()->pluck('name') ?? []),
                'is_super_admin' => $request->user()?->hasRole('Super Admin') ?? false,
                'impersonating' => $request->session()->has('inloggen_als_van'),
            ],
            'unread' => fn () => $request->user()?->unreadNotifications()->count() ?? 0,
            'flash' => fn () => ['melding' => $request->session()->get('melding'), 'uitnodiging' => $request->session()->get('uitnodiging')],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
