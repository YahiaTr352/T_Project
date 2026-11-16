@extends('web-site.invitations.layout')

@section('title', 'الدعوات — توثيق الهوية مطلوب')

@section('content')
    <div class="max-w-2xl mx-auto">
        <div class="glass-card rounded-3xl p-8 sm:p-12 text-center">
            <div class="mx-auto w-20 h-20 rounded-2xl bg-gradient-to-br from-amber-400 to-orange-500 flex items-center justify-center mb-6 shadow-lg">
                <i class="fas fa-lock text-3xl text-white"></i>
            </div>
            <h1 class="text-3xl font-extrabold text-slate-900 mb-3">{{ $customTitle ?? 'مجموعات الدعوات والإرسال' }}</h1>
            <p class="text-slate-600 leading-relaxed mb-6">
                @if(!empty($customMessage))
                    {{ $customMessage }}
                @elseif(!empty($isPinSession))
                    أنت داخل الموقع برمز الدخول. إنشاء المجموعات وإرسال الدعوات متاح فقط بعد توثيق هويتك في شجرة العائلة.
                @else
                    لإنشاء مجموعات وإرسال دعوات واتساب أو إشعارات التطبيق، سجّل الدخول ببصمة الوجه أو كود واتساب.
                @endif
            </p>
            <div class="rounded-2xl bg-slate-50 border border-slate-200 p-5 text-right text-sm text-slate-600 mb-8 space-y-2">
                <p class="font-bold text-slate-800"><i class="fas fa-shield-halved text-emerald-600 ml-1"></i> لماذا التوثيق؟</p>
                <p>حتى نعرف أنك فرد من العائلة، ونربط المجموعات والإرسالات بحسابك في الشجرة — وليس برقم هاتف غير موثّق.</p>
            </div>
            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                <a href="{{ route('site.password.show', ['tab' => 'face', 'redirect' => url('/invitations')]) }}"
                   class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl bg-emerald-600 text-white font-bold hover:bg-emerald-700 transition">
                    <i class="fas fa-user-gear"></i> الدخول ببصمة الوجه
                </a>
                <a href="{{ route('site.password.show', ['tab' => 'whatsapp', 'redirect' => url('/invitations')]) }}"
                   class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl bg-white border-2 border-emerald-600 text-emerald-700 font-bold hover:bg-emerald-50 transition">
                    <i class="fab fa-whatsapp"></i> الدخول بكود واتساب
                </a>
            </div>
        </div>
    </div>
@endsection
