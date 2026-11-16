<div>
    {{-- نموذج الحظر الجديد --}}
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-plus-circle mr-1"></i> حظر جديد</h6>
        </div>
        <div class="card-body">
            <div class="form-row align-items-end">
                <div class="form-group col-md-3 mb-2">
                    <label class="small text-muted">الشخص <span class="text-danger">*</span></label>
                    <input type="text" wire:model.live.debounce.400ms="personSearch" class="form-control form-control-sm"
                        placeholder="ابحث بالاسم...">
                    @if ($personResults->isNotEmpty())
                        <div class="list-group mt-1 shadow" style="max-height: 180px; overflow-y: auto; z-index: 10; position: relative;">
                            @foreach ($personResults as $person)
                                <a href="#" class="list-group-item list-group-item-action py-1 small"
                                    wire:click="selectPerson({{ $person->id }})">{{ $person->full_name }}</a>
                            @endforeach
                        </div>
                    @endif
                    @error('personId') <span class="text-danger small">{{ $message }}</span> @enderror
                </div>

                <div class="form-group col-md-3 mb-2">
                    <label class="small text-muted">المحادثة</label>
                    <input type="text" wire:model.live.debounce.400ms="conversationSearch" class="form-control form-control-sm"
                        placeholder="ابحث باسم المحادثة...">
                    @if ($conversationResults->isNotEmpty())
                        <div class="list-group mt-1 shadow" style="max-height: 180px; overflow-y: auto; z-index: 10; position: relative;">
                            @foreach ($conversationResults as $conversation)
                                <a href="#" class="list-group-item list-group-item-action py-1 small"
                                    wire:click="selectConversation({{ $conversation->id }})">
                                    {{ \Illuminate\Support\Str::limit($conversation->title, 45) }}
                                </a>
                            @endforeach
                        </div>
                    @endif
                    @error('conversationId') <span class="text-danger small">{{ $message }}</span> @enderror
                </div>

                <div class="form-group col-md-2 mb-2">
                    <label class="small text-muted">النطاق</label>
                    <select wire:model="banScope" class="form-control form-control-sm">
                        <option value="conversation">محادثة محددة</option>
                        <option value="global">حظر عام</option>
                    </select>
                </div>

                <div class="form-group col-md-1 mb-2">
                    <label class="small text-muted">المدة (يوم)</label>
                    <input type="number" min="0" max="3650" wire:model="durationDays" class="form-control form-control-sm">
                </div>

                <div class="form-group col-md-3 mb-2">
                    <label class="small text-muted">السبب <span class="text-danger">*</span></label>
                    <input type="text" wire:model="reason" class="form-control form-control-sm"
                        placeholder="مثال: إساءة متكررة">
                    @error('reason') <span class="text-danger small">{{ $message }}</span> @enderror
                </div>

                <div class="form-group col-md-2 mb-2">
                    <button type="button" wire:click="save" class="btn btn-danger btn-sm w-100">
                        <i class="fas fa-ban mr-1"></i> تنفيذ الحظر
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- الفلاتر --}}
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-filter mr-1"></i> تصفية</h6>
        </div>
        <div class="card-body">
            <div class="form-row align-items-end">
                <div class="form-group col-md-4 mb-2">
                    <label class="small text-muted">بحث</label>
                    <input type="text" wire:model.live.debounce.400ms="search" class="form-control form-control-sm"
                        placeholder="اسم الشخص أو سبب الحظر...">
                </div>
                <div class="form-group col-md-3 mb-2">
                    <label class="small text-muted">النطاق</label>
                    <select wire:model.live="scope" class="form-control form-control-sm">
                        <option value="">كل النطاقات</option>
                        <option value="global">حظر عام</option>
                        <option value="conversation">حظر من محادثة</option>
                    </select>
                </div>
                <div class="form-group col-md-2 mb-2">
                    <label class="small text-muted">الحالة</label>
                    <select wire:model.live="onlyActive" class="form-control form-control-sm">
                        <option value="1">النشطة فقط</option>
                        <option value="0">كل السجلات</option>
                    </select>
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

    {{-- الجدول --}}
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-user-slash mr-1"></i> سجل الحظر
                <span class="badge badge-light text-muted mr-1">{{ $bans->total() }}</span>
            </h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0 text-center">
                    <thead class="thead-light">
                        <tr>
                            <th>#</th>
                            <th>الشخص</th>
                            <th>النطاق</th>
                            <th>المحادثة</th>
                            <th>السبب</th>
                            <th>الحالة</th>
                            <th>التاريخ</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($bans as $ban)
                            <tr wire:key="ban-{{ $ban->id }}">
                                <td>{{ $ban->id }}</td>
                                <td class="text-right small">{{ $ban->person?->full_name ?? 'مستخدم محذوف' }}</td>
                                <td>
                                    @if ($ban->isGlobalBan())
                                        <span class="badge badge-dark">حظر عام</span>
                                    @else
                                        <span class="badge badge-info">محادثة محددة</span>
                                    @endif
                                </td>
                                <td class="text-right small">
                                    @if ($ban->conversation)
                                        <a href="{{ route('dashboard.chat.conversation.show', $ban->conversation) }}">
                                            {{ \Illuminate\Support\Str::limit($ban->conversation->title, 40) }}
                                        </a>
                                    @else
                                        <span class="text-muted">كل المحادثات</span>
                                    @endif
                                </td>
                                <td class="text-right small">{{ $ban->reason }}</td>
                                <td>
                                    @if ($ban->isCurrentlyActive())
                                        <span class="badge badge-danger">نشط</span>
                                    @else
                                        <span class="badge badge-secondary">مرفوع</span>
                                    @endif
                                </td>
                                <td>
                                    <small class="text-muted">
                                        {{ $ban->banned_at?->format('Y-m-d H:i') }}
                                        @if ($ban->expires_at)
                                            <br>ينتهي {{ $ban->expires_at->format('Y-m-d') }}
                                        @endif
                                    </small>
                                </td>
                                <td>
                                    @can('chat.moderation.bans')
                                        @if ($ban->isCurrentlyActive())
                                            <button type="button" class="btn btn-sm btn-outline-secondary"
                                                wire:click="revoke({{ $ban->id }})">رفع الحظر</button>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-4 text-muted">لا توجد سجلات حظر مطابقة.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-3">
                {{ $bans->links() }}
            </div>
        </div>
    </div>
</div>
