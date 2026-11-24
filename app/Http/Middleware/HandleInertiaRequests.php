<?php

namespace App\Http\Middleware;

use App\Models\KanaalGesprek;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
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
                'portal' => fn () => $this->omgeving($request),
                'omgevingen' => fn () => $this->wissel($request),
                'roles' => $request->user()?->getRoleNames() ?? [],
                'permissions' => $request->user()?->hasRole('Super Admin') ? Permission::pluck('name') : ($request->user()?->getAllPermissions()->pluck('name') ?? []),
                'is_super_admin' => $request->user()?->hasRole('Super Admin') ?? false,
                'impersonating' => $request->session()->has('inloggen_als_van'),
            ],
            'unread' => fn () => $request->user()?->unreadNotifications()->count() ?? 0,
            'tellers' => fn () => $this->tellers($request),
            'beschikbaarheid' => fn () => $this->beschikbaarheid($request),
            'flash' => fn () => ['melding' => $request->session()->get('melding'), 'uitnodiging' => $request->session()->get('uitnodiging')],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /** De omgeving waarin je nu werkt: de laatst bezochte (AlleenOmgeving), anders je startomgeving. */
    private function omgeving(Request $request): ?string
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }
        $laatst = $request->session()->get('omgeving');

        return $laatst && in_array($laatst, $user->omgevingen(), true) ? $laatst : $user->homePortal();
    }

    /**
     * Wissel tussen omgevingen, alleen als je er meer dan één hebt. De ondernemerskant heet naar de zaak.
     *
     * @return list<array{key: string, naam: string, url: string}>
     */
    private function wissel(Request $request): array
    {
        $omgevingen = $request->user()?->omgevingen() ?? [];
        if (count($omgevingen) < 2) {
            return [];
        }
        $zaak = $request->user()->companies()->orderBy('name')->value('name');
        $namen = ['team' => 'Team MEBRANDING', 'creator' => 'Creator', 'ondernemer' => $zaak ?? 'Ondernemer', 'academy' => 'Academy'];

        return array_map(fn ($o) => ['key' => $o, 'naam' => $namen[$o], 'url' => route(User::OMGEVINGEN[$o])], $omgevingen);
    }

    /**
     * Rode tellers in het teammenu: open taken per menupagina (dezelfde taken als de werkvoorraad), plus ongelezen
     * berichten van de eigen kanalen. Sleutel is het pad van de pagina, zodat het menu ze zonder koppeling vindt.
     *
     * @return array<string, int>
     */
    private function tellers(Request $request): array
    {
        $user = $request->user();
        if (! $user || $this->omgeving($request) !== 'team') {
            return [];
        }

        $taken = Task::open()->voor($user)->get(['type', 'collaboration_id']);
        $per = $taken->map(fn (Task $t) => $t->menuRoute())->filter()->countBy()->all();
        $per['team.werkvoorraad'] = $taken->count();
        if ($user->can('kanalen.beheren')) {
            $per['team.kanalen.index'] = (int) KanaalGesprek::sum('ongelezen');
        }

        return collect($per)->filter(fn ($aantal, $route) => $aantal > 0 && Route::has($route))
            ->mapWithKeys(fn ($aantal, $route) => [parse_url(route($route), PHP_URL_PATH) => $aantal])->all();
    }

    /** Creator: staat "Ik ben beschikbaar" aan, tot wanneer, en met of zonder plek. */
    private function beschikbaarheid(Request $request): ?array
    {
        $creator = config('mebranding.creator_kaart') && $this->omgeving($request) === 'creator' ? $request->user()->creator : null;
        if (! $creator) {
            return null;
        }
        $actief = $creator->beschikbaar_tot?->isFuture() ?? false;

        return ['actief' => $actief, 'tot' => $actief ? $creator->beschikbaar_tot->toIso8601String() : null, 'plek' => $actief && $creator->beschikbaar_lat !== null];
    }
}
