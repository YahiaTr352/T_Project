@extends('layouts.app')

@section('title', 'إضافة شخص جديد إلى شجرة العائلة')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/dashboard/people.css') }}?v={{ file_exists(public_path('css/dashboard/people.css')) ? filemtime(public_path('css/dashboard/people.css')) : time() }}">
@endpush

@section('content')
    <div class="container-fluid py-2">
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb bg-transparent p-0 mb-1" style="font-size: 0.95rem;">
                        <li class="breadcrumb-item">
                            <a href="{{ route('dashboard') }}" class="text-muted"><i class="fas fa-home ml-1"></i> لوحة التحكم</a>
                        </li>
                        <li class="breadcrumb-item">
                            <a href="{{ route('people.index') }}" class="text-muted">سجل الأفراد</a>
                        </li>
                        <li class="breadcrumb-item active text-primary font-weight-bold" aria-current="page">إضافة شخص جديد</li>
                    </ol>
                </nav>
                <h2 class="ppl-text-title mb-0 font-weight-bold">إضافة فرد جديد إلى شجرة العائلة</h2>
            </div>
            <div>
                <a href="{{ route('people.index') }}" class="btn btn-outline-secondary ppl-btn-action">
                    <i class="fas fa-arrow-right"></i>
                    <span>رجوع لقائمة الأفراد</span>
                </a>
            </div>
        </div>

        @include('components.alerts')

        <form action="{{ route('people.store') }}" method="POST" enctype="multipart/form-data" id="createPersonForm">
            @csrf
            <input type="hidden" name="redirect_to" value="show">

            @include('dashboard.people.partials.form', ['person' => null, 'males' => $males])

            {{-- شريط الحفظ السفلي الواضح --}}
            <div class="card p-3 shadow-sm border-0 mb-5" style="border-radius: 16px; background: var(--ppl-card-bg);">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div class="text-muted small">
                        <i class="fas fa-shield-alt text-success ml-1"></i>
                        سيتم حفظ البيانات وإدراج الفرد مباشرة في شجرة العائلة ونظام السجلات.
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('people.index') }}" class="btn btn-secondary ppl-btn-action px-4">
                            إلغاء
                        </a>
                        <button type="submit" class="btn btn-primary ppl-btn-action px-5 shadow">
                            <i class="fas fa-save ml-1"></i>
                            <span>حفظ وإضافة الفرد الآن</span>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    @include('dashboard.people.partials.father-wives-script')

    <script>
        $(function () {
            $('.custom-file-input').on('change', function () {
                var fileName = $(this).val().split('\\').pop();
                $(this).next('.custom-file-label').html(fileName);
            });
        });

        document.addEventListener('DOMContentLoaded', function () {
            const locationInput = document.getElementById('location');
            const locationIdInput = document.getElementById('location_id');
            const suggestionsDiv = document.getElementById('location_suggestions');
            let searchTimeout;

            if (!locationInput || !locationIdInput || !suggestionsDiv) return;

            locationInput.addEventListener('input', function () {
                const query = this.value.trim();
                clearTimeout(searchTimeout);

                if (query.length < 2) {
                    suggestionsDiv.style.display = 'none';
                    locationIdInput.value = '';
                    return;
                }

                searchTimeout = setTimeout(function () {
                    fetch('{{ route("locations.autocomplete") }}?q=' + encodeURIComponent(query))
                        .then(response => response.json())
                        .then(data => {
                            suggestionsDiv.innerHTML = '';

                            if (!Array.isArray(data) || data.length === 0) {
                                suggestionsDiv.innerHTML =
                                    '<div class="list-group-item text-muted p-3">لا توجد نتائج مطابقة</div>';
                                suggestionsDiv.style.display = 'block';
                                return;
                            }

                            data.forEach(function (location) {
                                const item = document.createElement('div');
                                item.className = 'list-group-item list-group-item-action p-3';
                                item.style.cursor = 'pointer';
                                item.innerHTML = `
                                    <div class="d-flex align-items-center justify-content-between">
                                        <strong class="text-primary"><i class="fas fa-map-marker-alt ml-2"></i>${location.name}</strong>
                                        ${location.persons_count > 0 ? `<span class="badge badge-light font-weight-bold">(${location.persons_count} فرد)</span>` : ''}
                                    </div>
                                `;

                                item.addEventListener('click', function () {
                                    locationInput.value = location.name;
                                    locationIdInput.value = location.id;
                                    suggestionsDiv.style.display = 'none';
                                });

                                suggestionsDiv.appendChild(item);
                            });

                            suggestionsDiv.style.display = 'block';
                        })
                        .catch(error => {
                            console.error('Error fetching locations:', error);
                        });
                }, 300);
            });

            document.addEventListener('click', function (e) {
                if (!locationInput.contains(e.target) && !suggestionsDiv.contains(e.target)) {
                    suggestionsDiv.style.display = 'none';
                }
            });
        });
    </script>
@endpush