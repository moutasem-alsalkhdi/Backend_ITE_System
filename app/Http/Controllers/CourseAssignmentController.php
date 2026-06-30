<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Course;
use Illuminate\Support\Facades\Auth;

class CourseAssignmentController extends Controller
{
    /**
     * إسناد مادة (نظري أو عملي) لمجموعة من الدكاترة أو المعيدين
     */
    public function assignStaff(Request $request)
    {
        $request->validate([
            'course_id'     => 'required|exists:courses,id',
            'staff_ids'     => 'required|array', // مصفوفة تحوي معرفات الدكاترة/المعيدين المختارين
            'staff_ids.*'   => 'exists:users,id',
            'section_type'  => 'required|in:theoretical,practical', // theoretical=نظري ، practical=عملي
            'academic_year' => 'required|string', // مثل "2026-2027"
            'semester'      => 'required|in:1,2', // الفصل
        ]);

        $courseId = $request->course_id;
        $sectionType = $request->section_type;
        $academicYear = $request->academic_year;
        $semester = $request->semester;

        try {
            DB::beginTransaction();

            // نقوم بإدخال البيانات لكل شخص تم اختياره في الواجهة (مثال: اختيار 3 معيدين لقسم العملي بمادة واحدة دفعة واحدة)
            foreach ($request->staff_ids as $userId) {
                DB::table('course_assignments')->updateOrInsert(
                    [
                        'course_id'     => $courseId,
                        'user_id'       => $userId,
                        'section_type'  => $sectionType,
                        'academic_year' => $academicYear,
                        'semester'      => $semester
                    ],
                    [
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }

            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'تم إسناد الكادر للمادة بنجاح']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'حدث خطأ أثناء المعالجة', 'error' => $e->getMessage()], 500);
        }
    }


    /**
     * جلب قائمة المواد مع تفاصيل الكادر التدريسي (نظري وعملي) لفصل محدد وسنة محددة
     * يدعم فلترة الآدمن، ويجلب للمدرس مواده المسندة إليه فقط تلقائياً.
     */
    public function getCourseAssignments(Request $request)
    {
        $user = Auth::user();

        // جلب الفصل الدراسي المفتوح حالياً
        $current = DB::table('enrollments')
            ->select('academic_year', 'semester')
            ->latest('enrolled_at')
            ->first();

        if (!$current) {
            return response()->json([
                'status' => 'error',
                'message' => 'لم يتم فتح أي فصل دراسي بعد.'
            ], 404);
        }

        $academicYear = $current->academic_year;
        $semester     = $current->semester;

        $department   = $request->department;
        $yearOfStudy  = $request->year_of_study;

        // الاستعلام الأساسي
        $query = DB::table('courses')
            ->select([
                'id',
                'name',
                'has_lab',
                'year_of_study',
                'theory_max_mark',
                'practical_max_mark',
                'semester',
                'department'
            ]);

        if ($user->role !== 'admin') {

            // الدكتور أو المعيد يرى مواده فقط في الفصل الحالي
            $query->whereIn('id', function ($subQuery) use ($user, $academicYear, $semester) {

                $subQuery->select('course_id')
                    ->from('course_assignments')
                    ->where('user_id', $user->id)
                    ->where('academic_year', $academicYear)
                    ->where('semester', $semester);
            });
        } else {

            // فلاتر الأدمن
            if ($department && $department != 'all') {
                $query->where('department', $department);
            }

            if ($yearOfStudy) {
                $query->where('year_of_study', $yearOfStudy);
            }
        }

        $courses = $query->get();

        // جلب جميع تعيينات الكادر لهذا الفصل
        $assignments = DB::table('course_assignments')
            ->join('users', 'course_assignments.user_id', '=', 'users.id')
            ->where('course_assignments.academic_year', $academicYear)
            ->where('course_assignments.semester', $semester)
            ->select([
                'course_assignments.course_id',
                'course_assignments.section_type',
                'users.id as user_id',
                'users.name as user_name'
            ])
            ->get()
            ->groupBy('course_id');

        return response()->json([
            'status' => 'success',
            'count'  => $courses->count(),
            'data'   => $courses->map(function ($course) use ($assignments) {

                $courseAssignments = $assignments->get($course->id, collect());

                return [
                    'id'                 => $course->id,
                    'name'               => $course->name,
                    'has_lab'            => (bool)$course->has_lab,
                    'year_of_study'      => $course->year_of_study,
                    'theory_max_mark'    => $course->theory_max_mark,
                    'practical_max_mark' => $course->practical_max_mark,
                    'semester'           => $course->semester,
                    'department'         => $course->department,

                    'staff_assignments' => [
                        'theoretical' => $courseAssignments
                            ->where('section_type', 'theoretical')
                            ->map(fn($a) => [
                                'id' => $a->user_id,
                                'name' => $a->user_name,
                            ])
                            ->values(),

                        'practical' => $courseAssignments
                            ->where('section_type', 'practical')
                            ->map(fn($a) => [
                                'id' => $a->user_id,
                                'name' => $a->user_name,
                            ])
                            ->values(),
                    ]
                ];
            })
        ]);
    }
}
