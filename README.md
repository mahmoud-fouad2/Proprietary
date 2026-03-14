# ZACO Assets Management System

**Developed by:** Mahmoud Fouad  
**Contact:** mahmoud.a.fouad2@gmail.com | +966 530047640 | +20 1116588189  
**Portfolio:** [ma-fo.info](https://ma-fo.info)  
**Copyright:** © 2024-<?= date('Y') ?> Mahmoud Fouad. All Rights Reserved.

---

يعمل على مسار النشر: `https://zaco.sa/assets`

## المتطلبات (Shared Hosting)

## Email (Cleaning Reports)

This project can send email notifications for the Cleaning module using SMTP.

1) Install PHP dependencies on the server:

- `composer install --no-dev --optimize-autoloader`

2) Configure SMTP in `config/config.local.php` (do not commit secrets):

- `mail.enabled` (true/false)
- `mail.from_email`, `mail.from_name`
- `mail.smtp.host`, `mail.smtp.port`, `mail.smtp.encryption` (tls/ssl), `mail.smtp.username`, `mail.smtp.password`
- Recipients:
	- `mail.cleaning.daily_to` (default: f.waleed@bfi.sa)
	- `mail.cleaning.weekly_to` (default: m.fouad@zaco.sa)

Optional (recommended): set `app.public_url` in config to build absolute links inside emails.

### Weekly cleaning report (cron)

Schedule (example: every Monday 08:00) to send the previous week's report:

- `php scripts/cron_cleaning_weekly_report.php`

To resend the same week (manual):

- `php scripts/cron_cleaning_weekly_report.php --force`
## التثبيت
1) أنشئ قاعدة بيانات MySQL (مثال: `zaco_assets`).

2) استورد مخطط القاعدة:
- `scripts/schema.mysql.sql`

### ترقية قاعدة بيانات موجودة (Multi-Org)
إذا كان لديك قاعدة بيانات قديمة وظهرت أخطاء مثل: `Unknown column ... org_id` فهذا يعني أن الترقية لم تُطبق على السيرفر.

- شغّل ملف الترقية:
	- `scripts/migrate_orgs.mysql.sql`

ملاحظة: الكود حالياً صار “متوافق” وسيتجاهل ميزة المنظمات تلقائياً إذا لم تكن الأعمدة موجودة، لكن لتفعيل الفلترة بالكامل يجب تنفيذ الترقية.

3) جهّز الإعدادات:
- انسخ `config/config.sample.php` إلى `config/config.local.php`
- عدّل:
	- `db.dsn` و `db.user` و `db.pass`
	- `app.secret_key` (قيمة طويلة عشوائية)
	- اترك `app.base_path` = `/assets`
	- على السيرفر اجعل `app.env = production`

4) ارفع المشروع إلى:
- `public_html/assets/`

## أول دخول
- إذا لم يوجد أي مستخدمين، افتح:
	- `https://zaco.sa/assets/setup`
	لإنشاء أول Super Admin (مرة واحدة فقط).

## ملاحظات
- المجلدات الحساسة محجوبة عبر `.htaccess`.
- الصور/الملفات يتم حفظها داخل `storage/uploads/` ويتم تقديمها عبر Routes محمية.

## استيراد الموظفين (Bulk Import)
تمت إضافة صفحة استيراد جماعي للموظفين عبر النسخ/اللصق من Excel أو CSV:
- افتح: `/employees/import`
- الصق البيانات (TSV/CSV) ثم اضغط “تنفيذ الاستيراد”.

قوالب جاهزة (للتعبئة ثم النسخ/اللصق داخل صفحة الاستيراد):
- `storage/templates/employees_import_template.tsv`
- `storage/templates/employees_import_template.csv`

ملاحظة: عمود `employee_no` اختياري—لو تركته فارغًا النظام يولّد رقم تلقائيًا.

## PDF عبر mPDF (Shared Hosting)
تم إضافة تصدير PDF عبر mPDF لصفحات: الأصول/الموظفين/العُهد.

1) على جهازك المحلي داخل مجلد المشروع:
- `composer install --no-dev`

2) ارفع المجلد `vendor/` إلى نفس مستوى `index.php` على السيرفر.

3) لو زر “تصدير PDF” أعطى رسالة أن المكتبة غير مثبتة، فهذا يعني أن `vendor/autoload.php` غير موجود على السيرفر.
