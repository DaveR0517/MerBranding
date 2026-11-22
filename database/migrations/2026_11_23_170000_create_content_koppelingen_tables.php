<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module content en koppelingen: social accounts (Instagram, TikTok), contentkalender, video-ideeën,
 * en gebruiksrechten. De handmatige controle "post verwijderd" (W8) staat in het logboek (subject publicatie).
 * Bron: CLAUDE.md hoofdstuk 8 (API's en AVG) en 9 (fase 3 en 7).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->morphs('owner'); // creator of bedrijf
            $table->string('platform', 20); // instagram, tiktok
            $table->string('handle')->nullable();
            $table->boolean('professional_confirmed')->default(false); // professioneel of creator-account bevestigd
            $table->string('status', 20)->default('niet_gekoppeld'); // niet_gekoppeld, handmatig, gekoppeld, verlopen, fout
            $table->text('access_token')->nullable(); // versleuteld (cast)
            $table->text('refresh_token')->nullable(); // versleuteld (cast)
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('last_synced_at')->nullable();
            $table->string('last_error')->nullable();
            $table->string('consent_version', 20)->nullable();
            $table->dateTime('consent_at')->nullable();
            $table->timestamps();
            $table->unique(['owner_type', 'owner_id', 'platform']);
        });

        Schema::create('content_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->string('title');
            $table->text('body')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'date']);
        });

        Schema::create('video_ideas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('collaboration_id')->nullable()->constrained()->nullOnDelete(); // verwijzing vanuit een aanvraag
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('idee'); // idee, gepland, gebruikt
            $table->string('source', 20); // ondernemer, team
            $table->timestamps();
        });

        Schema::create('usage_rights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collaboration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deliverable_version_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 20); // organisch, advertentie
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('ad_code')->nullable(); // advertentiecode of partnership-code
            $table->string('status', 20)->default('actief'); // actief, verlopen, ingetrokken
            $table->boolean('is_proposal')->default(true); // standaardduur is een VOORSTEL (open punt 7)
            $table->json('notified')->nullable(); // dagen vooraf waarop al gemeld is, bijv. [30, 7]
            $table->dateTime('extension_requested_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'ends_on']);
        });
    }

    public function down(): void
    {
        foreach (['usage_rights', 'video_ideas', 'content_notes', 'social_accounts'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
