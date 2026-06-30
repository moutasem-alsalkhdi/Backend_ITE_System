<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\wallet;

class User extends Authenticatable
{
    public function lectureFiles(): HasMany
    {
        return $this->hasMany(LectureFile::class, 'uploaded_by');
    }
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'university_id',
        'name',
        'email',
        'role',
        'year_of_study',
        'group_number',
        'exam_number',
        'qr_code',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }
    public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }
    // جلب الدكاترة والمعيدين المسندين للمادة مع الفلاتر المخصصة
    public function assignedStaff()
    {
        return $this->belongsToMany(User::class, 'course_assignments')
            ->withPivot('section_type', 'academic_year', 'semester')
            ->withTimestamps();
    }
}
