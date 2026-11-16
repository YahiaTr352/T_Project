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

    {{-- الفلاتر --}}
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-filter mr-1"></i> تصفية المحادثات</h6>
        </div>
        <div class="card-body">
            <div class="form-row align-items-end">
                <div class="form-group col-md-3 mb-2">
                    <label class="small text-muted">بحث بالعنوان</label>
                    <input type="text" wire:model.live.debounce.400ms="search" class="form-control form-control-sm"
                        placeholder="اسم المحادثة...">
                </div>
                <div class="form-group col-md-3 mb-2">
                    <label class="small text-muted">نوع المالك</label>
                    <select wire:model.live="ownerType" class="form-control form-control-sm">
                        <option value="">كل الأنواع</option>
                        @foreach ($ownerTypes as $type => $label)
                            <option value="{{ $type }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-3 mb-2">
                    <label class="small text-muted">الحالة</label>
                    <select wire:model.live="status" class="form-control form-control-sm">
                        <option value="">كل الحالات</option>
                        <option value="unlocked">مفتوحة</option>
                        <option value="locked">مقفلة</option>
                        <option value="inactive">غير نشطة</option>
                        <option value="slow">وضع بطيء</option>
                    </select>
                </div>
                <div class="form-group col-md-3 mb-2 d-flex gap-2">
                    <button type="button" wire:click="resetFilters" class="btn btn-secondary btn-sm flex-fill">
                        <i class="fas fa-redo mr-1"></i> إعادة ضبط
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- الجدول --}}
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-comments mr-1"></i> المحادثات
                <span class="badge badge-light text-muted mr-1">{{ $conversations->total() }}</span>
            </h6>
            <small class="text-muted"><i class="fas fa-sync-alt mr-1"></i> تحديث تلقائي كل ١٥ ثانية</small>
        </div>
        <div class="card-body p-0" wire:poll.15s>
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0 text-center">
                    <thead class="thead-light">
                        <tr>
                            <th>#</th>
                            <th>المحادثة</th>
                            <th>المالك</th>
                            <th>الرسائل</th>
                            <th>الحالة</th>
                            <th>آخر نشاط</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($conversations as $conv)
                            <tr wire:key="conv-{{ $conv->id }}">
                                <td>{{ $conv->id }}</td>
                                <td class="text-right">
                                    <div class="font-weight-bold text-dark">
                                        {{ \Illuminate\Support\Str::limit($conv->title, 60) }}
                                    </div>
                                    @if ($conv->last_pinned_message_id)
                                        <span class="badge badge-info badge-pill">
                                            <i class="fas fa-thumbtack mr-1"></i> رسالة مثبتة
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge badge-light text-dark">
                                        {{ class_basename($conv->conversable_type) }}
                                    </span>
                                </td>
                                <td><span class="badge badge-primary">{{ $conv->messages_count }}</span></td>
                                <td>
                                    @if ($conv->is_locked)
                                        <span class="badge badge-danger"><i class="fas fa-lock mr-1"></i> مقفلة</span>
                                    @elseif (! $conv->is_active)
                                        <span class="badge badge-secondary">غير نشطة</span>
                                    @else
                                        <span class="badge badge-success">نشطة</span>
                                    @endif
                                    @if ($conv->slow_mode_seconds > 0)
                                        <span class="badge badge-warning">بطيء {{ $conv->slow_mode_seconds }}ث</span>
                                    @endif
                                </td>
                                <td>
                                    <small class="text-muted">
                                        {{ $conv->last_message_at ? $conv->last_message_at->format('Y-m-d H:i') : '—' }}
                                    </small>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('dashboard.chat.conversation.show', $conv) }}"
                                            class="btn btn-primary shadow-sm" title="فتح المحادثة">
                                            <i class="fas fa-eye"></i> فتح
                                        </a>
                                        @canany(['chat.moderation.messages', 'chat.moderation.participants'])
                                            <button type="button" wire:click="openModal('clear_chat', {{ $conv->id }})"
                                                class="btn btn-outline-warning shadow-sm" title="مسح رسائل المحادثة">
                                                <i class="fas fa-broom"></i>
                                            </button>
                                            <button type="button" wire:click="openModal('delete_group', {{ $conv->id }})"
                                                class="btn btn-outline-danger shadow-sm" title="حذف المجموعة ككل">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        @endcanany
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-4 text-muted">لا توجد محادثات مطابقة.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-3">
                {{ $conversations->links() }}
            </div>
        </div>
    </div>

    {{-- نافذة منبثقة عائمة: مسح الشات بأكمله --}}
    @if ($modal === 'clear_chat')
        <div class="modal fade show" tabindex="-1" role="dialog"
            style="display: block; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background-color: rgba(0, 0, 0, 0.65); z-index: 9999; overflow-y: auto;">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content shadow-lg border-warning">
                    <div class="modal-header bg-warning text-dark py-2">
                        <h5 class="modal-title font-weight-bold">
                            <i class="fas fa-broom mr-1"></i> مسح جميع رسائل المحادثة #{{ $modalConversationId }}
                        </h5>
                        <button type="button" class="close" wire:click="closeModal" aria-label="إغلاق">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning mb-3">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            <strong>تنبيه:</strong> سيتم حذف كافة الرسائل والمرفقات في هذه المحادثة لدى جميع المشاركين نهائياً وتصفير العدادات، مع توثيق الإجراء في سجل التدقيق.
                        </div>
                        <div class="form-group mb-0">
                            <label class="small text-muted font-weight-bold">السبب (اختياري):</label>
                            <input type="text" wire:model="modalReason" class="form-control"
                                placeholder="مثال: تفريغ الشات بعد انتهاء النشاط">
                        </div>
                    </div>
                    <div class="modal-footer py-2">
                        <button type="button" class="btn btn-secondary" wire:click="closeModal">إلغاء</button>
                        <button type="button" class="btn btn-warning font-weight-bold" wire:click="confirmClearChat">
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
                            <i class="fas fa-trash-alt mr-1"></i> حذف المجموعة والمحادثة #{{ $modalConversationId }}
                        </h5>
                        <button type="button" class="close text-white" wire:click="closeModal" aria-label="إغلاق">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-danger mb-3">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            <strong>تحذير:</strong> سيتم حذف الكيان التابع (المجموعة/التحدي) ومحادثته بالكامل ونقلها لسلة المحذوفات.
                        </div>
                        <div class="form-group mb-0">
                            <label class="small text-muted font-weight-bold">سبب الحذف:</label>
                            <input type="text" wire:model="modalReason" class="form-control"
                                placeholder="مثال: مجموعة مخالفة للأنظمة">
                        </div>
                    </div>
                    <div class="modal-footer py-2">
                        <button type="button" class="btn btn-secondary" wire:click="closeModal">إلغاء</button>
                        <button type="button" class="btn btn-danger font-weight-bold" wire:click="confirmDeleteGroup">
                            <i class="fas fa-trash-alt mr-1"></i> تأكيد الحذف النهائي
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
