<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module communicatie en sales: berichten per samenwerking, helpcentrum, support,
 * sales-pipeline voor kennismakingen en de persoonlijke uitnodigingslink.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Berichten per samenwerking. visible_to is het kanaal: team_ondernemer, team_creator of alle (alleen met de instelling aan).
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collaboration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('visible_to', 20);
            $table->text('body');
            $table->timestamps();
            $table->index(['collaboration_id', 'visible_to']);
        });

        // Tot wanneer iemand de berichten van een samenwerking gelezen heeft.
        Schema::create('message_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collaboration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at');
            $table->unique(['collaboration_id', 'user_id']);
        });

        Schema::create('help_articles', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('category', 60)->nullable();
            $table->text('body'); // markdown
            $table->json('audiences'); // ondernemer, creator, team
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('portal', 20); // ondernemer, creator, team
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('collaboration_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject');
            $table->string('status', 20)->default('open')->index(); // open, beantwoord, gesloten
            $table->string('priority', 20)->default('normaal'); // laag, normaal, hoog
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('support_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->boolean('internal')->default(false); // interne notitie van het team
            $table->timestamps();
        });

        // Fase van een kennismaking in de sales-pipeline (één rij per lead, ontstaat bij de eerste wijziging).
        Schema::create('lead_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('stage', 20)->default('nieuw'); // nieuw, gebeld, afspraak, offerte, klant, verloren
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('next_action')->nullable();
            $table->date('next_action_on')->nullable();
            $table->timestamps();
        });

        // Persoonlijke uitnodigingslink per gebruiker.
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('code', 40)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['referrals', 'lead_stages', 'support_replies', 'support_tickets', 'help_articles', 'message_reads', 'messages'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
