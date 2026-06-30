<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LectureFile extends Model
{
    use HasFactory;

    protected $table = 'lecture_files';

    // إلغاء التايمستامب الافتراضي لأنك تستخدم حقل مخصص وهو uploaded_at
    public $timestamps = false;

    protected $fillable = [
        'course_id',
        'uploaded_by',
        'title',
        'file_url',
        'uploader_type',
        'academic_year',
        'is_archived',
        'uploaded_at',
    ];

    protected $casts = [
        'course_id' => 'integer',
        'uploaded_by' => 'integer',
        'is_archived' => 'boolean',
        'uploaded_at' => 'datetime', // لمعاملة الحقل كـ Carbon Instance تلقائياً
    ];

    /**
     * علاقة الملف بالمادة (كل ملف ينتمي لمادة واحدة محددة)
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    /**
     * علاقة الملف بالمستخدم/الدكتور الذي قام برفعه
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}