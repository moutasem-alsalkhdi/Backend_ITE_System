<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseDeadline extends Model
{
    use HasFactory;

    protected $table = 'course_deadlines';
    public $timestamps = false; // نستخدم created_at فقط

    protected $fillable = [
        'course_id',
        'request_type',
        'end_date',
    ];

    protected $casts = [
        'end_date'   => 'datetime',
        'created_at' => 'datetime',
    ];
}