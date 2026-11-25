<?php

use Database\Seeders\MessageTemplateSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kanaalplan en mailvormen (besluit Dave 25-11-2026): vaste zaken per mail, de rest via push en het portaal, met mail als vangnet.
 * vorm: opmaak van de mail. standaard_hash: vingerafdruk van de standaardtekst, zodat een volgende versie
 * alleen sjablonen bijwerkt die het team niet zelf heeft aangepast.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('message_templates', function (Blueprint $table) {
            $table->string('vorm', 20)->default('standaard')->after('event');
            $table->string('standaard_hash', 32)->nullable()->after('push');
        });

        (new MessageTemplateSeeder)->run();
    }

    public function down(): void
    {
        Schema::table('message_templates', fn (Blueprint $table) => $table->dropColumn(['vorm', 'standaard_hash']));
    }
};
