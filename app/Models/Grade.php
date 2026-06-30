<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Grade extends Model
{
    use HasFactory;

    // 💡 تنبيه: بما أن الجدول يحتوي على recorded_at بدلاً من created_at و updated_at التقليديين، نوقف التوقيت الافتراضي لارافيل
    public $timestamps = false;

    // الأعمدة المسموح تعبئتها جماعياً (Mass Assignment)
    protected $fillable = [
        'student_id',
        'course_id',
        'academic_year',
        'semester',
        'practical_score',
        'theoretical_score',
        'total_score',
        'status',
        'modified_by',
        'recorded_at'
    ];

    // تحويل أنواع البيانات تلقائياً عند جلبها من قاعدة البيانات
    protected $casts = [
        'academic_year'     => 'integer',
        'semester'          => 'integer',
        'practical_score'   => 'float',
        'theoretical_score' => 'float',
        'total_score'       => 'float',
        'recorded_at'       => 'datetime',
    ];

    /**
     * علاقة العلامة بالطالب (ينتمي إلى مستخدم)
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * علاقة العلامة بالمادة الدراسية
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    /**
     * علاقة العلامة بالشخص (الدكتور/المشرف) الذي قام برصدها أو تعديلها
     */
    public function modifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modified_by');
    }
}