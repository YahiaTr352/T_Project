@extends('layouts.app')

@section('title', 'تعديل وإدارة المجموعة')

@push('styles')
    @include('dashboard.notifications.partials.styles')
@endpush

@section('content')
<div class="container-fluid py-4" style="font-family: 'Tajawal', sans-serif;">
    {{-- Header --}}
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 font-bold notif-title mb-1">
                <i class="fas fa-users-cog text-emerald-600 dark:text-emerald-400 mr-2"></i>إدارة المجموعة: {{ $notificationGroup->name }}
            </h1>
            <p class="text-xs notif-subtitle m-0">تعديل تفاصيل المجموعة وتخصيص الأعضاء المسجلين فيها.</p>
        </div>
        <a href="{{ route('dashboard.notification-groups.index') }}" class="btn btn-outline-secondary btn-sm px-3 rounded-xl text-xs font-bold">
            العودة للمجموعات
        </a>
    </div>

    {{-- Alerts --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-xl shadow-sm border-0 mb-4 flex items-center justify-between" role="alert">
            <div class="flex items-center gap-2 text-xs font-bold">
                <i class="fas fa-check-circle text-emerald-600 text-base"></i>
                <span>{{ session('success') }}</span>
            </div>
            <button type="button" class="close text-emerald-700" data-dismiss="alert"><span>&times;</span></button>
        </div>
    @endif

    <div class="row">
        {{-- Left Column: Form --}}
        <div class="col-lg-5 mb-4">
            <div class="dashboard-notif-card">
                <div class="border-b border-slate-200 dark:border-slate-800 pb-3 mb-4">
                    <h5 class="text-sm font-bold notif-title m-0 flex items-center gap-2">
                        <i class="fas fa-edit text-emerald-600 dark:text-emerald-400"></i> تعديل البيانات الأساسية
                    </h5>
                </div>
                
                <form action="{{ route('dashboard.notification-groups.update', $notificationGroup) }}" method="post" class="space-y-4">
                    @csrf
                    @method('PUT')
                    <div>
                        <label for="name" class="block text-xs font-bold notif-title mb-1.5">اسم المجموعة:</label>
                        <input type="text" name="name" id="name" class="dashboard-input" value="{{ old('name', $notificationGroup->name) }}" required>
                    </div>
                    <div>
                        <label for="description" class="block text-xs font-bold notif-title mb-1.5">الوصف والملاحظات:</label>
                        <textarea name="description" id="description" class="dashboard-input" rows="3">{{ old('description', $notificationGroup->description) }}</textarea>
                    </div>

                    <button type="submit" class="btn btn-primary px-5 py-2 text-xs font-bold rounded-xl border-0 shadow-md">
                        <i class="fas fa-save mr-1"></i> حفظ التعديلات
                    </button>
                </form>
            </div>
        </div>

        {{-- Right Column: Members List --}}
        <div class="col-lg-7 mb-4">
            <div class="dashboard-notif-card p-0 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-900/80 flex items-center justify-between">
                    <h5 class="text-sm font-bold notif-title m-0 flex items-center gap-2">
                        <i class="fas fa-user-group text-emerald-600 dark:text-emerald-400"></i> أعضاء المجموعة 
                        <span class="px-2.5 py-0.5 text-xs rounded-full bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300 font-bold">
                            {{ number_format($notificationGroup->persons->count()) }} فرد
                        </span>
                    </h5>
                    <button type="button" class="btn btn-sm btn-success px-3.5 py-1.5 rounded-xl border-0 font-bold text-xs shadow-sm flex items-center gap-1" data-toggle="modal" data-target="#addPersonModal">
                        <i class="fas fa-user-plus"></i> إضافة عضو
                    </button>
                </div>

                <div class="p-6">
                    @if($notificationGroup->persons->count() > 0)
                        <div class="dashboard-table-container">
                            <table class="dashboard-table text-xs">
                                <thead>
                                    <tr>
                                        <th>اسم العضو</th>
                                        <th>المنطقة / المدينة</th>
                                        <th class="text-left">العمليات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($notificationGroup->persons as $p)
                                        <tr>
                                            <td class="font-bold notif-title">
                                                <a href="{{ route('people.show', $p->id) }}" class="text-emerald-700 dark:text-emerald-400 decoration-none hover:underline flex items-center gap-1.5 font-bold">
                                                    <i class="fas fa-user-circle text-slate-400"></i> {{ $p->full_name }}
                                                </a>
                                            </td>
                                            <td class="notif-subtitle font-bold">{{ $p->location ? $p->location->name : 'غير محدد' }}</td>
                                            <td class="text-left">
                                                <form action="{{ route('dashboard.notification-groups.detach-person', [$notificationGroup, $p]) }}" method="post" class="d-inline m-0" onsubmit="return confirm('هل تريد إزالة هذا الشخص من المجموعة؟');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-rose px-2.5 py-1 rounded-lg text-[11px] text-rose-700 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 border-rose-300 font-bold">
                                                        إزالة
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-10">
                            <div class="w-14 h-14 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center mx-auto mb-3 notif-muted">
                                <i class="fas fa-user-xmark text-xl"></i>
                            </div>
                            <h5 class="font-bold notif-title text-xs mb-1">لا يوجد أعضاء مضافين في هذه المجموعة</h5>
                            <p class="notif-subtitle text-[11px] mb-3">اضغط على زر "إضافة عضو" لبدء تخصيص المستلمين.</p>
                            <button type="button" class="btn btn-sm btn-primary px-4 rounded-xl border-0 font-bold text-xs" data-toggle="modal" data-target="#addPersonModal">
                                <i class="fas fa-plus mr-1"></i> إضافة أول عضو
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Add Member Modal --}}
<div class="modal fade" id="addPersonModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content dark:bg-slate-900 border-0 shadow-2xl rounded-2xl overflow-hidden">
            <div class="modal-header border-b border-slate-200 dark:border-slate-800 px-4 py-3">
                <h5 class="modal-title font-bold notif-title text-sm">إضافة شخص لشجرة العائلة إلى المجموعة</h5>
                <button type="button" class="close text-slate-400 hover:text-slate-600" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form action="{{ route('dashboard.notification-groups.attach-person', $notificationGroup) }}" method="post">
                @csrf
                <div class="modal-body p-4">
                    <div class="form-group mb-0">
                        <label class="block text-xs font-bold notif-title mb-2">ابحث بالاسم من شجرة العائلة:</label>
                        <select name="person_id" id="addPersonSelect" class="form-control" required style="width:100%;"></select>
                    </div>
                </div>
                <div class="modal-footer border-t border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/40 px-4 py-3">
                    <button type="button" class="btn btn-secondary btn-sm px-3 rounded-xl border-0 text-xs" data-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary btn-sm px-4 rounded-xl border-0 font-bold text-xs shadow-md">إضافة الفرد</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function() {
    var searchUrl = '{{ route("dashboard.notifications.persons-with-whatsapp") }}';
    if (typeof $ !== 'undefined' && $.fn.select2) {
        var $addSelect = $('#addPersonSelect');
        $addSelect.select2({
            placeholder: 'ابحث بالاسم...',
            dir: 'rtl',
            width: '100%',
            dropdownParent: $('#addPersonModal'),
            ajax: {
                url: searchUrl,
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return { q: params.term || '' };
                },
                processResults: function(data) {
                    var persons = (data && data.persons) ? data.persons : [];
                    return {
                        results: persons.map(function(p) {
                            return { id: p.id, text: p.full_name || ('#' + p.id) };
                        })
                    };
                },
                cache: true
            },
            minimumInputLength: 0
        });
    }
})();
</script>
@endpush
