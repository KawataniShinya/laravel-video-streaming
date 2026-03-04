<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function allowedPaths()
    {
        return $this->hasMany(UserAllowedPath::class);
    }

    /**
     * Check if user can access the given path.
     */
    public function canAccessPath($path): bool
    {
        if ($this->role === 'admin') {
            return true;
        }

        $allowed = $this->allowedPaths()->pluck('path')->toArray();
        if (empty($allowed)) {
            return false;
        }

        // Clean path for comparison
        $path = trim($path, '/');

        // Case 0: If requesting root, allow if ANY path is authorized
        if ($path === '') {
            return true;
        }

        foreach ($allowed as $allowedPath) {
            $allowedPath = trim($allowedPath, '/');
            
            // Case 1: Direct access or child access (Allowed: 'A', Request: 'A' or 'A/B')
            if ($allowedPath === '' || $path === $allowedPath || str_starts_with($path, $allowedPath . '/')) {
                return true;
            }

            // Case 2: Parent access (Allowed: 'A/B/C', Request: 'A' or 'A/B')
            // This is needed to let user navigate THROUGH folders to reach their target.
            if (str_starts_with($allowedPath, $path . '/')) {
                return true;
            }
        }

        return false;
    }
}
