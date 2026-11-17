<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سجل الدعوات</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;900&family=Amiri:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        @include('web-site.invitations.partials.typography-fonts')

        body {
            font-family: 'Tajawal', sans-serif;
            background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 50%, #a7f3d0 100%);
            min-height: 100vh;
        }

        h1, h2, h3 {
            font-family: 'Amiri', serif;
        }

        [data-invitation-font="tajawal"] .invitation-log-text { font-family: 'Invitation Tajawal', Tajawal, sans-serif; }
        [data-invitation-font="amiri"] .invitation-log-text { font-family: 'Invitation Amiri', Amiri, serif; }
        [data-invitation-weight="regular"] .invitation-log-text { font-weight: 400; }
        [data-invitation-weight="bold"] .invitation-log-text { font-weight: 700; }

        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
        }

        .log-item {
            transition: all 0.3s ease;
        }

        .log-item:hover {
            background-color: #f0fdf4;
            transform: translateX(-5px);
        }
    </style>
</head>

<body class="antialiased">
    @include('partials.main-header')

    <div class="py-8 px-4 sm:px-6 lg:px-8">
        <div class="max-w-6xl mx-auto">
            {{-- Header --}}
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-3xl font-extrabold text-gray-900 mb-2">
                        <i class="fas fa-history text-purple-600 mr-2"></i>
                        سجل الدعوات
                    </h1>
                </div>
                <a href="{{ route('invitations.index') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-white rounded-xl border border-gray-200 hover:bg-gray-50 transition">
                    <i class="fas fa-arrow-right"></i>
                    العودة
                </a>
            </div>

            <nav aria-label="سجل الدعوات" class="mb-6 flex gap-2">
                <a href="{{ route('invitations.logs') }}" @if(!$incoming) aria-current="page" @endif
                    class="rounded-xl border px-5 py-2.5 font-bold {{ !$incoming ? 'border-green-700 bg-green-700 text-white' : 'border-gray-200 bg-white text-gray-700' }}">
                    الصادر
                </a>
                <a href="{{ route('invitations.logs', ['direction' => 'incoming']) }}" @if($incoming) aria-current="page" @endif
                    class="rounded-xl border px-5 py-2.5 font-bold {{ $incoming ? 'border-green-700 bg-green-700 text-white' : 'border-gray-200 bg-white text-gray-700' }}">
                    الوارد
                </a>
            </nav>

            {{-- Alerts --}}
            @if(session('success'))
                <div role="status" class="mb-6 rounded-xl bg-green-50 border-l-4 border-green-400 p-4">
                    <div class="flex">
                        <i class="fas fa-check-circle text-green-400 text-xl mr-3"></i>
                        <p class="text-sm text-green-700 font-medium">{{ session('success') }}</p>
                    </div>
                </div>
            @endif
            @if(session('response_error'))
                <p role="alert" class="mb-6 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm font-medium text-rose-800">
                    {{ session('response_error') }}
                </p>
            @endif

            {{-- Logs List --}}
            @if($notifications->count() > 0)
                <div class="space-y-4">
                    @foreach($notifications as $notification)
                    @php
                        $fontWeight = $notification->targeting_filters['font_weight'] ?? '';
                        $fontFamily = $notification->targeting_filters['font_family'] ?? ($fontWeight ? 'tajawal' : '');
                    @endphp
                    @if($incoming)
                    <details class="glass-card rounded-2xl p-6" data-invitation-font="{{ $fontFamily }}" data-invitation-weight="{{ $fontWeight }}"
                        @if(session('responded_invitation_id') === $notification->id) open @endif>
                        <summary class="cursor-pointer">
                            <span class="invitation-log-text text-lg font-bold text-gray-900">{{ $notification->title ?: 'دعوة بدون عنوان' }}</span>
                            <span class="mt-1 block text-sm text-gray-600">
                                من {{ trim($notification->person?->first_name . ' ' . $notification->person?->last_name) ?: 'مرسل غير معروف' }}
                                · {{ ($notification->sent_at ?? $notification->created_at)?->format('Y-m-d H:i') }}
                            </span>
                        </summary>
                        <p class="invitation-log-text mt-4 whitespace-pre-wrap border-t border-gray-100 pt-4 text-gray-800">{{ $notification->body }}</p>
                        @if($notification->media_type === 'image' && $notification->media_url)
                            <img src="{{ $notification->media_url }}" alt="صورة الدعوة" class="mt-4 max-h-96 max-w-full rounded-xl">
                        @endif
                        @include('web-site.invitations.partials.response-form')
                    </details>
                    @continue
                    @endif

                    <div class="glass-card rounded-2xl p-6 log-item" data-invitation-font="{{ $fontFamily }}" data-invitation-weight="{{ $fontWeight }}">
                        <div class="flex items-start justify-between gap-4 mb-4">
                            <div class="flex items-start gap-4 flex-1">
                                <div class="w-12 h-12 rounded-full flex items-center justify-center flex-shrink-0
                                    {{ $notification->status === 'sent' ? 'bg-green-100' : ($notification->status === 'failed' ? 'bg-red-100' : 'bg-blue-100') }}">
                                    <i class="fab fa-whatsapp text-xl
                                        {{ $notification->status === 'sent' ? 'text-green-600' : ($notification->status === 'failed' ? 'text-red-600' : 'text-blue-600') }}"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h3 class="invitation-log-text text-lg font-bold text-gray-900 mb-1">
                                        {{ $notification->title ?: 'رسالة واتساب' }}
                                    </h3>
                                    <p class="invitation-log-text text-sm text-gray-600 mb-2 line-clamp-2">
                                        {{ Str::limit($notification->body, 120) }}
                                    </p>
                                    <div class="flex items-center gap-4 text-xs text-gray-500">
                                        <span>
                                            <i class="fas fa-users mr-1"></i>
                                            {{ $notification->recipients->count() }} مستلم
                                        </span>
                                        <span>
                                            <i class="fas fa-clock mr-1"></i>
                                            {{ $notification->created_at->format('Y-m-d H:i') }}
                                        </span>
                                        @if($notification->media_type)
                                            <span>
                                                @if($notification->media_type === 'image')
                                                    <i class="fas fa-image mr-1 text-blue-500"></i> صورة
                                                @elseif($notification->media_type === 'video')
                                                    <i class="fas fa-video mr-1 text-red-500"></i> فيديو
                                                @elseif($notification->media_type === 'voice')
                                                    <i class="fas fa-microphone mr-1 text-yellow-500"></i> صوت
                                                @endif
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="flex-shrink-0">
                                @if($notification->status === 'pending_approval')
                                    <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-medium bg-amber-100 text-amber-800">
                                        <i class="fas fa-hourglass-half mr-2 text-amber-600"></i> بانتظار الموافقة
                                    </span>
                                @elseif($notification->status === 'approved')
                                    <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-medium bg-sky-100 text-sky-800">
                                        <i class="fas fa-check-double mr-2 text-sky-600"></i> معتمدة
                                    </span>
                                @elseif($notification->status === 'rejected')
                                    <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-medium bg-rose-100 text-rose-800">
                                        <i class="fas fa-ban mr-2 text-rose-600"></i> مرفوضة
                                    </span>
                                @elseif($notification->status === 'sent')
                                    <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-medium bg-green-100 text-green-700">
                                        <i class="fas fa-check mr-2"></i> تم الإرسال
                                    </span>
                                @elseif($notification->status === 'sending')
                                    <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-medium bg-blue-100 text-blue-700">
                                        <i class="fas fa-spinner fa-spin mr-2"></i> جاري الإرسال
                                    </span>
                                @elseif($notification->status === 'partial')
                                    <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-medium bg-yellow-100 text-yellow-700">
                                        <i class="fas fa-exclamation-triangle mr-2"></i> جزئي
                                    </span>
                                @elseif($notification->status === 'failed')
                                    <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-medium bg-red-100 text-red-700">
                                        <i class="fas fa-times mr-2"></i> فشل
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-medium bg-gray-100 text-gray-700">
                                        {{ $notification->status_label }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        {{-- Pending Notice Banner --}}
                        @if($notification->status === 'pending_approval')
                            <div class="mt-3 p-3 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-800 flex items-center gap-2">
                                <i class="fas fa-info-circle text-amber-500 text-sm flex-shrink-0"></i>
                                <span>هذه الدعوة قيد المراجعة والاعتماد من قِبل إدارة العائلة، وسيتم إرسالها لجميع المستلمين فور اعتمادها.</span>
                            </div>
                        @endif

                        {{-- Rejection Reason Box --}}
                        @if($notification->status === 'rejected')
                            <div class="mt-3 p-3.5 bg-rose-50 border border-rose-200 rounded-xl text-xs text-rose-800">
                                <div class="flex items-center gap-2 font-bold mb-1 text-rose-900">
                                    <i class="fas fa-circle-xmark text-rose-600"></i>
                                    <span>تم رفض إرسال هذه الدعوة</span>
                                </div>
                                <p class="m-0 text-rose-700 leading-relaxed">
                                    <strong>سبب الرفض:</strong> {{ $notification->rejection_reason ?: 'لم يتم توضيح سبب محدد من الإدارة.' }}
                                </p>
                            </div>
                        @endif

                        {{-- Recipients List --}}
                        @if($notification->media_type === 'image' && $notification->media_url)
                            <img src="{{ $notification->media_url }}" alt="صورة الدعوة" class="mt-4 max-h-96 max-w-full rounded-xl">
                        @endif
                        @if($notification->recipients->isNotEmpty() && in_array($notification->status, \App\Models\Notification::RESPONDABLE_STATUSES, true))
                            @include('web-site.invitations.partials.response-recipients')
                        @elseif($notification->recipients->isNotEmpty())
                            <div class="border-t border-gray-100 pt-4 mt-4">
                                <h4 class="text-sm font-bold text-gray-700 mb-3">
                                    <i class="fas fa-user-friends text-green-600 mr-2"></i>
                                    المستلمون ({{ $notification->recipients->count() }})
                                </h4>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($notification->recipients->take(10) as $recipient)
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs {{ $recipient->recipient_name ? 'bg-amber-50 text-amber-800 border border-amber-200' : 'bg-gray-100 text-gray-700' }}">
                                            <i class="fas {{ $recipient->recipient_name ? 'fa-user-tag text-amber-600' : 'fa-user' }} text-xs mr-1"></i>
                                            {{ $recipient->person->first_name ?? ($recipient->recipient_name ?? 'ضيف') }}
                                        </span>
                                    @endforeach
                                    @if($notification->recipients->count() > 10)
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs bg-green-100 text-green-700 font-medium">
                                            +{{ $notification->recipients->count() - 10 }} آخرون
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                    @endforeach
                </div>

                {{-- Pagination --}}
                <div class="mt-8">
                    {{ $notifications->links() }}
                </div>
            @else
                <div class="glass-card rounded-2xl p-12 text-center">
                    <div class="w-24 h-24 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-inbox text-5xl text-gray-400"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">
                        {{ $incoming ? 'لا توجد دعوات واردة' : 'لا توجد رسائل مرسلة' }}
                    </h3>
                    @unless($incoming)
                        <p class="text-gray-600 mb-6">ابدأ بإرسال أول دعوة لك</p>
                        <a href="{{ route('invitations.send') }}" class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-green-500 to-green-600 text-white font-bold rounded-xl hover:shadow-lg transition">
                            <i class="fab fa-whatsapp"></i>
                            إرسال دعوة جديدة
                        </a>
                    @endunless
                </div>
            @endif
        </div>
    </div>
    @if($incoming && $notifications->contains(fn ($item) => $item->invitation_response === null))
        <dialog id="invitation-response-dialog" dir="rtl" aria-labelledby="invitation-response-title"
            aria-describedby="invitation-response-description"
            class="m-auto w-11/12 max-w-sm rounded-3xl border border-slate-200 bg-white p-6 text-center text-slate-900 shadow-2xl backdrop:bg-slate-900/55">
            <span data-response-icon class="mx-auto mb-3 inline-flex h-11 w-11 items-center justify-center rounded-full" aria-hidden="true">
                <i class="fas"></i>
            </span>
            <h2 id="invitation-response-title" class="text-lg font-bold"></h2>
            <p id="invitation-response-description" class="mt-2 text-sm leading-relaxed text-slate-600">
                بعد التأكيد، لا يمكنك تغيير ردك.
            </p>
            <div class="mt-5 flex flex-wrap gap-2">
                <button type="button" data-confirm-response
                    class="min-h-[44px] min-w-[8rem] flex-1 rounded-xl px-4 py-2 text-sm font-bold text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-800 disabled:opacity-60"></button>
                <button type="button" data-cancel-response autofocus
                    class="min-h-[44px] flex-1 rounded-xl border border-slate-300 px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-800">
                    إلغاء
                </button>
            </div>
        </dialog>
        <script>
            (() => {
                const dialog = document.getElementById('invitation-response-dialog');
                const title = dialog.querySelector('h2');
                const icon = dialog.querySelector('[data-response-icon]');
                const confirm = dialog.querySelector('[data-confirm-response]');
                const cancel = dialog.querySelector('[data-cancel-response]');
                let trigger;

                document.querySelectorAll('[data-invitation-response]').forEach((button) => {
                    button.addEventListener('click', () => {
                        trigger = button;
                        const accepted = button.dataset.invitationResponse === 'accepted';
                        title.textContent = accepted ? 'تأكيد قبول الدعوة' : 'تأكيد رفض الدعوة';
                        confirm.textContent = accepted ? 'تأكيد القبول' : 'تأكيد الرفض';
                        confirm.classList.toggle('bg-emerald-700', accepted);
                        confirm.classList.toggle('hover:bg-emerald-800', accepted);
                        confirm.classList.toggle('bg-rose-700', !accepted);
                        confirm.classList.toggle('hover:bg-rose-800', !accepted);
                        icon.classList.toggle('bg-emerald-50', accepted);
                        icon.classList.toggle('text-emerald-800', accepted);
                        icon.classList.toggle('bg-rose-50', !accepted);
                        icon.classList.toggle('text-rose-800', !accepted);
                        icon.firstElementChild.className = accepted ? 'fas fa-check' : 'fas fa-times';
                        dialog.showModal();
                    });
                });

                cancel.addEventListener('click', () => dialog.close());
                dialog.addEventListener('cancel', (event) => {
                    if (confirm.disabled) event.preventDefault();
                });
                confirm.addEventListener('click', () => {
                    if (!dialog.open || confirm.disabled) return;
                    const form = trigger.form;
                    form.elements.namedItem('response').value = trigger.dataset.invitationResponse;
                    confirm.disabled = true;
                    cancel.disabled = true;
                    form.requestSubmit();
                });
            })();
        </script>
    @endif
</body>
</html>
