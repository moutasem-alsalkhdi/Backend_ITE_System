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

    Route::post('/admin/create', [AdminManagementController::class, 'storeAdmin']);
    // رابط تغيير كلمة المرور للمستخدم الحالي (آدمن، دكتور، طالب...)
    Route::post('/user/change-password', [AuthController::class, 'changePassword']);

    // ── عرض الدكاترة والمعيدين ──────────────────────────────────────────
    Route::get('/admin/doctors', [AdminManagementController::class, 'getDoctors']);
    // روابط إسناد وإدارة مواد الدكاترة والمعيدين للآدمن
    Route::post('/admin/courses/assign-staff', [CourseAssignmentController::class, 'assignStaff']);
    Route::get('/doctor/course-assignment', [CourseAssignmentController::class, 'getdoctorCourseAssignment']);
    Route::get('/courses/assignments', [CourseAssignmentController::class, 'getCourseAssignments']);
 
    Route::post('/auth/doctor/register',    [AuthController::class, 'registerDoctor']);

    // رابط فتح الفصل الدراسي الجديد وتهيئة مواد كل الطلاب تلقائياً
    Route::post('/admin/semester/open', [EnrollmentController::class, 'openNewSemester']);
    Route::post('/import-excel', [AdminFileController::class, 'importExcelData']);
    // رابط جلب معلومات المواد (مع إمكانية الفرز والتصفية)
    Route::get('/courses/info', [CourseController::class, 'getCoursesInfo']);
    Route::get('/student/eligible-courses', [CourseController::class, 'getEligibleCourses']);
    
    Route::post('/LectureFile/upload-lecfile', [LectureFileController::class, 'uploadLectureFile']);
    Route::get('/LectureFile', [LectureFileController::class, 'index']);    // جلب محاضرات مادة معينة   
    Route::get('/LectureFile/download/{id}', [LectureFileController::class, 'download']); // تحميل ملف محاضرة
    // DELETE /api/LectureFile/{id}        → حذف نهائي من قاعدة البيانات والسيرفر — صاحب الملف
    Route::delete('/LectureFile/{id}',[LectureFileController::class, 'deleteFile']);


    Route::post('/attendance/record-qr', [AttendanceController::class, 'recordAttendanceByQr']);
    // المسار الجديد الخاص بالطالب لاستعراض حضوره
  
    Route::get('/doctor/attendance/list', [AttendanceController::class, 'getLectureAttendance']);
    //  صلاحيات الإدارة الجامعية والكنترول (Admin / Control Panel)
    Route::post('/admin/grades/import-excel', [GradeController::class, 'importExcelGrades']);
    // 1. التعديل الاستثنائي للعلامات
    Route::put('/admin/grades/exceptional-modify', [GradeController::class, 'exceptionalModify']);

    // 2. جلب السجل الأكاديمي (للطالب لعلاماته، أو للإدمن لعلامات أي طالب)
    Route::get('/student/academic-record', [GradeController::class, 'getAcademicRecord']);
    Route::get('/student/profile', [StudentController::class, 'getProfile']);
    Route::post('/academic/announcements', [AnnouncementController::class, 'createAnnouncement']);
    Route::get('/announcements', [AnnouncementController::class, 'getAnnouncements']);

    // 1. رابط الإدارة لرفع جدول دراسي جديد
    Route::post('/admin/schedules', [ScheduleController::class, 'uploadSchedule']);

    // 2. رابط الطلاب لاستعراض الجداول (الافتراضي و الفلترة المرنة)
    Route::get('/student/schedules', [ScheduleController::class, 'getSchedules']);

    // رابط تحديد المواعيد النهائية للمواد من قبل الإدارة
    Route::post('/admin/course-deadlines', [ServiceRequestController::class, 'setCourseDeadline']);

    // 1. روابط الطلاب (تقديم واستعراض وتتبع الطلبات)
    Route::post('/student/requests', [ServiceRequestController::class, 'submitRequest']);
    Route::get('/student/requests', [ServiceRequestController::class, 'getStudentRequests']);
    // رابط لوحة تحكم الإدارة لجلب وتصنيف الطلبات
    Route::get('/admin/service-requests', [ServiceRequestController::class, 'getAdminRequests']);

    // 2. رابط الإدارة (تعديل الحالة، إضافة الرسوم والملاحظات والإشعارات)
    Route::put('/admin/requests/{id}/status', [ServiceRequestController::class, 'updateStatus']);
    //Route::post('/services/requests/{id}/update-status', [FinanceWalletController::class, 'updateRequestStatus']);

    // روابط نظام الإشعارات 🔔
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread', [NotificationController::class, 'unread']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::delete('/notifications/{id}',[NotificationController::class, 'deleteNotification']);

    // مسار البحث عن طالب بواسطة الرقم الجامعي
    Route::get('/finance/student/search', [FinanceWalletController::class, 'searchStudent']);
    // مسار شحن الرصيد كاش
    Route::post('/finance/wallet/charge', [FinanceWalletController::class, 'chargeWallet']);
    // مسار قيام الآدمن بتحديث حالة الطلب وتحديد السعر المستحق

    Route::get('/student/wallet/balance',[WalletController::class, 'getBalance']);
    Route::get('/student/wallet/transactions',[WalletController::class, 'getTransactions']);
    Route::post('/student/wallet/pay',[WalletController::class, 'payForService']);

    // أضف هذه الروابط الجديدة:

    // ── جلسات الحضور الجديدة ──
    Route::post('/attendance/session/start', [AttendanceController::class, 'startAttendanceSession']);
    Route::post('/attendance/session/end', [AttendanceController::class, 'endAttendanceSession']);
    Route::post('/attendance/record', [AttendanceController::class, 'recordAttendance']);
    
    // ── جلب سجل الحضور التفصيلي ──
    Route::get('/student/attendance/detailed', [AttendanceController::class, 'getDetailedAttendance']);
    Route::get('/student/attendance', [AttendanceController::class, 'getStudentAttendance']);




    Route::post('/auth/logout', [AuthController::class, 'logout']);
});

Route::get('/students/pdf/{id}', [AdminFileController::class, 'downloadFile']);
