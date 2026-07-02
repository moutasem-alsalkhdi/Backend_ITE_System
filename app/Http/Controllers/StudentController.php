<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Exception;

class StudentController extends Controller
{
    /**
     * جلب بيانات الملف الشخصي للطالب مع نص الـ QR الخاص به
     * GET /api/student/profile
     */
    public function getProfile()
    {
        try {
            // جلب بيانات الطالب الحالي المسجل دخوله عبر الـ Token
            $student = DB::table('users')
                ->where('id', Auth::id())
                ->select('id', 'name', 'university_id', 'exam_number', 'qr_code', 'year_of_study', 'department')
                ->first();

            if (!$student) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'لم يتم العثور على بيانات هذا الطالب.'
                ], 404);
            }

            // إرجاع البيانات ومن ضمنها نص الـ QR الصاف ي للمطور
            return response()->json([
                'status'  => 'success',
                'data'    => [
                    'id'            => $student->id,
                    'name'          => $student->name,
                    'university_id' => $student->university_id,
                    'exam_number'   => $student->exam_number,
                    'qr_code_text'  => $student->qr_code,
                    'year_of_study' => $student->year_of_study,
                    'department'    => $student->department
                ]
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'حدث خطأ أثناء جلب البيانات: ' . $e->getMessage()
            ], 500);
        }
    }
}