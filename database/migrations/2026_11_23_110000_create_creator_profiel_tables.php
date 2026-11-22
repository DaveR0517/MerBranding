<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module creator-profiel: het eigen profiel van de creator (foto, bio, werkgebied, portfolio)
 * en de screening van aanmeldingen van de website (besluit, taak in de werkvoorraad).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creator_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('photo_path')->nullable(); // public disk
            $table->text('bio')->nullable();
            $table->unsignedSmallInteger('radius_km')->nullable(); // werkgebied
            $table->json('portfolio_links')->nullable();
            $table->timestamps();
        });

        Schema::create('creator_screenings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('creator_id')->nullable()->constrained()->nullOnDelete();
            $table->string('decision', 20)->nullable(); // goedgekeurd, aanvulling, afgewezen
            $table->text('reason')->nullable();
            $table->string('level', 20)->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('decided_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creator_screenings');
        Schema::dropIfExists('creator_profiles');
    }
};
