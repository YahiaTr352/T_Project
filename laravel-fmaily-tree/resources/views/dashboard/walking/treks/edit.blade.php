@extends('layouts.app')

@section('title', 'تعديل مسار الرحلة')

@push('styles')
<style>
    :root {
        --walk-card-bg: #ffffff;
        --walk-card-border: #e2e8f0;
        --walk-text-primary: #1e293b;
        --walk-text-muted: #64748b;
        --walk-input-bg: #ffffff;
        --walk-input-border: #cbd5e1;
        --walk-input-text: #1e293b;
    }
    body.dark,
    .dark {
        --walk-card-bg: #212529;
        --walk-card-border: #2e343c;
        --walk-text-primary: #f1f5f9;
        --walk-text-muted: #94a3b8;
        --walk-input-bg: #1a1d21;
        --walk-input-border: #374151;
        --walk-input-text: #f1f5f9;
    }
    .card-walk {
        background-color: var(--walk-card-bg) !important;
        border: 1px solid var(--walk-card-border) !important;
        border-radius: 18px;
    }
    .form-control-walk {
        background-color: var(--walk-input-bg) !important;
        border: 1px solid var(--walk-input-border) !important;
        color: var(--walk-input-text) !important;
        border-radius: 10px;
    }
    .form-control-walk:focus {
        border-color: #10b981 !important;
        box-shadow: 0 0 0 0.2rem rgba(16, 185, 129, 0.25) !important;
    }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-4 pb-3 border-bottom" style="border-color: var(--walk-card-border) !important;">
        <div>
            <h1 class="h3 mb-1 font-weight-bold" style="color: var(--walk-text-primary);">
                <i class="fas fa-edit text-primary mr-1"></i> تعديل مسار: {{ $trek->title }}
            </h1>
            <p class="small mb-0" style="color: var(--walk-text-muted);">
                تعديل الإعدادات العامة للرحلة وتواريخها وحالة تفعيلها أو جدولتها باليوم والساعة
            </p>
        </div>
        <div class="d-flex" style="gap: 8px;">
            <a href="{{ route('dashboard.walking.treks.show', $trek) }}" class="btn btn-outline-primary shadow-sm" style="border-radius: 10px;">
                <i class="fas fa-map-marked-alt mr-1"></i> إدارة المحطات ({{ $trek->waypoints()->count() }})
            </a>
            <a href="{{ route('dashboard.walking.treks.index') }}" class="btn btn-outline-secondary shadow-sm" style="border-radius: 10px;">
                <i class="fas fa-arrow-right mr-1"></i> العودة
            </a>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger shadow-sm" style="border-radius: 14px;">
            <ul class="mb-0 pr-3">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card card-walk shadow-sm col-lg-8 mx-auto p-0 mb-5">
        <form action="{{ route('dashboard.walking.treks.update', $trek) }}" method="POST" class="card-body p-4">
            @csrf
            @method('PUT')

            <div class="form-group mb-3">
                <label class="font-weight-bold small" style="color: var(--walk-text-primary);">عنوان الرحلة / المسار <span class="text-danger">*</span></label>
                <input type="text" name="title" class="form-control form-control-walk font-weight-bold" value="{{ old('title', $trek->title) }}" required>
            </div>

            <div class="form-group mb-3">
                <label class="font-weight-bold small" style="color: var(--walk-text-primary);">وصف الرحلة وأهدافها</label>
                <textarea name="description" class="form-control form-control-walk" rows="3">{{ old('description', $trek->description) }}</textarea>
            </div>

            <div class="row mb-3">
                <div class="col-md-6 form-group">
                    <label class="font-weight-bold small" style="color: var(--walk-text-primary);">الجمهور المستهدف <span class="text-danger">*</span></label>
                    <select name="target_type" id="target_type" class="form-control form-control-walk" required>
                        <option value="family_community" {{ old('target_type', $trek->target_type) === 'family_community' ? 'selected' : '' }}>عام لجميع أفراد العائلة (تراكم جماعي)</option>
                        <option value="group" {{ old('target_type', $trek->target_type) === 'group' ? 'selected' : '' }}>خاص بمجموعة / تحدي معين</option>
                    </select>
                </div>

                <div class="col-md-6 form-group" id="group_select_wrapper" style="display: {{ $trek->target_type === 'group' ? 'block' : 'none' }};">
                    <label class="font-weight-bold small" style="color: var(--walk-text-primary);">المجموعة المرتبطة</label>
                    <select name="walking_group_id" class="form-control form-control-walk">
                        <option value="">-- اختر المجموعة --</option>
                        @foreach($groups as $grp)
                            <option value="{{ $grp->id }}" {{ old('walking_group_id', $trek->walking_group_id) == $grp->id ? 'selected' : '' }}>{{ $grp->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6 form-group">
                    <label class="font-weight-bold small" style="color: var(--walk-text-primary);">حالة المسار <span class="text-danger">*</span></label>
                    <select name="status" id="trek_status_select" class="form-control form-control-walk" required>
                        <option value="active" {{ old('status', $trek->status) === 'active' ? 'selected' : '' }}>🟢 نشط (يعرض على تطبيق الموبايل)</option>
                        <option value="scheduled" {{ old('status', $trek->status) === 'scheduled' ? 'selected' : '' }}>🟡 مجدول (يتفعل في تاريخ ووقت محدد تلقائياً)</option>
                        <option value="inactive" {{ old('status', $trek->status) === 'inactive' ? 'selected' : '' }}>⚪ غير نشط / موقوف مؤقتاً</option>
                        <option value="upcoming" {{ old('status', $trek->status) === 'upcoming' ? 'selected' : '' }}>🔵 قادم (مسودة للتحضير)</option>
                        <option value="completed" {{ old('status', $trek->status) === 'completed' ? 'selected' : '' }}>مكتمل</option>
                        <option value="archived" {{ old('status', $trek->status) === 'archived' ? 'selected' : '' }}>مؤرشف</option>
                    </select>
                </div>

                <div class="col-md-6 form-group d-flex align-items-center mt-3">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" name="is_loop" class="custom-control-input" id="is_loop" value="1" {{ old('is_loop', $trek->is_loop) ? 'checked' : '' }}>
                        <label class="custom-control-label font-weight-bold small" for="is_loop" style="color: var(--walk-text-primary);">
                            مسار دائري (Loop): يعود لنقطة البداية
                        </label>
                    </div>
                </div>
            </div>

            <!-- Scheduled Activation Date/Time Section -->
            <div id="scheduled_fields_wrapper" class="card p-3 mb-3 border-warning bg-light" style="display: {{ old('status', $trek->status) === 'scheduled' ? 'block' : 'none' }}; border-radius: 12px;">
                <div class="row">
                    <div class="col-md-6 form-group mb-2">
                        <label class="font-weight-bold small text-warning">
                            <i class="fas fa-clock mr-1"></i> موعد التفعيل المجدول (توقيت مكة المكرمة GMT+3) <span class="text-danger">*</span>
                        </label>
                        <input type="datetime-local" name="scheduled_at" id="scheduled_at" class="form-control form-control-walk font-weight-bold" 
                               value="{{ old('scheduled_at', $trek->scheduled_at ? $trek->scheduled_at->format('Y-m-d\TH:i') : '') }}"
                               min="{{ Carbon\Carbon::now(App\Models\WalkingTrek::KSA_TIMEZONE)->format('Y-m-d\TH:i') }}">
                    </div>
                    <div class="col-md-6 form-group mb-2 d-flex align-items-center mt-3">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" name="auto_start_counting" class="custom-control-input" id="auto_start_counting" value="1" {{ old('auto_start_counting', $trek->auto_start_counting ?? true) ? 'checked' : '' }}>
                            <label class="custom-control-label font-weight-bold small" for="auto_start_counting">
                                بدء احتساب الخطوات تلقائياً من تاريخ التفعيل
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6 form-group">
                    <label class="font-weight-bold small" style="color: var(--walk-text-primary);">تاريخ بداية احتساب الخطوات</label>
                    <input type="date" name="start_date" class="form-control form-control-walk" value="{{ old('start_date', $trek->start_date?->format('Y-m-d')) }}">
                    <small class="form-text" style="color: var(--walk-text-muted);">اتركه فارغاً لاحتساب كافة الخطوات التراكمية، أو حدد تاريخاً.</small>
                </div>
                <div class="col-md-6 form-group">
                    <label class="font-weight-bold small" style="color: var(--walk-text-primary);">تاريخ نهاية المسار</label>
                    <input type="date" name="end_date" class="form-control form-control-walk" value="{{ old('end_date', $trek->end_date?->format('Y-m-d')) }}">
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-4 pt-2 border-top" style="border-color: var(--walk-card-border) !important;">
                <div>
                    @can('walking-program.delete')
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="if(confirm('هل أنت متأكد من حذف مسار «{{ $trek->title }}» وجميع محطاته نهائياً؟')) { document.getElementById('delete-trek-edit-form').submit(); }" style="border-radius: 10px;">
                        <i class="fas fa-trash-alt mr-1"></i> حذف المسار
                    </button>
                    @endcan
                </div>
                <div class="d-flex" style="gap: 8px;">
                    <a href="{{ route('dashboard.walking.treks.index') }}" class="btn btn-secondary" style="border-radius: 10px;">إلغاء</a>
                    <button type="submit" class="btn btn-primary px-4 font-weight-bold shadow-sm" style="border-radius: 10px;">
                        <i class="fas fa-save mr-1"></i> حفظ التعديلات
                    </button>
                </div>
            </div>
        </form>
        @can('walking-program.delete')
        <form id="delete-trek-edit-form" action="{{ route('dashboard.walking.treks.destroy', $trek) }}" method="POST" style="display: none;">
            @csrf
            @method('DELETE')
        </form>
        @endcan
    </div>
</div>

@push('scripts')
<script>
    $('#target_type').on('change', function () {
        if ($(this).val() === 'group') {
            $('#group_select_wrapper').slideDown();
        } else {
            $('#group_select_wrapper').slideUp();
        }
    });

    $('#trek_status_select').on('change', function() {
        if ($(this).val() === 'scheduled') {
            $('#scheduled_fields_wrapper').slideDown();
        } else {
            $('#scheduled_fields_wrapper').slideUp();
        }
    });
</script>
@endpush
@endsection
