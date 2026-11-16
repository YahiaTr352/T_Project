@extends('layouts.app')

@section('title', 'تفاصيل مسابقة القرآن الكريم: ' . $quranCompetition->title)

@push('styles')
<style>
    .winner-rank-badge {
        font-weight: 700;
        font-size: 0.82rem;
        padding: 6px 14px;
        border-radius: 20px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .winner-rank-1 {
        background: linear-gradient(135deg, #fef3c7, #fde68a);
        color: #92400e;
        border: 1px solid #fcd34d;
    }
    .winner-rank-2 {
        background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
        color: #475569;
        border: 1px solid #cbd5e1;
    }
    .winner-rank-3 {
        background: linear-gradient(135deg, #ffedd5, #fed7aa);
        color: #9a3412;
        border: 1px solid #fdba74;
    }
    .winner-rank-other {
        background: #f8fafc;
        color: #64748b;
        border: 1px solid #e2e8f0;
    }
    body.dark .winner-rank-other {
        background: #1e293b;
        color: #94a3b8;
        border-color: #334155;
    }

    .media-card-preview {
        height: 180px;
        width: 100%;
        object-fit: cover;
        border-top-left-radius: 16px;
        border-top-right-radius: 16px;
    }
    .media-grid-card {
        border-radius: 16px;
        overflow: hidden;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        background: var(--dash-card-bg, #ffffff);
        border: 1px solid var(--dash-card-border, #e2e8f0);
    }
    .media-grid-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.08);
    }
    body.dark .media-grid-card:hover {
        box-shadow: 0 10px 20px rgba(0,0,0,0.35);
    }

    .section-box {
        border: 1px solid var(--dash-card-border, #e2e8f0);
        border-radius: 16px;
        background: var(--dash-card-bg, #ffffff);
        transition: all 0.2s ease;
    }
    .section-header-box {
        background-color: rgba(241, 245, 249, 0.6);
        border-bottom: 1px solid var(--dash-card-border, #e2e8f0);
        padding: 1rem 1.25rem;
        border-top-left-radius: 16px;
        border-top-right-radius: 16px;
    }
    body.dark .section-header-box {
        background-color: rgba(15, 23, 42, 0.4);
    }
</style>
@endpush

@section('content')
<div class="container-fluid py-4">
    {{-- Page Header --}}
    <x-dashboard.page-header
        :title="$quranCompetition->title"
        :description="'موسم سنة ' . $quranCompetition->hijri_year . ' هـ — إدارة الفائزين، اللجان التنظيمية، ومعارض الوسائط.'">
        <x-slot name="actions">
            <a href="{{ route('dashboard.quran-competitions.edit', $quranCompetition) }}" class="btn btn-primary d-inline-flex align-items-center gap-2 px-3 py-2 shadow-sm" style="border-radius: 12px; font-weight: 600;">
                <i class="fe fe-edit-2"></i>
                <span>تعديل المسابقة</span>
            </a>
            <a href="{{ route('dashboard.quran-competitions.index') }}" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2 px-3 py-2" style="border-radius: 12px; font-weight: 600;">
                <i class="fe fe-arrow-right"></i>
                <span>العودة للقائمة</span>
            </a>
        </x-slot>
    </x-dashboard.page-header>

    {{-- Alerts --}}
    @include('components.alerts')

    {{-- Competition Quick Glance --}}
    <div class="row mb-4">
        <div class="{{ $quranCompetition->cover_image ? 'col-lg-8' : 'col-12' }}">
            <x-dashboard.card title="بيانات المسابقة" icon="fe-info">
                <div class="row g-3">
                    <div class="col-sm-6 col-md-3 mb-3">
                        <span class="text-muted d-block small font-weight-bold">الموسم الهجري:</span>
                        <span class="font-weight-bold d-inline-block mt-1" style="font-size: 1.1rem;">
                            {{ $quranCompetition->hijri_year }} هـ
                        </span>
                    </div>

                    <div class="col-sm-6 col-md-3 mb-3">
                        <span class="text-muted d-block small font-weight-bold">حالة المسابقة:</span>
                        <div class="mt-1">
                            @if($quranCompetition->is_active)
                                <span class="badge badge-success px-2 py-1 font-weight-bold" style="border-radius: 6px;">
                                    <i class="fe fe-check-circle mr-1"></i>نشطة ومتاحة
                                </span>
                            @else
                                <span class="badge badge-secondary px-2 py-1 font-weight-bold" style="border-radius: 6px;">
                                    <i class="fe fe-pause-circle mr-1"></i>معطلة (مسودة)
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="col-sm-6 col-md-3 mb-3">
                        <span class="text-muted d-block small font-weight-bold">التصنيف / الفئة:</span>
                        <span class="font-weight-bold d-inline-block mt-1">
                            {{ $quranCompetition->category->name ?? 'عام (بدون تصنيف)' }}
                        </span>
                    </div>

                    <div class="col-sm-6 col-md-3 mb-3">
                        <span class="text-muted d-block small font-weight-bold">الفترة الزمنية:</span>
                        <div class="small mt-1 font-weight-bold">
                            @if($quranCompetition->start_date || $quranCompetition->end_date)
                                {{ $quranCompetition->start_date?->format('Y-m-d') ?? '—' }} إلى {{ $quranCompetition->end_date?->format('Y-m-d') ?? '—' }}
                            @else
                                <span class="text-muted font-weight-normal">غير محددة</span>
                            @endif
                        </div>
                    </div>
                </div>

                @if($quranCompetition->description)
                    <div class="border-top pt-3 mt-2">
                        <span class="text-muted d-block small font-weight-bold mb-2">الوصف والتعليمات:</span>
                        <div class="text-dark" style="line-height: 1.7;">
                            {!! nl2br(e($quranCompetition->description)) !!}
                        </div>
                    </div>
                @endif
            </x-dashboard.card>
        </div>

        @if($quranCompetition->cover_image)
            <div class="col-lg-4">
                <x-dashboard.card title="صورة الغلاف" icon="fe-image">
                    <div class="text-center">
                        <img
                            src="{{ $quranCompetition->cover_image_url }}"
                            alt="{{ $quranCompetition->title }}"
                            class="img-fluid rounded shadow-sm"
                            style="max-height: 180px; width: 100%; object-fit: cover; border-radius: 14px;"
                        >
                    </div>
                </x-dashboard.card>
            </div>
        @endif
    </div>

    {{-- Section 1: Winners --}}
    <x-dashboard.card title="سجل الفائزين والمكرمين" icon="fe-award" class="mb-4">
        <x-slot name="headerAction">
            <button type="button" class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1 shadow-sm px-3 py-2" style="border-radius: 10px; font-weight: 700;" data-toggle="modal" data-target="#addWinnerModal">
                <i class="fe fe-plus"></i>
                <span>إضافة فائز جديد</span>
            </button>
        </x-slot>

        @if($quranCompetition->winners->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width: 140px;">المركز / الرتبة</th>
                            <th>اسم الفائز</th>
                            <th>مسار / فئة المسابقة</th>
                            <th>ملاحظات</th>
                            <th style="width: 80px;" class="text-center">حذف</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($quranCompetition->winners->sortBy('position') as $winner)
                            <tr>
                                <td>
                                    @if($winner->position == 1)
                                        <span class="winner-rank-badge winner-rank-1">
                                            <i class="fe fe-award"></i> المركز الأول
                                        </span>
                                    @elseif($winner->position == 2)
                                        <span class="winner-rank-badge winner-rank-2">
                                            <i class="fe fe-award"></i> المركز الثاني
                                        </span>
                                    @elseif($winner->position == 3)
                                        <span class="winner-rank-badge winner-rank-3">
                                            <i class="fe fe-award"></i> المركز الثالث
                                        </span>
                                    @else
                                        <span class="winner-rank-badge winner-rank-other">
                                            المركز {{ $winner->position }}
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="{{ $winner->person->avatar }}" alt="{{ $winner->person->full_name }}" class="rounded-circle" style="width: 38px; height: 38px; object-fit: cover;">
                                        <div>
                                            <span class="font-weight-bold d-block text-dark">{{ $winner->person->full_name }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @if($winner->category)
                                        <span class="badge badge-light border text-primary px-2 py-1 font-weight-bold" style="border-radius: 6px;">
                                            {{ $winner->category }}
                                        </span>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="text-muted small">{{ $winner->notes ?: '—' }}</span>
                                </td>
                                <td class="text-center">
                                    <form action="{{ route('dashboard.quran-competitions.remove-winner', $winner) }}" method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من إزالة هذا الفائز من السجل؟');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger p-2" title="إزالة الفائز" style="border-radius: 8px;">
                                            <i class="fe fe-trash-2"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-center py-4">
                <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-light text-muted mb-3" style="width: 60px; height: 60px;">
                    <i class="fe fe-award" style="font-size: 1.6rem;"></i>
                </span>
                <p class="text-muted mb-3">لم يتم تسجيل فائزين في هذه المسابقة حتى الآن.</p>
                <button type="button" class="btn btn-sm btn-outline-primary px-3 py-2" style="border-radius: 10px;" data-toggle="modal" data-target="#addWinnerModal">
                    <i class="fe fe-plus mr-1"></i> إضافة أول فائز
                </button>
            </div>
        @endif
    </x-dashboard.card>

    {{-- Section 2: Sections & Organizing Committees --}}
    <x-dashboard.card title="الأقسام واللجان الإشرافية" icon="fe-layers" class="mb-4">
        <x-slot name="headerAction">
            <button type="button" class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1 shadow-sm px-3 py-2" style="border-radius: 10px; font-weight: 700;" data-toggle="modal" data-target="#addSectionModal">
                <i class="fe fe-plus"></i>
                <span>إضافة قسم جديد</span>
            </button>
        </x-slot>

        @if($quranCompetition->sections->count() > 0)
            <div class="d-flex flex-column gap-4">
                @foreach($quranCompetition->sections->sortBy('sort_order') as $section)
                    <div class="section-box shadow-sm mb-3">
                        <div class="section-header-box d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div>
                                <h6 class="font-weight-bold mb-1 text-dark d-flex align-items-center gap-2">
                                    <i class="fe fe-folder text-primary"></i>
                                    <span>{{ $section->name }}</span>
                                </h6>
                                <small class="text-muted">
                                    أولوية الترتيب: {{ $section->sort_order }} — الأعضاء المشاركون: {{ $section->people->count() }}
                                </small>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <button type="button" class="btn btn-sm btn-outline-success d-inline-flex align-items-center gap-1" style="border-radius: 8px;" data-toggle="modal" data-target="#attachPeopleModal-{{ $section->id }}">
                                    <i class="fe fe-user-plus"></i>
                                    <span>إضافة أعضاء</span>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-primary p-2" style="border-radius: 8px;" title="تعديل اسم القسم" data-toggle="modal" data-target="#editSectionModal-{{ $section->id }}">
                                    <i class="fe fe-edit-2"></i>
                                </button>
                                <form action="{{ route('dashboard.quran-competitions.sections.destroy', $section) }}" method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من حذف هذا القسم وكل ما يرتبط به؟');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger p-2" style="border-radius: 8px;" title="حذف القسم">
                                        <i class="fe fe-trash-2"></i>
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div class="p-3">
                            @if($section->people->count() > 0)
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th style="width: 50px;">الصورة</th>
                                                <th>الاسم</th>
                                                <th>الترتيب الداخلي</th>
                                                <th style="width: 80px;" class="text-center">إزالة</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($section->people as $person)
                                                <tr>
                                                    <td>
                                                        <img src="{{ $person->avatar }}" alt="{{ $person->full_name }}" class="rounded-circle" style="width: 32px; height: 32px; object-fit: cover;">
                                                    </td>
                                                    <td>
                                                        <span class="font-weight-bold text-dark">{{ $person->full_name }}</span>
                                                    </td>
                                                    <td>
                                                        <span class="badge badge-light border px-2 py-1 small">
                                                            {{ $person->pivot->sort_order ?? 0 }}
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <form action="{{ route('dashboard.quran-competitions.sections.detach-person', [$section, $person]) }}" method="POST" class="d-inline" onsubmit="return confirm('إزالة هذا الشخص من القسم؟');">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-sm btn-outline-danger p-1" style="border-radius: 6px;" title="إزالة من القسم">
                                                                <i class="fe fe-x"></i>
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="text-center text-muted py-3 small">
                                    لا يوجد أعضاء في هذا القسم بعد. اضغط على زر "إضافة أعضاء" لربط أفراد العائلة بالقسم.
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Edit Section Modal --}}
                    <div class="modal fade" id="editSectionModal-{{ $section->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <form action="{{ route('dashboard.quran-competitions.sections.update', $section) }}" method="POST" class="modal-content shadow-lg border-0" style="border-radius: 20px;">
                                @csrf
                                @method('PUT')
                                <div class="modal-header border-bottom p-4">
                                    <h5 class="modal-title font-weight-bold d-flex align-items-center gap-2 mb-0">
                                        <i class="fe fe-edit-2 text-primary"></i>
                                        <span>تعديل القسم</span>
                                    </h5>
                                    <button type="button" class="close text-muted" data-dismiss="modal"><span>&times;</span></button>
                                </div>
                                <div class="modal-body p-4">
                                    <div class="form-group mb-3">
                                        <label class="font-weight-bold">اسم القسم <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="name" value="{{ $section->name }}" required style="border-radius: 10px;">
                                    </div>
                                    <div class="form-group mb-0">
                                        <label class="font-weight-bold">ترتيب العرض</label>
                                        <input type="number" class="form-control" name="sort_order" value="{{ $section->sort_order }}" min="0" style="border-radius: 10px;">
                                    </div>
                                </div>
                                <div class="modal-footer border-top p-3 d-flex justify-content-between">
                                    <button type="button" class="btn btn-outline-secondary px-3 py-2" data-dismiss="modal" style="border-radius: 10px;">إلغاء</button>
                                    <button type="submit" class="btn btn-primary px-4 py-2" style="border-radius: 10px; font-weight: 700;">حفظ التغييرات</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    {{-- Attach People Modal --}}
                    <div class="modal fade section-people-modal" id="attachPeopleModal-{{ $section->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <form action="{{ route('dashboard.quran-competitions.sections.attach-people', $section) }}" method="POST" class="modal-content shadow-lg border-0" style="border-radius: 20px;">
                                @csrf
                                <div class="modal-header border-bottom p-4">
                                    <h5 class="modal-title font-weight-bold d-flex align-items-center gap-2 mb-0">
                                        <i class="fe fe-user-plus text-success"></i>
                                        <span>إضافة أعضاء إلى: {{ $section->name }}</span>
                                    </h5>
                                    <button type="button" class="close text-muted" data-dismiss="modal"><span>&times;</span></button>
                                </div>
                                <div class="modal-body p-4">
                                    <div class="form-group mb-0">
                                        <label class="font-weight-bold">اختر الأعضاء <span class="text-danger">*</span></label>
                                        <select
                                            class="form-control no-search js-section-people"
                                            name="people[]"
                                            multiple
                                            data-url="{{ route('persons.search') }}"
                                            data-placeholder="ابحث بالاسم واختر عدة أشخاص..."
                                            style="width: 100%; border-radius: 10px;"
                                        ></select>
                                        <small class="form-text text-muted mt-2">
                                            اكتب حرفين على الأقل للبحث السريع في شجرة العائلة ثم اختر عدة أشخاص.
                                        </small>
                                        <div class="mt-2 small text-primary font-weight-bold js-selected-preview"></div>
                                    </div>
                                </div>
                                <div class="modal-footer border-top p-3 d-flex justify-content-between">
                                    <button type="button" class="btn btn-outline-secondary px-3 py-2" data-dismiss="modal" style="border-radius: 10px;">إلغاء</button>
                                    <button type="submit" class="btn btn-success d-inline-flex align-items-center gap-2 px-4 py-2" style="border-radius: 10px; font-weight: 700;">
                                        <i class="fe fe-plus"></i>
                                        <span>إضافة المحددين</span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-4">
                <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-light text-muted mb-3" style="width: 60px; height: 60px;">
                    <i class="fe fe-layers" style="font-size: 1.6rem;"></i>
                </span>
                <p class="text-muted mb-3">لم يتم إنشاء أقسام أو لجان تنظيمية لهذه المسابقة بعد.</p>
                <button type="button" class="btn btn-sm btn-outline-primary px-3 py-2" style="border-radius: 10px;" data-toggle="modal" data-target="#addSectionModal">
                    <i class="fe fe-plus mr-1"></i> إضافة أول قسم
                </button>
            </div>
        @endif
    </x-dashboard.card>

    {{-- Section 3: Media Gallery --}}
    <x-dashboard.card title="معرض الوسائط والتوثيق" icon="fe-image">
        <x-slot name="headerAction">
            <button type="button" class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1 shadow-sm px-3 py-2" style="border-radius: 10px; font-weight: 700;" data-toggle="modal" data-target="#addMediaModal">
                <i class="fe fe-plus"></i>
                <span>إضافة وسائط جديدة</span>
            </button>
        </x-slot>

        @if($quranCompetition->media->count() > 0)
            <div class="row">
                @foreach($quranCompetition->media->sortBy('sort_order') as $media)
                    <div class="col-sm-6 col-md-4 col-xl-3 mb-4">
                        <div class="media-grid-card h-100 d-flex flex-column shadow-sm">
                            <div class="position-relative">
                                @if($media->media_type === 'youtube')
                                    <img src="{{ $media->youtube_thumbnail }}" alt="{{ $media->caption }}" class="media-card-preview">
                                    <div class="position-absolute" style="top: 50%; left: 50%; transform: translate(-50%, -50%);">
                                        <span class="badge badge-danger p-2 rounded-circle shadow-lg" style="font-size: 1.2rem; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center;">
                                            <i class="fe fe-play" style="margin-right: -2px;"></i>
                                        </span>
                                    </div>
                                @elseif($media->media_type === 'video')
                                    <video class="media-card-preview" controls>
                                        <source src="{{ $media->file_url }}" type="video/mp4">
                                    </video>
                                @else
                                    <img src="{{ $media->file_url }}" alt="{{ $media->caption }}" class="media-card-preview">
                                @endif
                                <div class="position-absolute" style="top: 10px; right: 10px;">
                                    <span class="badge badge-dark px-2 py-1" style="border-radius: 6px; font-size: 0.72rem; opacity: 0.9;">
                                        @if($media->media_type === 'youtube')
                                            <i class="fe fe-video mr-1"></i> يوتيوب
                                        @elseif($media->media_type === 'video')
                                            <i class="fe fe-film mr-1"></i> فيديو
                                        @else
                                            <i class="fe fe-image mr-1"></i> صورة
                                        @endif
                                    </span>
                                </div>
                            </div>
                            <div class="p-3 d-flex flex-column flex-grow-1">
                                <p class="small text-dark font-weight-bold mb-2 flex-grow-1" style="min-height: 38px;">
                                    {{ $media->caption ?: 'بدون وصف' }}
                                </p>
                                <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                                    <small class="text-muted">ترتيب: {{ $media->sort_order }}</small>
                                    <form action="{{ route('dashboard.quran-competitions.remove-media', $media) }}" method="POST" onsubmit="return confirm('هل أنت متأكد من حذف هذه المادة الإعلامية؟');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger p-1" style="border-radius: 6px;" title="حذف المادة">
                                            <i class="fe fe-trash-2"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-4">
                <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-light text-muted mb-3" style="width: 60px; height: 60px;">
                    <i class="fe fe-image" style="font-size: 1.6rem;"></i>
                </span>
                <p class="text-muted mb-3">لا توجد وسائط توثيقية مرفوعة لهذه المسابقة بعد.</p>
                <button type="button" class="btn btn-sm btn-outline-primary px-3 py-2" style="border-radius: 10px;" data-toggle="modal" data-target="#addMediaModal">
                    <i class="fe fe-plus mr-1"></i> إضافة وسائط
                </button>
            </div>
        @endif
    </x-dashboard.card>
</div>

{{-- Add Winner Modal --}}
<div class="modal fade" id="addWinnerModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="{{ route('dashboard.quran-competitions.add-winner', $quranCompetition) }}" method="POST" class="modal-content shadow-lg border-0" style="border-radius: 20px;">
            @csrf
            <div class="modal-header border-bottom p-4">
                <h5 class="modal-title font-weight-bold d-flex align-items-center gap-2 mb-0">
                    <i class="fe fe-award text-primary"></i>
                    <span>إضافة فائز جديد</span>
                </h5>
                <button type="button" class="close text-muted" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body p-4">
                <div class="form-group mb-3">
                    <label for="person_id" class="font-weight-bold">الشخص المكرم <span class="text-danger">*</span></label>
                    <select class="form-control select2" id="person_id" name="person_id" required style="width: 100%;">
                        <option value="">— اختر الشخص —</option>
                        @foreach($persons as $person)
                            <option value="{{ $person->id }}">{{ $person->full_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group mb-3">
                    <label for="position" class="font-weight-bold">المركز / الترتيب <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" id="position" name="position" min="1" required placeholder="مثال: 1 للمركز الأول، 2 للمركز الثاني..." style="border-radius: 10px;">
                </div>
                <div class="form-group mb-3">
                    <label for="category" class="font-weight-bold">المسار أو الفئة</label>
                    <input type="text" class="form-control" id="category" name="category" placeholder="مثال: حفظ القرآن كاملاً، حفظ 5 أجزاء..." style="border-radius: 10px;">
                </div>
                <div class="form-group mb-0">
                    <label for="notes" class="font-weight-bold">ملاحظات إضافية</label>
                    <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="ملاحظات حول التكريم أو التقدير..." style="border-radius: 10px;"></textarea>
                </div>
            </div>
            <div class="modal-footer border-top p-3 d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary px-3 py-2" data-dismiss="modal" style="border-radius: 10px;">إلغاء</button>
                <button type="submit" class="btn btn-primary px-4 py-2" style="border-radius: 10px; font-weight: 700;">إضافة الفائز</button>
            </div>
        </form>
    </div>
</div>

{{-- Add Section Modal --}}
<div class="modal fade" id="addSectionModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="{{ route('dashboard.quran-competitions.sections.store', $quranCompetition) }}" method="POST" class="modal-content shadow-lg border-0" style="border-radius: 20px;">
            @csrf
            <div class="modal-header border-bottom p-4">
                <h5 class="modal-title font-weight-bold d-flex align-items-center gap-2 mb-0">
                    <i class="fe fe-plus-circle text-primary"></i>
                    <span>إضافة قسم أو لجنة جديدة</span>
                </h5>
                <button type="button" class="close text-muted" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body p-4">
                <div class="form-group mb-3">
                    <label class="font-weight-bold">اسم القسم أو اللجنة <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="name" placeholder="مثال: لجنة التحكيم، اللجنة التنظيمية..." required style="border-radius: 10px;">
                </div>
                <div class="form-group mb-0">
                    <label class="font-weight-bold">ترتيب العرض</label>
                    <input type="number" class="form-control" name="sort_order" value="0" min="0" style="border-radius: 10px;">
                </div>
            </div>
            <div class="modal-footer border-top p-3 d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary px-3 py-2" data-dismiss="modal" style="border-radius: 10px;">إلغاء</button>
                <button type="submit" class="btn btn-primary px-4 py-2" style="border-radius: 10px; font-weight: 700;">حفظ القسم</button>
            </div>
        </form>
    </div>
</div>

{{-- Add Media Modal --}}
<div class="modal fade" id="addMediaModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="{{ route('dashboard.quran-competitions.add-media', $quranCompetition) }}" method="POST" enctype="multipart/form-data" class="modal-content shadow-lg border-0" style="border-radius: 20px;">
            @csrf
            <div class="modal-header border-bottom p-4">
                <h5 class="modal-title font-weight-bold d-flex align-items-center gap-2 mb-0">
                    <i class="fe fe-image text-primary"></i>
                    <span>إضافة مادة إعلامية</span>
                </h5>
                <button type="button" class="close text-muted" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body p-4">
                <div class="form-group mb-3">
                    <label for="media_type" class="font-weight-bold">نوع الوسائط <span class="text-danger">*</span></label>
                    <select class="form-control" id="media_type" name="media_type" required onchange="toggleQuranMediaInputs()" style="border-radius: 10px;">
                        <option value="image">صورة فوتوغرافية</option>
                        <option value="video">ملف فيديو مباشر</option>
                        <option value="youtube">رابط يوتيوب (تضمين)</option>
                    </select>
                </div>
                <div class="form-group mb-3" id="fileInputGroup">
                    <label for="file" class="font-weight-bold">الملف <span class="text-danger">*</span></label>
                    <input type="file" class="form-control-file" id="file" name="file" accept="image/*,video/*">
                    <small class="form-text text-muted">الحد الأقصى: 10 ميجابايت (صور أو فيديو MP4/MOV).</small>
                </div>
                <div class="form-group mb-3" id="youtubeInputGroup" style="display: none;">
                    <label for="youtube_url" class="font-weight-bold">رابط يوتيوب <span class="text-danger">*</span></label>
                    <input type="url" class="form-control" id="youtube_url" name="youtube_url" placeholder="https://www.youtube.com/watch?v=..." style="border-radius: 10px;">
                </div>
                <div class="form-group mb-3">
                    <label for="caption" class="font-weight-bold">الوصف أو العنوان التوضيحي</label>
                    <input type="text" class="form-control" id="caption" name="caption" placeholder="وصف موجز للمادة الإعلامية..." style="border-radius: 10px;">
                </div>
                <div class="form-group mb-0">
                    <label for="sort_order" class="font-weight-bold">أولوية العرض</label>
                    <input type="number" class="form-control" id="sort_order" name="sort_order" value="0" min="0" style="border-radius: 10px;">
                </div>
            </div>
            <div class="modal-footer border-top p-3 d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary px-3 py-2" data-dismiss="modal" style="border-radius: 10px;">إلغاء</button>
                <button type="submit" class="btn btn-primary px-4 py-2" style="border-radius: 10px; font-weight: 700;">رفع وإضافة</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    function toggleQuranMediaInputs() {
        const mediaType = document.getElementById('media_type').value;
        const fileGroup = document.getElementById('fileInputGroup');
        const ytGroup   = document.getElementById('youtubeInputGroup');
        const fileInput = document.getElementById('file');
        const ytInput   = document.getElementById('youtube_url');

        if (mediaType === 'youtube') {
            fileGroup.style.display = 'none';
            ytGroup.style.display   = 'block';
            fileInput.removeAttribute('required');
            ytInput.setAttribute('required', 'required');
        } else {
            fileGroup.style.display = 'block';
            ytGroup.style.display   = 'none';
            fileInput.setAttribute('required', 'required');
            ytInput.removeAttribute('required');
        }
    }

    function initSectionPeopleSelect(modalEl) {
        const $modal = $(modalEl);
        const $select = $modal.find('select.js-section-people');
        if (!$select.length || $select.data('sectionPeopleInit')) return;
        $select.data('sectionPeopleInit', true);

        const url = $select.data('url');
        const placeholder = $select.data('placeholder') || 'ابحث بالاسم...';
        const $preview = $modal.find('.js-selected-preview');

        $select.select2({
            theme: 'bootstrap4',
            language: 'ar',
            dir: 'rtl',
            width: '100%',
            placeholder: placeholder,
            allowClear: true,
            closeOnSelect: false,
            minimumInputLength: 2,
            dropdownParent: $modal,
            ajax: {
                url: url,
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        term: params.term || '',
                        include_outside: 1
                    };
                },
                processResults: function(data) {
                    return data;
                },
                cache: true
            }
        });

        function renderSelectedPreview() {
            const data = $select.select2('data') || [];
            const names = data.map(x => x.text).filter(Boolean);
            $preview.text(names.length ? ('تم اختيار: ' + names.join('، ')) : '');
        }

        $select.on('change', renderSelectedPreview);
    }

    $(function() {
        $('.section-people-modal').on('shown.bs.modal', function() {
            initSectionPeopleSelect(this);
        });

        $('#addWinnerModal').on('shown.bs.modal', function () {
            $('#person_id').select2({
                theme: 'bootstrap4',
                dropdownParent: $('#addWinnerModal'),
                dir: 'rtl',
                width: '100%'
            });
        });
    });
</script>
@endpush
@endsection
