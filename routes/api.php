<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminManagementController;
use App\Http\Controllers\AdminFileController;
use App\Http\Controllers\LectureFileController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\GradeController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\ServiceRequestController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\FinanceWalletController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\CourseAssignmentController;

Route::post('/admin/login', [AuthController::class, 'adminLogin']);
Route::post('/auth/student/login',      [AuthController::class, 'studentLogin']);
Route::post('/auth/doctor/request-otp', [AuthController::class, 'requestOtp']);
Route::post('/auth/doctor/login',       [AuthController::class, 'doctorLogin']);

Route::middleware('auth:sanctum')->group(function () {
    // ════════════════════════════════════════
    // ADMIN APIs
    // ════════════════════════════════════════

    Route::post('/admin/create', [AdminManagementController::class, 'storeAdmin']); // إضافة آدمن جديد
 
    Route::post('/user/change-password', [AuthController::class, 'changePassword']); // تغيير كلمة المرورالحالية

    Route::get('/admin/doctors', [AdminManagementController::class, 'getDoctors']); // جلب قائمة الدكاترة والمعيدين المسجّلين في النظام
    Route::put('/admin/students/givevolunteerrole', [AdminManagementController::class, 'givevolunteerrole']); //منح الآدمن الطالب صلاحيات الفريق التطوعي

    Route::post('/admin/courses/assign-staff', [CourseAssignmentController::class, 'assignStaff']); //إسناد مادة (نظري أو عملي) لمجموعة من الدكاترة أو المعيدين
    Route::get('/admin/courses/assignments', [CourseAssignmentController::class, 'getCourseAssignments']); //جلب قائمة المواد مع تفاصيل الكادر التدريسي
 
    Route::post('/auth/doctor/register', [AuthController::class, 'registerDoctor']); //إضافة دكتور / معيد جديد

    Route::post('/admin/semester/open', [EnrollmentController::class, 'openNewSemester']); // فتح فصل دراسي جديد وتهيئة مواد كل الطلاب تلقائياً
    
    Route::post('/import-excel', [AdminFileController::class, 'importExcelData']); //إضافة (قوائم الطلاب وارقامهم الامتحانية و رقم الفئة)
    
    Route::get('/courses/info', [CourseController::class, 'getCoursesInfo']); // جلب معلومات المواد (مع إمكانية الفرز والتصفية حسب السنة والقسم)
    
    Route::post('/admin/grades/import-excel', [GradeController::class, 'importExcelGrades']); //تصدير ومعالجة درجات الطلاب من ملف Excel أو CSV
    
    Route::put('/admin/grades/exceptional-modify', [GradeController::class, 'exceptionalModify']); // التعديل الاستثنائي للعلامات

    Route::post('/admin/schedules', [ScheduleController::class, 'uploadSchedule']); //رفع جدول دراسي

    Route::post('/admin/course-deadlines', [ServiceRequestController::class, 'setCourseDeadline']); //تحديد أو تحديث الموعد النهائي للاعتراض/إعادة العملي لمادة معينة

    Route::get('/admin/service-requests', [ServiceRequestController::class, 'getAdminRequests']); //جلب وتصنيف طلبات الخدمات الطلابية للإدارة
    
    Route::put('/admin/requests/{id}/status', [ServiceRequestController::class, 'updateStatus']); //تحديث حالة الطلب وإضافة الرسوم والملاحظات
   
    Route::get('/student/search', [StudentController::class, 'searchStudent']); //البحث عن طالب بواسطة الرقم الجامعي وعرض معلوماته

    Route::post('/finance/wallet/charge', [FinanceWalletController::class, 'chargeWallet']); //شحن المحفظة برصيد


    
    // ════════════════════════════════════════
    // DOCTOR APIs
    // ════════════════════════════════════════
    Route::get('/doctor/courses/assignments', [CourseAssignmentController::class, 'getCourseAssignments']); //جلب للمدرس مواده المسندة إليه فقط
    Route::get('/doctor/attendance/list', [AttendanceController::class, 'getLectureAttendance']); //جلب قائمة الحضور لمادة معينة 
    Route::post('/attendance/session/start', [AttendanceController::class, 'startAttendanceSession']); //1. بدء جلسة حضور جديدة
    Route::post('/attendance/record', [AttendanceController::class, 'recordAttendance']); //2. مسح QR وتسجيل حضور الطالب
    Route::post('/attendance/session/end', [AttendanceController::class, 'endAttendanceSession']); // 3. إنهاء جلسة الحضور وإرسال الإشعارات
    Route::get('/student/attendance', [AttendanceController::class, 'getStudentAttendance']); //جلب ملخص الحضور للطالب

    // ════════════════════════════════════════
    // STUDENT APIs
    // ════════════════════════════════════════

    Route::put('/students/givevolunteerrole', [AdminManagementController::class, 'givevolunteerrole']); //منح الطالب الطالب صلاحيات الفريق التطوعي
    Route::get('/student/my-enrolled-courses', [CourseController::class, 'getMyEnrolledCourses']); //جلب مواد الطالب المسجل بيها بالفصل الدراسي الحالي
    Route::get('/student/eligible-courses', [CourseController::class, 'getEligibleCourses']); //المواد التي لها مواعيد نهائية فعالة ولم تنتهِ بعد
    Route::get('/student/getSemesterCourses', [CourseController::class, 'getSemesterCourses']); //جلب مواد الطالب في الفصل الحالي والسنة الدراسية الحالية (تفيد الفريق التطوعي)
    Route::get('/student/courses-by-year', [CourseController::class, 'getCoursesByYear']); //جلب كل مواد سنة دراسية معينة (لقسم الطالب الحالي) — لمستودع المحاضرات
    Route::get('/student/academic-record', [GradeController::class, 'getAcademicRecord']); //السجل الأكاديمي للعلامات
    Route::get('/student/profile', [StudentController::class, 'getProfile']); //جلب بيانات الملف الشخصي للطالب
    Route::get('/announcements', [AnnouncementController::class, 'getAnnouncements']); //جلب الإعلانات
    Route::get('/student/schedules', [ScheduleController::class, 'getSchedules']); //استعراض الجداول الدراسية للطالب (مع ميزة التصفح)
    Route::post('/student/requests', [ServiceRequestController::class, 'submitRequest']); //تقديم طلب إداري
    Route::get('/student/requests', [ServiceRequestController::class, 'getStudentRequests']); //استعراض الطالب لطلباته الشخصية وتتبع حالاتها

    Route::get('/notifications', [NotificationController::class, 'index']); //جلب كافة الإشعارات الخاصة بالمستخدم الحالي
    Route::get('/notifications/unread', [NotificationController::class, 'unread']); //جلب الإشعارات غير المقروءة فقط
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']); //تحويل إشعار معين إلى "مقروء"
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']); //تعيين كافة الإشعارات كمقروءة دفعة واحدة
    Route::delete('/notifications/{id}',[NotificationController::class, 'deleteNotification']); //حذف إشعار معين نهائياً

    Route::get('/student/wallet/balance',[WalletController::class, 'getBalance']); //عرض رصيد المحفظة الحالي للطالب المسجّل دخوله
    Route::get('/student/wallet/transactions',[WalletController::class, 'getTransactions']); //عرض سجل المعاملات المالية للطالب (شحن + دفع)
    Route::post('/student/wallet/pay',[WalletController::class, 'payForService']); //دفع مبلغ مقابل طلب والخصم من المحفظة
    Route::get('/student/attendance/detailed', [AttendanceController::class, 'getDetailedAttendance']); //جلب سجل الحضور الكامل للطالب بناءً على المادة والنوع (نظري وعملي)
    


    // ════════════════════════════════════════
    // SHARED APIs
    // ════════════════════════════════════════
    Route::get('/semester/current', [EnrollmentController::class, 'getCurrentSemester']); //جلب بيانات (السنة الاكاديمية والفصل الدراسي) الحالي والنشط في النظام
    Route::post('/LectureFile/upload-lecfile', [LectureFileController::class, 'uploadLectureFile']); //رفع ملفات المحاضرات (الدكتور و الفريق التطوعي)
    Route::get('/LectureFile/getCourseLectures', [LectureFileController::class, 'getCourseLectures']); // جلب محاضرات مادة معينة   
    Route::get('/LectureFile/archived', [LectureFileController::class, 'archivedfiles']); // جلب المحاضرات المؤرشفة
    Route::get('/LectureFile/download/{id}', [LectureFileController::class, 'download']); // تحميل ملف محاضرة
    Route::delete('/LectureFile/{id}',[LectureFileController::class, 'deleteFile']); //حذف ملف محاضرة (الدكتور و الفريق التطوعي) نهائيا من قاعدة البيانات والسيرفر
    Route::post('/academic/announcements', [AnnouncementController::class, 'createAnnouncement']); //نشر إعلان جديد من قبل الدكتور أو الإدارة

    Route::post('/auth/logout', [AuthController::class, 'logout']);
});
