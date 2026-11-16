<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تواصل العائلة - إنشاء حساب جديد</title>

    <!-- Google Fonts (Tajawal & Alexandria) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&family=Alexandria:wght@300;400;600;700&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            dark: '#0b2e29',
                            forest: '#145147',
                            emerald: '#059669',
                            mint: '#10b981',
                            gold: '#d4af37',
                            goldHover: '#b58920',
                            sand: '#f4ebd0',
                            lightBg: '#f8faf8'
                        }
                    },
                    fontFamily: {
                        sans: ['Tajawal', 'Alexandria', 'sans-serif'],
                        title: ['Alexandria', 'sans-serif']
                    }
                }
            }
        }
    </script>

    <style>
        body {
            background-color: var(--brand-lightBg, #f8faf8);
            background-image: url('data:image/svg+xml,%3Csvg width="60" height="60" viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg"%3E%3Cg fill="none" fill-rule="evenodd"%3E%3Cg fill="%23145147" fill-opacity="0.02"%3E%3Cpath d="M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z"/%3E%3C/g%3E%3C/g%3E%3C/svg%3E');
            min-height: 100vh;
        }

        /* Custom Scrollbar for form section */
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(20, 81, 71, 0.1);
            border-radius: 10px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: rgba(20, 81, 71, 0.2);
        }

        /* Float animation */
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-6px); }
            100% { transform: translateY(0px); }
        }

        .floating-icon {
            animation: float 4s ease-in-out infinite;
        }
    </style>
</head>

<body class="font-sans antialiased text-gray-800 flex items-center justify-center p-4 min-h-screen">

    <div class="w-full max-w-5xl rounded-3xl overflow-hidden bg-white border border-gray-100 shadow-[0_20px_60px_rgba(20,81,71,0.06)] grid grid-cols-1 lg:grid-cols-12 transition-all duration-300">
        
        <!-- Visual/Side Section -->
        <div class="lg:col-span-5 bg-gradient-to-br from-[#edf4f0] to-[#f6f3eb] p-10 text-brand-forest flex flex-col justify-between relative overflow-hidden hidden lg:flex border-l border-gray-100">
            <!-- subtle decorative patterns -->
            <div class="absolute -top-12 -left-12 w-48 h-48 bg-brand-forest/5 rounded-full"></div>
            <div class="absolute -bottom-24 -right-24 w-64 h-64 bg-brand-gold/10 rounded-full filter blur-xl"></div>
            
            <div class="flex items-center justify-between z-10">
                <span class="text-brand-forest/80 font-bold text-sm flex items-center">
                    <i class="fa-solid fa-seedling ml-1.5 text-xs text-brand-emerald"></i> ابدأ رحلتك الآن
                </span>
                <span class="text-xs text-brand-forest/60">شجرة عائلة السريّع</span>
            </div>

            <!-- Content -->
            <div class="my-auto z-10 space-y-5 max-w-xs">
                <div class="w-12 h-1 bg-brand-gold rounded"></div>
                <h2 class="text-2xl font-bold font-title leading-snug">"كل شجرة عظيمة كانت يوماً بذرة صغيرة."</h2>
                <p class="text-gray-600 text-sm leading-relaxed">
                    من خلال إنشاء حسابك، تصبح عضواً فعالاً في توثيق تاريخ العائلة وربط أواصر القربى بين الأجيال المتعاقبة.
                </p>
                <div class="space-y-2.5 pt-4 text-xs text-brand-forest/80">
                    <div class="flex items-center">
                        <i class="fa-solid fa-circle-check text-brand-emerald ml-2.5"></i>
                        <span>حماية كاملة لخصوصية بياناتك</span>
                    </div>
                    <div class="flex items-center">
                        <i class="fa-solid fa-circle-check text-brand-emerald ml-2.5"></i>
                        <span>المشاركة في المسابقات والأنشطة</span>
                    </div>
                    <div class="flex items-center">
                        <i class="fa-solid fa-circle-check text-brand-emerald ml-2.5"></i>
                        <span>توثيق شجرتك الشخصية وفروعها</span>
                    </div>
                </div>
            </div>

            <!-- Bottom Note -->
            <div class="flex items-center space-x-3 space-x-reverse z-10">
                <div class="w-10 h-10 rounded-full bg-brand-forest/5 flex items-center justify-center text-brand-forest floating-icon">
                    <i class="fa-solid fa-network-wired text-base"></i>
                </div>
                <div>
                    <h4 class="text-xs font-bold text-brand-forest">ترابط وثيق ودائم</h4>
                    <p class="text-[10px] text-gray-500">بوابة العائلة الرقمية الرسمية.</p>
                </div>
            </div>
        </div>

        <!-- Form Section -->
        <div class="lg:col-span-7 p-8 sm:p-10 flex flex-col justify-center max-h-[90vh] overflow-y-auto custom-scrollbar bg-white">
            
            <!-- Logo & Header -->
            <div class="text-center mb-6">
                <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-brand-forest/5 text-brand-forest mb-3 transition-transform duration-300 hover:scale-105">
                    <i class="fa-solid fa-user-plus text-2xl"></i>
                </div>
                <h1 class="text-2xl font-bold font-title text-brand-forest">إنشاء حساب جديد</h1>
                <p class="text-sm text-gray-400 mt-1">انضم لعائلتنا ووثّق نسبك في الشجرة الرسمية</p>
            </div>

            <!-- Alerts (Errors/Success) -->
            <div class="mb-4">
                @include('components.alerts')
            </div>

            <!-- Form -->
            <form action="{{ route('customRegister') }}" method="POST" id="registerForm" class="space-y-4">
                @csrf
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Full Name Input -->
                    <div>
                        <label for="formName" class="block text-sm font-semibold text-brand-forest mb-1.5">الاسم الكامل</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 right-0 flex items-center pr-4 text-gray-400">
                                <i class="fa-solid fa-user"></i>
                            </span>
                            <input type="text" name="name" id="formName" value="{{ old('name') }}" required
                                class="w-full pr-11 pl-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:border-brand-emerald focus:ring-2 focus:ring-brand-emerald/10 transition-all duration-300 outline-none text-[14px]"
                                placeholder="الاسم الثلاثي أو الرباعي">
                        </div>
                    </div>

                    <!-- Email Input -->
                    <div>
                        <label for="formEmail" class="block text-sm font-semibold text-brand-forest mb-1.5">البريد الإلكتروني</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 right-0 flex items-center pr-4 text-gray-400">
                                <i class="fa-solid fa-envelope"></i>
                            </span>
                            <input type="email" name="email" id="formEmail" value="{{ old('email') }}" required
                                class="w-full pr-11 pl-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:border-brand-emerald focus:ring-2 focus:ring-brand-emerald/10 transition-all duration-300 outline-none text-[14px]"
                                placeholder="example@family.com">
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Phone Input -->
                    <div>
                        <label for="formPhone" class="block text-sm font-semibold text-brand-forest mb-1.5">رقم الهاتف</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 right-0 flex items-center pr-4 text-gray-400">
                                <i class="fa-solid fa-phone"></i>
                            </span>
                            <input type="text" name="phone" id="formPhone" value="{{ old('phone') }}" required
                                class="w-full pr-11 pl-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:border-brand-emerald focus:ring-2 focus:ring-brand-emerald/10 transition-all duration-300 outline-none text-[14px] text-right"
                                placeholder="05xxxxxxxx">
                        </div>
                    </div>

                    <!-- Address Input -->
                    <div>
                        <label for="formAddress" class="block text-sm font-semibold text-brand-forest mb-1.5">العنوان الحالي</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 right-0 flex items-center pr-4 text-gray-400">
                                <i class="fa-solid fa-map-marker-alt"></i>
                            </span>
                            <input type="text" name="address" id="formAddress" value="{{ old('address') }}" required
                                class="w-full pr-11 pl-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:border-brand-emerald focus:ring-2 focus:ring-brand-emerald/10 transition-all duration-300 outline-none text-[14px]"
                                placeholder="المدينة، الحي">
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Password Input -->
                    <div>
                        <label for="formPassword" class="block text-sm font-semibold text-brand-forest mb-1.5">كلمة المرور</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 right-0 flex items-center pr-4 text-gray-400">
                                <i class="fa-solid fa-lock"></i>
                            </span>
                            <input type="password" name="password" id="formPassword" required
                                class="w-full pr-11 pl-11 py-2.5 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:border-brand-emerald focus:ring-2 focus:ring-brand-emerald/10 transition-all duration-300 outline-none text-[14px]"
                                placeholder="••••••••" onkeyup="checkPasswordStrength(this.value)">
                            <button type="button" onclick="togglePasswordVisibility('formPassword', 'togglePasswordIcon1')" 
                                class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400 hover:text-brand-forest transition-colors">
                                <i class="fa-solid fa-eye text-sm" id="togglePasswordIcon1"></i>
                            </button>
                        </div>
                        <!-- Strength bar -->
                        <div class="mt-2" id="strengthContainer" style="display: none;">
                            <div class="h-1.5 w-full bg-gray-200 rounded-full overflow-hidden">
                                <div id="strengthBar" class="h-full w-0 transition-all duration-500"></div>
                            </div>
                            <span id="strengthText" class="text-[11px] font-bold mt-1 block"></span>
                        </div>
                    </div>

                    <!-- Confirm Password Input -->
                    <div>
                        <label for="formPasswordConfirmation" class="block text-sm font-semibold text-brand-forest mb-1.5">تأكيد كلمة المرور</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 right-0 flex items-center pr-4 text-gray-400">
                                <i class="fa-solid fa-lock"></i>
                            </span>
                            <input type="password" name="password_confirmation" id="formPasswordConfirmation" required
                                class="w-full pr-11 pl-11 py-2.5 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:border-brand-emerald focus:ring-2 focus:ring-brand-emerald/10 transition-all duration-300 outline-none text-[14px]"
                                placeholder="••••••••">
                            <button type="button" onclick="togglePasswordVisibility('formPasswordConfirmation', 'togglePasswordIcon2')" 
                                class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400 hover:text-brand-forest transition-colors">
                                <i class="fa-solid fa-eye text-sm" id="togglePasswordIcon2"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit"
                    class="w-full py-3 px-4 bg-gradient-to-l from-brand-forest to-brand-emerald text-white font-bold rounded-xl shadow-md hover:shadow-lg hover:shadow-brand-forest/20 hover:scale-[1.01] active:scale-[0.99] transition-all duration-300 mt-2">
                    إنشاء الحساب الآن <i class="fa-solid fa-user-plus mr-1.5 text-sm"></i>
                </button>

                <!-- Divider -->
                <div class="relative my-4">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gray-100"></div>
                    </div>
                    <div class="relative flex justify-center text-xs uppercase">
                        <span class="bg-white px-3 text-gray-400">أو</span>
                    </div>
                </div>

                <!-- Back to Login Button -->
                <div class="text-center">
                    <p class="text-sm text-gray-400 mb-2">لديك حساب بالفعل في المنصة؟</p>
                    <a href="{{ route('login') }}" 
                        class="inline-flex items-center justify-center w-full py-2.5 px-4 border border-brand-emerald text-brand-emerald font-bold rounded-xl hover:bg-brand-emerald hover:text-white transition-all duration-300">
                        <i class="fa-solid fa-arrow-right-to-bracket ml-2 text-sm"></i> تسجيل الدخول
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Page Logic JS -->
    <script>
        function togglePasswordVisibility(fieldId, iconId) {
            const field = document.getElementById(fieldId);
            const icon = document.getElementById(iconId);
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        function checkPasswordStrength(password) {
            const container = document.getElementById('strengthContainer');
            const bar = document.getElementById('strengthBar');
            const text = document.getElementById('strengthText');
            
            if (password.length === 0) {
                container.style.display = 'none';
                return;
            }

            container.style.display = 'block';
            let score = 0;

            if (password.length >= 6) score++;
            if (password.length >= 8) score++;
            if (/[a-zA-Z]/.test(password) && /[0-9]/.test(password)) score++;
            if (/[^a-zA-Z0-9]/.test(password)) score++;

            if (score <= 1) {
                bar.style.width = '33%';
                bar.className = 'h-full bg-red-500 rounded-full transition-all duration-500';
                text.innerText = 'كلمة مرور ضعيفة';
                text.className = 'text-[11px] font-bold mt-1 text-red-500 block';
            } else if (score === 2 || score === 3) {
                bar.style.width = '66%';
                bar.className = 'h-full bg-yellow-500 rounded-full transition-all duration-500';
                text.innerText = 'كلمة مرور متوسطة';
                text.className = 'text-[11px] font-bold mt-1 text-yellow-600 block';
            } else {
                bar.style.width = '100%';
                bar.className = 'h-full bg-emerald-500 rounded-full transition-all duration-500';
                text.innerText = 'كلمة مرور قوية جداً';
                text.className = 'text-[11px] font-bold mt-1 text-emerald-600 block';
            }
        }
    </script>
</body>

</html>
