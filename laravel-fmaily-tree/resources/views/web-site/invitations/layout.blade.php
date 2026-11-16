<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'الدعوات')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Tajawal', sans-serif; background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 50%, #a7f3d0 100%); min-height: 100vh; }
        .glass-card { background: rgba(255,255,255,.95); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,.5); box-shadow: 0 10px 40px rgba(0,0,0,.08); }
        @yield('extra_styles')
    </style>
    @yield('head')
</head>
<body class="antialiased text-slate-800">
    @include('partials.main-header')
    <div class="py-8 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
        @if(session('success'))
            <div class="mb-6 rounded-xl bg-emerald-50 border-r-4 border-emerald-500 p-4 flex items-center gap-3">
                <i class="fas fa-check-circle text-emerald-500 text-xl"></i>
                <p class="text-sm text-emerald-800 font-medium">{{ session('success') }}</p>
            </div>
        @endif
        @if(session('error'))
            <div class="mb-6 rounded-xl bg-rose-50 border-r-4 border-rose-500 p-4 flex items-center gap-3">
                <i class="fas fa-exclamation-circle text-rose-500 text-xl"></i>
                <p class="text-sm text-rose-800 font-medium">{{ session('error') }}</p>
            </div>
        @endif
        @if($errors->any())
            <div class="mb-6 rounded-xl bg-rose-50 border-r-4 border-rose-500 p-4">
                <ul class="list-disc list-inside text-sm text-rose-700 space-y-1">
                    @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                </ul>
            </div>
        @endif
        @yield('content')
    </div>
    @include('web-site.partials.universal-image-viewer')
    @yield('scripts')
</body>
</html>
