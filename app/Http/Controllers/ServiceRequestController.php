<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Exception;
use App\Notifications\SystemNotification;
use App\Models\User;
use App\Models\ServiceRequest;

class ServiceRequestController extends Controller
{
    /**
     * جلب وتصنيف طلبات الخدمات الطلابية للإدارة
     * GET /api/admin/service-requests?status=pending&page=1
     */
    public function getAdminRequests(Request $request)
    {
        try {
            // 1. استقبال الفلتر من الفرونت اند (إذا لم يُرسل شيء، يجلب كل الطلبات)
            $statusFilter = $request->query('status');

            // 2. بناء الاستعلام الأساسي مع جلب اسم الطالب والرقم الجامعي عبر الـ Join
            $query = DB::table('service_requests')
                ->join('users', 'service_requests.student_id', '=', 'users.id')
                ->select(
                    'service_requests.*',
                    'users.name as student_name',
                    'users.exam_number as student_exam_number'
                );

            // 3. تطبيق الفلتر في حال تم تحديده في الرابط
            $allowedStatuses = ['pending', 'awaiting_payment', 'processing', 'ready', 'completed', 'rejected'];
            if ($statusFilter && in_array($statusFilter, $allowedStatuses)) {
                $query->where('service_requests.status', $statusFilter);
            }

            // ترتيب الطلبات من الأحدث إلى الأقدم مع توزيعها على صفحات (Pagination)
            $requestsData = $query->orderBy('service_requests.created_at', 'desc')
                ->paginate(15);

            // 4. 💡 حركة ذكية: جلب العدادات (Counts) لكل الحالات دفعة واحدة لعرضها في الـ Dashboard
            $stats = DB::table('service_requests')
                ->select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status');

            // دمج العدادات والتأكد من وجود القيمة 0 في حال خلو حالة معينة
            $counters = [
                'all'              => DB::table('service_requests')->count(),
                'pending'          => $stats['pending'] ?? 0,
                'awaiting_payment' => $stats['awaiting_payment'] ?? 0,
                'processing'       => $stats['processing'] ?? 0,
                'ready'            => $stats['ready'] ?? 0,
                'completed'        => $stats['completed'] ?? 0,
                'rejected'         => $stats['rejected'] ?? 0,
            ];

            // 5. إرسال الاستجابة الشاملة للفرونت اند
            return response()->json([
                'status' => 'success',
                'counters' => $counters, // عدادات البطاقات في الواجهة
                'meta' => [
                    'current_page' => $requestsData->currentPage(),
                    'last_page'    => $requestsData->lastPage(),
                    'total_items'  => $requestsData->total(),
                ],
                'data' => $requestsData->items() // مصفوفة الطلبات المفلترة
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'حدث خطأ أثناء جلب البيانات: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * 1. تقديم طلب إداري جديد (خاص بالطالب)
     * POST /api/student/requests
     */
    // public function submitRequest(Request $request)
    // {
    //     // التحقق من المدخلات الأساسية
    //     $request->validate([
    //         'request_type' => 'required|in:grade_sheet,objection,lab_redo,life_cert',
    //         'course_id'    => 'required_if:request_type,objection,lab_redo|integer',
    //         'objection_type' => 'required_if:request_type,objection|in:practical,theoretical', // مطلوب فقط للاعتراض أو الإعادة العملي
    //     ]);

    //     $student_id = Auth::id();
    //     $requestType = $request->input('request_type');
    //     $courseId = $request->input('course_id');

    //     try {
    //         // 🎯 الفحص البرمجي للموعد النهائي (الاطار الزمني للاعتراضات وإعادة العملي)
    //         if (in_array($requestType, ['objection', 'lab_redo'])) {

    //             // جلب الموعد النهائي المحدد لهذه المادة من جدول مواعيد المواد
    //             $deadlineInfo = DB::table('course_deadlines') // جدول افتراضي للمواعيد حددته الإدارة
    //                 ->where('course_id', $courseId)
    //                 ->where('request_type', $requestType)
    //                 ->first();

    //             // إذا حددت الإدارة موعداً وانتهى، نمنع الطالب فوراً من التقديم
    //             if ($deadlineInfo && now()->gt($deadlineInfo->end_date)) {
    //                 return response()->json([
    //                     'status'  => 'error',
    //                     'message' => 'عذراً، لقد انتهت المهلة الزمنية المتاحة لتقديم هذا النوع من الطلبات لهذه المادة.'
    //                 ], 400);
    //             }
    //             // إذا كان الطلب هو إعادة القسم العملي
    //             if ($requestType === 'lab_redo') {
    //                 // جلب بيانات المادة للتأكد من توزيع علاماتها
    //                 $course = DB::table('courses')->where('id', $courseId)->first();

    //                 // إذا كانت العلامة العظمى للعملي تساوي 0، فهذا يعني أن المادة ليس لها عملي أصلاً!
    //                 if ($course && $course->practical_max_mark == 0) {
    //                     return response()->json([
    //                         'status'  => 'error',
    //                         'message' => "عذراً، مادة '{$course->name}' هي مادة نظرية بالكامل ولا تحتوي على قسم عملي لتقديم طلب إعادة فيه."
    //                     ], 400);
    //                 }
    //             }
    //         }

    //         // إدراج الطلب في قاعدة البيانات (الحالة الافتراضية pending تلقائياً من الـ Schema)
    //         DB::table('service_requests')->insert([
    //             'student_id'   => $student_id,
    //             'course_id'    => $courseId,
    //             'request_type' => $requestType,
    //             'objection_type' => $request->input('objection_type'),
    //             'status'       => 'pending',
    //             'created_at'   => now(),
    //             'updated_at'   => now(),
    //         ]);

    //         return response()->json([
    //             'status'  => 'success',
    //             'message' => 'تم تقديم طلبك بنجاح، وهو الآن قيد الانتظار والمراجعة من قبل شؤون الطلاب.'
    //         ], 201);
    //     } catch (Exception $e) {
    //         return response()->json([
    //             'status'  => 'error',
    //             'message' => 'حدث خطأ أثناء إرسال الطلب: ' . $e->getMessage()
    //         ], 500);
    //     }
    // }

    public function submitRequest(Request $request)
    {
        // التحقق من المدخلات الأساسية
        $request->validate([
            'request_type' => 'required|in:grade_sheet,objection,lab_redo,life_cert',
            'course_id'    => 'required_if:request_type,objection,lab_redo|integer',
            'objection_type' => 'required_if:request_type,objection|in:practical,theoretical', // مطلوب فقط للاعتراض أو الإعادة العملي
        ]);

        $student_id = Auth::id();
        $requestType = $request->input('request_type');
        $courseId = $request->input('course_id');

        try {
            // 🎯 الفحص البرمجي للموعد النهائي (الاطار الزمني للاعتراضات وإعادة العملي)
            if (in_array($requestType, ['objection', 'lab_redo'])) {

                // جلب الموعد النهائي المحدد لهذه المادة من جدول مواعيد المواد
                $deadlineInfo = DB::table('course_deadlines')
                    ->where('course_id', $courseId)
                    ->where('request_type', $requestType)
                    ->first();

                // إذا حددت الإدارة موعداً وانتهى، نمنع الطالب فوراً من التقديم
                if ($deadlineInfo && now()->gt($deadlineInfo->end_date)) {
                    return response()->json([
                        'status'  => 'error',
                        'message' => 'عذراً، لقد انتهت المهلة الزمنية المتاحة لتقديم هذا النوع من الطلبات لهذه المادة.'
                    ], 400);
                }

                // ─── إذا كان الطلب هو إعادة القسم العملي ─────────────────
                if ($requestType === 'lab_redo') {
                    // 1. جلب بيانات المادة للتأكد من توزيع علاماتها
                    $course = DB::table('courses')->where('id', $courseId)->first();

                    // إذا كانت العلامة العظمى للعملي تساوي 0، فهذا يعني أن المادة ليس لها عملي أصلاً!
                    if ($course && $course->practical_max_mark == 0) {
                        return response()->json([
                            'status'  => 'error',
                            'message' => "عذراً، مادة '{$course->name}' هي مادة نظرية بالكامل ولا تحتوي على قسم عملي لتقديم طلب إعادة فيه."
                        ], 400);
                    }

                    // 2. 🎯 الفحص الجديد: التأكد من أن الطالب حامل المادة (راسب فيها)
                    // نقوم بجلب سجل علامة الطالب لهذه المادة محددة
                    $studentGrade = DB::table('grades')
                        ->where('student_id', $student_id)
                        ->where('course_id', $courseId)
                        ->first();

                    // شرط المنع: إذا وجدنا سجل علامات وكانت علامته الإجمالية ناجحة (أكبر أو تساوي 50 مثلاً)
                    // 💡 ملاحظة: يمكنك تعديل (total_mark >= 50) حسب عمود النجاح لديك في قاعدة البيانات (مثل: is_passed == 1)
                    if ($studentGrade && isset($studentGrade->total_mark) && $studentGrade->total_mark >= 50) {
                        return response()->json([
                            'status'  => 'error',
                            'message' => "عذراً، لا يمكنك تقديم طلب إعادة العملي لهذه المادة لأنك ناجح بها مسبقاً. هذه الميزة متاحة فقط للمواد المحمولة."
                        ], 400);
                    }
                }
                // 💡 لاحظ هنا: إذا كان request_type يساوي objection سيتخطى الفحص البرمجي للرسوب 
                // ويسمح له بالتقديم مباشرة ما دام الموعد النهائي صالحاً كما طلبت!
            }

            // إدراج الطلب في قاعدة البيانات بعد اجتياز الشروط بالكامل
            DB::table('service_requests')->insert([
                'student_id'   => $student_id,
                'course_id'    => $courseId,
                'request_type' => $requestType,
                'objection_type' => $request->input('objection_type'),
                'status'       => 'pending',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => 'تم تقديم طلبك بنجاح، وهو الآن قيد الانتظار والمراجعة من قبل شؤون الطلاب.'
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'حدث خطأ أثناء إرسال الطلب: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 2. تحديث حالة الطلب وإضافة الرسوم والملاحظات (خاص بالإدارة)
     * PUT /api/admin/requests/{id}/status
     */
    public function updateStatus(Request $request, $id)
    {
        // 1. التحقق من المدخلات باستخدام الحقول الحقيقية لقاعدة بياناتك والحالات الشاملة
        $request->validate([
            'status'           => 'required|in:pending,accepted,rejected,awaiting_payment,ready,cancelled,completed',
            'fee_amount'       => 'required_if:status,awaiting_payment|numeric|min:0',
            'admin_note'       => 'nullable|string|max:500',
            'payment_deadline' => 'nullable|date|after:now',
        ]);

        // 2. جلب الطلب باستخدام Eloquent (أفضل وأسرع من DB Facade)
        $serviceRequest = ServiceRequest::find($id);

        if (!$serviceRequest) {
            return response()->json(['status' => 'error', 'message' => 'الطلب غير موجود.'], 404);
        }

        // // 3. حماية أمنية: إذا كان الطلب مدفوعاً مسبقاً، نمنع تغيير سعره أو حالته للتلاعب
        // if ($serviceRequest->status === 'paid' || $serviceRequest->status === 'completed') {
        //     return response()->json(['status' => 'error', 'message' => 'لا يمكن تعديل طلب تم تسديد رسومه الماليّة بالفعل.'], 400);
        // }

        // 4. تجهيز البيانات وتحديثها بمرونة
        $updateData = [
            'status'     => $request->status,
            'admin_note' => $request->admin_note ?? $serviceRequest->admin_note,
        ];

        // إذا تحولت الحالة إلى بانتظار الدفع، نحقن السعر والتاريخ المستهدف
        if ($request->status === 'awaiting_payment') {
            $updateData['fee_amount'] = (float) $request->fee_amount;
            if ($request->payment_deadline) {
                $updateData['payment_deadline'] = $request->payment_deadline;
            }
        }

        $serviceRequest->update($updateData);

        // 5. بناء نص الإشعار الذكي بناءً على الحالة الجديدة ليتلقاه الطالب فوراً
        $notificationMessage = "تم تحديث حالة طلبك ذو الرقم (#{$serviceRequest->id}) إلى: " . $request->status;

        if ($request->status === 'awaiting_payment') {
            $notificationMessage = "طلبك رقم (#{$serviceRequest->id}) مقبول مبدئياً، يرجى تسديد الرسوم البالغة (" . number_format($request->fee_amount) . " ل.س) من محفظتك الإلكترونية.";
        } elseif ($request->status === 'ready') {
            $notificationMessage = "طلبك رقم (#{$serviceRequest->id}) أصبح جاهزاً، يرجى مراجعة نافذة شؤون الطلاب للاستلام.";
        }

        // 6. إرسال الإشعار الفعلي للطالب (باستخدام حقل الكولوم الحقيقي student_id)
        $studentModel = User::find($serviceRequest->student_id);
        if ($studentModel) {
            $studentModel->notify(new SystemNotification(
                'تحديث بشأن طلبك 📄',
                $notificationMessage,
                ['request_id' => $serviceRequest->id]
            ));
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'تم تحديث حالة الطلب وإشعار الطالب بنجاح.',
            'data'    => [
                'request_id' => $serviceRequest->id,
                'status'     => $serviceRequest->status,
                'fee_amount' => (float) $serviceRequest->fee_amount
            ]
        ], 200);
    }

    /**
     * 3. استعراض الطالب لطلباته الشخصية وتتبع حالاتها
     * GET /api/student/requests
     */
    public function getStudentRequests()
    {
        try {
            // جلب طلبات الطالب الحالي المسجل دخوله مع ربط اسم المادة إن وجدت
            $requests = DB::table('service_requests')
                ->leftJoin('courses', 'service_requests.course_id', '=', 'courses.id')
                ->where('service_requests.student_id', Auth::id())
                ->select(
                    'service_requests.id',
                    'service_requests.request_type',
                    'service_requests.status',
                    'service_requests.fee_amount',
                    'service_requests.admin_note',
                    'service_requests.payment_deadline',
                    'service_requests.created_at',
                    'courses.name as course_name'
                )
                ->orderBy('service_requests.id', 'desc')
                ->get();

            return response()->json([
                'status' => 'success',
                'count'  => $requests->count(),
                'data'   => $requests
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'حدث خطأ أثناء جلب الطلبات: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * 4. تحديد أو تحديث الموعد النهائي للاعتراض/إعادة العملي لمادة معينة (خاص بالإدارة)
     * POST /api/admin/course-deadlines
     */
    public function setCourseDeadline(Request $request)
    {
        $request->validate(
            [
                'course_id'    => 'required|integer|exists:courses,id',
                'request_type' => 'required|in:objection,lab_redo',
                'beginning_date' => 'prohibited_if:request_type,objection|date',
                'end_date'     => 'required|date|after:now', // يجب أن يكون تاريخ مستقبلي
            ],
            [
                'beginning_date.prohibited_if' => 'عذراً، لا يمكن تحديد تاريخ البدء يدوياً عندما يكون نوع الطلب اعتراض (objection).',
            ]
        );;

        if (Auth::user()->role !== 'admin') {
            return response()->json(['status' => 'error', 'message' => 'غير مصرح لك بإعداد مواعيد المواد.'], 403);
        }

        try {
            $courseId    = $request->input('course_id');
            $requestType = $request->input('request_type');
            $beginningDate = $request->input('beginning_date');
            $endDate     = $request->input('end_date');

            // أسلوب الـ UpdateOrCreate: إذا كانت المادة لها مهلة سابقة يقوم بتحديثها، وإلا ينشئ مهلة جديدة
            DB::table('course_deadlines')
                ->updateOrInsert(
                    ['course_id' => $courseId, 'request_type' => $requestType], // شروط البحث
                    ['end_date' => $endDate, 'created_at' => now()] // البيانات المراد إدخالها أو تحديثها
                );

            return response()->json([
                'status'  => 'success',
                'message' => 'تم تحديد الموعد النهائي لتقديم الطلبات لهذه المادة بنجاح.'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'حدث خطأ: ' . $e->getMessage()
            ], 500);
        }
    }
}
