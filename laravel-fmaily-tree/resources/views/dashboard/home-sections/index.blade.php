@extends('layouts.app')

@section('title', 'إدارة أقسام الصفحة الرئيسية')

@push('styles')
<style>
    .section-card-item {
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        background: #ffffff;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        overflow: hidden;
        margin-bottom: 14px;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.03);
    }
    body.dark .section-card-item {
        background: #1e293b;
        border-color: #334155;
        box-shadow: 0 4px 10px rgba(0,0,0,0.2);
    }
    .section-card-item:hover {
        border-color: #4f46e5;
        box-shadow: 0 10px 20px -3px rgba(79, 70, 229, 0.15);
        transform: translateY(-2px);
    }
    body.dark .section-card-item:hover {
        border-color: #818cf8;
        box-shadow: 0 10px 20px -3px rgba(99, 102, 241, 0.25);
    }
    .section-card-item.inactive {
        opacity: 0.65;
    }
    .section-card-item.inactive:hover {
        opacity: 0.95;
    }
    .section-header-row {
        display: flex;
        align-items: center;
        padding: 16px 20px;
        gap: 16px;
    }
    .drag-handle {
        color: #94a3b8;
        cursor: grab;
        font-size: 1.15rem;
        transition: color 0.2s;
        flex-shrink: 0;
    }
    .drag-handle:hover { color: #4f46e5; }
    .drag-handle:active { cursor: grabbing; }
    .section-info { flex: 1; min-width: 0; }
    .section-title {
        font-size: 1rem;
        font-weight: 700;
        color: #1e293b;
        margin: 0;
    }
    body.dark .section-title {
        color: #f1f5f9;
    }
    .order-badge {
        width: 32px;
        height: 32px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8rem;
        font-weight: 800;
        background: rgba(79, 70, 229, 0.1);
        color: #4f46e5;
        flex-shrink: 0;
    }
    body.dark .order-badge {
        background: rgba(129, 140, 248, 0.2);
        color: #a5b4fc;
    }
    .section-actions {
        display: flex;
        gap: 6px;
        flex-shrink: 0;
    }
    .section-actions .btn {
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        padding: 0;
        transition: all 0.2s ease;
    }
    .section-actions .btn:hover {
        transform: scale(1.08);
    }
    .save-order-bar {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
        color: #fff;
        padding: 14px 24px;
        display: none;
        align-items: center;
        justify-content: center;
        gap: 16px;
        z-index: 1050;
        box-shadow: 0 -4px 20px rgba(0,0,0,0.2);
        animation: slideUp 0.3s ease;
    }
    @keyframes slideUp {
        from { transform: translateY(100%); }
        to { transform: translateY(0); }
    }
</style>
@endpush

@section('content')
<div class="container-fluid py-3">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-1 text-gray-800 font-weight-bold">
                <i class="fas fa-magic text-primary mr-2"></i>أقسام الصفحة الرئيسية
            </h1>
            <p class="text-muted mb-0 small">إدارة وتنسيق أقسام الصفحة الرئيسية، تعديل الأكواد والمحتوى، وترتيب ظهورها بالسحب والإفلات.</p>
        </div>
        <a href="{{ route('dashboard.home-sections.create') }}" class="btn btn-primary shadow-sm font-weight-bold" style="border-radius: 12px; padding: 10px 20px;">
            <i class="fas fa-plus-circle mr-2"></i>إنشاء قسم جديد
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm mb-4" role="alert" style="border-radius: 12px;">
            <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm mb-4" role="alert" style="border-radius: 12px;">
            <i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    @endif

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <x-stats-card
            icon="fas fa-th-large"
            title="إجمالي الأقسام"
            :value="$stats['total'] ?? 0"
            color="primary"
        />
        <x-stats-card
            icon="fas fa-check-circle"
            title="أقسام نشطة بالرئيسية"
            :value="$stats['active'] ?? 0"
            color="success"
        />
        <x-stats-card
            icon="fas fa-eye-slash"
            title="أقسام معطلة"
            :value="$stats['inactive'] ?? 0"
            color="warning"
        />
    </div>

    <!-- Sections List Card -->
    @if($sections->count() > 0)
        <div class="card shadow-sm border-0 mb-4" style="border-radius: 16px; overflow: hidden;">
            <div class="card-header bg-transparent border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center" style="gap: 8px;">
                    <h6 class="mb-0 font-weight-bold text-primary">
                        <i class="fas fa-layer-group mr-1"></i>الأقسام المتاحة
                    </h6>
                    <span class="badge badge-light border font-weight-bold">{{ $sections->count() }}</span>
                </div>
                <small class="text-muted">
                    <i class="fas fa-arrows-alt-v mr-1"></i>اسحب المقبض الأيمن لأي قسم لتغيير ترتيبه
                </small>
            </div>
            <div class="card-body p-4">
                <div id="sections-sortable-list">
                    @php
                        $typeNames = [
                            'custom' => 'HTML & JS مخصص',
                            'rich_text' => 'نص غني',
                            'gallery' => 'معرض صور',
                            'cards' => 'بطاقات',
                            'video_section' => 'فيديو',
                            'table' => 'جدول بيانات',
                            'buttons' => 'أزرار',
                            'stats' => 'إحصائيات',
                            'hero' => 'بانر رئيسي',
                            'text_with_image' => 'نص + صورة',
                            'divider' => 'فاصل',
                            'mixed' => 'مختلط',
                        ];
                    @endphp
                    @foreach($sections as $section)
                        <div class="section-card-item {{ !$section->is_active ? 'inactive' : '' }}" data-id="{{ $section->id }}">
                            <div class="section-header-row">
                                <i class="fas fa-grip-vertical drag-handle" title="اسحب لتغيير الترتيب"></i>
                                <div class="order-badge">{{ $section->display_order }}</div>

                                <div class="section-info">
                                    <div class="d-flex align-items-center flex-wrap" style="gap: 8px; margin-bottom: 4px;">
                                        <h6 class="section-title mb-0">{{ $section->title }}</h6>
                                        <span class="badge badge-primary px-2 py-1" style="font-size: 0.72rem;">
                                            {{ $typeNames[$section->section_type] ?? $section->section_type }}
                                        </span>
                                        <span class="badge badge-{{ $section->is_active ? 'success' : 'secondary' }}" style="font-size: 0.72rem;">
                                            {{ $section->is_active ? 'نشط' : 'معطل' }}
                                        </span>
                                    </div>
                                    <div class="d-flex align-items-center flex-wrap text-muted small" style="gap: 14px;">
                                        <span><i class="fas fa-cubes mr-1 text-primary"></i>{{ $section->items->count() }} عنصر</span>
                                        @if(isset($section->settings['subtitle']) && $section->settings['subtitle'])
                                            <span><i class="fas fa-font mr-1"></i>{{ Str::limit($section->settings['subtitle'], 35) }}</span>
                                        @endif
                                        <span><i class="fas fa-clock mr-1"></i>{{ $section->updated_at->diffForHumans() }}</span>
                                    </div>
                                </div>

                                <div class="section-actions">
                                    <button type="button" class="btn btn-outline-info" title="معاينة القسم"
                                            onclick="openSectionPreview({{ $section->id }}, '{{ addslashes($section->title) }}')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <a href="{{ route('dashboard.home-sections.edit', $section) }}" 
                                       class="btn btn-outline-primary" title="تعديل وبناء المحتوى">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form action="{{ route('dashboard.home-sections.toggle', $section) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-{{ $section->is_active ? 'warning' : 'success' }}" 
                                                title="{{ $section->is_active ? 'تعطيل العرض' : 'تفعيل العرض' }}">
                                            <i class="fas fa-{{ $section->is_active ? 'pause' : 'play' }}"></i>
                                        </button>
                                    </form>
                                    <form action="{{ route('dashboard.home-sections.duplicate', $section) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-secondary" title="نسخ القسم">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </form>
                                    <form action="{{ route('dashboard.home-sections.destroy', $section) }}" method="POST" class="d-inline"
                                          onsubmit="return confirm('هل أنت متأكد من حذف القسم؟ سيتم حذف جميع عناصره نهائياً.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger" title="حذف نهائي">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @else
        <div class="card shadow-sm border-0" style="border-radius: 16px;">
            <div class="card-body text-center py-5">
                <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                    <i class="fas fa-magic fa-2x text-primary"></i>
                </div>
                <h4 class="font-weight-bold mb-2">لا توجد أقسام حتى الآن</h4>
                <p class="text-muted mb-4">أنشئ أول قسم تفاعلي للصفحة الرئيسية بضغطة زر واحدة</p>
                <a href="{{ route('dashboard.home-sections.create') }}" class="btn btn-primary px-4 py-2 font-weight-bold" style="border-radius: 12px;">
                    <i class="fas fa-plus-circle mr-2"></i>إنشاء أول قسم
                </a>
            </div>
        </div>
    @endif
</div>

<!-- Save Order Bottom Bar -->
<div class="save-order-bar" id="saveOrderBar">
    <div class="d-flex align-items-center" style="gap: 12px;">
        <i class="fas fa-arrows-alt fa-lg"></i>
        <span class="font-weight-bold">تم تغيير ترتيب الأقسام. هل تريد حفظ الترتيب الجديد؟</span>
    </div>
    <div class="d-flex align-items-center" style="gap: 8px;">
        <button type="button" class="btn btn-light btn-sm font-weight-bold px-3" onclick="saveSectionsOrder()" style="border-radius: 8px;">
            <i class="fas fa-save mr-1"></i>حفظ الترتيب
        </button>
        <button type="button" class="btn btn-outline-light btn-sm" onclick="location.reload()" style="border-radius: 8px;">
            تراجع
        </button>
    </div>
</div>

<!-- Preview Modal -->
<div class="modal fade" id="indexPreviewModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" style="max-width: 95vw;">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px; overflow: hidden; height: 90vh;">
            <div class="modal-header py-3 px-4 bg-dark text-white d-flex align-items-center justify-content-between">
                <h5 class="modal-title font-weight-bold mb-0" id="indexPreviewTitle">معاينة القسم</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body p-0 bg-light">
                <iframe id="indexPreviewIframe" src="about:blank" style="width: 100%; height: 100%; border: none;"></iframe>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script>
    const sortableList = document.getElementById('sections-sortable-list');
    if (sortableList) {
        new Sortable(sortableList, {
            handle: '.drag-handle',
            animation: 250,
            ghostClass: 'sortable-ghost',
            onEnd: function() {
                document.getElementById('saveOrderBar').style.display = 'flex';
                // Update badge numbers
                document.querySelectorAll('#sections-sortable-list .section-card-item').forEach((card, idx) => {
                    const badge = card.querySelector('.order-badge');
                    if (badge) badge.textContent = idx + 1;
                });
            }
        });
    }

    function saveSectionsOrder() {
        const items = document.querySelectorAll('#sections-sortable-list [data-id]');
        const orders = Array.from(items).map(item => item.dataset.id);

        fetch('{{ route("dashboard.home-sections.reorder") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ orders: orders })
        })
        .then(r => r.json())
        .then(data => {
            if (data && data.success) {
                document.getElementById('saveOrderBar').style.display = 'none';
                const toast = document.createElement('div');
                toast.style.cssText = 'position:fixed;bottom:24px;left:24px;background:#10b981;color:#fff;padding:12px 24px;border-radius:12px;z-index:9999;font-weight:700;box-shadow:0 8px 20px rgba(16,185,129,0.3);';
                toast.innerHTML = '<i class="fas fa-check-circle mr-2"></i>تم حفظ الترتيب الجديد بنجاح';
                document.body.appendChild(toast);
                setTimeout(() => toast.remove(), 2500);
            }
        })
        .catch(err => {
            alert('حدث خطأ أثناء حفظ الترتيب');
        });
    }

    function openSectionPreview(sectionId, title) {
        document.getElementById('indexPreviewTitle').innerHTML = '<i class="fas fa-eye text-primary mr-2"></i>معاينة القسم: ' + title;
        document.getElementById('indexPreviewIframe').src = '/dashboard/home-sections/' + sectionId + '/preview?t=' + Date.now();
        $('#indexPreviewModal').modal('show');
    }
</script>
@endpush
