<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Exception;

class AnnouncementController extends Controller
{
    /**
     * نشر إعلان جديد من قبل الدكتور أو الإدارة (يدعم الصور والملفات)
     * POST /api/academic/announcements
     */
    public function createAnnouncement(Request $request)
    {
        // 1. التحقق من صحة البيانات المدخلة
        $request->validate([
            'title'        => 'required|string|max:255',
            'content'      => 'nullable|string',
            'media_file'   => 'nullable|file|mimes:jpg,jpeg,png,pdf,docx|max:5120', // حد أقصى 5 ميجا بايت
            'course_id'    => 'nullable|integer',
            'target_year'  => 'nullable|integer|between:1,5',
            'is_permanent' => 'nullable|boolean', // سنتحقق من قيمته المنطقية بالأسفل
            'expires_at'   => 'nullable|date|after:now',
        ]);

        $user = Auth::user();

        // 2. فحص الصلاحيات (السماح للمسؤولين والدكاترة فقط بالنشر)
        if (!in_array($user->role, ['admin', 'doctor'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'غير مصرح لك بنشر الإعلانات الأكاديمية.'
            ], 403);
        }

        try {
            // 3. معالجة رفع الملف المرفق (صورة أو تنويه نصي PDF) إن وجد
            $mediaPath = null;
            if ($request->hasFile('media_file')) {
                $mediaPath = $request->file('media_file')->store('announcement_media', 'public');
            }

            // 4. إدخال الإعلان إلى قاعدة البيانات باستخدام الـ Query Builder المباشر
            DB::table('announcements')->insert([
                'sender_id'    => $user->id,
                'title'        => $request->input('title'),
                'content'      => $request->input('content'),
                'media_url'    => $mediaPath,
                'course_id'    => $request->input('course_id'),
                'target_year'  => $request->input('target_year'),
                // معالجة المدخلات البوليانية القادمة من الـ form-data وتحويلها لـ 0 أو 1
                'is_permanent' => $request->boolean('is_permanent') ? 1 : 0,
                'expires_at'   => $request->input('expires_at'),
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => 'تم نشر الإعلان الأكاديمي بنجاح للطلاب المستهدفين.'
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'حدث خطأ أثناء معالجة نشر الإعلان: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * جلب الإعلانات مع تصفية ذكية حسب دور المستخدم
     * GET /api/announcements
     */
    public function getAnnouncements(Request $request)
    {
        try {
            $user = Auth::user();

            $query = DB::table('announcements')
                ->join('users', 'announcements.sender_id', '=', 'users.id')
                ->select(
                    'announcements.id',
                    'announcements.title',
                    'announcements.content',
                    'announcements.media_url',
                    'announcements.course_id',
                    'announcements.target_year',
                    'announcements.is_permanent',
                    'announcements.expires_at',
                    'announcements.created_at',
                    'users.name as sender_name',
                    'users.role as sender_role'
                );

            // 1. فلترة حسب الدور
            if ($user->role === 'student') {
                // الطالب يرى إعلانات سنته أو الإعلانات العامة (target_year = null)
                $query->where(function ($q) use ($user) {
                    $q->whereNull('announcements.target_year')
                        ->orWhere('announcements.target_year', $user->year_of_study);
                });

                // إخفاء الإعلانات المنتهية غير الدائمة تلقائياً للطالب
                $query->where(function ($q) {
                    $q->where('announcements.is_permanent', true)
                        ->orWhereNull('announcements.expires_at')
                        ->orWhere('announcements.expires_at', '>', now());
                });
            }

            $announcements = $query->orderBy('announcements.id', 'desc')->get();

            // تحويل media_url إلى رابط كامل
            $announcements->transform(function ($item) {
                if ($item->media_url) {
                    $item->media_url = asset('storage/' . $item->media_url);
                }
                return $item;
            });

            return response()->json([
                'status' => 'success',
                'count'  => $announcements->count(),
                'data'   => $announcements,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'حدث خطأ أثناء جلب الإعلانات: ' . $e->getMessage()
            ], 500);
        }
    }
}
