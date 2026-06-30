<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Exception;
use Illuminate\Support\Facades\Auth;

class AdminManagementController extends Controller
{
    public function storeAdmin(Request $request)
    {
        // 1️⃣ التحقق من البيانات المدخلة بعناية
        $request->validate([
            'name'       => 'required|string|max:255',
            // الإيميل وكلمة المرور إجبارية للآدمن عكس السكيما العامة المشتركة مع الطلاب
            'email'      => 'required|email|max:255|unique:users,email',
            'password'   => 'required|string|min:8|confirmed',

        ], [
            'name.required'       => 'اسم الآدمن مطلوب.',
            'email.required'      => 'البريد الإلكتروني مطلوب لحساب الإدارة.',
            'email.unique'        => 'هذا البريد الإلكتروني مستخدم بالفعل في النظام.',
            'password.required'   => 'كلمة المرور مطلوبة.',
            'password.confirmed'  => 'تأكيد كلمة المرور غير متطابق.',
            'password.min'        => 'يجب ألا تقل كلمة المرور عن 8 محارف.',
        ]);

        // 2️⃣ إنشاء سجل الآدمن الجديد في قاعدة البيانات
        $admin = User::create([
            'name'        => $request->name,
            'email'       => $request->email,
            'password'    => Hash::make($request->password),
            'role'        => 'admin', // تثبيت الصلاحية كـ admin تلقائياً لحماية الـ API
            // باقي الحقول (مثل university_id, exam_number) ستنزل تلقائياً null لأنها خاصة بالطلاب
        ]);

        // 3️⃣ إرجاع استجابة نجاح نظيفة للمستخدم
        return response()->json([
            'status'  => 'success',
            'message' => 'تم إنشاء حساب الآدمن بنجاح بنجاح.',
            'data'    => [
                'id'         => $admin->id,
                'name'       => $admin->name,
                'email'      => $admin->email,
                'role'       => $admin->role,
            ]
        ], 201);
    }
    /**
     * جلب قائمة الدكاترة والمعيدين المسجّلين في النظام
     * GET /api/admin/doctors
     *
     * Query Params اختيارية:
     *   - role : تصفية بالدور (doctor أو assistant) — افتراضياً يجلب الاثنين
     */
    public function getDoctors(Request $request)
    {
        try {
            // التأكد أن المستخدم الحالي آدمن
            if (Auth::user()->role !== 'admin') {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'غير مصرح لك بعرض هذه البيانات.'
                ], 403);
            }

            $query = User::whereIn('role', ['doctor', 'assistant'])
                ->select('id', 'name', 'email', 'role', 'created_at');

            // فلتر اختياري بالدور
            if ($request->has('role') && in_array($request->query('role'), ['doctor', 'assistant'])) {
                $query->where('role', $request->query('role'));
            }

            $doctors = $query->orderBy('role')->orderBy('name')->get();

            return response()->json([
                'status' => 'success',
                'count'  => $doctors->count(),
                'data'   => $doctors,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'حدث خطأ أثناء جلب البيانات: ' . $e->getMessage()
            ], 500);
        }
    }
}
