@extends('layouts.app')

@section('title', 'طلبات تعديل بيانات الأعضاء')

@section('content')
<div class="container-fluid">
    <x-dashboard.page-header title="طلبات تعديل بيانات الأعضاء" description="مراجعة واعتماد التعديلات المقدمة من أفراد العائلة قبل تطبيقها على الشجرة">
        <x-slot name="actions">
            <a href="{{ route('people.index') }}" class="btn btn-outline-secondary shadow-sm">
                <i class="fas fa-users mr-1"></i> إدارة الشخصيات
            </a>
            <a href="{{ route('family-tree') }}" class="btn btn-dark shadow-sm">
                <i class="fas fa-tree mr-1"></i> شجرة العائلة
            </a>
        </x-slot>
    </x-dashboard.page-header>

    @include('components.alerts')

    {{-- بطاقات الإحصائيات --}}
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-0 shadow-sm rounded-lg bg-warning text-dark h-100">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-uppercase small font-weight-bold mb-1">طلبات قيد المراجعة</div>
                        <div class="h3 mb-0 font-weight-bold">{{ $pendingCount }}</div>
                    </div>
                    <i class="fas fa-clock fa-2x opacity-75"></i>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-0 shadow-sm rounded-lg bg-success text-white h-100">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-uppercase small font-weight-bold mb-1">طلبات معتمدة</div>
                        <div class="h3 mb-0 font-weight-bold">{{ $approvedCount }}</div>
                    </div>
                    <i class="fas fa-check-circle fa-2x opacity-75"></i>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-0 shadow-sm rounded-lg bg-danger text-white h-100">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-uppercase small font-weight-bold mb-1">طلبات مرفوضة</div>
                        <div class="h3 mb-0 font-weight-bold">{{ $rejectedCount }}</div>
                    </div>
                    <i class="fas fa-times-circle fa-2x opacity-75"></i>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-0 shadow-sm rounded-lg bg-primary text-white h-100">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-uppercase small font-weight-bold mb-1">إجمالي الطلبات</div>
                        <div class="h3 mb-0 font-weight-bold">{{ $pendingCount + $approvedCount + $rejectedCount }}</div>
                    </div>
                    <i class="fas fa-list fa-2x opacity-75"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- الفلاتر والبحث --}}
    <div class="card shadow-sm border-0 mb-4 rounded-lg">
        <div class="card-body">
            <form method="GET" action="{{ route('dashboard.profile-updates.index') }}" class="row align-items-end">
                <div class="col-md-4 mb-2">
                    <label class="form-label font-weight-bold small">بحث باسم العضو:</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-search text-muted"></i></span>
                        </div>
                        <input type="text" name="search" value="{{ $search }}" class="form-control" placeholder="ابحث بالاسم...">
                    </div>
                </div>

                <div class="col-md-3 mb-2">
                    <label class="form-label font-weight-bold small">حالة الطلب:</label>
                    <select name="status" class="form-control" onchange="this.form.submit()">
                        <option value="">جميع الحالات</option>
                        <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>قيد الانتظار (معلق)</option>
                        <option value="approved" {{ $status === 'approved' ? 'selected' : '' }}>معتمد</option>
                        <option value="rejected" {{ $status === 'rejected' ? 'selected' : '' }}>مرفوض</option>
                    </select>
                </div>

                <div class="col-md-3 mb-2">
                    <label class="form-label font-weight-bold small">نوع التعديل:</label>
                    <select name="change_type" class="form-control" onchange="this.form.submit()">
                        <option value="">جميع الأنواع</option>
                        <option value="profile_info" {{ $changeType === 'profile_info' ? 'selected' : '' }}>البيانات الشخصية</option>
                        <option value="contact_add" {{ $changeType === 'contact_add' ? 'selected' : '' }}>إضافة تواصل</option>
                        <option value="contact_update" {{ $changeType === 'contact_update' ? 'selected' : '' }}>تعديل تواصل</option>
                        <option value="contact_delete" {{ $changeType === 'contact_delete' ? 'selected' : '' }}>حذف تواصل</option>
                        <option value="child_add" {{ $changeType === 'child_add' ? 'selected' : '' }}>إضافة ابن/ابنة</option>
                        <option value="marriage_add" {{ $changeType === 'marriage_add' ? 'selected' : '' }}>إضافة زواج</option>
                        <option value="marriage_update" {{ $changeType === 'marriage_update' ? 'selected' : '' }}>تعديل زواج</option>
                        <option value="location_add" {{ $changeType === 'location_add' ? 'selected' : '' }}>إضافة موقع</option>
                        <option value="location_update" {{ $changeType === 'location_update' ? 'selected' : '' }}>تعديل موقع</option>
                        <option value="location_delete" {{ $changeType === 'location_delete' ? 'selected' : '' }}>حذف موقع</option>
                    </select>
                </div>

                <div class="col-md-2 mb-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-filter mr-1"></i> تصفية
                    </button>
                    @if($search || $status || $changeType)
                        <a href="{{ route('dashboard.profile-updates.index') }}" class="btn btn-outline-secondary ml-1" title="إعادة تعيين">
                            <i class="fas fa-undo"></i>
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    {{-- جدول الطلبات --}}
    <div class="card shadow-sm border-0 rounded-lg">
        <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between border-bottom">
            <h6 class="m-0 font-weight-bold">
                <i class="fas fa-list-check text-primary mr-2"></i>قائمة الطلبات ({{ $requests->total() }})
            </h6>

            @if($status === 'pending' && $requests->isNotEmpty())
                <form action="{{ route('dashboard.profile-updates.bulk-approve') }}" method="POST" id="bulkApproveForm" class="d-inline">
                    @csrf
                    <button type="button" class="btn btn-sm btn-success shadow-sm" onclick="submitBulkApprove()">
                        <i class="fas fa-check-double mr-1"></i> اعتماد المحدد دفعة واحدة
                    </button>
                </form>
            @endif
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="thead-light">
                        <tr>
                            @if($status === 'pending')
                                <th width="40" class="text-center">
                                    <input type="checkbox" id="selectAll">
                                </th>
                            @endif
                            <th>العضو مقدم الطلب</th>
                            <th>نوع الطلب</th>
                            <th>طريقة المصادقة</th>
                            <th>تاريخ التقديم</th>
                            <th>الحالة</th>
                            <th>المراجع</th>
                            <th class="text-center" width="160">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($requests as $item)
                            <tr>
                                @if($status === 'pending')
                                    <td class="text-center">
                                        <input type="checkbox" name="ids[]" value="{{ $item->id }}" form="bulkApproveForm" class="request-checkbox">
                                    </td>
                                @endif
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="{{ $item->person?->avatar }}" alt="" class="rounded-circle mr-2 border" width="40" height="40" style="object-fit: cover;">
                                        <div>
                                            <a href="{{ $item->person ? route('people.show', $item->person->id) : '#' }}" class="font-weight-bold text-decoration-none">
                                                {{ $item->person?->full_name ?? 'شخص محذوف' }}
                                            </a>
                                            <div class="small text-muted">ID: #{{ $item->person_id }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-light border px-2 py-1 font-weight-normal">
                                        <i class="fe {{ $item->change_type_icon }} mr-1 text-primary"></i>
                                        {{ $item->change_type_label }}
                                    </span>
                                </td>
                                <td>
                                    @if($item->requested_by_auth === 'face')
                                        <span class="badge badge-info text-white"><i class="fas fa-smile mr-1"></i> بصمة الوجه</span>
                                    @elseif($item->requested_by_auth === 'whatsapp')
                                        <span class="badge badge-success text-white"><i class="fab fa-whatsapp mr-1"></i> واتساب OTP</span>
                                    @elseif($item->requested_by_auth === 'system' || $item->requested_by_auth === 'system_normalization')
                                        <span class="badge badge-dark text-white"><i class="fas fa-robot mr-1"></i> تدقيق النظام الآلي</span>
                                    @else
                                        <span class="badge badge-secondary">{{ $item->requested_by_auth }}</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="small font-weight-bold">{{ $item->created_at->format('Y-m-d H:i') }}</div>
                                    <div class="small text-muted">{{ $item->created_at->diffForHumans() }}</div>
                                </td>
                                <td>
                                    <span class="badge {{ $item->status_badge_class }} px-2 py-1">
                                        {{ $item->status_label }}
                                    </span>
                                    @if($item->isRejected() && $item->rejection_reason)
                                        <div class="small text-danger mt-1" title="{{ $item->rejection_reason }}">
                                            <i class="fas fa-info-circle mr-1"></i> {{ \Illuminate\Support\Str::limit($item->rejection_reason, 25) }}
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    @if($item->reviewer)
                                        <span class="small font-weight-bold">{{ $item->reviewer->name }}</span>
                                        <div class="small text-muted">{{ optional($item->reviewed_at)->format('Y-m-d H:i') }}</div>
                                    @else
                                        <span class="small text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('dashboard.profile-updates.show', $item->id) }}" class="btn btn-outline-primary" title="معاينة الفروقات والمراجعة">
                                            <i class="fas fa-eye"></i> معاينة
                                        </a>

                                        @if($item->isPending())
                                            <form action="{{ route('dashboard.profile-updates.approve', $item->id) }}" method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من اعتماد هذا الطلب وتطبيقه على الشجرة؟');">
                                                @csrf
                                                <button type="submit" class="btn btn-success" title="اعتماد سريع">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                            <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#rejectModal{{ $item->id }}" title="رفض">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        @elseif($item->isRejected())
                                            <form action="{{ route('dashboard.profile-updates.reopen-and-approve', $item->id) }}" method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من إعادة فتح هذا الطلب واعتماده وتطبيقه على الشجرة؟ سيتم إشعار العضو بقبول طلبه.');">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-success" title="إعادة فتح واعتماد">
                                                    <i class="fas fa-undo-alt"></i> اعتماد
                                                </button>
                                            </form>
                                        @endif
                                    </div>

                                    @if($item->isPending())
                                        {{-- Modal الرفض --}}
                                        <div class="modal fade" id="rejectModal{{ $item->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                                            <div class="modal-dialog text-right" role="document">
                                                <div class="modal-content border-0 shadow">
                                                    <form action="{{ route('dashboard.profile-updates.reject', $item->id) }}" method="POST">
                                                        @csrf
                                                        <div class="modal-header bg-danger text-white">
                                                            <h5 class="modal-title font-weight-bold">
                                                                <i class="fas fa-times-circle mr-2"></i> رفض طلب التعديل
                                                            </h5>
                                                            <button type="button" class="close text-white" data-dismiss="modal">
                                                                <span>&times;</span>
                                                            </button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <p class="text-muted small">سيتم إرسال رسالة واتساب للعضو لإبلاغه برفض الطلب مع ذكر السبب إن وُجد.</p>
                                                            <div class="form-group">
                                                                <label class="font-weight-bold">سبب الرفض (اختياري):</label>
                                                                <textarea name="reason" class="form-control" rows="3" placeholder="مثال: يرجى التأكد من صحة الاسم أو إرفاق صورة واضحة..."></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">إلغاء</button>
                                                            <button type="submit" class="btn btn-danger font-weight-bold">تأكيد الرفض</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="fas fa-inbox fa-3x mb-3 text-gray-300 d-block"></i>
                                    لا توجد أي طلبات تعديل حالياً.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($requests->hasPages())
            <div class="card-footer bg-white border-top py-3 d-flex justify-content-center">
                {{ $requests->links() }}
            </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const selectAll = document.getElementById('selectAll');
        if (selectAll) {
            selectAll.addEventListener('change', function () {
                document.querySelectorAll('.request-checkbox').forEach(cb => cb.checked = selectAll.checked);
            });
        }
    });

    function submitBulkApprove() {
        const checked = document.querySelectorAll('.request-checkbox:checked');
        if (checked.length === 0) {
            alert('يرجى تحديد طلب واحد على الأقل للاعتماد.');
            return;
        }
        if (confirm(`هل أنت متأكد من اعتماد (${checked.length}) طلب(ات) وتطبيقها دفعة واحدة؟`)) {
            document.getElementById('bulkApproveForm').submit();
        }
    }
</script>
@endpush
@endsection
