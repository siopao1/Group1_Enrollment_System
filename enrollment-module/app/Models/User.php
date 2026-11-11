<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * An account from the main app's `users` table (backend/database/schema.sql).
 * Both apps sign in with the same usernames and passwords: the password is
 * stored in `password_hash` as bcrypt, which PHP's password_hash() and
 * Laravel's Hash facade both read.
 */
class User extends Authenticatable
{
    public const ROLES = ['admin', 'registrar', 'faculty'];

    public const ROLE_LABELS = [
        'admin' => 'Administrator',
        'registrar' => 'Registrar',
        'faculty' => 'Faculty',
    ];

    /** The table has created_at but no updated_at column. */
    public const UPDATED_AT = null;

    /** Tell Laravel's authentication which column holds the hash. */
    protected $authPasswordName = 'password_hash';

    /** The table has no remember_token column, so "remember me" is not used. */
    protected $rememberTokenName = '';

    protected $fillable = ['username', 'full_name', 'email', 'password_hash', 'role'];

    protected $hidden = ['password_hash'];

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function roleLabel(): string
    {
        return self::ROLE_LABELS[$this->role] ?? $this->role;
    }

    public function enrollmentsCreated()
    {
        return $this->hasMany(Enrollment::class, 'enrolled_by');
    }
}
