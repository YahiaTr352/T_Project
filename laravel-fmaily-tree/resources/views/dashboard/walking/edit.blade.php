@extends('layouts.app')

@section('title', 'تعديل سجل المشي')

@section('content')
<div class="container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-1 text-gray-800">
                <i class="fas fa-edit text-primary mr-2"></i> تعديل سجل المشي #{{ $walking->id }}
            </h1>
            <p class="text-muted small mb-0">تعديل بيانات الخطوات أو التاريخ أو الشخص المرتبط</p>
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
            <h6 class="m-0 font-weight-bold text-primary">تعديل بيانات السجل</h6>
        </div>
        <div class="card-body p-4">
            <form action="{{ route('dashboard.walking.update', $walking) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="row">
                    <div class="col-md-6 form-group">
                        <label class="font-weight-bold">الشخص من شجرة العائلة (بحث بالاسم الكامل)</label>
                        <select name="person_id" id="editPersonSelectAjax" class="form-control @error('person_id') is-invalid @enderror" style="width: 100%;">
                            @if($selectedPerson)
                                <option value="{{ $selectedPerson->id }}" selected>{{ $selectedPerson->full_name }}</option>
                            @endif
                        </select>
                    </div>

                    <div class="col-md-6 form-group">
                        <label class="font-weight-bold">مستخدم لوحة الإدارة (اختياري)</label>
                        <select name="user_id" class="form-control select2 @error('user_id') is-invalid @enderror" style="width: 100%;">
                            <option value="">-- اختياري --</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" {{ old('user_id', $walking->user_id) == $user->id ? 'selected' : '' }}>
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
                               value="{{ old('date', $walking->date ? $walking->date->format('Y-m-d') : '') }}" max="{{ now()->format('Y-m-d') }}" required>
                        @error('date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 form-group">
                        <label class="font-weight-bold">عدد الخطوات <span class="text-danger">*</span></label>
                        <input type="number" name="steps" id="editStepsInput" class="form-control @error('steps') is-invalid @enderror"
                               value="{{ old('steps', $walking->steps) }}" min="0" required>
                        @error('steps')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 form-group">
                        <label class="font-weight-bold">المسافة (كم)</label>
                        <input type="number" step="0.01" name="distance_km" id="editDistanceInput" class="form-control @error('distance_km') is-invalid @enderror"
                               value="{{ old('distance_km', $walking->distance_km) }}" min="0">
                    </div>

                    <div class="col-md-4 form-group">
                        <label class="font-weight-bold">المدة (دقيقة)</label>
                        <input type="number" name="duration_minutes" class="form-control @error('duration_minutes') is-invalid @enderror"
                               value="{{ old('duration_minutes', $walking->duration_minutes) }}" min="0">
                    </div>

                    <div class="col-md-4 form-group">
                        <label class="font-weight-bold">السعرات (kcal)</label>
                        <input type="number" name="calories" class="form-control @error('calories') is-invalid @enderror"
                               value="{{ old('calories', $walking->calories) }}" min="0">
                    </div>
                </div>

                <div class="form-group">
                    <label class="font-weight-bold">ملاحظات</label>
                    <textarea name="notes" class="form-control @error('notes') is-invalid @enderror" rows="3">{{ old('notes', $walking->notes) }}</textarea>
                </div>

                <div class="mt-4 pt-2 border-top d-flex">
                    <button type="submit" class="btn btn-primary px-4 shadow-sm">
                        <i class="fas fa-save mr-1"></i> حفظ التعديلات
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
        $('#editPersonSelectAjax').select2({
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

    document.getElementById('editStepsInput')?.addEventListener('input', function() {
        var steps = parseInt(this.value) || 0;
        var dist = (steps * 0.000762).toFixed(2);
        document.getElementById('editDistanceInput').value = dist > 0 ? dist : '';
    });
</script>
@endpush
