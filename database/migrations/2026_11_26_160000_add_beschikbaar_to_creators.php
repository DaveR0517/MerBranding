<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Ik ben beschikbaar": een creator deelt een paar uur lang dat hij een klus in de buurt kan doen, met de plek
 * (afgerond op ongeveer 100 meter) als de telefoon toestemming geeft. Na afloop wist App\Services\Beschikbaarheid de plek.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creators', function (Blueprint $table) {
            $table->timestamp('beschikbaar_tot')->nullable()->index();
            $table->decimal('beschikbaar_lat', 8, 3)->nullable();
            $table->decimal('beschikbaar_lng', 8, 3)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('creators', fn (Blueprint $table) => $table->dropColumn(['beschikbaar_tot', 'beschikbaar_lat', 'beschikbaar_lng']));
    }
};
