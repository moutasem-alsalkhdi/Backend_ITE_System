<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Notifications\SystemNotification;
use Exception;

class EnrollmentController extends Controller
{
    /**
     * فتح فصل دراسي جديد وتهيئة مواد جميع الطلاب (المستجدة والمحملة) بضغطة زر واحدة
     * POST /api/admin/semester/open
     */
    public function openNewSemester(Request $request)
    {
        // 1. التحقق من المدخلات القادمة من شؤون الطلاب
        $request->validate([
            'academic_year' => 'required|string',       // مثال: "2025-2026"
            'semester'      => 'required|integer|in:1,2', // 1: أول، 2: ثاني
        ]);

        $academicYear = $request->input('academic_year');
        $semester      = $request->input('semester');

        try {
            // 2. جلب جميع الطلاب المسجلين بالكلية
            $students = DB::table('users')->where('role', ['student', 'volunteer'])->get();

            if ($students->isEmpty()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'لا يوجد طلاب مسجلين في النظام لتهيئتهم.'
                ], 404);
            }

            DB::beginTransaction();

            // ─── الخطوة الأولى: أرشفة جميع ملفات المحاضرات النشطة ────────────────
            DB::table('lecture_files')
                ->where('is_archived', false)
                ->update(['is_archived' => true]);

            // ─── الخطوة الثانية: تسجيل الطلاب في مواد الفصل الجديد ──────────────
            $processedStudentsCount = 0;

            foreach ($students as $student) {

                // أولاً: جلب المواد المستجدة الخاصة بسنة الطالب الحالية وفصله الدراسي الحالي
                $newCourses = DB::table('courses')
                    ->where('year_of_study', $student->year_of_study)
                    ->where('department', $student->department)
                    ->where('semester', $semester)
                    ->whereNotIn('id', function ($query) use ($student) {
                        $query->select('course_id')
                            ->from('grades')
                            ->where('student_id', $student->id)
                            ->where('status', 'pass');
                    })
                    ->pluck('id')
                    ->toArray();

                // ثانياً: جلب المواد المحملة وعلاماتها
                $carriedCoursesRaw = DB::table('grades')
                    ->where('student_id', $student->id)
                    ->where('status', 'fail')
                    ->whereNotIn('course_id', function ($query) use ($student) {
                        $query->select('course_id')
                            ->from('grades')
                            ->where('student_id', $student->id)
                            ->where('status', 'pass');
                    })
                    ->get(['course_id', 'total_score']);

                // تصفية المواد المحملة لضمان عدم التكرار
                $carriedCoursesMap = [];
                foreach ($carriedCoursesRaw as $row) {
                    if (!isset($carriedCoursesMap[$row->course_id]) || $row->total_score > $carriedCoursesMap[$row->course_id]) {
                        $carriedCoursesMap[$row->course_id] = $row->total_score;
                    }
                }

                $carriedCourses = array_keys($carriedCoursesMap);
                $count = count($carriedCourses);

                $studentModel = User::find($student->id);

                if ($studentModel) {
                    if ($count > 5) {
                        $title = 'تنبيه: مواد محملة';
                        $body = "لديك {$count} مادة محملة من الفصول السابقة. يرجى مراجعة جدولك الدراسي.";

                        $studentModel->notify(new SystemNotification($title, $body, ['type' => 'carried_courses']));

                    }
                }

                if ($count > 5) {
                }

                // فحص شروط مواد الرأفة والمساعدة للتحميل
                $canLoadNextYear = true;
                if ($count > 4) {
                    $canLoadNextYear = false;
                    $failGrades = array_values($carriedCoursesMap);

                    if ($count == 5) {
                        $matchingGrades = array_filter($failGrades, function ($g) {
                            return $g == 58 || $g == 59;
                        });
                        if (count($matchingGrades) >= 1) $canLoadNextYear = true;
                    } elseif ($count == 6) {
                        $matchingGrades = array_filter($failGrades, function ($g) {
                            return $g == 59;
                        });
                        if (count($matchingGrades) >= 2) $canLoadNextYear = true;
                    }
                }

                // 💡 التعديل الذكي هنا:
                // جلب مواد السنة التالية فقط إذا نجح في الشروط، وبشرط ألا يكون الطالب في السنة الثالثة حالياً
                // (لأن مواد السنة الرابعة تتطلب اختصاصاً لم يُرفع من الإدارة بعد)
                // $newCoursesyear = [];
                // if ($canLoadNextYear && $student->year_of_study != 3) {
                //     $newCoursesyear = DB::table('courses')
                //         ->where('year_of_study', $student->year_of_study + 1)
                //         ->where('department', $student->department)
                //         ->where('semester', $semester)
                //         ->pluck('id')
                //         ->toArray();
                // }

                // ثالثاً: دمج المواد المستهدفة بدون أي تكرار
                $allTargetCourses = array_unique(array_merge($newCourses, $carriedCourses));

                // رابعاً: حقن المواد المستهدفة في جدول التسجيل enrollments
                foreach ($allTargetCourses as $courseId) {
                    DB::table('enrollments')->updateOrInsert(
                        [
                            'student_id'    => $student->id,
                            'course_id'     => $courseId,
                            'academic_year' => $academicYear,
                            'semester'      => $semester
                        ],
                        [
                            'enrolled_at'   => now()
                        ]
                    );
                }

                $processedStudentsCount++;
            }

            DB::commit();

            return response()->json([
                'status'  => 'success',
                'message' => "تم فتح الفصل الدراسي الجديد ({$academicYear} - الفصل {$semester}) بنجاح! وتوليد السجلات لـ ({$processedStudentsCount}) طالب تلقائياً 🚀"
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status'  => 'error',
                'message' => 'حدث خطأ غير متوقع: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * جلب بيانات الفصل الدراسي الحالي والنشط في النظام
     * GET /api/admin/semester/current
     */
    public function getCurrentSemester()
    {
        try {
            // جلب آخر فصل دراسي تم تسجيل الطلاب فيه
            $currentEnrollment = DB::table('enrollments')
                ->orderBy('id', 'desc')
                ->first(['academic_year', 'semester']);

            if (!$currentEnrollment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'لم يتم فتح أي فصل دراسي بعد.'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'academic_year' => $currentEnrollment->academic_year,
                    'semester'      => $currentEnrollment->semester,
                    'semester_text' => $currentEnrollment->semester == 1 ? 'الفصل الأول' : 'الفصل الثاني'
                ]
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'حدث خطأ أثناء جلب بيانات الفصل الدراسي: ' . $e->getMessage()
            ], 500);
        }
    }
}
