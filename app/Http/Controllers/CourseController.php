<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class CourseController extends Controller
{
    /**
     * جلب معلومات المواد (مع إمكانية الفرز والتصفية حسب السنة والقسم)
     * GET /api/courses/info
     */
    public function getCoursesInfo(Request $request)
    {
        $request->validate([
            'year_of_study' => 'nullable|integer|min:1|max:5',
            'department'    => 'nullable|string', 
        ]);

        // 2️⃣ إمكانية الفرز القادم من الـ Request
        $department = $request->query('department');
        $yearOfStudy = $request->query('year_of_study');

        // 3️⃣ بناء الاستعلام وجلب المواد بالاعتماد على الحقول الحقيقية في جدولك
        $query = DB::table('courses')
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

    /**
     * جلب مواد الطالب المسجل بيها بالفصل الدراسي الحالي
     * GET /api/student/my-enrolled-courses
     */
    public function getMyEnrolledCourses(Request $request)
    {
        $studentId = Auth::id();

        // 1. جلب الفصل الحالي (آخر فصل تم فتحه بالنظام)
        $current = DB::table('enrollments')
            ->orderBy('id', 'desc')
            ->first(['academic_year', 'semester']);

        if (!$current) {
            return response()->json([
                'status' => 'error',
                'message' => 'لم يتم فتح أي فصل دراسي بعد.'
            ], 404);
        }

        // 2. جلب مواد هذا الطالب بالذات لهذا الفصل بالتحديد
        $courses = DB::table('courses')
            ->join('enrollments', 'courses.id', '=', 'enrollments.course_id')
            ->where('enrollments.student_id', $studentId)
            ->where('enrollments.academic_year', $current->academic_year)
            ->where('enrollments.semester', $current->semester)
            ->select([
                'courses.id',
                'courses.name',
                'courses.has_lab',
                'courses.year_of_study',
                'courses.theory_max_mark',
                'courses.practical_max_mark',
                'courses.semester',
                'courses.department',
            ])
            ->get();

        return response()->json([
            'status' => 'success',
            'count'  => $courses->count(),
            'data'   => $courses,
        ], 200);
    }


    /**
     * المواد التي لها مواعيد نهائية فعالة ولم تنتهِ بعد
     * GET /api/student/eligible-courses
     */
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

    /**
     * جلب مواد الطالب في الفصل الحالي والسنة الدراسية الحالية (تفيد الفريق التطوعي)
     * GET /api/student/getSemesterCourses
     */
    public function getSemesterCourses(Request $request)
    {
        // 1️⃣ جلب بيانات المستخدم الحالي لفلترة المواد تلقائياً حسب قسمه
        $user = Auth::user();

        $current = DB::table('enrollments')
            ->orderBy('id', 'desc')
            ->first(['academic_year', 'semester']);

        if (!$current) {
            return response()->json([
                'status' => 'error',
                'message' => 'لم يتم فتح أي فصل دراسي بعد.'
            ], 404);
        }

        // 2️⃣ إمكانية الفرز القادم من الـ Request
        $department =  $user->department;
        $yearOfStudy = $user->year_of_study;

        // 3️⃣ بناء الاستعلام وجلب المواد بالاعتماد على الحقول الحقيقية في جدولك
        $query = DB::table('courses')
            ->where('department', $department)
            ->where('year_of_study', $yearOfStudy)
            ->where('semester', $current->semester)
            ->select('id', 'name', 'has_lab', 'year_of_study', 'theory_max_mark', 'practical_max_mark', 'semester', 'department')->get();



        // 5️⃣ إرجاع البيانات المنسقة وفقاً لحقول جدولك
        return response()->json([
            'status' => 'success',
            'count'  => $query->count(),
            'data'   => $query->map(function ($course) {
                return [
                    'id'                 => $course->id,
                    'name'               => $course->name,
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
    /**
     * جلب كل مواد سنة دراسية معينة (لقسم الطالب الحالي) — لمستودع المحاضرات
     * GET /api/student/courses-by-year
     */
    public function getCoursesByYear(Request $request)
    {
        $request->validate([
            'year_of_study' => 'required|integer|min:1|max:5',
            'department'    => 'nullable|string', // جديد — مطلوب فقط للسنوات 4-5
        ]);

        $user = Auth::user();
        $yearOfStudy = $request->query('year_of_study');
        $requestedDepartment = $request->query('department');

        if ($yearOfStudy <= 3) {
            $department = 'Basic Sciences';
        } else {
            // للسنوات 4-5 لازم يوصل قسم محدد من الطالب (من الشاشة الوسيطة)
            $department = $requestedDepartment ?? $user->department;
        }

        $courses = DB::table('courses')
            ->where('department', $department)
            ->where('year_of_study', $yearOfStudy)
            ->select(['id', 'name', 'has_lab', 'year_of_study', 'semester', 'department'])
            ->orderBy('semester')
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => 'success',
            'count'  => $courses->count(),
            'data'   => $courses,
        ], 200);
    }
}
