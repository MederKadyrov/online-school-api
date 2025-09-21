<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, Notifiable, HasRoles, SoftDeletes, HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'last_name',
        'first_name',
        'middle_name',
        'email',
        'phone',
        'sex',
        'pin',
        'citizenship',
        'address',
        'password',
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

    public function student()
    {
        return $this->hasOne(Student::class);
    }
    public function guardian()
    {
        return $this->hasOne(Guardian::class);
    }

    public function teacher() { return $this->hasOne(Teacher::class); }

    // Чтобы старый $user->name продолжал работать:
    public function getNameAttribute(): string
    {
        $parts = array_filter([$this->last_name, $this->first_name, $this->middle_name]);
        if (!empty($parts)) {
            return implode(' ', $parts);
        }
        // fallback на старое поле name, если ФИО ещё не заполнены
        return (string) ($this->attributes['name'] ?? '');
    }

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
}
