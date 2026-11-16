@extends('layouts.web-site.web')

@section('title', $title . ' - ' . $siteName)

@push('styles')
    <style>
        .legal-page {
            direction: rtl;
            text-align: right;
        }

        .legal-content {
            color: #374151;
            font-size: 1.05rem;
            line-height: 2;
            overflow-wrap: anywhere;
        }

        .legal-content > :first-child {
            margin-top: 0;
        }

        .legal-content h2 {
            position: relative;
            margin: 2.75rem 0 1.1rem;
            padding-right: 1rem;
            color: #145147;
            font-size: 1.45rem;
            font-weight: 800;
            line-height: 1.6;
            scroll-margin-top: 7rem;
        }

        .legal-content h2::before {
            position: absolute;
            top: 0.35rem;
            right: 0;
            width: 4px;
            height: calc(100% - 0.7rem);
            border-radius: 999px;
            background: linear-gradient(180deg, #37a05c, #145147);
            content: "";
        }

        .legal-content h3 {
            margin: 1.85rem 0 0.8rem;
            color: #1f6b52;
            font-size: 1.18rem;
            font-weight: 700;
            line-height: 1.7;
            scroll-margin-top: 7rem;
        }

        .legal-content p {
            margin: 0 0 1rem;
        }

        .legal-content strong {
            color: #145147;
            font-weight: 800;
        }

        .legal-content ul,
        .legal-content ol {
            margin: 0 0 1.35rem;
            padding-right: 1.5rem;
        }

        .legal-content ul {
            list-style: disc;
        }

        .legal-content ol {
            list-style: decimal;
        }

        .legal-content li {
            margin-bottom: 0.5rem;
            padding-right: 0.15rem;
        }

        .legal-content li::marker {
            color: #37a05c;
            font-weight: 700;
        }

        .legal-content blockquote {
            margin: 1.5rem 0;
            padding: 1.1rem 1.25rem;
            border-right: 4px solid #10b981;
            border-radius: 0.85rem;
            background: linear-gradient(135deg, #ecfdf5 0%, #f0fdf4 100%);
            color: #065f46;
        }

        .legal-content blockquote p:last-child {
            margin-bottom: 0;
        }

        .legal-content a {
            color: #047857;
            font-weight: 600;
            text-decoration: underline;
            text-underline-offset: 3px;
        }

        .legal-content a:hover {
            color: #145147;
        }

        .legal-content hr {
            margin: 2.5rem 0;
            border: 0;
            border-top: 1px dashed #d1d5db;
        }

        .legal-content code {
            padding: 0.15rem 0.4rem;
            border-radius: 0.35rem;
            background: #f3f4f6;
            font-size: 0.9em;
        }

        .legal-toc a {
            transition: all 0.2s ease;
        }

        .legal-toc a:hover,
        .legal-toc a:focus-visible {
            padding-right: 0.4rem;
            color: #145147;
            background: #ecfdf5;
        }

        @media print {
            .legal-no-print {
                display: none !important;
            }

            .legal-content {
                font-size: 12pt;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $badgeLabels = [
            'privacy' => 'سياسة الخصوصية',
            'terms' => 'الشروط والأحكام',
            'about' => 'من نحن',
        ];
        $badgeLabel = $badgeLabels[$type] ?? 'وثيقة رسمية';
        $healthSection = collect($sections)->first(
            fn ($section) => str_contains($section['title'], 'إفصاح البيانات')
        );
        $relatedPages = collect([
            'privacy' => ['label' => 'سياسة الخصوصية', 'route' => 'pages.privacy', 'icon' => 'fa-shield-halved'],
            'terms' => ['label' => 'الشروط والأحكام', 'route' => 'pages.terms', 'icon' => 'fa-file-signature'],
            'about' => ['label' => 'من نحن', 'route' => 'pages.about', 'icon' => 'fa-circle-info'],
        ])->except($type);
    @endphp

    <div class="legal-page min-h-screen bg-gradient-to-br from-gray-50 via-emerald-50/30 to-green-50/40">
        {{-- Hero --}}
        <section class="gradient-primary relative overflow-hidden">
            <div class="absolute -top-24 -left-24 h-72 w-72 rounded-full bg-white/10 blur-2xl"></div>
            <div class="absolute -bottom-32 -right-16 h-80 w-80 rounded-full bg-emerald-300/20 blur-3xl"></div>

            <div class="relative mx-auto max-w-6xl px-4 py-12 md:py-16">
                <div class="flex flex-col items-start justify-between gap-8 md:flex-row md:items-center">
                    <div class="max-w-3xl text-white">
                        <span class="mb-4 inline-flex items-center gap-2 rounded-full border border-white/25 bg-white/10 px-4 py-2 text-sm font-semibold text-emerald-50 backdrop-blur">
                            <i class="fa-solid fa-file-shield"></i>
                            {{ $badgeLabel }}
                        </span>

                        <h1 class="mb-3 text-3xl font-extrabold leading-tight md:text-4xl lg:text-5xl" style="font-family: 'Alexandria', sans-serif;">
                            {{ $title }}
                        </h1>

                        <p class="mb-4 text-emerald-50/90">
                            {{ $siteName }} — الشفافية والأمان جزء من التزامنا تجاه أفراد العائلة.
                        </p>

                        <div class="flex flex-wrap items-center gap-3 text-sm text-emerald-50/90">
                            @if($lastUpdated)
                                <span class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1.5">
                                    <i class="fa-regular fa-calendar-check"></i>
                                    آخر تحديث: {{ $lastUpdated }}
                                </span>
                            @endif
                            <span class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1.5">
                                <i class="fa-solid fa-location-dot"></i>
                                المملكة العربية السعودية
                            </span>
                        </div>
                    </div>

                    <div class="legal-no-print flex flex-wrap gap-3">
                        <a href="{{ url('/') }}"
                           class="inline-flex items-center gap-2 rounded-xl bg-white px-5 py-3 text-sm font-bold text-emerald-900 shadow-lg transition hover:-translate-y-0.5 hover:bg-emerald-50">
                            <i class="fa-solid fa-arrow-right"></i>
                            العودة للرئيسية
                        </a>
                        <button type="button"
                                onclick="window.print()"
                                class="inline-flex items-center gap-2 rounded-xl border border-white/30 bg-white/10 px-5 py-3 text-sm font-bold text-white backdrop-blur transition hover:bg-white/20">
                            <i class="fa-solid fa-print"></i>
                            طباعة
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <div class="mx-auto max-w-6xl px-4 py-10 md:py-14">
            @if(!$enabled)
                <div class="mx-auto max-w-2xl rounded-3xl border border-amber-200 bg-white p-8 text-center shadow-lg">
                    <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-amber-100 text-3xl text-amber-600">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                    <h2 class="mb-2 text-xl font-extrabold text-gray-900">هذه الصفحة غير مفعلة حالياً</h2>
                    <p class="mb-0 text-gray-500">يرجى مراجعة إدارة المنصة للمزيد من المعلومات.</p>
                </div>
            @else
                <div class="{{ count($sections) ? 'grid items-start gap-8 lg:grid-cols-[300px_minmax(0,1fr)]' : '' }}">
                    @if(count($sections))
                        <aside class="legal-no-print lg:sticky lg:top-24">
                            <div class="rounded-3xl border border-emerald-100 bg-white p-6 shadow-lg shadow-emerald-900/5">
                                <div class="mb-4 flex items-center gap-3 border-b border-emerald-50 pb-4">
                                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700">
                                        <i class="fa-solid fa-list-ul"></i>
                                    </span>
                                    <div>
                                        <h2 class="text-base font-extrabold text-gray-900">محتويات الصفحة</h2>
                                        <p class="text-xs text-gray-400">انتقل سريعاً إلى أي قسم</p>
                                    </div>
                                </div>

                                <nav class="legal-toc max-h-72 space-y-1 overflow-y-auto pl-1 lg:max-h-[calc(100vh-22rem)]">
                                    @foreach($sections as $section)
                                        <a href="#{{ $section['id'] }}"
                                           class="flex items-start gap-2 rounded-lg px-3 py-2 text-sm leading-6 text-gray-600">
                                            <span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-400"></span>
                                            <span>{{ $section['title'] }}</span>
                                        </a>
                                    @endforeach
                                </nav>

                                @if($healthSection)
                                    <a href="#{{ $healthSection['id'] }}"
                                       class="mt-5 block rounded-2xl border border-emerald-200 bg-emerald-50 p-4 transition hover:border-emerald-300 hover:bg-emerald-100/70">
                                        <span class="mb-1 flex items-center gap-2 text-sm font-extrabold text-emerald-900">
                                            <i class="fa-solid fa-heart-pulse"></i>
                                            إفصاح البيانات الصحية
                                        </span>
                                        <span class="block text-xs leading-6 text-emerald-700">
                                            تعرّف على كيفية الوصول إلى بيانات الصحة واللياقة وجمعها واستخدامها.
                                        </span>
                                    </a>
                                @endif
                            </div>

                            @if($relatedPages->isNotEmpty())
                                <div class="mt-4 rounded-3xl border border-gray-100 bg-white p-5 shadow-sm">
                                    <h2 class="mb-3 text-sm font-extrabold text-gray-800">صفحات ذات صلة</h2>
                                    <div class="space-y-2">
                                        @foreach($relatedPages as $page)
                                            <a href="{{ route($page['route']) }}"
                                               class="flex items-center justify-between rounded-xl border border-gray-100 px-3 py-2.5 text-sm font-semibold text-gray-600 transition hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-800">
                                                <span class="flex items-center gap-2">
                                                    <i class="fa-solid {{ $page['icon'] }} text-emerald-500"></i>
                                                    {{ $page['label'] }}
                                                </span>
                                                <i class="fa-solid fa-angle-left text-xs text-gray-400"></i>
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </aside>
                    @endif

                    <article class="overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-xl shadow-emerald-900/5">
                        <div class="h-1.5 bg-gradient-to-l from-[#145147] via-[#37a05c] to-[#7ed957]"></div>

                        <div class="p-6 md:p-10">
                            <div class="legal-content">
                                {!! $contentHtml !!}
                            </div>

                            <div class="mt-10 rounded-2xl border border-emerald-100 bg-gradient-to-br from-emerald-50 to-green-50 p-6 md:p-7">
                                <div class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
                                    <div class="flex items-start gap-4">
                                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-white text-xl text-emerald-600 shadow-sm">
                                            <i class="fa-solid fa-envelope-open-text"></i>
                                        </span>
                                        <div>
                                            <h2 class="mb-1 text-base font-extrabold text-emerald-950">هل لديك سؤال حول خصوصيتك؟</h2>
                                            <p class="mb-0 text-sm leading-7 text-emerald-800/80">
                                                تواصل معنا لأي استفسار أو طلب يتعلق ببياناتك الشخصية أو البيانات الصحية.
                                            </p>
                                        </div>
                                    </div>
                                    <a href="mailto:{{ $supportEmail }}"
                                       class="inline-flex shrink-0 items-center gap-2 rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-800">
                                        <i class="fa-solid fa-paper-plane"></i>
                                        مراسلة الإدارة
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="border-t border-gray-100 bg-gray-50/70 px-6 py-4 text-center text-xs text-gray-400 md:px-10">
                            جميع الحقوق محفوظة &copy; {{ date('Y') }} {{ $siteName }}
                        </div>
                    </article>
                </div>
            @endif
        </div>
    </div>
@endsection
