<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gmail in het portaal: een persoonlijk postvak is pas zichtbaar voor de eigenaren van MEBRANDING
 * als de eigenaar van dat postvak akkoord gaf (AVG). Hier staat wie, wanneer, en of het is ingetrokken.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('postvak_akkoorden', function (Blueprint $table) {
            $table->id();
            $table->string('postvak', 190)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('akkoord_op');
            $table->timestamp('ingetrokken_op')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('postvak_akkoorden');
    }
};
