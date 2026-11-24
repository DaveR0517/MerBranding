<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Webapp en pushmeldingen: per apparaat een abonnement (Web Push, standaard van de browser),
 * en de korte pushtekst per berichtsjabloon (stond al in database/data/berichtsjablonen.json).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_abonnementen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('endpoint', 500)->unique();
            $table->string('public_key', 255);
            $table->string('auth_token', 255);
            $table->string('content_encoding', 20)->default('aes128gcm');
            $table->string('apparaat', 120)->nullable();
            $table->timestamp('laatst_gebruikt_op')->nullable();
            $table->timestamps();
        });

        Schema::table('message_templates', function (Blueprint $table) {
            $table->string('push', 255)->nullable()->after('inapp');
        });
    }

    public function down(): void
    {
        Schema::table('message_templates', fn (Blueprint $table) => $table->dropColumn('push'));
        Schema::dropIfExists('push_abonnementen');
    }
};
