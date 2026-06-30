<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtpLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'otp_code',
        'reference_token',
        'is_used',
        'expires_at',
        'created_at',
    ];

    protected $casts = [
        'is_used'    => 'boolean',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}