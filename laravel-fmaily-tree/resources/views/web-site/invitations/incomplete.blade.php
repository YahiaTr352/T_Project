@extends('web-site.invitations.layout')

@section('title', 'الدعوات — أكمل بياناتك الأساسية')

@section('content')
    <div class="max-w-2xl mx-auto">
        <div class="glass-card rounded-3xl p-8 sm:p-12 text-center">
            <div class="mx-auto w-20 h-20 rounded-2xl bg-gradient-to-br from-amber-400 to-orange-500 flex items-center justify-center mb-6 shadow-lg">
                <i class="fas fa-user-pen text-3xl text-white"></i>
            </div>
            <h1 class="text-3xl font-extrabold text-slate-900 mb-3">أكمل بياناتك الأساسية أولاً</h1>
            <p class="text-slate-600 leading-relaxed mb-6">
                لا يمكن إرسال الدعوات لأن بياناتك الأساسية ناقصة:
                <strong class="text-slate-900">{{ implode('، ', $missing ?? []) }}</strong>.
                <br>
                المطلوب فقط: الاسم + تاريخ الميلاد — البيانات الفرعية (السيرة، الوظيفة...) ليست شرطاً.
            </p>
            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                <a href="{{ route('my-profile.show') }}"
                   class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl bg-emerald-600 text-white font-bold hover:bg-emerald-700 transition">
                    <i class="fas fa-user-check"></i> إكمال البيانات الآن
                </a>
                <a href="{{ route('home') }}"
                   class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl bg-white border font-bold hover:bg-slate-50 transition">
                    رجوع للرئيسية
                </a>
            </div>
        </div>
    </div>
@endsection
