@extends('layouts.app')

@section('title', 'إضافة منتج جديد')

@include('components.person-select-init')

@push('styles')
<style>
    .form-section-card {
        border: none;
        border-radius: 16px;
        box-shadow: 0 4px 18px rgba(0, 0, 0, 0.04);
        margin-bottom: 24px;
        transition: box-shadow 0.2s ease;
    }
    .form-section-card:hover {
        box-shadow: 0 6px 24px rgba(0, 0, 0, 0.07);
    }
    .form-section-header {
        background: transparent;
        border-bottom: 1px solid rgba(0, 0, 0, 0.06);
        padding: 18px 22px;
    }
    .dark .form-section-header {
        border-bottom-color: rgba(255, 255, 255, 0.08);
    }
    .section-icon-badge {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        margin-left: 10px;
    }
    .image-preview-zone {
        border: 2px dashed #cbd5e1;
        border-radius: 14px;
        padding: 24px 16px;
        text-align: center;
        background: rgba(0, 0, 0, 0.01);
        cursor: pointer;
        transition: all 0.2s ease;
        position: relative;
    }
    .dark .image-preview-zone {
        border-color: #334155;
        background: rgba(255, 255, 255, 0.02);
    }
    .image-preview-zone:hover {
        border-color: #3b82f6;
        background: rgba(59, 130, 246, 0.03);
    }
    .preview-img-display {
        max-height: 180px;
        border-radius: 10px;
        object-fit: cover;
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.1);
    }
    .slot-row {
        background: rgba(0, 0, 0, 0.02);
        border: 1px solid rgba(0, 0, 0, 0.06);
        border-radius: 12px;
        padding: 12px;
        margin-bottom: 12px;
        transition: background 0.15s ease;
    }
    .dark .slot-row {
        background: rgba(255, 255, 255, 0.02);
        border-color: rgba(255, 255, 255, 0.08);
    }
</style>
@endpush

@section('content')
<div class="container-fluid pb-5">
    <!-- Breadcrumb & Header -->
    <div class="row align-items-center justify-content-between mb-4">
        <div class="col-12 col-md-6 mb-2 mb-md-0">
            <h2 class="h3 font-weight-bold text-gray-800 mb-1 d-flex align-items-center">
                <i class="fas fa-plus-circle text-primary ml-2"></i>
                إضافة منتج أو مشروع جديد
            </h2>
            <p class="text-muted mb-0 font-weight-500">
                سجل منتجاً جديداً وحدد مالكه من شجرة العائلة والتصنيف وتفاصيل العرض والطلب
            </p>
        </div>
        <div class="col-12 col-md-6 text-md-left">
            <a href="{{ route('products.index') }}" class="btn btn-outline-secondary shadow-sm">
                <i class="fas fa-arrow-right ml-1"></i> العودة للمنتجات
            </a>
        </div>
    </div>

    @if(isset($errors) && $errors->any())
        <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 mb-4" role="alert">
            <h6 class="font-weight-bold mb-2"><i class="fas fa-exclamation-triangle ml-1"></i> يوجد بعض الأخطاء في البيانات المدخلة:</h6>
            <ul class="mb-0 pr-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    <form action="{{ route('products.store') }}" method="POST" enctype="multipart/form-data" id="productCreateForm">
        @csrf

        <div class="row">
            <!-- Left Column: Core Info & Details -->
            <div class="col-12 col-lg-8">
                <!-- 1. Basic Details Card -->
                <div class="card form-section-card">
                    <div class="form-section-header d-flex align-items-center">
                        <span class="section-icon-badge bg-primary text-white">
                            <i class="fas fa-cube"></i>
                        </span>
                        <h5 class="font-weight-bold text-dark mb-0">المعلومات الأساسية للمنتج</h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="form-group mb-4">
                            <label class="font-weight-bold text-dark">
                                اسم المنتج أو الخدمة <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="name"
                                   class="form-control form-control-lg @error('name') is-invalid @enderror"
                                   value="{{ old('name') }}"
                                   placeholder="مثال: عسل سدر طبيعي فاخر / خدمات تصميم جرافيك" required autofocus>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row">
                            <div class="col-12 col-md-6 form-group mb-4">
                                <label class="font-weight-bold text-dark">
                                    الفئة الرئيسية <span class="text-danger">*</span>
                                </label>
                                <select name="product_category_id" id="categorySelect"
                                        class="form-control @error('product_category_id') is-invalid @enderror" required
                                        onchange="loadSubcategories(this.value)">
                                    <option value="">اختر الفئة الرئيسية</option>
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat->id }}" {{ old('product_category_id') == $cat->id ? 'selected' : '' }}>
                                            {{ $cat->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('product_category_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 col-md-6 form-group mb-4">
                                <label class="font-weight-bold text-dark">
                                    الفئة الفرعية
                                </label>
                                <select name="product_subcategory_id" id="subcategorySelect"
                                        class="form-control @error('product_subcategory_id') is-invalid @enderror">
                                    <option value="">اختر الفئة الفرعية (اختياري)</option>
                                </select>
                                @error('product_subcategory_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12 col-md-6 form-group mb-4">
                                <label class="font-weight-bold text-dark">
                                    السعر (ر.س) <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <input type="number" name="price" step="0.01" min="0"
                                           class="form-control @error('price') is-invalid @enderror"
                                           value="{{ old('price', '0.00') }}" required>
                                    <div class="input-group-append">
                                        <span class="input-group-text font-weight-bold">ر.س</span>
                                    </div>
                                </div>
                                @error('price')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 col-md-6 form-group mb-4 d-flex align-items-center">
                                <div class="w-100 pt-3">
                                    <div class="custom-control custom-checkbox mb-2">
                                        <input type="checkbox" name="is_rental" value="1" class="custom-control-input" id="isRentalCheck" {{ old('is_rental') ? 'checked' : '' }}>
                                        <label class="custom-control-label font-weight-bold text-dark" for="isRentalCheck">
                                            منتج للتأجير والاستعارة
                                        </label>
                                        <small class="form-text text-muted">سيظهر ضمن قسم الاستعارة والتأجير فقط بدلاً من الشراء</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group mb-4">
                            <label class="font-weight-bold text-dark">وصف المنتج</label>
                            <textarea name="description" class="form-control @error('description') is-invalid @enderror"
                                      rows="4" placeholder="اكتب وصفاً جذاباً وشاملاً للمنتج ومواصفاته...">{{ old('description') }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group mb-0">
                            <label class="font-weight-bold text-dark">مميزات المنتج (ميزة في كل سطر)</label>
                            <textarea name="features" class="form-control" rows="4"
                                      placeholder="أدخل مميزات المنتج، ميزة واحدة في كل سطر:&#10;شحن سريع ومجاني&#10;ضمان لمدة سنتين&#10;جودة عالية ممتازة">{{ old('features') }}</textarea>
                            <small class="text-muted">ستظهر هذه المميزات كنقاط جذابة داخل بطاقة المنتج</small>
                        </div>
                    </div>
                </div>

                <!-- 2. Owner & Location Card (AJAX Full Name Search) -->
                <div class="card form-section-card">
                    <div class="form-section-header d-flex align-items-center">
                        <span class="section-icon-badge bg-info text-white">
                            <i class="fas fa-user-check"></i>
                        </span>
                        <div>
                            <h5 class="font-weight-bold text-dark mb-0">صاحب المنتج والموقع</h5>
                            <small class="text-muted">اربط المنتج بصاحبه من أفراد العائلة عبر البحث الذكي بالاسم الكامل وسلسلة النسب</small>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <div class="form-group mb-4">
                            <label class="font-weight-bold text-dark">
                                صاحب المنتج / المشروع في العائلة
                            </label>
                            <select name="owner_id" id="product_owner_id" class="form-control"
                                    data-placeholder="ابحث بالاسم الكامل لصاحب المنتج أو نسبه (الأب والجد)...">
                                @if(old('owner_id'))
                                    @php $oldPerson = \App\Models\Person::find(old('owner_id')); @endphp
                                    @if($oldPerson)
                                        <option value="{{ $oldPerson->id }}" selected>{{ $oldPerson->full_name }}</option>
                                    @endif
                                @endif
                            </select>
                            @error('owner_id')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror

                            <!-- Live Person Preview Card -->
                            <div id="product_owner_preview" class="person-preview-card"></div>

                            <small class="form-text text-muted mt-2">
                                <i class="fas fa-info-circle ml-1"></i>
                                تتيح لك هذه الميزة البحث الفوري بأي جزء من الاسم أو اسم الأب أو الجد، مع عرض كارت المعاينة والاسم الكامل بدقة دون تشابه الأسماء.
                            </small>
                        </div>

                        <div class="row">
                            <div class="col-12 col-md-6 form-group mb-0">
                                <label class="font-weight-bold text-dark">الموقع الجغرافي / المدينة</label>
                                <select name="location_id" class="form-control @error('location_id') is-invalid @enderror">
                                    <option value="">اختر الموقع (اختياري)</option>
                                    @foreach($locations as $location)
                                        <option value="{{ $location->id }}" {{ old('location_id') == $location->id ? 'selected' : '' }}>
                                            {{ $location->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-12 col-md-6 form-group mb-0">
                                <label class="font-weight-bold text-dark">رابط الموقع على خرائط جوجل</label>
                                <input type="url" name="location_url" class="form-control"
                                       value="{{ old('location_url') }}"
                                       placeholder="https://maps.google.com/...">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. Availability & Working Hours Card -->
                <div class="card form-section-card">
                    <div class="form-section-header d-flex align-items-center">
                        <span class="section-icon-badge bg-warning text-white">
                            <i class="fas fa-clock"></i>
                        </span>
                        <h5 class="font-weight-bold text-dark mb-0">أوقات العمل والتوفر</h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="custom-control custom-switch mb-3">
                            <input type="checkbox" name="available_all_week" value="1"
                                   class="custom-control-input" id="availableAllWeekSwitch"
                                   {{ old('available_all_week') ? 'checked' : '' }}
                                   onchange="toggleAvailabilityView()">
                            <label class="custom-control-label font-weight-bold text-dark cursor-pointer" for="availableAllWeekSwitch">
                                متاح طوال أيام الأسبوع في نفس التوقيت
                            </label>
                        </div>

                        <!-- All Week Hours -->
                        <div id="allWeekSection" class="p-3 bg-light rounded mb-3" style="display: none;">
                            <div class="row">
                                <div class="col-12 col-md-6 form-group mb-0">
                                    <label class="text-xs font-weight-bold">وقت بداية العمل</label>
                                    <input type="time" name="all_week_start_time" class="form-control" value="{{ old('all_week_start_time') }}">
                                </div>
                                <div class="col-12 col-md-6 form-group mb-0">
                                    <label class="text-xs font-weight-bold">وقت نهاية العمل</label>
                                    <input type="time" name="all_week_end_time" class="form-control" value="{{ old('all_week_end_time') }}">
                                </div>
                            </div>
                        </div>

                        <!-- Specific Slots Builder -->
                        <div id="specificSlotsSection">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <span class="text-sm font-weight-bold text-dark">مواعيد وفترات محددة خلال الأسبوع:</span>
                                <button type="button" class="btn btn-sm btn-outline-success" onclick="addAvailabilitySlot()">
                                    <i class="fas fa-plus ml-1"></i> إضافة فترة
                                </button>
                            </div>

                            <div id="slotsContainer">
                                <!-- Dynamic slots will be appended here -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Media, Settings, Contact -->
            <div class="col-12 col-lg-4">
                <!-- Status & Publish Card -->
                <div class="card form-section-card">
                    <div class="card-body p-4">
                        <h6 class="font-weight-bold text-dark mb-3">حالة العرض</h6>
                        <div class="custom-control custom-switch mb-3">
                            <input type="checkbox" name="is_active" value="1"
                                   class="custom-control-input" id="isActiveSwitch"
                                   {{ old('is_active', true) ? 'checked' : '' }}>
                            <label class="custom-control-label font-weight-bold text-dark cursor-pointer" for="isActiveSwitch">
                                تفعيل المنتج ونشره فوراً
                            </label>
                            <small class="form-text text-muted">إذا كان معطلاً لن يظهر للأعضاء في متجر العائلة</small>
                        </div>

                        <hr>

                        <div class="d-flex flex-column" style="gap: 10px;">
                            <button type="submit" class="btn btn-primary btn-block py-2 font-weight-bold shadow-sm">
                                <i class="fas fa-save ml-1"></i> حفظ ونشر المنتج
                            </button>
                            <a href="{{ route('products.index') }}" class="btn btn-outline-secondary btn-block">
                                إلغاء
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Media Upload Card -->
                <div class="card form-section-card">
                    <div class="form-section-header d-flex align-items-center">
                        <span class="section-icon-badge bg-info text-white">
                            <i class="fas fa-camera"></i>
                        </span>
                        <h6 class="font-weight-bold text-dark mb-0">الوسائط والصور</h6>
                    </div>
                    <div class="card-body p-4">
                        <!-- Main Image Upload -->
                        <div class="form-group mb-4">
                            <label class="font-weight-bold text-dark mb-1">الصورة الرئيسية للمنتج</label>
                            <div class="image-preview-zone" onclick="document.getElementById('mainImageInput').click()">
                                <input type="file" name="main_image" id="mainImageInput" class="d-none" accept="image/*" onchange="previewMainImage(this)">
                                <div id="mainImagePlaceholder">
                                    <i class="fas fa-cloud-upload-alt fa-2x text-primary mb-2"></i>
                                    <p class="text-xs font-weight-bold text-dark mb-1">اضغط لاختيار الصورة الرئيسية</p>
                                    <small class="text-muted text-xs d-block">PNG, JPG, WEBP حتى 5MB</small>
                                </div>
                                <div id="mainImageDisplayBox" style="display: none;">
                                    <img id="mainImageDisplay" src="" class="preview-img-display w-100">
                                    <span class="btn btn-xs btn-outline-danger mt-2">تغيير الصورة</span>
                                </div>
                            </div>
                            @error('main_image')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Additional Gallery Images -->
                        <div class="form-group mb-4">
                            <label class="font-weight-bold text-dark mb-1">صور إضافية للمعرض</label>
                            <input type="file" name="images[]" class="form-control-file" accept="image/*" multiple>
                            <small class="text-muted text-xs">يمكنك تحديد عدة صور معاً لتظهر في معرض المنتج</small>
                        </div>

                        <!-- Video / YouTube -->
                        <div class="form-group mb-4">
                            <label class="font-weight-bold text-dark mb-1">فيديو محلي</label>
                            <input type="file" name="video_file" class="form-control-file" accept="video/mp4,video/mpeg,video/quicktime">
                            <small class="text-muted text-xs">الحد الأقصى 20 ميجابايت (MP4, MOV)</small>
                        </div>

                        <div class="form-group mb-0">
                            <label class="font-weight-bold text-dark mb-1">أو رابط فيديو يوتيوب</label>
                            <input type="url" name="youtube_url" class="form-control form-control-sm"
                                   placeholder="https://www.youtube.com/watch?v=..."
                                   value="{{ old('youtube_url') }}">
                        </div>
                    </div>
                </div>

                <!-- Contact Info Card -->
                <div class="card form-section-card">
                    <div class="form-section-header d-flex align-items-center">
                        <span class="section-icon-badge bg-success text-white">
                            <i class="fas fa-address-book"></i>
                        </span>
                        <h6 class="font-weight-bold text-dark mb-0">معلومات التواصل للطلب</h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="form-group mb-3">
                            <label class="text-xs font-weight-bold text-dark">رقم الهاتف</label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                </div>
                                <input type="text" name="contact_phone" class="form-control"
                                       value="{{ old('contact_phone') }}" placeholder="05xxxxxxxx">
                            </div>
                        </div>

                        <div class="form-group mb-3">
                            <label class="text-xs font-weight-bold text-dark">رقم الواتساب</label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend">
                                    <span class="input-group-text text-success"><i class="fab fa-whatsapp"></i></span>
                                </div>
                                <input type="text" name="contact_whatsapp" class="form-control"
                                       value="{{ old('contact_whatsapp') }}" placeholder="05xxxxxxxx">
                            </div>
                        </div>

                        <div class="form-group mb-3">
                            <label class="text-xs font-weight-bold text-dark">البريد الإلكتروني</label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                </div>
                                <input type="email" name="contact_email" class="form-control"
                                       value="{{ old('contact_email') }}" placeholder="email@domain.com">
                            </div>
                        </div>

                        <div class="form-group mb-3">
                            <label class="text-xs font-weight-bold text-dark">حساب إنستغرام</label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend">
                                    <span class="input-group-text text-danger"><i class="fab fa-instagram"></i></span>
                                </div>
                                <input type="text" name="contact_instagram" class="form-control"
                                       value="{{ old('contact_instagram') }}" placeholder="@username">
                            </div>
                        </div>

                        <div class="form-group mb-3">
                            <label class="text-xs font-weight-bold text-dark">حساب فيسبوك</label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend">
                                    <span class="input-group-text text-primary"><i class="fab fa-facebook"></i></span>
                                </div>
                                <input type="text" name="contact_facebook" class="form-control"
                                       value="{{ old('contact_facebook') }}" placeholder="رابط الصفحة">
                            </div>
                        </div>

                        <div class="form-group mb-0">
                            <label class="text-xs font-weight-bold text-dark">الموقع الشخصي / المتجر</label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-globe"></i></span>
                                </div>
                                <input type="url" name="website_url" class="form-control"
                                       value="{{ old('website_url') }}" placeholder="https://...">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    // Subcategories Dynamic AJAX Loader
    function loadSubcategories(categoryId, selectedSubId = null) {
        const subSelect = document.getElementById('subcategorySelect');
        subSelect.innerHTML = '<option value="">جاري التحميل...</option>';

        if (!categoryId) {
            subSelect.innerHTML = '<option value="">اختر الفئة الفرعية (اختياري)</option>';
            return;
        }

        const url = "{{ route('products.subcategories.by-category', ':id') }}".replace(':id', categoryId);
        fetch(url)
            .then(res => res.json())
            .then(data => {
                subSelect.innerHTML = '<option value="">اختر الفئة الفرعية (اختياري)</option>';
                if (data.success && data.subcategories) {
                    data.subcategories.forEach(sub => {
                        const opt = document.createElement('option');
                        opt.value = sub.id;
                        opt.textContent = sub.name;
                        if (selectedSubId && selectedSubId == sub.id) {
                            opt.selected = true;
                        }
                        subSelect.appendChild(opt);
                    });
                }
            })
            .catch(() => {
                subSelect.innerHTML = '<option value="">اختر الفئة الفرعية (اختياري)</option>';
            });
    }

    // Main Image Preview Handler
    function previewMainImage(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#mainImageDisplay').attr('src', e.target.result);
                $('#mainImagePlaceholder').hide();
                $('#mainImageDisplayBox').show();
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    // Toggle Availability View
    function toggleAvailabilityView() {
        const isAllWeek = document.getElementById('availableAllWeekSwitch').checked;
        document.getElementById('allWeekSection').style.display = isAllWeek ? 'block' : 'none';
        document.getElementById('specificSlotsSection').style.display = isAllWeek ? 'none' : 'block';
    }

    // Dynamic Availability Slots Counter
    let slotIndex = 0;
    function addAvailabilitySlot() {
        slotIndex++;
        const html = `
            <div class="slot-row" id="slotRow${slotIndex}">
                <div class="form-row align-items-center">
                    <div class="col-12 col-md-4 mb-2 mb-md-0">
                        <label class="text-xs font-weight-bold mb-1">اليوم</label>
                        <select name="availability_slots[${slotIndex}][day_of_week]" class="form-control form-control-sm" required>
                            <option value="">اختر اليوم</option>
                            <option value="saturday">السبت</option>
                            <option value="sunday">الأحد</option>
                            <option value="monday">الإثنين</option>
                            <option value="tuesday">الثلاثاء</option>
                            <option value="wednesday">الأربعاء</option>
                            <option value="thursday">الخميس</option>
                            <option value="friday">الجمعة</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-3 mb-2 mb-md-0">
                        <label class="text-xs font-weight-bold mb-1">من</label>
                        <input type="time" name="availability_slots[${slotIndex}][start_time]" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-6 col-md-3 mb-2 mb-md-0">
                        <label class="text-xs font-weight-bold mb-1">إلى</label>
                        <input type="time" name="availability_slots[${slotIndex}][end_time]" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-12 col-md-2 d-flex align-items-center justify-content-between pt-md-4">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" name="availability_slots[${slotIndex}][is_active]" value="1" class="custom-control-input" id="slotActive${slotIndex}" checked>
                            <label class="custom-control-label text-xs" for="slotActive${slotIndex}">نشط</label>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger p-1 px-2" onclick="removeSlot(${slotIndex})">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
        $('#slotsContainer').append(html);
    }

    function removeSlot(id) {
        $(`#slotRow${id}`).remove();
    }

    $(document).ready(function() {
        // Initialize Person Select2 with AJAX and Live Preview
        initPersonSelect2('#product_owner_id', {
            placeholder: 'ابحث بالاسم الكامل لصاحب المنتج أو نسبه...',
            previewContainer: '#product_owner_preview'
        });

        // Initialize Subcategories on page load if category was selected (e.g. on validation fail)
        const oldCatId = "{{ old('product_category_id') }}";
        const oldSubId = "{{ old('product_subcategory_id') }}";
        if (oldCatId) {
            loadSubcategories(oldCatId, oldSubId);
        }

        // Initialize availability view
        toggleAvailabilityView();
    });
</script>
@endpush
