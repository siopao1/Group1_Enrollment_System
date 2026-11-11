<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    public const STATUSES = ['Active', 'Inactive'];

    public const DEPARTMENTS = ['Information Technology', 'Computer Science', 'Information Systems', 'General Education'];

    protected $fillable = ['code', 'name', 'units', 'department', 'description', 'prerequisite_id', 'status'];

    protected $casts = ['units' => 'integer'];

    /** A subject has many offerings (sections), one per term and section code. */
    public function offerings(): HasMany
    {
        return $this->hasMany(CourseOffering::class);
    }

    /** The subject that must be taken first, if any. */
    public function prerequisite(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'prerequisite_id');
    }

    /** Subjects that list this one as their prerequisite. */
    public function requiredBy(): HasMany
    {
        return $this->hasMany(Subject::class, 'prerequisite_id');
    }
}
