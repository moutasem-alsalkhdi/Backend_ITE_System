<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Exception;

class NotificationController extends Controller
{
    /**
     * جلب كافة الإشعارات الخاصة بالمستخدم الحالي
     * GET /api/notifications
     */
    public function index()
    {
        try {
            /** @var \App\Models\User $user */ // 💡 هذا السطر يحل مشكلة الـ Undefined method للمحرر
            $user = Auth::user();

            $notifications = $user->notifications()->get();

            return response()->json([
                'status' => 'success',
                'count'  => $notifications->count(),
                'unread_count' => $user->unreadNotifications()->count(),
                'data'   => $notifications
            ], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     *  جلب الإشعارات غير المقروءة فقط
     * GET /api/notifications/unread
     */
    public function unread()
    {
        try {
            /** @var \App\Models\User $user */ // 💡 يحل خطأ التوابع غير المعرفة
            $user = Auth::user();

            return response()->json([
                'status' => 'success',
                'count'  => $user->unreadNotifications()->count(),
                'data'   => $user->unreadNotifications()->get()
            ], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * تحويل إشعار معين إلى "مقروء"
     * POST /api/notifications/{id}/read
     */
    public function markAsRead(string $id)
    {
        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();

            $notification = $user->notifications()->where('id', $id)->first();

            if (!$notification) {
                return response()->json(['status' => 'error', 'message' => 'الإشعار غير موجود.'], 404);
            }

            $notification->markAsRead();

            return response()->json([
                'status'  => 'success',
                'message' => 'تم تعيين الإشعار كمقروء بنجاح.'
            ], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * تعيين كافة الإشعارات كمقروءة دفعة واحدة
     * POST /api/notifications/read-all
     */
    public function markAllAsRead()
    {
        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();

            $user->unreadNotifications->markAsRead();

            return response()->json([
                'status'  => 'success',
                'message' => 'تم تعيين جميع الإشعارات كمقروءة.'
            ], 200);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
    /**
     * حذف إشعار معين نهائياً
     * DELETE /api/notifications/{id}
     */
    public function deleteNotification(string $id)
    {
        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();

            $notification = $user->notifications()->where('id', $id)->first();

            if (!$notification) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'الإشعار غير موجود أو لا تملك صلاحية حذفه.'
                ], 404);
            }

            $notification->delete();

            return response()->json([
                'status'  => 'success',
                'message' => 'تم حذف الإشعار بنجاح.'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'حدث خطأ أثناء حذف الإشعار: ' . $e->getMessage()
            ], 500);
        }
    }
}
