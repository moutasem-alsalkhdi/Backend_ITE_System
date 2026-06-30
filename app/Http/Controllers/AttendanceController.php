<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Attendance;
use App\Models\User;
use Exception;

class AttendanceController extends Controller
{
    /**
     * دالة recordAttendance: لتسجيل حضور الطالب عند مسح الـ QR
     */
    // أضف هذه الدالة الجديدة:
    public function recordAttendanceByQr(Request $request)
    {
        $request->validate([
            'qr_code'       => 'required|string|exists:users,qr_code', // ✅ نبحث عن qr_code بجدول users
            'course_id'     => 'required|integer|exists:courses,id',
            'session_type'  => 'required|in:theory,lab',
            'total_sessions' => 'required|integer',
            'lecture_number' => 'required|string',
        ]);

        try {
            // جلب الطالب من خلال qr_code
            $student = User::where('qr_code', $request->qr_code)->first();

            if (!$student || $student->role !== 'student') {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'رمز QR غير صحيح أو لا ينتمي لطالب'
                ], 401);
            }

            // التحقق من عدم التكرار
            $alreadyRecorded = Attendance::where('student_id', $student->id)
                ->where('course_id', $request->course_id)
                ->where('lecture_number', $request->lecture_number)
                ->exists();

            if ($alreadyRecorded) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'تم تسجيل حضور هذا الطالب في هذه المحاضرة بالفعل!'
                ], 409);
            }

            // إدخال بيانات الحضور
            $attendance = Attendance::create([
                'student_id'     => $student->id,
                'course_id'      => $request->course_id,
                'session_type'   => $request->session_type,
                'scanned_by'     => Auth::id(),
                'total_sessions' => $request->total_sessions,
                'lecture_number' => $request->lecture_number,
                'attended_at'    => now(),
            ]);

            return response()->json([
                'status'        => 'success',
                'message'       => "تم تسجيل حضور الطالب ({$student->name}) بنجاح",
                'student_name'  => $student->name,
                'data'          => $attendance
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'حدث خطأ أثناء تسجيل الحضور: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getStudentAttendance(Request $request)
    {
        try {
            // 1. جلب الـ ID الخاص بالطالب الحالي تلقائياً من الـ Token
            $studentId =  Auth::id();

            // 2. تجهيز الاستعلام وجلب العلاقات
            $query = Attendance::with(['course', 'scanner:id,name'])
                ->where('student_id', $studentId);

            // 3. التصفية الاختيارية بمادة معينة إذا أرسلها التطبيق
            if ($request->has('course_id')) {
                $request->validate([
                    'course_id' => 'integer|exists:courses,id'
                ]);
                $query->where('course_id', $request->query('course_id'));
            }

            // 4. جلب كل السجلات مرتبة من الأحدث للأقدم
            $attendanceRecords = $query->orderBy('attended_at', 'desc')->get();

            // 5. 🔥 حساب عدد الحضور لكل مادة على حدا بشكل ديناميكي ذكي
            $attendanceSummary = $attendanceRecords->groupBy('course_id')->map(function ($group) {
                return [
                    'course_id'         => $group->first()->course->id,
                    'course_name'       => $group->first()->course->name,
                    'attended_sessions' => $group->count(), // عداد الحضور الخاص بهذه المادة فقط
                ];
            })->values(); // تحويل المفاتيح إلى مصفوفة مرتبة تعود للـ JSON

            // 6. إرجاع الرد المنظم الجديد
            return response()->json([
                'status'             => 'success',
                'attendance_summary' => $attendanceSummary, // عداد مخصص لكل مادة على حدا 🎯
                'detailed_records'   => $attendanceRecords   // السجل التفصيلي لجميع المحاضرات
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'حدث خطأ أثناء جلب سجل الحضور الخاص بك: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * دالة getLectureAttendance: جلب قائمة أسماء الطلاب الحاضرين في محاضرة/جلسة معينة للدكتور
     */
    public function getLectureAttendance(Request $request)
    {
        // 1. التحقق من إرسال معاملات الفلترة الأساسية لتحديد المحاضرة بدقة
        $request->validate([
            'course_id'      => 'required|integer|exists:courses,id',
            'session_type'   => 'required|in:theory,lab',
            'lecture_number' => 'required|string',
        ]);

        try {
            // 2. الاستعلام من جدول الحضور وجلب علاقة الطالب (Student) مع تحديد الحقول المطلوبة فقط لسرعة الأداء
            $attendanceRecords = Attendance::with(['student:id,name,university_id,group_number,exam_number'])
                ->where('course_id', $request->input('course_id'))
                ->where('session_type', $request->input('session_type'))
                ->where('lecture_number', $request->input('lecture_number'))
                ->orderBy('created_at', 'asc') // الترتيب حسب وقت مسح الكود (الأقدم فالأحدث - من حضر أولاً يظهر أولاً)
                ->get();

            // 3. إعادة هيكلة البيانات (Mapping) لتظهر مصفوفة الطلاب بشكل نظيف ومباشر للـ Frontend
            $studentsList = $attendanceRecords->map(function ($record) {
                // تأمين بحال عدم وجود سجل الطالب بالخطأ
                if (!$record->student) return null;

                return [
                    'student_id'    => $record->student->id,
                    'name'          => $record->student->name,
                    'university_id' => $record->student->university_id,
                    'group_number'  => $record->student->group_number,
                    'exam_number'   => $record->student->exam_number, // يظهر هنا الحقل المصلح بنجاح 👍
                    'attended_at'   => $record->created_at ? $record->created_at->format('H:i:s') : null, // وقت الحضور الدقيق (ساعة:دقيقة:ثانية)
                ];
            })->filter()->sortBy('name')->values(); // تصفية أي قيم فارغة وإعادة ترتيب المؤشرات

            // 4. إرجاع الرد النهائي مع معلومات الجلسة والعدد الإجمالي للطلاب الحاضرين
            return response()->json([
                'status' => 'success',
                'session_info' => [
                    'course_id'      => $request->input('course_id'),
                    'session_type'   => $request->input('session_type'),
                    'lecture_number' => $request->input('lecture_number'),
                    'total_present'  => $studentsList->count(), // إجمالي عدد الطلاب الحاضرين في القاعة حالياً
                ],
                'students' => $studentsList
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'حدث خطأ أثناء جلب قائمة الحضور للطلبة: ' . $e->getMessage()
            ], 500);
        }
    }
}
