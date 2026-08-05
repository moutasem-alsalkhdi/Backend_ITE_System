<?php

namespace App\Http\Controllers;

use App\Models\LectureFile;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
class LectureFileController extends Controller
{
    /**
     * رفع ملفات المحاضرات (الدكتور و الفريق التطوعي) 
     */
    public function uploadLectureFile(Request $request)
    {
        $currentUser = $request->user();

        if (!$currentUser || !in_array($currentUser->role, ['admin', 'doctor', 'volunteer'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'عذراً، لا تملك الصلاحية الكافية لإجراء هذه العملية.'
            ], 403);
        }
        // التحقق من الحقول القادمة من الـ Postman
        $request->validate([
            'course_id'     => 'required|integer',
            'title'         => 'required|string|max:200',
            'academic_year' => 'required|string',
            'lecture_file'  => 'required|mimes:pdf,doc,docx,ppt,pptx,zip,rar|max:20480',
        ]);

        try {
            if ($request->file('lecture_file')->isValid()) {

                // تخزين الملف المرفوع في مجلد public/course_lectures
                $path = $request->file('lecture_file')->store('course_lectures', 'public');
                $user = Auth::user();

                // إدخال كافة المعلومات في قاعدة البيانات
                $lecture = LectureFile::create([
                    'course_id'     => $request->input('course_id'),
                    'uploaded_by'   => Auth::id(),
                    'title'         => $request->input('title'),
                    'file_url'      => $path,
                    'uploader_type' => $user ? $user->role : null,
                    'academic_year' => $request->input('academic_year'),
                    'is_archived'   => false,
                    'uploaded_at'   => now(),
                ]);

                return response()->json([
                    'status'  => 'success',
                    'message' => 'تم حفظ المحاضرة وتعبئة كامل البيانات بنجاح.',
                    'data'    => $lecture
                ], 201);
            }

            throw new Exception("الملف غير صالح للرفع.");
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'حدث خطأ أثناء رفع المحاضرة: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * جلب محاضرات مادة معينة
     * GET /api/LectureFile/getCourseLectures
     */
    public function getCourseLectures(Request $request)
    {
        $request->validate([
            'course_id'     => 'required|integer|exists:courses,id',
            'uploader_type' => 'nullable|in:doctor,volunteer',
        ]);

        try {
            $query = LectureFile::with(['course', 'uploader:id,name'])
                ->where('course_id', $request->query('course_id'))
                ->where('is_archived', false);

            if ($request->filled('uploader_type')) {
                $query->where('uploader_type', $request->query('uploader_type'));
            }

            $lectures = $query->orderBy('uploaded_at', 'desc')->get();

            return response()->json([
                'status'  => 'success',
                'count'   => $lectures->count(),
                'data'    => $lectures
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'حدث خطأ أثناء جلب المحاضرات: ' . $e->getMessage()
            ], 500);
        }
    }


    /**
     * جلب المحاضرات المؤرشفة
     * GET /api/LectureFile/archived
     */
    public function archivedfiles(Request $request)
    {
        $request->validate([
            'course_id'     => 'required|integer|exists:courses,id',
            'uploader_type' => 'nullable|in:doctor,volunteer',
        ]);

        try {
            $query = LectureFile::with(['course', 'uploader:id,name'])
                ->where('course_id', $request->query('course_id'))
                ->where('is_archived', true);

            if ($request->filled('uploader_type')) {
                $query->where('uploader_type', $request->query('uploader_type'));
            }

            $lectures = $query->orderBy('uploaded_at', 'desc')->get();

            return response()->json([
                'status'  => 'success',
                'count'   => $lectures->count(),
                'data'    => $lectures
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'حدث خطأ أثناء جلب المحاضرات: ' . $e->getMessage()
            ], 500);
        }
    }


    /**
     * @param int|string $id
     * تحميل ملف محاضرة
     * GET /api/LectureFile/download/{id}
     */
    public function download($id)
    {
        try {
            // 1. البحث عن المحاضرة في قاعدة البيانات
            $lecture = LectureFile::find($id);

            if (!$lecture) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'المحاضرة المطلوبة غير موجودة في النظام.'
                ], 404);
            }

            if (!Storage::disk('public')->exists($lecture->file_url)) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'عذراً، ملف المحاضرة غير موجود الفيزيائية على السيرفر.'
                ], 404);
            }


            $extension = pathinfo($lecture->file_url, PATHINFO_EXTENSION);

            $safeTitle = preg_replace('/[^A-Za-z0-9_\-\x{0600}-\x{06FF} ]/u', '', $lecture->title);
            $downloadName = $safeTitle . '.' . $extension;

            /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
            $disk = Storage::disk('public');

            return $disk->download($lecture->file_url, $downloadName);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'حدث خطأ أثناء محاولة تحميل الملف: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * حذف ملف محاضرة نهائياً (من قاعدة البيانات ومن السيرفر)
     * DELETE /api/LectureFile/{id}
     */
    public function deleteFile($id)
    {
        try {
            $user    = Auth::user();
            $lecture = LectureFile::find($id);

            if (!$lecture) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'الملف المطلوب غير موجود.'
                ], 404);
            }

            // الدكتور يحذف بس ملفاته الشخصية
            // أي عضو بالفريق التطوعي (volunteer) يقدر يحذف أي ملف رفعه فريق تطوعي، بغض النظر مين رفعه بالتحديد
            $canDelete = false;

            if ($user->role === 'doctor') {
                $canDelete = $lecture->uploaded_by === $user->id;
            } elseif ($user->role === 'volunteer') {
                $canDelete = $lecture->uploader_type === 'volunteer';
            }

            if (!$canDelete) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'غير مصرح لك بحذف هذا الملف.'
                ], 403);
            }

            if (Storage::disk('public')->exists($lecture->file_url)) {
                Storage::disk('public')->delete($lecture->file_url);
            }

            $lecture->delete();

            return response()->json([
                'status'  => 'success',
                'message' => 'تم حذف الملف نهائياً من النظام والسيرفر.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'حدث خطأ أثناء حذف الملف: ' . $e->getMessage()
            ], 500);
        }
    }
}
