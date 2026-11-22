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
        'statistieken.bekijken', 'klassement.beheren', 'planning.beheren', 'berichten.beheren', 'sales.beheren', 'koppelingen.beheren', 'support.beheren',
        'resultaten.bekijken', 'cijfers.invoeren',
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
    ];

    /** Rechtensjablonen voor medewerkers. */
    public const TEMPLATES = [
        'Accountmanager' => ['leads.bekijken', 'leads.beheren', 'bedrijven.bekijken', 'bedrijven.beheren', 'creators.bekijken', 'samenwerkingen.bekijken', 'samenwerkingen.koppelen', 'logboek.bekijken', 'statistieken.bekijken', 'berichten.beheren', 'support.beheren', 'resultaten.bekijken', 'cijfers.invoeren'],
        'Contentcontrole' => ['creators.bekijken', 'creators.screenen', 'creators.beheren', 'samenwerkingen.bekijken', 'content.controleren', 'klassement.beheren', 'koppelingen.beheren', 'resultaten.bekijken', 'cijfers.invoeren'],
        'Planning' => ['bedrijven.bekijken', 'creators.bekijken', 'samenwerkingen.bekijken', 'samenwerkingen.plannen', 'planning.beheren'],
        'Financieel' => ['bedrijven.bekijken', 'financieel.bekijken', 'financieel.beheren', 'tegoed.corrigeren', 'logboek.bekijken', 'statistieken.bekijken', 'resultaten.bekijken'],
        'Academybeheer' => ['creators.bekijken', 'academy.beheren'],
        'Sales' => ['leads.bekijken', 'leads.beheren', 'bedrijven.bekijken', 'sales.beheren', 'resultaten.bekijken'],
    ];

    /** Rollen buiten het team. Hun rechten gelden binnen het eigen bedrijf of de eigen klussen (policies, fase 1). */
    public const ROLES = ['Super Admin', 'Medewerker', 'Ondernemer Eigenaar', 'Ondernemer Lid', 'Creator', 'Student'];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach (self::ROLES as $name) {
            Role::findOrCreate($name, 'web');
        }

        foreach (self::TEMPLATES as $name => $permissions) {
            Role::findOrCreate($name, 'web')->syncPermissions($permissions);
        }
    }
}
