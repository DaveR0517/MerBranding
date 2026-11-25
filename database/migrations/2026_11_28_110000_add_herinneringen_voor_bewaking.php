<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Berichten van de bewaking (App\Services\Bewaking) gaan maar één keer per ding, net als bij samenwerkingen:
 * per rij een lijst sleutel => moment van versturen (trait App\Models\Concerns\HeeftHerinneringen).
 */
return new class extends Migration
{
    private const TABELLEN = ['companies', 'creators', 'social_accounts', 'course_days', 'enrollments', 'payout_runs'];

    public function up(): void
    {
        foreach (self::TABELLEN as $tabel) {
            Schema::table($tabel, function (Blueprint $table) {
                $table->json('herinneringen')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABELLEN as $tabel) {
            Schema::table($tabel, function (Blueprint $table) {
                $table->dropColumn('herinneringen');
            });
        }
    }
};
