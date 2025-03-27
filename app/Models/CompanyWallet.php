<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyWallet extends Model
{
    use HasFactory;

    protected $table = 'company_wallets';

    protected $fillable = ['network', 'address'];
}
