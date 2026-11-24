<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kaart: coördinaten van vestigingen en van aanmeldingen van ondernemers (PDOK Locatieserver, App\Services\Geocoder).
 * precisie: adres (op de deur) of plaats (midden van de woonplaats, als er geen adres is).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['locations', 'leads'] as $tabel) {
            Schema::table($tabel, function (Blueprint $table) {
                $table->decimal('lat', 10, 7)->nullable();
                $table->decimal('lng', 10, 7)->nullable();
                $table->string('geo_precisie', 10)->nullable();
                $table->timestamp('geocoded_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['locations', 'leads'] as $tabel) {
            Schema::table($tabel, fn (Blueprint $table) => $table->dropColumn(['lat', 'lng', 'geo_precisie', 'geocoded_at']));
        }
    }
};
