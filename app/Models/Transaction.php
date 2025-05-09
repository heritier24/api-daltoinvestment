<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $table = 'transactions';

    protected $fillable = [
        'user_id',
        'amount',
        'type',
        'status',
        'network',
        'reference_number',
        'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function referralFees()
    {
        return $this->hasMany(ReferralFees::class, 'transaction_id');
    }
}
