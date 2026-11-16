<div>
    {{-- الفلاتر --}}
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-filter mr-1"></i> تصفية السجل</h6>
        </div>
        <div class="card-body">
            <div class="form-row align-items-end">
                <div class="form-group col-md-4 mb-2">
                    <label class="small text-muted">نوع الإجراء</label>
                    <select wire:model.live="action" class="form-control form-control-sm">
                        <option value="">كل الإجراءات</option>
                        @foreach ($actions as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-4 mb-2">
                    <label class="small text-muted">بحث</label>
                    <input type="text" wire:model.live.debounce.400ms="search" class="form-control form-control-sm"
                        placeholder="اسم الشخص أو اسم الإداري أو السبب...">
                </div>
                <div class="form-group col-md-2 mb-2">
                    <label class="small text-muted">لكل صفحة</label>
                    <select wire:model.live="perPage" class="form-control form-control-sm">
                        <option value="20">20</option>
                        <option value="30">30</option>
                        <option value="50">50</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-history mr-1"></i> سجل التدقيق
                <span class="badge badge-light text-muted mr-1">{{ $logs->total() }}</span>
            </h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0 text-center">
                    <thead class="thead-light">
                        <tr>
                            <th>#</th>
                            <th>الإجراء</th>
                            <th>المحادثة</th>
                            <th>الرسالة</th>
                            <th>الشخص</th>
                            <th>نفّذه</th>
                            <th>السبب</th>
                            <th>التاريخ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            <tr wire:key="log-{{ $log->id }}">
                                <td>{{ $log->id }}</td>
                                <td>
                                    <span class="badge badge-{{ $log->actionBadgeClass() }}">{{ $log->actionLabel() }}</span>
                                </td>
                                <td class="text-right small">
                                    @if ($log->conversation)
                                        <a href="{{ route('dashboard.chat.conversation.show', $log->conversation) }}">
                                            {{ \Illuminate\Support\Str::limit($log->conversation->title, 35) }}
                                        </a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-right small">
                                    @if ($log->message)
                                        {{ \Illuminate\Support\Str::limit((string) $log->message->message, 45) }}
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-right small">{{ $log->person?->full_name ?? '—' }}</td>
                                <td class="text-right small">{{ $log->adminUser?->name ?? 'النظام' }}</td>
                                <td class="text-right small">{{ \Illuminate\Support\Str::limit((string) $log->reason, 40) }}</td>
                                <td>
                                    <small class="text-muted">{{ $log->created_at?->format('Y-m-d H:i') }}</small>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-4 text-muted">لا توجد سجلات مطابقة.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-3">
                {{ $logs->links() }}
            </div>
        </div>
    </div>
</div>
