<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تواصل العائلة - تسجيل الدخول</title>

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
        
        <!-- Form Section -->
        <div class="lg:col-span-6 p-8 sm:p-12 flex flex-col justify-center bg-white">
            
            <!-- Logo & Header -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-brand-forest/5 text-brand-forest mb-4 transition-transform duration-300 hover:scale-105">
                    <i class="fa-solid fa-tree text-3xl"></i>
                </div>
                <h1 class="text-2xl font-bold font-title text-brand-forest">مرحباً بك في شجرتك</h1>
                <p class="text-sm text-gray-400 mt-2">سجل دخولك لتوثيق جذورك واستكشاف تراث عائلتك</p>
            </div>

            <!-- Alerts (Errors/Success) -->
            <div class="mb-4">
                @include('components.alerts')
            </div>

            <!-- Form -->
            <form action="{{ route('customLogin') }}" method="POST" class="space-y-5">
                @csrf
                
                <!-- Email Input -->
                <div>
                    <label for="formEmail" class="block text-sm font-semibold text-brand-forest mb-1.5">البريد الإلكتروني</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 right-0 flex items-center pr-4 text-gray-400">
                            <i class="fa-solid fa-envelope"></i>
                        </span>
                        <input type="email" name="email" id="formEmail" value="{{ old('email') }}" required
                            class="w-full pr-11 pl-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:border-brand-emerald focus:ring-2 focus:ring-brand-emerald/10 transition-all duration-300 outline-none text-[15px]" 
                            placeholder="example@family.com">
                    </div>
                </div>

                <!-- Password Input -->
                <div>
                    <div class="flex justify-between items-center mb-1.5">
                        <label for="formPassword" class="text-sm font-semibold text-brand-forest">كلمة المرور</label>
                        <a href="{{ route('forgetPassword') }}" class="text-xs font-semibold text-brand-emerald hover:text-brand-forest hover:underline transition-colors">هل نسيت كلمة المرور؟</a>
                    </div>
                    <div class="relative">
                        <span class="absolute inset-y-0 right-0 flex items-center pr-4 text-gray-400">
                            <i class="fa-solid fa-lock"></i>
                        </span>
                        <input type="password" name="password" id="formPassword" required
                            class="w-full pr-11 pl-11 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:border-brand-emerald focus:ring-2 focus:ring-brand-emerald/10 transition-all duration-300 outline-none text-[15px]"
                            placeholder="••••••••">
                        <button type="button" onclick="togglePasswordVisibility('formPassword', 'togglePasswordIcon')" 
                            class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400 hover:text-brand-forest transition-colors">
                            <i class="fa-solid fa-eye text-sm" id="togglePasswordIcon"></i>
                        </button>
                    </div>
                </div>

                <!-- Remember Me Checkbox -->
                <div class="flex items-center">
                    <input type="checkbox" name="remember" id="remember" class="w-4 h-4 text-brand-emerald border-gray-300 rounded focus:ring-brand-emerald">
                    <label for="remember" class="mr-2 text-sm text-gray-500 cursor-pointer select-none">تذكرني على هذا الجهاز</label>
                </div>

                <!-- Submit Button -->
                <button type="submit" 
                    class="w-full py-3 px-4 bg-gradient-to-l from-brand-forest to-brand-emerald text-white font-bold rounded-xl shadow-md hover:shadow-lg hover:shadow-brand-forest/20 hover:scale-[1.01] active:scale-[0.99] transition-all duration-300">
                    تسجيل الدخول <i class="fa-solid fa-arrow-left-to-bracket mr-1.5 text-sm"></i>
                </button>

                <!-- Divider -->
                <div class="relative my-6">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gray-100"></div>
                    </div>
                    <div class="relative flex justify-center text-xs uppercase">
                        <span class="bg-white px-3 text-gray-400">أو</span>
                    </div>
                </div>

                <!-- Register Button -->
                <div class="text-center">
                    <p class="text-sm text-gray-400 mb-3">ليس لديك حساب في المنصة؟</p>
                    <a href="{{ route('register') }}" 
                        class="inline-flex items-center justify-center w-full py-2.5 px-4 border border-brand-emerald text-brand-emerald font-bold rounded-xl hover:bg-brand-emerald hover:text-white transition-all duration-300">
                        <i class="fa-solid fa-user-plus ml-2 text-sm"></i> إنشاء حساب جديد
                    </a>
                </div>
            </form>
        </div>

        <!-- Visual/Side Section -->
        <div class="lg:col-span-6 bg-gradient-to-br from-[#edf4f0] to-[#f6f3eb] p-12 text-brand-forest flex flex-col justify-between relative overflow-hidden hidden lg:flex border-r border-gray-100">
            <!-- subtle decorative patterns -->
            <div class="absolute -top-12 -right-12 w-48 h-48 bg-brand-forest/5 rounded-full"></div>
            <div class="absolute -bottom-24 -left-24 w-64 h-64 bg-brand-gold/10 rounded-full filter blur-xl"></div>
            
            <div class="flex items-center justify-between z-10">
                <span class="text-brand-forest/80 font-bold text-sm flex items-center">
                    <i class="fa-solid fa-shield-halved ml-1.5 text-xs text-brand-emerald"></i> بوابة آمنة وموثقة
                </span>
                <span class="text-xs text-brand-forest/60">عائلة السريّع</span>
            </div>

            <!-- Quote/Message -->
            <div class="my-auto z-10 space-y-5 max-w-md">
                <div class="w-12 h-1 bg-brand-gold rounded"></div>
                <h2 class="text-3xl font-bold font-title leading-snug text-brand-forest">"معرفة تاريخنا هي معرفة أنفسنا."</h2>
                <p class="text-gray-600 text-sm leading-relaxed">
                    تواصل العائلة هي أكثر من مجرد أسماء وتواريخ، إنها قصة الأجيال، روابط المحبة، والتراث الذي يجمعنا. ابدأ اليوم رحلتك في توثيق هذه القصة الخالدة.
                </p>
            </div>

            <!-- Bottom Note -->
            <div class="flex items-center space-x-3 space-x-reverse z-10">
                <div class="w-10 h-10 rounded-full bg-brand-forest/5 flex items-center justify-center text-brand-forest floating-icon">
                    <i class="fa-solid fa-feather-alt text-lg"></i>
                </div>
                <div>
                    <h4 class="text-xs font-bold text-brand-forest">شجرة العائلة الرقمية</h4>
                    <p class="text-[10px] text-gray-500">تاريخنا، نكتبه معاً بأيدينا.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Password visibility toggle JS -->
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
    </script>
</body>

</html>
