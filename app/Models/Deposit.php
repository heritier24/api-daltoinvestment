<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Deposit extends Model
{
    use HasFactory;

    protected $table = 'deposits';

    protected $fillable = [
        'user_id',
        'network',
        'amount',
        'status',
        'reference_number',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
