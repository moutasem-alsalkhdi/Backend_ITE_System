<?php

namespace App\Http\Controllers;

use App\Models\Grade;
use App\Models\User;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Exception;
use App\Notifications\SystemNotification;


class GradeController extends Controller
{
    /**
     * 1. استيراد ومعالجة درجات الطلاب من ملف Excel أو CSV
     * POST /api/admin/grades/import-excel
     */
    public function importExcelGrades(Request $request)
    {
        // 1. إضافة نوع الامتحان للتحقق (عملي أو نظري)
        $request->validate([
            'excel_file'    => 'required|file|extensions:csv,xlsx,xls',
            'course_id'     => 'required|integer',
            'academic_year' => 'required',
            'semester'      => 'required|integer|between:1,3',
            'department'    => 'required|in:Basic Sciences,software,networks,ai',
            'exam_type'     => 'required|in:practical,theoretical', // 🎯 الحقل الجديد المضاف
        ]);

        $course_id     = $request->input('course_id');
        $academic_year = $request->input('academic_year');
        $semester      = $request->input('semester');
        $department    = $request->input('department');
        $exam_type     = $request->input('exam_type'); // 🎯
        $admin_id      = Auth::id();

        $path      = $request->file('excel_file')->store('admin_files', 'public');
        $full_path = storage_path('app/public/' . $path);



        DB::beginTransaction();

        try {
            // جلب بيانات المادة وفحص قفل أمان القسم
            $course = DB::table('courses')->where('id', $course_id)->first();
            if (!$course) {
                throw new Exception("المادة المحددة غير موجودة في النظام.");
            }
            if ($course->department !== $department) {
                throw new Exception("خطأ أمان: مادة ({$course->name}) لا تنتمي إلى قسم ({$department}) المختار!");
            }

            // قفل أمان إضافي: إذا حاول رفع عملي لمادة ليس لها عملي أصلاً
            if ($exam_type === 'practical' && !$course->has_lab) {
                throw new Exception("خطأ: مادة ({$course->name}) لا تمتلك شق عملي (has_lab = false)، لا يمكن رفع علامات عملي منفصلة لها.");
            }

            $spreadsheet = IOFactory::load($full_path);
            $sheet       = $spreadsheet->getActiveSheet();
            $rows        = $sheet->toArray();

            if (empty($rows)) {
                throw new Exception("الملف فارغ.");
            }

            $headers = array_map('trim', $rows[0]);
            $headers = array_map('strtolower', $headers);

            // 🎯 تعديل دالة المساعد لتصبح مرنة وتقبل حقول اختيارية بناءً على نوع الرفع
            $col = function (string $name, bool $required = true) use ($headers): ?int {
                $index = array_search($name, $headers);
                if ($index === false) {
                    if ($required) {
                        throw new Exception("العمود '{$name}' غير موجود في الملف المُرفق.");
                    }
                    return null;
                }
                return $index;
            };

            // تحديد إلزامية الأعمدة بناءً على نوع الامتحان وبنية المادة
            $exam_number_idx = $col('exam_number', true);
            $practical_idx   = $col('practical_score', (bool)$course->has_lab); // إجباري فقط لو المادة لها عملي
            $theoretical_idx = $col('theoretical_score', $exam_type === 'theoretical');
            $total_idx       = $col('total_score', $exam_type === 'theoretical');
            $status_idx      = $col('status', $exam_type === 'theoretical');

            $safe_academic_year = $academic_year;

            DB::table('uploaded_files')->insert([
                'uploaded_by'   => $admin_id,
                'file_type'     => 'grades',
                'file_url'      => $path,
                'academic_year' => $safe_academic_year,
                'uploaded_at'   => now(),
            ]);
            DB::table('course_deadlines')->insert([
                'course_id'      => $course_id,
                'request_type'   => 'objection',
                'objection_type' => $exam_type,
                'beginning_date' => now(),
            ]);

            $updated_count = 0;

            foreach (array_slice($rows, 1) as $row) {
                if (empty(array_filter($row))) continue;

                $exam_number       = trim($row[$exam_number_idx]);
                $practical_score   = $practical_idx !== null ? trim($row[$practical_idx]) : 0;
                $theoretical_score = $theoretical_idx !== null ? trim($row[$theoretical_idx]) : 0;
                $total_score       = $total_idx !== null ? trim($row[$total_idx]) : 0;
                $status_val        = $status_idx !== null ? trim($row[$status_idx]) : 'fail';

                if (!$exam_number) continue;

                // جلب الطالب والتأكد من قسمه
                $student = DB::table('users')
                    ->where('exam_number', $exam_number)
                    ->where('role', 'student')
                    ->where('department', $department)
                    ->first();

                if ($student) {

                    // 🎯 فحص النجاح المسبق للفصول الماضية
                    $hasPassedBefore = DB::table('grades')
                        ->where('student_id', $student->id)
                        ->where('course_id', $course_id)
                        ->where('status', 'pass')
                        ->exists();

                    if ($hasPassedBefore) {
                        throw new Exception("خطأ في سجل العلامات: الطالب ({$student->name}) صاحب الرقم الامتحاني ({$exam_number}) ناجح في هذه المادة سابقاً ولا يمكن رصد علامة جديدة له.");
                    }

                    // جلب سجل العلامات الحالي للطالب في هذا الفصل إن وجد
                    $existingGradeSameSemester = DB::table('grades')
                        ->where('student_id', $student->id)
                        ->where('course_id', $course_id)
                        ->where('academic_year', $safe_academic_year)
                        ->where('semester', $semester)
                        ->first();

                    // 🎯 القفل السحري المطلوب: مقارنة درجات العملي عند رفع شيت النظري
                    if ($exam_type === 'theoretical' && $course->has_lab) {
                        if ($existingGradeSameSemester) {
                            $stored_practical = (float)$existingGradeSameSemester->practical_score;
                            $incoming_practical = is_numeric($practical_score) ? (float)$practical_score : 0;

                            if ($stored_practical !== $incoming_practical) {
                                throw new Exception("تنبيه تعارض بيانات المادة! الطالب: ({$student->name}) رقم امتحاني: ({$exam_number}) لديه علامة عملي مسجلة مسبقاً بـ ({$stored_practical}) بينما يحتوي ملف النظري الحالي على علامة عملي مغايرة بـ ({$incoming_practical}). تم إلغاء العملية بالكامل لضمان صحة البيانات.");
                            }
                        }
                    }

                    // 🎯 إعداد مصفوفة البيانات للحفظ بناءً على نوع الامتحان المرفوع
                    if ($exam_type === 'practical') {
                        // في حال رفع العملي: نحدث أو ننشئ حقل العملي فقط ونصفر الباقي مؤقتاً
                        $gradeData = [
                            'practical_score' => is_numeric($practical_score) ? (float)$practical_score : 0,
                            'modified_by'     => $admin_id,
                            'recorded_at'     => now()
                        ];

                        if (!$existingGradeSameSemester) {
                            $gradeData['student_id']        = $student->id;
                            $gradeData['course_id']         = $course_id;
                            $gradeData['academic_year']     = $safe_academic_year;
                            $gradeData['semester']          = $semester;
                            $gradeData['theoretical_score'] = 0;
                            $gradeData['total_score']       = 0;
                            $gradeData['status']            = 'fail'; // افتراضي لحين صدور النظري
                        }
                    } else {
                        // في حال رفع النظري: يتم اعتماد كافة العلامات والحالة النهائية والنجاح
                        $status = (str_contains($status_val, 'ناجح') || strtolower($status_val) === 'pass') ? 'pass' : 'fail';

                        $gradeData = [
                            'practical_score'   => is_numeric($practical_score) ? (float)$practical_score : 0,
                            'theoretical_score' => is_numeric($theoretical_score) ? (float)$theoretical_score : 0,
                            'total_score'       => is_numeric($total_score) ? (float)$total_score : 0,
                            'status'            => $status,
                            'modified_by'       => $admin_id,
                            'recorded_at'       => now()
                        ];

                        if (!$existingGradeSameSemester) {
                            $gradeData['student_id']    = $student->id;
                            $gradeData['course_id']     = $course_id;
                            $gradeData['academic_year'] = $safe_academic_year;
                            $gradeData['semester']      = $semester;
                        }
                    }

                    // التنفيذ الفعلي في قاعدة البيانات (حفظ أو تعديل)
                    if ($existingGradeSameSemester) {
                        DB::table('grades')->where('id', $existingGradeSameSemester->id)->update($gradeData);
                    } else {
                        DB::table('grades')->insert($gradeData);
                    }

                    $updated_count++;

                    // 🎯 تخصيص محتوى الإشعارات الموجهة للطالب بناءً على نوع الرفع
                    $studentModel = User::find($student->id);
                    if ($studentModel) {
                        if ($exam_type === 'practical') {
                            $title = "صدور علامات العملي مادة ({$course->name})";
                            $body  = "تم رصد علامة القسم العملي الخاصة بك: (" . (is_numeric($practical_score) ? $practical_score : 0) . ").";
                        } else {
                            $resultStatus = ($gradeData['status'] === 'pass') ? 'ناجح ' : 'راسب ';
                            $title = "صدور النتيجة النهائية لمادة ({$course->name})";
                            $body  = "المجموع الكلي: ({$total_score}) الكلية تصنف الحالة بـ [{$resultStatus}].";
                        }

                        $studentModel->notify(new SystemNotification($title, $body, ['course_id' => $course_id]));
                    }
                }
            }

            DB::commit();

            return response()->json([
                'status'  => 'success',
                'message' => "تمت معالجة ملف ({$exam_type}) بنجاح، ورصد درجات ({$updated_count}) طالب في قسم ({$department}) مع التحقق التلقائي من تطابق البيانات.",
                'data'    => [
                    'recorded_count' => $updated_count,
                ]
            ]);
        } catch (Exception $e) {
            DB::rollBack(); // في حال حدوث أي خلل في التطابق يتم التراجع عن كل شيء فوراً حماية لقاعدة البيانات
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * 2. التعديل والرصد الاستثنائي الفردي للعلامات (خاص بالإدارة والكنترول)
     * يقبل التعديل والحفظ الشامل حتى لو كان الطالب ناجحاً مسبقاً 🎯
     * PUT /api/admin/grades/exceptional-modify
     */
    public function exceptionalModify(Request $request)
    {
        // 1️⃣ المرحلة الأولى من التحقق: الحقول الأساسية المطلوبة للوصول للمادة والطالب
        $request->validate([
            'search_query'  => 'required|string',
            'department'    => 'required|string|max:50',
            'course_id'     => 'required|integer',
            'academic_year' => 'required|string',
            'semester'      => 'required|integer|between:1,3',
        ]);

        $course_id     = $request->input('course_id');
        $department    = $request->input('department');
        $query         = $request->input('search_query');
        $academic_year = $request->input('academic_year');
        $semester      = $request->input('semester');

        // 2️⃣ جلب بيانات المادة أولاً للحصول على السقوف العليا للعلامات (Max Marks)
        $course = DB::table('courses')
            ->where('id', $course_id)
            ->where('department', $department)
            ->first();

        if (!$course) {
            return response()->json([
                'status'  => 'error',
                'message' => "خطأ في النظام: المادة المحددة غير موجودة أو لا تنتمي إلى قسم ({$department})!"
            ], 422);
        }

        // 3️⃣ المرحلة الثانية من التحقق: فحص العلامات ديناميكياً بناءً على بنية المادة المجلوبة
        $request->validate([
            'practical_score'   => 'nullable|numeric|min:0|max:' . $course->practical_max_mark,
            'theoretical_score' => 'nullable|numeric|min:0|max:' . $course->theory_max_mark,
        ], [
            // رسائل خطأ مخصصة وواضحة للآدمن
            'practical_score.max'   => "علامة العملي لهذه المادة لا يمكن أن تتجاوز ({$course->practical_max_mark}).",
            'theoretical_score.max' => "علامة النظري لهذه المادة لا يمكن أن تتجاوز ({$course->theory_max_mark}).",
        ]);

        // 4️⃣ بدء معالجة البيانات بعد ضمان صحة القيام والحدود العليا
        DB::beginTransaction();

        try {
            // البحث عن الطالب والتأكد من قسمه الدراسي
            $student = DB::table('users')
                ->where('role', 'student')
                ->where('department', $department)
                ->where(function ($q) use ($query) {
                    $q->where('exam_number', $query)
                        ->orWhere('name', 'LIKE', '%' . $query . '%');
                })->first();

            if (!$student) {
                return response()->json([
                    'status'  => 'error',
                    'message' => "لم يتم العثور على طالب يطابق بيانات البحث ضمن قسم ({$department})."
                ], 404);
            }

            // الفحص من جدول التسجيل (enrollments)
            $isEnrolled = DB::table('enrollments')
                ->where('student_id', $student->id)
                ->where('course_id', $course_id)
                ->where('academic_year', $academic_year)
                ->where('semester', $semester)
                ->exists();

            if (!$isEnrolled) {
                return response()->json([
                    'status'  => 'error',
                    'message' => "خطأ في الرصد: الطالب ({$student->name}) غير مسجل رسمياً في هذه المادة لهذا الفصل الدراسي!"
                ], 422);
            }

            // بما أن الـ Validation ضمن لنا الحدود، نقوم بجلب القيم مباشرة
            $practical   = $request->input('practical_score', 0) ?? 0;
            $theoretical = $request->input('theoretical_score', 0) ?? 0;
            $totalScore  = $practical + $theoretical;

            // قفل أمان للمجموع الكلي (مثلاً لا يتجاوز مجموع الحدين الأقصيين للمادة)
            $maxPossibleTotal = $course->practical_max_mark + $course->theory_max_mark;
            if ($totalScore > $maxPossibleTotal) {
                return response()->json([
                    'status'  => 'error',
                    'message' => "خطأ في الرصد: المجموع الكلي للعلامات ({$totalScore}) يتجاوز الحد الأعلى المسموح للمادة وهو ({$maxPossibleTotal})."
                ], 422);
            }

            $status = $totalScore >= 60 ? 'pass' : 'fail';

            $existingGradeSameSemester = DB::table('grades')
                ->where('student_id', $student->id)
                ->where('course_id', $course_id)
                ->where('academic_year', $academic_year)
                ->where('semester', $semester)
                ->first();

            if ($existingGradeSameSemester) {
                DB::table('grades')
                    ->where('id', $existingGradeSameSemester->id)
                    ->update([
                        'practical_score'   => $practical,
                        'theoretical_score' => $theoretical,
                        'total_score'       => $totalScore,
                        'status'            => $status,
                        'modified_by'       => Auth::id(),
                        'recorded_at'       => now()
                    ]);
                $msg = "تم تحديث علامة الطالب ({$student->name}) بشكل استثنائي بنجاح.";
            } else {
                DB::table('grades')->insert([
                    'student_id'        => $student->id,
                    'course_id'         => $course_id,
                    'academic_year'     => $academic_year,
                    'semester'          => $semester,
                    'practical_score'   => $practical,
                    'theoretical_score' => $theoretical,
                    'total_score'       => $totalScore,
                    'status'            => $status,
                    'modified_by'       => Auth::id(),
                    'recorded_at'       => now()
                ]);
                $msg = "تم رصد وإدخال علامة استثنائية جديدة للطالب ({$student->name}) بنجاح.";
            }

            // إرسال إشعار فوري للطالب
            $studentModel = User::find($student->id);
            if ($studentModel) {
                $resultStatus = ($status === 'pass') ? 'ناجح' : 'راسب';
                $studentModel->notify(new SystemNotification(
                    "تعديل في السجل الأكاديمي",
                    "قامت الإدارة بتحديث علامة مادة ({$course->name}). المجموع الجديد: {$totalScore} [{$resultStatus}].",
                    ['course_id' => $course_id]
                ));
            }

            DB::commit();
            return response()->json(['status' => 'success', 'message' => $msg], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * 3. السجل الأكاديمي الرقمي (خاص بالطالب المسجل دخوله حالياً فقط)
     * GET /api/student/academic-record
     */
    public function getAcademicRecord(Request $request)
    {
        try {
            $studentId = Auth::id();

            // 🎯 تم حجب الـ LeftJoin مع جدول المستخدمين لحماية خصوصية الهيئة الإدارية والتدريسية
            $query = DB::table('grades')
                ->join('courses', 'grades.course_id', '=', 'courses.id')
                ->where('grades.student_id', $studentId)
                ->select([
                    'grades.id',
                    'grades.student_id',
                    'grades.course_id',
                    'courses.name as course_name',
                    'grades.academic_year',
                    'grades.semester',
                    'grades.practical_score',
                    'grades.theoretical_score',
                    'grades.total_score',
                    'grades.status',
                    'grades.recorded_at'
                ]);

            // الفلاتر المعتادة
            if ($request->has('academic_year')) {
                $query->where('grades.academic_year', $request->query('academic_year'));
            }
            if ($request->has('semester')) {
                $query->where('grades.semester', $request->query('semester'));
            }
            if ($request->has('status')) {
                $query->where('grades.status', $request->query('status'));
            }
            if ($request->has('course_id')) {
                $query->where('grades.course_id', $request->query('course_id'));
            }

            // الترتيب التصاعدي لعرض التسلسل الزمني لرحلة الطالب الدراسية
            $records = $query->orderBy('grades.academic_year', 'asc')
                ->orderBy('grades.semester', 'asc')
                ->get();

            return response()->json([
                'status'          => 'success',
                'filters_applied' => $request->only(['academic_year', 'semester', 'status', 'course_id']),
                'count'           => $records->count(),
                'data'            => $records
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'حدث خطأ أثناء جلب السجل الأكاديمي: ' . $e->getMessage()
            ], 500);
        }
    }
}
