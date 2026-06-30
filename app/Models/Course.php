<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    use HasFactory;

    // اسم الجدول في قاعدة البيانات
    protected $table = 'courses';

    // إلغاء التايمستامب الافتراضي لعدم وجود حقول created_at و updated_at
    public $timestamps = false;

    // الحقول المسموح تعبئتها جماعياً
    protected $fillable = [
        'name',
        'has_lab',
        'year_of_study',
    ];

    // عمل Cast للمتغيرات لتعود بأنواعها الصحيحة بدلاً من نصوص
    protected $casts = [
        'has_lab' => 'boolean',
        'year_of_study' => 'integer',
    ];

    /**
     * علاقة المادة بالملفات والمحاضرات (المادة تملك العديد من الملفات)
     */
    public function lectureFiles(): HasMany
    {
        return $this->hasMany(LectureFile::class, 'course_id');
    }
    // جلب الدكاترة والمعيدين المسندين للمادة مع الفلاتر المخصصة
    public function assignedStaff()
    {
        return $this->belongsToMany(User::class, 'course_assignments')
            ->withPivot('section_type', 'academic_year', 'semester')
            ->withTimestamps();
    }
}
