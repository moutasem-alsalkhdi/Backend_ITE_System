<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    use HasFactory;

    protected $table = 'schedules';
    public $timestamps = false; // إيقافها لأننا نستخدم created_at فقط

    protected $fillable = [
        'uploaded_by',
        'title',
        'image_url',
        'target_year',
        'academic_year',
        'semester',
    ];

    protected $casts = [
        'target_year' => 'integer',
        'semester'    => 'integer',
        'created_at'  => 'datetime',
    ];
}