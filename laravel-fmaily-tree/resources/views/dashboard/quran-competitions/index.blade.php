@extends('layouts.app')

@section('title', 'إدارة مسابقات القرآن الكريم')

@push('styles')
<style>
    .quran-table-cover {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        object-fit: cover;
        box-shadow: 0 2px 6px rgba(0,0,0,0.08);
        border: 1px solid rgba(226, 232, 240, 0.8);
        flex-shrink: 0;
    }
    body.dark .quran-table-cover {
        border-color: rgba(51, 65, 85, 0.8);
    }
    .quran-table-placeholder {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: rgba(27, 104, 255, 0.08);
        color: #1b68ff;
        font-size: 1.2rem;
        flex-shrink: 0;
    }
    body.dark .quran-table-placeholder {
        background-color: rgba(59, 130, 246, 0.15);
        color: #60a5fa;
    }
</style>
@endpush

@section('content')
<div class="container-fluid py-4">
    {{-- Page Header --}}
    <x-dashboard.page-header
        title="إدارة مسابقات القرآن الكريم"
        description="استعراض ومتابعة مسابقات حفظ وتلاوة القرآن الكريم السنوية، إدارة قوائم الفائزين، الأقسام، ومعارض الوسائط التوثيقية.">
        <x-slot name="actions">
            <a href="{{ route('dashboard.quran-competitions.create') }}" class="btn btn-primary d-inline-flex align-items-center gap-2 px-3 py-2 shadow-sm" style="border-radius: 12px; font-weight: 700;">
                <i class="fe fe-plus"></i>
                <span>إضافة مسابقة جديدة</span>
            </a>
        </x-slot>
    </x-dashboard.page-header>

    {{-- Alerts --}}
    @include('components.alerts')

    {{-- Statistics Cards --}}
    <div class="row mb-4">
        <x-dashboard.stat-card
            icon="fe-award"
            title="إجمالي المسابقات"
            :value="$stats['total']"
            gradient="primary"
            description="كافة المسابقات المجدولة"
        />
        <x-dashboard.stat-card
            icon="fe-check-circle"
            title="المسابقات النشطة"
            :value="$stats['active']"
            gradient="success"
            description="متاحة للمشاهدة والتفاعل"
        />
        <x-dashboard.stat-card
            icon="fe-users"
            title="إجمالي الفائزين"
            :value="$stats['total_winners']"
            gradient="info"
            description="مكرّمون في كافة المواسم"
        />
        <x-dashboard.stat-card
            icon="fe-image"
            title="الوسائط التوثيقية"
            :value="$stats['total_media']"
            gradient="purple"
            description="صور، فيديوهات، وتغطيات"
        />
    </div>

    {{-- Main Content Table --}}
    <x-dashboard.card title="قائمة مسابقات القرآن الكريم" icon="fe-book-open">
        <x-slot name="headerAction">
            <span class="badge badge-light px-3 py-2 border font-weight-bold" style="border-radius: 10px; font-size: 0.82rem;">
                العدد الإجمالي: {{ $competitions->count() }} مسابقة
            </span>
        </x-slot>

        @if($competitions->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th>المسابقة</th>
                            <th>التصنيف</th>
                            <th>الموسم الهجري</th>
                            <th>الفترة الزمنية</th>
                            <th>الإحصائيات</th>
                            <th>الحالة</th>
                            <th style="width: 140px;" class="text-center">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($competitions as $index => $competition)
                            <tr>
                                <td>
                                    <span class="text-muted font-weight-bold">{{ $index + 1 }}</span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        @if($competition->cover_image)
                                            <img src="{{ $competition->cover_image_url }}" alt="{{ $competition->title }}" class="quran-table-cover">
                                        @else
                                            <div class="quran-table-placeholder">
                                                <i class="fe fe-book"></i>
                                            </div>
                                        @endif
                                        <div>
                                            <a href="{{ route('dashboard.quran-competitions.show', $competition) }}" class="font-weight-bold text-decoration-none d-block text-dark" style="font-size: 0.95rem;">
                                                {{ $competition->title }}
                                            </a>
                                            @if($competition->description)
                                                <small class="text-muted d-block mt-1">
                                                    {{ Str::limit(strip_tags($competition->description), 50) }}
                                                </small>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @if($competition->category)
                                        <span class="badge badge-light text-primary border px-2 py-1 font-weight-bold" style="border-radius: 8px;">
                                            <i class="fe fe-folder mr-1"></i>
                                            {{ $competition->category->name }}
                                        </span>
                                        @if($competition->category->parent)
                                            <small class="d-block text-muted mt-1">
                                                {{ $competition->category->parent->name }}
                                            </small>
                                        @endif
                                    @else
                                        <span class="badge badge-light text-muted border px-2 py-1" style="border-radius: 8px;">
                                            عام (بدون تصنيف)
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge badge-light border px-2 py-1 font-weight-bold" style="border-radius: 8px;">
                                        {{ $competition->hijri_year }} هـ
                                    </span>
                                </td>
                                <td>
                                    @if($competition->start_date || $competition->end_date)
                                        <div class="small">
                                            @if($competition->start_date)
                                                <div class="text-muted"><i class="fe fe-calendar mr-1"></i>{{ $competition->start_date->format('Y-m-d') }}</div>
                                            @endif
                                            @if($competition->end_date)
                                                <div class="text-muted mt-1"><i class="fe fe-arrow-left mr-1"></i>{{ $competition->end_date->format('Y-m-d') }}</div>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-muted small">— غير محددة —</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex flex-column gap-1">
                                        <span class="badge badge-light text-success border px-2 py-1 text-right" style="border-radius: 6px; font-size: 0.76rem;">
                                            <i class="fe fe-award mr-1"></i>
                                            {{ $competition->winners->count() }} فائز
                                        </span>
                                        <span class="badge badge-light text-info border px-2 py-1 text-right" style="border-radius: 6px; font-size: 0.76rem;">
                                            <i class="fe fe-image mr-1"></i>
                                            {{ $competition->media->count() }} وسائط
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    @if($competition->is_active)
                                        <span class="badge badge-success px-2 py-1 font-weight-bold" style="border-radius: 8px;">
                                            <i class="fe fe-check mr-1"></i>نشطة
                                        </span>
                                    @else
                                        <span class="badge badge-secondary px-2 py-1 font-weight-bold" style="border-radius: 8px;">
                                            <i class="fe fe-pause mr-1"></i>معطلة
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex align-items-center justify-content-center gap-1">
                                        <a href="{{ route('dashboard.quran-competitions.show', $competition) }}" class="btn btn-sm btn-outline-info p-2" title="عرض التفاصيل والفائزين" style="border-radius: 8px;">
                                            <i class="fe fe-eye"></i>
                                        </a>
                                        <a href="{{ route('dashboard.quran-competitions.edit', $competition) }}" class="btn btn-sm btn-outline-primary p-2" title="تعديل المسابقة" style="border-radius: 8px;">
                                            <i class="fe fe-edit-2"></i>
                                        </a>
                                        <form action="{{ route('dashboard.quran-competitions.destroy', $competition) }}" method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من حذف هذه المسابقة نهائياً؟');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger p-2" title="حذف" style="border-radius: 8px;">
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
                        <i class="fe fe-book-open" style="font-size: 2.2rem;"></i>
                    </span>
                </div>
                <h5 class="font-weight-bold text-dark mb-2">لا توجد مسابقات قرآن كريم مسجلة حالياً</h5>
                <p class="text-muted max-w-sm mx-auto mb-4">
                    ابدأ بإنشاء مسابقة سنوية لحفظ وتلاوة القرآن الكريم لأبناء وبنات العائلة وتحديد فئات التنافس.
                </p>
                <a href="{{ route('dashboard.quran-competitions.create') }}" class="btn btn-primary d-inline-flex align-items-center gap-2 px-4 py-2" style="border-radius: 12px; font-weight: 700;">
                    <i class="fe fe-plus"></i>
                    <span>إضافة مسابقة جديدة الآن</span>
                </a>
            </div>
        @endif
    </x-dashboard.card>
</div>
@endsection
