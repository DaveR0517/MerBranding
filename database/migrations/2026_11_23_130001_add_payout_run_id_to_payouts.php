<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Een uitbetaling hoort bij hooguit één betaalrun (module cijfers en geld). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->foreignId('payout_run_id')->nullable()->after('run_on')->constrained('payout_runs')->nullOnDelete();
            $table->dateTime('paid_at')->nullable()->after('payout_run_id');
        });
    }

    public function down(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payout_run_id');
            $table->dropColumn('paid_at');
        });
    }
};
