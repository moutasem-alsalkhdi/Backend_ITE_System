<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Attendance;
use App\Models\User;
use App\Notifications\SystemNotification;
use Exception;

class AttendanceController extends Controller
{
    /**
     * 1. بدء جلسة حضور جديدة (نقطة البداية)
     * POST /api/attendance/session/start
     */
    public function startAttendanceSession(Request $request)
    {
        $request->validate([
            'course_id'     => 'required|integer|exists:courses,id',
            'session_type'  => 'required|in:theoretical,practical',
            'lecture_number' => 'required|string|max:50',
        ]);

        try {
            $doctoredId = Auth::id();
            $course = DB::table('courses')->where('id', $request->course_id)->first();

            // 🎯 فحص أمان: التأكد من أن الدكتور عضو تدريسي في هذه المادة

            $isCourseInstructor = DB::table('course_assignments')
                ->where('user_id', $doctoredId)
                ->where('course_id', $request->course_id)
                ->where('section_type', $request->session_type)
                ->exists();

            if (!$isCourseInstructor && Auth::user()->role !== 'admin') {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'غير مصرح لك بتسجيل الحضور لهذه المادة.'
                ], 403);
            }

            // جلب عدد الطلاب المسجلين للمادة في الفصل الحالي
            $currentYear = DB::table('course_assignments')
                ->where('user_id', $doctoredId)
                ->where('course_id', $request->course_id)
                //->where('session_type', $request->session_type)
                ->pluck('academic_year');

            $semester = DB::table('course_assignments')
                ->where('user_id', $doctoredId)
                ->where('course_id', $request->course_id)
                //->where('session_type', $request->session_type)
                ->pluck('semester');

            $enrolledCount = DB::table('enrollments')
                ->where('course_id', $request->course_id)
                ->where('academic_year', $currentYear)
                ->where('semester', $semester)
                ->count();

            // إنشاء جلسة حضور جديدة
            $session = DB::table('attendance_sessions')->insert([
                'course_id'     => $request->course_id,
                'session_type'  => $request->session_type,
                'lecture_number' => $request->lecture_number,
                'opened_by'     => $doctoredId,
                'total_enrolled' => $enrolledCount,
                'scanned_count' => 0,
                'started_at'    => now(),
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            // جلب معرف الجلسة المُنشأة للتو
            $sessionId = DB::table('attendance_sessions')
                ->where('course_id', $request->course_id)
                ->where('session_type', $request->session_type)
                ->where('lecture_number', $request->lecture_number)
                ->where('opened_by', $doctoredId)
                ->orderBy('started_at', 'desc')
                ->first()
                ->id;

            return response()->json([
                'status' => 'success',
                'message' => 'تم بدء جلسة الحضور بنجاح.',
                'data' => [
                    'session_id'     => $sessionId,
                    'course_id'      => $request->course_id,
                    'session_type'   => $request->session_type,
                    'lecture_number' => $request->lecture_number,
                    'total_enrolled' => $enrolledCount,
                ]
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'حدث خطأ: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 2. مسح QR وتسجيل حضور الطالب
     * POST /api/attendance/record
     */
    public function recordAttendance(Request $request)
    {
        $request->validate([
            'session_id'    => 'required|integer|exists:attendance_sessions,id',
            'qr_code' => 'required|string|exists:users,qr_code',
        ]);

        try {
            $doctoredId = Auth::id();
            // جلب بيانات الجلسة
            $session = DB::table('attendance_sessions')->where('opened_by', $doctoredId)->find($request->session_id);
            if (!$session) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'جلسة الحضور غير موجودة / فقط الدكتور الذي بدأ الجلسة يمكنه تسجيل حضورك.'
                ], 404);
            }

            // التأكد من أن الجلسة لم تنتهِ بعد
            if ($session->ended_at !== null) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'انتهت جلسة الحضور بالفعل ولا يمكن إضافة طلاب جدد.'
                ], 400);
            }


            // جلب الطالب
            $student = User::where('qr_code', $request->qr_code)
                ->whereIn('role', ['student', 'volunteer'])
                ->first();

            if (!$student) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'الطالب غير موجود.'
                ], 404);
            }

            // 🎯 منع الحضور للمواد المحمولة إلا بعد إعادة العملي
            $hasFailedGrade = DB::table('grades')
                ->where('student_id', $student->id)
                ->where('course_id', $session->course_id)
                ->where('status', 'fail')
                ->exists();

            $hasLabRedo = DB::table('service_requests')
                ->where('student_id', $student->id)
                ->where('course_id', $session->course_id)
                ->where('request_type', 'lab_redo')
                ->where('status', 'completed')
                ->exists();

            if ($hasFailedGrade && !$hasLabRedo) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'لا يمكنك تسجيل حضورك بهذه المادة لأنك حامل لها. يجب أن تقوم بطلب إعادة عملي أولاً.'
                ], 403);
            }

            // التحقق من عدم تسجيل الطالب مسبقاً في نفس الجلسة
            $alreadyPresent = Attendance::where('session_id', $request->session_id)
                ->where('student_id', $student->id)
                ->exists();

            if ($alreadyPresent) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'تم تسجيل حضورك مسبقاً في هذه الجلسة.'
                ], 409);
            }

            // تسجيل الحضور
            $attendance = Attendance::create([
                'session_id'   => $request->session_id,
                'student_id'   => $student->id,
                'course_id'    => $session->course_id,
                'session_type' => $session->session_type,
                'scanned_by'   => Auth::id(),
                'lecture_number' => $session->lecture_number,
                'attended_at'  => now(),
            ]);

            // زيادة عداد الطلاب الممسوحين في الجلسة
            DB::table('attendance_sessions')
                ->where('id', $request->session_id)
                ->increment('scanned_count');

            return response()->json([
                'status'  => 'success',
                'message' => 'تم تسجيل حضور الطالب بنجاح.',
                'student_name' => $student->name,
                'data'    => $attendance
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'حدث خطأ: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * 3. إنهاء جلسة الحضور وإرسال الإشعارات
     * POST /api/attendance/session/end
     */
    public function endAttendanceSession(Request $request)
    {
        $request->validate([
            'session_id' => 'required|integer|exists:attendance_sessions,id',
        ]);

        DB::beginTransaction();
        try {
            // جلب بيانات الجلسة
            $session = DB::table('attendance_sessions')->find($request->session_id);
            if (!$session) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'جلسة الحضور غير موجودة.'
                ], 404);
            }

            // التأكد من أن الدكتور هو من بدأ الجلسة
            if ($session->opened_by != Auth::id() && Auth::user()->role !== 'admin') {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'فقط الدكتور الذي بدأ الجلسة يمكنه إنهاؤها.'
                ], 403);
            }

            // إنهاء الجلسة
            DB::table('attendance_sessions')
                ->where('id', $request->session_id)
                ->update([
                    'ended_at'  => now(),
                    'updated_at' => now(),
                ]);

            // جلب جميع الطلاب المسجلين للمادة
            $currentYear = now()->year . '-' . (now()->year + 1);
            $semester = now()->month >= 9 || now()->month <= 1 ? 1 : 2;

            $enrolledStudents = DB::table('enrollments')
                ->join('users', 'enrollments.student_id', '=', 'users.id')
                ->where('enrollments.course_id', $session->course_id)
                ->where('enrollments.academic_year', $currentYear)
                ->where('enrollments.semester', $semester)
                ->select('users.id', 'users.name')
                ->get();

            // جلب الطلاب الذين تم مسح QR لهم
            $presentStudentIds = Attendance::where('session_id', $request->session_id)
                ->pluck('student_id')
                ->toArray();

            // إرسال إشعارات للطلاب الحاضرين
            foreach ($enrolledStudents as $student) {
                $studentModel = User::find($student->id);
                if ($studentModel) {
                    $course = DB::table('courses')->where('id', $session->course_id)->first();
                    $type = $session->session_type === 'theoretical' ? 'نظري' : 'عملي';

                    if (in_array($student->id, $presentStudentIds)) {
                        $title = 'تم تسجيل حضورك ✓';
                        $body = "تم تسجيل حضورك في محاضرة {$type} لمادة {$course->name} (المحاضرة: {$session->lecture_number})";
                        // إشعار للحاضرين
                        $studentModel->notify(new SystemNotification(
                            $title,
                            $body,
                            ['course_id' => $session->course_id, 'type' => 'attendance']
                        ));
                    } else {
                        $title = 'تم تسجيل غيابك ✗';
                        $body = "لم يتم تسجيل حضورك في محاضرة {$type} لمادة {$course->name} (المحاضرة: {$session->lecture_number})";
                        // إشعار للغائبين
                        $studentModel->notify(new SystemNotification(
                            $title,
                            $body,
                            ['course_id' => $session->course_id, 'type' => 'absence']
                        ));
                    }
                }
            }

            DB::commit();

            return response()->json([
                'status'  => 'success',
                'message' => 'تم إنهاء جلسة الحضور وإرسال الإشعارات للطلاب.',
                'data'    => [
                    'total_enrolled' => $session->total_enrolled,
                    'scanned_count'  => $session->scanned_count,
                    'absent_count'   => $session->total_enrolled - $session->scanned_count,
                ]
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status'  => 'error',
                'message' => 'حدث خطأ: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * (نظري وعملي) جلب سجل الحضور الكامل للطالب بناءً على المادة والنوع
     * GET /api/student/attendance/detailed
     */
    public function getDetailedAttendance(Request $request)
    {
        $request->validate([
            'course_id'    => 'required|integer|exists:courses,id',
            'session_type' => 'nullable|in:theoretical,practical',
        ]);

        try {
            $studentId = Auth::id();
            $courseId = $request->course_id;
            $sessionType = $request->session_type;

            // جلب الجلسات للمادة
            $query = DB::table('attendance_sessions')
                ->where('course_id', $courseId)
                ->where('ended_at', '!=', null); // جلسات انتهت فقط

            if ($sessionType) {
                $query->where('session_type', $sessionType);
            }

            $sessions = $query->orderBy('started_at', 'asc')->get();

            if ($sessions->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'data'   => [],
                    'message' => 'لا توجد جلسات حضور لهذه المادة بعد.'
                ], 200);
            }

            // بناء قائمة الحضور والغياب
            $attendanceRecords = [];
            foreach ($sessions as $session) {
                $isPresent = Attendance::where('session_id', $session->id)
                    ->where('student_id', $studentId)
                    ->exists();

                // جلب اسم الدكتور الذي قام بالمسح
                $doctor = DB::table('users')
                    ->where('id', $session->opened_by)
                    ->first();
                $therole = DB::table('users')
                    ->where('id', $session->opened_by)
                    ->first();

                $attendanceRecords[] = [
                    'lecture_number'  => $session->lecture_number,
                    'session_type'    => $session->session_type === 'theoretical' ? 'نظري' : 'عملي',
                    'status'          => $isPresent ? 'حاضر' : 'غائب',
                    'scanned_by'     => $doctor->name ?? '—',
                    'the_role'         => $therole->role,
                    'started_at'      => $session->started_at,
                    'ended_at'        => $session->ended_at,
                ];
            }

            // إحصائيات
            $totalSessions = count($attendanceRecords);
            $presentCount = collect($attendanceRecords)->where('status', 'حاضر')->count();
            $absentCount = $totalSessions - $presentCount;

            return response()->json([
                'status'  => 'success',
                'stats'   => [
                    'total_sessions' => $totalSessions,
                    'present_count'  => $presentCount,
                    'absent_count'   => $absentCount,
                    'percentage'     => $totalSessions > 0 ? round(($presentCount / $totalSessions) * 100, 2) : 0,
                ],
                'data'    => $attendanceRecords
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'حدث خطأ: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * جلب ملخص الحضور للطالب
     * GET /api/student/attendance
     */
    public function getStudentAttendance(Request $request)
    {
        try {
            $studentId = Auth::id();

            // كل الجلسات المنتهية (من attendance_sessions) كأساس للعد الكلي
            $totalSessions = DB::table('attendance_sessions')
                ->join('courses', 'attendance_sessions.course_id', '=', 'courses.id')
                ->where('attendance_sessions.ended_at', '!=', null)
                ->select(
                    'courses.id as course_id',
                    'courses.name as course_name',
                    'attendance_sessions.session_type',
                    DB::raw('COUNT(DISTINCT attendance_sessions.id) as total_sessions')
                )
                ->groupBy('courses.id', 'courses.name', 'attendance_sessions.session_type')
                ->get()
                ->keyBy(fn($row) => $row->course_id . '_' . $row->session_type);

            // حضور الطالب الفعلي (من جدول attendances مباشرة عبر الموديل)
            $attended = Attendance::where('student_id', $studentId)
                ->select('course_id', 'session_type', DB::raw('COUNT(*) as attended_sessions'))
                ->groupBy('course_id', 'session_type')
                ->get()
                ->keyBy(fn($row) => $row->course_id . '_' . $row->session_type);

            $attendanceSummary = $totalSessions->map(function ($row) use ($attended) {
                $key = $row->course_id . '_' . $row->session_type;
                return [
                    'course_id'         => $row->course_id,
                    'course_name'       => $row->course_name,
                    'session_type'      => $row->session_type,
                    'total_sessions'    => $row->total_sessions,
                    'attended_sessions' => $attended[$key]->attended_sessions ?? 0,
                ];
            })->values();

            return response()->json([
                'status'              => 'success',
                'attendance_summary'  => $attendanceSummary
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'حدث خطأ: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * جلب قائمة الحضور لمادة معينة 
     * GET /api/doctor/attendance/list
     */
    public function getLectureAttendance(Request $request)
    {
        $request->validate([
            'course_id'      => 'required|integer|exists:courses,id',
            'session_type'   => 'required|in:theoretical,practical',
            'lecture_number' => 'required|string',
            'scope'          => 'nullable|in:all,mine',
        ]);

        try {
            $scope = $request->input('scope', 'all'); // افتراضياً يعرض الكل

            $sessionsQuery = DB::table('attendance_sessions')
                ->where('course_id', $request->course_id)
                ->where('session_type', $request->session_type)
                ->where('lecture_number', $request->lecture_number);

            if ($scope === 'mine') {
                $sessionsQuery->where('opened_by', Auth::id());
            }

            $sessionIds = $sessionsQuery->pluck('id');

            if ($sessionIds->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'session_info' => [
                        'course_id'      => $request->course_id,
                        'session_type'   => $request->session_type,
                        'lecture_number' => $request->lecture_number,
                        'scope'          => $scope,
                        'total_present'  => 0,
                    ],
                    'students' => [],
                ], 200);
            }

            $attendanceRecords = Attendance::with(['student:id,name,university_id,group_number,exam_number'])
                ->whereIn('session_id', $sessionIds)
                ->orderBy('created_at', 'asc')
                ->get()
                ->unique('student_id');

            $studentsList = $attendanceRecords->map(function ($record) {
                if (!$record->student) return null;

                return [
                    'student_id'    => $record->student->id,
                    'name'          => $record->student->name,
                    'university_id' => $record->student->university_id,
                    'group_number'  => $record->student->group_number,
                    'exam_number'   => $record->student->exam_number,
                    'attended_at'   => $record->created_at ? $record->created_at->format('H:i:s') : null,
                ];
            })->filter()->sortBy('name')->values();

            return response()->json([
                'status' => 'success',
                'session_info' => [
                    'course_id'      => $request->course_id,
                    'session_type'   => $request->session_type,
                    'lecture_number' => $request->lecture_number,
                    'scope'          => $scope,
                    'total_present'  => $studentsList->count(),
                    'sessions_count' => $sessionIds->count(),
                ],
                'students' => $studentsList,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'حدث خطأ أثناء جلب قائمة الحضور للطلبة: ' . $e->getMessage()
            ], 500);
        }
    }
}
