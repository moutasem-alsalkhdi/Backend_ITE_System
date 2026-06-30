<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SystemNotification extends Notification
{
    use Queueable;

    private $title;
    private $message;
    private $extraData;

    // الممرر (Constructor) لاستقبال عنوان الإشعار ونصّه وأي بيانات إضافية (مثل رقم الطلب أو معرف المادة)
    public function __construct(string $title, string $message, array $extraData = [])
    {
        $this->title = $title;
        $this->message = $message;
        $this->extraData = $extraData;
    }

    // تحديد القناة (نختار هنا التخزين في قاعدة البيانات)
    public function via($notifiable): array
    {
        return ['database'];
    }

    // هيكلية البيانات التي سيتم تحويلها لـ JSON وتخزينها في قاعدة البيانات
    public function toDatabase($notifiable): array
    {
        return [
            'title'      => $this->title,
            'message'    => $this->message,
            'extra_data' => $this->extraData, // لربط الإشعار بشاشة معينة مستقبلاً في الفرونت اند
        ];
    }
}