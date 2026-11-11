<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Subjects (courses) a program offers, e.g. "IT 101 – Introduction to Computing".
 * A subject may name another subject as its prerequisite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 120);
            $table->unsignedTinyInteger('units');
            $table->string('department', 80);
            $table->text('description')->nullable();
            $table->foreignId('prerequisite_id')->nullable()
                ->constrained('subjects')->nullOnDelete();
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subjects');
    }
};
