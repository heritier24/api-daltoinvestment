<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyROI extends Model
{
    use HasFactory;

    protected $table = 'daily_r_o_i_s';

    protected $fillable = [
        'user_id',
        'deposit_id',
        'amount',
        'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function deposit()
    {
        return $this->belongsTo(Deposit::class);
    }
}
