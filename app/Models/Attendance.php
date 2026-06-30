<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'course_id',
        'session_type',
        'scanned_by',
        'total_sessions',
        'lecture_number',
        'attended_at'
    ];

    // علاقة: سجل الحضور ينتمي إلى طالب
    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    // علاقة: سجل الحضور ينتمي إلى مادة
    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    // علاقة: سجل الحضور يوثق المستخدم الذي قام بالمسح (الدكتور/المعيد)
    public function scanner()
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }
}
