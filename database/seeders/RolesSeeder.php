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
    ];

    /** Rechtensjablonen voor medewerkers. */
    public const TEMPLATES = [
        'Accountmanager' => ['leads.bekijken', 'leads.beheren', 'bedrijven.bekijken', 'bedrijven.beheren', 'creators.bekijken', 'samenwerkingen.bekijken', 'samenwerkingen.koppelen', 'logboek.bekijken', 'statistieken.bekijken', 'berichten.beheren', 'support.beheren'],
        'Contentcontrole' => ['creators.bekijken', 'creators.screenen', 'creators.beheren', 'samenwerkingen.bekijken', 'content.controleren', 'klassement.beheren', 'koppelingen.beheren'],
        'Planning' => ['bedrijven.bekijken', 'creators.bekijken', 'samenwerkingen.bekijken', 'samenwerkingen.plannen', 'planning.beheren'],
        'Financieel' => ['bedrijven.bekijken', 'financieel.bekijken', 'financieel.beheren', 'tegoed.corrigeren', 'logboek.bekijken', 'statistieken.bekijken'],
        'Academybeheer' => ['creators.bekijken', 'academy.beheren'],
        'Sales' => ['leads.bekijken', 'leads.beheren', 'bedrijven.bekijken', 'sales.beheren'],
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
