<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Wallet;
use Exception;

class StudentController extends Controller
{
    /**
     * جلب بيانات الملف الشخصي للطالب
     * GET /api/student/profile
     */
    public function getProfile()
    {
        try {
            // جلب بيانات الطالب الحالي المسجل دخوله عبر الـ Token
            $student = DB::table('users')
                ->where('id', Auth::id())
                ->select('id', 'name', 'university_id', 'exam_number', 'qr_code', 'year_of_study', 'department','role')
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
                    'department'    => $student->department,
                    'role'          => $student->role,
                ]
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'حدث خطأ أثناء جلب البيانات: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * البحث عن طالب عبر رقمه الجامعي وعرض معلوماته
     * GET /api/student/search
     */
    public function searchStudent(Request $request)
    {
        $request->validate([
            'university_id' => 'required|string',
        ]);

        // جلب الطالب مع التأكد من أن دوره (student)
        $student = User::where('university_id', $request->university_id)
            ->whereIn('role', ['student', 'volunteer'])
            ->first();

        if (!$student) {
            return response()->json([
                'status' => 'error',
                'message' => 'عذراً، لم يتم العثور على طالب بهذا الرقم الجامعي.'
            ], 404);
        }

        // جلب المحفظة أو إنشاؤها فوراً برصيد 0 إذا لم تكن موجودة مسبقاً
        $wallet = Wallet::where('user_id' , $student->id)->first();

        return response()->json([
            'status' => 'success',
            'data' => [
                'student_id'    => $student->id,
                'name'          => $student->name,
                'father_name'     => $student->name,
                'university_id' => $student->university_id,
                'department'    => $student->department,
                'year_of_study' => $student->year_of_study,
                'exam_number'     => $student->exam_number,
                'group_number'     => $student->group_number,
                'current_balance' => (float) $wallet->balance
            ]
        ]);
    }

}