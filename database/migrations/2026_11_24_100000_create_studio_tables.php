<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MeStudio: ontwerpen, sjablonen, mediatheek en exports voor de eigen marketing van MEBRANDING. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studio_templates', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->nullable()->unique(); // alleen voor meegeleverde huisstijlsjablonen
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('format', 30);
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->json('pages');
            $table->string('preview_path')->nullable();
            $table->boolean('is_builtin')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });

        Schema::create('studio_designs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('studio_templates')->nullOnDelete();
            $table->string('name');
            $table->string('format', 30);
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->json('pages');
            $table->string('status', 20)->default('concept'); // concept, klaar, ingepland, geplaatst
            $table->dateTime('scheduled_at')->nullable();
            $table->text('caption')->nullable();
            $table->integer('feed_position')->nullable();
            $table->string('post_url')->nullable();
            $table->dateTime('posted_at')->nullable();
            $table->string('preview_path')->nullable();
            $table->timestamps();
            $table->index(['status', 'feed_position']);
        });

        Schema::create('studio_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 20); // foto, video, logo
            $table->string('name');
            $table->string('disk', 20)->default('local');
            $table->string('path');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->timestamps();
        });

        Schema::create('studio_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('design_id')->constrained('studio_designs')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 10); // png, jpg, webm, mp4
            $table->unsignedInteger('page')->default(1);
            $table->string('path');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('status', 20)->default('klaar'); // klaar, omzetten, mislukt, alleen_webm
            $table->string('message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['studio_exports', 'studio_media', 'studio_designs', 'studio_templates'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
