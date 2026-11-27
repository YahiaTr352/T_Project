@extends('web-site.invitations.layout')

@section('title', 'سجل الدعوات')

@section('extra_styles')
    @include('web-site.invitations.partials.typography-fonts')

    [data-invitation-font="tajawal"] .invitation-log-text { font-family: 'Invitation Tajawal', Tajawal, sans-serif; }
    [data-invitation-font="amiri"] .invitation-log-text { font-family: 'Invitation Amiri', Amiri, serif; }
    [data-invitation-weight="regular"] .invitation-log-text { font-weight: 400; }
    [data-invitation-weight="bold"] .invitation-log-text { font-weight: 700; }

    .log-item {
        transition: transform 0.2s ease, border-color 0.2s ease;
    }
    .log-item:hover {
        border-color: var(--inv-border-strong);
        transform: translateY(-1px);
    }
@endsection

@section('content')
    <div class="space-y-5">
        {{-- البانر التعريفي --}}
        <section class="inv-hero">
            <img src="{{ asset('images/invitations/event-invitation.jpg') }}" alt="سجل الدعوات" class="inv-hero-img">
            <div class="inv-hero-overlay">
                <h2 class="text-xl sm:text-2xl font-extrabold text-white">سجل الدعوات</h2>
                <p class="text-xs sm:text-sm text-emerald-50/90 mt-1 font-medium">
                    {{ $inbox ? 'إشعارات الدعوات والتصاميم الخاصة بك' : ($incoming ? 'الدعوات الواردة إليك من أفراد العائلة' : 'متابعة الدعوات الصادرة وحالة التسليم والردود') }}
                </p>
            </div>
        </section>

        {{-- شريط التبديل بين الصادر والإشعارات والوارد --}}
        <nav aria-label="سجل الدعوات" class="inv-card rounded-2xl p-1.5 grid grid-cols-3 gap-1.5">
            <a href="{{ route('invitations.logs') }}" @if(!$incoming && !$inbox) aria-current="page" @endif
                class="flex items-center justify-center gap-2 rounded-xl py-2.5 px-3 text-xs sm:text-sm font-bold transition {{ !$incoming && !$inbox ? 'inv-inset shadow-sm inv-text-primary' : 'inv-muted hover:opacity-80' }}"
                style="{{ !$incoming && !$inbox ? 'background: var(--inv-surface-soft); border: 1px solid var(--inv-border-strong);' : '' }}">
                <i class="fas fa-paper-plane text-xs" aria-hidden="true"></i>
                <span>الصادر</span>
            </a>
            <a href="{{ route('invitations.logs', ['direction' => 'notifications']) }}" @if($inbox) aria-current="page" @endif
                class="flex items-center justify-center gap-2 rounded-xl py-2.5 px-3 text-xs sm:text-sm font-bold transition {{ $inbox ? 'inv-inset shadow-sm inv-text-primary' : 'inv-muted hover:opacity-80' }}"
                style="{{ $inbox ? 'background: var(--inv-surface-soft); border: 1px solid var(--inv-border-strong);' : '' }}">
                <i class="fas fa-bell text-xs" aria-hidden="true"></i>
                <span>الإشعارات</span>
            </a>
            <a href="{{ route('invitations.logs', ['direction' => 'incoming']) }}" @if($incoming) aria-current="page" @endif
                class="flex items-center justify-center gap-2 rounded-xl py-2.5 px-3 text-xs sm:text-sm font-bold transition {{ $incoming ? 'inv-inset shadow-sm inv-text-primary' : 'inv-muted hover:opacity-80' }}"
                style="{{ $incoming ? 'background: var(--inv-surface-soft); border: 1px solid var(--inv-border-strong);' : '' }}">
                <i class="fas fa-clock text-xs" aria-hidden="true"></i>
                <span>الوارد</span>
            </a>
        </nav>

        {{-- تنبيهات الرد --}}
        @if(session('response_error'))
            <p role="alert" class="rounded-2xl inv-badge-danger p-4 text-sm font-bold">
                {{ session('response_error') }}
            </p>
        @endif

        {{-- قائمة السجل --}}
        @if($notifications->count() > 0)
            <div class="space-y-3.5">
                @foreach($notifications as $notification)
                    @php
                        $fontWeight = $notification->targeting_filters['font_weight'] ?? '';
                        $fontFamily = $notification->targeting_filters['font_family'] ?? ($fontWeight ? 'tajawal' : '');
                        $canReply = $notification->person_id !== null && $notification->acceptsInvitationResponses();
                        $thumbUrl = ($notification->media_type === 'image' && $notification->media_path)
                            ? asset('storage/'.$notification->media_path)
                            : asset('images/invitations/event-invitation.jpg');
                    @endphp

                    @if($incoming || $inbox)
                        @php
                            $resp = $notification->invitation_response;
                            $respBadgeClass = match($resp) {
                                'accepted' => 'inv-badge-success',
                                'declined' => 'inv-badge-danger',
                                default => $canReply ? 'inv-badge-warning' : 'inv-badge-neutral',
                            };
                            $respBadgeText = match($resp) {
                                'accepted' => 'سأحضر',
                                'declined' => 'معتذر',
                                default => $canReply ? 'بانتظار الرد' : 'إشعار',
                            };
                        @endphp
                        <details class="inv-card rounded-3xl p-4 sm:p-5 log-item" data-invitation-font="{{ $fontFamily }}" data-invitation-weight="{{ $fontWeight }}"
                            @if($inbox || session('responded_invitation_id') === $notification->id) open @endif>
                            <summary class="cursor-pointer list-none">
                                <div class="flex items-center gap-3.5">
                                    <div class="w-14 h-14 rounded-2xl inv-inset overflow-hidden shrink-0">
                                        <img src="{{ $thumbUrl }}" alt="" class="w-full h-full object-cover">
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="invitation-log-text text-base font-extrabold truncate" style="color: var(--inv-text)">
                                                {{ $notification->display_title }}
                                            </span>
                                            <span class="shrink-0 px-3 py-1 rounded-full text-xs font-bold {{ $respBadgeClass }}">
                                                {{ $respBadgeText }}
                                            </span>
                                        </div>
                                        <span class="mt-1 block text-xs inv-muted font-medium">
                                            @if ($notification->person)
                                                من: {{ $notification->person->full_name }} ·
                                            @endif
                                            {{ ($notification->sent_at ?? $notification->created_at)?->format('Y-m-d H:i') }}
                                        </span>
                                    </div>
                                </div>
                                <div class="mt-3 pt-2.5 border-t flex items-center justify-between text-xs" style="border-color: var(--inv-border);">
                                    <span class="inv-muted">اضغط لعرض بطاقة الدعوة والرد</span>
                                    <span class="font-bold inv-text-primary inline-flex items-center gap-1">
                                        عرض <i class="fas fa-chevron-left text-[10px]" aria-hidden="true"></i>
                                    </span>
                                </div>
                            </summary>

                            @if ($notification->display_body !== '')
                                <p class="invitation-log-text mt-4 whitespace-pre-wrap break-words border-t pt-4 text-sm leading-relaxed"
                                   style="border-color: var(--inv-border); color: var(--inv-text);">
                                    {{ $notification->display_body }}
                                </p>
                            @endif
                            @include('web-site.invitations.partials.log-media')
                            @if ($canReply)
                                @include('web-site.invitations.partials.response-form')
                            @endif
                        </details>
                        @continue
                    @endif

                    @php
                        $statusBadge = match($notification->status) {
                            'pending_approval' => ['inv-badge-warning', 'fa-hourglass-half', 'بانتظار الموافقة'],
                            'approved'         => ['inv-badge-info',    'fa-check-double',   'معتمدة'],
                            'rejected'         => ['inv-badge-danger',  'fa-ban',            'مرفوضة'],
                            'sent'             => ['inv-badge-success', 'fa-check',          'تم الإرسال'],
                            'sending'          => ['inv-badge-info',    'fa-spinner fa-spin','جاري الإرسال'],
                            'partial'          => ['inv-badge-warning', 'fa-exclamation-triangle', 'جزئي'],
                            'failed'           => ['inv-badge-danger',  'fa-times',          'فشل'],
                            default            => ['inv-badge-neutral', 'fa-circle',         $notification->status_label],
                        };
                    @endphp
                    <div class="inv-card rounded-3xl p-4 sm:p-5 log-item" data-invitation-font="{{ $fontFamily }}" data-invitation-weight="{{ $fontWeight }}">
                        <div class="flex items-start justify-between gap-3.5">
                            <div class="flex items-start gap-3.5 flex-1 min-w-0">
                                <div class="w-14 h-14 rounded-2xl inv-inset overflow-hidden shrink-0">
                                    <img src="{{ $thumbUrl }}" alt="" class="w-full h-full object-cover">
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-wrap items-center justify-between gap-2 mb-1">
                                        <h3 class="invitation-log-text text-base font-extrabold truncate" style="color: var(--inv-text)">
                                            {{ $notification->display_title }}
                                        </h3>
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold {{ $statusBadge[0] }}">
                                            <i class="fas {{ $statusBadge[1] }}" aria-hidden="true"></i>
                                            {{ $statusBadge[2] }}
                                        </span>
                                    </div>
                                    <p class="invitation-log-text text-xs sm:text-sm inv-text-sec mb-2 line-clamp-2">
                                        {{ Str::limit($notification->display_body, 120) }}
                                    </p>
                                    <div class="flex flex-wrap items-center gap-3 text-xs inv-muted font-medium">
                                        <span>
                                            <i class="fas fa-users ml-1 inv-text-primary" aria-hidden="true"></i>
                                            {{ $notification->recipients->count() }} مستلم
                                        </span>
                                        <span>
                                            <i class="fas fa-clock ml-1 inv-text-primary" aria-hidden="true"></i>
                                            {{ $notification->created_at->format('Y-m-d H:i') }}
                                        </span>
                                        @if($notification->media_type)
                                            <span>
                                                @if($notification->media_type === 'image')
                                                    <i class="fas fa-image ml-1 text-emerald-500" aria-hidden="true"></i> صورة
                                                @elseif($notification->media_type === 'video')
                                                    <i class="fas fa-video ml-1 text-rose-500" aria-hidden="true"></i> فيديو
                                                @elseif($notification->media_type === 'voice')
                                                    <i class="fas fa-microphone ml-1 text-amber-500" aria-hidden="true"></i> صوت
                                                @endif
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        @if($notification->status === 'pending_approval')
                            <div class="mt-3 p-3 inv-badge-warning rounded-2xl text-xs flex items-center gap-2 font-medium">
                                <i class="fas fa-info-circle text-sm shrink-0" aria-hidden="true"></i>
                                <span>هذه الدعوة قيد المراجعة والاعتماد من قِبل إدارة العائلة، وسيتم إرسالها لجميع المستلمين فور اعتمادها.</span>
                            </div>
                        @endif

                        @if($notification->status === 'rejected')
                            <div class="mt-3 p-3.5 inv-badge-danger rounded-2xl text-xs">
                                <div class="flex items-center gap-2 font-bold mb-1">
                                    <i class="fas fa-circle-xmark" aria-hidden="true"></i>
                                    <span>تم رفض إرسال هذه الدعوة</span>
                                </div>
                                <p class="m-0 leading-relaxed">
                                    <strong>سبب الرفض:</strong> {{ $notification->rejection_reason ?: 'لم يتم توضيح سبب محدد من الإدارة.' }}
                                </p>
                            </div>
                        @endif

                        @include('web-site.invitations.partials.log-media')
                        @if($notification->recipients->isNotEmpty() && $notification->acceptsInvitationResponses())
                            @include('web-site.invitations.partials.response-recipients')
                        @elseif($notification->recipients->isNotEmpty())
                            <div class="border-t pt-4 mt-4" style="border-color: var(--inv-border);">
                                <h4 class="text-xs font-extrabold inv-text-sec mb-2.5">
                                    <i class="fas fa-user-friends inv-text-primary ml-1.5" aria-hidden="true"></i>
                                    المستلمون ({{ $notification->recipients->count() }})
                                </h4>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($notification->recipients->take(10) as $recipient)
                                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-medium {{ $recipient->recipient_name ? 'inv-badge-warning' : 'inv-inset inv-text-sec' }}">
                                            <i class="fas {{ $recipient->recipient_name ? 'fa-user-tag' : 'fa-user' }} text-[10px]" aria-hidden="true"></i>
                                            {{ $recipient->person->first_name ?? ($recipient->recipient_name ?? 'ضيف') }}
                                        </span>
                                    @endforeach
                                    @if($notification->recipients->count() > 10)
                                        <span class="inv-pill">+{{ $notification->recipients->count() - 10 }} آخرون</span>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="mt-6">
                {{ $notifications->links() }}
            </div>
        @else
            <div class="inv-card rounded-3xl p-10 text-center">
                <div class="w-16 h-16 rounded-full mx-auto mb-4 flex items-center justify-center" style="background: var(--inv-surface-soft);">
                    <i class="fas fa-inbox text-2xl inv-text-primary" aria-hidden="true"></i>
                </div>
                <h3 class="text-lg font-extrabold mb-2" style="color: var(--inv-text)">
                    {{ $inbox ? 'لا توجد إشعارات للدعوات' : ($incoming ? 'لا توجد دعوات واردة' : 'لا توجد رسائل مرسلة') }}
                </h3>
                @unless($incoming || $inbox)
                    <p class="text-sm inv-muted mb-5">ابدأ بإرسال أول دعوة لك</p>
                    <a href="{{ route('invitations.send') }}" class="inv-btn-primary inline-flex items-center gap-2 px-6 py-3 text-sm">
                        <i class="fab fa-whatsapp" aria-hidden="true"></i>
                        إرسال دعوة جديدة
                    </a>
                @endunless
            </div>
        @endif
    </div>

    @if(($incoming || $inbox) && $notifications->contains(fn ($item) => $item->person_id !== null && $item->acceptsInvitationResponses() && $item->invitation_response === null))
        <dialog id="invitation-response-dialog" dir="rtl" aria-labelledby="invitation-response-title"
            aria-describedby="invitation-response-description"
            class="inv-dialog m-auto w-11/12 max-w-sm rounded-3xl p-6 text-center shadow-2xl">
            <span data-response-icon class="mx-auto mb-3 inline-flex h-11 w-11 items-center justify-center rounded-full" aria-hidden="true">
                <i class="fas"></i>
            </span>
            <h2 id="invitation-response-title" class="text-lg font-bold" style="color: var(--inv-text)"></h2>
            <p id="invitation-response-description" class="mt-2 text-sm leading-relaxed inv-muted">
                بعد التأكيد، لا يمكنك تغيير ردك.
            </p>
            <div class="mt-5 flex flex-wrap gap-2">
                <button type="button" data-confirm-response
                    class="min-h-[44px] min-w-[8rem] flex-1 rounded-xl px-4 py-2 text-sm font-bold text-white cursor-pointer disabled:opacity-60"></button>
                <button type="button" data-cancel-response autofocus
                    class="min-h-[44px] flex-1 rounded-xl inv-inset px-4 py-2 text-sm font-bold cursor-pointer hover:opacity-85">
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
                        icon.classList.toggle('inv-badge-success', accepted);
                        icon.classList.toggle('inv-badge-danger', !accepted);
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
@endsection
