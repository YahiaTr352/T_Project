<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $person->full_name }} - الملف الشخصي</title>

    {{-- استخدام نفس إعدادات Tailwind والخطوط --}}
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'sans': ['Tajawal', 'sans-serif'],
                        'serif': ['Amiri', 'serif'],
                    },
                    keyframes: {
                        float: {
                            '0%, 100%': {
                                transform: 'translateY(0px)'
                            },
                            '50%': {
                                transform: 'translateY(-10px)'
                            },
                        },
                        fadeInUp: {
                            'from': {
                                opacity: '0',
                                transform: 'translateY(20px)'
                            },
                            'to': {
                                opacity: '1',
                                transform: 'translateY(0)'
                            },
                        }
                    },
                    animation: {
                        'float': 'float 6s ease-in-out infinite',
                        'fade-in-up': 'fadeInUp 0.5s ease-out forwards',
                    },
                    boxShadow: {
                        'green-glow': '0 0 40px rgba(34, 197, 94, 0.2)',
                    }
                }
            }
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;900&family=Amiri:wght@400;700&display=swap"
        rel="stylesheet">
    <style>
        body {
            font-family: 'Tajawal', sans-serif;
        }

        h1,
        h2,
        h3,
        h4 {
            font-family: 'Amiri', serif;
        }

        ::-webkit-scrollbar {
            width: 10px;
        }

        ::-webkit-scrollbar-track {
            background: #f0fdf4;
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, #22c55e, #16a34a);
            border-radius: 5px;
        }

        .animate-delay-100 {
            animation-delay: 100ms;
        }

        .animate-delay-200 {
            animation-delay: 200ms;
        }

        .animate-delay-300 {
            animation-delay: 300ms;
        }
    </style>
</head>

<body class="bg-gradient-to-br from-green-50 to-emerald-50 text-gray-800">
    @php
        $privacyService = $privacyService ?? app(\App\Services\ProfilePrivacyService::class);
        $canViewPhoto = $privacyService->canViewField($person, 'photo');
        $displayAvatar = $canViewPhoto ? $person->avatar : ($person->gender === 'female' ? asset('assets/img/avatar-female-hejap-1.png') : asset('assets/img/avatar-male.png'));

        $getDisplayAvatar = function (?App\Models\Person $member) use ($privacyService): string {
            if (! $member) {
                return asset('assets/img/avatar-male.png');
            }
            if (! $privacyService->canViewField($member, 'photo')) {
                return $member->gender === 'female'
                    ? asset('assets/img/avatar-female-hejap-1.png')
                    : asset('assets/img/avatar-male.png');
            }
            return $member->avatar;
        };
    @endphp
    @include('partials.main-header')
    <main class="container mx-auto px-4 py-8 lg:py-12 relative z-10 max-w-7xl">
        <div class="mb-8">
            <a href="{{ url()->previous() }}"
                class="inline-flex items-center gap-2 px-4 py-2 bg-white/60 backdrop-blur-md border border-white/30 rounded-full hover:bg-white/90 transition-all duration-300 group shadow-sm hover:shadow-lg">
                <svg class="w-5 h-5 text-green-600 transition-transform group-hover:-translate-x-1" fill="none"
                    stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                <span class="font-medium text-gray-700">العودة للخلف</span>
            </a>
        </div>

        <div class="lg:grid lg:grid-cols-3 lg:gap-12">

            {{-- العمود الرئيسي: الصورة والمعلومات الأساسية --}}
            <div class="lg:col-span-2">
                <article
                    class="bg-white/80 backdrop-blur-md border border-white/30 rounded-3xl overflow-hidden shadow-green-glow">
                    <header class="p-6 lg:p-10 bg-gradient-to-br from-green-50/50 to-emerald-50/20">
                        <div class="flex flex-col sm:flex-row items-center text-center sm:text-right gap-6">
                            <img src="{{ $displayAvatar }}" alt="{{ $person->first_name }}"
                                onerror="this.onerror=null; this.src='{{ $person->gender === 'female' ? asset('assets/img/avatar-female-hejap-1.png') : asset('assets/img/avatar-male.png') }}';"
                                class="flex-shrink-0 w-32 h-32 lg:w-40 lg:h-40 rounded-full border-4 border-white shadow-lg object-cover">
                            <div class="flex-grow">
                                <h1
                                    class="text-3xl sm:text-4xl lg:text-5xl font-bold mb-4 leading-relaxed font-serif bg-gradient-to-r from-green-600 to-emerald-500 text-transparent bg-clip-text">
                                    {{ $person->full_name }}
                                </h1>
                                {{-- حاوية المعلومات المختصرة --}}
                                <div
                                    class="flex flex-wrap items-center justify-center sm:justify-start gap-x-6 gap-y-3 text-base text-gray-600">
                                    {{-- العمر وتاريخ الميلاد للرجال فقط مع فحص الخصوصية --}}
                                    @if ($person->gender === 'male' && $privacyService->canViewField($person, 'birth_date'))
                                        @if ($person->age)
                                            <div class="flex items-center gap-2">
                                                <span>(العمر: {{ $person->age }} عاماً)</span>
                                            </div>
                                        @endif
                                        @if ($person->birth_date)
                                            <div class="flex items-center gap-2">
                                                <i class="fas fa-birthday-cake text-green-500"></i>
                                                <span>{{ $person->birth_date }}</span>
                                            </div>
                                        @endif
                                    @endif

                                    {{-- عرض الجنس --}}
                                    @if ($person->gender)
                                        <div class="flex items-center gap-2">
                                            <i class="fas {{ $person->gender == 'male' ? 'fa-mars text-blue-500' : 'fa-venus text-pink-500' }}"></i>
                                            <span>{{ $person->gender == 'male' ? 'ذكر' : 'أنثى' }}</span>
                                        </div>
                                    @endif

                                    {{-- حالة الوفاة --}}
                                    @if ($person->death_date)
                                        <div class="flex items-center gap-2">
                                            <i class="fas fa-dove text-gray-600"></i>
                                            <span>{{ $person->gender == 'female' ? 'رحمها الله' : 'رحمه الله' }}</span>
                                        </div>
                                    @endif
                                </div>

                                {{-- وسائل التواصل المسموح للمشاهد برؤيتها --}}
                                @if ($person->contactAccounts && $person->contactAccounts->isNotEmpty())
                                    <div class="mt-4 flex flex-wrap gap-2 justify-center sm:justify-start">
                                        @foreach($person->contactAccounts as $account)
                                            <a href="{{ $account->url }}" target="_blank" rel="noopener"
                                               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white hover:bg-emerald-50 text-gray-800 text-xs font-semibold shadow-sm hover:shadow transition border border-emerald-100 hover:border-emerald-300">
                                                <i class="fab {{ $account->icon }} text-emerald-600 text-sm"></i>
                                                <span>{{ $account->label ?: $account->value }}</span>
                                            </a>
                                        @endforeach
                                    </div>
                                @endif

                                {{-- زر عرض في شجرة العائلة --}}
                                <div class="mt-4 flex flex-wrap gap-3 justify-center sm:justify-start">
                                    <a href="{{ route('sila') }}#person-{{ $person->id }}"
                                        class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-green-500 to-emerald-500 text-white font-bold rounded-lg shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1 hover:scale-105">
                                        <i class="fas fa-sitemap"></i>
                                        <span>عرض في شجرة العائلة</span>
                                    </a>

                                    @php
                                        $identityPersonId = app(\App\Services\SiteIdentityResolver::class)->viewerPersonId();
                                    @endphp
                                    @if($identityPersonId && (int) $identityPersonId === (int) $person->id)
                                        <span class="inline-flex items-center gap-2 px-6 py-3 bg-emerald-50 text-emerald-700 font-bold rounded-lg border border-emerald-200">هذا أنت</span>
                                    @elseif($identityPersonId)
                                    <button onclick="openKinshipModal({{ $person->id }}, '{{ addslashes($person->full_name) }}', 'compare_with_me')"
                                        class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-amber-500 to-yellow-500 text-white font-bold rounded-lg shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1 hover:scale-105">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                        </svg>
                                        <span>صلة القرابة معي</span>
                                    </button>
                                    @endif

                                    <button onclick="openKinshipModal({{ $person->id }}, '{{ addslashes($person->full_name) }}', 'compare_as_source')"
                                        class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-emerald-600 to-green-600 text-white font-bold rounded-lg shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1 hover:scale-105">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                        </svg>
                                        <span>مقارنة هذا الشخص مع عضو آخر...</span>
                                    </button>
                                </div>


                            </div>
                        </div>
                    </header>

                    @if ($person->biography && $privacyService->canViewField($person, 'biography'))
                        <div class="p-6 lg:p-10 border-t border-green-200/60">
                            <div
                                class="prose prose-lg max-w-none prose-p:leading-relaxed prose-headings:font-serif prose-headings:text-green-700">
                                <h2>نبذة تعريفية</h2>
                                <p>{{ $person->biography }}</p>
                            </div>
                        </div>
                    @endif

                    {{-- معلومات إضافية --}}
                    <div class="p-6 lg:p-10 border-t border-green-200/60">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            {{-- تاريخ الميلاد (للرجال فقط) --}}
                            @if ($person->gender === 'male' && $person->birth_date && $privacyService->canViewField($person, 'birth_date'))
                                <div class="bg-green-50 rounded-xl p-4">
                                    <div class="flex items-center gap-3 mb-2">
                                        <i class="fas fa-birthday-cake text-green-600 text-xl"></i>
                                        <h3 class="font-bold text-gray-800">تاريخ الميلاد</h3>
                                    </div>
                                    <p class="text-gray-700">{{ $person->birth_date }}</p>
                                </div>
                            @endif

                            {{-- العمر (للرجال فقط) --}}
                            @if ($person->gender === 'male' && $person->age && $privacyService->canViewField($person, 'birth_date'))
                                <div class="bg-green-50 rounded-xl p-4">
                                    <div class="flex items-center gap-3 mb-2">
                                        <i class="fas fa-calendar-alt text-green-600 text-xl"></i>
                                        <h3 class="font-bold text-gray-800">العمر</h3>
                                    </div>
                                    <p class="text-gray-700">{{ $person->age }} سنة</p>
                                </div>
                            @endif

                            {{-- مكان الميلاد --}}
                            @if ($person->birth_place && $privacyService->canViewField($person, 'birth_date'))
                                <div class="bg-green-50 rounded-xl p-4">
                                    <div class="flex items-center gap-3 mb-2">
                                        <i class="fas fa-map-marker-alt text-green-600 text-xl"></i>
                                        <h3 class="font-bold text-gray-800">مكان الميلاد</h3>
                                    </div>
                                    <p class="text-gray-700">{{ $person->birth_place }}</p>
                                </div>
                            @endif

                            {{-- مكان الإقامة --}}
                            @if ($person->location && $privacyService->canViewField($person, 'location'))
                                <div class="bg-green-50 rounded-xl p-4">
                                    <div class="flex items-center gap-3 mb-2">
                                        <i class="fas fa-home text-green-600 text-xl"></i>
                                        <h3 class="font-bold text-gray-800">مكان الإقامة</h3>
                                    </div>
                                    <p class="text-gray-700">{{ $person->location->name }}</p>
                                </div>
                            @endif

                            {{-- المهنة --}}
                            @if ($person->occupation && $privacyService->canViewField($person, 'occupation'))
                                <div class="bg-green-50 rounded-xl p-4">
                                    <div class="flex items-center gap-3 mb-2">
                                        <i class="fas fa-briefcase text-green-600 text-xl"></i>
                                        <h3 class="font-bold text-gray-800">المهنة</h3>
                                    </div>
                                    <p class="text-gray-700">{{ $person->occupation }}</p>
                                </div>
                            @endif

                            {{-- تاريخ الوفاة --}}
                            @if ($person->death_date)
                                <div class="bg-gray-50 rounded-xl p-4">
                                    <div class="flex items-center gap-3 mb-2">
                                        <i class="fas fa-dove text-gray-600 text-xl"></i>
                                        <h3 class="font-bold text-gray-800">تاريخ الوفاة</h3>
                                    </div>
                                    <p class="text-gray-700">{{ $person->death_date }}</p>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- الأب والأم --}}
                    @if ($person->parent || $person->mother)
                        <div class="p-6 lg:p-10 border-t border-green-200/60">
                            <h2 class="text-2xl font-bold font-serif mb-6 text-green-700">الوالدين</h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                @if ($person->parent)
                                    <a href="{{ route('people.profile.show', $person->parent->id) }}"
                                        class="group bg-white rounded-xl p-4 shadow-md hover:shadow-lg transition-all duration-300 border border-green-200 hover:border-green-400">
                                        <div class="flex items-center gap-4">
                                            <div class="w-16 h-16 rounded-full overflow-hidden border-2 border-green-300 flex-shrink-0 bg-emerald-50">
                                                <img src="{{ $getDisplayAvatar($person->parent) }}" alt="{{ $person->parent->full_name }}" class="w-full h-full object-cover" loading="lazy" onerror="this.onerror=null; this.src='{{ asset('assets/img/avatar-male.png') }}';">
                                            </div>
                                            <div class="flex-grow">
                                                <h3 class="font-bold text-gray-800 group-hover:text-green-600 transition-colors">{{ $person->parent->full_name }}</h3>
                                                <p class="text-sm text-gray-600">الأب</p>
                                                @if ($person->parent->death_date)
                                                    <p class="text-xs text-gray-500 mt-1">رحمه الله</p>
                                                @endif
                                            </div>
                                        </div>
                                    </a>
                                @endif

                                @if ($person->mother)
                                    <a href="{{ route('people.profile.show', $person->mother->id) }}"
                                        class="group bg-white rounded-xl p-4 shadow-md hover:shadow-lg transition-all duration-300 border border-green-200 hover:border-green-400">
                                        <div class="flex items-center gap-4">
                                            <div class="w-16 h-16 rounded-full overflow-hidden border-2 border-pink-300 flex-shrink-0 bg-pink-50">
                                                <img src="{{ $getDisplayAvatar($person->mother) }}" alt="{{ $person->mother->full_name }}" class="w-full h-full object-cover" loading="lazy" onerror="this.onerror=null; this.src='{{ asset('assets/img/avatar-female-hejap-1.png') }}';">
                                            </div>
                                            <div class="flex-grow">
                                                <h3 class="font-bold text-gray-800 group-hover:text-green-600 transition-colors">{{ $person->mother->full_name }}</h3>
                                                <p class="text-sm text-gray-600">الأم</p>
                                                @if ($person->mother->death_date)
                                                    <p class="text-xs text-gray-500 mt-1">رحمها الله</p>
                                                @endif
                                            </div>
                                        </div>
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endif

                    {{-- الأخوة --}}
                    @if ($siblings && $siblings->count() > 0)
                        <div class="p-6 lg:p-10 border-t border-green-200/60">
                            <h2 class="text-2xl font-bold font-serif mb-6 text-green-700">الإخوة والأخوات</h2>
                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                                @foreach ($siblings as $sibling)
                                    <a href="{{ route('people.profile.show', $sibling->id) }}"
                                        class="group bg-white rounded-xl p-4 shadow-md hover:shadow-lg transition-all duration-300 border border-green-200 hover:border-green-400 text-center">
                                        <div class="w-20 h-20 rounded-full overflow-hidden border-2 {{ $sibling->gender == 'male' ? 'border-blue-300 bg-blue-50' : 'border-pink-300 bg-pink-50' }} mx-auto mb-3">
                                            <img src="{{ $getDisplayAvatar($sibling) }}" alt="{{ $sibling->full_name }}" class="w-full h-full object-cover" loading="lazy" onerror="this.onerror=null; this.src='{{ $sibling->gender == 'female' ? asset('assets/img/avatar-female-hejap-1.png') : asset('assets/img/avatar-male.png') }}';">
                                        </div>
                                        <h3 class="font-bold text-sm text-gray-800 group-hover:text-green-600 transition-colors line-clamp-2">{{ $sibling->full_name }}</h3>
                                        @if ($sibling->death_date)
                                            <p class="text-xs text-gray-500 mt-1">{{ $sibling->gender == 'male' ? 'رحمه الله' : 'رحمها الله' }}</p>
                                        @endif
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- الزوجات/الزوج --}}
                    @if ($spouses && $spouses->count() > 0)
                        <div class="p-6 lg:p-10 border-t border-green-200/60">
                            <h2 class="text-2xl font-bold font-serif mb-6 text-green-700">{{ $person->gender == 'male' ? 'الزوجات' : 'الزوج' }}</h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                @foreach ($spouses as $spouse)
                                    <a href="{{ route('people.profile.show', $spouse->id) }}"
                                        class="group bg-white rounded-xl p-4 shadow-md hover:shadow-lg transition-all duration-300 border border-green-200 hover:border-green-400">
                                        <div class="flex items-center gap-4">
                                            <div class="w-16 h-16 rounded-full overflow-hidden border-2 {{ $spouse->gender == 'male' ? 'border-blue-300 bg-blue-50' : 'border-pink-300 bg-pink-50' }} flex-shrink-0">
                                                <img src="{{ $getDisplayAvatar($spouse) }}" alt="{{ $spouse->full_name }}" class="w-full h-full object-cover" loading="lazy" onerror="this.onerror=null; this.src='{{ $spouse->gender == 'female' ? asset('assets/img/avatar-female-hejap-1.png') : asset('assets/img/avatar-male.png') }}';">
                                            </div>
                                            <div class="flex-grow">
                                                <h3 class="font-bold text-gray-800 group-hover:text-green-600 transition-colors">{{ $spouse->full_name }}</h3>
                                                <p class="text-sm text-gray-600">{{ $spouse->gender == 'male' ? 'زوج' : 'زوجة' }}</p>
                                                @if ($spouse->death_date)
                                                    <p class="text-xs text-gray-500 mt-1">{{ $spouse->gender == 'male' ? 'رحمه الله' : 'رحمها الله' }}</p>
                                                @endif
                                            </div>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- الأبناء --}}
                    @if ($children && $children->count() > 0)
                        <div class="p-6 lg:p-10 border-t border-green-200/60">
                            <h2 class="text-2xl font-bold font-serif mb-6 text-green-700">الأبناء</h2>
                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                                @foreach ($children as $child)
                                    <a href="{{ route('people.profile.show', $child->id) }}"
                                        class="group bg-white rounded-xl p-4 shadow-md hover:shadow-lg transition-all duration-300 border border-green-200 hover:border-green-400 text-center">
                                        <div class="w-20 h-20 rounded-full overflow-hidden border-2 {{ $child->gender == 'male' ? 'border-blue-300 bg-blue-50' : 'border-pink-300 bg-pink-50' }} mx-auto mb-3">
                                            <img src="{{ $getDisplayAvatar($child) }}" alt="{{ $child->full_name }}" class="w-full h-full object-cover" loading="lazy" onerror="this.onerror=null; this.src='{{ $child->gender == 'female' ? asset('assets/img/avatar-female-hejap-1.png') : asset('assets/img/avatar-male.png') }}';">
                                        </div>
                                        <h3 class="font-bold text-sm text-gray-800 group-hover:text-green-600 transition-colors line-clamp-2">{{ $child->full_name }}</h3>
                                        @if ($child->death_date)
                                            <p class="text-xs text-gray-500 mt-1">{{ $child->gender == 'male' ? 'رحمه الله' : 'رحمها الله' }}</p>
                                        @endif
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </article>
            </div>

            {{-- العمود الجانبي: الأوسمة والتكريمات --}}
            <aside class="lg:col-span-1 mt-12 lg:mt-0">
                <div class="sticky top-12">
                    <h2
                        class="text-3xl font-bold font-serif mb-6 bg-gradient-to-r from-green-600 to-emerald-500 text-transparent bg-clip-text flex items-center gap-3">
                        <svg class="w-8 h-8" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M12.963 2.286a.75.75 0 00-1.071 1.052A6.75 6.75 0 0115.75 12c0 2.56-1.42 4.823-3.568 5.952a.75.75 0 00.568 1.402A8.25 8.25 0 0017.25 12c0-3.36-2.02-6.22-4.932-7.564a.75.75 0 00-.355-.15z"
                                clip-rule="evenodd" />
                            <path
                                d="M12 2.25a.75.75 0 000 1.5v16.5a.75.75 0 000 1.5h.75a.75.75 0 000-1.5H12V3.75a.75.75 0 00-.75-.75h-.75a.75.75 0 00.75-.75zM8.25 12a3.75 3.75 0 117.5 0 3.75 3.75 0 01-7.5 0zM12 14.25a2.25 2.25 0 100-4.5 2.25 2.25 0 000 4.5z" />
                        </svg>
                        الأوسمة والتكريمات
                    </h2>

                    @if ($person->padges->isNotEmpty())
                        <div class="space-y-4">
                            @foreach ($person->padges as $index => $padge)
                                <div class="opacity-0 animate-fade-in-up"
                                    style="animation-delay: {{ $index * 100 }}ms;">
                                    <div
                                        class="group flex items-start gap-4 p-4 bg-white/90 backdrop-blur-md rounded-2xl border border-transparent hover:border-green-300 hover:shadow-lg transition-all duration-300">
                                        <div
                                            class="flex-shrink-0 w-12 h-12 bg-gradient-to-br from-green-100 to-green-200 rounded-lg flex items-center justify-center transition-transform duration-300 group-hover:scale-110 group-hover:rotate-6">
                                            @if (isset($padge->image))
                                                <img src="{{ asset('storage/' . $padge->image) }}" class="w-7 h-7">
                                            @else
                                                <svg class="w-7 h-7 text-green-600" xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd"
                                                        d="M15.188 8.42a.75.75 0 01.293.972l-3.033 7.076a.75.75 0 01-1.341-.575l.386-1.714a3.75 3.75 0 00-.83-2.324l-3.14-4.593a.75.75 0 111.233-.842l2.378 3.481a3.75 3.75 0 004.47 1.293l1.587-.68a.75.75 0 01.972.293z"
                                                        clip-rule="evenodd" />
                                                    <path
                                                        d="M9.919 4.31a.75.75 0 01.782.646l.704 4.223a.75.75 0 01-1.48.248l-.704-4.223a.75.75 0 01.7- .894zM10 2a.75.75 0 01.75.75v.008a.75.75 0 01-1.5 0V2.75A.75.75 0 0110 2zM5.992 5.514a.75.75 0 01.884-.462l4.223.704a.75.75 0 01-.248 1.48l-4.223-.704a.75.75 0 01-.413-1.018zM3.204 9.11a.75.75 0 011.018.413l.704 4.223a.75.75 0 11-1.48.248l-.704-4.223a.75.75 0 01.462-.884z" />
                                                </svg>
                                            @endif
                                        </div>
                                        <div class="flex-grow">
                                            <h4 class="font-bold text-gray-800 text-lg">{{ $padge->name }}</h4>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center p-6 bg-white/60 rounded-2xl">
                            <p class="text-gray-500">لم يحصل على أي أوسمة بعد.</p>
                        </div>
                    @endif
                </div>
            </aside>

        </div>
    </main>

    <!-- Kinship Modal (Website Brand Visual Identity & Mobile Layout) -->
    <div id="kinshipModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-md z-50 hidden items-center justify-center p-3 sm:p-6 overflow-y-auto">
        <div class="bg-white rounded-[28px] max-w-3xl w-full p-4 sm:p-7 shadow-[0_20px_60px_rgba(0,0,0,0.15)] relative animate-fade-in-up border border-emerald-500/20 my-6 text-slate-800">
            <button onclick="closeKinshipModal()" class="absolute top-4 left-4 w-9 h-9 rounded-full bg-slate-100 hover:bg-slate-200 flex items-center justify-center text-slate-600 hover:text-slate-900 transition-colors z-20 border border-slate-200">
                ✕
            </button>

            <!-- Search Picker -->
            <div id="kinshipPicker" class="hidden mb-6 p-4 rounded-2xl bg-slate-50 border border-slate-200">
                <label class="block text-sm font-bold text-emerald-800 mb-2">اختر العضو الآخر لمقارنة النسب معه</label>
                <input type="text" id="kinshipSearchInput" class="w-full bg-white border border-slate-300 rounded-xl px-4 py-3 text-sm text-slate-800 placeholder-slate-400 focus:ring-2 focus:ring-emerald-500 focus:outline-none" placeholder="ابحث بالاسم..." autocomplete="off">
                <input type="hidden" id="kinshipOtherPersonId">
                <div id="kinshipSearchResults" class="mt-2 max-h-56 overflow-y-auto hidden border border-slate-200 rounded-xl bg-white shadow-xl"></div>
            </div>

            <!-- Loader -->
            <div id="kinshipLoader" class="py-20 text-center hidden">
                <div class="inline-block animate-spin rounded-full h-14 w-14 border-4 border-emerald-500 border-t-transparent mb-4 shadow-[0_0_20px_rgba(55,160,92,0.3)]"></div>
                <p class="text-emerald-800 font-bold text-base">جاري بناء شجرة النسب والقرابة...</p>
            </div>

            <!-- Kinship Content Area -->
            <div id="kinshipContent" class="hidden space-y-5">
                <!-- 📸 Capturable Tree Card (Website Brand Identity) -->
                <div id="kinshipCaptureCard" class="bg-gradient-to-b from-[#f0fdf4] via-[#ffffff] to-[#f0fdf4] rounded-[24px] p-5 sm:p-8 text-slate-800 shadow-sm relative overflow-hidden border-2 border-emerald-300">
                    
                    <!-- Decorative Background Motif -->
                    <div class="absolute inset-0 opacity-40 pointer-events-none bg-[radial-gradient(#86efac_1px,transparent_1px)] [background-size:24px_24px]"></div>

                    <!-- Top Insignia Header -->
                    <div class="text-center relative z-10 mb-4">
                        <div class="inline-flex items-center gap-1.5 text-xs text-emerald-800 font-bold bg-emerald-100 border border-emerald-300 px-4 py-1.5 rounded-full shadow-sm mb-3">
                            <span>✦</span>
                            <span>عائلة السريّع 🌴</span>
                            <span>✦</span>
                        </div>
                    </div>

                    <!-- 🌳 Visual Lineage Tree Container -->
                    <div id="kinshipTreeContainer" class="relative z-10 py-1">
                        <!-- Dynamic Tree Generated by JS with SVG Bezier Curves -->
                    </div>

                    <!-- 👑 Relationship Title Ribbon at Bottom of Tree -->
                    <div class="text-center relative z-10 mt-6 mb-2">
                        <div id="kinshipTitleBadge" class="inline-block bg-gradient-to-r from-[#145147] to-[#37a05c] text-white font-extrabold text-base sm:text-lg px-8 py-2.5 rounded-full shadow-[0_8px_24px_rgba(20,81,71,0.25)] border-2 border-emerald-300"></div>
                    </div>

                    <!-- Summary & Watermark Footer -->
                    <div class="relative z-10 mt-4 pt-3 border-t border-emerald-200 text-center">
                        <p id="kinshipSummary" class="text-slate-600 text-xs sm:text-sm leading-relaxed max-w-lg mx-auto font-medium"></p>
                        <div class="text-xs text-emerald-800 font-bold mt-2 tracking-wide">تطبيق ومنصة عائلة السريّع 🌴</div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex flex-col sm:flex-row gap-3 pt-2">
                    <button id="shareKinshipImgBtn" onclick="shareKinshipAsImage()" class="flex-1 bg-gradient-to-r from-[#25D366] to-[#128C7E] hover:opacity-95 text-white py-3.5 px-6 rounded-2xl font-bold text-sm flex items-center justify-center gap-2 shadow-[0_8px_20px_rgba(37,211,102,0.3)] transition-all active:scale-[0.98]">
                        <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                        <span>مشاركة صلة القرابة (صورة 📸)</span>
                    </button>
                    <button onclick="closeKinshipModal()" class="bg-slate-100 hover:bg-slate-200 text-slate-700 py-3.5 px-6 rounded-2xl font-bold text-sm transition-colors border border-slate-200">
                        إغلاق
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Include html2canvas -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    <script>
        const CURRENT_USER_PERSON_ID = @json($identityPersonId ?? app(\App\Services\SiteIdentityResolver::class)->viewerPersonId());
        let kinshipPersonsCache = null;

        function openKinshipModal(personId, name, mode = 'compare_with_me') {
            document.getElementById('kinshipModal').classList.remove('hidden');
            document.getElementById('kinshipModal').classList.add('flex');
            document.getElementById('kinshipContent').classList.add('hidden');
            document.getElementById('kinshipLoader').classList.add('hidden');
            document.getElementById('kinshipPicker').classList.add('hidden');
            document.getElementById('kinshipSearchResults').classList.add('hidden');
            document.getElementById('kinshipSearchInput').value = '';
            document.getElementById('kinshipOtherPersonId').value = '';

            if (mode === 'compare_with_me') {
                if (!CURRENT_USER_PERSON_ID) {
                    alert('ادخل ببصمة الوجه أو واتساب لمقارنة صلة القرابة معك، أو استخدم مقارنة عضو آخر.');
                    closeKinshipModal();
                    return;
                }
                if (personId === CURRENT_USER_PERSON_ID) {
                    alert('لا يمكن مقارنة الشخص بنفسه.');
                    closeKinshipModal();
                    return;
                }
                calculateKinship(null, personId);
                return;
            }

            document.getElementById('kinshipPicker').classList.remove('hidden');
            setupKinshipSearch(personId);
            setTimeout(() => document.getElementById('kinshipSearchInput').focus(), 200);
        }

        function setupKinshipSearch(sourcePersonId) {
            const input = document.getElementById('kinshipSearchInput');
            const results = document.getElementById('kinshipSearchResults');

            const render = (query) => {
                const q = (query || '').trim();
                if (!q || !kinshipPersonsCache) {
                    results.classList.add('hidden');
                    results.innerHTML = '';
                    return;
                }
                const matches = kinshipPersonsCache.filter((p) => {
                    const label = (p.full_name || `${p.first_name} ${p.last_name || ''}`).toLowerCase();
                    return p.id !== sourcePersonId && label.includes(q.toLowerCase());
                }).slice(0, 8);

                results.innerHTML = matches.map((p) => {
                    const label = p.full_name || `${p.first_name} ${p.last_name || ''}`;
                    return `<button type="button" class="w-full text-right px-4 py-3 hover:bg-amber-500/20 text-sm font-medium border-b border-emerald-800/40 flex items-center justify-between text-white transition-colors" data-id="${p.id}"><span>${label}</span><span class="text-xs text-amber-400 font-bold">اختيار ←</span></button>`;
                }).join('') || '<div class="px-4 py-3 text-sm text-emerald-300/60 text-center">لا توجد نتائج</div>';
                results.classList.remove('hidden');
            };

            input.oninput = () => {
                if (kinshipPersonsCache) {
                    render(input.value);
                    return;
                }
                fetch('/api/persons/search')
                    .then((res) => res.json())
                    .then((data) => {
                        kinshipPersonsCache = data.persons || [];
                        render(input.value);
                    });
            };

            results.onclick = (event) => {
                const btn = event.target.closest('button[data-id]');
                if (!btn) return;
                const otherId = parseInt(btn.dataset.id, 10);
                input.value = btn.querySelector('span').textContent.trim();
                document.getElementById('kinshipOtherPersonId').value = otherId;
                results.classList.add('hidden');
                calculateKinship(sourcePersonId, otherId);
            };
        }

        function calculateKinship(personAId, personBId) {
            document.getElementById('kinshipPicker').classList.add('hidden');
            document.getElementById('kinshipLoader').classList.remove('hidden');
            document.getElementById('kinshipContent').classList.add('hidden');

            let query = `?person_b_id=${personBId}`;
            if (personAId) query += `&person_a_id=${personAId}`;

            fetch(`/api/v1/kinship/calculate${query}`)
                .then(res => res.json())
                .then(data => {
                    document.getElementById('kinshipLoader').classList.add('hidden');
                    if (data.success && data.data) {
                        document.getElementById('kinshipContent').classList.remove('hidden');
                        document.getElementById('kinshipTitleBadge').innerText = data.data.relationship.title;
                        document.getElementById('kinshipSummary').innerText = data.data.relationship.summary_text;

                        renderVisualKinshipTree(data.data);
                    } else {
                        alert(data.message || 'تعذر حساب صلة القرابة.');
                        closeKinshipModal();
                    }
                })
                .catch(err => {
                    document.getElementById('kinshipLoader').classList.add('hidden');
                    alert('حدث خطأ في طلب القرابة.');
                    closeKinshipModal();
                });
        }

        function getShortDisplayName(person) {
            if (!person) return '';
            if (person.id === 0 || person.is_virtual_family_root || person.name === 'عائلة السريّع' || person.full_name === 'عائلة السريّع' || person.name === 'السريّع' || person.full_name === 'السريّع' || person.name === 'عائلة السريع' || person.full_name === 'عائلة السريع' || person.name === 'السريع' || person.full_name === 'السريع') {
                return 'السريّع';
            }
            const raw = (person.first_name || person.name || person.full_name || '').trim();
            const parts = raw.split(/\s+/);
            if (parts.length >= 2 && (parts[0] === 'عبد' || parts[0] === 'ابو' || parts[0] === 'أبو' || parts[0] === 'ام' || parts[0] === 'أم' || parts[0] === 'ابن')) {
                return parts[0] + ' ' + parts[1];
            }
            return parts[0] || raw;
        }

        function renderVisualKinshipTree(data) {
            const container = document.getElementById('kinshipTreeContainer');
            container.innerHTML = '';

            const rel = data.relationship;
            const ancestor = rel.common_ancestor;
            const branchA = data.branch_a || [];
            const branchB = data.branch_b || [];
            const upperChain = data.upper_chain || [];

            // Helper to render individual person avatar node (photo + short name only)
            const makeNodeHtml = (person, type = 'normal') => {
                if (!person) return '';
                const isVirtual = person.id === 0 || person.is_virtual_family_root || person.name === 'عائلة السريّع' || person.full_name === 'عائلة السريّع' || person.name === 'السريّع' || person.full_name === 'السريّع' || person.name === 'عائلة السريع' || person.full_name === 'عائلة السريع' || person.name === 'السريع' || person.full_name === 'السريع';
                const photo = person.avatar || person.photo_url || (isVirtual ? '/assets/img/family-logo.png' : (person.gender === 'female' ? '/assets/img/avatar-female-hejap-1.png' : '/assets/img/avatar-male.png'));
                const name = getShortDisplayName(person);

                if (type === 'root' || isVirtual) {
                    return `
                        <div class="flex flex-col items-center text-center relative z-20 my-1">
                            <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-full p-1 bg-gradient-to-tr from-amber-600 via-yellow-400 to-amber-300 shadow-[0_4px_16px_rgba(217,119,6,0.3)]">
                                <img src="${photo}" alt="${name}" class="w-full h-full object-cover rounded-full bg-white border border-amber-200">
                            </div>
                            <div class="font-bold text-[#145147] text-xs sm:text-sm mt-1 max-w-[130px] leading-tight truncate">${name}</div>
                        </div>
                    `;
                }

                if (type === 'apex') {
                    return `
                        <div class="flex flex-col items-center text-center relative z-20 my-1">
                            <div class="text-amber-500 text-xl sm:text-2xl -mb-1 drop-shadow-sm">👑</div>
                            <div class="w-15 h-15 sm:w-17 sm:h-17 rounded-full p-1 bg-gradient-to-tr from-amber-600 via-amber-400 to-yellow-300 shadow-[0_8px_24px_rgba(217,119,6,0.25)]">
                                <img src="${photo}" alt="${name}" class="w-full h-full object-cover rounded-full bg-white border border-amber-200">
                            </div>
                            <div class="font-bold text-[#145147] text-xs sm:text-sm mt-1 max-w-[150px] leading-tight truncate">${name}</div>
                        </div>
                    `;
                }

                if (type === 'target') {
                    return `
                        <div class="flex flex-col items-center text-center relative z-20 my-1">
                            <div class="w-13 h-13 sm:w-15 sm:h-15 rounded-full p-1 bg-gradient-to-tr from-emerald-600 via-emerald-400 to-teal-300 shadow-[0_0_16px_rgba(52,211,153,0.4)]">
                                <img src="${photo}" alt="${name}" class="w-full h-full object-cover rounded-full bg-white border border-emerald-200">
                            </div>
                            <div class="font-bold text-slate-900 text-xs sm:text-sm mt-1 max-w-[130px] leading-tight truncate">${name}</div>
                        </div>
                    `;
                }

                // Normal intermediate node
                return `
                    <div class="flex flex-col items-center text-center relative z-20 my-1">
                        <div class="w-11 h-11 sm:w-13 sm:h-13 rounded-full p-0.5 bg-gradient-to-tr from-amber-500 to-yellow-400 shadow-[0_3px_10px_rgba(217,119,6,0.15)]">
                            <img src="${photo}" alt="${name}" class="w-full h-full object-cover rounded-full bg-white border border-amber-200">
                        </div>
                        <div class="font-medium text-slate-700 text-[11px] sm:text-xs mt-1 max-w-[120px] leading-tight truncate">${name}</div>
                    </div>
                `;
            };

            // Helper to render a node with optional horizontal spouse link (without clutter)
            const renderNodeWithSpouse = (person, type = 'normal') => {
                const nodeHtml = makeNodeHtml(person, type);
                if (!person.spouse) {
                    return nodeHtml;
                }

                const spouse = person.spouse;
                const spouseRole = spouse.role || (spouse.gender === 'female' ? 'زوجة' : 'زوج');
                const spouseNodeHtml = makeNodeHtml(spouse, 'target');

                const connectorHtml = `
                    <div class="flex flex-col items-center justify-center px-1">
                        <span class="text-[9px] sm:text-[10px] text-amber-800 font-bold px-1.5 py-0.5 bg-amber-100 border border-amber-300 rounded-full shadow-xs whitespace-nowrap mb-0.5">
                            ${spouseRole}
                        </span>
                        <div class="w-4 sm:w-7 h-0.5 bg-gradient-to-r from-emerald-400 via-amber-400 to-amber-500 rounded-full"></div>
                    </div>
                `;

                const spouseBlockLeft = `
                    <div class="flex items-center">
                        ${spouseNodeHtml}
                        ${connectorHtml}
                    </div>
                `;

                const dummyBlockRight = `
                    <div class="flex items-center opacity-0 pointer-events-none" aria-hidden="true">
                        ${connectorHtml}
                        ${spouseNodeHtml}
                    </div>
                `;

                const spouseBlockRight = `
                    <div class="flex items-center">
                        ${connectorHtml}
                        ${spouseNodeHtml}
                    </div>
                `;

                const dummyBlockLeft = `
                    <div class="flex items-center opacity-0 pointer-events-none" aria-hidden="true">
                        ${spouseNodeHtml}
                        ${connectorHtml}
                    </div>
                `;

                return `
                    <div class="flex items-center justify-center my-1 z-20">
                        ${isBranchA ? `
                            ${spouseBlockLeft}
                            ${nodeHtml}
                            ${dummyBlockRight}
                        ` : `
                            ${dummyBlockLeft}
                            ${nodeHtml}
                            ${spouseBlockRight}
                        `}
                    </div>
                `;
            };

            // 1. Unrelated Person
            if (rel.type === 'unrelated') {
                container.innerHTML = `
                    <div class="flex flex-col items-center justify-center p-6 text-center max-w-md mx-auto bg-slate-50 rounded-2xl border border-slate-200">
                        <div class="flex items-center gap-4 mb-3">
                            ${makeNodeHtml(data.person_a, 'self')}
                            <div class="flex flex-col items-center">
                                <span class="text-xl text-slate-400">✕</span>
                                <span class="text-[10px] text-slate-500 font-bold px-2 py-0.5 bg-slate-200 rounded-full mt-1">لا توجد صلة</span>
                            </div>
                            ${makeNodeHtml(data.person_b, 'target')}
                        </div>
                        <p class="text-xs sm:text-sm text-slate-600 leading-relaxed font-medium">
                            ${rel.summary_text || 'هذا الشخص من خارج العائلة ولا توجد صلة نسب أو مصاهرة مسجلة تربطه بالطرف الآخر.'}
                        </p>
                    </div>
                `;
                return;
            }

            // 2. Direct Marriage (Affinal Spouse)
            if (rel.type === 'affinal_spouse') {
                const familyPerson = rel.family_person || (data.person_a.from_outside_the_family ? data.person_b : data.person_a);
                const spousePerson = rel.spouse_info || (data.person_a.from_outside_the_family ? data.person_a : data.person_b);
                const familyBranch = (data.person_a.id === familyPerson.id ? branchA : branchB) || [];
                const cleanFamilyChain = familyBranch.filter(p => p.id !== 0 && !p.is_virtual_family_root).reverse();

                let html = '<div class="flex flex-col items-center gap-1 py-1">';

                // Upper chain (Virtual root badge 'عائلة السريع')
                if (upperChain && upperChain.length > 0) {
                    upperChain.forEach((node) => {
                        const isRoot = node.id === 0 || node.is_virtual_family_root;
                        html += makeNodeHtml(node, isRoot ? 'root' : 'normal');
                        html += '<div class="w-1 h-5 bg-gradient-to-b from-amber-400 to-yellow-500 rounded-full shadow-sm my-0.5"></div>';
                    });
                }

                // Ancestry line down to parent of family member
                for (let i = 0; i < cleanFamilyChain.length - 1; i++) {
                    html += makeNodeHtml(cleanFamilyChain[i], i === 0 ? 'apex' : 'normal');
                    html += '<div class="w-1 h-5 bg-gradient-to-b from-amber-400 to-emerald-500 rounded-full shadow-sm my-0.5"></div>';
                }

                // Bottom: Clean Horizontal Marriage Line
                const targetFamilyNode = cleanFamilyChain[cleanFamilyChain.length - 1] || familyPerson;
                const spouseRoleLabel = (spousePerson.gender === 'female' ? 'زوجة' : 'زوج');

                html += `
                    <div class="flex items-center justify-center gap-2 sm:gap-4 my-1 relative z-20">
                        ${makeNodeHtml(targetFamilyNode, 'target')}
                        <div class="flex flex-col items-center justify-center px-1">
                            <span class="text-[10px] sm:text-[11px] text-amber-800 font-bold px-2.5 py-0.5 bg-amber-100 border border-amber-300 rounded-full shadow-xs whitespace-nowrap mb-0.5">
                                ${spouseRoleLabel}
                            </span>
                            <div class="w-8 sm:w-14 h-0.5 bg-gradient-to-r from-emerald-400 via-amber-400 to-amber-500 rounded-full"></div>
                        </div>
                        ${makeNodeHtml(spousePerson, 'normal')}
                    </div>
                `;
                html += '</div>';
                container.innerHTML = html;
                return;
            }

            // 3. Direct Descent (e.g. Father & Son, Grandparent & Grandson)
            if (rel.is_direct || (!ancestor && branchA.length === 0)) {
                const path = data.path || [];
                let html = '<div class="flex flex-col items-center gap-1 py-1">';
                
                if (upperChain.length > 0) {
                    upperChain.forEach((node) => {
                        const isRoot = node.id === 0 || node.is_virtual_family_root;
                        html += makeNodeHtml(node, isRoot ? 'root' : 'normal');
                        html += '<div class="w-1 h-5 bg-gradient-to-b from-amber-400 to-yellow-500 rounded-full shadow-sm my-0.5"></div>';
                    });
                }

                path.forEach((step, idx) => {
                    const isLast = (idx === path.length - 1);
                    html += renderNodeWithSpouse(step, idx === 0 ? 'apex' : (isLast ? 'target' : 'normal'));
                    if (!isLast) {
                        html += '<div class="w-1 h-5 bg-gradient-to-b from-amber-400 to-emerald-500 rounded-full shadow-sm my-0.5"></div>';
                    }
                });
                html += '</div>';
                container.innerHTML = html;
                return;
            }

            // 4. Two-Branch Tree (Collateral, Virtual Root, or In-Law)
            const cleanBranchA = ancestor ? branchA.filter(p => p.id !== ancestor.id).reverse() : branchA.slice().reverse();
            const cleanBranchB = ancestor ? branchB.filter(p => p.id !== ancestor.id).reverse() : branchB.slice().reverse();

            let treeHtml = `<div class="flex flex-col items-center w-full max-w-xl mx-auto">`;

            // Upper Lineage Chain (Ancestors above LCA)
            const isAncestorVirtual = !ancestor || ancestor.id === 0 || ancestor.is_virtual_family_root || ancestor.name === 'عائلة السريّع' || ancestor.name === 'السريّع' || ancestor.name === 'عائلة السريع' || ancestor.name === 'السريع';
            const cleanUpperChain = (upperChain || []).filter((node) => {
                if (!node) return false;
                if (node.id === 0 || node.is_virtual_family_root || node.name === 'عائلة السريّع' || node.first_name === 'عائلة السريّع' || node.name === 'السريّع' || node.first_name === 'السريّع' || node.name === 'عائلة السريع' || node.first_name === 'عائلة السريع' || node.name === 'السريع' || node.first_name === 'السريع') return false;
                if (ancestor && (node.id === ancestor.id || isAncestorVirtual)) return false;
                return true;
            });

            if (cleanUpperChain.length > 0) {
                treeHtml += `<div class="flex flex-col items-center">`;
                cleanUpperChain.forEach((node) => {
                    const isRoot = node.id === 0 || node.is_virtual_family_root;
                    treeHtml += `
                        <div class="flex flex-col items-center">
                            ${makeNodeHtml(node, isRoot ? 'root' : 'normal')}
                            <div class="w-1 h-5 bg-gradient-to-b from-amber-400 to-yellow-500 rounded-full shadow-sm my-0.5"></div>
                        </div>
                    `;
                });
                treeHtml += `</div>`;
            }

            // Apex (Common Ancestor)
            if (ancestor) {
                const isVirtual = ancestor.id === 0 || ancestor.is_virtual_family_root;
                treeHtml += `
                    <div class="my-1">
                        ${makeNodeHtml(ancestor, isVirtual ? 'root' : 'apex')}
                    </div>
                `;
            }

            // SVG Smooth Arched Branch Connectors (100% Mathematically Symmetrical to Column Centers 25% and 75%)
            treeHtml += `
                <div class="w-full h-12 relative my-0">
                    <svg class="w-full h-full" viewBox="0 0 400 48" preserveAspectRatio="none">
                        <defs>
                            <linearGradient id="brandArchGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                                <stop offset="0%" stop-color="#37a05c" />
                                <stop offset="25%" stop-color="#f59e0b" />
                                <stop offset="50%" stop-color="#d97706" />
                                <stop offset="75%" stop-color="#f59e0b" />
                                <stop offset="100%" stop-color="#37a05c" />
                            </linearGradient>
                        </defs>
                        <path d="M 200 0 C 200 28, 130 16, 130 48 M 200 0 C 200 28, 270 16, 270 48" stroke="url(#brandArchGrad)" stroke-width="3.5" fill="none" stroke-linecap="round" />
                    </svg>
                </div>

                <!-- Two Symmetrical Columns (Centered under X=130 and X=270 with inward narrowing) -->
                <div class="grid grid-cols-2 w-full items-start px-10">
                    <!-- Column A (Right in RTL, lands under 130 / 270) -->
                    <div class="flex flex-col items-center w-full">
                        ${cleanBranchA.map((node, idx) => {
                            const isTarget = (idx === cleanBranchA.length - 1);
                            return `
                                <div class="flex flex-col items-center">
                                    ${renderNodeWithSpouse(node, isTarget ? 'target' : 'normal', true)}
                                    ${!isTarget ? '<div class="w-0.5 h-5 bg-emerald-400/80 rounded-full my-0.5"></div>' : ''}
                                </div>
                            `;
                        }).join('')}
                    </div>

                    <!-- Column B (Left in RTL, lands under 300 / 100) -->
                    <div class="flex flex-col items-center w-full">
                        ${cleanBranchB.map((node, idx) => {
                            const isTarget = (idx === cleanBranchB.length - 1);
                            return `
                                <div class="flex flex-col items-center">
                                    ${renderNodeWithSpouse(node, isTarget ? 'target' : 'normal', false)}
                                    ${!isTarget ? '<div class="w-0.5 h-5 bg-emerald-400/80 rounded-full my-0.5"></div>' : ''}
                                </div>
                            `;
                        }).join('')}
                    </div>
                </div>
            </div>`;

            container.innerHTML = treeHtml;
        }

        async function shareKinshipAsImage() {
            const card = document.getElementById('kinshipCaptureCard');
            const btn = document.getElementById('shareKinshipImgBtn');
            if (!card || !window.html2canvas) return;

            const originalBtnHtml = btn.innerHTML;
            btn.innerHTML = '<span class="animate-spin inline-block mr-2">⏳</span> جاري تجهيز الصورة...';
            btn.disabled = true;

            try {
                const canvas = await html2canvas(card, {
                    scale: 2.5,
                    useCORS: true,
                    allowTaint: true,
                    backgroundColor: '#ffffff',
                    scrollX: 0,
                    scrollY: 0,
                    onclone: (clonedDoc) => {
                        const clonedCard = clonedDoc.getElementById('kinshipCaptureCard');
                        if (clonedCard) {
                            clonedCard.style.width = '560px';
                            clonedCard.style.maxWidth = '560px';
                            clonedCard.style.margin = '0 auto';
                            clonedCard.style.boxSizing = 'border-box';
                        }
                    }
                });

                canvas.toBlob(async (blob) => {
                    btn.innerHTML = originalBtnHtml;
                    btn.disabled = false;

                    if (!blob) {
                        alert('تعذر إنشاء صورة القرابة.');
                        return;
                    }

                    const file = new File([blob], 'صلة_القرابة_عائلة_السريّع.png', { type: 'image/png' });

                    if (navigator.canShare && navigator.canShare({ files: [file] })) {
                        try {
                            await navigator.share({
                                title: 'صلة القرابة والنسب - عائلة السريّع',
                                text: 'شجرة صلة القرابة والنسب - عائلة السريّع 🌴',
                                files: [file]
                            });
                            return;
                        } catch (err) {
                            if (err.name === 'AbortError') return;
                        }
                    }

                    // Fallback: download the image
                    const link = document.createElement('a');
                    link.download = 'صلة_القرابة_عائلة_السريّع.png';
                    link.href = canvas.toDataURL('image/png');
                    link.click();
                }, 'image/png');
            } catch (err) {
                btn.innerHTML = originalBtnHtml;
                btn.disabled = false;
                alert('حدث خطأ أثناء التقاط الصورة.');
            }
        }

        function closeKinshipModal() {
            document.getElementById('kinshipModal').classList.add('hidden');
            document.getElementById('kinshipModal').classList.remove('flex');
        }
    </script>
</body>

</html>

