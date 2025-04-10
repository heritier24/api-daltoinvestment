<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReferralFees extends Model
{
    use HasFactory;

    protected $table = 'referral_fees';

    protected $fillable = ['referrer_id', 'referred_user_id', 'transaction_id', 'deposit_amount', 'fee_amount'];

    public function referrer()
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    public function referredUser()
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }
}
