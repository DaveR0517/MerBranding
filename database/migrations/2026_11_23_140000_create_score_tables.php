<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Score, niveau en klassement (CLAUDE.md 7.3, hoofdstuk 8 platformwerk, docs/02 flow 5).
 * Ruwe cijfers staan in stat_snapshots en reviews; hier staan alleen de berekende scores,
 * de gebeurtenissen die de score uitleggen en de voorstellen tot niveauwijziging.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Eén rij per creator per meetmoment (dagelijks), zodat het verloop zichtbaar is.
        Schema::create('scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            // Deelscores van 0 tot 100, null als er geen gegevens zijn (het gewicht wordt dan herverdeeld).
            $table->decimal('reach', 5, 1)->nullable();
            $table->decimal('engagement', 5, 1)->nullable();
            $table->decimal('ad_result', 5, 1)->nullable();
            $table->decimal('quality', 5, 1)->nullable();
            $table->decimal('reliability', 5, 1)->nullable();
            $table->decimal('total', 5, 1);
            $table->unsignedSmallInteger('jobs_count')->default(0);
            $table->boolean('is_new_talent')->default(false);
            $table->dateTime('calculated_at');
            $table->timestamps();

            $table->unique(['creator_id', 'period_end']);
        });

        // Wat de score verklaart: strafpunten op Betrouwbaarheid en kwaliteitsoordelen van het team.
        Schema::create('score_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_id')->constrained()->cascadeOnDelete();
            $table->foreignId('collaboration_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 20); // niet_gekomen, te_laat, post_weg, beoordeling
            $table->string('reason');
            $table->smallInteger('points'); // strafpunten negatief, een beoordeling 0 tot 100
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['creator_id', 'created_at']);
        });

        // Voorstellen tot niveauwijziging. Nooit automatisch: een teamlid beslist met reden.
        Schema::create('level_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_id')->constrained()->cascadeOnDelete();
            $table->string('from_level', 20);
            $table->string('to_level', 20);
            $table->decimal('score', 5, 1)->nullable();
            $table->string('reason'); // waarom het systeem dit voorstelt
            $table->string('status', 20)->default('open'); // open, goedgekeurd, afgewezen
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('decided_at')->nullable();
            $table->string('decision_reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'creator_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('level_proposals');
        Schema::dropIfExists('score_events');
        Schema::dropIfExists('scores');
    }
};
