<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تواصل العائلة - تعيين كلمة مرور جديدة</title>

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
    </style>
</head>

<body class="font-sans antialiased text-gray-800 flex items-center justify-center p-4 min-h-screen">

    <div class="w-full max-w-md rounded-3xl overflow-hidden bg-white border border-gray-100 shadow-[0_20px_60px_rgba(20,81,71,0.06)] p-8 sm:p-10 transition-all duration-300">
        
        <!-- Logo & Header -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-brand-forest/5 text-brand-forest mb-4">
                <i class="fa-solid fa-lock-open text-3xl"></i>
            </div>
            <h1 class="text-2xl font-bold font-title text-brand-forest">تعيين كلمة مرور جديدة</h1>
            <p class="text-sm text-gray-400 mt-2">يرجى كتابة كلمة مرور جديدة قوية لتأمين حسابك</p>
        </div>

        <!-- Alerts (Errors/Success) -->
        <div class="mb-4">
            @include('components.alerts')
        </div>

        <!-- Form -->
        <form action="{{ route('password.update') }}" method="POST" class="space-y-5">
            @csrf
            
            <!-- Hidden Token -->
            <input type="hidden" name="token" value="{{ $token }}">

            <!-- Email Input -->
            <div>
                <label for="formEmail" class="block text-sm font-semibold text-brand-forest mb-1.5">البريد الإلكتروني</label>
                <div class="relative">
                    <span class="absolute inset-y-0 right-0 flex items-center pr-4 text-gray-400">
                        <i class="fa-solid fa-envelope"></i>
                    </span>
                    <input type="email" name="email" id="formEmail" value="{{ $email ?? old('email') }}" required readonly
                        class="w-full pr-11 pl-4 py-3 bg-gray-100 border border-gray-200 rounded-xl outline-none text-[15px] text-gray-400 cursor-not-allowed">
                </div>
            </div>

            <!-- New Password Input -->
            <div>
                <label for="formPassword" class="block text-sm font-semibold text-brand-forest mb-1.5">كلمة المرور الجديدة</label>
                <div class="relative">
                    <span class="absolute inset-y-0 right-0 flex items-center pr-4 text-gray-400">
                        <i class="fa-solid fa-lock"></i>
                    </span>
                    <input type="password" name="password" id="formPassword" required
                        class="w-full pr-11 pl-11 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:border-brand-emerald focus:ring-2 focus:ring-brand-emerald/10 transition-all duration-300 outline-none text-[15px]"
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
                <label for="formPasswordConfirmation" class="block text-sm font-semibold text-brand-forest mb-1.5">تأكيد كلمة المرور الجديدة</label>
                <div class="relative">
                    <span class="absolute inset-y-0 right-0 flex items-center pr-4 text-gray-400">
                        <i class="fa-solid fa-lock"></i>
                    </span>
                    <input type="password" name="password_confirmation" id="formPasswordConfirmation" required
                        class="w-full pr-11 pl-11 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:border-brand-emerald focus:ring-2 focus:ring-brand-emerald/10 transition-all duration-300 outline-none text-[15px]"
                        placeholder="••••••••">
                    <button type="button" onclick="togglePasswordVisibility('formPasswordConfirmation', 'togglePasswordIcon2')" 
                        class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400 hover:text-brand-forest transition-colors">
                        <i class="fa-solid fa-eye text-sm" id="togglePasswordIcon2"></i>
                    </button>
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit" 
                class="w-full py-3 px-4 bg-gradient-to-l from-brand-forest to-brand-emerald text-white font-bold rounded-xl shadow-md hover:shadow-lg hover:shadow-brand-forest/20 hover:scale-[1.01] active:scale-[0.99] transition-all duration-300">
                حفظ كلمة المرور الجديدة <i class="fa-solid fa-save mr-1.5 text-sm"></i>
            </button>
        </form>
    </div>

    <!-- JS logic -->
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
