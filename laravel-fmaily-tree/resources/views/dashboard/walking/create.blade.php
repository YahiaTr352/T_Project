@extends('layouts.app')

@section('title', 'إضافة سجل مشي جديد')

@section('content')
<div class="container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-1 text-gray-800">
                <i class="fas fa-plus-circle text-primary mr-2"></i> إضافة سجل مشي
            </h1>
            <p class="text-muted small mb-0">إدخال سجل خطوات يدوي لأحد أفراد العائلة أو مستخدمي لوحة التحكم</p>
        </div>
        <a href="{{ route('dashboard.walking.index') }}" class="btn btn-secondary shadow-sm">
            <i class="fas fa-arrow-right mr-1"></i> العودة للوحة المشي
        </a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-exclamation-triangle mr-1"></i>
            <ul class="mb-0 pr-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    @endif

    <div class="card shadow-sm border-0">
        <div class="card-header bg-transparent py-3">
            <h6 class="m-0 font-weight-bold text-primary">بيانات السجل اليومي</h6>
        </div>
        <div class="card-body p-4">
            <form action="{{ route('dashboard.walking.store') }}" method="POST">
                @csrf

                <div class="row">
                    <div class="col-md-6 form-group">
                        <label class="font-weight-bold">اختر الشخص من شجرة العائلة (بحث بالاسم الكامل) <span class="text-primary">*</span></label>
                        <select name="person_id" id="personSelectAjax" class="form-control @error('person_id') is-invalid @enderror" data-placeholder="ابحث بالاسم الكامل لشخص في شجرة العائلة..." style="width: 100%;">
                        </select>
                        <small class="text-muted">الربط بشخص في شجرة العائلة يتيح ظهوره في لوحة الصدارة وتطبيق الجوال.</small>
                    </div>

                    <div class="col-md-6 form-group">
                        <label class="font-weight-bold">أو اختر مستخدم لوحة الإدارة <small class="text-muted">(اختياري)</small></label>
                        <select name="user_id" class="form-control select2 @error('user_id') is-invalid @enderror" style="width: 100%;">
                            <option value="">-- اختياري --</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" {{ old('user_id') == $user->id ? 'selected' : '' }}>
                                    {{ $user->name }} ({{ $user->email }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 form-group">
                        <label class="font-weight-bold">التاريخ <span class="text-danger">*</span></label>
                        <input type="date" name="date" class="form-control @error('date') is-invalid @enderror"
                               value="{{ old('date', now()->format('Y-m-d')) }}" max="{{ now()->format('Y-m-d') }}" required>
                        @error('date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 form-group">
                        <label class="font-weight-bold">عدد الخطوات <span class="text-danger">*</span></label>
                        <input type="number" name="steps" id="createStepsInput" class="form-control @error('steps') is-invalid @enderror"
                               value="{{ old('steps') }}" min="0" placeholder="مثال: 8500" required>
                        @error('steps')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 form-group">
                        <label class="font-weight-bold">المسافة (كم) <small class="text-muted">(تلقائي)</small></label>
                        <input type="number" step="0.01" name="distance_km" id="createDistanceInput" class="form-control @error('distance_km') is-invalid @enderror"
                               value="{{ old('distance_km') }}" min="0" placeholder="0.00">
                    </div>

                    <div class="col-md-4 form-group">
                        <label class="font-weight-bold">المدة (دقيقة)</label>
                        <input type="number" name="duration_minutes" class="form-control @error('duration_minutes') is-invalid @enderror"
                               value="{{ old('duration_minutes') }}" min="0" placeholder="0">
                    </div>

                    <div class="col-md-4 form-group">
                        <label class="font-weight-bold">السعرات المحروقة (kcal)</label>
                        <input type="number" name="calories" class="form-control @error('calories') is-invalid @enderror"
                               value="{{ old('calories') }}" min="0" placeholder="0">
                    </div>
                </div>

                <div class="form-group">
                    <label class="font-weight-bold">ملاحظات إضافية</label>
                    <textarea name="notes" class="form-control @error('notes') is-invalid @enderror" rows="3" placeholder="أي ملاحظات عن النشاط الرياضي...">{{ old('notes') }}</textarea>
                </div>

                <div class="mt-4 pt-2 border-top d-flex">
                    <button type="submit" class="btn btn-primary px-4 shadow-sm">
                        <i class="fas fa-save mr-1"></i> حفظ السجل
                    </button>
                    <a href="{{ route('dashboard.walking.index') }}" class="btn btn-secondary mr-2">
                        إلغاء
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        $('#personSelectAjax').select2({
            theme: 'bootstrap4',
            placeholder: 'ابحث بالاسم الكامل لشخص في شجرة العائلة...',
            allowClear: true,
            ajax: {
                url: "{{ route('dashboard.walking.persons.search') }}",
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return { q: params.term };
                },
                processResults: function (data) {
                    return { results: data.results };
                },
                cache: true
            },
            minimumInputLength: 0,
            templateResult: function(person) {
                if (!person.id) return person.text;
                var avatarUrl = person.avatar || "{{ asset('admin-assets/assets/avatars/default.png') }}";
                return $('<div class="d-flex align-items-center"><img src="' + avatarUrl + '" class="avatar-sm ml-2 mr-2" style="width: 28px; height: 28px;"><span>' + person.full_name + '</span></div>');
            },
            templateSelection: function(person) {
                return person.full_name || person.text;
            }
        });
    });

    document.getElementById('createStepsInput')?.addEventListener('input', function() {
        var steps = parseInt(this.value) || 0;
        var dist = (steps * 0.000762).toFixed(2);
        document.getElementById('createDistanceInput').value = dist > 0 ? dist : '';
    });
</script>
@endpush
