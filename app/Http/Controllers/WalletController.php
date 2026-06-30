<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Wallet;
use App\Models\ServiceRequest;
use Exception;

class WalletController extends Controller
{
    /**
     * عرض رصيد المحفظة الحالي للطالب المسجّل دخوله
     * GET /api/student/wallet/balance
     */
    public function getBalance(Request $request)
    {
        try {
            $student = $request->user();

            $wallet = Wallet::where('user_id', $student->id)->first();

            // إذا لم تكن المحفظة موجودة نعيدها برصيد 0 بدل خطأ
            if (!$wallet) {
                return response()->json([
                    'status' => 'success',
                    'data'   => [
                        'balance'    => 0.00,
                        'has_wallet' => false,
                    ]
                ], 200);
            }

            return response()->json([
                'status' => 'success',
                'data'   => [
                    'balance'    => (float) $wallet->balance,
                    'has_wallet' => true,
                ]
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'حدث خطأ أثناء جلب رصيد المحفظة: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * عرض سجل المعاملات المالية للطالب (شحن + دفع)
     * GET /api/student/wallet/transactions
     */
    public function getTransactions(Request $request)
    {
        try {
            $student = $request->user();

            $wallet = Wallet::where('user_id', $student->id)->first();

            if (!$wallet) {
                return response()->json([
                    'status' => 'success',
                    'data'   => [],
                    'count'  => 0,
                ], 200);
            }

            $query = DB::table('wallet_transactions')
                ->where('wallet_id', $wallet->id)
                ->select(
                    'id',
                    'type',
                    'amount',
                    'balance_before',
                    'balance_after',
                    'description',
                    'created_at'
                );

            // فلتر اختياري بنوع الحركة (credit أو debit)
            if ($request->has('type') && in_array($request->query('type'), ['credit', 'debit'])) {
                $query->where('type', $request->query('type'));
            }

            $transactions = $query->orderBy('id', 'desc')->paginate(20);

            return response()->json([
                'status'          => 'success',
                'current_balance' => (float) $wallet->balance,
                'meta'            => [
                    'current_page' => $transactions->currentPage(),
                    'last_page'    => $transactions->lastPage(),
                    'total'        => $transactions->total(),
                ],
                'data' => $transactions->items(),
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'حدث خطأ أثناء جلب سجل المعاملات: ' . $e->getMessage()
            ], 500);
        }
    }
    public function payForService(Request $request)
    {
        // 1. نستقبل فقط معرف الطلب الأكاديمي، ولا نقبل أي حقل مالي من الفرونت إند
        $request->validate([
            'service_request_id' => 'required|integer|exists:service_requests,id',
        ]);

        $student = $request->user();

        DB::beginTransaction();

        try {
            // 2. تحديد المبلغ: جلب الطلب من قاعدة البيانات للتأكد من وجوده وصاحبه وسعره
            $serviceRequest = ServiceRequest::where('id', $request->service_request_id)
                ->where('student_id', $student->id)
                ->first();

            if (!$serviceRequest) {
                throw new Exception("لم يتم العثور على هذا الطلب أو أنك لا تملك صلاحية الوصول إليه.");
            }

            // 3. 🎯  قفل أمان يمنع الدفع إلا إذا وافق الآدمن وحدد السعر
            if ($serviceRequest->status !== 'awaiting_payment') {
                if ($serviceRequest->status === 'paid') {
                    throw new Exception("هذا الطلب مدفوع ومسدد مسبقاً بالفعل.");
                }
                if ($serviceRequest->status === 'pending') {
                    throw new Exception("هذا الطلب قيد المراجعة حالياً، يرجى انتظار تحديد الرسوم المالية من الموظف.");
                }
                throw new Exception("لا يمكن تسديد رسوم هذا الطلب بسبب حالته الحالية: " . $serviceRequest->status);
            }

            // 4. قراءة المبلغ الذي حدده الآدمن في السيرفر بشكل آمن 100%
            $amountToDeduct = (float) $serviceRequest->fee_amount;

            if ($amountToDeduct <= 0) {
                throw new Exception("خطأ في احتساب قيمة الرسوم لهذه الخدمة.");
            }

            // 5. قفل سطر المحفظة في قاعدة البيانات لمنع تداخل العمليات المتزامنة (Race Conditions)
            $wallet = Wallet::where('user_id', $student->id)->lockForUpdate()->first();

            if (!$wallet || $wallet->balance < $amountToDeduct) {
                throw new Exception("رصيد محفظتك الحالي غير كافٍ. تحتاج إلى " . number_format($amountToDeduct) . " ل.س. لإتمام العملية.");
            }

            $balanceBefore = (float) $wallet->balance;
            $balanceAfter  = $balanceBefore - $amountToDeduct;

            // 6. خصم المبلغ من محفظة الطالب
            $wallet->update([
                'balance' => $balanceAfter
            ]);

            // 7. تسجيل حركة الخصم المالي في الأرشيف (Debit)
            DB::table('wallet_transactions')->insert([
                'wallet_id'      => $wallet->id,
                'type'           => 'debit',
                'amount'         => $amountToDeduct,
                'balance_before' => $balanceBefore,
                'balance_after'  => $balanceAfter,
                'description'    => "دفع رسوم الكترونية لطلب الخدمة رقم (#{$serviceRequest->id})",
                'processed_by'   => null,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            // 8. تحديث حالة الطلب الأكاديمي ليصبح "مدفوع" في جدول الخدمات
            $serviceRequest->update([
                'status' => 'paid',
            ]);

            DB::commit();

            return response()->json([
                'status'  => 'success',
                'message' => 'تم خصم الرصيد بنجاح وتسديد رسوم الطلب الجامعي.',
                'data'    => [
                    'transaction_type' => 'debit',
                    'deducted_amount'  => $amountToDeduct,
                    'remaining_balance' => $balanceAfter,
                    'service_request_id' => $serviceRequest->id
                ]
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
