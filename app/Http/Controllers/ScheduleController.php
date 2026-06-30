<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Exception;
use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Support\Facades\Notification;

class ScheduleController extends Controller
{
    /**
     * 1. رفع جدول دراسي جديد (خاص بالإدارة/الأساتذة المخولين)
     * POST /api/admin/schedules
     */
    public function uploadSchedule(Request $request)
    {
        // التحقق من المدخلات
        $request->validate([
            'title'         => 'required|string|max:255',
            'image_file'    => 'required|image|mimes:jpeg,png,jpg,webp|max:4096',
            'target_year'   => 'required|integer|between:1,5',
            'academic_year' => 'required|string',
            'semester'      => 'required|integer|between:1,3',
        ]);

        // فحص الصلاحية
        if (Auth::user()->role !== 'admin') {
            return response()->json(['status' => 'error', 'message' => 'غير مصرح لك برفع الجداول الدراسية.'], 403);
        }

        try {
            $targetYear   = $request->input('target_year');
            $academicYear = $request->input('academic_year');
            $semester     = $request->input('semester');

            // 🎯 1. البحث عن جدول موجود مسبقاً يطابق نفس الفئة المستهدفة تماماً
            $existingSchedule = DB::table('schedules')
                ->where('target_year', $targetYear)
                ->where('academic_year', $academicYear)
                ->where('semester', $semester)
                ->first();

            // 2. تخزين الصورة الجديدة في مجلد public/schedules
            $imagePath = $request->file('image_file')->store('schedules', 'public');

            if ($existingSchedule) {
                // 🎯 3. أسلوب احترافي: حذف ملف الصورة القديمة من الهارد ديسك للسيرفر حتى لا تملأ المساحة بملفات مهملة
                if (Storage::disk('public')->exists($existingSchedule->image_url)) {
                    Storage::disk('public')->delete($existingSchedule->image_url);
                }

                // 🎯 4. تحديث السجل الموجود مسبقاً بالبيانات والصورة الجديدة
                DB::table('schedules')
                    ->where('id', $existingSchedule->id)
                    ->update([
                        'uploaded_by' => Auth::id(),
                        'title'       => $request->input('title'),
                        'image_url'   => $imagePath,
                        'created_at'  => now(), // تحديث الوقت ليظهر للطالب متى تم آخر تحديث للجدول
                    ]);

                $message = 'تم تحديث الجدول الدراسي الحالي بنجاح وحذف الجدول القديم.';

                $notificationTitle = 'تم تحديث الجدول الدراسي';
                $student = User::where('role', 'student')->get();
            if ($student) {
                Notification::send($student, new SystemNotification($notificationTitle, ""));
            }
                $statusCode = 200;
            } else {
                // 5. إذا لم يكن هناك جدول سابق لهذه الفئة، نقوم بعملية إدخال (Insert) جديدة
                DB::table('schedules')->insert([
                    'uploaded_by'   => Auth::id(),
                    'title'         => $request->input('title'),
                    'image_url'     => $imagePath,
                    'target_year'   => $targetYear,
                    'academic_year' => $academicYear,
                    'semester'      => $semester,
                    'created_at'    => now(),
                ]);

                $message = 'تم رفع وتصنيف الجدول الدراسي بنجاح أول مرة.';
                $notificationTitle = 'تم رفع الجدول الدراسي';
                $student = User::where('role', 'student')->get();
            if ($student) {
                Notification::send($student, new SystemNotification($notificationTitle, ""));
            }
                $statusCode = 201;
            }
            

            return response()->json([
                'status'  => 'success',
                'message' => $message
            ], $statusCode);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'حدث خطأ أثناء معالجة الجدول: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 2. استعراض الجداول الدراسية (للطالب مع ميزة التصفح المرن)
     * GET /api/student/schedules
     */
    public function getSchedules(Request $request)
    {
        try {
            $user = Auth::user();

            // تحقيق "التوجيه التلقائي والمرونة": 
            // إذا أرسل الطالب سنة معينة في الـ Params نقوم بفلترتها (تصفح مرن للمواد المحمولة)
            // وإذا لم يرسل، يقوم النظام تلقائياً باعتماد سنته الدراسية الحالية المسجلة في حسابه (توجيه تلقائي)
            $targetYear = $request->query('target_year') ?? ($user->year_of_study ?? 1);

            // بناء الاستعلام لجلب الجداول المتاحة لهذه السنة الدراسية
            $query = DB::table('schedules')
                ->join('users', 'schedules.uploaded_by', '=', 'users.id')
                ->where('schedules.target_year', $targetYear)
                ->select(
                    'schedules.id',
                    'schedules.title',
                    'schedules.image_url',
                    'schedules.target_year',
                    'schedules.academic_year',
                    'schedules.semester',
                    'schedules.created_at',
                    'users.name as uploaded_by_name'
                );

            // فلاتر إضافية اختيارية (حسب الفصل أو السنة الأكاديمية)
            if ($request->has('semester')) {
                $query->where('schedules.semester', $request->query('semester'));
            }
            if ($request->has('academic_year')) {
                $query->where('schedules.academic_year', $request->query('academic_year'));
            }

            // جلب الجداول (الأحدث دائماً أولاً)
            $schedules = $query->orderBy('schedules.id', 'desc')->get();

            // تعديل الرابط ليظهر للمطور كرابط كامل ومباشر للصورة (Full URL)
            foreach ($schedules as $schedule) {
                $schedule->image_url = asset('storage/' . $schedule->image_url);
            }

            return response()->json([
                'status'        => 'success',
                'viewing_year'  => (int)$targetYear, // لإخبار الواجهة بالسنة المعروضة حالياً
                'count'         => $schedules->count(),
                'data'          => $schedules
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'حدث خطأ أثناء جلب الجداول الدراسية: ' . $e->getMessage()
            ], 500);
        }
    }
}
