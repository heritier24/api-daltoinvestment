<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Interest extends Model
{
    use HasFactory;

    protected $table = 'interests';

    protected $fillable = [
        'user_id',
        'amount',
        'rate',
        'base_amount',
        'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
