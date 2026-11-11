<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A student from the main app's `students` table. Students are created and
 * edited in the main app; this module reads them and enrolls them.
 */
class Student extends Model
{
    protected $primaryKey = 'student_id';

    public $incrementing = false;

    protected $keyType = 'string';

    /** The table has created_at but no updated_at column. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'student_id', 'first_name', 'middle_name', 'last_name', 'suffix', 'email',
        'contact_number', 'address', 'program_id', 'year_level', 'academic_year', 'status',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class, 'program_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'student_id', 'student_id');
    }

    public function getFullNameAttribute(): string
    {
        return trim(implode(' ', array_filter([
            $this->first_name, $this->middle_name, $this->last_name, $this->suffix,
        ])));
    }
}
