<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MembershipFees extends Model
{
    use HasFactory;

    protected $table = 'membership_fees';

    protected $fillable = [
        'user_id',
        'amount',
        'network',
        'wallet_address',
        'status',
        'reference_number',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
