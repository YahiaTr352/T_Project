@extends('layouts.web-site.web')

@section('title', 'حسابي — ' . ($person['full_name'] ?? ''))

@push('styles')
<style>
    @keyframes profileFadeUp {
        from { opacity: 0; transform: translateY(24px); }
        to { opacity: 1; transform: translateY(0); }
    }
    @keyframes profileHeroGlow {
        0%, 100% { opacity: 0.45; transform: scale(1); }
        50% { opacity: 0.75; transform: scale(1.05); }
    }
    .profile-hero { position: relative; overflow: hidden; }
    .profile-hero-glow {
        position: absolute; inset: -20%;
        background: radial-gradient(circle at 30% 20%, rgba(55,160,92,.35), transparent 55%),
                    radial-gradient(circle at 80% 70%, rgba(20,81,71,.4), transparent 50%);
        animation: profileHeroGlow 8s ease-in-out infinite;
        pointer-events: none;
    }
    .profile-section { animation: profileFadeUp .6s ease both; }
    .profile-section:nth-child(2) { animation-delay: .08s; }
    .profile-section:nth-child(3) { animation-delay: .16s; }
    .profile-section:nth-child(4) { animation-delay: .24s; }
    .profile-tab.active { background: linear-gradient(135deg, #145147, #37a05c); color: #fff; }
    .profile-input {
        width: 100%; border: 1px solid #d1fae5; border-radius: .75rem;
        padding: .65rem 1rem; background: #fff; transition: border-color .2s, box-shadow .2s;
    }
    .profile-input:focus { outline: none; border-color: #37a05c; box-shadow: 0 0 0 3px rgba(55,160,92,.15); }
    .profile-card { background: rgba(255,255,255,.92); border: 1px solid rgba(55,160,92,.12); border-radius: 1.25rem; }
    .parent-lock { background: #f0fdf4; border: 1px dashed #86efac; border-radius: .75rem; padding: .75rem 1rem; }
    .spouse-type-label.active {
        background-color: #fff;
        box-shadow: 0 3px 10px rgba(20,81,71,0.12);
        color: #145147;
        font-weight: 700;
        border: 1px solid rgba(55,160,92,0.25);
    }
</style>
@endpush

@section('content')
@php
    $p = $person;
@endphp

<section class="profile-hero gradient-primary text-white py-14 md:py-20">
    <div class="profile-hero-glow"></div>
    <div class="container mx-auto px-4 relative z-10 max-w-5xl">
        <div class="flex flex-col md:flex-row items-center gap-8">
            <div class="relative">
                <img src="{{ $p['avatar'] }}" alt="{{ $p['full_name'] }}"
                    class="w-36 h-36 md:w-44 md:h-44 rounded-full object-cover ring-4 ring-white/30 shadow-2xl">
                <span class="absolute bottom-2 left-2 bg-white/90 text-emerald-800 text-xs font-bold px-3 py-1 rounded-full">
                    {{ $authMethod === 'face' ? 'بصمة الوجه' : 'واتساب' }}
                </span>
            </div>
            <div class="text-center md:text-right flex-1">
                <p class="text-emerald-100/90 text-sm mb-2 tracking-wide">صفحتي الشخصية في شجرة العائلة</p>
                <h1 class="text-3xl md:text-5xl font-extrabold mb-3" style="font-family: 'Alexandria', sans-serif;">{{ $p['full_name'] }}</h1>
                <p class="text-emerald-50/90 max-w-xl">{{ $p['occupation'] ?: 'حدّث بياناتك وابقَ على تواصل مع العائلة' }}</p>
                <div class="flex flex-wrap gap-3 justify-center md:justify-start mt-6">
                    <a href="{{ route('people.profile.show', $p['id']) }}" class="inline-flex items-center gap-2 bg-white/15 hover:bg-white/25 px-4 py-2 rounded-xl transition">
                        <i class="fas fa-eye"></i> عرض الملف العام
                    </a>
                    <form action="{{ route('site.identity.logout') }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-2 bg-red-500/80 hover:bg-red-500 px-4 py-2 rounded-xl transition">
                            <i class="fas fa-sign-out-alt"></i> تسجيل الخروج
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="container mx-auto px-4 max-w-5xl -mt-8 relative z-20 pb-16">
    @if(session('success'))
        <div class="mb-6 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl px-4 py-3">{{ session('success') }}</div>
    @endif
    @if(isset($pendingRequests) && $pendingRequests->isNotEmpty())
        <div class="mb-6 bg-amber-50 border border-amber-200 text-amber-900 rounded-xl p-4 shadow-sm">
            <div class="flex items-center gap-3">
                <i class="fas fa-clock text-amber-600 text-xl"></i>
                <div class="flex-1">
                    <p class="font-bold text-sm md:text-base">لديك {{ $pendingRequests->count() }} طلب(ات) تعديل قيد مراجعة واعتماد الإدارة</p>
                    <div class="flex flex-wrap gap-2 mt-2">
                        @foreach($pendingRequests as $pendingReq)
                            <span class="inline-flex items-center gap-1 bg-amber-100/80 text-amber-800 text-xs px-2.5 py-1 rounded-lg">
                                <i class="fas {{ $pendingReq->change_type_icon }} text-[10px]"></i>
                                {{ $pendingReq->change_type_label }}
                            </span>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endif
    @if($errors->any())
        <div class="mb-6 bg-red-50 border border-red-200 text-red-800 rounded-xl px-4 py-3">
            <ul class="list-disc list-inside text-sm">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    {{-- الأب والأم — للعرض فقط --}}
    <div class="profile-section profile-card p-6 mb-6 shadow-lg">
        <h2 class="text-xl font-bold text-emerald-900 mb-4"><i class="fas fa-users ml-2 text-emerald-600"></i>النسب (للعرض فقط)</h2>
        <div class="grid md:grid-cols-2 gap-4">
            <div class="parent-lock">
                <p class="text-xs text-emerald-600 mb-1">الأب</p>
                <p class="font-semibold text-gray-800">{{ $parents['father']['full_name'] ?? '—' }}</p>
            </div>
            <div class="parent-lock">
                <p class="text-xs text-emerald-600 mb-1">الأم</p>
                <p class="font-semibold text-gray-800">{{ $parents['mother']['full_name'] ?? '—' }}</p>
            </div>
        </div>
        <p class="text-xs text-gray-500 mt-3">لا يمكن تعديل بيانات الأب أو الأم من هذه الصفحة.</p>
    </div>

    {{-- تبويبات --}}
    {{-- تبويبات --}}
    <div class="profile-section flex flex-wrap gap-2 mb-6" id="profileTabs">
        @foreach(['basic'=>'بياناتي','contacts'=>'التواصل','children'=>'أبنائي','marriages'=>'الزواج','locations'=>'أماكني','privacy'=>'الخصوصية'] as $key => $label)
            <button type="button" data-tab="{{ $key }}" class="profile-tab px-4 py-2 rounded-xl text-sm font-semibold bg-white text-emerald-800 border border-emerald-100 shadow-sm transition {{ $loop->first ? 'active' : '' }}">{{ $label }}</button>
        @endforeach
    </div>

    {{-- بياناتي --}}
    <div class="profile-section profile-card p-6 shadow-lg tab-panel" data-panel="basic">
        <form action="{{ route('my-profile.update') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf @method('PUT')
            <div class="grid md:grid-cols-2 gap-4">
                <div class="md:col-span-2 p-3 bg-gray-50 border border-gray-200 rounded-xl flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div>
                        <span class="text-xs font-semibold text-gray-500 block mb-0.5"><i class="fas fa-lock text-emerald-600 ml-1"></i>الاسم الكامل (موثق رسمي في الشجرة)</span>
                        <span class="font-bold text-gray-800 text-base">{{ $p['full_name'] }}</span>
                    </div>
                    <span class="text-xs text-amber-800 bg-amber-50 border border-amber-200 px-3 py-1.5 rounded-lg inline-flex items-center gap-1.5">
                        <i class="fas fa-shield-alt text-amber-600"></i>
                        <span>الاسم غير قابل للتعديل (يرجى مراجعة إدارة العائلة للتصحيح)</span>
                    </span>
                </div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">تاريخ الميلاد</label>
                    <input type="date" name="birth_date" value="{{ old('birth_date', $p['birth_date']) }}" class="profile-input"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">مكان الميلاد</label>
                    <input type="text" name="birth_place" value="{{ old('birth_place', $p['birth_place']) }}" class="profile-input"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">المهنة</label>
                    <input type="text" name="occupation" value="{{ old('occupation', $p['occupation']) }}" class="profile-input"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">الصورة الشخصية</label>
                    <input type="file" name="photo" accept="image/*" class="profile-input"></div>
            </div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">نبذة</label>
                <textarea name="biography" rows="3" class="profile-input">{{ old('biography', $p['biography']) }}</textarea></div>
            <details class="border border-gray-100 rounded-xl p-4">
                <summary class="cursor-pointer font-semibold text-gray-700">بيانات الوفاة (اختياري)</summary>
                <div class="grid md:grid-cols-2 gap-4 mt-4">
                    <div><label class="block text-sm mb-1">تاريخ الوفاة</label><input type="date" name="death_date" value="{{ old('death_date', $p['death_date']) }}" class="profile-input"></div>
                    <div><label class="block text-sm mb-1">مكان الوفاة</label><input type="text" name="death_place" value="{{ old('death_place', $p['death_place']) }}" class="profile-input"></div>
                    <div><label class="block text-sm mb-1">المقبرة</label><input type="text" name="cemetery" value="{{ old('cemetery', $p['cemetery']) }}" class="profile-input"></div>
                    <div><label class="block text-sm mb-1">موقع القبر</label><input type="text" name="cemetery_location" value="{{ old('cemetery_location', $p['cemetery_location']) }}" class="profile-input"></div>
                    <div><label class="block text-sm mb-1">رقم القبر</label><input type="text" name="grave_number" value="{{ old('grave_number', $p['grave_number']) }}" class="profile-input"></div>
                </div>
            </details>
            <button type="submit" class="gradient-primary text-white px-6 py-3 rounded-xl font-bold hover:opacity-90 transition">حفظ البيانات</button>
        </form>
    </div>

    {{-- التواصل --}}
    <div class="profile-section profile-card p-6 shadow-lg tab-panel hidden" data-panel="contacts">
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-3 mb-4 text-sm text-amber-900">
            <strong>ملاحظة:</strong> رقم الهاتف (<code>phone</code>) ≠ واتساب (<code>whatsapp</code>). الدخول عبر OTP يتطلب حساب واتساب مسجّل.
        </div>
        <div class="space-y-3 mb-6">
            @forelse($contacts as $contact)
                @php
                    $cVis = $contact['visibility'] ?? 'family';
                @endphp
                <div class="flex flex-wrap items-center justify-between gap-3 border border-emerald-50 rounded-xl p-3 bg-emerald-50/40">
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-xs font-bold text-emerald-700 uppercase">{{ $contact['type'] }}</span>
                            @if($cVis === 'public')
                                <span class="bg-green-100 text-green-800 text-[11px] font-bold px-2 py-0.5 rounded-full"><i class="fas fa-globe ml-1"></i>مفتوح للجميع</span>
                            @elseif($cVis === 'private')
                                <span class="bg-rose-100 text-rose-800 text-[11px] font-bold px-2 py-0.5 rounded-full"><i class="fas fa-lock ml-1"></i>مخفي نهائياً</span>
                            @else
                                <span class="bg-blue-100 text-blue-800 text-[11px] font-bold px-2 py-0.5 rounded-full"><i class="fas fa-users ml-1"></i>العائلة فقط</span>
                            @endif
                        </div>
                        <p class="font-semibold text-gray-800 font-mono">{{ $contact['value'] }}</p>
                        @if($contact['label'])<p class="text-xs text-gray-500">{{ $contact['label'] }}</p>@endif
                    </div>
                    <form action="{{ route('my-profile.contacts.destroy', $contact['id']) }}" method="POST" onsubmit="return confirm('حذف هذا الحساب؟')">
                        @csrf @method('DELETE')
                        <button class="text-red-600 text-sm hover:underline">حذف</button>
                    </form>
                </div>
            @empty
                <p class="text-gray-500 text-sm">لا توجد حسابات تواصل بعد.</p>
            @endforelse
        </div>
        <form action="{{ route('my-profile.contacts.store') }}" method="POST" class="grid md:grid-cols-5 gap-3 items-end border-t pt-4">
            @csrf
            <div><label class="text-sm font-medium">النوع</label>
                <select name="type" class="profile-input" required>
                    @foreach(['phone','whatsapp','email','facebook','instagram','twitter','linkedin','telegram','other'] as $t)
                        <option value="{{ $t }}">{{ $t }}</option>
                    @endforeach
                </select></div>
            <div class="md:col-span-2"><label class="text-sm font-medium">القيمة</label><input name="value" class="profile-input" required></div>
            <div><label class="text-sm font-medium">مستوى الظهور</label>
                <select name="visibility" class="profile-input">
                    <option value="family" selected>أبناء العائلة فقط</option>
                    <option value="public">مفتوح للجميع (العامة والأنساب)</option>
                    <option value="private">مخفي نهائياً (الإدارة وأنت فقط)</option>
                </select></div>
            <div><button type="submit" class="w-full gradient-secondary text-white py-3 rounded-xl font-bold">إضافة</button></div>
        </form>
    </div>

    {{-- الأبناء --}}
    <div class="profile-section profile-card p-6 shadow-lg tab-panel hidden" data-panel="children">
        <div class="mb-4 p-3.5 bg-emerald-50/80 border border-emerald-200 text-emerald-950 rounded-xl text-xs flex items-center gap-2.5">
            <i class="fas fa-shield-alt text-emerald-600 text-base flex-shrink-0"></i>
            <span>أسماء الأبناء تُعتمد وتوثق في شجرة العائلة بعد الإضافة ولا يمكن تعديلها من هنا. في حال وجود خطأ مطبعي في اسم ابن أو ابنة، يرجى التواصل مع إدارة العائلة.</span>
        </div>
        @if($children->count())
            <div class="grid sm:grid-cols-2 gap-3 mb-6">
                @foreach($children as $child)
                    <div class="flex items-center gap-3 p-3 rounded-xl bg-gray-50">
                        <img src="{{ $child['avatar'] }}" class="w-12 h-12 rounded-full object-cover" alt="">
                        <div><p class="font-bold">{{ $child['full_name'] }}</p><p class="text-xs text-gray-500">{{ $child['birth_date'] ?? '' }}</p></div>
                    </div>
                @endforeach
            </div>
        @endif
        <form action="{{ route('my-profile.children.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4 border-t pt-4">
            @csrf
            <h3 class="font-bold text-emerald-900">إضافة ابن / ابنة</h3>
            <div class="grid md:grid-cols-2 gap-4">
                <div><label class="text-sm">الاسم</label><input name="first_name" class="profile-input" required></div>
                <div><label class="text-sm">الجنس</label>
                    <select name="gender" class="profile-input" required><option value="male">ذكر</option><option value="female">أنثى</option></select></div>
                <div><label class="text-sm">تاريخ الميلاد</label><input type="date" name="birth_date" class="profile-input"></div>
                @if($p['gender'] === 'male')
                    <div><label class="text-sm">الأم</label>
                        <select name="mother_id" class="profile-input" required>
                            <option value="">— اختر —</option>
                            @foreach($wifeCandidates as $wife)
                                <option value="{{ $wife->id }}">{{ $wife->full_name }}</option>
                            @endforeach
                        </select></div>
                @else
                    <div><label class="text-sm">الأب</label>
                        @if(isset($husbandCandidates) && $husbandCandidates->isNotEmpty())
                            <select name="parent_id" class="profile-input" required>
                                <option value="">— اختر الأب من الأزواج المسجلين —</option>
                                @foreach($husbandCandidates as $candidate)
                                    <option value="{{ $candidate->id }}">
                                        {{ $candidate->full_name }} @if(!empty($candidate->status_text))({{ $candidate->status_text }})@endif
                                    </option>
                                @endforeach
                            </select>
                        @else
                            <div class="p-2.5 bg-amber-50 border border-amber-200 rounded-lg text-amber-800 text-xs mt-1 leading-relaxed">
                                ⚠️ لا يوجد أزواج مسجلون في ملفكِ الشخصي. يرجى توثيق سجل الزواج أولاً من تبويب "سجلات الزواج" لتتمكني من إضافة الأبناء ونسبهم لوالدهم.
                            </div>
                        @endif
                    </div>
                @endif
            </div>
            <button type="submit" class="gradient-primary text-white px-6 py-3 rounded-xl font-bold">إضافة</button>
        </form>
    </div>

    {{-- الزواج --}}
    <div class="profile-section profile-card p-6 shadow-lg tab-panel hidden" data-panel="marriages">
        @foreach($marriages as $marriage)
            <div class="border border-emerald-50 rounded-xl p-4 mb-4">
                <p class="font-bold">{{ $marriage['spouse']['full_name'] ?? '—' }}
                    @if(!empty($marriage['order_label']))
                        <span class="text-xs bg-emerald-100 text-emerald-800 px-2 py-1 rounded-full mr-2">{{ $marriage['order_label'] }}</span>
                    @endif
                </p>
                <p class="text-sm text-gray-500">{{ $marriage['status'] }} — {{ $marriage['married_at'] ?? '' }}</p>
                <form action="{{ route('my-profile.marriages.update', $marriage['id']) }}" method="POST" class="grid md:grid-cols-4 gap-3 mt-3 items-end">
                    @csrf @method('PUT')
                    <div><label class="text-xs">تاريخ الزواج</label><input type="date" name="married_at" value="{{ $marriage['married_at'] }}" class="profile-input"></div>
                    <div><label class="text-xs">تاريخ الطلاق</label><input type="date" name="divorced_at" value="{{ $marriage['divorced_at'] }}" class="profile-input"></div>
                    <div><label class="text-xs">ترتيب الزوجة (1 = الأولى)</label><input type="number" name="sort_order" min="1" max="100" value="{{ $marriage['sort_order'] }}" placeholder="تلقائي" class="profile-input"></div>
                    <div class="flex items-center gap-2"><input type="checkbox" name="is_divorced" value="1" {{ $marriage['is_divorced'] ? 'checked' : '' }}><label class="text-sm">منفصل</label></div>
                    <button class="md:col-span-4 text-emerald-700 text-sm font-bold">تحديث (طلب مراجعة)</button>
                </form>
            </div>
        @endforeach
        <form action="{{ route('my-profile.marriages.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4 border-t pt-4 mt-6">
            @csrf
            <h3 class="font-bold text-emerald-900 text-lg flex items-center gap-2">
                <i class="fas fa-heart text-pink-500"></i>
                إضافة زواج جديد
            </h3>

            {{-- اختيار نوع الزوجة / الزوج --}}
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">نوع التسجيل</label>
                <div class="grid grid-cols-3 gap-2 p-1.5 bg-emerald-50/70 rounded-xl border border-emerald-100">
                    <label class="cursor-pointer flex items-center justify-center gap-1.5 md:gap-2 p-2.5 rounded-lg font-medium text-xs md:text-sm text-center transition spouse-type-label active" data-type="existing">
                        <input type="radio" name="spouse_type" value="existing" checked class="hidden" onchange="toggleSpouseType('existing')">
                        <i class="fas fa-tree text-emerald-700"></i>
                        <span>من شجرة العائلة</span>
                    </label>
                    <label class="cursor-pointer flex items-center justify-center gap-1.5 md:gap-2 p-2.5 rounded-lg font-medium text-xs md:text-sm text-center transition spouse-type-label" data-type="outside_family">
                        <input type="radio" name="spouse_type" value="outside_family" class="hidden" onchange="toggleSpouseType('outside_family')">
                        <i class="fas fa-globe text-amber-600"></i>
                        <span>من خارج العائلة</span>
                    </label>
                    <label class="cursor-pointer flex items-center justify-center gap-1.5 md:gap-2 p-2.5 rounded-lg font-medium text-xs md:text-sm text-center transition spouse-type-label" data-type="inside_family">
                        <input type="radio" name="spouse_type" value="inside_family" class="hidden" onchange="toggleSpouseType('inside_family')">
                        <i class="fas fa-user-plus text-blue-600"></i>
                        <span>من العائلة (غير مسجلة)</span>
                    </label>
                </div>
            </div>

            {{-- الحالة 1: من شجرة العائلة --}}
            <div id="spouse_panel_existing" class="space-y-3">
                @if($p['gender'] === 'male')
                    <div>
                        <div class="flex justify-between items-center mb-1">
                            <label class="text-sm font-medium text-gray-700">اختر الزوجة من الشجرة</label>
                            <span class="text-xs text-emerald-600 font-medium">متاحة في شجرة العائلة</span>
                        </div>
                        <input type="text" id="spouse_search_input" placeholder="ابحث في الأسماء للتصفية السريعة..." oninput="filterSpouseSelect(this.value)" class="profile-input mb-2 text-sm bg-gray-50/50">
                        <select name="wife_id" id="spouse_select_existing" class="profile-input" required>
                            <option value="">— اختر الزوجة —</option>
                            @foreach($spouseCandidates as $c)
                                <option value="{{ $c->id }}">{{ $c->full_name }}</option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <div>
                        <label class="text-sm font-medium text-gray-700 mb-1 block">اختر الزوج من الشجرة</label>
                        <select name="husband_id" id="spouse_select_existing" class="profile-input" required>
                            <option value="">— اختر الزوج —</option>
                            @foreach($spouseCandidates as $c)
                                <option value="{{ $c->id }}">{{ $c->full_name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>

            {{-- الحالة 2: من خارج العائلة --}}
            <div id="spouse_panel_outside" class="hidden space-y-4">
                <div class="p-3 bg-amber-50/70 border border-amber-200 rounded-xl text-xs text-amber-900">
                    <i class="fas fa-info-circle ml-1 text-amber-600"></i>
                    سيتم تسجيل الزوجة كعضو مرتبط من خارج العائلة وتوثيق عقد الزواج بعد مراجعة واعتماد الإدارة.
                </div>
                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">اسم الزوجة الأول <span class="text-red-500">*</span></label>
                        <input type="text" name="first_name" id="outside_first_name" disabled placeholder="مثال: نورة" class="profile-input">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">اسم العائلة / القبيلة</label>
                        <input type="text" name="last_name" id="outside_last_name" disabled placeholder="مثال: آل فلان أو الغامدي" class="profile-input">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">تاريخ الميلاد (اختياري)</label>
                        <input type="date" name="birth_date" id="outside_birth_date" disabled class="profile-input">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">الصورة الشخصية (اختياري)</label>
                        <input type="file" name="photo" id="outside_photo" disabled accept="image/*" class="profile-input">
                    </div>
                </div>
            </div>

            {{-- الحالة 3: من داخل العائلة ولكن غير مسجلة --}}
            <div id="spouse_panel_inside" class="hidden space-y-4">
                <div class="p-3 bg-blue-50/70 border border-blue-200 rounded-xl text-xs text-blue-900">
                    <i class="fas fa-info-circle ml-1 text-blue-600"></i>
                    إذا كانت الزوجة من أبناء العائلة ولكن لم تتم إضافتها للشجرة بعد، يمكنك تسجيل اسمها واختيار والدها لربط نسبها الصحيح في الشجرة.
                </div>
                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">اسم الزوجة الأول <span class="text-red-500">*</span></label>
                        <input type="text" name="first_name" id="inside_first_name" disabled placeholder="مثال: مريم" class="profile-input">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">والدها في شجرة العائلة <span class="text-red-500">*</span></label>
                        <select name="parent_id" id="inside_parent_id" disabled class="profile-input">
                            <option value="">— اختر الأب من رجال العائلة —</option>
                            @foreach($fatherCandidates as $father)
                                <option value="{{ $father->id }}">{{ $father->full_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">تاريخ الميلاد (اختياري)</label>
                        <input type="date" name="birth_date" id="inside_birth_date" disabled class="profile-input">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">الصورة الشخصية (اختياري)</label>
                        <input type="file" name="photo" id="inside_photo" disabled accept="image/*" class="profile-input">
                    </div>
                </div>
            </div>

            {{-- تاريخ الزواج + الترتيب المشترك بين الحالات --}}
            <div class="grid md:grid-cols-2 gap-4 border-t pt-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">تاريخ الزواج</label>
                    <input type="date" name="married_at" class="profile-input">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ترتيب الزوجة (1 = الأولى — فاضي = تلقائي بالتاريخ)</label>
                    <input type="number" name="sort_order" min="1" max="100" placeholder="مثال: 1" class="profile-input">
                </div>
            </div>

            <button type="submit" class="gradient-secondary text-white px-8 py-3 rounded-xl font-bold hover:opacity-90 transition shadow-md">
                <i class="fas fa-paper-plane ml-1"></i> إرسال طلب إضافة الزواج
            </button>
        </form>
    </div>

    {{-- الأماكن --}}
    <div class="profile-section profile-card p-6 shadow-lg tab-panel hidden" data-panel="locations">
        @foreach($locations as $loc)
            <div class="flex justify-between items-center border border-gray-100 rounded-xl p-3 mb-2">
                <div><p class="font-bold">{{ $loc['location_name'] }}</p><p class="text-xs text-gray-500">{{ $loc['label'] }}</p></div>
                <form action="{{ route('my-profile.locations.destroy', $loc['id']) }}" method="POST">@csrf @method('DELETE')
                    <button class="text-red-600 text-sm">حذف</button></form>
            </div>
        @endforeach
        <form action="{{ route('my-profile.locations.store') }}" method="POST" class="grid md:grid-cols-3 gap-3 items-end border-t pt-4">
            @csrf
            <div><label class="text-sm">المكان</label>
                <select name="location_id" class="profile-input" required>
                    @foreach($locationsList as $l)<option value="{{ $l->id }}">{{ $l->name }}</option>@endforeach
                </select></div>
            <div><label class="text-sm">تسمية</label><input name="label" class="profile-input" placeholder="المنزل / العمل"></div>
            <div><label class="text-sm">رابط</label><input name="url" type="url" class="profile-input" placeholder="https://"></div>
            <button type="submit" class="gradient-primary text-white py-3 rounded-xl font-bold md:col-span-3">إضافة موقع</button>
        </form>
    </div>

    {{-- الخصوصية والظهور --}}
    @php
        $priv = $privacy_settings ?? [
            'contacts' => 'family',
            'photo' => 'public',
            'birth_date' => 'family',
            'location' => 'public',
            'biography' => 'public',
            'occupation' => 'public',
        ];
    @endphp
    <div class="profile-section profile-card p-6 shadow-lg tab-panel hidden" data-panel="privacy">
        <div class="flex items-center gap-3 mb-6 pb-4 border-b border-emerald-100">
            <div class="w-12 h-12 rounded-2xl bg-emerald-100 text-emerald-800 flex items-center justify-center text-xl shadow-inner">
                <i class="fas fa-user-shield"></i>
            </div>
            <div>
                <h2 class="text-xl font-bold text-emerald-950">إعدادات الخصوصية والظهور</h2>
                <p class="text-xs text-gray-500">تحكّم بمن يمكنه رؤية وسائل تواصلك، صورتك، وبياناتك في الشجرة والموقع والتطبيق</p>
            </div>
        </div>

        {{-- تنبيه الاسم غير قابل للإخفاء --}}
        <div class="mb-6 bg-emerald-50/70 border border-emerald-200 rounded-2xl p-4 flex items-start gap-3">
            <i class="fas fa-lock text-emerald-600 mt-1"></i>
            <div class="text-sm">
                <span class="font-bold text-emerald-900 block mb-0.5">الاسم الكامل: ثابت للجميع (مقفل)</span>
                <p class="text-emerald-800/90 text-xs">
                    يظل الاسم الكامل ({{ $p['full_name'] }}) ظاهراً دائماً لكافة أعضاء العائلة والزوار لضمان دقة وتكامل شجرة العائلة ولا يمكن إخفاؤه.
                </p>
            </div>
        </div>

        <form action="{{ route('my-profile.privacy.update') }}" method="POST" class="space-y-6">
            @csrf

            <div class="grid md:grid-cols-2 gap-4">
                {{-- 1. وسائل التواصل الإجمالية --}}
                <div class="bg-gray-50/70 border border-gray-200/80 rounded-2xl p-4 transition hover:border-emerald-300">
                    <div class="flex items-center gap-2 mb-3">
                        <i class="fas fa-address-book text-emerald-600 text-base"></i>
                        <span class="font-bold text-gray-800 text-sm">قسم وسائل التواصل (إجمالاً)</span>
                    </div>
                    <p class="text-xs text-gray-500 mb-3">الظهور العام لجميع أرقامك وروابطك</p>
                    <select name="contacts" class="profile-input bg-white text-sm">
                        <option value="family" {{ ($priv['contacts'] ?? 'family') === 'family' ? 'selected' : '' }}>أبناء العائلة فقط (افتراضي)</option>
                        <option value="public" {{ ($priv['contacts'] ?? '') === 'public' ? 'selected' : '' }}>مفتوح للجميع (العامة والأنساب)</option>
                        <option value="private" {{ ($priv['contacts'] ?? '') === 'private' ? 'selected' : '' }}>مخفي نهائياً (الإدارة وأنت فقط)</option>
                    </select>
                </div>

                {{-- 2. الصورة الشخصية --}}
                <div class="bg-gray-50/70 border border-gray-200/80 rounded-2xl p-4 transition hover:border-emerald-300">
                    <div class="flex items-center gap-2 mb-3">
                        <i class="fas fa-camera text-emerald-600 text-base"></i>
                        <span class="font-bold text-gray-800 text-sm">الصورة الشخصية</span>
                    </div>
                    <p class="text-xs text-gray-500 mb-3">في حال إخفائها تظهر الأيقونة الرمزية التلقائية</p>
                    <select name="photo" class="profile-input bg-white text-sm">
                        <option value="public" {{ ($priv['photo'] ?? 'public') === 'public' ? 'selected' : '' }}>مفتوحة للجميع (العامة والأنساب)</option>
                        <option value="family" {{ ($priv['photo'] ?? '') === 'family' ? 'selected' : '' }}>أبناء العائلة فقط</option>
                        <option value="private" {{ ($priv['photo'] ?? '') === 'private' ? 'selected' : '' }}>مخفية نهائياً (الإدارة وأنت فقط)</option>
                    </select>
                </div>

                {{-- 3. تاريخ الميلاد والعمر --}}
                <div class="bg-gray-50/70 border border-gray-200/80 rounded-2xl p-4 transition hover:border-emerald-300">
                    <div class="flex items-center gap-2 mb-3">
                        <i class="fas fa-birthday-cake text-emerald-600 text-base"></i>
                        <span class="font-bold text-gray-800 text-sm">تاريخ الميلاد والعمر</span>
                    </div>
                    <p class="text-xs text-gray-500 mb-3">تاريخ ميلادك ومكان الميلاد وسنوات العمر</p>
                    <select name="birth_date" class="profile-input bg-white text-sm">
                        <option value="family" {{ ($priv['birth_date'] ?? 'family') === 'family' ? 'selected' : '' }}>أبناء العائلة فقط</option>
                        <option value="public" {{ ($priv['birth_date'] ?? '') === 'public' ? 'selected' : '' }}>مفتوح للجميع (العامة والأنساب)</option>
                        <option value="private" {{ ($priv['birth_date'] ?? '') === 'private' ? 'selected' : '' }}>مخفي نهائياً (الإدارة وأنت فقط)</option>
                    </select>
                </div>

                {{-- 4. مكان الإقامة والمواقع --}}
                <div class="bg-gray-50/70 border border-gray-200/80 rounded-2xl p-4 transition hover:border-emerald-300">
                    <div class="flex items-center gap-2 mb-3">
                        <i class="fas fa-map-marker-alt text-emerald-600 text-base"></i>
                        <span class="font-bold text-gray-800 text-sm">مكان الإقامة والمواقع</span>
                    </div>
                    <p class="text-xs text-gray-500 mb-3">المدينة والحي والروابط الجغرافية لمواقعك</p>
                    <select name="location" class="profile-input bg-white text-sm">
                        <option value="public" {{ ($priv['location'] ?? 'public') === 'public' ? 'selected' : '' }}>مفتوح للجميع (العامة والأنساب)</option>
                        <option value="family" {{ ($priv['location'] ?? '') === 'family' ? 'selected' : '' }}>أبناء العائلة فقط</option>
                        <option value="private" {{ ($priv['location'] ?? '') === 'private' ? 'selected' : '' }}>مخفي نهائياً (الإدارة وأنت فقط)</option>
                    </select>
                </div>

                {{-- 5. النبذة التعريفية --}}
                <div class="bg-gray-50/70 border border-gray-200/80 rounded-2xl p-4 transition hover:border-emerald-300">
                    <div class="flex items-center gap-2 mb-3">
                        <i class="fas fa-align-right text-emerald-600 text-base"></i>
                        <span class="font-bold text-gray-800 text-sm">النبذة الشخصية</span>
                    </div>
                    <p class="text-xs text-gray-500 mb-3">السيرة الموجزة والنبذة المكتوبة في ملفك</p>
                    <select name="biography" class="profile-input bg-white text-sm">
                        <option value="public" {{ ($priv['biography'] ?? 'public') === 'public' ? 'selected' : '' }}>مفتوحة للجميع (العامة والأنساب)</option>
                        <option value="family" {{ ($priv['biography'] ?? '') === 'family' ? 'selected' : '' }}>أبناء العائلة فقط</option>
                        <option value="private" {{ ($priv['biography'] ?? '') === 'private' ? 'selected' : '' }}>مخفية نهائياً (الإدارة وأنت فقط)</option>
                    </select>
                </div>

                {{-- 6. المهنة والعمل --}}
                <div class="bg-gray-50/70 border border-gray-200/80 rounded-2xl p-4 transition hover:border-emerald-300">
                    <div class="flex items-center gap-2 mb-3">
                        <i class="fas fa-briefcase text-emerald-600 text-base"></i>
                        <span class="font-bold text-gray-800 text-sm">المهنة ومجال العمل</span>
                    </div>
                    <p class="text-xs text-gray-500 mb-3">المسمى الوظيفي ومجال تخصصك المهني</p>
                    <select name="occupation" class="profile-input bg-white text-sm">
                        <option value="public" {{ ($priv['occupation'] ?? 'public') === 'public' ? 'selected' : '' }}>مفتوحة للجميع (العامة والأنساب)</option>
                        <option value="family" {{ ($priv['occupation'] ?? '') === 'family' ? 'selected' : '' }}>أبناء العائلة فقط</option>
                        <option value="private" {{ ($priv['occupation'] ?? '') === 'private' ? 'selected' : '' }}>مخفية نهائياً (الإدارة وأنت فقط)</option>
                    </select>
                </div>
            </div>

            {{-- تخصيص كل حساب تواصل مسجّل --}}
            @if(count($contacts) > 0)
                <div class="mt-8 pt-6 border-t border-gray-100">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="font-bold text-gray-900 text-base">تخصيص مستوى الظهور لكل حساب تواصل</h3>
                            <p class="text-xs text-gray-500">يمكنك تحديد خصوصية مستقلة لكل رقم أو بريد على حدة</p>
                        </div>
                    </div>

                    <div class="space-y-3">
                        @foreach($contacts as $c)
                            @php
                                $vis = $c['visibility'] ?? 'family';
                            @endphp
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3.5 bg-gray-50/80 border border-gray-200/70 rounded-xl">
                                <div class="flex items-center gap-3">
                                    <span class="text-xs font-bold uppercase px-2.5 py-1 rounded-lg bg-emerald-100/70 text-emerald-800">{{ $c['type'] }}</span>
                                    <div>
                                        <p class="font-mono text-sm font-semibold text-gray-800">{{ $c['value'] }}</p>
                                        @if(!empty($c['label']))
                                            <p class="text-xs text-gray-400">{{ $c['label'] }}</p>
                                        @endif
                                    </div>
                                </div>
                                <div class="w-full sm:w-56">
                                    <select name="contact_visibilities[{{ $c['id'] }}]" class="profile-input bg-white text-xs py-1.5">
                                        <option value="family" {{ $vis === 'family' ? 'selected' : '' }}>أبناء العائلة فقط</option>
                                        <option value="public" {{ $vis === 'public' ? 'selected' : '' }}>مفتوح للجميع (الأنساب والعامة)</option>
                                        <option value="private" {{ $vis === 'private' ? 'selected' : '' }}>مخفي نهائياً (الإدارة وأنت فقط)</option>
                                    </select>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="pt-4 flex justify-end">
                <button type="submit" class="gradient-primary text-white px-8 py-3 rounded-xl font-bold hover:opacity-95 transition shadow-lg flex items-center gap-2">
                    <i class="fas fa-check-circle"></i>
                    حفظ إعدادات الخصوصية (تطبيق فوري)
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('#profileTabs .profile-tab').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('#profileTabs .profile-tab').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const tab = btn.dataset.tab;
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.toggle('hidden', p.dataset.panel !== tab));
    });
});

function toggleSpouseType(type) {
    document.querySelectorAll('.spouse-type-label').forEach(lbl => {
        lbl.classList.toggle('active', lbl.dataset.type === type);
    });

    const panelExisting = document.getElementById('spouse_panel_existing');
    const panelOutside = document.getElementById('spouse_panel_outside');
    const panelInside = document.getElementById('spouse_panel_inside');

    if (panelExisting) panelExisting.classList.toggle('hidden', type !== 'existing');
    if (panelOutside) panelOutside.classList.toggle('hidden', type !== 'outside_family');
    if (panelInside) panelInside.classList.toggle('hidden', type !== 'inside_family');

    const selectExisting = document.getElementById('spouse_select_existing');
    const outsideInputs = panelOutside ? panelOutside.querySelectorAll('input, select') : [];
    const insideInputs = panelInside ? panelInside.querySelectorAll('input, select') : [];

    if (type === 'existing') {
        if (selectExisting) {
            selectExisting.disabled = false;
            selectExisting.required = true;
        }
        outsideInputs.forEach(i => { i.disabled = true; i.required = false; });
        insideInputs.forEach(i => { i.disabled = true; i.required = false; });
    } else if (type === 'outside_family') {
        if (selectExisting) {
            selectExisting.disabled = true;
            selectExisting.required = false;
        }
        outsideInputs.forEach(i => {
            i.disabled = false;
            if (i.id === 'outside_first_name') i.required = true;
        });
        insideInputs.forEach(i => { i.disabled = true; i.required = false; });
    } else if (type === 'inside_family') {
        if (selectExisting) {
            selectExisting.disabled = true;
            selectExisting.required = false;
        }
        outsideInputs.forEach(i => { i.disabled = true; i.required = false; });
        insideInputs.forEach(i => {
            i.disabled = false;
            if (i.id === 'inside_first_name' || i.id === 'inside_parent_id') i.required = true;
        });
    }
}

function filterSpouseSelect(term) {
    term = term.trim().toLowerCase();
    const select = document.getElementById('spouse_select_existing');
    if (!select) return;
    Array.from(select.options).forEach((opt, idx) => {
        if (idx === 0) return;
        const text = opt.text.toLowerCase();
        opt.style.display = text.includes(term) ? '' : 'none';
    });
}
</script>
@endpush
