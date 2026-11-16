<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StorageFallbackController extends Controller
{
    /**
     * معالجة طلبات ملفات التخزين /storage/{path}
     * في حال وجود الملف محلياً يتم تقديمه مباشرة.
     * في حال عدم وجوده، يتم التحويل تلقائياً إلى رابط الإنتاج المحدد في .env (MEDIA_BASE_URL).
     */
    public function handle(Request $request, $path)
    {
        $cleanPath = ltrim($path, '/');

        // 1. التحقق من وجود الملف في التخزين المحلي
        if (Storage::disk('public')->exists($cleanPath)) {
            return response()->file(Storage::disk('public')->path($cleanPath));
        }

        $directPublicPath = public_path('storage/' . $cleanPath);
        if (file_exists($directPublicPath) && !is_dir($directPublicPath)) {
            return response()->file($directPublicPath);
        }

        // 2. التحقق من متغير بيئة وسائط الإنتاج MEDIA_BASE_URL
        $rawEnv = env('MEDIA_BASE_URL');
        $mediaBaseUrl = $rawEnv !== null ? trim((string) $rawEnv) : 'https://alsorayia.com';

        // إذا تم تعيين دومين إنتاج وليس محلياً، نحوّل الطلب إليه تلقائياً
        if (!empty($mediaBaseUrl) && !str_contains($mediaBaseUrl, 'localhost') && !str_contains($mediaBaseUrl, '127.0.0.1')) {
            $remoteUrl = rtrim($mediaBaseUrl, '/') . '/storage/' . $cleanPath;
            return redirect()->away($remoteUrl, 302);
        }

        // 3. في حال عدم وجود الملف محلياً ولم يُحدد دومين خارجي
        abort(404, 'File not found');
    }
}
