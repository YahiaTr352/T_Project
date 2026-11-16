<div>
    {{-- الفلاتر --}}
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-filter mr-1"></i> تصفية البلاغات</h6>
            @if ($pending > 0)
                <span class="badge badge-warning rounded-pill px-2 py-1">{{ $pending }} بلاغ مفتوح</span>
            @endif
        </div>
        <div class="card-body">
            <div class="form-row align-items-end">
                <div class="form-group col-md-3 mb-2">
                    <label class="small text-muted">الحالة</label>
                    <select wire:model.live="status" class="form-control form-control-sm">
                        <option value="pending">مفتوحة</option>
                        <option value="reviewing">قيد المراجعة</option>
                        <option value="resolved">تمت المعالجة</option>
                        <option value="dismissed">مرفوضة</option>
                        <option value="all">الكل</option>
                    </select>
                </div>
                <div class="form-group col-md-3 mb-2">
                    <label class="small text-muted">السبب</label>
                    <select wire:model.live="reason" class="form-control form-control-sm">
                        <option value="">كل الأسباب</option>
                        @foreach ($reasons as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-4 mb-2">
                    <label class="small text-muted">بحث</label>
                    <input type="text" wire:model.live.debounce.400ms="search" class="form-control form-control-sm"
                        placeholder="اسم المبلّغ أو نص الرسالة...">
                </div>
                <div class="form-group col-md-2 mb-2">
                    <label class="small text-muted">لكل صفحة</label>
                    <select wire:model.live="perPage" class="form-control form-control-sm">
                        <option value="10">10</option>
                        <option value="20">20</option>
                        <option value="50">50</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-flag mr-1"></i> طابور البلاغات
                <span class="badge badge-light text-muted mr-1">{{ $reports->total() }}</span>
            </h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0 text-center">
                    <thead class="thead-light">
                        <tr>
                            <th>#</th>
                            <th>السبب</th>
                            <th>المبلّغ</th>
                            <th>الرسالة المُبلَّغ عنها</th>
                            <th>الحالة</th>
                            <th>التاريخ</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($reports as $report)
                            <tr wire:key="report-{{ $report->id }}">
                                <td>{{ $report->id }}</td>
                                <td>
                                    <span class="badge badge-pill badge-primary">{{ $report->reasonLabel() }}</span>
                                </td>
                                <td class="text-right small">
                                    {{ $report->reporter?->full_name ?? 'مستخدم محذوف' }}
                                    @if ($report->details)
                                        <div class="text-muted">{{ \Illuminate\Support\Str::limit($report->details, 60) }}</div>
                                    @endif
                                </td>
                                <td class="text-right small">
                                    @if ($report->message)
                                        <div class="text-dark">
                                            {{ \Illuminate\Support\Str::limit((string) $report->message->message, 80) }}
                                        </div>
                                        <small class="text-muted">
                                            من: {{ $report->message->sender?->full_name ?? 'إدارة التطبيق' }}
                                        </small>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge badge-{{ $report->statusBadgeClass() }}">{{ $report->statusLabel() }}</span>
                                </td>
                                <td>
                                    <small class="text-muted">{{ $report->created_at?->format('Y-m-d H:i') }}</small>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        @if ($can['messages'] && $report->message)
                                            <button type="button" class="btn btn-outline-warning" title="إخفاء الرسالة"
                                                wire:click="hideMessage({{ $report->message_id }})">
                                                <i class="fas fa-eye-slash"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-danger" title="حذف الرسالة"
                                                wire:click="deleteMessage({{ $report->message_id }})"
                                                onclick="return confirm('هل تريد حذف الرسالة نهائياً؟')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        @endif
                                        @if ($can['bans'] && $report->message)
                                            <button type="button" class="btn btn-outline-dark" title="حظر المُبلَّغ عنه"
                                                wire:click="banOffender({{ $report->message->sender_person_id }}, {{ $report->conversation_id }})">
                                                <i class="fas fa-ban"></i>
                                            </button>
                                        @endif
                                        @if ($can['reports'] && $report->isPending())
                                            <button type="button" class="btn btn-outline-info" title="قيد المراجعة"
                                                wire:click="markReviewing({{ $report->id }})">
                                                <i class="fas fa-search"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-success" title="معالجة"
                                                wire:click="resolve({{ $report->id }})">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary" title="رفض"
                                                wire:click="dismiss({{ $report->id }})">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-4 text-muted">لا توجد بلاغات مطابقة.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-3">
                {{ $reports->links() }}
            </div>
        </div>
    </div>
</div>
