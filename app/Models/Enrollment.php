<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Enrollment extends Model
{
   use HasFactory;

    protected $table = 'enrollments';
    public $timestamps = false; // إيقافها لأننا نستخدم created_at فقط

    protected $fillable = [
        'student_id',
        'course_id',
        'academic_year',
        'semester',
        'created_at', // لتسجيل وقت التسجيل
    ];

    protected $casts = [
        'student_id' => 'integer',
        'course_id' => 'integer',
        'created_at' => 'datetime', // لمعاملة الحقل كـ Carbon Instance تلقائياً
    ];
}
