<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CourseSeeder extends Seeder
{
    public function run(): void
    {
        $courses = [

            // 🔹 السنة الأولى - الفصل الأول
            ['name' => 'التحليل (1)', 'has_lab' => true, 'year_of_study' => 1, 'semester' => 1, 'department' => 'Basic Sciences', 'theory_max_mark' => 80, 'practical_max_mark' => 20],
            ['name' => 'الجبر العام', 'has_lab' => true, 'year_of_study' => 1, 'semester' => 1, 'department' => 'Basic Sciences', 'theory_max_mark' => 80, 'practical_max_mark' => 20],
            ['name' => 'الفيزياء', 'has_lab' => true, 'year_of_study' => 1, 'semester' => 1, 'department' => 'Basic Sciences', 'theory_max_mark' => 80, 'practical_max_mark' => 20],
            ['name' => 'البرمجة (1)', 'has_lab' => true, 'year_of_study' => 1, 'semester' => 1, 'department' => 'Basic Sciences', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'مبادئ عمل الحاسوب', 'has_lab' => true, 'year_of_study' => 1, 'semester' => 1, 'department' => 'Basic Sciences', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'اللغة الأجنبية (1)', 'has_lab' => true, 'year_of_study' => 1, 'semester' => 1, 'department' => 'Basic Sciences', 'theory_max_mark' => 100, 'practical_max_mark' => 0],

            // 🔹 السنة الأولى - الفصل الثاني
            ['name' => 'التحليل (2)', 'has_lab' => true, 'year_of_study' => 1, 'semester' => 2, 'department' => 'Basic Sciences', 'theory_max_mark' => 80, 'practical_max_mark' => 20],
            ['name' => 'الجبر الخطي', 'has_lab' => true, 'year_of_study' => 1, 'semester' => 2, 'department' => 'Basic Sciences', 'theory_max_mark' => 80, 'practical_max_mark' => 20],
            ['name' => 'الدارات الكهربائية والإلكترونية', 'has_lab' => true, 'year_of_study' => 1, 'semester' => 2, 'department' => 'Basic Sciences', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'البرمجة (2)', 'has_lab' => true, 'year_of_study' => 1, 'semester' => 2, 'department' => 'Basic Sciences', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'اللغة العربية', 'has_lab' => false, 'year_of_study' => 1, 'semester' => 2, 'department' => 'Basic Sciences', 'theory_max_mark' => 100, 'practical_max_mark' => 0],
            ['name' => 'الثقافة', 'has_lab' => false, 'year_of_study' => 1, 'semester' => 2, 'department' => 'Basic Sciences', 'theory_max_mark' => 100, 'practical_max_mark' => 0],
            ['name' => 'اللغة الأجنبية (2)', 'has_lab' => false, 'year_of_study' => 1, 'semester' => 2, 'department' => 'Basic Sciences', 'theory_max_mark' => 100, 'practical_max_mark' => 0],

            // 🔹 السنة الثانية - الفصل الأول
            ['name' => 'التحليل (3)', 'has_lab' => true, 'year_of_study' => 2, 'semester' => 1, 'department' => 'Basic Sciences', 'theory_max_mark' => 80, 'practical_max_mark' => 20],
            ['name' => 'خوارزميات وبنى معطيات (1)', 'has_lab' => true, 'year_of_study' => 2, 'semester' => 1, 'department' => 'Basic Sciences', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'دارات منطقية', 'has_lab' => true, 'year_of_study' => 2, 'semester' => 1, 'department' => 'Basic Sciences', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'برمجة (3)', 'has_lab' => true, 'year_of_study' => 2, 'semester' => 1, 'department' => 'Basic Sciences', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'الاحتمالات والإحصاء', 'has_lab' => true, 'year_of_study' => 2, 'semester' => 1, 'department' => 'Basic Sciences', 'theory_max_mark' => 80, 'practical_max_mark' => 20],
            ['name' => 'اللغة الأجنبية (3)', 'has_lab' => false, 'year_of_study' => 2, 'semester' => 1, 'department' => 'Basic Sciences', 'theory_max_mark' => 100, 'practical_max_mark' => 0],

            // 🔹 السنة الثانية - الفصل الثاني
            ['name' => 'التحليل العددي', 'has_lab' => true, 'year_of_study' => 2, 'semester' => 2, 'department' => 'Basic Sciences', 'theory_max_mark' => 80, 'practical_max_mark' => 20],
            ['name' => 'الاتصالات الرقمية', 'has_lab' => true, 'year_of_study' => 2, 'semester' => 2, 'department' => 'Basic Sciences', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'خوارزميات وبنى معطيات (2)', 'has_lab' => true, 'year_of_study' => 2, 'semester' => 2, 'department' => 'Basic Sciences', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'بنيان الحواسيب (1)', 'has_lab' => true, 'year_of_study' => 2, 'semester' => 2, 'department' => 'Basic Sciences', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'مهارات تواصل', 'has_lab' => false, 'year_of_study' => 2, 'semester' => 2, 'department' => 'Basic Sciences', 'theory_max_mark' => 100, 'practical_max_mark' => 0],
            ['name' => 'اللغة الأجنبية (4)', 'has_lab' => false, 'year_of_study' => 2, 'semester' => 2, 'department' => 'Basic Sciences', 'theory_max_mark' => 100, 'practical_max_mark' => 0],

            // 🔹 السنة الثالثة - الفصل الأول
            ['name' => 'بحوث العمليات', 'has_lab' => true, 'year_of_study' => 3, 'semester' => 1, 'department' => 'Basic Sciences', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'لغات البرمجة', 'has_lab' => true, 'year_of_study' => 3, 'semester' => 1, 'department' => 'Basic Sciences', 'theory_max_mark' => 60, 'practical_max_mark' => 40],
            ['name' => 'بنيان الحواسيب (2)', 'has_lab' => true, 'year_of_study' => 3, 'semester' => 1, 'department' => 'Basic Sciences', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'أساسيات الشبكات الحاسوبية', 'has_lab' => true, 'year_of_study' => 3, 'semester' => 1, 'department' => 'Basic Sciences', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'البيانيات الحاسوبية', 'has_lab' => true, 'year_of_study' => 3, 'semester' => 1, 'department' => 'Basic Sciences', 'theory_max_mark' => 70, 'practical_max_mark' => 30],

            // 🔹 السنة الثالثة - الفصل الثاني
            ['name' => 'قواعد معطيات (1)', 'has_lab' => true, 'year_of_study' => 3, 'semester' => 2, 'department' => 'Basic Sciences', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'الأتومات واللغات الصورية', 'has_lab' => true, 'year_of_study' => 3, 'semester' => 2, 'department' => 'Basic Sciences', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'مبادئ الذكاء الصنعي', 'has_lab' => true, 'year_of_study' => 3, 'semester' => 2, 'department' => 'Basic Sciences', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'الحسابات العلمية', 'has_lab' => false, 'year_of_study' => 3, 'semester' => 2, 'department' => 'Basic Sciences', 'theory_max_mark' => 100, 'practical_max_mark' => 0],
            ['name' => 'مشروع السنة الثالثة', 'has_lab' => false, 'year_of_study' => 3, 'semester' => 2, 'department' => 'Basic Sciences', 'theory_max_mark' => 100, 'practical_max_mark' => 0],

            // ================== قسم هندسة البرمجيات ==================

            // السنة الرابعة - الفصل الأول
            ['name' => 'نظم تشغيل (1)', 'has_lab' => true, 'year_of_study' => 4, 'semester' => 1, 'department' => 'software', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'قواعد معطيات (2)', 'has_lab' => true, 'year_of_study' => 4, 'semester' => 1, 'department' => 'software', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'هندسة البرمجيات (1)', 'has_lab' => true, 'year_of_study' => 4, 'semester' => 1, 'department' => 'software', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'المترجمات', 'has_lab' => true, 'year_of_study' => 4, 'semester' => 1, 'department' => 'software', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'خوارزميات البحث الذكية', 'has_lab' => true, 'year_of_study' => 4, 'semester' => 1, 'department' => 'software', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'الاقتصاد والإدارة في المؤسسة', 'has_lab' => true, 'year_of_study' => 4, 'semester' => 1, 'department' => 'software', 'theory_max_mark' => 80, 'practical_max_mark' => 20],

            // السنة الرابعة - الفصل الثاني
            ['name' => 'هندسة البرمجيات (2)', 'has_lab' => true, 'year_of_study' => 4, 'semester' => 2, 'department' => 'software', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'البرمجة التفرعية', 'has_lab' => true, 'year_of_study' => 4, 'semester' => 2, 'department' => 'software', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'نظم الوسائط المتعددة', 'has_lab' => true, 'year_of_study' => 4, 'semester' => 2, 'department' => 'software', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'التسويق', 'has_lab' => false, 'year_of_study' => 4, 'semester' => 2, 'department' => 'software', 'theory_max_mark' => 100, 'practical_max_mark' => 0],
            ['name' => 'مشروع المترجمات', 'has_lab' => false, 'year_of_study' => 4, 'semester' => 2, 'department' => 'software', 'theory_max_mark' => 100, 'practical_max_mark' => 0],
            ['name' => 'مشروع السنة الرابعة', 'has_lab' => false, 'year_of_study' => 4, 'semester' => 2, 'department' => 'software', 'theory_max_mark' => 100, 'practical_max_mark' => 0],

            // السنة الخامسة - الفصل الأول
            ['name' => 'قواعد معطيات متقدمة', 'has_lab' => true, 'year_of_study' => 5, 'semester' => 1, 'department' => 'software', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'تطبيقات الانترنت', 'has_lab' => true, 'year_of_study' => 5, 'semester' => 1, 'department' => 'software', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'إدارة المشاريع', 'has_lab' => true, 'year_of_study' => 5, 'semester' => 1, 'department' => 'software', 'theory_max_mark' => 80, 'practical_max_mark' => 20],
            ['name' => 'امن نظم المعلومات', 'has_lab' => true, 'year_of_study' => 5, 'semester' => 1, 'department' => 'software', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'هندسة البرمجيات (3)', 'has_lab' => true, 'year_of_study' => 5, 'semester' => 1, 'department' => 'software', 'theory_max_mark' => 70, 'practical_max_mark' => 30],

            // السنة الخامسة - الفصل الثاني
            ['name' => 'هندسة نظم المعلومات', 'has_lab' => true, 'year_of_study' => 5, 'semester' => 2, 'department' => 'software', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'نظم البحث عن المعلومات', 'has_lab' => true, 'year_of_study' => 5, 'semester' => 2, 'department' => 'software', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'النظم والتطبيقات الموزعة', 'has_lab' => true, 'year_of_study' => 5, 'semester' => 2, 'department' => 'software', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'مشروع التخرج', 'has_lab' => false, 'year_of_study' => 5, 'semester' => 2, 'department' => 'software', 'theory_max_mark' => 100, 'practical_max_mark' => 0],

            // ================== قسم النظم والشبكات ==================

            // السنة الرابعة - الفصل الأول
            ['name' => 'نظم تشغيل (1)', 'has_lab' => true, 'year_of_study' => 4, 'semester' => 1, 'department' => 'networks', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'برمجة التطبيقات الشبكية', 'has_lab' => true, 'year_of_study' => 4, 'semester' => 1, 'department' => 'networks', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'هندسة البرمجيات (1)', 'has_lab' => true, 'year_of_study' => 4, 'semester' => 1, 'department' => 'networks', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'خوارزميات البحث الذكية', 'has_lab' => true, 'year_of_study' => 4, 'semester' => 1, 'department' => 'networks', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'الاقتصاد والإدارة في المؤسسة', 'has_lab' => true, 'year_of_study' => 4, 'semester' => 1, 'department' => 'networks', 'theory_max_mark' => 80, 'practical_max_mark' => 20],

            // السنة الرابعة - الفصل الثاني
            ['name' => 'نظم تشغيل (2)', 'has_lab' => true, 'year_of_study' => 4, 'semester' => 2, 'department' => 'networks', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'نظم الوسائط المتعددة', 'has_lab' => true, 'year_of_study' => 4, 'semester' => 2, 'department' => 'networks', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'البرمجة التفرعية', 'has_lab' => true, 'year_of_study' => 4, 'semester' => 2, 'department' => 'networks', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'بروتوكولات الاتصال الحاسوبية', 'has_lab' => true, 'year_of_study' => 4, 'semester' => 2, 'department' => 'networks', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'مشروع السنة الرابعة', 'has_lab' => true, 'year_of_study' => 4, 'semester' => 2, 'department' => 'networks', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'التسويق', 'has_lab' => false, 'year_of_study' => 4, 'semester' => 2, 'department' => 'networks', 'theory_max_mark' => 100, 'practical_max_mark' => 0],

            // السنة الخامسة - الفصل الأول
            ['name' => 'نظم وتطبيقات موزعة', 'has_lab' => true, 'year_of_study' => 5, 'semester' => 1, 'department' => 'networks', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'تصميم الشبكات الحاسوبية', 'has_lab' => true, 'year_of_study' => 5, 'semester' => 1, 'department' => 'networks', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'إدارة المشاريع', 'has_lab' => true, 'year_of_study' => 5, 'semester' => 1, 'department' => 'networks', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'إدارة الشبكات الحاسوبية', 'has_lab' => true, 'year_of_study' => 5, 'semester' => 1, 'department' => 'networks', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'أمن نظم المعلومات', 'has_lab' => true, 'year_of_study' => 5, 'semester' => 1, 'department' => 'networks', 'theory_max_mark' => 70, 'practical_max_mark' => 30],

            // السنة الخامسة - الفصل الثاني
            ['name' => 'أمن الشبكات الحاسوبية', 'has_lab' => true, 'year_of_study' => 5, 'semester' => 2, 'department' => 'networks', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'نمذجة ومحاكاه النظم الشبكية', 'has_lab' => true, 'year_of_study' => 5, 'semester' => 2, 'department' => 'networks', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'نظم الزمن الحقيقي', 'has_lab' => true, 'year_of_study' => 5, 'semester' => 2, 'department' => 'networks', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'مشروع التخرج', 'has_lab' => false, 'year_of_study' => 5, 'semester' => 2, 'department' => 'networks', 'theory_max_mark' => 100, 'practical_max_mark' => 0],

            // ================== قسم الذكاء الصنعي ==================

            // السنة الرابعة - الفصل الأول
            ['name' => 'خوارزميات البحث الذكية', 'has_lab' => true, 'year_of_study' => 4, 'semester' => 1, 'department' => 'ai', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'هندسة البرمجيات (1)', 'has_lab' => true, 'year_of_study' => 4, 'semester' => 1, 'department' => 'ai', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'المترجمات', 'has_lab' => true, 'year_of_study' => 4, 'semester' => 1, 'department' => 'ai', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'نظم التشغيل (1)', 'has_lab' => true, 'year_of_study' => 4, 'semester' => 1, 'department' => 'ai', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'الشبكات العصبونية', 'has_lab' => true, 'year_of_study' => 4, 'semester' => 1, 'department' => 'ai', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'الاقتصاد والإدارة في المؤسسة', 'has_lab' => true, 'year_of_study' => 4, 'semester' => 1, 'department' => 'ai', 'theory_max_mark' => 70, 'practical_max_mark' => 30],

            // السنة الرابعة - الفصل الثاني
            ['name' => 'الحقائق الافتراضية', 'has_lab' => true, 'year_of_study' => 4, 'semester' => 2, 'department' => 'ai', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'نظم الوسائط المتعددة', 'has_lab' => true, 'year_of_study' => 4, 'semester' => 2, 'department' => 'ai', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'البرمجة التفرعية', 'has_lab' => true, 'year_of_study' => 4, 'semester' => 2, 'department' => 'ai', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'نظم قواعد المعرفة', 'has_lab' => true, 'year_of_study' => 4, 'semester' => 2, 'department' => 'ai', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'مشروع السنة الرابعة', 'has_lab' => true, 'year_of_study' => 4, 'semester' => 2, 'department' => 'ai', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'التسويق', 'has_lab' => true, 'year_of_study' => 4, 'semester' => 2, 'department' => 'ai', 'theory_max_mark' => 70, 'practical_max_mark' => 30],

            // السنة الخامسة - الفصل الأول
            ['name' => 'معالجة اللغات الطبيعية', 'has_lab' => true, 'year_of_study' => 5, 'semester' => 1, 'department' => 'ai', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'الرؤية الحاسوبية', 'has_lab' => true, 'year_of_study' => 5, 'semester' => 1, 'department' => 'ai', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'الروبوتية', 'has_lab' => true, 'year_of_study' => 5, 'semester' => 1, 'department' => 'ai', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'إدارة المشاريع', 'has_lab' => true, 'year_of_study' => 5, 'semester' => 1, 'department' => 'ai', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'أمن نظم المعلومات', 'has_lab' => true, 'year_of_study' => 5, 'semester' => 1, 'department' => 'ai', 'theory_max_mark' => 70, 'practical_max_mark' => 30],

            // السنة الخامسة - الفصل الثاني
            ['name' => 'المنطق الترجيحي والخوارزميات الوراثية', 'has_lab' => true, 'year_of_study' => 5, 'semester' => 2, 'department' => 'ai', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'التعلم التلقائي', 'has_lab' => true, 'year_of_study' => 5, 'semester' => 2, 'department' => 'ai', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'استكشاف المعرفة', 'has_lab' => true, 'year_of_study' => 5, 'semester' => 2, 'department' => 'ai', 'theory_max_mark' => 70, 'practical_max_mark' => 30],
            ['name' => 'مشروع التخرج', 'has_lab' => true, 'year_of_study' => 5, 'semester' => 2, 'department' => 'ai', 'theory_max_mark' => 100, 'practical_max_mark' => 0],
        ];

        // حقن المصفوفة بالكامل داخل جدول المواد بضغطة زر واحدة
        DB::table('courses')->insert($courses);
    }
}
