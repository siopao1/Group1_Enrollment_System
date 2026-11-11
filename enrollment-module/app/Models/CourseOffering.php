<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CourseOffering extends Model
{
    public const SEMESTERS = ['1st Semester', '2nd Semester', 'Summer'];

    public const ACADEMIC_YEARS = ['2026–2027', '2025–2026'];

    public const STATUSES = ['Open', 'Closed'];

    protected $fillable = [
        'subject_id', 'section', 'semester', 'academic_year',
        'instructor', 'room', 'schedule', 'slots', 'status',
    ];

    protected $casts = ['slots' => 'integer'];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /** Enrollments that include this offering (many-to-many through enrollment_offering). */
    public function enrollments(): BelongsToMany
    {
        return $this->belongsToMany(Enrollment::class, 'enrollment_offering')->withTimestamps();
    }

    /** Adds "enrolled_count": students holding a seat (dropped enrollments don't count). */
    public function scopeWithEnrolledCount(Builder $query): Builder
    {
        return $query->withCount([
            'enrollments as enrolled_count' => fn (Builder $q) => $q->where('enrollments.status', '!=', 'Dropped'),
        ]);
    }

    public function scopeForTerm(Builder $query, string $semester, string $academicYear): Builder
    {
        return $query->where('semester', $semester)->where('academic_year', $academicYear);
    }

    public function enrolledCount(): int
    {
        return $this->enrolled_count
            ?? $this->enrollments()->where('enrollments.status', '!=', 'Dropped')->count();
    }

    public function seatsLeft(): int
    {
        return max(0, $this->slots - $this->enrolledCount());
    }

    /** "IT 101 – A" */
    public function label(): string
    {
        return "{$this->subject->code} – {$this->section}";
    }
}
