<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kern van het platform: bedrijven, creators, samenwerkingen (11 statussen),
 * video's, feedback, publicaties, tegoed, uitbetalingen, werkvoorraad en logboek.
 * Bron: CLAUDE.md hoofdstuk 6, 7 en 10, docs/05-besluiten-workflows.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('kvk', 20)->nullable();
            $table->string('branche')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->foreignId('account_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('self_confirm_visits')->default(false); // W2: zelf bevestigen, 24 uur
            $table->boolean('owner_approves_content')->default(false); // W3 optie A
            $table->timestamps();
        });

        Schema::create('company_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20)->default('eigenaar'); // eigenaar of lid
            $table->timestamps();
            $table->unique(['company_id', 'user_id']);
        });

        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->text('experience')->nullable(); // wat de creator mag ervaren
            $table->timestamps();
        });

        Schema::create('visit_windows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday'); // 1 maandag .. 7 zondag
            $table->time('starts_at');
            $table->time('ends_at');
            $table->timestamps();
        });

        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('plan_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('price_cents');
            $table->unsignedInteger('points_per_month');
            $table->date('valid_from');
            $table->boolean('is_proposal')->default(true); // VOORSTEL
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_version_id')->constrained();
            $table->date('starts_on');
            $table->date('cancels_on')->nullable();
            $table->string('status', 20)->default('actief');
            $table->timestamps();
        });

        Schema::create('creators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('level', 20)->default('bronze');
            $table->string('kind', 20)->default('beide'); // influencer, ugc, beide
            $table->json('niches')->nullable();
            $table->date('birthdate')->nullable();
            $table->string('city')->nullable();
            $table->string('instagram')->nullable();
            $table->string('tiktok')->nullable();
            $table->string('vat_status')->nullable(); // creator vult zelf in
            $table->text('payout_details')->nullable(); // versleuteld (cast)
            $table->string('status', 20)->default('actief'); // screening, actief, gepauzeerd
            $table->timestamps();
        });

        Schema::create('collaborations', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained();
            $table->foreignId('creator_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('briefing');
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->boolean('alcohol')->default(false);
            $table->string('status', 30)->index();
            $table->unsignedSmallInteger('points')->default(0);
            $table->string('creator_level', 20)->nullable();
            $table->unsignedInteger('fee_cents')->default(0);
            $table->dateTime('visit_at')->nullable();
            $table->dateTime('visited_at')->nullable();
            $table->dateTime('content_deadline_at')->nullable();
            $table->date('publish_on')->nullable();
            $table->dateTime('live_at')->nullable();
            $table->dateTime('measure_until')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collaboration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('creator_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('expires_at');
            $table->string('answer', 20)->nullable(); // geaccepteerd, geweigerd, verlopen
            $table->dateTime('answered_at')->nullable();
            $table->timestamps();
        });

        Schema::create('signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('collaboration_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('agreement', 40); // kluscontract, raamovereenkomst
            $table->string('version', 20);
            $table->string('signed_name');
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('deliverable_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collaboration_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('version');
            $table->string('disk', 20);
            $table->string('path');
            $table->string('mime', 60)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->text('caption');
            $table->json('label_check'); // controle reclamevermelding
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('outcome', 20)->nullable(); // feedback, goedgekeurd
            $table->timestamps();
        });

        Schema::create('feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deliverable_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('timecode')->nullable(); // seconden
            $table->text('body');
            $table->boolean('visible_to_creator')->default(true);
            $table->timestamps();
        });

        Schema::create('publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collaboration_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 20);
            $table->string('url');
            $table->dateTime('posted_at');
            $table->dateTime('last_found_at')->nullable();
            $table->timestamps();
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collaboration_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('body')->nullable();
            $table->timestamps();
        });

        Schema::create('credit_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('collaboration_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 20); // toekennen, reserveren, verbruiken, vrijgeven, correctie
            $table->integer('points'); // effect op het beschikbare saldo
            $table->string('reason');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_id')->constrained()->cascadeOnDelete();
            $table->foreignId('collaboration_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('amount_cents');
            $table->string('status', 20)->default('opeisbaar'); // opeisbaar, uitbetaald
            $table->date('run_on'); // eerstvolgende vrijdag (W7)
            $table->timestamps();
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40)->index(); // werkblok
            $table->string('title');
            $table->foreignId('collaboration_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('creator_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('permission', 60)->nullable(); // wie het oppakt
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('due_at')->nullable();
            $table->dateTime('closed_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('subject');
            $table->string('event', 60);
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->string('reason')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->string('role', 5);
            $table->string('event');
            $table->json('channels');
            $table->string('subject');
            $table->json('email')->nullable();
            $table->text('inapp')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['message_templates', 'activity_log', 'tasks', 'payouts', 'credit_ledger', 'reviews', 'publications', 'feedback', 'deliverable_versions', 'signatures', 'invitations', 'collaborations', 'creators', 'subscriptions', 'plan_versions', 'plans', 'visit_windows', 'locations', 'company_user', 'companies'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
