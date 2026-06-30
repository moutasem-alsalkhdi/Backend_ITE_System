<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceRequest extends Model
{
    use HasFactory;

    protected $table = 'service_requests';
    
    // تفعيل الـ timestamps لأنك أضفتها في الـ Schema
    public $timestamps = true;

    protected $fillable = [
        'student_id',
        'course_id',
        'request_type',
        'status',
        'fee_amount',
        'admin_note',
        'window_deadline',
        'payment_deadline',
    ];

    protected $casts = [
        'fee_amount'       => 'decimal:2',
        'window_deadline'  => 'datetime',
        'payment_deadline' => 'datetime',
        'created_at'       => 'datetime',
        'updated_at'       => 'datetime',
    ];
}