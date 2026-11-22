<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Academy (CLAUDE.md fase 5, docs/02 flow 3): cursusdagen, inschrijvingen met wachtlijst, check-in,
 * lessen, oefenopdrachten, praktijkexamen en certificaat met controlelink.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_days', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->date('date')->index();
            $table->time('starts_at');
            $table->time('ends_at');
            $table->string('location_name');
            $table->string('address')->nullable();
            $table->string('city');
            $table->unsignedSmallInteger('max_participants');
            $table->string('teacher_name')->nullable(); // OPEN: wie lesgeeft
            $table->unsignedInteger('price_cents'); // VOORSTEL: 29500
            $table->boolean('exam_required')->default(true); // OPEN: praktijkexamen als voorwaarde, per cursusdag
            $table->string('status', 20)->default('open'); // open, afgesloten, geannuleerd
            $table->dateTime('closed_at')->nullable();
            $table->text('notes')->nullable(); // wat mee te nemen, parkeren
            $table->timestamps();
        });

        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_day_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->index(); // ingeschreven, wachtlijst, aanwezig, afwezig, geslaagd, afgemeld
            $table->string('paid_status', 20)->default('nog_te_betalen'); // placeholder tot de betaalpartij er is
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('offer_expires_at')->nullable(); // plek vrij: betalen voor dit moment (VOORSTEL 24 uur)
            $table->string('checkin_code', 40)->unique();
            $table->dateTime('checked_in_at')->nullable();
            $table->foreignId('checked_in_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('checkin_reason')->nullable(); // handmatig ingecheckt: waarom
            $table->string('phone', 40)->nullable();
            $table->string('city')->nullable();
            $table->string('tiktok')->nullable();
            $table->string('instagram')->nullable();
            $table->date('birthdate')->nullable();
            $table->timestamps();
            $table->unique(['course_day_id', 'user_id']);
        });

        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->longText('body'); // markdown
            $table->unsignedSmallInteger('position')->default(0);
            $table->unsignedSmallInteger('minutes')->nullable();
            $table->timestamps();
        });

        Schema::create('lesson_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->dateTime('completed_at');
            $table->timestamps();
            $table->unique(['lesson_id', 'user_id']);
        });

        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->json('criteria'); // beoordelingslijst: [{label, hint}]
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('open'); // open, ingeleverd, voldoende, verbeteren, onvoldoende
            $table->unsignedTinyInteger('attempt')->default(1);
            $table->dateTime('due_at')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->json('scores')->nullable(); // per criterium 1 tot 5
            $table->decimal('average', 3, 1)->nullable();
            $table->text('feedback')->nullable();
            $table->foreignId('assessor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('assessed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assignment_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('exam_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('attempt')->default(1);
            $table->string('label')->nullable(); // bij het examen: Video 1, 2, 3
            $table->string('link')->nullable();
            $table->string('disk', 20)->nullable();
            $table->string('path')->nullable();
            $table->string('mime', 60)->nullable();
            $table->text('note')->nullable();
            $table->json('scores')->nullable(); // oefenopdracht: per criterium 1 tot 5
            $table->text('feedback')->nullable();
            $table->foreignId('assessor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('assessed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('number', 30)->unique();
            $table->string('verification_code', 40)->unique();
            $table->dateTime('issued_at');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
        Schema::dropIfExists('submissions');
        Schema::dropIfExists('exams');
        Schema::dropIfExists('assignments');
        Schema::dropIfExists('lesson_progress');
        Schema::dropIfExists('lessons');
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('course_days');
    }
};
