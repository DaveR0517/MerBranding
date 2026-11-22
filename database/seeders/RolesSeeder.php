<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Rollen en rechten (CLAUDE.md hoofdstuk 5). Veilig om vaker te draaien.
 * Rechtensjablonen voor medewerkers zijn rollen met losse rechten; een medewerker kan er meerdere hebben.
 */
class RolesSeeder extends Seeder
{
    /** Rechten per onderdeel van het teamportaal. Fase 1 verfijnt dit. */
    public const PERMISSIONS = [
        'leads.bekijken', 'leads.beheren',
        'bedrijven.bekijken', 'bedrijven.beheren',
        'creators.bekijken', 'creators.screenen', 'creators.beheren',
        'samenwerkingen.bekijken', 'samenwerkingen.koppelen', 'samenwerkingen.plannen',
        'content.controleren',
        'financieel.bekijken', 'financieel.beheren', 'tegoed.corrigeren',
        'academy.beheren',
        'team.beheren', 'instellingen.beheren', 'logboek.bekijken',
        'statistieken.bekijken', 'studio.gebruiken', 'klassement.beheren', 'planning.beheren', 'berichten.beheren', 'sales.beheren', 'koppelingen.beheren', 'support.beheren',
        'resultaten.bekijken', 'cijfers.invoeren',
        'kanalen.beheren',
    ];

    /** Leesbare naam per recht, voor het scherm Team en rechten. Een recht zonder naam toont zijn sleutel. */
    public const LABELS = [
        'leads.bekijken' => 'Aanmeldingen bekijken', 'leads.beheren' => 'Aanmeldingen beheren',
        'bedrijven.bekijken' => 'Bedrijven bekijken', 'bedrijven.beheren' => 'Bedrijven beheren',
        'creators.bekijken' => 'Creators bekijken', 'creators.screenen' => 'Creators screenen', 'creators.beheren' => 'Creators beheren',
        'samenwerkingen.bekijken' => 'Samenwerkingen bekijken', 'samenwerkingen.koppelen' => 'Creators koppelen', 'samenwerkingen.plannen' => 'Bezoeken plannen',
        'content.controleren' => 'Content controleren',
        'financieel.bekijken' => 'Financieel bekijken', 'financieel.beheren' => 'Financieel beheren', 'tegoed.corrigeren' => 'Tegoed corrigeren',
        'academy.beheren' => 'Academy beheren',
        'team.beheren' => 'Team en rechten beheren', 'instellingen.beheren' => 'Instellingen, pakketten en sjablonen', 'logboek.bekijken' => 'Logboek bekijken',
        'statistieken.bekijken' => 'Statistieken bekijken', 'klassement.beheren' => 'Klassement beheren', 'planning.beheren' => 'Planning beheren',
        'berichten.beheren' => 'Berichten beheren', 'sales.beheren' => 'Sales beheren', 'koppelingen.beheren' => 'Koppelingen beheren', 'support.beheren' => 'Support beheren',
        'resultaten.bekijken' => 'Resultaten bekijken', 'cijfers.invoeren' => 'Cijfers handmatig invoeren',
        'kanalen.beheren' => 'Eigen kanalen (Instagram en TikTok van MEBRANDING)',
    ];

    /** Rechtensjablonen voor medewerkers. */
    public const TEMPLATES = [
        'Accountmanager' => ['studio.gebruiken', 'leads.bekijken', 'leads.beheren', 'bedrijven.bekijken', 'bedrijven.beheren', 'creators.bekijken', 'samenwerkingen.bekijken', 'samenwerkingen.koppelen', 'logboek.bekijken', 'statistieken.bekijken', 'berichten.beheren', 'support.beheren', 'resultaten.bekijken', 'cijfers.invoeren', 'kanalen.beheren'],
        'Contentcontrole' => ['studio.gebruiken', 'creators.bekijken', 'creators.screenen', 'creators.beheren', 'samenwerkingen.bekijken', 'content.controleren', 'klassement.beheren', 'koppelingen.beheren', 'resultaten.bekijken', 'cijfers.invoeren'],
        'Planning' => ['studio.gebruiken', 'bedrijven.bekijken', 'creators.bekijken', 'samenwerkingen.bekijken', 'samenwerkingen.plannen', 'planning.beheren'],
        'Financieel' => ['studio.gebruiken', 'bedrijven.bekijken', 'financieel.bekijken', 'financieel.beheren', 'tegoed.corrigeren', 'logboek.bekijken', 'statistieken.bekijken', 'resultaten.bekijken'],
        'Academybeheer' => ['studio.gebruiken', 'creators.bekijken', 'academy.beheren'],
        'Sales' => ['studio.gebruiken', 'leads.bekijken', 'leads.beheren', 'bedrijven.bekijken', 'sales.beheren', 'resultaten.bekijken', 'kanalen.beheren'],
    ];

    /** Rollen buiten het team. Hun rechten gelden binnen het eigen bedrijf of de eigen klussen (policies, fase 1). */
    public const ROLES = ['Super Admin', 'Medewerker', 'Ondernemer Eigenaar', 'Ondernemer Lid', 'Creator', 'Student'];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $nieuw = [];
        foreach (self::PERMISSIONS as $name) {
            if (! Permission::where('name', $name)->where('guard_name', 'web')->exists()) {
                $nieuw[] = $name;
            }
            Permission::findOrCreate($name, 'web');
        }

        foreach (self::ROLES as $name) {
            Role::findOrCreate($name, 'web');
        }

        // Nieuw sjabloon: alle rechten. Bestaand sjabloon: alleen rechten die net nieuw zijn in het platform,
        // zodat wijzigingen van het team (Teambeheer) bij een deploy blijven staan.
        foreach (self::TEMPLATES as $name => $permissions) {
            $rol = Role::where('name', $name)->where('guard_name', 'web')->first();
            if (! $rol) {
                Role::create(['name' => $name, 'guard_name' => 'web'])->syncPermissions($permissions);
            } elseif ($erbij = array_intersect($permissions, $nieuw)) {
                $rol->givePermissionTo($erbij);
            }
        }
    }
}
