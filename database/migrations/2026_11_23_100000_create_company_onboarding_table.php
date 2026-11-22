<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Welkom en "Aan de slag" voor de ondernemer: welke van de drie startstappen het bedrijf heeft afgevinkt.
 * Eén rij per bedrijf. Module ondernemer-beheer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_onboarding', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $table->timestamp('locations_checked_at')->nullable(); // stap 1: vestiging en tijden gecontroleerd
            $table->timestamp('team_invited_at')->nullable(); // stap 2: team uitgenodigd (of bewust overgeslagen)
            $table->timestamp('first_request_at')->nullable(); // stap 3: eerste samenwerking aangevraagd
            $table->timestamp('closed_at')->nullable(); // welkomstscherm weggeklikt
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_onboarding');
    }
};
