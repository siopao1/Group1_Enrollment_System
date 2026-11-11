<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot table: which course offerings (subjects/sections) an enrollment includes.
 * Enrollment ↔ CourseOffering is a many-to-many relationship.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollment_offering', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
            // An offering that students are enrolled in cannot be deleted.
            $table->foreignId('course_offering_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['enrollment_id', 'course_offering_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_offering');
    }
};
