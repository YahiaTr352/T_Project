# تطبيق بوابة العائلة وشجرة النسب

![Laravel](https://img.shields.io/badge/Laravel-10.x-red.svg)
![PHP](https://img.shields.io/badge/PHP-8.1+-blue.svg)
![Livewire](https://img.shields.io/badge/Livewire-3.x-green.svg)
![Tailwind CSS](https://img.shields.io/badge/Tailwind%20CSS-3.x-blue.svg)

تطبيق ويب لإدارة شجرة العائلة وبوابة تواصل عائلية (لوحة تحكم + موقع عام + API).

## المتطلبات

- PHP 8.1+
- Composer
- Node.js & npm
- MySQL (مُفضّل) أو PostgreSQL

## التثبيت

```bash
git clone <repository-url>
cd family-tree-app
composer install
npm install
cp .env.example .env
php artisan key:generate
```

عدّل `.env` (قاعدة البيانات، والمفاتيح الاختيارية أدناه)، ثم:

```bash
php artisan migrate
php artisan db:seed
php artisan storage:link
npm run build
php artisan serve
```

التطبيق يعمل على `http://localhost:8000`.

### متغيرات بيئة مهمة

| المتغير | الغرض |
|---------|--------|
| `DB_*` | اتصال قاعدة البيانات |
| `SITE_PASSWORD_*` | حماية الموقع برقم سري (اختياري) |
| `AWS_*` / `AWS_REKOGNITION_COLLECTION` | التعرف على الوجه (اختياري) |
| `ULTRAMSG_*` | إرسال واتساب (اختياري) |
| `OPENAI_API_KEY` / `ANTHROPIC_API_KEY` / `GEMINI_API_KEY` | مزوّدو الذكاء الاصطناعي (اختياري) |

لا تشارك ملف `.env` أبداً. استخدم `.env.example` كمرجع فقط.

## الميزات الرئيسية

- شجرة عائلة تفاعلية (Nested Set)
- إدارة الأشخاص، الصور، القصص، والشارات
- لوحة تحكم بصلاحيات (Spatie Permission)
- معرض ومقالات وبرامج ومجلس عائلي
- مسابقات (عامة / قرآن) ومتجر/إيجار
- دعوات عبر واتساب
- مصادقة (جلسة + Sanctum API) مع دعم OTP ووجه
- سجل زيارات ونشاط (Activity / Audit)

## التقنيات

| الطبقة | الأدوات |
|--------|---------|
| Backend | Laravel 10, Livewire 3, Sanctum |
| Frontend | Blade, Alpine.js, Vite, Tailwind |
| Admin UI | `public/admin-assets` |
| مكتبات | Nested Set, Excel, DomPDF, Spatie Permission |

## هيكل موجز

```
app/
  Http/Controllers/     # لوحة التحكم، الموقع العام، API
  Livewire/             # مكونات Livewire النشطة
  Models/ Services/     # منطق الأعمال
resources/views/        # dashboard + web-site + layouts
routes/                 # web.php, api.php, auth.php
public/admin-assets/    # أصول لوحة التحكم
public/assets/img/      # صور عامة (شعارات / افتراضيات)
```

## التطوير

```bash
npm run dev      # Vite أثناء التطوير
npm run build    # بناء الأصول للإنتاج
php artisan test
./vendor/bin/pint
```

طوابير العمل: تأكد أن `QUEUE_CONNECTION` مضبوط وشغّل `php artisan queue:work` عند الحاجة.

## ملاحظات للمسؤول التالي

- اسم المجلد/الكيان `padges` هو تهجئة قديمة لـ badges — لا تُعاد تسميته بدون migration.
- واجهة الشجرة الحالية: `resources/views/old_index.blade.php` (مستخدمة فعلياً من `FamilyTreeController`).
- `public/build` مُدرَج في المستودع حالياً لتسهيل النشر بدون Node على السيرفر؛ حدّثه بـ `npm run build` بعد تغيير الأصول.

## الترخيص

مشروع خاص (proprietary) — غير مرخّص كـ open source ما لم يُتفق خلاف ذلك.
