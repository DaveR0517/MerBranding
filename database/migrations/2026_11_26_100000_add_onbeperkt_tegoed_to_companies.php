<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Uitzondering voor een eigen zaak van MEBRANDING (besluit Dave, 24 nov: Cheung Kwong Franeker):
 * zonder abonnement aanvragen zonder puntenlimiet. Verbruik wordt nog wel geboekt, zodat het zichtbaar blijft.
 * Alleen aan te zetten op de server, niet in een scherm.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('onbeperkt_tegoed')->default(false)->after('owner_approves_content');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('onbeperkt_tegoed');
        });
    }
};
