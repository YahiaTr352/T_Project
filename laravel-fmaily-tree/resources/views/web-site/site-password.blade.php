@extends('layouts.web-site.web')

@section('title', 'حماية الموقع - الدخول الذكي')

@push('styles')
<style>
    /* السيم والتناسق مع باقي الموقع */
    .site-pw-wrapper {
        min-height: calc(100vh - 200px);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2.5rem 1rem;
        background: #F4F7F6;
    }

    .site-pw-card {
        background: #FFFFFF;
        border-radius: 24px;
        box-shadow: 0 20px 40px rgba(20, 81, 71, 0.08);
        border: 1px solid rgba(20, 81, 71, 0.1);
        overflow: hidden;
        width: 100%;
        max-width: 980px;
    }

    .site-pw-side-banner {
        background: linear-gradient(135deg, #145147 0%, #37a05c 50%, #166534 100%);
        color: #FFFFFF;
        padding: 3rem 2.5rem;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        position: relative;
    }

    .site-pw-side-banner::after {
        content: '';
        position: absolute;
        bottom: -50px;
        left: -50px;
        width: 200px;
        height: 200px;
        background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, rgba(255,255,255,0) 70%);
        border-radius: 50%;
        pointer-events: none;
    }

    /* Tabs Bar */
    .pw-tab-container {
        display: flex;
        background: #F1F5F9;
        padding: 5px;
        border-radius: 16px;
        gap: 4px;
        margin-bottom: 1.75rem;
    }

    .pw-tab-btn {
        flex: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 10px 14px;
        border-radius: 12px;
        font-family: 'Tajawal', sans-serif;
        font-weight: 700;
        font-size: 0.9rem;
        color: #64748B;
        background: transparent;
        border: none;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .pw-tab-btn.active {
        background: #FFFFFF;
        color: #145147;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }

    /* Unified Fixed Height for Tab Panes (Zero Layout Shift) */
    .site-pw-pane:not(.hidden) {
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        height: 380px;
    }

    .wa-step-pane:not(.hidden) {
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        flex: 1;
    }

    /* Camera Viewport */
    .webcam-circle-frame {
        width: 168px;
        height: 168px;
        border-radius: 50%;
        border: 3px dashed #37a05c;
        margin: 0 auto;
        position: relative;
        overflow: hidden;
        background: #F8FAFC;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .scan-laser-beam {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: #37a05c;
        box-shadow: 0 0 10px #37a05c;
        animation: scanAnim 1.8s infinite ease-in-out;
        display: none;
        z-index: 10;
    }

    @keyframes scanAnim {
        0% { top: 5%; }
        50% { top: 90%; }
        100% { top: 5%; }
    }

    /* PIN & OTP Cells */
    .digit-cell-input {
        width: 48px;
        height: 56px;
        text-align: center;
        font-size: 1.5rem;
        font-weight: 700;
        border: 2px solid #E2E8F0;
        border-radius: 12px;
        background: #FFFFFF;
        color: #1E293B;
        transition: all 0.2s ease;
    }

    .digit-cell-input:focus {
        border-color: #37a05c;
        box-shadow: 0 0 0 3px rgba(55, 160, 92, 0.15);
        outline: none;
    }

    .digit-cell-input.filled {
        border-color: #37a05c;
        background: #F0FDF4;
    }

    .btn-brand-primary {
        background: linear-gradient(135deg, #145147 0%, #37a05c 100%);
        color: #FFFFFF;
        font-family: 'Tajawal', sans-serif;
        font-weight: 700;
        font-size: 1rem;
        padding: 13px 20px;
        border-radius: 14px;
        border: none;
        width: 100%;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        transition: all 0.25s ease;
    }

    .btn-brand-primary:hover {
        opacity: 0.95;
        transform: translateY(-1px);
        box-shadow: 0 8px 18px rgba(20, 81, 71, 0.25);
    }

    .btn-brand-primary:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }

    /* Country Picker Modal */
    .country-modal-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.55);
        backdrop-filter: blur(4px);
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.2s ease;
    }

    .country-modal-backdrop.open {
        opacity: 1;
        pointer-events: auto;
    }

    .country-modal-sheet {
        background: #FFFFFF;
        width: 100%;
        max-width: 480px;
        max-height: 82vh;
        border-radius: 24px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        border: 1px solid #E2E8F0;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        transform: translateY(16px) scale(0.98);
        transition: transform 0.22s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .country-modal-backdrop.open .country-modal-sheet {
        transform: translateY(0) scale(1);
    }

    /* Responsive Queries */
    @media (max-width: 640px) {
        .site-pw-wrapper {
            padding: 1rem 0.5rem;
        }
        .form-padding-wrap {
            padding: 1.5rem 1.25rem !important;
        }
        .site-pw-pane:not(.hidden) {
            height: 350px;
        }
        .digit-cell-input {
            width: 40px;
            height: 48px;
            font-size: 1.25rem;
        }
        .webcam-circle-frame {
            width: 144px;
            height: 144px;
        }
        .country-modal-backdrop {
            align-items: flex-end;
            padding: 0;
        }
        .country-modal-sheet {
            max-width: 100%;
            max-height: 85vh;
            border-bottom-left-radius: 0;
            border-bottom-right-radius: 0;
            transform: translateY(100%);
        }
    }
</style>
@endpush

@section('content')
<div class="site-pw-wrapper">
    <div class="site-pw-card">
        <div class="grid grid-cols-1 lg:grid-cols-12">

            <!-- Form Side -->
            <div class="lg:col-span-7 form-padding-wrap p-8 sm:p-10 flex flex-col justify-start">

                <!-- Header Title -->
                <div class="text-center mb-5">
                    <div class="w-14 h-14 rounded-2xl bg-[#145147]/10 text-[#145147] mx-auto flex items-center justify-center mb-3">
                        <i class="fas fa-shield-halved text-2xl"></i>
                    </div>
                    <h1 class="text-2xl font-black text-slate-800">حماية الموقع</h1>
                    <p class="text-slate-500 text-sm mt-1">اختر طريقة التحقق المناسبة للوصول إلى المنصة</p>
                </div>

                <!-- Alert Notice Box -->
                <div id="siteAuthAlert" class="hidden mb-4 p-3 rounded-xl text-xs font-bold flex items-center gap-2.5"></div>

                @if(session('error'))
                    <div class="mb-4 p-3 rounded-xl text-xs font-bold bg-red-50 text-red-600 border border-red-200 flex items-center gap-2">
                        <i class="fas fa-circle-exclamation"></i> {{ session('error') }}
                    </div>
                @endif
                @if(session('success'))
                    <div class="mb-4 p-3 rounded-xl text-xs font-bold bg-emerald-50 text-emerald-600 border border-emerald-200 flex items-center gap-2">
                        <i class="fas fa-circle-check"></i> {{ session('success') }}
                    </div>
                @endif

                <!-- Tabs -->
                <div class="pw-tab-container">
                    <button type="button" class="pw-tab-btn active" onclick="setSiteAuthTab('faceTab')" id="tabFaceBtn">
                        <i class="fas fa-user-gear"></i> بصمة الوجه
                    </button>
                    <button type="button" class="pw-tab-btn" onclick="setSiteAuthTab('whatsappTab')" id="tabWaBtn">
                        <i class="fab fa-whatsapp"></i> الواتساب
                    </button>
                    <button type="button" class="pw-tab-btn" onclick="setSiteAuthTab('passcodeTab')" id="tabPinBtn">
                        <i class="fas fa-key"></i> رمز الدخول
                    </button>
                </div>

                <!-- TAB 1: FACE VERIFICATION -->
                <div id="faceTab" class="site-pw-pane">
                    <div class="text-center">
                        <h3 class="font-bold text-slate-800 text-base">التعرف السريع بـ بصمة الوجه</h3>
                        <p class="text-xs text-slate-500 mt-0.5">التقط صورة سيلفي وسيقوم النظام فوراً بالمطابقة البيومترية (&ge; 85%)</p>
                    </div>

                    <div class="my-auto flex flex-col items-center justify-center">
                        <div class="webcam-circle-frame">
                            <video id="webcamView" autoplay playsinline class="w-full h-full object-cover hidden rounded-full"></video>
                            <canvas id="capturedCanvas" class="w-full h-full object-cover hidden rounded-full"></canvas>
                            <div id="camPlaceholder" class="text-center p-3">
                                <i class="fas fa-camera text-3xl text-[#37a05c] mb-1"></i>
                                <p class="text-xs text-slate-500 font-bold">كاميرا الجوال / الويب</p>
                            </div>
                            <div class="scan-laser-beam" id="scanLaserLine"></div>
                        </div>

                        <!-- Progress Bar -->
                        <div id="faceScanProgress" class="hidden w-full mt-2">
                            <div class="flex justify-between text-xs font-bold text-[#145147] mb-1">
                                <span>جاري تحليل النقاط البيومترية...</span>
                                <span id="faceScanVal">0%</span>
                            </div>
                            <div class="w-full h-1.5 bg-slate-100 rounded-full overflow-hidden">
                                <div id="faceScanBar" class="h-full bg-[#37a05c] transition-all duration-200" style="width: 0%"></div>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <button type="button" class="btn-brand-primary" onclick="initWebcamScan()">
                            <i class="fas fa-camera"></i> تشغيل الكاميرا والمطابقة المباشرة
                        </button>
                        <div class="flex items-center gap-2 text-center text-xs text-slate-400">
                            <div class="flex-1 border-t border-slate-200"></div>
                            <span>أو</span>
                            <div class="flex-1 border-t border-slate-200"></div>
                        </div>
                        <input type="file" id="fileSelfieInput" accept="image/*" class="hidden" onchange="uploadSelfieFile(this.files && this.files.length > 0 ? this.files[0] : this)">
                        <button type="button" class="w-full py-2.5 px-4 rounded-xl border border-dashed border-[#37a05c]/50 text-[#145147] font-bold text-xs hover:bg-[#37a05c]/5 transition flex items-center justify-center gap-2" onclick="document.getElementById('fileSelfieInput').click()">
                            <i class="fas fa-upload"></i> اختيار صورة سيلفي من الجهاز
                        </button>
                    </div>
                </div>

                <!-- TAB 2: WHATSAPP OTP -->
                <div id="whatsappTab" class="site-pw-pane hidden">
                    <div class="text-center">
                        <h3 class="font-bold text-slate-800 text-base">الدخول عبر كود الواتساب</h3>
                        <p class="text-xs text-slate-500 mt-0.5">أدخل رقم جوالك المعتمد لدى العائلة وتلقّ كود التحقق بنقرة واحدة</p>
                    </div>

                    <!-- Step 1: Input Phone -->
                    <div id="waPhoneStep" class="wa-step-pane">
                        <div class="my-auto py-2">
                            <!-- خيار تسجيل كابن أو ابنة -->
                            <div class="mb-3 p-2.5 rounded-xl border border-slate-200 bg-slate-50/90 hover:bg-slate-100 transition-colors">
                                <label class="flex items-center justify-between cursor-pointer gap-2 select-none">
                                    <div class="flex items-center gap-2">
                                        <input type="checkbox" id="waChildModeCheckbox" onchange="toggleWaChildMode(this.checked)" class="w-4 h-4 rounded text-[#37a05c] focus:ring-[#37a05c] border-slate-300">
                                        <div>
                                            <span class="block text-xs font-bold text-slate-800">تسجيل كابن أو ابنة</span>
                                            <span class="block text-[11px] text-slate-500">مخصصة لمن هم في عمر 10 سنوات أو أقل (يصل كود التحقق لواتساب الأب أو الأم)</span>
                                        </div>
                                    </div>
                                    <i class="fas fa-child text-lg text-emerald-600"></i>
                                </label>
                            </div>

                            <!-- منطقة اختيار الابن أو الابنة في حال وجود أكثر من ابن/ابنة -->
                            <div id="waChildPickerSection" class="hidden mb-3 p-3 bg-amber-50/90 border border-amber-200 rounded-xl text-right">
                                <div class="text-xs font-bold text-amber-900 mb-2 flex items-center justify-between">
                                    <span>اختر الابن أو الابنة:</span>
                                    <span id="waParentNameBadge" class="text-[10px] bg-amber-100 px-2 py-0.5 rounded-full text-amber-800"></span>
                                </div>
                                <div id="waChildrenContainer" class="space-y-1.5 max-h-36 overflow-y-auto pr-1"></div>
                            </div>

                            <label class="block text-xs font-bold text-slate-700 mb-2" for="waPhoneField" id="waPhoneFieldLabel">رقم الجوال المسجل:</label>
                            <div class="flex items-center border-2 border-slate-200 rounded-xl overflow-hidden focus-within:border-[#37a05c] bg-slate-50 transition-colors">
                                <button
                                    type="button"
                                    id="waCountryPickerBtn"
                                    onclick="openCountryPickerModal()"
                                    class="bg-slate-100 hover:bg-slate-200/80 active:bg-slate-200 px-3 py-3 font-bold text-xs text-slate-700 flex items-center gap-1.5 border-l border-slate-200 transition cursor-pointer shrink-0"
                                    title="اختر كود الدولة"
                                >
                                    <span id="waSelectedFlag" class="text-base leading-none">🇸🇦</span>
                                    <span id="waSelectedDialCode" class="font-bold text-slate-800 text-xs" dir="ltr">+966</span>
                                    <i class="fas fa-chevron-down text-[10px] text-slate-500 mr-0.5"></i>
                                </button>
                                <input
                                    type="tel"
                                    id="waPhoneField"
                                    placeholder="5X XXX XXXX"
                                    maxlength="15"
                                    inputmode="tel"
                                    class="w-full px-3.5 py-3 bg-transparent text-slate-800 font-bold text-sm outline-none"
                                    dir="ltr"
                                >
                            </div>
                            <p class="text-[11px] text-slate-400 mt-2 text-center" id="waPhoneHint">يمكنك اختيار مفتاح أي دولة وإدخال رقمك المسجل في شجرة العائلة</p>
                        </div>

                        <button type="button" class="btn-brand-primary" onclick="sendWaOtpReq()" id="btnWaSend">
                            <i class="fab fa-whatsapp"></i> <span id="btnWaSendText">إرسال كود التحقق عبر الواتساب</span>
                        </button>
                    </div>

                    <!-- Step 2: Input OTP -->
                    <div id="waCodeStep" class="wa-step-pane hidden">
                        <div class="my-auto py-2">
                            <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-xl mb-4 text-xs font-bold text-emerald-800 flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-circle-check text-emerald-500 text-base shrink-0"></i>
                                    <div class="text-right">
                                        <div class="text-[11px] text-emerald-700/90">تم إرسال كود التحقق إلى واتساب الرقم:</div>
                                        <strong id="waSentPhoneText" class="text-sm text-emerald-950 inline-block mt-0.5" dir="ltr"></strong>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    onclick="editWaPhone()"
                                    class="px-2.5 py-1.5 rounded-lg bg-white/80 hover:bg-white text-[#145147] border border-emerald-200/80 text-[11px] font-bold flex items-center gap-1 shrink-0 transition"
                                >
                                    <i class="fas fa-pen text-[10px]"></i>
                                    <span>تعديل الرقم</span>
                                </button>
                            </div>

                            <p class="text-center text-xs font-bold text-slate-600 mb-3">أدخل الكود المكون من 6 أرقام:</p>

                            <div class="flex justify-center gap-2" dir="ltr">
                                @for($i = 0; $i < 6; $i++)
                                    <input type="text" maxlength="6" inputmode="numeric" autocomplete="one-time-code" class="digit-cell-input wa-digit-input" data-idx="{{ $i }}">
                                @endfor
                            </div>
                        </div>

                        <div>
                            <button type="button" class="btn-brand-primary mb-2.5" onclick="verifyWaOtpReq()" id="btnWaVerify">
                                <i class="fas fa-unlock"></i> تأكيد الرمز والدخول
                            </button>

                            <div class="text-center text-xs font-bold text-slate-500">
                                <span id="waTimerText">إعادة إرسال الرمز خلال 60 ثانية</span>
                                <button type="button" id="waResendLink" onclick="sendWaOtpReq()" class="hidden text-[#145147] underline">إعادة إرسال رمز الواتساب</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 3: PASSCODE PIN -->
                <div id="passcodeTab" class="site-pw-pane hidden">
                    <div class="text-center">
                        <h3 class="font-bold text-slate-800 text-base">رمز دخول العائلة التقليدي</h3>
                        <p class="text-xs text-slate-500 mt-0.5">أدخل رمز الدخول المكون من {{ $passwordLength }} أرقام</p>
                    </div>

                    <form action="{{ route('site.password.verify') }}" method="POST" id="pinFormSubmit" class="flex-1 flex flex-col justify-between">
                        @csrf
                        <div class="my-auto py-2">
                            <div class="w-14 h-14 rounded-full bg-slate-100 border border-slate-200 text-[#145147] mx-auto flex items-center justify-center mb-5">
                                <i class="fas fa-lock text-2xl"></i>
                            </div>
                            <div class="flex justify-center gap-2" dir="ltr">
                                @for($i = 0; $i < $passwordLength; $i++)
                                    <input type="text" maxlength="1" pattern="[0-9]" inputmode="numeric" class="digit-cell-input pin-cell-input" data-index="{{ $i }}" @if($i === 0) autofocus @endif>
                                @endfor
                            </div>
                            <p class="text-[11px] text-slate-400 mt-3 text-center">أدخل الرمز الرقمي الموحّد المخصص لأفراد العائلة</p>
                        </div>
                        <input type="hidden" name="password" id="hiddenPinField">
                        <button type="submit" class="btn-brand-primary" id="btnPinSubmit" disabled>
                            <i class="fas fa-unlock"></i> التحقق من رمز الدخول
                        </button>
                    </form>
                </div>

            </div>

            <!-- Side Banner -->
            <div class="lg:col-span-5 site-pw-side-banner hidden lg:flex">
                <div>
                    <div class="w-12 h-12 rounded-xl bg-white/20 flex items-center justify-center mb-4">
                        <i class="fas fa-shield-cat text-2xl"></i>
                    </div>
                    <h2 class="text-2xl font-black mb-3">منصّتكم العائلية والتفاعلية الآمنة</h2>
                    <p class="text-emerald-100 text-sm leading-relaxed mb-6 opacity-90">
                        توثيق حسابك مرّة واحدة بـ بصمة الوجه أو كود الواتساب يمنحك دخولاً آمنات وتلقائياً على جميع أجهزتك.
                    </p>

                    <div class="space-y-3">
                        <div class="flex items-center gap-3 bg-white/10 p-2.5 rounded-xl backdrop-blur-sm">
                            <i class="fas fa-bolt text-emerald-300"></i>
                            <span class="text-xs font-bold">تخطي كلمات السر مستقبلاً</span>
                        </div>
                        <div class="flex items-center gap-3 bg-white/10 p-2.5 rounded-xl backdrop-blur-sm">
                            <i class="fas fa-tree text-emerald-300"></i>
                            <span class="text-xs font-bold">الوصول الفوري لشجرة العائلة والدليل</span>
                        </div>
                        <div class="flex items-center gap-3 bg-white/10 p-2.5 rounded-xl backdrop-blur-sm">
                            <i class="fas fa-trophy text-emerald-300"></i>
                            <span class="text-xs font-bold">المشاركة والتصويت في المسابقات والجوائز</span>
                        </div>
                    </div>
                </div>

                <div class="text-xs text-emerald-200/80 pt-6 border-t border-white/20">
                    عائلة السريِّع &copy; {{ date('Y') }}
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Country Picker Modal (مطابق لتطبيق الجوال) -->
<div id="countryPickerModal" class="country-modal-backdrop" onclick="handleCountryBackdropClick(event)">
    <div class="country-modal-sheet" onclick="event.stopPropagation()">
        <!-- Grabber Handle -->
        <div class="flex justify-center pt-2.5 pb-1">
            <div class="w-11 h-1.5 rounded-full bg-slate-300"></div>
        </div>

        <!-- Header -->
        <div class="flex items-center justify-between px-5 py-2.5">
            <button
                type="button"
                onclick="closeCountryPickerModal()"
                class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-700 flex items-center justify-center transition"
                title="إغلاق"
            >
                <i class="fas fa-xmark text-sm"></i>
            </button>
            <div class="text-center">
                <h3 class="text-base font-bold text-slate-800">اختر الدولة</h3>
                <p class="text-xs text-slate-500 mt-0.5">رمز الاتصال الدولي لحساب الواتساب</p>
            </div>
            <div class="w-8"></div>
        </div>

        <!-- Search Box -->
        <div class="px-4 mt-1 mb-2.5">
            <div class="flex items-center gap-2 bg-slate-50 border border-slate-200 focus-within:border-[#37a05c] rounded-xl px-3 h-11 transition">
                <i class="fas fa-magnifying-glass text-slate-400 text-sm"></i>
                <input
                    type="text"
                    id="countrySearchInput"
                    oninput="handleCountrySearchInput(this.value)"
                    placeholder="ابحث بالاسم أو برمز الدولة (مثال: مصر، 20، 966)..."
                    class="w-full bg-transparent text-slate-800 text-xs font-medium outline-none"
                    autocomplete="off"
                >
                <button
                    type="button"
                    id="countrySearchClearBtn"
                    onclick="clearCountrySearch()"
                    class="hidden text-slate-400 hover:text-slate-600 transition"
                >
                    <i class="fas fa-circle-xmark text-sm"></i>
                </button>
            </div>
        </div>

        <!-- Frequent Countries Chips -->
        <div id="frequentCountriesSection" class="px-4 mb-2">
            <p class="text-[11px] font-bold text-slate-500 mb-1.5">الأكثر استخداماً:</p>
            <div id="frequentCountriesChips" class="flex flex-wrap gap-1.5"></div>
        </div>

        <!-- Countries List -->
        <div id="countriesListContainer" class="flex-1 overflow-y-auto px-4 pb-5 space-y-1"></div>

        <!-- Empty State -->
        <div id="countriesEmptyState" class="hidden py-10 text-center text-slate-400">
            <i class="fas fa-globe text-3xl mb-2 opacity-60"></i>
            <p class="text-xs font-bold text-slate-500">لم يتم العثور على أي دولة مطابقة</p>
        </div>
    </div>
</div>

<!-- Face OTP Modal لتوثيق الجهاز الجديد عبر الواتساب (للأطفال أو الأجهزة الجديدة) -->
<div id="faceOtpModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm hidden p-4">
    <div class="bg-white rounded-2xl p-6 max-w-sm w-full text-center shadow-2xl border border-slate-200">
        <div class="w-14 h-14 rounded-full bg-emerald-50 border border-emerald-200 text-[#37a05c] mx-auto flex items-center justify-center mb-3">
            <i class="fab fa-whatsapp text-2xl"></i>
        </div>
        <h4 class="font-bold text-slate-800 text-base mb-1" id="faceOtpModalTitle">توثيق الجهاز لمرة واحدة</h4>
        <p class="text-xs text-slate-500 mb-4" id="faceOtpModalDesc">تم إرسال كود التحقق إلى واتساب ولي الأمر لتوثيق هذا المتصفح.</p>

        <input type="text" id="faceOtpCodeInput" maxlength="6" inputmode="numeric" placeholder="------" class="w-48 mx-auto text-center font-bold text-2xl tracking-[0.25em] py-2 px-3 border-2 border-slate-200 rounded-xl focus:border-[#37a05c] outline-none bg-slate-50 mb-4" oninput="this.value = this.value.replace(/\D/g, '')">

        <div class="flex gap-2">
            <button type="button" onclick="confirmFaceOtpReq()" id="btnConfirmFaceOtp" class="flex-1 btn-brand-primary py-2.5 text-xs font-bold">
                <i class="fas fa-check"></i> تأكيد الكود
            </button>
            <button type="button" onclick="closeFaceOtpModal()" class="px-4 py-2.5 rounded-xl bg-slate-100 text-slate-600 hover:bg-slate-200 text-xs font-bold">
                إلغاء
            </button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
let camStream = null;
let timerRef = null;
let verifyingWaOtp = false;
let submittedWaPhone = '';
const siteAuthRedirect = @json($safeRedirect ?? '/');
const siteAuthInitialTab = @json($initialTab ?? 'face');

/* ==================== COUNTRY DATA & HELPERS (MATCHING MOBILE APP) ==================== */
const COUNTRIES = [
    // الدول الخليجية والعربية الأكثر طلباً واستخداماً
    { code: 'SA', nameAr: 'المملكة العربية السعودية', nameEn: 'Saudi Arabia', dialCode: '+966', flag: '🇸🇦', placeholder: '5X XXX XXXX', isFrequent: true },
    { code: 'EG', nameAr: 'مصر', nameEn: 'Egypt', dialCode: '+20', flag: '🇪🇬', placeholder: '01X XXXX XXXX', isFrequent: true },
    { code: 'AE', nameAr: 'الإمارات العربية المتحدة', nameEn: 'United Arab Emirates', dialCode: '+971', flag: '🇦🇪', placeholder: '5X XXX XXXX', isFrequent: true },
    { code: 'KW', nameAr: 'الكويت', nameEn: 'Kuwait', dialCode: '+965', flag: '🇰🇼', placeholder: '9X XXX XXX', isFrequent: true },
    { code: 'QA', nameAr: 'قطر', nameEn: 'Qatar', dialCode: '+974', flag: '🇶🇦', placeholder: '3X XXX XXX', isFrequent: true },
    { code: 'BH', nameAr: 'مملكة البحرين', nameEn: 'Bahrain', dialCode: '+973', flag: '🇧🇭', placeholder: '3X XXX XXX', isFrequent: true },
    { code: 'OM', nameAr: 'سلطنة عُمان', nameEn: 'Oman', dialCode: '+968', flag: '🇴🇲', placeholder: '9X XXX XXX', isFrequent: true },
    { code: 'JO', nameAr: 'المملكة الأردنية الهاشمية', nameEn: 'Jordan', dialCode: '+962', flag: '🇯🇴', placeholder: '7X XXX XXXX', isFrequent: true },
    // باقي الدول العربية
    { code: 'IQ', nameAr: 'العراق', nameEn: 'Iraq', dialCode: '+964', flag: '🇮🇶', placeholder: '7X XXXX XXXX' },
    { code: 'YE', nameAr: 'اليمن', nameEn: 'Yemen', dialCode: '+967', flag: '🇾🇪', placeholder: '7X XXX XXXX' },
    { code: 'SY', nameAr: 'سوريا', nameEn: 'Syria', dialCode: '+963', flag: '🇸🇾', placeholder: '9X XXX XXXX' },
    { code: 'LB', nameAr: 'لبنان', nameEn: 'Lebanon', dialCode: '+961', flag: '🇱🇧', placeholder: '7X XXX XXX' },
    { code: 'PS', nameAr: 'فلسطين', nameEn: 'Palestine', dialCode: '+970', flag: '🇵🇸', placeholder: '59 XXX XXXX' },
    { code: 'SD', nameAr: 'السودان', nameEn: 'Sudan', dialCode: '+249', flag: '🇸🇩', placeholder: '9X XXX XXXX' },
    { code: 'LY', nameAr: 'ليبيا', nameEn: 'Libya', dialCode: '+218', flag: '🇱🇾', placeholder: '9X XXX XXXX' },
    { code: 'TN', nameAr: 'تونس', nameEn: 'Tunisia', dialCode: '+216', flag: '🇹🇳', placeholder: '9X XXX XXX' },
    { code: 'DZ', nameAr: 'الجزائر', nameEn: 'Algeria', dialCode: '+213', flag: '🇩🇿', placeholder: '6X XXX XXXX' },
    { code: 'MA', nameAr: 'المغرب', nameEn: 'Morocco', dialCode: '+212', flag: '🇲🇦', placeholder: '6X XXX XXXX' },
    // دول عالمية شائعة لمغتربي العائلة والطلاب
    { code: 'TR', nameAr: 'تركيا', nameEn: 'Turkey', dialCode: '+90', flag: '🇹🇷', placeholder: '5XX XXX XXXX' },
    { code: 'GB', nameAr: 'المملكة المتحدة', nameEn: 'United Kingdom', dialCode: '+44', flag: '🇬🇧', placeholder: '7XXX XXXXXX' },
    { code: 'US', nameAr: 'الولايات المتحدة الأمريكية', nameEn: 'United States', dialCode: '+1', flag: '🇺🇸', placeholder: 'XXX XXX XXXX' },
    { code: 'CA', nameAr: 'كندا', nameEn: 'Canada', dialCode: '+1', flag: '🇨🇦', placeholder: 'XXX XXX XXXX' },
    { code: 'DE', nameAr: 'ألمانيا', nameEn: 'Germany', dialCode: '+49', flag: '🇩🇪', placeholder: '15X XXXXXXXX' },
    { code: 'FR', nameAr: 'فرنسا', nameEn: 'France', dialCode: '+33', flag: '🇫🇷', placeholder: '6 XX XX XX XX' },
    { code: 'MY', nameAr: 'ماليزيا', nameEn: 'Malaysia', dialCode: '+60', flag: '🇲🇾', placeholder: '1X XXX XXXX' },
];

const FREQUENT_COUNTRIES = COUNTRIES.filter(c => c.isFrequent);
let selectedCountry = COUNTRIES[0];
let countrySearchQuery = '';

function toWesternDigits(str) {
    if (!str) return '';
    return String(str)
        .replace(/[\u0660-\u0669]/g, d => String(d.charCodeAt(0) - 0x0660))
        .replace(/[\u06F0-\u06F9]/g, d => String(d.charCodeAt(0) - 0x06f0));
}

function normalizeOtpInput(raw) {
    return toWesternDigits(raw).replace(/\D/g, '');
}

function normalizePhoneNumber(rawInput, dialCode) {
    const western = toWesternDigits(rawInput).trim();
    let cleanDigits = western.replace(/\D/g, '');
    const numericDialCode = dialCode.replace(/\D/g, '');

    if (cleanDigits.startsWith('00' + numericDialCode)) {
        cleanDigits = cleanDigits.slice(2 + numericDialCode.length);
    } else if (cleanDigits.startsWith(numericDialCode) && cleanDigits.length > numericDialCode.length + 5) {
        cleanDigits = cleanDigits.slice(numericDialCode.length);
    }

    if (cleanDigits.startsWith('0')) {
        cleanDigits = cleanDigits.replace(/^0+/, '');
    }

    const fullPhone = cleanDigits.length > 0 ? `${dialCode}${cleanDigits}` : '';
    const isValid = cleanDigits.length >= 7 && cleanDigits.length <= 14;

    return {
        fullPhone,
        localDigits: cleanDigits,
        isValid,
    };
}

function formatPhoneDisplay(dialCode, localDigits) {
    const clean = toWesternDigits(localDigits).replace(/\D/g, '');
    if (!clean) return dialCode;

    if (clean.length <= 4) {
        return `${dialCode} ${clean}`;
    } else if (clean.length <= 7) {
        return `${dialCode} ${clean.slice(0, 3)} ${clean.slice(3)}`;
    }
    return `${dialCode} ${clean.slice(0, 3)} ${clean.slice(3, 6)} ${clean.slice(6)}`;
}

function applySelectedCountry(country, focusInput = false) {
    selectedCountry = country;
    try {
        localStorage.setItem('preferred_country_code', country.code);
    } catch (e) {}

    const flagEl = document.getElementById('waSelectedFlag');
    const codeEl = document.getElementById('waSelectedDialCode');
    const phoneInput = document.getElementById('waPhoneField');

    if (flagEl) flagEl.textContent = country.flag;
    if (codeEl) codeEl.textContent = country.dialCode;
    if (phoneInput) {
        phoneInput.placeholder = country.placeholder;
        if (focusInput) {
            setTimeout(() => phoneInput.focus(), 50);
        }
    }
}

function openCountryPickerModal() {
    countrySearchQuery = '';
    const searchInput = document.getElementById('countrySearchInput');
    if (searchInput) searchInput.value = '';
    document.getElementById('countrySearchClearBtn')?.classList.add('hidden');

    renderCountryPickerContent();
    const modal = document.getElementById('countryPickerModal');
    if (modal) {
        modal.classList.add('open');
        setTimeout(() => searchInput?.focus(), 100);
    }
}

function closeCountryPickerModal() {
    const modal = document.getElementById('countryPickerModal');
    if (modal) modal.classList.remove('open');
}

function handleCountryBackdropClick(event) {
    if (event.target === event.currentTarget) {
        closeCountryPickerModal();
    }
}

function handleCountrySearchInput(val) {
    countrySearchQuery = val || '';
    const clearBtn = document.getElementById('countrySearchClearBtn');
    if (clearBtn) {
        clearBtn.classList.toggle('hidden', countrySearchQuery.length === 0);
    }
    renderCountryPickerContent();
}

function clearCountrySearch() {
    const searchInput = document.getElementById('countrySearchInput');
    if (searchInput) {
        searchInput.value = '';
        searchInput.focus();
    }
    handleCountrySearchInput('');
}

function selectCountryByCode(code) {
    const found = COUNTRIES.find(c => c.code === code);
    if (found) {
        applySelectedCountry(found, true);
    }
    closeCountryPickerModal();
}

function renderCountryPickerContent() {
    const q = toWesternDigits(countrySearchQuery.trim().toLowerCase());
    const freqSection = document.getElementById('frequentCountriesSection');
    const freqContainer = document.getElementById('frequentCountriesChips');
    const listContainer = document.getElementById('countriesListContainer');
    const emptyState = document.getElementById('countriesEmptyState');

    if (freqSection && freqContainer) {
        if (!q) {
            freqSection.classList.remove('hidden');
            freqContainer.innerHTML = FREQUENT_COUNTRIES.map(c => {
                const isSelected = c.code === selectedCountry.code;
                return `
                    <button
                        type="button"
                        onclick="selectCountryByCode('${c.code}')"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-full text-xs font-bold border transition cursor-pointer ${
                            isSelected
                                ? 'bg-[#37a05c] text-white border-[#37a05c]'
                                : 'bg-slate-100 hover:bg-slate-200/70 text-slate-700 border-slate-200'
                        }"
                    >
                        <span class="text-sm leading-none">${c.flag}</span>
                        <span>${c.nameAr}</span>
                    </button>
                `;
            }).join('');
        } else {
            freqSection.classList.add('hidden');
        }
    }

    const filtered = !q
        ? COUNTRIES
        : COUNTRIES.filter(item => {
            const matchNameAr = item.nameAr.toLowerCase().includes(q);
            const matchNameEn = item.nameEn.toLowerCase().includes(q);
            const matchDial = item.dialCode.replace('+', '').includes(q.replace('+', ''));
            const matchIso = item.code.toLowerCase().includes(q);
            return matchNameAr || matchNameEn || matchDial || matchIso;
        });

    if (!listContainer || !emptyState) return;

    if (filtered.length === 0) {
        listContainer.innerHTML = '';
        listContainer.classList.add('hidden');
        emptyState.classList.remove('hidden');
        return;
    }

    emptyState.classList.add('hidden');
    listContainer.classList.remove('hidden');
    listContainer.innerHTML = filtered.map(item => {
        const isSelected = item.code === selectedCountry.code;
        return `
            <button
                type="button"
                onclick="selectCountryByCode('${item.code}')"
                class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl transition cursor-pointer text-right ${
                    isSelected ? 'bg-[#37a05c]/10' : 'hover:bg-slate-50'
                }"
            >
                <div class="flex items-center gap-3 min-w-0 flex-1">
                    <span class="text-2xl leading-none shrink-0">${item.flag}</span>
                    <div class="min-w-0 flex-1">
                        <div class="text-sm truncate ${isSelected ? 'font-bold text-[#37a05c]' : 'font-medium text-slate-800'}">${item.nameAr}</div>
                        <div class="text-[11px] text-slate-400 truncate">${item.nameEn}</div>
                    </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <span class="px-2.5 py-1 rounded-lg text-xs font-bold ${
                        isSelected ? 'bg-[#37a05c] text-white' : 'bg-slate-200/80 text-slate-700'
                    }" dir="ltr">${item.dialCode}</span>
                    ${isSelected ? '<i class="fas fa-check text-[#37a05c] text-sm"></i>' : ''}
                </div>
            </button>
        `;
    }).join('');
}

function setSiteAuthTab(tabId) {
    document.querySelectorAll('.site-pw-pane').forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('.pw-tab-btn').forEach(btn => btn.classList.remove('active'));

    document.getElementById(tabId).classList.remove('hidden');

    if (tabId === 'faceTab') document.getElementById('tabFaceBtn').classList.add('active');
    if (tabId === 'whatsappTab') document.getElementById('tabWaBtn').classList.add('active');
    if (tabId === 'passcodeTab') document.getElementById('tabPinBtn').classList.add('active');

    if (tabId !== 'faceTab' && camStream) {
        camStream.getTracks().forEach(t => t.stop());
        camStream = null;
        document.getElementById('webcamView').classList.add('hidden');
        document.getElementById('camPlaceholder').classList.remove('hidden');
        document.getElementById('scanLaserLine').style.display = 'none';
    }
}

function showSiteNotice(msg, isSuccess = false) {
    const alertBox = document.getElementById('siteAuthAlert');
    alertBox.classList.remove('hidden', 'bg-red-50', 'text-red-600', 'border-red-200', 'bg-emerald-50', 'text-emerald-600', 'border-emerald-200');
    alertBox.classList.add('border');

    if (isSuccess) {
        alertBox.classList.add('bg-emerald-50', 'text-emerald-600', 'border-emerald-200');
        alertBox.innerHTML = `<i class="fas fa-circle-check"></i> ${msg}`;
    } else {
        alertBox.classList.add('bg-red-50', 'text-red-600', 'border-red-200');
        alertBox.innerHTML = `<i class="fas fa-circle-exclamation"></i> ${msg}`;
    }
}

/* ==================== FACE VERIFICATION WEBCAM ==================== */
async function initWebcamScan() {
    showSiteNotice('');
    document.getElementById('siteAuthAlert').classList.add('hidden');

    const video = document.getElementById('webcamView');
    const placeholder = document.getElementById('camPlaceholder');
    const laser = document.getElementById('scanLaserLine');

    try {
        camStream = await navigator.mediaDevices.getUserMedia({ video: { width: 400, height: 400, facingMode: "user" } });
        video.srcObject = camStream;
        video.classList.remove('hidden');
        placeholder.classList.add('hidden');
        laser.style.display = 'block';

        await video.play();

        // Wait for video frame readiness before triggering capture
        const checkFrameReady = () => {
            if (video.videoWidth > 0 && video.videoHeight > 0) {
                setTimeout(captureAndVerifyFace, 800);
            } else {
                setTimeout(checkFrameReady, 200);
            }
        };
        checkFrameReady();
    } catch (err) {
        showSiteNotice('تعذر فتح الكاميرا المباشرة، يمكنك اختيار صورة سيلفي من جهازك.');
    }
}

function captureAndVerifyFace() {
    const video = document.getElementById('webcamView');
    const canvas = document.getElementById('capturedCanvas');
    const ctx = canvas.getContext('2d');

    if (!video.videoWidth || video.videoWidth === 0) {
        setTimeout(captureAndVerifyFace, 400);
        return;
    }

    canvas.width = 400;
    canvas.height = 400;
    ctx.drawImage(video, 0, 0, 400, 400);

    canvas.toBlob(blob => {
        if (blob && blob.size > 0) {
            uploadSelfieFile(blob);
        } else {
            showSiteNotice('تعذر التقاط إطار الصورة من الكاميرا، يرجى إعادة المحاولة.');
        }
    }, 'image/jpeg', 0.92);
}

function uploadSelfieFile(fileOrBlob) {
    const progressBox = document.getElementById('faceScanProgress');
    const progressBar = document.getElementById('faceScanBar');
    const progressVal = document.getElementById('faceScanVal');
    const laser = document.getElementById('scanLaserLine');

    // Defensive check: if an HTMLInputElement was passed, extract the File object
    let actualFile = fileOrBlob;
    if (fileOrBlob && fileOrBlob.files && fileOrBlob.files.length > 0) {
        actualFile = fileOrBlob.files[0];
    } else if (fileOrBlob instanceof HTMLInputElement) {
        showSiteNotice('يرجى اختيار صورة سيلفي صالحة من جهازك.');
        return;
    }

    if (!actualFile) {
        showSiteNotice('لم يتم اختيار صورة صالحة.');
        return;
    }

    if (actualFile instanceof File) {
        document.getElementById('webcamView').classList.add('hidden');
        document.getElementById('camPlaceholder').classList.remove('hidden');
    }

    progressBox.classList.remove('hidden');
    laser.style.display = 'block';

    let pct = 15;
    progressBar.style.width = '15%';
    progressVal.innerText = '15%';

    const timer = setInterval(() => {
        if (pct < 85) {
            pct += 15;
            progressBar.style.width = pct + '%';
            progressVal.innerText = pct + '%';
        }
    }, 130);

    const formData = new FormData();
    formData.append('image', actualFile, actualFile.name || 'selfie.jpg');
    formData.append('device_uuid', getOrCreateSiteDeviceUuid());
    formData.append('device_name', 'متصفح الويب (' + (navigator.platform || 'Desktop') + ')');

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

    fetch('/api/v1/auth/face-verify', {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        credentials: 'same-origin',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        clearInterval(timer);
        progressBar.style.width = '100%';
        progressVal.innerText = '100%';

        if (data.success) {
            if (data.requires_otp) {
                laser.style.display = 'none';
                openFaceOtpModal(data);
                return;
            }
            const score = data.data?.similarity_score || 99.1;
            showSiteNotice(`تم المطابقة بنجاح بنسبة ${score}%! جاري التوجيه للموقع...`, true);
            setTimeout(() => {
                window.location.href = (typeof siteAuthRedirect === 'string' && siteAuthRedirect.startsWith('/')) ? siteAuthRedirect : '/';
            }, 800);
        } else {
            laser.style.display = 'none';
            showSiteNotice(data.message || 'فشلت مطابقة صورة الوجه.');
        }
    })
    .catch(() => {
        clearInterval(timer);
        laser.style.display = 'none';
        showSiteNotice('حدث خطأ أثناء الاتصال بالخادم لمطابقة الوجه.');
    });
}

/* ==================== DEVICE UUID & FACE OTP ==================== */
function getOrCreateSiteDeviceUuid() {
    let uuid = localStorage.getItem('site_device_uuid');
    if (!uuid) {
        uuid = 'web-' + 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            const r = Math.random() * 16 | 0, v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
        localStorage.setItem('site_device_uuid', uuid);
    }
    return uuid;
}

let currentFaceChallenge = null;

function openFaceOtpModal(data) {
    currentFaceChallenge = data.data || {};
    const modal = document.getElementById('faceOtpModal');
    const title = document.getElementById('faceOtpModalTitle');
    const desc = document.getElementById('faceOtpModalDesc');
    const input = document.getElementById('faceOtpCodeInput');

    if (data.is_child || data.data?.is_child) {
        const relation = data.guardian_relation || data.data?.guardian_relation || 'ولي الأمر';
        const childName = data.data?.person?.first_name || '';
        title.innerText = 'توثيق لمرة واحدة للابن أو الابنة';
        desc.innerText = `تم إرسال كود التحقق إلى واتساب ${relation} (${data.data?.masked_phone || ''}) لتوثيق هذا المتصفح لـ ${childName}.`;
    } else {
        title.innerText = 'توثيق الجهاز لمرة واحدة';
        desc.innerText = `تم إرسال كود التحقق إلى رقم الواتساب المسجل (${data.data?.masked_phone || ''}) لتوثيق هذا المتصفح.`;
    }

    input.value = '';
    modal.classList.remove('hidden');
    setTimeout(() => input.focus(), 100);
}

function closeFaceOtpModal() {
    const modal = document.getElementById('faceOtpModal');
    if (modal) modal.classList.add('hidden');
    currentFaceChallenge = null;
}

function confirmFaceOtpReq() {
    if (!currentFaceChallenge) return;
    const code = document.getElementById('faceOtpCodeInput').value.trim();
    if (code.length < 6) {
        showSiteNotice('يرجى إدخال كود التحقق المكون من 6 أرقام.');
        return;
    }

    const btn = document.getElementById('btnConfirmFaceOtp');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري التوثيق...';

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

    fetch('/api/v1/auth/face-verify/confirm-otp', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        credentials: 'same-origin',
        body: JSON.stringify({
            challenge_token: currentFaceChallenge.challenge_token,
            otp_code: code,
            device_uuid: getOrCreateSiteDeviceUuid()
        })
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check"></i> تأكيد الكود';

        if (data.success) {
            closeFaceOtpModal();
            showSiteNotice('تم توثيق الجهاز بنجاح! جاري الدخول للموقع...', true);
            setTimeout(() => {
                window.location.href = (typeof siteAuthRedirect === 'string' && siteAuthRedirect.startsWith('/')) ? siteAuthRedirect : '/';
            }, 700);
        } else {
            showSiteNotice(data.message || 'كود التحقق غير صحيح.');
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check"></i> تأكيد الكود';
        showSiteNotice('حدث خطأ أثناء تأكيد كود التحقق.');
    });
}

/* ==================== WHATSAPP OTP ==================== */
let isChildAuthMode = false;
let selectedChildId = null;
let selectedChildName = '';

function toggleWaChildMode(checked) {
    isChildAuthMode = !!checked;
    selectedChildId = null;
    selectedChildName = '';
    const picker = document.getElementById('waChildPickerSection');
    if (picker) picker.classList.add('hidden');
    const container = document.getElementById('waChildrenContainer');
    if (container) container.innerHTML = '';

    const label = document.getElementById('waPhoneFieldLabel');
    if (label) label.innerText = isChildAuthMode ? 'رقم واتساب ولي الأمر (الأب أو الأم):' : 'رقم الجوال المسجل:';

    const hint = document.getElementById('waPhoneHint');
    if (hint) hint.innerText = isChildAuthMode ? 'أدخل رقم واتساب الوالد المسجل في شجرة العائلة لتلقي الكود' : 'يمكنك اختيار مفتاح أي دولة وإدخال رقمك المسجل في شجرة العائلة';

    const btnText = document.getElementById('btnWaSendText');
    if (btnText) btnText.innerText = isChildAuthMode ? 'متابعة لاختيار الابن أو الابنة وإرسال الكود' : 'إرسال كود التحقق عبر الواتساب';
}

function renderChildPicker(parentPhone, parentName, children) {
    const section = document.getElementById('waChildPickerSection');
    const badge = document.getElementById('waParentNameBadge');
    const container = document.getElementById('waChildrenContainer');

    if (badge) badge.innerText = parentName ? 'رعاية: ' + parentName : '';
    if (container) {
        container.innerHTML = '';
        children.forEach(c => {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'w-full flex items-center justify-between p-2 rounded-lg bg-white border border-amber-200 hover:border-amber-400 hover:bg-amber-50/50 transition cursor-pointer text-right';
            item.onclick = () => {
                selectedChildId = c.id;
                selectedChildName = c.first_name || c.full_name;
                dispatchChildOtpReq(parentPhone, c.id, selectedChildName);
            };

            const ageText = c.age ? c.age + ' سنة' : '';
            item.innerHTML = `
                <div class="flex items-center gap-2">
                    <i class="fas fa-chevron-left text-[10px] text-amber-600"></i>
                    <span class="text-[11px] font-bold text-amber-800 bg-amber-100 px-2 py-0.5 rounded-md">${ageText}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold text-slate-800">${c.first_name || c.full_name}</span>
                    <div class="w-7 h-7 rounded-full bg-amber-100 text-amber-700 flex items-center justify-center font-bold text-xs">
                        <i class="fas fa-user text-[10px]"></i>
                    </div>
                </div>
            `;
            container.appendChild(item);
        });
    }

    if (section) section.classList.remove('hidden');
}

function dispatchChildOtpReq(parentPhone, childId, childName) {
    const btn = document.getElementById('btnWaSend');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري إرسال كود التحقق...';
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

    fetch('/api/v1/auth/child/send-otp', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        credentials: 'same-origin',
        body: JSON.stringify({ parent_phone: parentPhone, child_id: childId })
    })
    .then(r => r.json())
    .then(data => {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fab fa-whatsapp"></i> <span id="btnWaSendText">متابعة لاختيار الابن أو الابنة وإرسال الكود</span>';
        }

        if (data.success) {
            submittedWaPhone = parentPhone;
            selectedChildId = childId;
            selectedChildName = childName;
            verifyingWaOtp = false;
            document.getElementById('waPhoneStep').classList.add('hidden');
            document.getElementById('waCodeStep').classList.remove('hidden');
            document.getElementById('waSentPhoneText').innerText = `واتساب الوالد لدخول (${childName})`;

            const waCells = document.querySelectorAll('.wa-digit-input');
            waCells.forEach(c => {
                c.value = '';
                c.classList.remove('filled');
            });

            const expiresSec = data.data?.expires_in ? Math.min(data.data.expires_in, 60) : 60;
            startWaTimer(expiresSec);
            showSiteNotice(`تم إرسال كود دخول (${childName}) إلى واتساب ولي الأمر بنجاح.`, true);
            setTimeout(() => waCells[0]?.focus(), 100);
        } else {
            showSiteNotice(data.message || 'تعذر إرسال كود الواتساب.');
        }
    })
    .catch(() => {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fab fa-whatsapp"></i> <span id="btnWaSendText">متابعة لاختيار الابن أو الابنة وإرسال الكود</span>';
        }
        showSiteNotice('حدث خطأ أثناء إرسال كود الواتساب لولي الأمر.');
    });
}

function editWaPhone() {
    document.getElementById('waCodeStep').classList.add('hidden');
    document.getElementById('waPhoneStep').classList.remove('hidden');
    document.querySelectorAll('.wa-digit-input').forEach(d => {
        d.value = '';
        d.classList.remove('filled');
    });
    if (timerRef) clearInterval(timerRef);
    setTimeout(() => document.getElementById('waPhoneField')?.focus(), 50);
}

function sendWaOtpReq() {
    const rawPhone = document.getElementById('waPhoneField').value.trim();
    const { fullPhone, isValid, localDigits } = normalizePhoneNumber(rawPhone, selectedCountry.dialCode);

    if (!isValid || localDigits.length < 7) {
        showSiteNotice(isChildAuthMode ? 'يرجى إدخال رقم واتساب ولي الأمر (الأب أو الأم) بشكل صحيح.' : 'يرجى إدخال رقم جوال صحيح مسجل لدى العائلة.');
        return;
    }

    const btn = document.getElementById('btnWaSend');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الإرسال...';

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

    // إذا كان في وضع تسجيل/دخول الطفل
    if (isChildAuthMode) {
        if (selectedChildId) {
            dispatchChildOtpReq(fullPhone, selectedChildId, selectedChildName);
            return;
        }

        fetch('/api/v1/auth/child/parent-lookup', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            credentials: 'same-origin',
            body: JSON.stringify({ phone: fullPhone })
        })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fab fa-whatsapp"></i> <span id="btnWaSendText">متابعة لاختيار الابن أو الابنة وإرسال الكود</span>';

            if (!data.success || !data.data?.children?.length) {
                showSiteNotice(data.message || 'لا يوجد أبناء مسجلين (10 سنوات فأقل) لهذا الحساب في شجرة العائلة.');
                return;
            }

            const children = data.data.children;
            if (children.length === 1) {
                dispatchChildOtpReq(fullPhone, children[0].id, children[0].first_name);
            } else {
                renderChildPicker(fullPhone, data.data.parent?.name, children);
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fab fa-whatsapp"></i> <span id="btnWaSendText">متابعة لاختيار الابن أو الابنة وإرسال الكود</span>';
            showSiteNotice('حدث خطأ أثناء البحث عن أبناء ولي الأمر.');
        });
        return;
    }

    fetch('/api/v1/auth/whatsapp/send-otp', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        credentials: 'same-origin',
        body: JSON.stringify({ phone: fullPhone })
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fab fa-whatsapp"></i> إرسال كود التحقق عبر الواتساب';

        if (data.success) {
            submittedWaPhone = fullPhone;
            verifyingWaOtp = false;
            document.getElementById('waPhoneStep').classList.add('hidden');
            document.getElementById('waCodeStep').classList.remove('hidden');
            document.getElementById('waSentPhoneText').innerText = formatPhoneDisplay(selectedCountry.dialCode, localDigits);

            const waCells = document.querySelectorAll('.wa-digit-input');
            waCells.forEach(c => {
                c.value = '';
                c.classList.remove('filled');
            });

            const expiresSec = data.data?.expires_in ? Math.min(data.data.expires_in, 60) : 60;
            startWaTimer(expiresSec);
            showSiteNotice('تم إرسال رمز التحقق إلى الواتساب بنجاح.', true);
            setTimeout(() => waCells[0]?.focus(), 100);
        } else {
            showSiteNotice(data.message || 'تعذر إرسال كود الواتساب. حاول مجدداً.');
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fab fa-whatsapp"></i> إرسال كود التحقق عبر الواتساب';
        showSiteNotice('حدث خطأ أثناء الاتصال بخدمة الواتساب.');
    });
}

function verifyWaOtpReq() {
    if (verifyingWaOtp) return;

    const digits = document.querySelectorAll('.wa-digit-input');
    let code = '';
    digits.forEach(d => code += normalizeOtpInput(d.value));
    code = code.slice(0, 6);

    if (code.length < 6) {
        showSiteNotice('يرجى إكمال كود التحقق (6 أرقام).');
        return;
    }

    const rawPhone = document.getElementById('waPhoneField').value.trim();
    const phoneToVerify = submittedWaPhone || normalizePhoneNumber(rawPhone, selectedCountry.dialCode).fullPhone;

    verifyingWaOtp = true;
    const btn = document.getElementById('btnWaVerify');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري التأكيد...';

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

    const endpoint = (isChildAuthMode && selectedChildId)
        ? '/api/v1/auth/child/verify-otp'
        : '/api/v1/auth/whatsapp/verify-otp';

    const payload = (isChildAuthMode && selectedChildId)
        ? { parent_phone: phoneToVerify, child_id: selectedChildId, otp_code: code, device_uuid: getOrCreateSiteDeviceUuid() }
        : { phone: phoneToVerify, otp_code: code, device_uuid: getOrCreateSiteDeviceUuid() };

    fetch(endpoint, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        credentials: 'same-origin',
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
        verifyingWaOtp = false;
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-unlock"></i> تأكيد الرمز والدخول';

        if (data.success) {
            showSiteNotice('تم التحقق بنجاح وتوثيق الجلسة! جاري التوجيه...', true);
            setTimeout(() => {
                window.location.href = (typeof siteAuthRedirect === 'string' && siteAuthRedirect.startsWith('/')) ? siteAuthRedirect : '/';
            }, 700);
        } else {
            showSiteNotice(data.message || 'كود التحقق غير صحيح.');
            digits.forEach(d => {
                d.value = '';
                d.classList.remove('filled');
            });
            setTimeout(() => digits[0]?.focus(), 50);
        }
    })
    .catch(() => {
        verifyingWaOtp = false;
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-unlock"></i> تأكيد الرمز والدخول';
        showSiteNotice('تعذر التأكد من كود التحقق من السيرفر.');
    });
}

function startWaTimer(sec) {
    let left = sec;
    const txt = document.getElementById('waTimerText');
    const link = document.getElementById('waResendLink');

    link.classList.add('hidden');
    txt.classList.remove('hidden');

    if (timerRef) clearInterval(timerRef);

    timerRef = setInterval(() => {
        left--;
        txt.innerText = `إعادة إرسال الرمز خلال ${left} ثانية`;
        if (left <= 0) {
            clearInterval(timerRef);
            txt.classList.add('hidden');
            link.classList.remove('hidden');
        }
    }, 1000);
}

/* ==================== PIN & OTP INPUT AUTO FOCUS ==================== */
document.addEventListener('DOMContentLoaded', function() {
    try {
        const savedCode = localStorage.getItem('preferred_country_code');
        if (savedCode) {
            const found = COUNTRIES.find(c => c.code === savedCode);
            if (found) applySelectedCountry(found, false);
        }
    } catch (e) {}

    const tabMap = { face: 'faceTab', whatsapp: 'whatsappTab', pin: 'passcodeTab' };
    if (tabMap[siteAuthInitialTab]) {
        setSiteAuthTab(tabMap[siteAuthInitialTab]);
    }

    const waPhoneInput = document.getElementById('waPhoneField');
    if (waPhoneInput) {
        waPhoneInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                sendWaOtpReq();
            }
        });
    }

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeCountryPickerModal();
        }
    });

    const pinCells = document.querySelectorAll('.pin-cell-input');
    const hiddenField = document.getElementById('hiddenPinField');
    const btnPin = document.getElementById('btnPinSubmit');
    const len = {{ $passwordLength }};

    pinCells.forEach((cell, i) => {
        cell.addEventListener('input', (e) => {
            const digits = normalizeOtpInput(e.target.value);
            if (!digits) {
                e.target.value = '';
                e.target.classList.remove('filled');
            } else {
                e.target.value = digits[0];
                e.target.classList.add('filled');
                if (i < pinCells.length - 1) pinCells[i + 1].focus();
            }

            let val = '';
            pinCells.forEach(c => val += c.value);
            hiddenField.value = val;
            btnPin.disabled = (val.length !== len);
        });

        cell.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && !e.target.value && i > 0) {
                pinCells[i - 1].focus();
            }
        });
    });

    const waCells = document.querySelectorAll('.wa-digit-input');
    waCells.forEach((cell, i) => {
        cell.addEventListener('input', (e) => {
            const digits = normalizeOtpInput(e.target.value);
            if (digits.length === 0) {
                e.target.value = '';
                e.target.classList.remove('filled');
                return;
            }

            if (digits.length > 1) {
                digits.slice(0, 6).split('').forEach((ch, offset) => {
                    if (i + offset < waCells.length) {
                        waCells[i + offset].value = ch;
                        waCells[i + offset].classList.add('filled');
                    }
                });
                const nextIdx = Math.min(i + digits.length, waCells.length - 1);
                waCells[nextIdx].focus();
            } else {
                e.target.value = digits;
                e.target.classList.add('filled');
                if (i < waCells.length - 1) waCells[i + 1].focus();
            }

            let fullCode = '';
            waCells.forEach(c => fullCode += c.value);
            if (fullCode.length === 6) {
                setTimeout(() => verifyWaOtpReq(), 50);
            }
        });

        cell.addEventListener('paste', (e) => {
            const pasted = (e.clipboardData || window.clipboardData)?.getData('text') || '';
            const digits = normalizeOtpInput(pasted).slice(0, 6);
            if (digits.length > 0) {
                e.preventDefault();
                digits.split('').forEach((ch, idx) => {
                    if (idx < waCells.length) {
                        waCells[idx].value = ch;
                        waCells[idx].classList.add('filled');
                    }
                });
                const focusIdx = Math.min(digits.length, waCells.length - 1);
                waCells[focusIdx].focus();
                if (digits.length === 6) {
                    setTimeout(() => verifyWaOtpReq(), 50);
                }
            }
        });

        cell.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace') {
                if (e.target.value) {
                    e.target.value = '';
                    e.target.classList.remove('filled');
                    e.preventDefault();
                } else if (i > 0) {
                    waCells[i - 1].value = '';
                    waCells[i - 1].classList.remove('filled');
                    waCells[i - 1].focus();
                    e.preventDefault();
                }
            }
        });
    });
});
</script>
@endpush
