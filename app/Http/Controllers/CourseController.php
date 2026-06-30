<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class CourseController extends Controller
{
    /**
     * جلب معلومات المواد المتوافقة مع السكيما الفعلية
     */
    public function getCoursesInfo(Request $request)
    {
        // 1️⃣ جلب بيانات المستخدم الحالي لفلترة المواد تلقائياً حسب قسمه
        $user = Auth::user();

        // 2️⃣ إمكانية الفرز القادم من الـ Request
        $department = $request->query('department', $user->department);
        $yearOfStudy = $request->query('year_of_study');

        // 3️⃣ بناء الاستعلام وجلب المواد بالاعتماد على الحقول الحقيقية في جدولك
        $query = DB::table('courses')
            ->leftJoin('course_deadlines', function ($join) {
                $join->on('courses.id', '=', 'course_deadlines.course_id')
                    ->where('course_deadlines.request_type', '=', 'objection');
            })
            ->select([
                'courses.id as course_id',
                'courses.name as course_name',
                'courses.has_lab',
                'courses.year_of_study',
                'courses.theory_max_mark',
                'courses.practical_max_mark',
                'courses.semester',
                'courses.department'
            ]);

        // 4️⃣ تطبيق الفلاتر إن وُجدت
        if ($department && $department !== 'all') {
            $query->where('courses.department', $department);
        }

        if ($yearOfStudy) {
            $query->where('courses.year_of_study', $yearOfStudy);
        }

        $courses = $query->get();

        // 5️⃣ إرجاع البيانات المنسقة وفقاً لحقول جدولك
        return response()->json([
            'status' => 'success',
            'count'  => $courses->count(),
            'data'   => $courses->map(function ($course) {
                return [
                    'id'                 => $course->course_id,
                    'name'               => $course->course_name,
                    'has_lab'            => (bool) $course->has_lab,
                    'year_of_study'      => $course->year_of_study,
                    'theory_max_mark'    => $course->theory_max_mark,
                    'practical_max_mark' => $course->practical_max_mark,
                    'semester'           => $course->semester,
                    'department'         => $course->department,
                ];
            })
        ], 200);
    }
    public function getEligibleCourses(Request $request)
    {
        $request->validate(['request_type' => 'required|in:objection,lab_redo']);
        $student_id = Auth::id();
        $type = $request->query('request_type');

        // 1. جلب المواد التي لها مواعيد نهائية فعالة ولم تنتهِ بعد
        $activeCourseIds = DB::table('course_deadlines')
            ->where('request_type', $type)
            ->where('end_date', '>', now())
            ->pluck('course_id');

        // 2. جلب تفاصيل هذه المواد
        $courses = DB::table('courses')->whereIn('id', $activeCourseIds)->get();

        // 3. تصفية إضافية خاصة بـ التكرار العملي (lab_redo) لحملة المادة فقط
        if ($type === 'lab_redo') {
            $filteredCourses = [];
            foreach ($courses as $course) {
                // استثناء المواد التي ليس بها عملي أصلاً
                if ($course->practical_max_mark == 0) continue;

                // التحقق من علامة الطالب (total_mark < 50 تعني راسب/حامل المادة)
                $grade = DB::table('grades')
                    ->where('student_id', $student_id)
                    ->where('course_id', $course->id)
                    ->first();

                if ($grade && $grade->total_mark < 50) {
                    $filteredCourses[] = $course;
                }
            }
            return response()->json($filteredCourses);
        }

        // بالنسبة للاعتراض (objection)، تعود كل المواد المتاحة وقتها تلقائياً
        return response()->json($courses);
    }
}
