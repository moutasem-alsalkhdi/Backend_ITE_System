<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Exception;

class AdminFileController extends Controller
{
    /**
     * إضافة (قوائم الطلاب وارقامهم الامتحانية و رقم الفئة)
     * POST /api/import-excel
     */
    public function importExcelData(Request $request)
    {
        $request->validate([
            'excel_file'    => 'required|file|extensions:csv,xlsx,xls',
            'file_type'     => 'required|in:students_list,exam_numbers,group_lists',
            'academic_year' => 'required',
            'department'    => 'required|in:Basic Sciences,software,networks,ai',
            'year_of_study' => 'required_if:file_type,students_list|integer|between:1,5',
        ]);

        $file_type     = $request->input('file_type');
        $academic_year = $request->input('academic_year');
        $department    = $request->input('department');
        $student_year  = $request->input('year_of_study');
        $admin_id      = Auth::id();

        $path      = $request->file('excel_file')->store('admin_files', 'public');
        $full_path = storage_path('app/public/' . $path);

        DB::beginTransaction();

        try {
            // ─── قراءة الملف (CSV أو Excel) ──────────────────
            $spreadsheet = IOFactory::load($full_path);
            $sheet       = $spreadsheet->getActiveSheet();
            $rows        = $sheet->toArray();

            if (empty($rows)) {
                throw new Exception("الملف فارغ.");
            }

            // ─── قراءة أسماء الأعمدة من السطر الأول ──────────
            $headers = array_map('trim', $rows[0]);
            $headers = array_map('strtolower', $headers);

            // دالة للبحث عن index العمود باسمه
            $col = function (string $name) use ($headers): int {
                $index = array_search($name, $headers);
                if ($index === false) {
                    throw new Exception("العمود '{$name}' غير موجود في الملف.");
                }
                return $index;
            };

            // ─── تسجيل الملف في uploaded_files ───────────────
            DB::table('uploaded_files')->insert([
                'uploaded_by'   => $admin_id,
                'file_type'     => $file_type === 'students_list' ? 'exam_numbers' : $file_type,
                'file_url'      => $path,
                'academic_year' => $academic_year,
                'uploaded_at'   => now(),
            ]);

            $inserted_count = 0;
            $updated_count  = 0;

            // ─── معالجة كل سطر (نتخطى السطر الأول) ──────────
            foreach (array_slice($rows, 1) as $row) {

                // تخطي الأسطر الفارغة
                if (empty(array_filter($row))) continue;

                if ($file_type === 'students_list') {

                    $name          = trim($row[$col('name')]);
                    //$father_name   = trim($row[$col('father_name')]);
                    $university_id = trim($row[$col('university_id')]);

                    if (!$name  || !$university_id) continue;

                    $exists = DB::table('users')
                        ->where('university_id', $university_id)
                        ->exists();

                    if (!$exists) {
                        // 1. إذا كان الطالب جديد تماماً -> نقوم بإنشائه مع حفظ القسم
                        DB::table('users')->insert([
                            'university_id' => $university_id,
                            'name'          => $name,
                            //'father_name'   => $father_name,
                            'role'          => 'student',
                            'department'    => $department, // 🎯 مضاف: حفظ القسم المختار للطالب الجديد
                            'year_of_study' => $student_year,
                            'qr_code'       => 'QR-' . $university_id . '-' . Str::random(5),
                            'created_at'    => now(),
                            'updated_at'    => now(),
                        ]);
                        $inserted_count++;
                    } else {
                        // 2. إذا كان الطالب موجود مسبقاً -> نحدث سنته الدراسية وقسمه الحالي
                        DB::table('users')
                            ->where('university_id', $university_id)
                            ->update([
                                'year_of_study' => $student_year,
                                'department'    => $department, // 🎯 مضاف: تحديث القسم في حال تم نقله أو تعديله
                                'updated_at'    => now()
                            ]);
                        $updated_count++;
                    }
                } elseif ($file_type === 'exam_numbers') {

                    $university_id = trim($row[$col('name')]);
                    //$father_name   = trim($row[$col('father_name')]);
                    $exam_number   = trim($row[$col('exam_number')]);

                    if (!$university_id || !$exam_number) continue;

                    $affected = DB::table('users')
                        ->where('name', $university_id)
                        ->whereIn('role', ['student', 'volunteer'])
                        ->update(['exam_number' => $exam_number]);

                    if ($affected > 0) $updated_count++;
                } elseif ($file_type === 'group_lists') {

                    $university_id = trim($row[$col('university_id')]);
                    $group_number  = trim($row[$col('group_number')]);

                    if (!$university_id || !$group_number) continue;

                    $affected = DB::table('users')
                        ->where('university_id', $university_id)
                        ->whereIn('role', ['student', 'volunteer'])
                        ->update(['group_number' => $group_number]);

                    if ($affected > 0) $updated_count++;
                }
            }

            DB::commit();

            $message = $file_type === 'students_list'
                ? "تم معالجة قائمة الطلاب: تسجيل ({$inserted_count}) طالب جديد، وتحديث بيانات ({$updated_count}) طالب موجود مسبقاً في قسم ({$department})."
                : "تم تحديث بيانات ({$updated_count}) طالب بنجاح.";

            return response()->json([
                'status'  => 'success',
                'message' => $message,
                'data'    => [
                    'inserted' => $inserted_count,
                    'updated'  => $updated_count,
                ]
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status'  => 'error',
                'message' => 'حدث خطأ: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function downloadFile(int $id)
    {
        $file = DB::table('lecture_files')->where('id', $id)->first();

        if (!$file) {
            return response()->json(['message' => 'الملف غير موجود'], 404);
        }

        $full_path = storage_path('app/public/' . $file->file_url);

        if (!file_exists($full_path)) {
            return response()->json(['message' => 'الملف غير موجود على السيرفر'], 404);
        }

        return response()->download($full_path, basename($file->file_url));
    }

}
