<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تواصل العائلة - استعادة كلمة المرور</title>

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
                <i class="fa-solid fa-key text-3xl"></i>
            </div>
            <h1 class="text-2xl font-bold font-title text-brand-forest">نسيت كلمة المرور؟</h1>
            <p class="text-sm text-gray-400 mt-2">لا تقلق، أدخل بريدك الإلكتروني وسنرسل لك رابطاً لاستعادتها</p>
        </div>

        <!-- Alerts (Errors/Success) -->
        <div class="mb-4">
            @include('components.alerts')
        </div>

        <!-- Form -->
        <form action="{{ route('resetPassword') }}" method="POST" class="space-y-6">
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

            <!-- Submit Button -->
            <button type="submit" 
                class="w-full py-3 px-4 bg-gradient-to-l from-brand-forest to-brand-emerald text-white font-bold rounded-xl shadow-md hover:shadow-lg hover:shadow-brand-forest/20 hover:scale-[1.01] active:scale-[0.99] transition-all duration-300">
                إرسال رابط الاستعادة <i class="fa-solid fa-paper-plane mr-1.5 text-sm"></i>
            </button>

            <!-- Back to Login Link -->
            <div class="text-center pt-2">
                <a href="{{ route('login') }}" class="inline-flex items-center text-sm font-bold text-brand-emerald hover:text-brand-forest transition-colors">
                    <i class="fa-solid fa-arrow-right ml-2 text-xs"></i> العودة لتسجيل الدخول
                </a>
            </div>
        </form>
    </div>
</body>

</html>
