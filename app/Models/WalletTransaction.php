<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletTransaction extends Model
{
    protected $fillable = [
        'wallet_id', 'type', 'amount', 'balance_before', 
        'balance_after', 'description', 'processed_by', 'reference_type', 'reference_id'
    ];

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }
}
