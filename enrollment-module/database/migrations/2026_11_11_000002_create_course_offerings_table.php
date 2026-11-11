<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A section of a subject offered in a given semester and academic year,
 * with its instructor, room, schedule and number of slots (capacity).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_offerings', function (Blueprint $table) {
            $table->id();
            // A subject that still has offerings cannot be deleted.
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->string('section', 20);
            $table->enum('semester', ['1st Semester', '2nd Semester', 'Summer']);
            $table->string('academic_year', 20);
            $table->string('instructor', 100)->nullable();
            $table->string('room', 40)->nullable();
            $table->string('schedule', 100)->nullable();
            $table->unsignedSmallInteger('slots')->default(40);
            $table->enum('status', ['Open', 'Closed'])->default('Open');
            $table->timestamps();

            // One section code per subject per term.
            $table->unique(['subject_id', 'section', 'semester', 'academic_year'], 'course_offerings_section_unique');
            $table->index(['academic_year', 'semester']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_offerings');
    }
};
