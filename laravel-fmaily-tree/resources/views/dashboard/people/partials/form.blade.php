@php
    /** @var \App\Models\Person|null $person */
    /** @var \Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Collection $males */

    $isEdit = isset($person) && $person && $person->exists;
    $familyStatus = match (old('from_outside_the_family', $isEdit ? $person->from_outside_the_family : null)) {
        0, '0', false => '0',
        1, '1', true => '1',
        default => '',
    };
    $selectedFatherId = old('parent_id', $isEdit ? $person->parent_id : '');
    $selectedMotherId = old('mother_id', $isEdit ? $person->mother_id : '');

    $father = $isEdit ? $person->parent : null;
    $wives = $father ? ($father->wives ?? collect()) : collect();
    $isDeceased = old('is_deceased', $isEdit ? ($person->death_date || $person->death_place || $person->cemetery ? 1 : 0) : 0);
@endphp

{{-- ====================================================================
     1. بطاقة الهوية والبيانات الشخصية
     ==================================================================== --}}
<div class="ppl-form-card">
    <div class="ppl-form-card-header">
        <i class="fas fa-id-card text-primary fa-lg"></i>
        <h4 class="ppl-form-card-title">1. المعلومات الشخصية الأساسية</h4>
    </div>

    <div class="row">
        {{-- الاسم الأول --}}
        <div class="col-md-6 mb-3">
            <div class="form-group mb-0">
                <label for="first_name" class="ppl-form-label">
                    <span>الاسم الأول</span>
                    <span class="text-danger">*</span>
                </label>
                <input type="text" class="form-control ppl-form-control" id="first_name" name="first_name"
                       value="{{ old('first_name', $isEdit ? $person->first_name : '') }}" 
                       placeholder="مثال: محمد، عبد الله، فاطمة..." required>
                <small class="form-text text-muted">الاسم الأول المجرد بدون اسم الأب أو اللقب</small>
            </div>
        </div>

        {{-- الاسم الأخير أو اللقب --}}
        <div class="col-md-6 mb-3">
            <div class="form-group mb-0">
                <label for="last_name" class="ppl-form-label">
                    <span>الاسم الأخير أو اللقب العائلي</span>
                </label>
                <input type="text" class="form-control ppl-form-control" id="last_name" name="last_name"
                       value="{{ old('last_name', $isEdit ? $person->last_name : 'السريّع') }}" 
                       placeholder="مثال: السريّع">
                <small class="form-text text-muted">يُترك 'السريّع' لأبناء العائلة الأصليين</small>
            </div>
        </div>

        {{-- الجنس --}}
        <div class="col-md-6 mb-3">
            <div class="form-group mb-0">
                <label for="gender" class="ppl-form-label">
                    <span>الجنس</span>
                    <span class="text-danger">*</span>
                </label>
                <select class="form-control ppl-form-control" id="gender" name="gender" required>
                    <option value="male" @selected(old('gender', $isEdit ? $person->gender : 'male') == 'male')>
                        👨 ذكر
                    </option>
                    <option value="female" @selected(old('gender', $isEdit ? $person->gender : '') == 'female')>
                        👩 أنثى
                    </option>
                </select>
            </div>
        </div>

        {{-- الحالة العائلية (داخل / خارج العائلة) --}}
        <div class="col-md-6 mb-3">
            <div class="form-group mb-0">
                <label for="from_outside_the_family" class="ppl-form-label">
                    <span>الانتماء للعائلة</span>
                    <span class="text-danger">*</span>
                </label>
                <select class="form-control ppl-form-control" id="from_outside_the_family" name="from_outside_the_family" @required(!$isEdit)>
                    @if (!$isEdit)
                        <option value="" disabled @selected($familyStatus === '')>اختر الانتماء للعائلة</option>
                    @endif
                    <option value="0" @selected($familyStatus === '0')>
                        🏛️ من آل السريّع (داخل العائلة)
                    </option>
                    <option value="1" @selected($familyStatus === '1')>
                        🤝 مصاهرة أو زواج (من خارج العائلة)
                    </option>
                </select>
            </div>
        </div>
    </div>
</div>

{{-- ====================================================================
     2. بطاقة النسب والوالدان
     ==================================================================== --}}
<div class="ppl-form-card">
    <div class="ppl-form-card-header">
        <i class="fas fa-sitemap text-info fa-lg"></i>
        <h4 class="ppl-form-card-title">2. النسب والوالدان في شجرة العائلة</h4>
    </div>

    @if (!$isEdit)
        @include('dashboard.people.partials.parent-fields', ['idPrefix' => '', 'parentsRequired' => $familyStatus === '0'])
    @else
    <div class="row">
        {{-- اختيار الأب --}}
        <div class="col-md-6 mb-3">
            <div class="form-group mb-0">
                <label for="parent_id" class="ppl-form-label">
                    <i class="fas fa-male text-primary"></i>
                    <span>الأب (من سجل أفراد العائلة)</span>
                </label>
                <select class="form-control select2 js-father-select ppl-form-control" id="parent_id" name="parent_id" data-placeholder="ابحث عن اسم الأب...">
                    <option value="">-- اختر الأب إن وجد --</option>
                    @foreach ($males as $fatherOption)
                        <option value="{{ $fatherOption->id }}" @selected((string) $selectedFatherId === (string) $fatherOption->id)>
                            {{ $fatherOption->full_name }}
                        </option>
                    @endforeach
                </select>
                <small class="form-text text-muted">اختيار الأب سيقوم بتحديث قائمة أمهات العائلة تلقائياً أدناه</small>
            </div>
        </div>

        {{-- اختيار الأم --}}
        <div class="col-md-6 mb-3">
            <div class="form-group mb-0">
                <label for="mother_id" class="ppl-form-label">
                    <i class="fas fa-female text-pink"></i>
                    <span>الأم (من زوجات الأب)</span>
                </label>
                <select class="form-control js-mother-select ppl-form-control" id="mother_id" name="mother_id">
                    @if ($selectedFatherId)
                        <option value="">-- اختر الأم --</option>
                        @foreach ($wives as $wife)
                            <option value="{{ $wife->id }}" @selected((string) $selectedMotherId === (string) $wife->id)>
                                {{ $wife->full_name }}
                            </option>
                        @endforeach
                    @else
                        <option value="">-- يرجى اختيار الأب أولاً لعرض زوجاته --</option>
                    @endif
                </select>
                <small class="form-text text-muted">تظهر تلقائياً الزوجات المرتبطات بالأب المختار</small>
            </div>
        </div>
    </div>
    @endif
</div>

{{-- ====================================================================
     3. بطاقة الميلاد والإقامة والمهنة
     ==================================================================== --}}
<div class="ppl-form-card">
    <div class="ppl-form-card-header">
        <i class="fas fa-calendar-check text-success fa-lg"></i>
        <h4 class="ppl-form-card-title">3. الميلاد والإقامة والمهنة</h4>
    </div>

    <div class="row">
        {{-- تاريخ الميلاد --}}
        <div class="col-md-6 mb-3">
            <div class="form-group mb-0">
                <label for="birth_date" class="ppl-form-label">
                    <i class="fas fa-calendar-alt text-primary"></i>
                    <span>تاريخ الميلاد (ميلادي)</span>
                </label>
                <input type="date" class="form-control ppl-form-control" id="birth_date" name="birth_date"
                       value="{{ old('birth_date', $isEdit && $person->birth_date ? $person->birth_date->format('Y-m-d') : '') }}">
                
                {{-- كبسولة المعاينة الهجرية التلقائية الفورية --}}
                <div id="birth_date_hijri_container" class="mt-2" style="display: none;">
                    <span class="ppl-badge ppl-badge-hijri py-2 px-3 font-weight-bold" style="font-size: 0.92rem;">
                        <i class="fas fa-moon ml-1"></i> الموافق بالهجري: <span id="birth_date_hijri_val"></span>
                    </span>
                </div>
            </div>
        </div>

        {{-- مكان الميلاد --}}
        <div class="col-md-6 mb-3">
            <div class="form-group mb-0">
                <label for="birth_place" class="ppl-form-label">
                    <i class="fas fa-map-pin text-success"></i>
                    <span>مكان أو مدينة الميلاد</span>
                </label>
                <input type="text" class="form-control ppl-form-control" id="birth_place" name="birth_place"
                       value="{{ old('birth_place', $isEdit ? $person->birth_place : '') }}"
                       placeholder="مثال: الرياض، عنيزة، بريدة، جدة...">
            </div>
        </div>

        {{-- مكان الإقامة الحالي مع البحث التلقائي --}}
        <div class="col-md-6 mb-3">
            <div class="form-group mb-0 position-relative">
                <label for="location" class="ppl-form-label">
                    <i class="fas fa-home text-warning"></i>
                    <span>مكان الإقامة الحالي</span>
                </label>
                <input type="text" class="form-control ppl-form-control js-location-autocomplete" id="location" name="location"
                       value="{{ old('location', $isEdit ? ($person->location ? $person->location->display_name : '') : '') }}"
                       autocomplete="off" placeholder="اكتب للبحث عن مدينة أو حي...">
                <input type="hidden" id="location_id" name="location_id"
                       value="{{ old('location_id', $isEdit ? $person->location_id : '') }}">
                <div id="location_suggestions" class="list-group shadow-lg mt-1"
                     style="display: none; position: absolute; z-index: 1050; max-height: 220px; overflow-y: auto; width: 100%; border-radius: 10px;">
                </div>
            </div>
        </div>

        {{-- المهنة والعمل --}}
        <div class="col-md-6 mb-3">
            <div class="form-group mb-0">
                <label for="occupation" class="ppl-form-label">
                    <i class="fas fa-briefcase text-secondary"></i>
                    <span>المهنة أو مجال العمل</span>
                </label>
                <input type="text" class="form-control ppl-form-control" id="occupation" name="occupation"
                       value="{{ old('occupation', $isEdit ? $person->occupation : '') }}"
                       placeholder="مثال: مهندس، معلم، رائد أعمال، طبيب...">
            </div>
        </div>
    </div>
</div>

{{-- ====================================================================
     4. بطاقة سجل الوفاة (للمتوفين فقط مع مفتاح تبديل بديهي)
     ==================================================================== --}}
<div class="ppl-form-card" style="border-right: 4px solid #ef4444;">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div class="d-flex align-items-center gap-2">
            <i class="fas fa-dove text-danger fa-lg"></i>
            <h4 class="ppl-form-card-title mb-0">4. حالة الوفاة (رحمهم الله)</h4>
        </div>
        <div class="custom-control custom-switch" style="cursor: pointer;">
            <input type="checkbox" class="custom-control-input" id="is_deceased_toggle" name="is_deceased" value="1"
                   {{ $isDeceased ? 'checked' : '' }}>
            <label class="custom-control-label font-weight-bold" for="is_deceased_toggle" style="font-size: 1rem; cursor: pointer;">
                الشخص متوفى؟
            </label>
        </div>
    </div>

    <div id="deceased_fields_container" style="{{ $isDeceased ? 'display: block;' : 'display: none;' }}">
        <div class="alert alert-info py-2 px-3 mb-3 small">
            <i class="fas fa-info-circle ml-1"></i> يرجى تعبئة بيانات الوفاة والمقبرة لحفظها في السجل التاريخي للأسرة.
        </div>

        <div class="row">
            {{-- تاريخ الوفاة --}}
            <div class="col-md-6 mb-3">
                <div class="form-group mb-0">
                    <label for="death_date" class="ppl-form-label">
                        <i class="fas fa-calendar-times text-danger"></i>
                        <span>تاريخ الوفاة (ميلادي)</span>
                    </label>
                    <input type="date" class="form-control ppl-form-control" id="death_date" name="death_date"
                           value="{{ old('death_date', $isEdit && $person->death_date ? $person->death_date->format('Y-m-d') : '') }}">
                    
                    {{-- كبسولة المعاينة الهجرية التلقائية الفورية --}}
                    <div id="death_date_hijri_container" class="mt-2" style="display: none;">
                        <span class="ppl-badge ppl-badge-hijri py-2 px-3 font-weight-bold" style="font-size: 0.92rem;">
                            <i class="fas fa-moon ml-1"></i> الموافق بالهجري: <span id="death_date_hijri_val"></span>
                        </span>
                    </div>
                </div>
            </div>

            {{-- مكان الوفاة --}}
            <div class="col-md-6 mb-3">
                <div class="form-group mb-0">
                    <label for="death_place" class="ppl-form-label">
                        <i class="fas fa-map-marker text-danger"></i>
                        <span>مكان الوفاة</span>
                    </label>
                    <input type="text" class="form-control ppl-form-control" id="death_place" name="death_place"
                           value="{{ old('death_place', $isEdit ? $person->death_place : '') }}"
                           placeholder="مثال: الرياض، مكة المكرمة...">
                </div>
            </div>

            {{-- اسم المقبرة --}}
            <div class="col-md-4 mb-3">
                <div class="form-group mb-0">
                    <label for="cemetery" class="ppl-form-label">
                        <i class="fas fa-monument text-secondary"></i>
                        <span>اسم المقبرة</span>
                    </label>
                    <input type="text" class="form-control ppl-form-control" id="cemetery" name="cemetery"
                           value="{{ old('cemetery', $isEdit ? $person->cemetery : '') }}"
                           placeholder="مثال: مقبرة النسيم، مقبرة العود...">
                </div>
            </div>

            {{-- رقم القبر --}}
            <div class="col-md-4 mb-3">
                <div class="form-group mb-0">
                    <label for="grave_number" class="ppl-form-label">
                        <i class="fas fa-hashtag text-secondary"></i>
                        <span>رقم أو مربع القبر</span>
                    </label>
                    <input type="text" class="form-control ppl-form-control" id="grave_number" name="grave_number"
                           value="{{ old('grave_number', $isEdit ? $person->grave_number : '') }}"
                           placeholder="مثال: مربع 14 - قبر 52">
                </div>
            </div>

            {{-- لوكيشن القبر --}}
            <div class="col-md-4 mb-3">
                <div class="form-group mb-0">
                    <label for="cemetery_location" class="ppl-form-label">
                        <i class="fas fa-map-marked text-danger"></i>
                        <span>رابط خريطة القبر (GPS)</span>
                    </label>
                    <input type="url" class="form-control ppl-form-control" id="cemetery_location" name="cemetery_location"
                           value="{{ old('cemetery_location', $isEdit ? $person->cemetery_location : '') }}"
                           placeholder="https://maps.google.com/?q=...">
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ====================================================================
     5. بطاقة الصورة الشخصية والسيرة الذاتية مع مساعد الذكاء الاصطناعي
     ==================================================================== --}}
<div class="ppl-form-card">
    <div class="ppl-form-card-header">
        <i class="fas fa-camera text-primary fa-lg"></i>
        <h4 class="ppl-form-card-title">5. الصورة الشخصية والسيرة الذاتية</h4>
    </div>

    {{-- الصورة الشخصية ومعاينتها الفورية --}}
    <div class="row align-items-center mb-4">
        <div class="col-md-3 text-center mb-3 mb-md-0">
            <div class="position-relative d-inline-block">
                @if ($isEdit && $person->photo_url)
                    <img id="avatar_preview" src="{{ asset('storage/' . $person->photo_url) }}" alt="{{ $person->full_name }}" 
                         class="rounded-circle shadow border" style="width: 120px; height: 120px; object-fit: cover;">
                @else
                    <img id="avatar_preview" src="{{ asset('admin-assets/assets/avatars/default.png') }}" alt="معاينة الصورة" 
                         class="rounded-circle shadow-sm border" style="width: 120px; height: 120px; object-fit: cover;">
                @endif
            </div>
        </div>
        <div class="col-md-9">
            <label for="photo" class="ppl-form-label">
                <span>اختيار صورة شخصية جديدة</span>
            </label>
            <div class="custom-file mb-2">
                <input type="file" class="custom-file-input" id="photo" name="photo" accept="image/*" onchange="previewAvatarImage(this)">
                <label class="custom-file-label ppl-form-control" for="photo" style="line-height: 2;">اختر ملف صورة من جهازك...</label>
            </div>
            <small class="form-text text-muted">الصيغ المدعومة: JPG, PNG, WEBP بحجم أقصى 2 ميجابايت.</small>
        </div>
    </div>

    {{-- السيرة الذاتية ومساعد AI --}}
    <div class="form-group mb-0">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
            <label for="biography" class="ppl-form-label mb-0">
                <i class="fas fa-pen-nib text-primary"></i>
                <span>السيرة والنبذة التعريفية</span>
            </label>
            @if($isEdit && auth()->user()?->can('ai.generate.use'))
                <button type="button" class="btn btn-sm btn-outline-primary font-weight-bold" id="aiGenerateBiographyBtn"
                        data-url="{{ route('dashboard.ai.generate.biography', $person) }}" style="border-radius: 8px;">
                    <i class="fas fa-robot ml-1"></i> توليد سيرة بالذكاء الاصطناعي
                </button>
            @endif
        </div>
        <textarea class="form-control ppl-form-control" id="biography" name="biography" rows="5"
                  placeholder="اكتب نبذة مختصرة عن مسيرة الشخص وإنجازاته ومناصبه وذريته...">{{ old('biography', $isEdit ? $person->biography : '') }}</textarea>

        {{-- مسودة الـ AI التفاعلية --}}
        <div id="aiBiographyDraft" class="mt-3 d-none">
            <div class="alert alert-info shadow-sm p-3 mb-0" style="border-radius: 12px;">
                <div class="d-flex align-items-center font-weight-bold mb-2 text-primary" style="font-size: 1rem;">
                    <i class="fas fa-magic ml-2"></i> مسودة الذكاء الاصطناعي المقترحة:
                </div>
                <div id="aiBiographyOutput" class="small p-3 bg-white rounded border" style="white-space: pre-wrap; font-size: 0.95rem; line-height: 1.7; color: #1e293b;"></div>
                <div class="mt-3 d-flex gap-2">
                    <button type="button" class="btn btn-success btn-sm px-3 font-weight-bold" id="aiBiographyApproveBtn">
                        <i class="fas fa-check ml-1"></i> اعتماد وملء حقل السيرة
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm px-3 font-weight-bold" id="aiBiographyRejectBtn">
                        <i class="fas fa-times ml-1"></i> رفض المسودة
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- سكريبت تفاعلي خاص بالنموذج --}}
<script>
    function previewAvatarImage(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.getElementById('avatar_preview');
                if (preview) {
                    preview.src = e.target.result;
                }
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        // مفتاح إظهار/إخفاء حقول الوفاة
        const deceasedToggle = document.getElementById('is_deceased_toggle');
        const deceasedContainer = document.getElementById('deceased_fields_container');
        if (deceasedToggle && deceasedContainer) {
            deceasedToggle.addEventListener('change', function () {
                deceasedContainer.style.display = this.checked ? 'block' : 'none';
            });
        }

        // تحويل التاريخ الميلادي إلى هجري فوري
        const gregorianToHijri = (gregorianDate) => {
            if (!gregorianDate) return '';
            try {
                let trimmed = String(gregorianDate).trim().replace(/\//g, '-');
                if (!trimmed || trimmed.startsWith('0000')) return '';
                const normalized = trimmed.includes('T') ? trimmed : trimmed + 'T12:00:00Z';
                const date = new Date(normalized);
                if (isNaN(date.getTime())) return '';
                const formatter = new Intl.DateTimeFormat('ar-SA-u-ca-islamic-umalqura', {
                    timeZone: 'UTC',
                    day: 'numeric',
                    month: 'long',
                    year: 'numeric'
                });
                const parts = formatter.formatToParts(date);
                const day = parts.find(p => p.type === 'day')?.value || '';
                const month = parts.find(p => p.type === 'month')?.value || '';
                const year = parts.find(p => p.type === 'year')?.value || '';
                return `${day} ${month} ${year} هـ`;
            } catch (e) {
                return '';
            }
        };

        function updateHijriPreview(inputEl, containerEl, valEl) {
            if (!inputEl || !containerEl || !valEl) return;
            const val = inputEl.value;
            const hijri = gregorianToHijri(val);
            if (hijri) {
                valEl.textContent = hijri;
                containerEl.style.display = 'inline-block';
            } else {
                containerEl.style.display = 'none';
            }
        }

        const birthInput = document.getElementById('birth_date');
        const birthContainer = document.getElementById('birth_date_hijri_container');
        const birthVal = document.getElementById('birth_date_hijri_val');

        const deathInput = document.getElementById('death_date');
        const deathContainer = document.getElementById('death_date_hijri_container');
        const deathVal = document.getElementById('death_date_hijri_val');

        if (birthInput && birthContainer && birthVal) {
            birthInput.addEventListener('input', () => updateHijriPreview(birthInput, birthContainer, birthVal));
            updateHijriPreview(birthInput, birthContainer, birthVal);
        }

        if (deathInput && deathContainer && deathVal) {
            deathInput.addEventListener('input', () => updateHijriPreview(deathInput, deathContainer, deathVal));
            updateHijriPreview(deathInput, deathContainer, deathVal);
        }

        // تكامل توليد السيرة بالذكاء الاصطناعي
        const aiBtn = document.getElementById('aiGenerateBiographyBtn');
        if (aiBtn) {
            let generationId = null;
            const draftBox = document.getElementById('aiBiographyDraft');
            const outputEl = document.getElementById('aiBiographyOutput');
            const approveBtn = document.getElementById('aiBiographyApproveBtn');
            const rejectBtn = document.getElementById('aiBiographyRejectBtn');
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

            aiBtn.addEventListener('click', async () => {
                aiBtn.disabled = true;
                aiBtn.innerHTML = '<i class="fas fa-spinner fa-spin ml-1"></i> جاري التوليد...';
                try {
                    const res = await fetch(aiBtn.dataset.url, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'Content-Type': 'application/json',
                        },
                        body: '{}',
                    });
                    const data = await res.json();
                    if (!data.ok) {
                        alert(data.message || 'تعذر التوليد حالياً');
                        return;
                    }
                    generationId = data.generation.id;
                    outputEl.textContent = data.generation.output;
                    draftBox.classList.remove('d-none');
                } catch (e) {
                    alert('تعذر الاتصال بخدمة الذكاء الاصطناعي');
                } finally {
                    aiBtn.disabled = false;
                    aiBtn.innerHTML = '<i class="fas fa-robot ml-1"></i> توليد سيرة بالذكاء الاصطناعي';
                }
            });

            approveBtn?.addEventListener('click', async () => {
                if (!generationId) return;
                const res = await fetch(`{{ url('/dashboard/ai/generations') }}/${generationId}/approve`, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json' },
                    body: '{}',
                });
                const data = await res.json();
                if (data.ok) {
                    document.getElementById('biography').value = data.generation.output;
                    draftBox.classList.add('d-none');
                }
            });

            rejectBtn?.addEventListener('click', async () => {
                if (!generationId) {
                    draftBox.classList.add('d-none');
                    return;
                }
                await fetch(`{{ url('/dashboard/ai/generations') }}/${generationId}/reject`, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json' },
                    body: '{}',
                });
                draftBox.classList.add('d-none');
            });
        }
    });
</script>
