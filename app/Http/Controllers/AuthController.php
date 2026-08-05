<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\OtpLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function adminLogin(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)
            ->where('role', 'admin')
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'بيانات تسجيل الدخول غير صحيحة'
            ], 401);
        }

        $token = $user->createToken('admin-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role,
            ]
        ]);
    }
    // ─── تسجيل دخول الطالب ───────────────────────────────
    public function studentLogin(Request $request)
    {
        $request->validate([
            'university_id' => 'required|string',
        ]);

        $user = User::where('university_id', $request->university_id)
            ->whereIn('role', ['student', 'volunteer'])
            ->first();

        if (!$user) {
            return response()->json(['message' => 'الرقم الجامعي غير صحيح'], 401);
        }

        $token = $user->createToken('student-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'            => $user->id,
                'name'          => $user->name,
                'university_id' => $user->university_id,
                'exam_number'   => $user->exam_number,
                'year_of_study' => $user->year_of_study,
                'group_number'  => $user->group_number,
                'department'    => $user->department,
                'role'          => $user->role,
            ]
        ]);
    }

    /**
     * إضافة دكتور / معيد جديد
     * POST /api/auth/doctor/register
     */
    public function registerDoctor(Request $request)
    {
        if (Auth::user()->role !== 'admin') {
            return response()->json([
                'status'  => 'error',
                'message' => 'غير مصرح لك بالقيام بهذا الإجراء، هذا الصلاحية للإدارة فقط.'
            ], 403);
        }
        $request->validate([
            'name'  => 'required|string|max:100',
            'email' => 'required|email|unique:users,email',
            'role'  => 'required|in:doctor,assistant',
        ]);

        $user = User::create([
            'name'  => $request->name,
            'email' => $request->email,
            'role'  => $request->role,
        ]);

        return response()->json([
            'message' => 'تم إضافة المستخدم بنجاح',
            'user'    => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role,
            ]
        ], 201);
    }


    // ─── 1. طلب OTP  ─────────────────────────────────────────
    public function requestOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)
            ->whereIn('role', ['doctor', 'assistant'])
            ->first();

        if (!$user) {
            return response()->json(['message' => 'الإيميل غير مسجل في النظام'], 404);
        }

        // إلغاء أي OTP قديم
        OtpLog::where('user_id', $user->id)->where('is_used', false)->delete();

        $otp = rand(100000, 999999);
        $referenceToken = Str::random(64); // 🎯 توليد رمز مرجعي فريد وطويل جداً للعملية

        OtpLog::create([
            'user_id'         => $user->id,
            'otp_code'        => $otp,
            'reference_token' => $referenceToken, // 🎯 حفظه في القاعدة
            'is_used'         => false,
            'expires_at'      => Carbon::now()->addMinutes(10),
        ]);

        Mail::raw("رمز التحقق الخاص بك: {$otp}", function ($message) use ($user) {
            $message->to($user->email)->subject('رمز تسجيل الدخول');
        });

        // 🎯 نعيد الرمز المرجعي للفرونت إند ليحتفظ به بدلاً من الإيميل
        return response()->json([
            'message'   => 'تم إرسال رمز التحقق على إيميلك',
            'otp_token' => $referenceToken
        ]);
    }

    // ─── 2. تسجيل دخول الدكتور  ─────────────────────
    public function doctorLogin(Request $request)
    {
        $request->validate([
            'otp_token' => 'required|string', // 🎯 استبدال الإيميل بالتوكن المرجعي
            'otp_code'  => 'required|string',
        ]);

        // 🎯 البحث عن الـ OTP ومطابقته مع الرمز المرجعي المتوقع
        $otp = OtpLog::where('reference_token', $request->otp_token)
            ->where('otp_code', $request->otp_code)
            ->where('is_used', false)
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if (!$otp) {
            return response()->json(['message' => 'الرمز غير صحيح أو منتهي الصلاحية'], 401);
        }

        // جلب بيانات المستخدم بناءً على الـ OTP الصحيح
        $user = User::find($otp->user_id);

        if (!$user || !in_array($user->role, ['doctor', 'assistant'])) {
            return response()->json(['message' => 'صلاحية الدخول غير مسموحة'], 403);
        }

        $otp->update(['is_used' => true]);

        $token = $user->createToken('doctor-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role,
            ]
        ]);
    }
    public function changePassword(Request $request)
    {
        // 1️⃣ التحقق من صحة المدخلات وقواعد كلمة المرور الجديدة
        $request->validate([
            'current_password' => 'required|string',
            // different:current_password تضمن ألا تكون الكلمة الجديدة مطابقة للقديمة
            'new_password'     => 'required|string|min:8|confirmed|different:current_password',
        ], [
            'current_password.required' => 'كلمة المرور الحالية مطلوبة.',
            'new_password.required'     => 'كلمة المرور الجديدة مطلوبة.',
            'new_password.min'          => 'يجب ألا تقل كلمة المرور الجديدة عن 8 محارف.',
            'new_password.confirmed'    => 'تأكيد كلمة المرور الجديدة غير متطابق.',
            'new_password.different'    => 'يجب أن تكون كلمة المرور الجديدة مختلفة عن كلمة المرور الحالية.',
        ]);

        // جلب سجل المستخدم الحالي المسجل دخوله عبر التوكن
        $user = User::find(Auth::id());

        // 2️⃣ التحقق أمنياً من أن كلمة المرور الحالية المكتوبة تطابق المخزنة في القاعدة
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'كلمة المرور الحالية التي أدخلتها غير صحيحة!'
            ], 422);
        }

        // 3️⃣ تحديث كلمة المرور الجديدة بعد تشفيرها
        $user->update([
            'password' => Hash::make($request->new_password),
            // 'must_change_password' => false // أزل التعليق عن هذا السطر في حال اعتمدت الفكرة السابقة
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'تم تغيير كلمة المرور بنجاح.'
        ], 200);
    }

    // ─── تسجيل خروج ──────────────────────────────────────
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'تم تسجيل الخروج بنجاح']);
    }
}
