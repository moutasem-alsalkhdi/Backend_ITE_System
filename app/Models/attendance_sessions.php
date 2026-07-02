<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class attendance_sessions extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'session_type',
        'lecture_number',
        'opened_by',
        'total_enrolled',
        'scanned_count',
        'started_at',
        "ended_at",
        "notes"
    ];
    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'opened_by');
    }
}
