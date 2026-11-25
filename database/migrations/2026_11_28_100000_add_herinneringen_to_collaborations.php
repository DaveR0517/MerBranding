<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Herinneringen en teamsignalen die de planner stuurt (App\Services\Herinneringen) gaan maar één keer.
 * Per samenwerking een lijst: sleutel => moment van versturen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collaborations', function (Blueprint $table) {
            $table->json('herinneringen')->nullable()->after('cancel_reason');
        });
    }

    public function down(): void
    {
        Schema::table('collaborations', function (Blueprint $table) {
            $table->dropColumn('herinneringen');
        });
    }
};
