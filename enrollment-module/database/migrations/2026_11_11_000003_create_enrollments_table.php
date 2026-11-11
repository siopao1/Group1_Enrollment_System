<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A student's enrollment for one term (semester + academic year).
 * The subjects taken that term are linked through enrollment_offering.
 *
 * student_id and enrolled_by point at tables created by the main PHP app
 * (backend/database/schema.sql), so their column types match those tables:
 * students.student_id is VARCHAR(20) and users.id is a signed INT.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->string('student_id', 20);
            $table->enum('semester', ['1st Semester', '2nd Semester', 'Summer']);
            $table->string('academic_year', 20);
            $table->enum('status', ['Pending', 'Confirmed', 'Active', 'Dropped', 'Completed'])->default('Pending');
            $table->date('enrolled_at');
            $table->integer('enrolled_by')->nullable();
            $table->timestamps();

            // Deleting a student in the main app removes their enrollments too.
            $table->foreign('student_id')->references('student_id')->on('students')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('enrolled_by')->references('id')->on('users')->nullOnDelete();

            // At most one enrollment per student per term.
            $table->unique(['student_id', 'semester', 'academic_year'], 'enrollments_student_term_unique');
            $table->index(['academic_year', 'semester']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
