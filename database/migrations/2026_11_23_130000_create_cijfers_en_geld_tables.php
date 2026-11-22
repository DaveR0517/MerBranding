<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module cijfers en geld (CLAUDE.md 8 statistieken, 9 fase 6, 10 datamodel).
 * - stat_snapshots: ruwe metingen per publicatie. Berekende waarden (totalen, interactie) worden niet opgeslagen.
 * - payout_runs: de wekelijkse betaalrun op vrijdag.
 * - invoices: facturen voor ondernemers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stat_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publication_id')->constrained()->cascadeOnDelete();
            $table->string('metric', 20); // views, likes, comments, shares, saves, reach
            $table->unsignedBigInteger('value');
            $table->string('source', 20); // handmatig, instagram, tiktok
            $table->string('period', 20)->default('totaal'); // totaal = opgeteld sinds plaatsing
            $table->dateTime('fetched_at');
            $table->boolean('is_estimate')->default(false);
            $table->string('screenshot_path')->nullable();
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('replaces_id')->nullable()->constrained('stat_snapshots')->nullOnDelete(); // correctie door het team
            $table->string('note')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['publication_id', 'metric', 'fetched_at']);
        });

        Schema::create('payout_runs', function (Blueprint $table) {
            $table->id();
            $table->date('run_on')->unique(); // altijd een vrijdag
            $table->string('status', 20)->default('samengesteld'); // samengesteld, uitbetaald
            $table->unsignedInteger('total_cents')->default(0);
            $table->foreignId('composed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('exported_at')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 20)->unique();
            $table->date('period_start');
            $table->date('period_end');
            $table->json('lines'); // [{omschrijving, toelichting, aantal, prijs_cents, bedrag_cents}]
            $table->unsignedInteger('subtotal_cents');
            $table->unsignedTinyInteger('vat_rate')->default(21);
            $table->unsignedInteger('vat_cents');
            $table->unsignedInteger('total_cents');
            $table->string('status', 20)->default('openstaand'); // concept, openstaand, betaald
            $table->date('issued_on');
            $table->date('due_on');
            $table->dateTime('paid_at')->nullable();
            $table->unsignedTinyInteger('reminders_sent')->default(0);
            $table->dateTime('last_reminder_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('payout_runs');
        Schema::dropIfExists('stat_snapshots');
    }
};
