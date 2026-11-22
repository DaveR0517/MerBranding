<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MeStudio merkslot: de aangepaste merkkit (één rij, de rest uit config/studio.php) en een seedversie op sjablonen. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studio_merkkit', function (Blueprint $table) {
            $table->id();
            $table->json('data');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('studio_templates', function (Blueprint $table) {
            $table->unsignedInteger('seed_version')->nullable()->after('is_builtin');
        });
    }

    public function down(): void
    {
        Schema::table('studio_templates', fn (Blueprint $table) => $table->dropColumn('seed_version'));
        Schema::dropIfExists('studio_merkkit');
    }
};
