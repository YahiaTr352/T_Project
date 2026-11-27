@extends('web-site.invitations.layout')

@section('title', 'الدعوات — أكمل بياناتك الأساسية')

@section('content')
    <div class="max-w-xl mx-auto mt-4">
        <div class="inv-card rounded-3xl p-8 sm:p-10 text-center">
            <div class="mx-auto w-20 h-20 rounded-full inv-badge-warning flex items-center justify-center mb-6">
                <i class="fas fa-user-pen text-3xl" aria-hidden="true"></i>
            </div>
            <h2 class="text-2xl sm:text-3xl font-extrabold mb-3" style="color: var(--inv-text)">أكمل بياناتك الأساسية أولاً</h2>
            <p class="inv-text-sec leading-relaxed text-sm sm:text-base mb-7">
                لا يمكن إرسال الدعوات لأن بياناتك الأساسية ناقصة:
                <strong style="color: var(--inv-text)">{{ implode('، ', $missing ?? []) }}</strong>.
                <br>
                المطلوب فقط: الاسم + تاريخ الميلاد — البيانات الفرعية (السيرة، الوظيفة...) ليست شرطاً.
            </p>
            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                <a href="{{ route('my-profile.show') }}"
                   class="inv-btn-primary inline-flex items-center justify-center gap-2 px-6 py-3 text-sm">
                    <i class="fas fa-user-check" aria-hidden="true"></i> إكمال البيانات الآن
                </a>
                <a href="{{ route('home') }}"
                   class="inv-btn-outline inline-flex items-center justify-center gap-2 px-6 py-3 text-sm">
                    رجوع للرئيسية
                </a>
            </div>
        </div>
    </div>
@endsection
