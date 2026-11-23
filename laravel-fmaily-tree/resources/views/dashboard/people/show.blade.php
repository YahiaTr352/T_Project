@extends('layouts.app')

@section('title', 'ملف: ' . $person->full_name)

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/dashboard/people.css') }}?v={{ file_exists(public_path('css/dashboard/people.css')) ? filemtime(public_path('css/dashboard/people.css')) : time() }}">
@endpush

@section('content')
    @include('components.person-select-init')

    <div class="container-fluid py-2">
        {{-- شريط مسار التنقل --}}
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb bg-transparent p-0 mb-2" style="font-size: 0.95rem;">
                <li class="breadcrumb-item">
                    <a href="{{ route('dashboard') }}" class="text-muted"><i class="fas fa-home ml-1"></i> لوحة التحكم</a>
                </li>
                <li class="breadcrumb-item">
                    <a href="{{ route('people.index') }}" class="text-muted">سجل الأفراد</a>
                </li>
                <li class="breadcrumb-item active font-weight-bold text-primary" aria-current="page">
                    {{ $person->full_name }}
                </li>
            </ol>
        </nav>

        @include('components.alerts')

        {{-- ====================================================================
             1. بطاقة الهوية والبروفايل الرئيسية (HERO PROFILE CARD)
             ==================================================================== --}}
        <div class="ppl-hero-card">
            <div class="ppl-hero-banner"></div>
            <div class="ppl-hero-body">
                <div class="d-flex flex-column flex-md-row align-items-center align-items-md-end justify-content-between gap-3">
                    {{-- الصورة والاسم --}}
                    <div class="d-flex flex-column flex-md-row align-items-center align-items-md-end gap-3 text-center text-md-right">
                        <div class="ppl-avatar-wrapper">
                            <img src="{{ $person->avatar }}" alt="{{ $person->full_name }}" class="ppl-avatar-img">
                        </div>
                        <div>
                            <h1 class="ppl-hero-title mb-1">{{ $person->full_name }}</h1>
                            <div class="ppl-hero-lineage">
                                <i class="fas fa-sitemap ml-1 text-primary"></i>
                                {{ $person->buildFullName(true) }}
                            </div>
                            <div class="d-flex flex-wrap align-items-center justify-content-center justify-content-md-start gap-2">
                                {{-- شارة الجنس --}}
                                <span class="ppl-badge {{ $person->gender == 'male' ? 'ppl-badge-male' : 'ppl-badge-female' }}">
                                    <i class="fas {{ $person->gender == 'male' ? 'fa-mars' : 'fa-venus' }}"></i>
                                    {{ $person->gender == 'male' ? 'ذكر' : 'أنثى' }}
                                </span>

                                {{-- شارة الانتماء --}}
                                @if(!$person->from_outside_the_family)
                                    <span class="ppl-badge ppl-badge-inside">
                                        <i class="fas fa-home"></i> من آل السريّع
                                    </span>
                                @else
                                    <span class="ppl-badge ppl-badge-outside">
                                        <i class="fas fa-handshake"></i> مصاهرة (من خارج العائلة)
                                    </span>
                                @endif

                                {{-- شارة الحياة / الوفاة --}}
                                @if($person->death_date)
                                    <span class="ppl-badge ppl-badge-deceased">
                                        <i class="fas fa-dove"></i> رحمه الله (توفي: {{ $person->death_date->format('Y') }})
                                    </span>
                                @else
                                    <span class="ppl-badge ppl-badge-alive">
                                        <i class="fas fa-heart"></i> على قيد الحياة حفظه الله
                                        @if($person->age)
                                            ({{ $person->age }} سنة)
                                        @endif
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- شريط الأزرار السريعة --}}
                    <div class="d-flex flex-wrap gap-2 mt-3 mt-md-0">
                        <a href="{{ route('people.edit', $person->id) }}" class="btn btn-primary ppl-btn-action shadow-sm">
                            <i class="fas fa-edit"></i>
                            <span>تعديل البيانات</span>
                        </a>
                        <a href="{{ route('family-tree') }}" class="btn btn-outline-secondary ppl-btn-action" target="_blank" title="مشاهدة في شجرة العائلة">
                            <i class="fas fa-tree"></i>
                            <span>شجرة العائلة</span>
                        </a>
                        @if ($person->photo_url)
                            <form action="{{ route('people.removePhoto', $person->id) }}" method="POST"
                                  onsubmit="return confirm('هل أنت متأكد من حذف الصورة الشخصية؟');" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger ppl-btn-action" title="حذف الصورة الشخصية">
                                    <i class="fas fa-trash-alt"></i>
                                    <span>حذف الصورة</span>
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- ====================================================================
             2. تبويبات التنقل العريضة المريحة لكبار السن
             ==================================================================== --}}
        <div class="card shadow-sm border-0 mb-4" style="border-radius: 16px;">
            <div class="card-header bg-transparent border-0 p-3 pb-0">
                <ul class="nav nav-pills flex-column flex-sm-row gap-2" id="profileTab" role="tablist">
                    <li class="nav-item flex-sm-fill text-center" role="presentation">
                        <a class="nav-link active font-weight-bold py-3 px-3 rounded-lg" id="overview-tab" data-toggle="pill" href="#overview" role="tab" style="font-size: 1.05rem;">
                            <i class="fas fa-id-card ml-2"></i>
                            <span>البيانات الأساسية والولادة</span>
                        </a>
                    </li>
                    <li class="nav-item flex-sm-fill text-center" role="presentation">
                        <a class="nav-link font-weight-bold py-3 px-3 rounded-lg" id="lineage-tab" data-toggle="pill" href="#lineage" role="tab" style="font-size: 1.05rem;">
                            <i class="fas fa-user-friends ml-2"></i>
                            <span>الوالدان والأزواج ({{ $marriages->count() }})</span>
                        </a>
                    </li>
                    <li class="nav-item flex-sm-fill text-center" role="presentation">
                        <a class="nav-link font-weight-bold py-3 px-3 rounded-lg" id="children-tab" data-toggle="pill" href="#children" role="tab" style="font-size: 1.05rem;">
                            <i class="fas fa-child ml-2"></i>
                            <span>الأبناء والذرية ({{ $children->count() }})</span>
                        </a>
                    </li>
                    <li class="nav-item flex-sm-fill text-center" role="presentation">
                        <a class="nav-link font-weight-bold py-3 px-3 rounded-lg" id="contacts-tab" data-toggle="pill" href="#contacts" role="tab" style="font-size: 1.05rem;">
                            <i class="fas fa-map-marked-alt ml-2"></i>
                            <span>المواقع والتواصل ({{ $person->contactAccounts->count() + $person->locations->count() }})</span>
                        </a>
                    </li>
                    <li class="nav-item flex-sm-fill text-center" role="presentation">
                        <a class="nav-link font-weight-bold py-3 px-3 rounded-lg" id="breastfeeding-tab" data-toggle="pill" href="#breastfeeding" role="tab" style="font-size: 1.05rem;">
                            <i class="fas fa-baby ml-2"></i>
                            <span>علاقات الرضاعة</span>
                        </a>
                    </li>
                </ul>
            </div>

            <div class="card-body p-4">
                <div class="tab-content" id="profileTabContent">

                    {{-- ----------------------------------------------------
                         التبويب الأول: البيانات الأساسية والولادة
                         ---------------------------------------------------- --}}
                    <div class="tab-pane fade show active" id="overview" role="tabpanel">
                        <h4 class="ppl-text-title font-weight-bold mb-4 d-flex align-items-center">
                            <i class="fas fa-info-circle text-primary ml-2"></i>
                            <span>تفاصيل الهوية والولادة</span>
                        </h4>

                        <div class="ppl-info-grid mb-4">
                            {{-- تاريخ الميلاد --}}
                            <div class="ppl-info-item">
                                <span class="ppl-info-label">
                                    <i class="fas fa-calendar-alt text-primary"></i> تاريخ الميلاد
                                </span>
                                @if ($person->birth_date)
                                    <div class="ppl-info-value">{{ format_gregorian_in_arabic($person->birth_date) }}</div>
                                    <span class="ppl-badge ppl-badge-hijri mt-1">
                                        {{ gregorian_to_hijri($person->birth_date) }}
                                    </span>
                                @else
                                    <div class="ppl-info-value text-muted font-weight-normal">غير مسجل</div>
                                @endif
                            </div>

                            {{-- العمر وفترة الحياة --}}
                            <div class="ppl-info-item">
                                <span class="ppl-info-label">
                                    <i class="fas fa-hourglass-half text-info"></i> العمر الحسابي
                                </span>
                                <div class="ppl-info-value">
                                    @if($person->age)
                                        {{ $person->age }} سنة
                                    @else
                                        <span class="text-muted font-weight-normal">غير محدد</span>
                                    @endif
                                </div>
                                @if($person->life_span)
                                    <small class="text-muted mt-1 font-weight-bold">فترة الحياة: {{ $person->life_span }}</small>
                                @endif
                            </div>

                            {{-- مكان الميلاد --}}
                            <div class="ppl-info-item">
                                <span class="ppl-info-label">
                                    <i class="fas fa-map-pin text-success"></i> مكان الميلاد
                                </span>
                                <div class="ppl-info-value">
                                    {{ $person->birth_place ?: 'غير مسجل' }}
                                </div>
                            </div>

                            {{-- مكان الإقامة الحالي --}}
                            <div class="ppl-info-item">
                                <span class="ppl-info-label">
                                    <i class="fas fa-home text-warning"></i> مكان الإقامة
                                </span>
                                <div class="ppl-info-value">
                                    {{ $person->location_display ?: 'غير مسجل' }}
                                </div>
                            </div>

                            {{-- المهنة --}}
                            <div class="ppl-info-item">
                                <span class="ppl-info-label">
                                    <i class="fas fa-briefcase text-secondary"></i> المهنة أو العمل
                                </span>
                                <div class="ppl-info-value">
                                    {{ $person->occupation ?: 'غير مسجل' }}
                                </div>
                            </div>
                        </div>

                        {{-- قسم بيانات الوفاة (يظهر إذا كان متوفياً أو مسجل تاريخ وفاة) --}}
                        @if($person->death_date || $person->death_place || $person->cemetery)
                            <div class="p-3 mb-4 rounded-lg" style="background: rgba(239, 68, 68, 0.05); border: 1px solid rgba(239, 68, 68, 0.2);">
                                <h5 class="font-weight-bold text-danger mb-3 d-flex align-items-center">
                                    <i class="fas fa-dove ml-2"></i>
                                    <span>سجل الوفاة والمقبرة (رحمه الله تعالى)</span>
                                </h5>

                                <div class="ppl-info-grid">
                                    <div class="ppl-info-item">
                                        <span class="ppl-info-label"><i class="fas fa-calendar-times text-danger"></i> تاريخ الوفاة</span>
                                        @if($person->death_date)
                                            <div class="ppl-info-value text-danger">{{ format_gregorian_in_arabic($person->death_date) }}</div>
                                            <span class="ppl-badge ppl-badge-hijri mt-1">
                                                {{ gregorian_to_hijri($person->death_date) }}
                                            </span>
                                        @else
                                            <div class="ppl-info-value text-muted font-weight-normal">-</div>
                                        @endif
                                    </div>

                                    <div class="ppl-info-item">
                                        <span class="ppl-info-label"><i class="fas fa-map-marker text-danger"></i> مكان الوفاة</span>
                                        <div class="ppl-info-value">{{ $person->death_place ?: '-' }}</div>
                                    </div>

                                    <div class="ppl-info-item">
                                        <span class="ppl-info-label"><i class="fas fa-monument text-secondary"></i> اسم المقبرة</span>
                                        <div class="ppl-info-value">{{ $person->cemetery ?: '-' }}</div>
                                    </div>

                                    <div class="ppl-info-item">
                                        <span class="ppl-info-label"><i class="fas fa-hashtag text-secondary"></i> رقم القبر</span>
                                        <div class="ppl-info-value">{{ $person->grave_number ?: '-' }}</div>
                                    </div>

                                    @if($person->cemetery_location)
                                        <div class="ppl-info-item">
                                            <span class="ppl-info-label"><i class="fas fa-map-marked text-danger"></i> موقع القبر على الخريطة</span>
                                            <div class="mt-1">
                                                <a href="{{ $person->cemetery_location }}" target="_blank" class="btn btn-sm btn-outline-danger font-weight-bold">
                                                    <i class="fas fa-external-link-alt ml-1"></i> فتح الخريطة (GPS)
                                                </a>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif

                        {{-- السيرة الذاتية --}}
                        <div class="mt-4">
                            <h5 class="ppl-text-title font-weight-bold mb-3 d-flex align-items-center">
                                <i class="fas fa-book-open text-primary ml-2"></i>
                                <span>السيرة والنبذة الذاتية</span>
                            </h5>
                            @if($person->biography)
                                <div class="ppl-bio-card shadow-sm">
                                    {{ $person->biography }}
                                </div>
                            @else
                                <div class="text-center p-4 rounded-lg border border-dashed text-muted">
                                    <i class="fas fa-feather fa-2x mb-2" style="opacity: 0.3;"></i>
                                    <p class="mb-0">لا توجد سيرة ذاتية مسجلة بعد. يمكنك كتابة سيرة أو توليدها بالذكاء الاصطناعي عبر زر التعديل.</p>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- ----------------------------------------------------
                         التبويب الثاني: الوالدان والأزواج
                         ---------------------------------------------------- --}}
                    <div class="tab-pane fade" id="lineage" role="tabpanel">
                        {{-- قسم الوالدين --}}
                        <h4 class="ppl-text-title font-weight-bold mb-3 d-flex align-items-center">
                            <i class="fas fa-users text-primary ml-2"></i>
                            <span>الوالدان المباشران</span>
                        </h4>

                        <div class="row mb-5">
                            {{-- الأب --}}
                            <div class="col-md-6 mb-3 mb-md-0">
                                <div class="ppl-form-card h-100">
                                    <div class="ppl-form-label text-primary">
                                        <i class="fas fa-male fa-lg"></i> الأب
                                    </div>
                                    @if($person->parent)
                                        <a href="{{ route('people.show', $person->parent_id) }}" class="ppl-family-card">
                                            <img src="{{ $person->parent->avatar }}" alt="{{ $person->parent->full_name }}"
                                                 class="rounded-circle border" width="60" height="60" style="object-fit: cover;">
                                            <div>
                                                <h5 class="ppl-text-title font-weight-bold mb-1">{{ $person->parent->full_name }}</h5>
                                                <span class="ppl-badge ppl-badge-male">
                                                    {{ $person->parent->age ? $person->parent->age . ' سنة' : 'والد' }}
                                                </span>
                                            </div>
                                        </a>
                                    @else
                                        <div class="text-center p-4 text-muted border border-dashed rounded-lg">
                                            <i class="fas fa-question-circle fa-2x mb-2" style="opacity: 0.3;"></i>
                                            <p class="mb-0">لم يتم ربط الأب في شجرة العائلة حتى الآن.</p>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            {{-- الأم --}}
                            <div class="col-md-6">
                                <div class="ppl-form-card h-100">
                                    <div class="ppl-form-label text-pink">
                                        <i class="fas fa-female fa-lg"></i> الأم
                                    </div>
                                    @if($person->mother)
                                        <a href="{{ route('people.show', $person->mother_id) }}" class="ppl-family-card">
                                            <img src="{{ $person->mother->avatar }}" alt="{{ $person->mother->full_name }}"
                                                 class="rounded-circle border" width="60" height="60" style="object-fit: cover;">
                                            <div>
                                                <h5 class="ppl-text-title font-weight-bold mb-1">{{ $person->mother->full_name }}</h5>
                                                <span class="ppl-badge ppl-badge-female">
                                                    {{ $person->mother->age ? $person->mother->age . ' سنة' : 'والدة' }}
                                                </span>
                                            </div>
                                        </a>
                                    @else
                                        <div class="text-center p-4 text-muted border border-dashed rounded-lg">
                                            <i class="fas fa-question-circle fa-2x mb-2" style="opacity: 0.3;"></i>
                                            <p class="mb-0">لم يتم ربط الأم في شجرة العائلة حتى الآن.</p>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- قسم الزوجات / الأزواج --}}
                        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
                            <h4 class="ppl-text-title font-weight-bold mb-0 d-flex align-items-center">
                                <i class="fas fa-heart text-danger ml-2"></i>
                                <span>سجل الزيجات ({{ $person->gender == 'male' ? 'الزوجات' : 'الزوج' }})</span>
                            </h4>
                            <div class="d-flex flex-wrap gap-2">
                                <button type="button" class="btn btn-primary ppl-btn-action" data-toggle="modal" data-target="#addMarriageModal">
                                    <i class="fas fa-plus"></i>
                                    <span>إضافة زواج</span>
                                </button>
                                <button type="button" class="btn btn-outline-warning ppl-btn-action" data-toggle="modal" data-target="#addPersonsOutsideTheFamilyTreeModal">
                                    <i class="fas fa-user-plus"></i>
                                    <span>إضافة شريك من خارج العائلة</span>
                                </button>
                            </div>
                        </div>

                        @if($marriages->isNotEmpty())
                            @if($person->gender == 'male' && $marriages->count() > 1)
                                <p class="text-muted mb-3 small">
                                    <i class="fas fa-info-circle ml-1"></i>
                                    يمكنك سحب وإفلات أي صف لإعادة ترتيب الزوجات يدوياً (في حال عدم وجود تاريخ زواج).
                                </p>
                            @endif
                            <div class="ppl-table-wrapper">
                                <div class="table-responsive">
                                    <table class="table ppl-table">
                                        <thead>
                                            <tr>
                                                @if($person->gender == 'male' && $marriages->count() > 1)
                                                    <th class="text-center" style="width: 50px;">ترتيب</th>
                                                @endif
                                                <th>{{ $person->gender == 'male' ? 'الزوجة' : 'الزوج' }}</th>
                                                <th>تاريخ الزواج</th>
                                                <th>تاريخ الانفصال</th>
                                                <th>الحالة الزوجية</th>
                                                <th>المدة</th>
                                                <th class="text-center" style="min-width: 130px;">الإجراءات</th>
                                            </tr>
                                        </thead>
                                        <tbody id="marriages-sortable" data-url="{{ route('marriages.reorder') }}" data-husband-id="{{ $person->id }}">
                                            @foreach ($marriages as $marriage)
                                                @php
                                                    $spouse = $person->gender == 'male' ? $marriage->wife : $marriage->husband;
                                                @endphp
                                                <tr data-id="{{ $marriage->id }}">
                                                    @if($person->gender == 'male' && $marriages->count() > 1)
                                                        <td class="text-center">
                                                            <span class="ppl-drag-handle" title="اسحب للترتيب">
                                                                <i class="fas fa-grip-vertical"></i>
                                                            </span>
                                                        </td>
                                                    @endif
                                                    <td>
                                                        @if($spouse)
                                                            <div class="d-flex align-items-center">
                                                                <img src="{{ $spouse->avatar }}" alt="{{ $spouse->full_name }}"
                                                                     class="rounded-circle ml-3 border" width="44" height="44" style="object-fit: cover;">
                                                                <div>
                                                                    <a href="{{ route('people.show', $spouse->id) }}" class="ppl-person-link d-block">
                                                                        {{ $spouse->full_name }}
                                                                    </a>
                                                                    <small class="text-muted">
                                                                        {{ $spouse->from_outside_the_family ? 'من خارج العائلة' : 'من آل السريّع' }}
                                                                    </small>
                                                                </div>
                                                            </div>
                                                        @else
                                                            <span class="text-muted">غير محدد</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if($marriage->married_at)
                                                            <div class="font-weight-bold">{{ format_gregorian_in_arabic($marriage->married_at) }}</div>
                                                            <small class="text-muted">{{ gregorian_to_hijri($marriage->married_at) }}</small>
                                                        @else
                                                            <span class="text-muted">غير مسجل</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if($marriage->divorced_at)
                                                            <div class="font-weight-bold text-danger">{{ format_gregorian_in_arabic($marriage->divorced_at) }}</div>
                                                            <small class="text-muted">{{ gregorian_to_hijri($marriage->divorced_at) }}</small>
                                                        @else
                                                            <span>-</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if ($marriage->isDivorced())
                                                            <span class="ppl-badge ppl-badge-deceased">
                                                                <i class="fas fa-times-circle"></i> {{ $marriage->status_text }}
                                                            </span>
                                                        @elseif($marriage->married_at)
                                                            <span class="ppl-badge ppl-badge-inside">
                                                                <i class="fas fa-check-circle"></i> {{ $marriage->status_text }}
                                                            </span>
                                                        @else
                                                            <span class="ppl-badge ppl-badge-outside">
                                                                {{ $marriage->status_text }}
                                                            </span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if ($marriage->married_at)
                                                            <span class="font-weight-bold">
                                                                {{ $marriage->isDivorced() ? $marriage->married_at->diffInYears($marriage->divorced_at ?? now()) : $marriage->married_at->diffInYears(now()) }} سنة
                                                            </span>
                                                        @else
                                                            <span>-</span>
                                                        @endif
                                                    </td>
                                                    <td class="text-center">
                                                        <div class="d-flex justify-content-center gap-1">
                                                            <button type="button" class="btn btn-outline-info ppl-btn-icon-circle"
                                                                    data-toggle="modal" data-target="#showMarriageModal{{ $marriage->id }}"
                                                                    title="تفاصيل الزواج">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-outline-primary ppl-btn-icon-circle"
                                                                    data-toggle="modal" data-target="#editMarriageModal{{ $marriage->id }}"
                                                                    title="تعديل عقد الزواج">
                                                                <i class="fas fa-pen"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-outline-danger ppl-btn-icon-circle"
                                                                    data-toggle="modal" data-target="#deleteMarriageModal{{ $marriage->id }}"
                                                                    title="حذف عقد الزواج">
                                                                <i class="fas fa-trash-alt"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @else
                            <div class="text-center p-5 rounded-lg border border-dashed text-muted">
                                <i class="fas fa-ring fa-3x mb-3" style="opacity: 0.25;"></i>
                                <h5 class="ppl-text-title font-weight-bold">لا يوجد سجلات زواج مسجلة</h5>
                                <p class="mb-3">لم يتم تسجيل أي زيجات لهذا الشخص حتى الآن. يمكنك إضافة سجل بسهولة عبر الأزرار أعلاه.</p>
                            </div>
                        @endif
                    </div>

                    {{-- ----------------------------------------------------
                         التبويب الثالث: الأبناء والذرية
                         ---------------------------------------------------- --}}
                    <div class="tab-pane fade" id="children" role="tabpanel">
                        <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-2">
                            <div>
                                <h4 class="ppl-text-title font-weight-bold mb-1 d-flex align-items-center">
                                    <i class="fas fa-child text-success ml-2"></i>
                                    <span>سجل الأبناء والذرية ({{ $children->count() }})</span>
                                </h4>
                                @can('people.update')
                                    <p class="text-muted mb-0 small">
                                        <i class="fas fa-info-circle ml-1"></i>
                                        اسحب أي صف لتغيير مكانه، ثم أكّد الترتيب لحفظه.
                                    </p>
                                    <p id="children-order-status" class="small mb-0" role="status"></p>
                                @endcan
                            </div>
                            <button type="button" class="btn btn-success ppl-btn-action shadow-sm" data-toggle="modal" data-target="#addChildModal">
                                <i class="fas fa-plus"></i>
                                <span>إضافة ابن أو ابنة</span>
                            </button>
                        </div>

                        @if ($children->isNotEmpty())
                            <div class="ppl-table-wrapper">
                                <div class="table-responsive">
                                    <table class="table ppl-table">
                                        <thead>
                                            <tr>
                                                <th class="text-center" style="width: 50px;">ترتيب</th>
                                                <th>الاسم</th>
                                                <th>الجنس</th>
                                                <th>تاريخ الميلاد</th>
                                                <th>العمر</th>
                                                <th class="text-center" style="min-width: 130px;">الإجراءات</th>
                                            </tr>
                                        </thead>
                                        <tbody id="children-sortable" data-url="{{ route('people.children.reorder', $person) }}">
                                            @foreach ($children as $child)
                                                <tr data-id="{{ $child->id }}">
                                                    <td class="text-center">
                                                        @can('people.update')
                                                            <span class="ppl-drag-handle" title="اسحب لتغيير مكان العضو">
                                                                <i class="fas fa-grip-vertical" aria-hidden="true"></i>
                                                            </span>
                                                        @endcan
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <img src="{{ $child->avatar }}" alt="{{ $child->full_name }}"
                                                                 class="rounded-circle ml-3 border" width="44" height="44" style="object-fit: cover;">
                                                            <div>
                                                                <a href="{{ route('people.show', $child->id) }}" class="ppl-person-link d-block">
                                                                    {{ $child->full_name }}
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="ppl-badge {{ $child->gender == 'male' ? 'ppl-badge-male' : 'ppl-badge-female' }}">
                                                            <i class="fas {{ $child->gender == 'male' ? 'fa-male' : 'fa-female' }}"></i>
                                                            {{ $child->gender == 'male' ? 'ذكر' : 'أنثى' }}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        @if($child->birth_date)
                                                            <div class="font-weight-bold">{{ format_gregorian_in_arabic($child->birth_date) }}</div>
                                                            <small class="text-muted">{{ gregorian_to_hijri($child->birth_date) }}</small>
                                                        @else
                                                            <span class="text-muted">-</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <span class="font-weight-bold">
                                                            {{ $child->age ? $child->age . ' سنة' : '-' }}
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <div class="d-flex justify-content-center gap-1">
                                                            <a href="{{ route('people.show', $child->id) }}" class="btn btn-outline-info ppl-btn-icon-circle" title="عرض ملف الابن">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                            <a href="{{ route('people.edit', $child->id) }}" class="btn btn-outline-primary ppl-btn-icon-circle" title="تعديل بيانات الابن">
                                                                <i class="fas fa-pen"></i>
                                                            </a>
                                                            <button type="button" class="btn btn-outline-danger ppl-btn-icon-circle"
                                                                    data-toggle="modal" data-target="#deletePersonModal{{ $child->id }}" title="حذف">
                                                                <i class="fas fa-trash-alt"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @else
                            <div class="text-center p-5 rounded-lg border border-dashed text-muted">
                                <i class="fas fa-baby-carriage fa-3x mb-3" style="opacity: 0.25;"></i>
                                <h5 class="ppl-text-title font-weight-bold">لا يوجد أبناء مسجلين</h5>
                                <p class="mb-3">لم يتم تسجيل أي أبناء لهذا الشخص حتى الآن. يمكنك إضافة ابن/ابنة بنقرة واحدة عبر الزر أعلاه.</p>
                            </div>
                        @endif
                    </div>

                    {{-- ----------------------------------------------------
                         التبويب الرابع: المواقع والتواصل
                         ---------------------------------------------------- --}}
                    <div class="tab-pane fade" id="contacts" role="tabpanel">
                        <div class="row">
                            {{-- وسائل التواصل --}}
                            <div class="col-lg-6 mb-4 mb-lg-0">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <h4 class="ppl-text-title font-weight-bold mb-0 d-flex align-items-center">
                                        <i class="fas fa-phone-alt text-success ml-2"></i>
                                        <span>وسائل وحسابات التواصل</span>
                                    </h4>
                                    <button type="button" class="btn btn-sm btn-outline-success ppl-btn-action" data-toggle="modal" data-target="#addContactAccountModal{{ $person->id }}">
                                        <i class="fas fa-plus"></i> إضافة رقم أو حساب
                                    </button>
                                </div>

                                @if($person->contactAccounts->isNotEmpty())
                                    <div class="ppl-table-wrapper">
                                        <table class="table ppl-table">
                                            <thead>
                                                <tr>
                                                    <th>الوسيلة</th>
                                                    <th>القيمة</th>
                                                    <th>التسمية</th>
                                                    <th class="text-center">إجراءات</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($person->contactAccounts as $account)
                                                    <tr>
                                                        <td>
                                                            <span class="font-weight-bold">
                                                                <i class="fas {{ $account->icon }} text-primary ml-1"></i>
                                                                {{ ucfirst($account->type) }}
                                                            </span>
                                                        </td>
                                                        <td>
                                                            @if($account->type === 'whatsapp')
                                                                <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $account->value) }}" target="_blank" class="font-weight-bold text-success">
                                                                    <i class="fab fa-whatsapp ml-1"></i> {{ $account->value }}
                                                                </a>
                                                            @elseif($account->type === 'phone')
                                                                <a href="tel:{{ $account->value }}" class="font-weight-bold text-primary">
                                                                    <i class="fas fa-phone ml-1"></i> {{ $account->value }}
                                                                </a>
                                                            @elseif($account->type === 'email')
                                                                <a href="mailto:{{ $account->value }}" class="font-weight-bold text-info">
                                                                    <i class="fas fa-envelope ml-1"></i> {{ $account->value }}
                                                                </a>
                                                            @else
                                                                <span class="font-weight-bold">{{ $account->value }}</span>
                                                            @endif
                                                        </td>
                                                        <td class="text-muted">{{ $account->label ?: '-' }}</td>
                                                        <td class="text-center">
                                                            <div class="d-flex justify-content-center gap-1">
                                                                <button type="button" class="btn btn-outline-primary ppl-btn-icon-circle"
                                                                        data-toggle="modal" data-target="#editContactAccountModal{{ $account->id }}" title="تعديل">
                                                                    <i class="fas fa-pen"></i>
                                                                </button>
                                                                <form action="{{ route('people.contact-accounts.destroy', ['person' => $person->id, 'contactAccountId' => $account->id]) }}"
                                                                      method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من حذف هذا الحساب؟');">
                                                                    @csrf
                                                                    @method('DELETE')
                                                                    <button type="submit" class="btn btn-outline-danger ppl-btn-icon-circle" title="حذف">
                                                                        <i class="fas fa-trash-alt"></i>
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
                                    <div class="text-center p-4 rounded-lg border border-dashed text-muted">
                                        <i class="fas fa-address-book fa-2x mb-2" style="opacity: 0.25;"></i>
                                        <p class="mb-0">لا توجد حسابات أو أرقام تواصل مسجلة.</p>
                                    </div>
                                @endif
                            </div>

                            {{-- المواقع الجغرافية --}}
                            <div class="col-lg-6">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <h4 class="ppl-text-title font-weight-bold mb-0 d-flex align-items-center">
                                        <i class="fas fa-map-marked text-danger ml-2"></i>
                                        <span>المواقع والعناوين</span>
                                    </h4>
                                    <button type="button" class="btn btn-sm btn-outline-danger ppl-btn-action" data-toggle="modal" data-target="#addPersonLocationModal{{ $person->id }}">
                                        <i class="fas fa-plus"></i> إضافة موقع
                                    </button>
                                </div>

                                @if($person->locations->isNotEmpty())
                                    <div class="ppl-table-wrapper">
                                        <table class="table ppl-table">
                                            <thead>
                                                <tr>
                                                    <th>اسم الموقع</th>
                                                    <th>التسمية</th>
                                                    <th>الصفة</th>
                                                    <th class="text-center">إجراءات</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($person->locations as $location)
                                                    @php
                                                        $personLocation = $person->personLocations->where('location_id', $location->id)->first();
                                                    @endphp
                                                    <tr>
                                                        <td class="font-weight-bold">{{ $location->name }}</td>
                                                        <td class="text-muted">{{ $personLocation->label ?? '-' }}</td>
                                                        <td>
                                                            @if($personLocation && $personLocation->is_primary)
                                                                <span class="ppl-badge ppl-badge-inside">أساسي</span>
                                                            @else
                                                                <span class="ppl-badge ppl-badge-deceased">ثانوي</span>
                                                            @endif
                                                        </td>
                                                        <td class="text-center">
                                                            <div class="d-flex justify-content-center gap-1">
                                                                @if($personLocation)
                                                                    <button type="button" class="btn btn-outline-primary ppl-btn-icon-circle"
                                                                            data-toggle="modal" data-target="#editPersonLocationModal{{ $personLocation->id }}" title="تعديل">
                                                                        <i class="fas fa-pen"></i>
                                                                    </button>
                                                                    <form action="{{ route('people.locations.destroy', [$person->id, $personLocation->id]) }}"
                                                                          method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من حذف هذا الموقع؟');">
                                                                        @csrf
                                                                        @method('DELETE')
                                                                        <button type="submit" class="btn btn-outline-danger ppl-btn-icon-circle" title="حذف">
                                                                            <i class="fas fa-trash-alt"></i>
                                                                        </button>
                                                                    </form>
                                                                @endif
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <div class="text-center p-4 rounded-lg border border-dashed text-muted">
                                        <i class="fas fa-map-pin fa-2x mb-2" style="opacity: 0.25;"></i>
                                        <p class="mb-0">لا توجد مواقع إضافية مسجلة.</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- ----------------------------------------------------
                         التبويب الخامس: علاقات الرضاعة
                         ---------------------------------------------------- --}}
                    <div class="tab-pane fade" id="breastfeeding" role="tabpanel">
                        @php
                            $nursingRelationships = $person->nursingRelationships()->with('breastfedChild')->get();
                            $breastfedRelationships = $person->breastfedRelationships()->with('nursingMother')->get();
                        @endphp

                        <div class="d-flex align-items-center justify-content-between mb-4">
                            <h4 class="ppl-text-title font-weight-bold mb-0 d-flex align-items-center">
                                <i class="fas fa-baby text-info ml-2"></i>
                                <span>علاقات الرضاعة وأخوة الرضاعة</span>
                            </h4>
                            <a href="{{ route('breastfeeding.index') }}" class="btn btn-outline-primary ppl-btn-action">
                                <i class="fas fa-cog"></i>
                                <span>إدارة الرضاعة المركزية</span>
                            </a>
                        </div>

                        @if ($nursingRelationships->isNotEmpty() || $breastfedRelationships->isNotEmpty())
                            <div class="row">
                                @if($nursingRelationships->isNotEmpty())
                                    <div class="col-md-6 mb-4">
                                        <h5 class="font-weight-bold text-success mb-3">
                                            <i class="fas fa-female ml-1"></i> كأم مرضعة (أرضعت):
                                        </h5>
                                        <div class="ppl-table-wrapper">
                                            <table class="table ppl-table">
                                                <thead>
                                                    <tr>
                                                        <th>الطفل المرتضع</th>
                                                        <th>المدة</th>
                                                        <th>الحالة</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($nursingRelationships as $rel)
                                                        <tr>
                                                            <td>
                                                                <a href="{{ route('people.show', $rel->breastfedChild->id) }}" class="ppl-person-link">
                                                                    {{ $rel->breastfedChild->full_name }}
                                                                </a>
                                                            </td>
                                                            <td>{{ $rel->duration_in_months ? $rel->duration_in_months . ' شهر' : '-' }}</td>
                                                            <td>
                                                                <span class="ppl-badge {{ $rel->is_active ? 'ppl-badge-inside' : 'ppl-badge-deceased' }}">
                                                                    {{ $rel->is_active ? 'نشط' : 'غير نشط' }}
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                @endif

                                @if($breastfedRelationships->isNotEmpty())
                                    <div class="col-md-6">
                                        <h5 class="font-weight-bold text-info mb-3">
                                            <i class="fas fa-child ml-1"></i> كطفل مرتضع (أرضعته):
                                        </h5>
                                        <div class="ppl-table-wrapper">
                                            <table class="table ppl-table">
                                                <thead>
                                                    <tr>
                                                        <th>الأم المرضعة</th>
                                                        <th>المدة</th>
                                                        <th>الحالة</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($breastfedRelationships as $rel)
                                                        <tr>
                                                            <td>
                                                                <a href="{{ route('people.show', $rel->nursingMother->id) }}" class="ppl-person-link">
                                                                    {{ $rel->nursingMother->full_name }}
                                                                </a>
                                                            </td>
                                                            <td>{{ $rel->duration_in_months ? $rel->duration_in_months . ' شهر' : '-' }}</td>
                                                            <td>
                                                                <span class="ppl-badge {{ $rel->is_active ? 'ppl-badge-inside' : 'ppl-badge-deceased' }}">
                                                                    {{ $rel->is_active ? 'نشط' : 'غير نشط' }}
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @else
                            <div class="text-center p-5 rounded-lg border border-dashed text-muted">
                                <i class="fas fa-baby fa-3x mb-3" style="opacity: 0.25;"></i>
                                <h5 class="ppl-text-title font-weight-bold">لا توجد علاقات رضاعة مسجلة</h5>
                                <p class="mb-3">لم يتم تسجيل أي علاقات رضاعة (كأم أو كطفل) لهذا الشخص.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ====================================================================
         مودالات العمليات (توضع بالأسفل كلياً خارج الجداول لتفادي مشاكل الـ DOM)
         ==================================================================== --}}
    
    {{-- مودال إضافة شخص من خارج العائلة --}}
    @include('dashboard.people.modals.add-outside-the-family')

    {{-- مودال إضافة ابن/ابنة --}}
    @include('dashboard.people.modals.add_child')

    {{-- مودال إضافة زواج جديد --}}
    @include('dashboard.people.modals.add_marriage')

    {{-- مودالات حسابات التواصل --}}
    @include('dashboard.people.modals.add-contact-account', ['person' => $person])
    @foreach ($person->contactAccounts as $account)
        @include('dashboard.people.modals.edit-contact-account', ['account' => $account, 'person' => $person])
    @endforeach

    {{-- مودالات المواقع الجغرافية --}}
    @include('dashboard.people.modals.add-person-location', ['person' => $person])
    @foreach ($person->personLocations as $personLocation)
        @include('dashboard.people.modals.edit-person-location', ['personLocation' => $personLocation, 'person' => $person])
    @endforeach

    {{-- مودالات الزواج (عرض وتعديل وحذف) --}}
    @foreach ($marriages as $marriage)
        @include('dashboard.marriages.modals.show', ['marriage' => $marriage])
        @include('dashboard.marriages.modals.edit', ['marriage' => $marriage])
        @include('dashboard.marriages.modals.delete', ['marriage' => $marriage])
    @endforeach

    {{-- مودالات حذف الأبناء المنفصلة --}}
    @foreach ($children as $child)
        @include('dashboard.people.modals.delete', ['person' => $child])
    @endforeach

@endsection

@push('scripts')
    {{-- مكتبة SortableJS --}}
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>

    {{-- سكريبت تحميل زوجات الأب تلقائياً --}}
    @include('dashboard.people.partials.father-wives-script')

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // سكريبت اسم الملف
            $('.custom-file-input').on('change', function() {
                var fileName = $(this).val().split('\\').pop();
                $(this).next('.custom-file-label').html(fileName);
            });

            // تهيئة Tooltips
            $('[data-toggle="tooltip"]').tooltip();

            // تهيئة بحث الزوج/الزوجة عبر Select2
            $('#addMarriageModal').on('shown.bs.modal', function() {
                @if($person->gender == 'male')
                    initPersonSelect2('#person_modal_wife_id', {
                        gender: 'female',
                        parentModal: '#addMarriageModal',
                        placeholder: 'ابحث عن الزوجة بالاسم الكامل أو النسب...',
                        previewContainer: '#person_modal_wife_preview',
                        excludeId: {{ $person->id }}
                    });
                @else
                    initPersonSelect2('#person_modal_husband_id', {
                        gender: 'male',
                        parentModal: '#addMarriageModal',
                        placeholder: 'ابحث عن الزوج بالاسم الكامل أو النسب...',
                        previewContainer: '#person_modal_husband_preview',
                        excludeId: {{ $person->id }}
                    });
                @endif
            });

            // ترتيب الأبناء بالسحب والإفلات
            const sortableElement = document.getElementById('children-sortable');
            if (sortableElement?.querySelector('.ppl-drag-handle') && typeof Sortable !== 'undefined') {
                const status = document.getElementById('children-order-status');
                let savedOrder = Array.from(sortableElement.rows, (row) => row.dataset.id);
                const sortable = Sortable.create(sortableElement, {
                    animation: 150,
                    handle: '.ppl-drag-handle',
                    onEnd: async function(evt) {
                        if (evt.oldIndex === evt.newIndex) return;
                        const order = sortable.toArray();
                        sortable.sort(savedOrder);
                        if (!window.confirm('هل أنت متأكد من تغيير ترتيب الأبناء؟')) return;

                        sortable.option('disabled', true);
                        status.classList.remove('text-danger');
                        status.textContent = 'جارٍ حفظ الترتيب…';
                        let failureMessage = 'تعذّر حفظ الترتيب. حاول مرة ثانية.';
                        try {
                            const response = await fetch(sortableElement.dataset.url, {
                                method: 'POST',
                                headers: {
                                    'Accept': 'application/json',
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                },
                                body: JSON.stringify({ order: order, previous_order: savedOrder, confirmed: true })
                            });
                            if (!response.ok) {
                                const data = await response.json();
                                failureMessage = data.errors?.order?.[0] || data.message || failureMessage;
                                throw new Error(failureMessage);
                            }
                            sortable.sort(order, true);
                            savedOrder = order;
                            status.textContent = 'تم حفظ ترتيب الأبناء.';
                        } catch {
                            sortable.sort(savedOrder, true);
                            status.classList.add('text-danger');
                            status.textContent = failureMessage;
                        } finally {
                            sortable.option('disabled', false);
                        }
                    }
                });
            }

            // ترتيب الزوجات بالسحب والإفلات
            const marriagesSortableElement = document.getElementById('marriages-sortable');
            if (marriagesSortableElement) {
                Sortable.create(marriagesSortableElement, {
                    animation: 150,
                    handle: '.ppl-drag-handle',
                    onEnd: function(evt) {
                        const orderedIds = Array.from(evt.to.children).map(row => parseInt(row.dataset.id, 10));
                        const husbandId = parseInt(marriagesSortableElement.dataset.husbandId, 10);
                        updateMarriagesOrder(husbandId, orderedIds, marriagesSortableElement.dataset.url);
                    }
                });
            }

            function updateMarriagesOrder(husbandId, orderedIds, url) {
                fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        husband_id: husbandId,
                        ordered_ids: orderedIds
                    })
                })
                .then(response => {
                    if (!response.ok) throw new Error('فشل تحديث ترتيب الزوجات');
                    return response.json();
                })
                .catch(error => {
                    console.error('Error:', error);
                });
            }
        });
    </script>
@endpush
