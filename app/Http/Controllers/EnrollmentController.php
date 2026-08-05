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
     * فتح فصل دراسي جديد وتهيئة مواد جميع الطلاب (المستجدة والمحملة) 
     * POST /api/admin/semester/open
     */
    public function openNewSemester(Request $request)
    {
        $request->validate([
            'academic_year' => 'required|string',
            'semester'      => 'required|integer|in:1,2',
        ]);

        $academicYear = $request->input('academic_year');
        $semester      = $request->input('semester');

        try {
            $students = DB::table('users')->whereIn('role', ['student', 'volunteer'])->get(); // ← مصحح

            if ($students->isEmpty()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'لا يوجد طلاب مسجلين في النظام لتهيئتهم.'
                ], 404);
            }

            DB::beginTransaction();
            if($semester==1)

            DB::table('lecture_files')
                ->where('is_archived', false)
                ->update(['is_archived' => true]);

            $processedStudentsCount = 0;

            foreach ($students as $student) {
                $courseDepartment = $student->year_of_study <= 3 ? 'Basic Sciences' : $student->department;
                $newCourses = DB::table('courses')
                    ->where('year_of_study', $student->year_of_study)
                    ->where('department', $courseDepartment)
                    ->where('semester', $semester)
                    ->whereNotIn('id', function ($query) use ($student) {
                        $query->select('course_id')
                            ->from('grades')
                            ->where('student_id', $student->id)
                            ->where('status', 'pass');
                    })
                    ->pluck('id')
                    ->toArray();

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

                $carriedCoursesMap = [];
                foreach ($carriedCoursesRaw as $row) {
                    if (!isset($carriedCoursesMap[$row->course_id]) || $row->total_score > $carriedCoursesMap[$row->course_id]) {
                        $carriedCoursesMap[$row->course_id] = $row->total_score;
                    }
                }
                $carriedCourses = array_keys($carriedCoursesMap);

                // مواد الفصل الماضي اللي لسا بدون أي علامة مرصودة إطلاقاً
                $previousSemester = $semester == 1 ? 2 : 1;
                $yearParts = explode('-', $academicYear);
                $previousAcademicYear = $semester == 1
                    ? (intval($yearParts[0]) - 1) . '-' . (intval($yearParts[1]) - 1)
                    : $academicYear;

                $ungradedCourses = DB::table('enrollments')
                    ->where('student_id', $student->id)
                    ->where('academic_year', $previousAcademicYear)
                    ->where('semester', $previousSemester)
                    ->whereNotIn('course_id', function ($query) use ($student) {
                        $query->select('course_id')
                            ->from('grades')
                            ->where('student_id', $student->id);
                    })
                    ->pluck('course_id')
                    ->toArray();

                $count = count($carriedCourses) + count($ungradedCourses);

                $studentModel = User::find($student->id);
                if ($studentModel && $count > 5) {
                    $title = 'تنبيه: مواد محملة';
                    $body = "لديك {$count} مادة محملة من الفصول السابقة. يرجى مراجعة جدولك الدراسي.";
                    $studentModel->notify(new SystemNotification($title, $body, ['type' => 'carried_courses']));
                }

                $canLoadNextYear = true;
                if ($count > 4) {
                    $canLoadNextYear = false;
                    $failGrades = array_values($carriedCoursesMap);

                    if ($count == 5) {
                        $matchingGrades = array_filter($failGrades, fn($g) => $g == 58 || $g == 59);
                        if (count($matchingGrades) >= 1) $canLoadNextYear = true;
                    } elseif ($count == 6) {
                        $matchingGrades = array_filter($failGrades, fn($g) => $g == 59);
                        if (count($matchingGrades) >= 2) $canLoadNextYear = true;
                    }
                }

                $allTargetCourses = array_unique(array_merge($newCourses, $carriedCourses, $ungradedCourses));

                foreach ($allTargetCourses as $courseId) {
                    DB::table('enrollments')->updateOrInsert(
                        [
                            'student_id'    => $student->id,
                            'course_id'     => $courseId,
                            'academic_year' => $academicYear,
                            'semester'      => $semester
                        ],
                        ['enrolled_at' => now()]
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
     * جلب بيانات (السنة الاكاديمية والفصل الدراسي) الحالي والنشط في النظام
     * GET /api/semester/current
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
