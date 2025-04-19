<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone_number',
        'password',
        'promocode',
        'role',
        'networkaddress',
        'usdt_wallet',
        'referred_by',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'role' => 'string',
    ];

    // Relationship for the referrer
    public function referrer()
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function referralFees()
    {
        return $this->hasMany(ReferralFees::class, 'referred_user_id');
    }

    public function notifications()
    {
        return $this->belongsToMany(Notifications::class, 'notification_user', 'user_id', 'notification_id')
            ->withPivot('is_read', 'read_at')
            ->withTimestamps();
    }
}
