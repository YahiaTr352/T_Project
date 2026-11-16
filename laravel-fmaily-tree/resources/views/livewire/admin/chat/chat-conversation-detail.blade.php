<div>
    {{-- تنبيهات --}}
    @if ($flashMessage)
        <div class="alert alert-{{ $flashType === 'warning' ? 'warning' : 'success' }} alert-dismissible fade show shadow-sm"
            role="alert">
            {{ $flashMessage }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    {{-- بطاقة المحادثة --}}
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h6 class="m-0 font-weight-bold text-gray-800">
                    <i class="fas fa-comments text-primary mr-1"></i>{{ $conversation->title }}
                </h6>
                <small class="text-muted">
                    {{ class_basename($conversation->conversable_type) }} #{{ $conversation->conversable_id }}
                    · {{ number_format($conversation->messages_count) }} رسالة
                    @if ($conversation->last_message_at)
                        · آخر نشاط {{ $conversation->last_message_at->diffForHumans() }}
                    @endif
                </small>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <span class="badge badge-{{ $conversation->is_locked ? 'danger' : 'success' }} p-2 align-self-center">
                    <i class="fas fa-{{ $conversation->is_locked ? 'lock' : 'unlock' }} mr-1"></i>
                    {{ $conversation->is_locked ? 'مقفلة' : 'مفتوحة' }}
                </span>
                @if ($conversation->slow_mode_seconds > 0)
                    <span class="badge badge-warning p-2 align-self-center">
                        <i class="fas fa-hourglass-half mr-1"></i> بطيء {{ $conversation->slow_mode_seconds }}ث
                    </span>
                @endif
                <span class="badge badge-{{ $conversation->allow_attachments ? 'info' : 'secondary' }} p-2 align-self-center">
                    <i class="fas fa-paperclip mr-1"></i>
                    {{ $conversation->allow_attachments ? 'المرفقات مسموحة' : 'المرفقات ممنوعة' }}
                </span>
                @can('chat.moderation.participants')
                    <button type="button" wire:click="toggleLock" class="btn btn-sm btn-outline-danger">
                        <i class="fas fa-lock mr-1"></i> {{ $conversation->is_locked ? 'فتح المحادثة' : 'قفل المحادثة' }}
                    </button>
                    <button type="button" wire:click="toggleAttachments" class="btn btn-sm btn-outline-info">
                        <i class="fas fa-paperclip mr-1"></i> {{ $conversation->allow_attachments ? 'منع المرفقات' : 'السماح بالمرفقات' }}
                    </button>
                @endcan
                @canany(['chat.moderation.messages', 'chat.moderation.participants'])
                    <button type="button" wire:click="openModal('clear_chat')" class="btn btn-sm btn-outline-warning">
                        <i class="fas fa-broom mr-1"></i> مسح الشات بأكمله
                    </button>
                    <button type="button" wire:click="openModal('delete_group')" class="btn btn-sm btn-danger">
                        <i class="fas fa-trash-alt mr-1"></i> حذف المجموعة ككل
                    </button>
                @endcanany
            </div>
        </div>

        <div class="card-body border-bottom">
            <div class="row align-items-end">
                <div class="col-md-3 mb-2">
                    <label class="small text-muted">الوضع البطيء (ثانية بين الرسائل)</label>
                    <div class="input-group input-group-sm">
                        <input type="number" min="0" max="86400" wire:model="slowMode" class="form-control"
                            @disabled(!auth()->user()->can('chat.moderation.participants'))>
                        <div class="input-group-append">
                            <button class="btn btn-outline-primary" type="button" wire:click="saveSlowMode"
                                @disabled(!auth()->user()->can('chat.moderation.participants'))>حفظ</button>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 mb-2">
                    <label class="small text-muted">إعلان الإدارة (يظهر كرسالة رسمية من الإدارة)</label>
                    <div class="input-group input-group-sm">
                        <input type="text" wire:model="announcement" class="form-control"
                            placeholder="مثال: ينتهي التحدي غداً — ننتظر مشاركتكم">
                        <div class="input-group-append">
                            <button class="btn btn-primary" type="button" wire:click="postAnnouncement"
                                @disabled(!auth()->user()->can('chat.moderation.messages'))>نشر</button>
                        </div>
                    </div>
                </div>

                <div class="col-md-3 mb-2 text-left">
                    @if ($conversation->last_pinned_message_id)
                        <button class="btn btn-sm btn-outline-secondary" type="button" wire:click="unpinMessage">
                            <i class="fas fa-thumbtack mr-1"></i> إلغاء التثبيت
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        {{-- سجل الرسائل --}}
        <div class="col-xl-8 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3 d-flex align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-comments mr-1"></i> سجل الرسائل</h6>
                    <small class="text-muted">آخر ١٢٠ رسالة</small>
                </div>
                <div class="card-body p-0" style="max-height: 640px; overflow-y: auto;" wire:poll.10s>
                    <div class="list-group list-group-flush">
                        @forelse ($messages as $msg)
                            <div wire:key="msg-{{ $msg->id }}"
                                class="list-group-item list-group-item-action {{ $msg->is_hidden ? 'bg-light' : '' }}">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1" style="min-width: 0;">
                                        <div class="d-flex align-items-center mb-1 flex-wrap">
                                            @if ($msg->isSystemMessage())
                                                <span class="badge badge-dark mr-2">
                                                    <i class="fas fa-shield-alt mr-1"></i>
                                                    {{ $msg->senderUser?->name ?? 'إدارة التطبيق' }}
                                                </span>
                                            @else
                                                <span class="font-weight-bold text-dark mr-2">
                                                    {{ $msg->sender?->full_name ?? 'مستخدم محذوف' }}
                                                </span>
                                            @endif
                                            <span class="badge badge-light text-muted mr-2">{{ $msg->type }}</span>
                                            @if ($msg->is_flagged)
                                                <span class="badge badge-warning mr-2" title="{{ $msg->flag_reason }}">
                                                    <i class="fas fa-exclamation-triangle mr-1"></i> مُعلَّمة
                                                </span>
                                            @endif
                                            @if ($msg->reported_count > 0)
                                                <span class="badge badge-danger mr-2">
                                                    <i class="fas fa-flag mr-1"></i> {{ $msg->reported_count }} بلاغ
                                                </span>
                                            @endif
                                            @if ($conversation->last_pinned_message_id === $msg->id)
                                                <span class="badge badge-info mr-2">
                                                    <i class="fas fa-thumbtack mr-1"></i> مثبتة
                                                </span>
                                            @endif
                                        </div>

                                        @if ($msg->is_hidden)
                                            <div class="text-muted small fst-italic">
                                                <i class="fas fa-eye-slash mr-1"></i>
                                                هذه الرسالة مخفية من المستخدمين.
                                            </div>
                                        @elseif ($msg->message)
                                            <div class="text-dark" style="white-space: pre-wrap; word-break: break-word;">{{ $msg->message }}</div>
                                        @endif

                                        @if ($msg->attachment_url)
                                            <a href="{{ $msg->attachment_url }}" target="_blank" class="small">
                                                <i class="fas fa-paperclip mr-1"></i> عرض المرفق
                                            </a>
                                        @endif

                                        <small class="text-muted d-block mt-1">
                                            {{ $msg->created_at?->format('Y-m-d H:i') }}
                                            @if ($msg->is_edited) · مُعدّلة @endif
                                        </small>
                                    </div>

                                    <div class="btn-group btn-group-sm ml-2" style="white-space: nowrap;">
                                        @can('chat.moderation.messages')
                                            @if ($msg->is_hidden)
                                                <button type="button" class="btn btn-outline-success" title="استرجاع"
                                                    wire:click="restoreMessage({{ $msg->id }})">
                                                    <i class="fas fa-undo"></i>
                                                </button>
                                            @else
                                                <button type="button" class="btn btn-outline-warning" title="إخفاء"
                                                    wire:click="hideMessage({{ $msg->id }})">
                                                    <i class="fas fa-eye-slash"></i>
                                                </button>
                                            @endif
                                            <button type="button" class="btn btn-outline-secondary" title="تثبيت"
                                                wire:click="pinMessage({{ $msg->id }})">
                                                <i class="fas fa-thumbtack"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-danger" title="حذف"
                                                wire:click="deleteMessage({{ $msg->id }})"
                                                onclick="return confirm('هل تريد حذف هذه الرسالة نهائياً؟')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        @endcan
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="text-muted text-center py-5">لا توجد رسائل في هذه المحادثة.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        {{-- المشاركون والحظر --}}
        <div class="col-xl-4 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-users mr-1"></i> المشاركون</h6>
                </div>
                <div class="card-body border-bottom py-2">
                    <input type="text" wire:model.live.debounce.400ms="participantSearch" class="form-control form-control-sm"
                        placeholder="بحث بالاسم...">
                </div>
                <div class="card-body p-0" style="max-height: 340px; overflow-y: auto;">
                    <div class="list-group list-group-flush">
                        @forelse ($participants as $participant)
                            <div wire:key="part-{{ $participant->id }}"
                                class="list-group-item {{ $participant->can_send ? '' : 'bg-danger bg-light' }}">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="flex-grow-1" style="min-width: 0;">
                                        <div class="font-weight-bold text-dark small">
                                            {{ $participant->person?->full_name ?? 'مستخدم محذوف' }}
                                        </div>
                                        <small class="text-muted">
                                            {{ $participant->role }}
                                            @if ($participant->warnings_count > 0)
                                                · <span class="text-danger">{{ $participant->warnings_count }} تحذير</span>
                                            @endif
                                            @if (! $participant->can_send)
                                                · <span class="text-danger font-weight-bold">ممنوع من الإرسال</span>
                                            @endif
                                            @if ($participant->is_muted)
                                                · <span class="text-muted">مكتوم</span>
                                            @endif
                                        </small>
                                    </div>
                                    <div class="btn-group btn-group-sm ml-2">
                                        @can('chat.moderation.participants')
                                            @if ($participant->can_send)
                                                <button type="button" class="btn btn-outline-danger" title="منع الإرسال"
                                                    wire:click="openRestrict({{ $participant->person_id }})">
                                                    <i class="fas fa-user-slash"></i>
                                                </button>
                                            @else
                                                <button type="button" class="btn btn-outline-success" title="السماح بالإرسال"
                                                    wire:click="unrestrict({{ $participant->person_id }})">
                                                    <i class="fas fa-user-check"></i>
                                                </button>
                                            @endif
                                            <button type="button" class="btn btn-outline-warning" title="تحذير"
                                                wire:click="warn({{ $participant->person_id }})">
                                                <i class="fas fa-exclamation-triangle"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary" title="كتم/إلغاء كتم"
                                                wire:click="toggleMute({{ $participant->person_id }})">
                                                <i class="fas fa-bell-slash"></i>
                                            </button>
                                        @endcan
                                        @can('chat.moderation.bans')
                                            <button type="button" class="btn btn-outline-dark" title="حظر"
                                                wire:click="openBan({{ $participant->person_id }})">
                                                <i class="fas fa-ban"></i>
                                            </button>
                                        @endcan
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="text-muted text-center py-4 small">لا يوجد مشاركون مسجّلون.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-ban mr-1"></i> المحظورون من هذه المحادثة</h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        @forelse ($bans as $ban)
                            <div wire:key="ban-{{ $ban->id }}" class="list-group-item d-flex justify-content-between align-items-center">
                                <div style="min-width: 0;">
                                    <div class="small font-weight-bold text-dark">{{ $ban->person?->full_name ?? 'مستخدم محذوف' }}</div>
                                    <small class="text-muted">{{ $ban->reason }}</small>
                                    @if ($ban->expires_at)
                                        <small class="text-muted d-block">ينتهي: {{ $ban->expires_at->format('Y-m-d') }}</small>
                                    @endif
                                </div>
                                <div>
                                    @if ($ban->isCurrentlyActive())
                                        <span class="badge badge-danger mb-1">نشط</span>
                                    @else
                                        <span class="badge badge-secondary mb-1">مرفوع</span>
                                    @endif
                                    @can('chat.moderation.bans')
                                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                            wire:click="revokeBan({{ $ban->id }})">رفع</button>
                                    @endcan
                                </div>
                            </div>
                        @empty
                            <div class="text-muted text-center py-4 small">لا يوجد محظورون.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- نموذج منع الإرسال --}}
    @if ($modal === 'restrict')
        <div class="card border-left-danger shadow mb-3">
            <div class="card-body">
                <h6 class="m-0 font-weight-bold text-danger mb-2">
                    <i class="fas fa-user-slash mr-1"></i> منع {{ $this->personName($modalPersonId) }} من الإرسال
                </h6>
                <div class="form-row align-items-end">
                    <div class="form-group col-md-8 mb-2">
                        <label class="small text-muted">السبب (يظهر في سجل التدقيق)</label>
                        <input type="text" wire:model="modalReason" class="form-control form-control-sm"
                            placeholder="مثال: إزعاج متكرر للمشاركين">
                    </div>
                    <div class="form-group col-md-4 mb-2 d-flex gap-2">
                        <button type="button" class="btn btn-danger btn-sm flex-fill" wire:click="confirmRestrict">تأكيد</button>
                        <button type="button" class="btn btn-secondary btn-sm" wire:click="closeModal">إلغاء</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- نموذج الحظر --}}
    @if ($modal === 'ban')
        <div class="card border-left-dark shadow mb-3">
            <div class="card-body">
                <h6 class="m-0 font-weight-bold text-dark mb-2">
                    <i class="fas fa-ban mr-1"></i> حظر {{ $this->personName($modalPersonId) }}
                </h6>
                <div class="form-row align-items-end">
                    <div class="form-group col-md-3 mb-2">
                        <label class="small text-muted">نطاق الحظر</label>
                        <select wire:model="banScope" class="form-control form-control-sm">
                            <option value="conversation">هذه المحادثة فقط</option>
                            <option value="global">كل المحادثات (عام)</option>
                        </select>
                    </div>
                    <div class="form-group col-md-2 mb-2">
                        <label class="small text-muted">المدة (أيام، 0 = دائم)</label>
                        <input type="number" min="0" max="3650" wire:model="banDurationDays" class="form-control form-control-sm">
                    </div>
                    <div class="form-group col-md-5 mb-2">
                        <label class="small text-muted">سبب الحظر</label>
                        <input type="text" wire:model="banReason" class="form-control form-control-sm"
                            placeholder="مثال: إساءة متكررة بعد تحذيرات">
                    </div>
                    <div class="form-group col-md-2 mb-2 d-flex gap-2">
                        <button type="button" class="btn btn-dark btn-sm flex-fill" wire:click="confirmBan">تأكيد</button>
                        <button type="button" class="btn btn-secondary btn-sm" wire:click="closeModal">إلغاء</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- نافذة منبثقة عائمة: مسح الشات بأكمله --}}
    @if ($modal === 'clear_chat')
        <div class="modal fade show" tabindex="-1" role="dialog"
            style="display: block; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background-color: rgba(0, 0, 0, 0.65); z-index: 9999; overflow-y: auto;">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content shadow-lg border-warning">
                    <div class="modal-header bg-warning text-dark py-2">
                        <h5 class="modal-title font-weight-bold">
                            <i class="fas fa-broom mr-1"></i> مسح جميع رسائل المحادثة للجميع
                        </h5>
                        <button type="button" class="close" wire:click="closeModal" aria-label="إغلاق">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning mb-3">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            <strong>تنبيه:</strong> سيتم حذف كافة الرسائل والمرفقات في هذه المحادثة لدى جميع المشاركين نهائياً وتصفير العدادات، مع توثيق الإجراء في سجل الإشراف.
                        </div>
                        <div class="form-group mb-0">
                            <label class="small text-muted font-weight-bold">سبب مسح المحادثة (اختياري):</label>
                            <input type="text" wire:model="modalReason" class="form-control"
                                placeholder="مثال: تنظيف المحادثة بعد انتهاء الفعالية">
                        </div>
                    </div>
                    <div class="modal-footer py-2">
                        <button type="button" class="btn btn-secondary" wire:click="closeModal">إلغاء</button>
                        <button type="button" class="btn btn-warning font-weight-bold" wire:click="clearChat">
                            <i class="fas fa-check mr-1"></i> تأكيد مسح الشات الآن
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- نافذة منبثقة عائمة: حذف المجموعة ككل --}}
    @if ($modal === 'delete_group')
        <div class="modal fade show" tabindex="-1" role="dialog"
            style="display: block; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background-color: rgba(0, 0, 0, 0.65); z-index: 9999; overflow-y: auto;">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content shadow-lg border-danger">
                    <div class="modal-header bg-danger text-white py-2">
                        <h5 class="modal-title font-weight-bold">
                            <i class="fas fa-trash-alt mr-1"></i> حذف المجموعة ككل والمحادثة
                        </h5>
                        <button type="button" class="close text-white" wire:click="closeModal" aria-label="إغلاق">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-danger mb-3">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            <strong>تحذير:</strong> سيتم حذف الكيان التابع ({{ $conversation->conversable?->name ?? $conversation->title }}) ومحادثته بالكامل ونقلها لسلة المحذوفات.
                        </div>
                        <div class="form-group mb-0">
                            <label class="small text-muted font-weight-bold">سبب حذف المجموعة:</label>
                            <input type="text" wire:model="modalReason" class="form-control"
                                placeholder="مثال: مجموعة مخالفة لسياسات المنصة">
                        </div>
                    </div>
                    <div class="modal-footer py-2">
                        <button type="button" class="btn btn-secondary" wire:click="closeModal">إلغاء</button>
                        <button type="button" class="btn btn-danger font-weight-bold" wire:click="deleteGroup">
                            <i class="fas fa-trash-alt mr-1"></i> تأكيد الحذف النهائي
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
