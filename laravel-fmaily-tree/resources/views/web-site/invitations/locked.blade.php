@extends('web-site.invitations.layout')

@section('title', 'الدعوات — توثيق الهوية مطلوب')

@section('content')
    <div class="max-w-xl mx-auto mt-4">
        <div class="inv-card rounded-3xl p-8 sm:p-10 text-center">
            <div class="mx-auto w-20 h-20 rounded-full flex items-center justify-center mb-6"
                 style="background: var(--inv-surface-soft); border: 1px solid var(--inv-border-strong);">
                <i class="fas fa-shield-halved text-3xl inv-text-primary" aria-hidden="true"></i>
            </div>
            <h2 class="text-2xl sm:text-3xl font-extrabold mb-3" style="color: var(--inv-text)">
                {{ $customTitle ?? 'مجموعات الدعوات والإرسال' }}
            </h2>
            <p class="inv-text-sec leading-relaxed text-sm sm:text-base mb-6">
                @if(!empty($customMessage))
                    {{ $customMessage }}
                @elseif(!empty($isPinSession))
                    أنت داخل الموقع برمز الدخول. إنشاء المجموعات وإرسال الدعوات متاح فقط بعد توثيق هويتك في شجرة العائلة.
                @else
                    لإنشاء مجموعات وإرسال دعوات واتساب أو إشعارات التطبيق، سجّل الدخول ببصمة الوجه أو كود واتساب.
                @endif
            </p>

            <div class="rounded-2xl inv-inset p-4 text-right text-sm inv-text-sec mb-7 space-y-1.5">
                <p class="font-bold" style="color: var(--inv-text)">
                    <i class="fas fa-circle-info inv-text-primary ml-1.5" aria-hidden="true"></i> لماذا التوثيق؟
                </p>
                <p class="text-xs sm:text-sm inv-muted">حتى نعرف أنك فرد من العائلة، ونربط المجموعات والإرسالات بحسابك في الشجرة — وليس برقم هاتف غير موثّق.</p>
            </div>

            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                <a href="{{ route('site.password.show', ['tab' => 'face', 'redirect' => url('/invitations')]) }}"
                   class="inv-btn-primary inline-flex items-center justify-center gap-2 px-6 py-3 text-sm">
                    <i class="fas fa-user-check" aria-hidden="true"></i> الدخول ببصمة الوجه
                </a>
                <a href="{{ route('site.password.show', ['tab' => 'whatsapp', 'redirect' => url('/invitations')]) }}"
                   class="inv-btn-outline inline-flex items-center justify-center gap-2 px-6 py-3 text-sm">
                    <i class="fab fa-whatsapp" aria-hidden="true"></i> الدخول بكود واتساب
                </a>
            </div>
        </div>
    </div>
@endsection
