<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** One student's enrollment for one term, with the offerings (subjects) taken. */
class Enrollment extends Model
{
    public const STATUSES = ['Pending', 'Confirmed', 'Active', 'Dropped', 'Completed'];

    protected $fillable = ['student_id', 'semester', 'academic_year', 'status', 'enrolled_at', 'enrolled_by'];

    protected $casts = ['enrolled_at' => 'date'];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id', 'student_id');
    }

    public function offerings(): BelongsToMany
    {
        return $this->belongsToMany(CourseOffering::class, 'enrollment_offering')->withTimestamps();
    }

    public function enrolledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enrolled_by');
    }

    /** Total units of the subjects in this enrollment (offerings.subject must be loaded). */
    public function totalUnits(): int
    {
        return (int) $this->offerings->sum(fn (CourseOffering $o) => $o->subject->units);
    }
}
