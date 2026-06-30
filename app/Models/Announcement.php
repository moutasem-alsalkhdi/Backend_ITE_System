<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    use HasFactory;

    // 1. تحديد اسم الجدول في قاعدة البيانات
    protected $table = 'announcements';

    // 2. إيقاف الطوابع الزمنية الافتراضية لأن الجدول لا يحتوي على updated_at 🎯
    public $timestamps = false;

    // 3. الحقول المسموح بتعبئتها جماعياً (Mass Assignable)
    protected $fillable = [
        'sender_id',
        'title',
        'content',
        'media_url',
        'course_id',
        'target_year',
        'is_permanent',
        'expires_at',
    ];

    // 4. تحويل أنواع البيانات تلقائياً عند جلبها (Casting)
    protected $casts = [
        'is_permanent' => 'boolean',
        'target_year'  => 'integer',
        'expires_at'   => 'datetime',
        'created_at'   => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | العلاقات (Relationships) - اختيارية ولكن مفيدة جداً إن احتجتها مستقبلاً
    |--------------------------------------------------------------------------
    */

    /**
     * علاقة الإعلان بالناشر (الدكتور أو الإدمن)
     */
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * علاقة الإعلان بالمادة (إذا كان مرتبطاً بمادة معينة)
     */
    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }
}