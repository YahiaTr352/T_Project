@extends('layouts.app')

@section('title', 'إدارة الأشخاص')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/dashboard/people.css') }}?v={{ file_exists(public_path('css/dashboard/people.css')) ? filemtime(public_path('css/dashboard/people.css')) : time() }}">
@endpush

@section('content')
    <div class="container-fluid py-2">
        {{-- عنوان الصفحة ومسار التنقل باستخدام المكون المشترك --}}
        <x-dashboard.page-header title="سجل أفراد العائلة" description="عرض وتصفية وتعديل شجرة العائلة وسجل الأقارب بسهولة">
            <x-slot name="actions">
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('family-tree') }}" class="btn btn-outline-primary ppl-btn-action" target="_blank">
                        <i class="fas fa-tree"></i>
                        <span>عرض شجرة العائلة</span>
                    </a>
                    <a href="{{ route('people.export.excel', request()->query()) }}" class="btn btn-success ppl-btn-action">
                        <i class="fas fa-file-excel"></i>
                        <span>تصدير Excel</span>
                    </a>
                    <a href="{{ route('people.create') }}" class="btn btn-primary ppl-btn-action shadow-sm">
                        <i class="fas fa-plus"></i>
                        <span>إضافة شخص جديد</span>
                    </a>
                </div>
            </x-slot>
        </x-dashboard.page-header>

        @include('components.alerts')

        {{-- إحصائيات سريعة --}}
        <div class="row mb-4">
            <x-stats-card icon="fas fa-users" title="إجمالي الأفراد" :value="$stats['total']" color="primary" />
            <x-stats-card icon="fas fa-male" title="عدد الذكور" :value="$stats['male']" color="info" />
            <x-stats-card icon="fas fa-female" title="عدد الإناث" :value="$stats['female']" color="pink" />
            <x-stats-card icon="fas fa-heart" title="الأحياء حفظهم الله" :value="$stats['living']" color="success" />
            <x-stats-card icon="fas fa-camera" title="أشخاص بصور" :value="$stats['with_photos']" color="warning" />
        </div>

        {{-- لوحة التصفية والبحث المتقدمة --}}
        <div class="ppl-filter-card">
            <form action="{{ route('people.index') }}" method="GET" id="searchFilterForm">
                @if (request('gender'))
                    <input type="hidden" name="gender" id="hidden_gender" value="{{ request('gender') }}">
                @endif

                <div class="row align-items-center">
                    {{-- تبويبات الجنس --}}
                    <div class="col-12 col-md-4 mb-3 mb-md-0">
                        <label class="ppl-filter-label">
                            <i class="fas fa-venus-mars text-primary"></i>
                            <span>تصفية حسب الجنس:</span>
                        </label>
                        <div class="btn-group w-100" role="group">
                            <a href="{{ route('people.index', array_merge(request()->except(['gender', 'page']))) }}" 
                               class="btn {{ !request('gender') ? 'btn-primary font-weight-bold' : 'btn-outline-secondary' }}"
                               style="font-size: 0.95rem; padding: 9px 14px;">
                               الكل
                            </a>
                            <a href="{{ route('people.index', array_merge(request()->except('page'), ['gender' => 'male'])) }}" 
                               class="btn {{ request('gender') === 'male' ? 'btn-primary font-weight-bold' : 'btn-outline-secondary' }}"
                               style="font-size: 0.95rem; padding: 9px 14px;">
                               <i class="fas fa-male ml-1"></i> الذكور
                            </a>
                            <a href="{{ route('people.index', array_merge(request()->except('page'), ['gender' => 'female'])) }}" 
                               class="btn {{ request('gender') === 'female' ? 'btn-primary font-weight-bold' : 'btn-outline-secondary' }}"
                               style="font-size: 0.95rem; padding: 9px 14px;">
                               <i class="fas fa-female ml-1"></i> الإناث
                            </a>
                        </div>
                    </div>

                    {{-- تصنيف العائلة (داخل / خارج) --}}
                    <div class="col-12 col-md-4 mb-3 mb-md-0">
                        <label class="ppl-filter-label">
                            <i class="fas fa-users-cog text-primary"></i>
                            <span>الانتماء للعائلة:</span>
                        </label>
                        <div class="ppl-segmented-group">
                            <div class="ppl-segmented-item">
                                <input type="radio" name="family_status" id="fs_all" value="" 
                                       {{ !request('family_status') ? 'checked' : '' }} onchange="this.form.submit()">
                                <label for="fs_all" class="ppl-segmented-label">
                                    <span>الكل</span>
                                </label>
                            </div>
                            <div class="ppl-segmented-item">
                                <input type="radio" name="family_status" id="fs_inside" value="1" 
                                       {{ request('family_status') === '1' ? 'checked' : '' }} onchange="this.form.submit()">
                                <label for="fs_inside" class="ppl-segmented-label">
                                    <i class="fas fa-home"></i>
                                    <span>داخل العائلة</span>
                                </label>
                            </div>
                            <div class="ppl-segmented-item">
                                <input type="radio" name="family_status" id="fs_outside" value="0" 
                                       {{ request('family_status') === '0' ? 'checked' : '' }} onchange="this.form.submit()">
                                <label for="fs_outside" class="ppl-segmented-label">
                                    <i class="fas fa-handshake"></i>
                                    <span>خارج العائلة</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    {{-- مربع البحث الذكي --}}
                    <div class="col-12 col-md-4">
                        <label class="ppl-filter-label">
                            <i class="fas fa-search text-primary"></i>
                            <span>البحث السريع بالاسم:</span>
                        </label>
                        <div class="ppl-search-box">
                            <i class="fas fa-search ppl-search-icon"></i>
                            <input type="text" name="search" class="form-control ppl-search-input" 
                                   placeholder="اكتب الاسم أو اللقب للبحث..." 
                                   value="{{ request('search') }}">
                            <button type="submit" class="btn btn-primary ppl-search-submit">
                                <span>بحث</span>
                                <i class="fas fa-arrow-left"></i>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- مؤشر نتائج البحث والتصفية --}}
                @if(request('search') || request('gender') || request('family_status') !== null)
                    <div class="d-flex align-items-center justify-content-between mt-3 pt-3 border-top">
                        <div class="small text-muted font-weight-bold">
                            <i class="fas fa-filter text-primary ml-1"></i>
                            يتم تطبيق فلاتر مخصصة (النتائج المعروضة: {{ $people->total() }} شخص)
                        </div>
                        <a href="{{ route('people.index') }}" class="btn btn-sm btn-outline-danger">
                            <i class="fas fa-times-circle ml-1"></i> إعادة ضبط وتصفير الفلاتر
                        </a>
                    </div>
                @endif
            </form>
        </div>

        {{-- بطاقة جدول الأشخاص --}}
        <div class="ppl-table-wrapper mb-4">
            <div class="table-responsive">
                <table class="table ppl-table">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 50px;" title="السحب والإفلات لإعادة الترتيب">ترتيب</th>
                            <th>الاسم الكامل</th>
                            <th>الوالدان (الأب / الأم)</th>
                            <th>الجنس</th>
                            <th>الانتماء</th>
                            <th>الحالة والعمر</th>
                            <th>الإقامة والمهنة</th>
                            <th class="text-center" style="min-width: 140px;">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody id="people-sortable" data-url="{{ route('people.reorder') }}">
                        @forelse($people as $person)
                            <tr data-id="{{ $person->id }}">
                                {{-- مقبض السحب والترتيب --}}
                                <td class="text-center">
                                    <span class="ppl-drag-handle" title="اسحب لتحريك الترتيب">
                                        <i class="fas fa-grip-vertical"></i>
                                    </span>
                                </td>

                                {{-- الاسم والصورة --}}
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="{{ $person->avatar }}" alt="{{ $person->full_name }}"
                                             class="rounded-circle ml-3 shadow-sm border" 
                                             width="46" height="46" style="object-fit: cover;">
                                        <div>
                                            <a href="{{ route('people.show', $person->id) }}" class="ppl-person-link d-block">
                                                {{ $person->full_name }}
                                            </a>
                                            @if($person->occupation)
                                                <small class="text-muted d-block font-weight-bold">
                                                    <i class="fas fa-briefcase ml-1" style="font-size: 0.75rem;"></i>
                                                    {{ $person->occupation }}
                                                </small>
                                            @endif
                                        </div>
                                    </div>
                                </td>

                                {{-- الأب والأم --}}
                                <td>
                                    <div style="font-size: 0.92rem; line-height: 1.5;">
                                        <div class="mb-1">
                                            <span class="text-muted">الأب:</span>
                                            @if($person->parent)
                                                <a href="{{ route('people.show', $person->parent_id) }}" class="text-primary font-weight-bold">
                                                    {{ $person->parent->full_name }}
                                                </a>
                                            @else
                                                <span class="text-muted">غير مسجل</span>
                                            @endif
                                        </div>
                                        <div>
                                            <span class="text-muted">الأم:</span>
                                            @if($person->mother)
                                                <a href="{{ route('people.show', $person->mother_id) }}" class="text-primary font-weight-bold">
                                                    {{ $person->mother->full_name }}
                                                </a>
                                            @else
                                                <span class="text-muted">غير مسجلة</span>
                                            @endif
                                        </div>
                                    </div>
                                </td>

                                {{-- الجنس --}}
                                <td>
                                    <span class="ppl-badge {{ $person->gender == 'male' ? 'ppl-badge-male' : 'ppl-badge-female' }}">
                                        <i class="fas {{ $person->gender == 'male' ? 'fa-male' : 'fa-female' }}"></i>
                                        {{ $person->gender == 'male' ? 'ذكر' : 'أنثى' }}
                                    </span>
                                </td>

                                {{-- داخل أو خارج العائلة --}}
                                <td>
                                    @if(!$person->from_outside_the_family)
                                        <span class="ppl-badge ppl-badge-inside">
                                            <i class="fas fa-home"></i> داخل العائلة
                                        </span>
                                    @else
                                        <span class="ppl-badge ppl-badge-outside">
                                            <i class="fas fa-handshake"></i> خارج العائلة
                                        </span>
                                    @endif
                                </td>

                                {{-- العمر وفترة الحياة --}}
                                <td>
                                    @if($person->death_date)
                                        <span class="ppl-badge ppl-badge-deceased mb-1 d-inline-flex">
                                            <i class="fas fa-feather-alt ml-1"></i> رحمه الله ({{ $person->age ?? 'متوفى' }})
                                        </span>
                                        <div class="small text-muted">{{ $person->life_span }}</div>
                                    @else
                                        <span class="ppl-badge ppl-badge-alive mb-1 d-inline-flex">
                                            <i class="fas fa-heart ml-1"></i> حي ({{ $person->age ?? 'غير معروف' }})
                                        </span>
                                    @endif
                                </td>

                                {{-- مكان الإقامة --}}
                                <td>
                                    @if($person->location_display)
                                        <div class="font-weight-bold text-muted" style="font-size: 0.92rem;">
                                            <i class="fas fa-map-marker-alt text-danger ml-1"></i>
                                            {{ $person->location_display }}
                                        </div>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>

                                {{-- أزرار الإجراءات واضحة وسهلة النقر --}}
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-1">
                                        <a href="{{ route('people.show', $person->id) }}" 
                                           class="btn btn-outline-info ppl-btn-icon-circle" 
                                           title="عرض الملف الكامل">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="{{ route('people.edit', $person->id) }}" 
                                           class="btn btn-outline-primary ppl-btn-icon-circle" 
                                           title="تعديل البيانات">
                                            <i class="fas fa-pen"></i>
                                        </a>
                                        <button type="button" 
                                                class="btn btn-outline-danger ppl-btn-icon-circle" 
                                                data-toggle="modal" 
                                                data-target="#deletePersonModal{{ $person->id }}" 
                                                title="حذف">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <div class="py-4">
                                        <i class="fas fa-user-slash fa-3x text-muted mb-3" style="opacity: 0.3;"></i>
                                        <h5 class="ppl-text-title font-weight-bold">لا يوجد نتائج مطابقة</h5>
                                        <p class="text-muted mb-3" style="font-size: 1rem;">لم نعثر على أي شخص مطابق لمعايير البحث أو الفلاتر المحددة.</p>
                                        <a href="{{ route('people.index') }}" class="btn btn-primary px-4 py-2">
                                            <i class="fas fa-sync ml-1"></i> إظهار كل الأفراد
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- الترقيم المنسق --}}
            @if($people->hasPages())
                <div class="d-flex justify-content-center py-3 border-top">
                    {{ $people->links() }}
                </div>
            @endif
        </div>
    </div>

    {{-- ✅ مودالات الحذف توضع هنا خارج الجدول لتفادي تداخل الـ DOM وتكرار الـ ID --}}
    @foreach ($people as $person)
        @include('dashboard.people.modals.delete', ['person' => $person])
    @endforeach
@endsection

@push('scripts')
    {{-- مكتبة SortableJS لترتيب السجلات بالسحب والإفلات --}}
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const sortableEl = document.getElementById('people-sortable');
            if (sortableEl) {
                Sortable.create(sortableEl, {
                    animation: 150,
                    handle: '.ppl-drag-handle',
                    onEnd: function(evt) {
                        const order = Array.from(evt.to.children).map((row, index) => ({
                            id: row.dataset.id,
                            order: index + 1
                        }));
                        updateOrder(order, sortableEl.dataset.url);
                    }
                });
            }

            function updateOrder(order, url) {
                fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ order: order })
                })
                .then(response => response.json())
                .then(data => {
                    // Success feedback
                })
                .catch(error => {
                    console.error('Error updating order:', error);
                });
            }
        });
    </script>
@endpush
