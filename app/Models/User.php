<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'email_verified_at',
        'phone_number',
        'avatar',
        'bio',
        'last_seen_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
    

    public function authProviders(): HasMany
    {
        return $this->hasMany(AuthProvider::class);
    }
    public function conversations(): BelongsToMany
{
    return $this->belongsToMany(Conversation::class, 'conversation_participants')
        ->withPivot(['role', 'joined_at', 'left_at', 'muted_until', 'last_read_message_id'])
        ->withTimestamps();
}

public function sentMessages(): HasMany
{
    return $this->hasMany(Message::class, 'sender_id');
}
}