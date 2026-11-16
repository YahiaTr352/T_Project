@extends('layouts.app')

@section('title', 'إدارة استطلاعات الرأي والمسابقات')

@push('styles')
@include('dashboard.quiz-competitions._styles')
<style>
    .quiz-title-link {
        font-weight: 700;
        font-size: 1rem;
        color: var(--dash-heading, #1e293b);
        text-decoration: none !important;
        transition: color 0.2s ease;
    }
    .quiz-title-link:hover {
        color: #0d9488;
    }
    body.dark .quiz-title-link {
        color: #f1f5f9;
    }
    body.dark .quiz-title-link:hover {
        color: #2dd4bf;
    }
    /* Drag & Drop Styles for Competitions Table */
    .drag-handle-cell {
        cursor: grab;
        user-select: none;
    }
    .drag-handle-cell:active, .sortable-comp-row.sortable-chosen {
        cursor: grabbing;
    }
    .drag-grip-icon {
        color: #94a3b8;
        font-size: 1.15rem;
        transition: color 0.2s;
    }
    .sortable-comp-row:hover .drag-grip-icon {
        color: #0d9488;
    }
    .comp-order-badge {
        min-width: 28px;
        height: 24px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        font-size: 0.78rem;
        font-weight: 800;
        color: #475569;
        background: #f8fafc;
    }
    body.dark .comp-order-badge {
        color: #cbd5e1;
        background: #1e293b;
    }
    .sortable-ghost {
        opacity: 0.35;
        background: #ecfdf5 !important;
    }
    .sortable-chosen {
        background: #f0fdf4 !important;
        box-shadow: 0 4px 12px rgba(13, 148, 136, 0.15);
    }
    /* Floating Reorder Toast */
    .reorder-toast-notification {
        position: fixed;
        bottom: 24px;
        left: 24px;
        background: #ffffff;
        border: 1.5px solid #10b981;
        border-radius: 12px;
        padding: 12px 20px;
        z-index: 99999;
        direction: rtl;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.12);
        animation: toastSlideUp 0.3s ease-out;
    }
    body.dark .reorder-toast-notification {
        background: #1e293b;
        border-color: #059669;
    }
    body.dark .reorder-toast-notification #reorderToastMessage {
        color: #f1f5f9 !important;
    }
    @keyframes toastSlideUp {
        from { transform: translateY(20px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }
</style>
@endpush

@section('content')
<div class="container-fluid py-4">
    {{-- Page Header --}}
    <x-dashboard.page-header
        title="إدارة استطلاعات الرأي والمسابقات"
        description="أنشئ وأدر استطلاعات الرأي والمسابقات التفاعلية لأفراد العائلة، تتبع الردود الحية، وأجرِ السحب الآلي على الجوائز.">
        <x-slot name="actions">
            <a href="{{ route('dashboard.quiz-competitions.create') }}" class="btn btn-primary d-inline-flex align-items-center gap-2 px-3 py-2 shadow-sm" style="border-radius: 12px; font-weight: 700;">
                <i class="fe fe-plus"></i>
                <span>إنشاء استطلاع جديد</span>
            </a>
        </x-slot>
    </x-dashboard.page-header>

    {{-- System Alerts --}}
    @include('components.alerts')

    {{-- Statistics Cards --}}
    <div class="row mb-4">
        <x-dashboard.stat-card
            icon="fe-help-circle"
            title="إجمالي الاستطلاعات"
            :value="$stats['total']"
            gradient="primary"
            description="جميع الفعاليات والاستطلاعات"
        />
        <x-dashboard.stat-card
            icon="fe-check-circle"
            title="الاستطلاعات النشطة"
            :value="$stats['active']"
            gradient="success"
            description="متاحة للمشاركة والتصويت"
        />
        <x-dashboard.stat-card
            icon="fe-list"
            title="إجمالي الأسئلة والبنود"
            :value="$stats['total_questions']"
            gradient="info"
            description="بنود الاستبيانات والأسئلة"
        />
        <x-dashboard.stat-card
            icon="fe-clock"
            title="الاستطلاعات المغلقة"
            :value="$stats['inactive']"
            gradient="warning"
            description="مسودات أو منتهية الصلاحية"
        />
    </div>

    {{-- Main Table Card --}}
    <x-dashboard.card title="قائمة استطلاعات الرأي والمسابقات" icon="fe-help-circle">
        <x-slot name="headerAction">
            <div class="d-flex align-items-center gap-2">
                <span class="badge badge-light text-muted border px-2 py-1 small" style="border-radius: 8px;">
                    <i class="fe fe-move mr-1 text-primary"></i> اسحب الصفوف لتحديد أولوية الظهور في التطبيق
                </span>
                <span class="badge badge-light px-3 py-2 border font-weight-bold" style="border-radius: 10px; font-size: 0.82rem;">
                    العدد الإجمالي: {{ $competitions->count() }} استطلاع
                </span>
            </div>
        </x-slot>

        @if($competitions->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width: 75px;" class="text-center">الترتيب</th>
                            <th>عنوان الاستطلاع</th>
                            <th>الأسئلة والبنود</th>
                            <th>الفترة الزمنية</th>
                            <th>الرعاة الداعمون</th>
                            <th>الحالة</th>
                            <th style="width: 170px;" class="text-center">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody id="sortableCompetitionsBody">
                        @foreach($competitions as $index => $competition)
                            <tr class="sortable-comp-row" data-competition-id="{{ $competition->id }}">
                                <td class="text-center drag-handle-cell" title="اسحب من هنا لإعادة الترتيب">
                                    <div class="d-flex align-items-center justify-content-center gap-2">
                                        <span class="drag-grip-icon"><i class="fe fe-menu"></i></span>
                                        <span class="comp-order-badge badge badge-light border">{{ $index + 1 }}</span>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <a href="{{ route('dashboard.quiz-competitions.show', $competition) }}" class="quiz-title-link d-inline-block">
                                            {{ $competition->title }}
                                        </a>
                                        @if($competition->show_draw_only)
                                            <span class="badge badge-light text-warning border px-2 py-0 mr-1 font-weight-bold" style="font-size: 0.72rem; border-radius: 6px;">
                                                <i class="fe fe-award mr-1"></i>وضع القرعة فقط
                                            </span>
                                        @endif
                                        @if($competition->description)
                                            <small class="text-muted d-block mt-1">
                                                {{ Str::limit(strip_tags($competition->description), 65) }}
                                            </small>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-light text-primary border px-2 py-1 font-weight-bold" style="border-radius: 8px;">
                                        <i class="fe fe-list mr-1"></i>
                                        {{ $competition->questions->count() }} بنود
                                    </span>
                                </td>
                                <td>
                                    @if($competition->start_at || $competition->end_at)
                                        <div class="small">
                                            @if($competition->start_at)
                                                <div class="text-muted"><i class="fe fe-calendar mr-1"></i>{{ $competition->start_at->format('Y-m-d') }}</div>
                                            @endif
                                            @if($competition->end_at)
                                                <div class="text-muted mt-1"><i class="fe fe-arrow-left mr-1"></i>{{ $competition->end_at->format('Y-m-d') }}</div>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-muted small">— غير محددة —</span>
                                    @endif
                                </td>
                                <td>
                                    @if($competition->sponsors && $competition->sponsors->count() > 0)
                                        <div class="d-flex flex-wrap gap-1">
                                            @foreach($competition->sponsors->take(2) as $sponsor)
                                                <span class="badge badge-light border px-2 py-1 small" style="border-radius: 6px;">
                                                    {{ $sponsor->name }}
                                                </span>
                                            @endforeach
                                            @if($competition->sponsors->count() > 2)
                                                <span class="badge badge-light text-muted border px-1" style="border-radius: 6px;">
                                                    +{{ $competition->sponsors->count() - 2 }}
                                                </span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($competition->is_active)
                                        <span class="badge badge-success px-2 py-1 font-weight-bold" style="border-radius: 8px;">
                                            <i class="fe fe-check mr-1"></i>نشط
                                        </span>
                                    @else
                                        <span class="badge badge-secondary px-2 py-1 font-weight-bold" style="border-radius: 8px;">
                                            <i class="fe fe-pause mr-1"></i>معطل
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex align-items-center justify-content-center gap-1">
                                        <a href="{{ route('dashboard.quiz-competitions.show', $competition) }}" class="btn btn-sm btn-outline-info p-2" title="عرض تفاصيل الاستطلاع والأسئلة" style="border-radius: 8px;">
                                            <i class="fe fe-eye"></i>
                                        </a>
                                        <a href="{{ route('dashboard.quiz-competitions.responses', $competition) }}" class="btn btn-sm btn-outline-success p-2" title="لوحة النتائج والإحصائيات" style="border-radius: 8px;">
                                            <i class="fe fe-bar-chart-2"></i>
                                        </a>
                                        <a href="{{ route('dashboard.quiz-competitions.export', $competition) }}" class="btn btn-sm btn-outline-secondary p-2" title="تصدير النتائج Excel" target="_blank" rel="noopener" style="border-radius: 8px;">
                                            <i class="fe fe-download"></i>
                                        </a>
                                        <a href="{{ route('dashboard.quiz-competitions.edit', $competition) }}" class="btn btn-sm btn-outline-primary p-2" title="تعديل الإعدادات" style="border-radius: 8px;">
                                            <i class="fe fe-edit-2"></i>
                                        </a>
                                        <form action="{{ route('dashboard.quiz-competitions.destroy', $competition) }}" method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من حذف هذا الاستطلاع وكل أسئلته ونتائجه؟');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger p-2" title="حذف الاستطلاع" style="border-radius: 8px;">
                                                <i class="fe fe-trash-2"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            {{-- Empty State --}}
            <div class="text-center py-5">
                <div class="mb-4">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-light text-muted" style="width: 80px; height: 80px;">
                        <i class="fe fe-help-circle" style="font-size: 2.2rem;"></i>
                    </span>
                </div>
                <h5 class="font-weight-bold text-dark mb-2">لا توجد استطلاعات رأي أو مسابقات حالياً</h5>
                <p class="text-muted max-w-sm mx-auto mb-4">
                    قم بإنشاء استطلاع رأي تفاعلي أو مسابقة عائلية وإضافة الأسئلة والخيارات لبدء جمع الأصوات.
                </p>
                <a href="{{ route('dashboard.quiz-competitions.create') }}" class="btn btn-primary d-inline-flex align-items-center gap-2 px-4 py-2" style="border-radius: 12px; font-weight: 700;">
                    <i class="fe fe-plus"></i>
                    <span>إنشاء استطلاع جديد الآن</span>
                </a>
            </div>
        @endif
    </x-dashboard.card>

    {{-- Floating Toast Notification for Reorder --}}
    <div id="reorderToast" class="reorder-toast-notification shadow-lg" style="display: none;">
        <div class="d-flex align-items-center gap-2">
            <i class="fe fe-check-circle text-success" style="font-size: 1.25rem;"></i>
            <span id="reorderToastMessage" class="font-weight-bold small text-dark">تم تحديث ترتيب المسابقات بنجاح</span>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var tbody = document.getElementById('sortableCompetitionsBody');
        if (!tbody) return;

        var sortable = new Sortable(tbody, {
            handle: '.drag-handle-cell',
            animation: 220,
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            onEnd: function () {
                var rows = tbody.querySelectorAll('.sortable-comp-row');
                var competitionIds = [];
                rows.forEach(function (row, idx) {
                    var badge = row.querySelector('.comp-order-badge');
                    if (badge) {
                        badge.textContent = idx + 1;
                    }
                    var id = row.getAttribute('data-competition-id');
                    if (id) {
                        competitionIds.push(parseInt(id, 10));
                    }
                });

                fetch("{{ route('dashboard.quiz-competitions.reorder') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': "{{ csrf_token() }}",
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ competition_ids: competitionIds })
                })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data && data.success) {
                        showReorderToast(data.message || 'تم تحديث وترتيب المسابقات بنجاح ✓', false);
                    } else {
                        showReorderToast('حدث خطأ أثناء حفظ الترتيب', true);
                    }
                })
                .catch(function (err) {
                    console.error('Error reordering competitions', err);
                    showReorderToast('فشل حفظ الترتيب، يرجى المحاولة لاحقاً', true);
                });
            }
        });

        var toastTimeout;
        function showReorderToast(msg, isError) {
            var toast = document.getElementById('reorderToast');
            var msgEl = document.getElementById('reorderToastMessage');
            if (!toast || !msgEl) return;

            msgEl.textContent = msg;
            var icon = toast.querySelector('i');
            if (isError) {
                toast.style.borderColor = '#ef4444';
                if (icon) icon.className = 'fe fe-alert-circle text-danger';
            } else {
                toast.style.borderColor = '#10b981';
                if (icon) icon.className = 'fe fe-check-circle text-success';
            }

            toast.style.display = 'block';
            clearTimeout(toastTimeout);
            toastTimeout = setTimeout(function () {
                toast.style.display = 'none';
            }, 3500);
        }
    });
</script>
@endpush
