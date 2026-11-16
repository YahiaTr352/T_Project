<div>
    {{-- إحصائيات --}}
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body py-3">
                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">إجمالي الكلمات</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $counts['total'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body py-3">
                    <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">حجب (منع الإرسال)</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $counts['block'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body py-3">
                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">إخفاء (***)</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $counts['mask'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body py-3">
                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">تحذير (تسجيل فقط)</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $counts['warn'] }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- نموذج الإضافة --}}
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-plus-circle mr-1"></i> إضافة كلمة ممنوعة</h6>
        </div>
        <div class="card-body">
            <div class="form-row align-items-end">
                <div class="form-group col-md-5 mb-2">
                    <label class="small text-muted">الكلمة <span class="text-danger">*</span></label>
                    <input type="text" wire:model="word" class="form-control form-control-sm"
                        placeholder="الكلمة الممنوعة...">
                    @error('word') <span class="text-danger small">{{ $message }}</span> @enderror
                    <small class="text-muted">يتم تجاهل الهمزات والتشكيل والتطويل أثناء الفحص.</small>
                </div>
                <div class="form-group col-md-4 mb-2">
                    <label class="small text-muted">الإجراء عند 발견ها</label>
                    <select wire:model="severity" class="form-control form-control-sm">
                        @foreach ($severities as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-3 mb-2">
                    <button type="button" wire:click="save" class="btn btn-primary btn-sm w-100">
                        <i class="fas fa-plus mr-1"></i> إضافة
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- الفلاتر --}}
    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="form-row align-items-end">
                <div class="form-group col-md-4 mb-2">
                    <label class="small text-muted">بحث</label>
                    <input type="text" wire:model.live.debounce.400ms="search" class="form-control form-control-sm"
                        placeholder="ابحث عن كلمة...">
                </div>
                <div class="form-group col-md-3 mb-2">
                    <label class="small text-muted">نوع الإجراء</label>
                    <select wire:model.live="severityFilter" class="form-control form-control-sm">
                        <option value="">كل الأنواع</option>
                        @foreach ($severities as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-2 mb-2">
                    <label class="small text-muted">لكل صفحة</label>
                    <select wire:model.live="perPage" class="form-control form-control-sm">
                        <option value="15">15</option>
                        <option value="25">25</option>
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
                <i class="fas fa-ban mr-1"></i> الكلمات الممنوعة
                <span class="badge badge-light text-muted mr-1">{{ $words->total() }}</span>
            </h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0 text-center">
                    <thead class="thead-light">
                        <tr>
                            <th>#</th>
                            <th>الكلمة</th>
                            <th>الإجراء</th>
                            <th>الحالة</th>
                            <th>أُضيفت</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($words as $item)
                            <tr wire:key="word-{{ $item->id }}">
                                <td>{{ $item->id }}</td>
                                <td class="text-right font-weight-bold text-dark">{{ $item->word }}</td>
                                <td>
                                    <select class="form-control form-control-sm" style="width: auto; display: inline-block;"
                                        wire:change="changeSeverity({{ $item->id }}, $event.target.value)">
                                        @foreach ($severities as $key => $label)
                                            <option value="{{ $key }}" {{ $item->severity === $key ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    @if ($item->is_active)
                                        <span class="badge badge-success">مفعّلة</span>
                                    @else
                                        <span class="badge badge-secondary">معطّلة</span>
                                    @endif
                                </td>
                                <td>
                                    <small class="text-muted">{{ $item->created_at?->format('Y-m-d') }}</small>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-{{ $item->is_active ? 'secondary' : 'success' }}"
                                            wire:click="toggle({{ $item->id }})">
                                            <i class="fas fa-power-off"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger" wire:click="remove({{ $item->id }})"
                                            onclick="return confirm('حذف هذه الكلمة الممنوعة؟')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-4 text-muted">لا توجد كلمات ممنوعة.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-3">
                {{ $words->links() }}
            </div>
        </div>
    </div>
</div>
