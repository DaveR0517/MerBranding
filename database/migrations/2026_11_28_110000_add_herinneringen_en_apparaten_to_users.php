<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Account: herinneringen die maar één keer gaan (zoals bij samenwerkingen) en de apparaten waarop iemand eerder inlogde.
 * Een apparaat is een vingerafdruk van browser en besturingssysteem uit de user agent; geen IP-adres.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('herinneringen')->nullable();
            $table->json('apparaten')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['herinneringen', 'apparaten']);
        });
    }
};
