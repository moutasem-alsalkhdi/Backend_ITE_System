<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Wallet;
use Exception;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Notifications\SystemNotification;
use App\Models\ServiceRequest;

class FinanceWalletController extends Controller
{

    /**
     * 1. البحث عن الطالب وعرض رصيد محفظته الحالي
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
        $wallet = Wallet::firstOrCreate(
            ['user_id' => $student->id],
            ['balance' => 0.00]
        );

        return response()->json([
            'status' => 'success',
            'data' => [
                'student_id'    => $student->id,
                'name'          => $student->name,
                'university_id' => $student->university_id,
                'department'    => $student->department,
                'year_of_study' => $student->year_of_study,
                'current_balance' => (float) $wallet->balance
            ]
        ]);
    }

    /**
     * 2. شحن محفظة الطالب (Credit Process)
     */
    public function chargeWallet(Request $request)
    {
        $request->validate([
            'university_id' => 'required|string|exists:users,university_id',
            'amount'       => 'required|numeric|min:1000', // الحد الأدنى للشحن مثلاً 1000 ليرة
            'description'  => 'nullable|string|max:255',
        ]);

        // 2️⃣ جلب بيانات الطالب والتأكد من أن حسابه بصلاحية (student)
        $student = User::where('university_id', $request->university_id)
            ->whereIn('role', ['student', 'volunteer'])
            ->first();

        if (!$student) {
            return response()->json([
                'status'  => 'error',
                'message' => 'عذراً، هذا الرقم الجامعي لا ينتمي لحساب طالب.'
            ], 404);
        }

        $adminId = Auth::id(); // معرف الموظف الذي قام بالشحن يدوياً
        $amount  = (float) $request->input('amount');
        $desc    = $request->input('description') ?? 'شحن رصيد نقدي من الدائرة المالية';

        DB::beginTransaction();

        try {
            // 🎯 قفل أمان صارم: البحث باستخدام معرف الـ id الخاص بالطالب المستخرج بأمان
            $wallet = Wallet::where('user_id', $student->id)->lockForUpdate()->first();

            if (!$wallet) {
                // تلافياً لأي خطأ، إن لم تكن المحفظة موجودة ننشئها بربطها بـ user_id
                $wallet = Wallet::create([
                    'user_id' => $student->id,
                    'balance' => 0.00
                ]);
            }

            $balanceBefore = (float) $wallet->balance;
            $balanceAfter  = $balanceBefore + $amount;

            // 1. تحديث رصيد المحفظة الفعلي
            $wallet->update([
                'balance' => $balanceAfter
            ]);

            // 2. تسجيل الحركة المالية في الأرشيف للتدقيق والمحاسبة
            DB::table('wallet_transactions')->insert([
                'wallet_id'      => $wallet->id,
                'type'           => 'credit', // شحن
                'amount'         => $amount,
                'balance_before' => $balanceBefore,
                'balance_after'  => $balanceAfter,
                'description'    => $desc,
                'processed_by'   => $adminId,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            DB::commit();

            // 3. إرسال إشعار فوري للطالب (استخدام متغير $student مباشرة دون استعلام إضافي لتسريع الأداء)
            $student->notify(new SystemNotification(
                'تم شحن محفظتك',
                'تم إضافة ' . number_format($amount) . ' ل.س إلى محفظتك. رصيدك الحالي: ' . number_format($balanceAfter) . ' ل.س.',
                ['type' => 'wallet_charge', 'amount' => $amount, 'new_balance' => $balanceAfter]
            ));

            return response()->json([
                'status'  => 'success',
                'message' => "تم شحن حساب الطالب ({$student->name}) بنجاح بمبلغ " . number_format($amount) . " ل.س.",
                'data'    => [
                    'transaction_type' => 'credit',
                    'university_id'    => $student->university_id,
                    'charged_amount'   => $amount,
                    'new_balance'      => $balanceAfter,
                ]
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status'  => 'error',
                'message' => 'حدث خطأ غير متوقع أثناء معالجة العملية المالية: ' . $e->getMessage()
            ], 500);
        }
    }
}
