<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** An academic program from the main app's `programs` table. */
class Program extends Model
{
    public $timestamps = false;

    protected $fillable = ['name'];

    public function students(): HasMany
    {
        return $this->hasMany(Student::class, 'program_id');
    }
}
