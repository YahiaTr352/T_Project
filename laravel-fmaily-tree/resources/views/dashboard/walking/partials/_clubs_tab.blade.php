<!-- ════════════════ TAB 6: WALKING CLUBS & STREAKS MANAGEMENT ════════════════ -->
<div class="tab-pane fade {{ $activeTab === 'tab-clubs' ? 'show active' : '' }} p-3" id="tab-clubs" role="tabpanel">

    <!-- Top Alert & Action Header -->
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4 pb-3 border-bottom" style="gap: 16px;">
        <div>
            <h5 class="font-weight-bold mb-1 text-dark">
                <i class="fas fa-medal text-warning mr-2"></i> إدارة نوادي المشي وسلسلة الاستمرارية
            </h5>
            <p class="text-muted small mb-0">
                تحكم ديناميكي كامل في أسماء النوادي، شاراتها وألوانها، حدود الخطوات، وتاريخ بدء احتساب الاستمرارية للمشتركين.
            </p>
        </div>
        <div>
            <button type="button" class="btn btn-success shadow-sm px-3" data-toggle="modal" data-target="#createClubModal">
                <i class="fas fa-plus mr-1"></i> إضافة نادٍ جديد
            </button>
        </div>
    </div>

    <!-- Info Notice Box -->
    <div class="alert alert-info border-0 shadow-sm rounded-lg mb-4">
        <div class="d-flex align-items-center">
            <div class="mr-3 ml-2" style="font-size: 24px;">💡</div>
            <div>
                <strong class="d-block mb-1">ملاحظة حاسمة بخصوص تاريخ بدء احتساب الخطوات:</strong>
                <span class="small">
                    عند تحديد <strong>تاريخ بدء الاحتساب</strong> لأي نادٍ، سيقوم النظام بحظر واقتطاع أي أيام سابقة لهذا التاريخ تلقائياً عند حساب عدد أيام الاستمرارية (Streak).
                    مثال: مشترك لديه 20 يوماً متتالية، وتم ضبط بدء الاحتساب على <strong>أمس</strong>، فستُحسب استمراريته فوراً كـ <strong>يوم واحد فقط</strong>.
                </span>
            </div>
        </div>
    </div>

    <!-- Clubs Grid Cards -->
    <div class="row" id="walkingClubsGrid">
        @forelse($walkingClubs as $club)
        <div class="col-xl-4 col-md-6 mb-4" id="club-card-{{ $club->id }}">
            <div class="card h-100 shadow-sm border-0 walking-kpi-card" style="border-top: 4px solid {{ $club->badge_color }} !important;">
                <div class="card-body p-3 d-flex flex-column justify-content-between">
                    <div>
                        <!-- Club Header Badge & Status -->
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="d-flex align-items-center" style="gap: 10px;">
                                <div style="width: 44px; height: 44px; border-radius: 12px; background: {{ $club->accent_light }}; display: flex; align-items: center; justify-content: center; font-size: 22px; border: 1px solid {{ $club->badge_color }};">
                                    {{ $club->icon ?? '👟' }}
                                </div>
                                <div>
                                    <h6 class="font-weight-bold mb-0 text-dark" id="club-title-{{ $club->id }}">{{ $club->name }}</h6>
                                    <small class="text-muted">{{ $club->short_name }} ({{ $club->sub_label ?? 'يومياً' }})</small>
                                </div>
                            </div>
                            <span class="badge {{ $club->is_active ? 'badge-success' : 'badge-secondary' }} px-2 py-1" id="club-badge-status-{{ $club->id }}">
                                {{ $club->is_active ? 'نشط' : 'معطّل' }}
                            </span>
                        </div>

                        <!-- Step Requirements -->
                        <div class="p-2 mb-3 rounded" style="background: var(--walk-filter-bg); border: 1px solid var(--walk-card-border);">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="small text-muted font-weight-bold">الحد الأدنى للخطوات:</span>
                                <span class="badge badge-primary font-weight-bold px-2 py-1" style="font-size: 13px;">
                                    {{ number_format($club->min_steps) }} خطوة
                                </span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="small text-muted font-weight-bold">الحد الأعلى للخطوات:</span>
                                <span class="small font-weight-bold {{ $club->max_steps ? 'text-dark' : 'text-success' }}">
                                    {{ $club->max_steps ? number_format($club->max_steps) . ' خطوة' : 'مفتوح للأعلى (الأعلى فئة) 👑' }}
                                </span>
                            </div>
                        </div>

                        <!-- Calculation Start Date Highlight -->
                        <div class="p-2 mb-3 rounded border" style="background: {{ $club->calculation_start_date ? '#fef3c7' : '#f8fafc' }}; border-color: {{ $club->calculation_start_date ? '#f59e0b' : '#e2e8f0' }} !important;">
                            <div class="d-flex align-items-center justify-content-between">
                                <span class="small font-weight-bold {{ $club->calculation_start_date ? 'text-warning' : 'text-muted' }}">
                                    <i class="far fa-calendar-alt mr-1"></i> تاريخ بدء الاحتساب:
                                </span>
                                <span class="font-weight-bold {{ $club->calculation_start_date ? 'text-dark' : 'text-muted' }}" id="club-date-display-{{ $club->id }}" style="font-size: 13px;">
                                    {{ $club->calculation_start_date ? $club->calculation_start_date->format('Y-m-d') : 'من البداية (غير مقيد)' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="pt-2 border-top d-flex align-items-center justify-content-between mt-2" style="gap: 8px;">
                        <button type="button" class="btn btn-sm btn-outline-primary flex-grow-1 edit-club-btn"
                                data-club="{{ json_encode($club) }}">
                            <i class="fas fa-edit mr-1"></i> تعديل النادي
                        </button>
                        <button type="button" class="btn btn-sm {{ $club->is_active ? 'btn-outline-warning' : 'btn-outline-success' }} toggle-club-btn"
                                data-id="{{ $club->id }}"
                                data-action="{{ route('dashboard.walking.clubs.toggle-status', $club->id) }}">
                            <i class="fas {{ $club->is_active ? 'fa-pause' : 'fa-play' }}"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger delete-club-btn"
                                data-id="{{ $club->id }}"
                                data-name="{{ $club->name }}"
                                data-action="{{ route('dashboard.walking.clubs.destroy', $club->id) }}">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        @empty
        <div class="col-12 text-center py-5 text-muted">
            <i class="fas fa-medal fa-3x mb-3 text-secondary"></i>
            <h6>لا توجد نوادٍ مسجلة حالياً</h6>
        </div>
        @endforelse
    </div>
</div>

<!-- ════════════════ MODAL: CREATE WALKING CLUB ════════════════ -->
<div class="modal fade" id="createClubModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title font-weight-bold">
                    <i class="fas fa-plus-circle mr-2"></i> إضافة نادٍ جديد لبرنامج المشي
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form action="{{ route('dashboard.walking.clubs.store') }}" method="POST" id="createClubForm">
                @csrf
                <div class="modal-body p-4">
                    <div class="row">
                        <div class="col-md-8 form-group">
                            <label class="font-weight-bold">اسم النادي الرئيسي <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" placeholder="مثال: حمادة العب / نادي الـ 5,000 خطوة يومياً" required>
                            <small class="text-muted">هذا الاسم سيظهر في شريط التبويبات بالموبايل وشارات المتصدرين.</small>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold">الاسم المختصر <span class="text-danger">*</span></label>
                            <input type="text" name="short_name" class="form-control" placeholder="مثال: نادي الـ 5,000" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">الحد الأدنى للخطوات (دخول النادي) <span class="text-danger">*</span></label>
                            <input type="number" name="min_steps" class="form-control" placeholder="مثال: 5000" min="100" max="100000" required>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">الحد الأعلى للخطوات (اختياري)</label>
                            <input type="number" name="max_steps" class="form-control" placeholder="مثال: 9999 (اتركه فارغاً إذا كان أعلى فئة)">
                            <small class="text-muted">اتركه فارغاً إذا كان النادي مفتوحاً للأعلى بدون سقف.</small>
                        </div>
                    </div>

                    <!-- Calculation Start Date Setting -->
                    <div class="card border-warning bg-light p-3 mb-3">
                        <label class="font-weight-bold text-dark mb-1">
                            <i class="far fa-calendar-check text-warning mr-1"></i> تاريخ بدء احتساب الخطوات والاستمرارية:
                        </label>
                        <p class="small text-muted mb-2">
                            اختر التاريخ الذي يبدأ منه احتساب استمرارية النادي. أي أيام سابقة لهذا التاريخ لن تُحسب نهائياً في سلسلة الأيام المتتالية.
                        </p>
                        <div class="input-group mb-2">
                            <input type="date" name="calculation_start_date" id="createClubStartDate" class="form-control">
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-secondary" onclick="$('#createClubStartDate').val('')">
                                    إلغاء التقييد
                                </button>
                            </div>
                        </div>
                        <!-- Quick Date Presets -->
                        <div class="d-flex flex-wrap" style="gap: 6px;">
                            <button type="button" class="btn btn-xs btn-outline-primary" onclick="$('#createClubStartDate').val('{{ now()->toDateString() }}')">اليوم</button>
                            <button type="button" class="btn btn-xs btn-outline-primary" onclick="$('#createClubStartDate').val('{{ now()->subDay()->toDateString() }}')">أمس</button>
                            <button type="button" class="btn btn-xs btn-outline-primary" onclick="$('#createClubStartDate').val('{{ now()->startOfMonth()->toDateString() }}')">بداية هذا الشهر</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary" onclick="$('#createClubStartDate').val('')">من البداية</button>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3 form-group">
                            <label class="font-weight-bold">الأيقونة / الرمز</label>
                            <input type="text" name="icon" class="form-control text-center" value="🥉" placeholder="🥉">
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="font-weight-bold">لون الشارة</label>
                            <input type="color" name="badge_color" class="form-control p-1" value="#D97706" style="height: 38px;">
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="font-weight-bold">خلفية الشارة الفاتحة</label>
                            <input type="color" name="accent_light" class="form-control p-1" value="#FEF3C7" style="height: 38px;">
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="font-weight-bold">الوصف الفرعي</label>
                            <input type="text" name="sub_label" class="form-control" value="يومياً">
                        </div>
                    </div>

                    <div class="custom-control custom-switch mt-2">
                        <input type="checkbox" class="custom-control-input" id="createClubActive" name="is_active" value="1" checked>
                        <label class="custom-control-label font-weight-bold" for="createClubActive">تفعيل النادي فوراً في التطبيق</label>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-success px-4 font-weight-bold">
                        <i class="fas fa-check mr-1"></i> حفظ وإنشاء النادي
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ════════════════ MODAL: EDIT WALKING CLUB ════════════════ -->
<div class="modal fade" id="editClubModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title font-weight-bold">
                    <i class="fas fa-edit mr-2"></i> تعديل بيانات النادي وتاريخ الاحتساب
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form action="" method="POST" id="editClubForm">
                @csrf
                @method('PUT')
                <div class="modal-body p-4">
                    <div class="row">
                        <div class="col-md-8 form-group">
                            <label class="font-weight-bold">اسم النادي الرئيسي <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="editClubName" class="form-control" required>
                            <small class="text-muted">يمكنك تسمية النادي بأي اسم (مثل: "حمادة العب").</small>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold">الاسم المختصر <span class="text-danger">*</span></label>
                            <input type="text" name="short_name" id="editClubShortName" class="form-control" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">الحد الأدنى للخطوات <span class="text-danger">*</span></label>
                            <input type="number" name="min_steps" id="editClubMinSteps" class="form-control" min="100" max="100000" required>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">الحد الأعلى للخطوات (اختياري)</label>
                            <input type="number" name="max_steps" id="editClubMaxSteps" class="form-control">
                            <small class="text-muted">اتركه فارغاً إذا كان هذا النادي هو الفئة العليا بدون سقف.</small>
                        </div>
                    </div>

                    <!-- Calculation Start Date Setting -->
                    <div class="card border-warning bg-light p-3 mb-3">
                        <label class="font-weight-bold text-dark mb-1">
                            <i class="far fa-calendar-check text-warning mr-1"></i> تاريخ بدء احتساب الخطوات والاستمرارية:
                        </label>
                        <p class="small text-muted mb-2">
                            اضبط هذا التاريخ ليتم احتساب أيام المشتركين في هذا النادي بدءاً منه فقط وتجاهل أي أيام سابقة.
                        </p>
                        <div class="input-group mb-2">
                            <input type="date" name="calculation_start_date" id="editClubStartDate" class="form-control">
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-secondary" onclick="$('#editClubStartDate').val('')">
                                    إلغاء التقييد
                                </button>
                            </div>
                        </div>
                        <!-- Quick Date Presets -->
                        <div class="d-flex flex-wrap" style="gap: 6px;">
                            <button type="button" class="btn btn-xs btn-outline-primary" onclick="$('#editClubStartDate').val('{{ now()->toDateString() }}')">اليوم</button>
                            <button type="button" class="btn btn-xs btn-outline-primary" onclick="$('#editClubStartDate').val('{{ now()->subDay()->toDateString() }}')">أمس</button>
                            <button type="button" class="btn btn-xs btn-outline-primary" onclick="$('#editClubStartDate').val('{{ now()->startOfMonth()->toDateString() }}')">بداية هذا الشهر</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary" onclick="$('#editClubStartDate').val('')">من البداية</button>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3 form-group">
                            <label class="font-weight-bold">الأيقونة / الرمز</label>
                            <input type="text" name="icon" id="editClubIcon" class="form-control text-center" placeholder="🥉">
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="font-weight-bold">لون الشارة</label>
                            <input type="color" name="badge_color" id="editClubBadgeColor" class="form-control p-1" style="height: 38px;">
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="font-weight-bold">خلفية الشارة الفاتحة</label>
                            <input type="color" name="accent_light" id="editClubAccentLight" class="form-control p-1" style="height: 38px;">
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="font-weight-bold">الوصف الفرعي</label>
                            <input type="text" name="sub_label" id="editClubSubLabel" class="form-control">
                        </div>
                    </div>

                    <div class="custom-control custom-switch mt-2">
                        <input type="checkbox" class="custom-control-input" id="editClubActive" name="is_active" value="1">
                        <label class="custom-control-label font-weight-bold" for="editClubActive">النادي نشط ويظهر في تطبيق الموبايل</label>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary px-4 font-weight-bold">
                        <i class="fas fa-save mr-1"></i> حفظ التعديلات
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
$(document).ready(function () {
    // Open Edit Modal with Club Data
    $(document).on('click', '.edit-club-btn', function () {
        const club = $(this).data('club');
        const updateUrl = "{{ url('dashboard/walking/clubs') }}/" + club.id;
        
        $('#editClubForm').attr('action', updateUrl);
        $('#editClubName').val(club.name);
        $('#editClubShortName').val(club.short_name);
        $('#editClubSubLabel').val(club.sub_label || 'يومياً');
        $('#editClubMinSteps').val(club.min_steps);
        $('#editClubMaxSteps').val(club.max_steps || '');
        $('#editClubStartDate').val(club.calculation_start_date ? club.calculation_start_date.split('T')[0] : '');
        $('#editClubIcon').val(club.icon || '🥉');
        $('#editClubBadgeColor').val(club.badge_color || '#10B981');
        $('#editClubAccentLight').val(club.accent_light || '#D1FAE5');
        $('#editClubActive').prop('checked', Boolean(club.is_active));

        $('#editClubModal').modal('show');
    });

    // Toggle Club Status AJAX
    $(document).on('click', '.toggle-club-btn', function () {
        const btn = $(this);
        const actionUrl = btn.data('action');
        const clubId = btn.data('id');

        btn.prop('disabled', true);
        $.ajax({
            url: actionUrl,
            type: 'POST',
            data: { _token: '{{ csrf_token() }}' },
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            dataType: 'json',
            success: function (res) {
                btn.prop('disabled', false);
                if (res.success) {
                    const badge = $('#club-badge-status-' + clubId);
                    if (res.is_active) {
                        badge.removeClass('badge-secondary').addClass('badge-success').text('نشط');
                        btn.removeClass('btn-outline-success').addClass('btn-outline-warning').html('<i class="fas fa-pause"></i>');
                    } else {
                        badge.removeClass('badge-success').addClass('badge-secondary').text('معطّل');
                        btn.removeClass('btn-outline-warning').addClass('btn-outline-success').html('<i class="fas fa-play"></i>');
                    }
                    if (window.toastr) toastr.success(res.message);
                }
            },
            error: function () {
                btn.prop('disabled', false);
                alert('حدث خطأ أثناء تغيير حالة النادي.');
            }
        });
    });

    // Delete Club AJAX / Confirm
    $(document).on('click', '.delete-club-btn', function () {
        const btn = $(this);
        const actionUrl = btn.data('action');
        const clubName = btn.data('name');
        const clubId = btn.data('id');

        if (!confirm('هل أنت متأكد من رغبتك في حذف نادي (' + clubName + ')؟')) {
            return;
        }

        btn.prop('disabled', true);
        $.ajax({
            url: actionUrl,
            type: 'DELETE',
            data: { _token: '{{ csrf_token() }}' },
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    $('#club-card-' + clubId).fadeOut(300, function () { $(this).remove(); });
                    if (window.toastr) {
                        toastr.success(res.message);
                    } else {
                        alert(res.message);
                    }
                } else {
                    btn.prop('disabled', false);
                    alert(res.message || 'تعذر الحذف.');
                }
            },
            error: function (xhr) {
                btn.prop('disabled', false);
                const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'تعذر حذف النادي.';
                alert(msg);
            }
        });
    });
});
</script>
@endpush
