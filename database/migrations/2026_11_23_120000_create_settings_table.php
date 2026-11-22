<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Instellingen die het team zelf beheert (CLAUDE.md 8, zelfbeheer). Overschrijven config/mebranding.php. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 120)->unique(); // configpad, bijv. mebranding.timers.invitation_hours
            $table->json('value');
            $table->boolean('is_proposal')->default(true); // VOORSTEL tot het besloten is
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
