@extends('layouts.app')

@section('content')
    @include('components.person-select-init')

    <div class="container-fluid">
        {{-- عنوان الصفحة ومسار التنقل --}}
        <div class="row">
            <div class="col-12">
                <h1 class="h3 mb-0 text-gray-800">إدارة الزواج</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="{{ route('dashboard') }}">لوحة التحكم</a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">سجلات الزواج</li>
                    </ol>
                </nav>
            </div>
        </div>

        @include('components.alerts')

        {{-- إحصائيات الزواج --}}
        <div class="row mb-4">
            <x-stats-card icon="fas fa-ring" title="إجمالي حالات الزواج" :value="$totalMarriages" color="primary" />
            <x-stats-card icon="fas fa-heart" title="زواج نشط" :value="$activeMarriages" color="success" />
            <x-stats-card icon="fas fa-heart-broken" title="حالات الانفصال" :value="$divorcedMarriages" color="danger" />
            <x-stats-card icon="fas fa-question-circle" title="غير محددة الحالة" :value="$unknownStatusMarriages" color="warning" />
        </div>

        {{-- بطاقة قائمة الزواج --}}
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h6 class="m-0 font-weight-bold text-primary">سجلات الزواج</h6>
                <div>
                    <button class="btn btn-primary shadow-sm" data-toggle="modal" data-target="#createMarriageModal">
                        <i class="fas fa-plus mr-1"></i> إضافة سجل زواج
                    </button>
                    <button class="btn btn-outline-primary ml-2 shadow-sm" data-toggle="modal" data-target="#addPersonsOutsideTheFamilyTreeModal">
                        <i class="fas fa-user-plus mr-1"></i> إضافة شخص خارج العائلة
                    </button>
                </div>
                @include('dashboard.people.modals.add-outside-the-family')
            </div>
            <div class="card-body">
                {{-- تبويب الحالات --}}
                <ul class="nav nav-tabs mb-4">
                    <li class="nav-item">
                        <a class="nav-link {{ $status === 'all' ? 'active' : '' }}"
                            href="{{ route('marriages.index') }}">الكل</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ $status === 'active' ? 'active' : '' }}"
                            href="{{ route('marriages.index', ['status' => 'active']) }}">زواج نشط</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ $status === 'divorced' ? 'active' : '' }}"
                            href="{{ route('marriages.index', ['status' => 'divorced']) }}">منفصلون</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ $status === 'unknown' ? 'active' : '' }}"
                            href="{{ route('marriages.index', ['status' => 'unknown']) }}">غير محدد</a>
                    </li>
                </ul>

                {{-- نموذج البحث --}}
                <form action="{{ route('marriages.index') }}" method="GET" class="mb-4 bg-light p-3 rounded">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group mb-md-0">
                                <label for="husband_search" class="font-weight-bold">
                                    <i class="fas fa-mars text-primary mr-1"></i> بحث بالزوج:
                                </label>
                                <select id="husband_search" name="husband_id" class="form-control" data-placeholder="ابحث بالاسم الكامل للزوج...">
                                    @if(isset($selectedHusband) && $selectedHusband)
                                        <option value="{{ $selectedHusband->id }}" selected>
                                            {{ $selectedHusband->full_name }}
                                        </option>
                                    @else
                                        <option value=""></option>
                                    @endif
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group mb-md-0">
                                <label for="wife_search" class="font-weight-bold">
                                    <i class="fas fa-venus text-danger mr-1"></i> بحث بالزوجة:
                                </label>
                                <select id="wife_search" name="wife_id" class="form-control" data-placeholder="ابحث بالاسم الكامل للزوجة...">
                                    @if(isset($selectedWife) && $selectedWife)
                                        <option value="{{ $selectedWife->id }}" selected>
                                            {{ $selectedWife->full_name }}
                                        </option>
                                    @else
                                        <option value=""></option>
                                    @endif
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group mb-md-0">
                                <label for="date_range" class="font-weight-bold">
                                    <i class="fas fa-calendar-alt text-secondary mr-1"></i> نطاق التاريخ:
                                </label>
                                <input type="text" id="date_range" name="date_range" class="form-control"
                                    value="{{ request('date_range') }}" placeholder="حدد نطاق تاريخ الزواج/الانفصال">
                            </div>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-12 text-right">
                            <button type="submit" class="btn btn-primary shadow-sm px-4">
                                <i class="fas fa-search mr-1"></i> بحث
                            </button>
                            <a href="{{ route('marriages.index') }}" class="btn btn-secondary shadow-sm px-3">
                                <i class="fas fa-undo mr-1"></i> إعادة تعيين
                            </a>
                        </div>
                    </div>
                </form>

                @if(request()->filled('husband_id') && isset($selectedHusband) && $selectedHusband)
                    <div class="alert alert-info d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div>
                            <i class="fas fa-sort-numeric-down ml-1"></i>
                            زوجات <strong>{{ $selectedHusband->full_name }}</strong> مرتبة الآن (الأولى ثم الثانية...).
                            عدّل رقم <strong>ترتيب الزوجة</strong> من زر التعديل، أو رتّب بالتاريخ.
                            @if(!empty($wifeOrderLabels))
                                <div class="mt-1 small">الترتيب الحالي: {{ implode(' ← ', $wifeOrderLabels) }}</div>
                            @endif
                        </div>
                        <a href="{{ route('marriages.index') }}" class="btn btn-sm btn-outline-secondary">عرض الكل</a>
                    </div>
                @else
                    <div class="alert alert-light border small text-muted">
                        <i class="fas fa-info-circle ml-1"></i>
                        لترتيب زوجات رجل معين يدوياً (الأولى/الثانية...) ابحث بالزوج أعلاه — سيُعرض الجدول بترتيب الزوجات، وعدّل عمود <strong>ترتيب الزوجة</strong>.
                        بدون ترتيب يدوي يُعتمد تاريخ الزواج تلقائياً.
                    </div>
                @endif

                {{-- جدول سجلات الزواج --}}
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle" id="marriagesTable">
                        <thead class="thead-light">
                            <tr>
                                <th>الزوج</th>
                                <th>الزوجة</th>
                                <th>ترتيب الزوجة</th>
                                <th>تاريخ الزواج</th>
                                <th>تاريخ الانفصال</th>
                                <th>الحالة</th>
                                <th>المدة</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($marriages as $marriage)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="{{ $marriage->husband->avatar }}" alt="{{ $marriage->husband->full_name }}" class="rounded-circle mr-2" width="32" height="32" style="object-fit: cover;">
                                            <a href="{{ route('people.show', $marriage->husband_id) }}" class="font-weight-bold text-decoration-none">
                                                {{ $marriage->husband->full_name }}
                                            </a>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="{{ $marriage->wife->avatar }}" alt="{{ $marriage->wife->full_name }}" class="rounded-circle mr-2" width="32" height="32" style="object-fit: cover;">
                                            <a href="{{ route('people.show', $marriage->wife_id) }}" class="font-weight-bold text-decoration-none">
                                                {{ $marriage->wife->full_name }}
                                            </a>
                                        </div>
                                        @if(request()->filled('husband_id') && !empty($wifeOrderLabels[$marriage->wife_id] ?? null))
                                            <span class="badge badge-info mt-1">{{ $wifeOrderLabels[$marriage->wife_id] }}</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <span class="badge badge-secondary px-2 py-1" title="1 = الزوجة الأولى">{{ $marriage->sort_order ?? '-' }}</span>
                                    </td>
                                    <td>{{ $marriage->married_at ? $marriage->married_at->format('Y-m-d') : 'غير معروف' }}</td>
                                    <td>{{ $marriage->divorced_at ? $marriage->divorced_at->format('Y-m-d') : ($marriage->is_divorced ? '✅' : '-') }}</td>
                                    <td>
                                        @if ($marriage->isDivorced())
                                            <span class="badge badge-danger px-2 py-1">{{ $marriage->status_text }}</span>
                                        @elseif($marriage->married_at)
                                            <span class="badge badge-success px-2 py-1">{{ $marriage->status_text }}</span>
                                        @else
                                            <span class="badge badge-warning px-2 py-1">{{ $marriage->status_text }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($marriage->married_at)
                                            @if ($marriage->isDivorced())
                                                {{ $marriage->married_at->diffInYears($marriage->divorced_at ?? now()) }} سنة
                                            @else
                                                {{ $marriage->married_at->diffInYears(now()) }} سنة
                                            @endif
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>
                                        {{-- زر عرض --}}
                                        <button type="button" class="btn btn-sm btn-circle btn-info" data-toggle="modal"
                                            data-target="#showMarriageModal{{ $marriage->id }}" title="عرض">
                                            <i class="fas fa-eye"></i>
                                        </button>

                                        {{-- زر تعديل --}}
                                        <button type="button" class="btn btn-sm btn-circle btn-primary"
                                            data-toggle="modal" data-target="#editMarriageModal{{ $marriage->id }}"
                                            title="تعديل">
                                            <i class="fas fa-edit"></i>
                                        </button>

                                        {{-- زر حذف --}}
                                        <button type="button" class="btn btn-sm btn-circle btn-danger"
                                            data-toggle="modal" data-target="#deleteMarriageModal{{ $marriage->id }}"
                                            title="حذف">
                                            <i class="fas fa-trash"></i>
                                        </button>

                                        {{-- تضمين المودالات لكل سجل زواج --}}
                                        @include('dashboard.marriages.modals.delete', ['marriage' => $marriage])
                                        @include('dashboard.marriages.modals.show', ['marriage' => $marriage])
                                        @include('dashboard.marriages.modals.edit', ['marriage' => $marriage])
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">لا توجد سجلات زواج</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- الترقيم --}}
                <div class="d-flex justify-content-center mt-3">
                    {{ $marriages->appends(request()->query())->links() }}
                </div>
            </div>
        </div>
    </div>

    {{-- مودال إضافة سجل زواج (ثابت) --}}
    @include('dashboard.marriages.modals.create')

@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            // تهيئة فلاتر البحث العلوية عبر AJAX Select2
            initPersonSelect2('#husband_search', {
                gender: 'male',
                placeholder: 'ابحث بالاسم الكامل للزوج...'
            });

            initPersonSelect2('#wife_search', {
                gender: 'female',
                placeholder: 'ابحث بالاسم الكامل للزوجة...'
            });

            // تهيئة مودال إضافة زواج جديد
            function updateCreateMarriageBadge() {
                const husbandVal = $('#create_marriage_husband_id').val();
                const wifeVal = $('#create_marriage_wife_id').val();
                if (husbandVal && wifeVal) {
                    $('#createMarriageConnectorBadge').addClass('active');
                } else {
                    $('#createMarriageConnectorBadge').removeClass('active');
                }
            }

            $('#createMarriageModal').on('shown.bs.modal', function() {
                initPersonSelect2('#create_marriage_husband_id', {
                    gender: 'male',
                    parentModal: '#createMarriageModal',
                    placeholder: 'ابحث عن الزوج بالاسم الكامل...',
                    previewContainer: '#create_husband_preview',
                    excludeId: function() {
                        return $('#create_marriage_wife_id').val();
                    },
                    onSelect: function() {
                        updateCreateMarriageBadge();
                    },
                    onClear: function() {
                        updateCreateMarriageBadge();
                    }
                });

                initPersonSelect2('#create_marriage_wife_id', {
                    gender: 'female',
                    parentModal: '#createMarriageModal',
                    placeholder: 'ابحث عن الزوجة بالاسم الكامل...',
                    previewContainer: '#create_wife_preview',
                    excludeId: function() {
                        return $('#create_marriage_husband_id').val();
                    },
                    onSelect: function() {
                        updateCreateMarriageBadge();
                    },
                    onClear: function() {
                        updateCreateMarriageBadge();
                    }
                });
            });

            // تهيئة مودالات التعديل عند فتحها
            $(document).on('shown.bs.modal', '.modal[id^="editMarriageModal"]', function() {
                const $modal = $(this);
                const husbandSelect = $modal.find('.js-marriage-edit-husband');
                const wifeSelect = $modal.find('.js-marriage-edit-wife');

                if (husbandSelect.length) {
                    initPersonSelect2(husbandSelect, {
                        gender: 'male',
                        parentModal: '#' + $modal.attr('id'),
                        placeholder: 'ابحث عن الزوج بالاسم الكامل...',
                        previewContainer: husbandSelect.data('preview'),
                        excludeId: function() {
                            return wifeSelect.val();
                        }
                    });
                }

                if (wifeSelect.length) {
                    initPersonSelect2(wifeSelect, {
                        gender: 'female',
                        parentModal: '#' + $modal.attr('id'),
                        placeholder: 'ابحث عن الزوجة بالاسم الكامل...',
                        previewContainer: wifeSelect.data('preview'),
                        excludeId: function() {
                            return husbandSelect.val();
                        }
                    });
                }
            });

            // تفعيل date range picker
            $('#date_range').daterangepicker({
                locale: {
                    format: 'YYYY-MM-DD',
                    applyLabel: 'تطبيق',
                    cancelLabel: 'إلغاء',
                    fromLabel: 'من',
                    toLabel: 'إلى',
                    customRangeLabel: 'مخصص',
                    daysOfWeek: ['ح', 'ن', 'ث', 'ر', 'خ', 'ج', 'س'],
                    monthNames: ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس',
                        'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'
                    ],
                    firstDay: 6
                },
                opens: 'right',
                autoUpdateInput: false
            });

            $('#date_range').on('apply.daterangepicker', function(ev, picker) {
                $(this).val(picker.startDate.format('YYYY-MM-DD') + ' - ' + picker.endDate.format('YYYY-MM-DD'));
            });

            $('#date_range').on('cancel.daterangepicker', function(ev, picker) {
                $(this).val('');
            });

            // التحقق من صحة النموذج قبل الإرسال
            $('#createMarriageForm, .edit-marriage-form').on('submit', function(e) {
                const husbandId = $(this).find('select[name="husband_id"]').val();
                const wifeId = $(this).find('select[name="wife_id"]').val();

                if (!husbandId || !wifeId) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'warning',
                        title: 'تنبيه',
                        text: 'يرجى اختيار الزوج والزوجة قبل الحفظ.',
                    });
                    return;
                }

                if (husbandId === wifeId) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'خطأ',
                        text: 'لا يمكن أن يكون الزوج والزوجة نفس الشخص!',
                    });
                }
            });
        });
    </script>
@endpush

