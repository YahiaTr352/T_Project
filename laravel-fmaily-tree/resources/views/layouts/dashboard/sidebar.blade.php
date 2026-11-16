<aside class="sidebar-left border-right shadow-sm" id="leftSidebar" data-simplebar>
    {{-- ===== Brand Header & Mobile Close Button ===== --}}
    <div class="sidebar-brand-wrapper d-flex align-items-center justify-content-between">
        <a class="brand-logo-link" href="{{ safe_route('dashboard') }}">
            <span class="sidebar-brand-icon">
                <i class="fe fe-home"></i>
            </span>
            <span class="sidebar-brand-title">لوحة التحكم</span>
        </a>
        <button type="button" class="btn collapseSidebar toggle-btn d-lg-none text-muted" id="sidebarCloseBtn" aria-label="إغلاق القائمة">
            <i class="fe fe-x fe-18"></i>
        </button>
    </div>

    <nav class="vertnav navbar navbar-light p-0">

        {{-- ==========================================
             الإحصائيات (رابط رئيسي مباشر)
        =========================================== --}}
        @can('dashboard.view')
        <ul class="navbar-nav flex-fill w-100 mb-1">
            <li class="nav-item w-100 {{ request()->routeIs('dashboard') && !request()->routeIs('dashboard.*') ? 'active' : '' }}">
                <a class="nav-link" href="{{ safe_route('dashboard') }}">
                    <span class="nav-link-content">
                        <i class="fe fe-pie-chart item-icon"></i>
                        <span class="item-text">الإحصائيات العامة</span>
                    </span>
                </a>
            </li>
        </ul>
        @endcan

        <div class="sidebar-divider"></div>

        {{-- ==========================================
             إدارة العائلة (dropdown)
        =========================================== --}}
        @if (auth()->user()->can('people.view') ||
                auth()->user()->can('marriages.view') ||
                auth()->user()->can('breastfeeding.view') ||
                auth()->user()->can('locations.view') ||
                auth()->user()->can('friendships.view'))

            @php
                $familyActive = request()->routeIs([
                    'people.*', 'marriages.*', 'breastfeeding.*',
                    'locations.*', 'friendships.*', 'dashboard.profile-updates.*'
                ]);
                $pendingProfileUpdatesCount = $pendingProfileUpdatesCount ?? 0;
            @endphp

            <ul class="navbar-nav flex-fill w-100 mb-1">
                <li class="nav-item w-100 {{ $familyActive ? 'active' : '' }}">
                    <a class="nav-link {{ $familyActive ? '' : 'collapsed' }}"
                       href="#collapseFamily"
                       data-toggle="collapse"
                       aria-expanded="{{ $familyActive ? 'true' : 'false' }}"
                       aria-controls="collapseFamily">
                        <span class="nav-link-content">
                            <i class="fe fe-git-merge item-icon"></i>
                            <span class="item-text">إدارة العائلة</span>
                            @if ($pendingProfileUpdatesCount > 0)
                                <span class="badge badge-warning text-dark font-weight-bold ml-2 px-2 rounded-pill">{{ $pendingProfileUpdatesCount > 99 ? '99+' : $pendingProfileUpdatesCount }}</span>
                            @endif
                        </span>
                        <i class="fe fe-chevron-down nav-chevron"></i>
                    </a>
                    <div id="collapseFamily" class="collapse {{ $familyActive ? 'show' : '' }}">
                        <div class="sub-nav-wrapper">
                            <ul class="navbar-nav flex-fill w-100 sub-nav-list">
                                @can('people.view')
                                <li class="nav-item w-100 {{ request()->routeIs('people.*') ? 'active' : '' }}">
                                    <a class="nav-link sub-nav-link" href="{{ route('people.index') }}">
                                        <span class="sub-item-content">
                                            <i class="fe fe-users sub-item-icon"></i>
                                            <span class="item-text">الشخصيات</span>
                                        </span>
                                    </a>
                                </li>
                                <li class="nav-item w-100 {{ request()->routeIs('dashboard.profile-updates.*') ? 'active' : '' }}">
                                    <a class="nav-link sub-nav-link" href="{{ route('dashboard.profile-updates.index') }}">
                                        <span class="sub-item-content">
                                            <i class="fe fe-check-square sub-item-icon text-warning"></i>
                                            <span class="item-text">طلبات تعديل البيانات</span>
                                        </span>
                                        @if ($pendingProfileUpdatesCount > 0)
                                            <span class="badge badge-warning text-dark font-weight-bold rounded-pill">{{ $pendingProfileUpdatesCount }}</span>
                                        @endif
                                    </a>
                                </li>
                                @endcan
                                @can('marriages.view')
                                <li class="nav-item w-100 {{ request()->routeIs('marriages.*') ? 'active' : '' }}">
                                    <a class="nav-link sub-nav-link" href="{{ route('marriages.index') }}">
                                        <span class="sub-item-content">
                                            <i class="fe fe-heart sub-item-icon"></i>
                                            <span class="item-text">الزواج</span>
                                        </span>
                                    </a>
                                </li>
                                @endcan
                                @can('breastfeeding.view')
                                @if(Route::has('breastfeeding.index'))
                                <li class="nav-item w-100 {{ request()->routeIs('breastfeeding.*') ? 'active' : '' }}">
                                    <a class="nav-link sub-nav-link" href="{{ route('breastfeeding.index') }}">
                                        <span class="sub-item-content">
                                            <i class="fe fe-shield sub-item-icon"></i>
                                            <span class="item-text">الرضاعة</span>
                                        </span>
                                    </a>
                                </li>
                                @endif
                                @endcan
                                @can('friendships.view')
                                <li class="nav-item w-100 {{ request()->routeIs('friendships.*') ? 'active' : '' }}">
                                    <a class="nav-link sub-nav-link" href="{{ route('friendships.index') }}">
                                        <span class="sub-item-content">
                                            <i class="fe fe-user-check sub-item-icon"></i>
                                            <span class="item-text">الأصدقاء</span>
                                        </span>
                                    </a>
                                </li>
                                @endcan
                                @can('locations.view')
                                <li class="nav-item w-100 {{ request()->routeIs('locations.*') ? 'active' : '' }}">
                                    <a class="nav-link sub-nav-link" href="{{ route('locations.index') }}">
                                        <span class="sub-item-content">
                                            <i class="fe fe-map-pin sub-item-icon"></i>
                                            <span class="item-text">الأماكن</span>
                                        </span>
                                    </a>
                                </li>
                                @endcan
                            </ul>
                        </div>
                    </div>
                </li>
            </ul>
        @endif

        {{-- ==========================================
             الصفحة الرئيسية (dropdown)
        =========================================== --}}
        @if (auth()->user()->can('site-content.view') ||
                auth()->user()->can('slideshow.view') ||
                auth()->user()->can('home-gallery.view') ||
                auth()->user()->can('courses.view') ||
                auth()->user()->can('programs.view') ||
                auth()->user()->can('councils.view'))

            @php
                $homeActive = request()->routeIs([
                    'dashboard.site-content.*', 'dashboard.slideshow.*', 'dashboard.home-gallery.*',
                    'dashboard.home-sections.*', 'dashboard.courses.*', 'dashboard.program-categories.*',
                    'dashboard.programs.*', 'dashboard.proud-of.*', 'dashboard.councils.*',
                    'dashboard.events.*', 'dashboard.family-news.*',
                ]);
            @endphp

            <ul class="navbar-nav flex-fill w-100 mb-1">
                <li class="nav-item w-100 {{ $homeActive ? 'active' : '' }}">
                    <a class="nav-link {{ $homeActive ? '' : 'collapsed' }}"
                       href="#collapseHome"
                       data-toggle="collapse"
                       aria-expanded="{{ $homeActive ? 'true' : 'false' }}"
                       aria-controls="collapseHome">
                        <span class="nav-link-content">
                            <i class="fe fe-layout item-icon"></i>
                            <span class="item-text">الصفحة الرئيسية</span>
                        </span>
                        <i class="fe fe-chevron-down nav-chevron"></i>
                    </a>
                    <div id="collapseHome" class="collapse {{ $homeActive ? 'show' : '' }}">
                        <div class="sub-nav-wrapper">
                            <ul class="navbar-nav flex-fill w-100 sub-nav-list">
                                @can('site-content.view')
                                <li class="nav-item w-100 {{ request()->routeIs('dashboard.site-content.*') ? 'active' : '' }}">
                                    <a class="nav-link sub-nav-link" href="{{ route('dashboard.site-content.index') }}">
                                        <span class="sub-item-content">
                                            <i class="fe fe-file-text sub-item-icon"></i>
                                            <span class="item-text">محتوى الصفحة</span>
                                        </span>
                                    </a>
                                </li>
                                <li class="nav-item w-100 {{ request()->routeIs('dashboard.home-sections.*') ? 'active' : '' }}">
                                    <a class="nav-link sub-nav-link" href="{{ route('dashboard.home-sections.index') }}">
                                        <span class="sub-item-content">
                                            <i class="fe fe-grid sub-item-icon"></i>
                                            <span class="item-text">الأقسام الديناميكية</span>
                                        </span>
                                    </a>
                                </li>
                                @endcan
                                @can('slideshow.view')
                                <li class="nav-item w-100 {{ request()->routeIs('dashboard.slideshow.*') ? 'active' : '' }}">
                                    <a class="nav-link sub-nav-link" href="{{ route('dashboard.slideshow.index') }}">
                                        <span class="sub-item-content">
                                            <i class="fe fe-monitor sub-item-icon"></i>
                                            <span class="item-text">السلايدشو</span>
                                        </span>
                                    </a>
                                </li>
                                @endcan
                                @can('home-gallery.view')
                                <li class="nav-item w-100 {{ request()->routeIs('dashboard.home-gallery.*') ? 'active' : '' }}">
                                    <a class="nav-link sub-nav-link" href="{{ route('dashboard.home-gallery.index') }}">
                                        <span class="sub-item-content">
                                            <i class="fe fe-image sub-item-icon"></i>
                                            <span class="item-text">صور الصفحة الرئيسية</span>
                                        </span>
                                    </a>
                                </li>
                                @endcan
                                @can('courses.view')
                                <li class="nav-item w-100 {{ request()->routeIs('dashboard.courses.*') ? 'active' : '' }}">
                                    <a class="nav-link sub-nav-link" href="{{ route('dashboard.courses.index') }}">
                                        <span class="sub-item-content">
                                            <i class="fe fe-book sub-item-icon"></i>
                                            <span class="item-text">الدورات</span>
                                        </span>
                                    </a>
                                </li>
                                @endcan
                                @can('programs.view')
                                <li class="nav-item w-100 {{ request()->routeIs(['dashboard.programs.*', 'dashboard.program-categories.*']) ? 'active' : '' }}">
                                    <a class="nav-link sub-nav-link" href="{{ route('dashboard.programs.index') }}">
                                        <span class="sub-item-content">
                                            <i class="fe fe-layers sub-item-icon"></i>
                                            <span class="item-text">البرامج</span>
                                        </span>
                                    </a>
                                </li>
                                <li class="nav-item w-100 {{ request()->routeIs('dashboard.proud-of.*') ? 'active' : '' }}">
                                    <a class="nav-link sub-nav-link" href="{{ route('dashboard.proud-of.index') }}">
                                        <span class="sub-item-content">
                                            <i class="fe fe-award sub-item-icon"></i>
                                            <span class="item-text">نفتخر بهم</span>
                                        </span>
                                    </a>
                                </li>
                                @endcan
                                @can('councils.view')
                                <li class="nav-item w-100 {{ request()->routeIs('dashboard.councils.*') ? 'active' : '' }}">
                                    <a class="nav-link sub-nav-link" href="{{ route('dashboard.councils.index') }}">
                                        <span class="sub-item-content">
                                            <i class="fe fe-users sub-item-icon"></i>
                                            <span class="item-text">مجالس العائلة</span>
                                        </span>
                                    </a>
                                </li>
                                <li class="nav-item w-100 {{ request()->routeIs('dashboard.events.*') ? 'active' : '' }}">
                                    <a class="nav-link sub-nav-link" href="{{ route('dashboard.events.index') }}">
                                        <span class="sub-item-content">
                                            <i class="fe fe-calendar sub-item-icon"></i>
                                            <span class="item-text">مناسبات العائلة</span>
                                        </span>
                                    </a>
                                </li>
                                <li class="nav-item w-100 {{ request()->routeIs('dashboard.family-news.*') ? 'active' : '' }}">
                                    <a class="nav-link sub-nav-link" href="{{ route('dashboard.family-news.index') }}">
                                        <span class="sub-item-content">
                                            <i class="fe fe-rss sub-item-icon"></i>
                                            <span class="item-text">أخبار العائلة</span>
                                        </span>
                                    </a>
                                </li>
                                @endcan
                            </ul>
                        </div>
                    </div>
                </li>
            </ul>
        @endif

        {{-- ==========================================
             المحتوى والوسائط (dropdown)
        =========================================== --}}
        @if (auth()->user()->can('articles.view') ||
                auth()->user()->can('categories.view') ||
                auth()->user()->can('images.view') ||
                auth()->user()->can('stories.view'))

            @php
                $contentActive = request()->routeIs([
                    'articles.*', 'categories.*', 'dashboard.images.*', 'stories.*',
                    'dashboard.competitions.*', 'dashboard.quran-competitions.*',
                    'dashboard.quiz-competitions.*', 'dashboard.important-links.*',
                    'dashboard.sponsors.*'
                ]);
                $pendingImportantLinksCount = $pendingImportantLinksCount ?? 0;
            @endphp

            <ul class="navbar-nav flex-fill w-100 mb-1">
                <li class="nav-item w-100 {{ $contentActive ? 'active' : '' }}">
                    <a class="nav-link {{ $contentActive ? '' : 'collapsed' }}"
                       href="#collapseContent"
                       data-toggle="collapse"
                       aria-expanded="{{ $contentActive ? 'true' : 'false' }}"
                       aria-controls="collapseContent">
                        <span class="nav-link-content">
                            <i class="fe fe-folder item-icon"></i>
                            <span class="item-text">المحتوى والوسائط</span>
                            @if ($pendingImportantLinksCount > 0)
                                <span class="badge badge-danger rounded-pill">{{ $pendingImportantLinksCount > 99 ? '99+' : $pendingImportantLinksCount }}</span>
                            @endif
                        </span>
                        <i class="fe fe-chevron-down nav-chevron"></i>
                    </a>
                    <div id="collapseContent" class="collapse {{ $contentActive ? 'show' : '' }}">
                        <div class="sub-nav-wrapper">
                            <ul class="navbar-nav flex-fill w-100 sub-nav-list">
                                @can('articles.view')
                                <li class="nav-item w-100 {{ request()->routeIs('articles.*') ? 'active' : '' }}">
                                    <a class="nav-link sub-nav-link" href="{{ route('articles.index') }}">
                                        <span class="sub-item-content">
                                            <i class="fe fe-file-text sub-item-icon"></i>
                                            <span class="item-text">المقالات</span>
                                        </span>
                                    </a>
                                </li>
                                @endcan
                                @can('categories.view')
                                <li class="nav-item w-100 {{ request()->routeIs('categories.*') ? 'active' : '' }}">
                                    <a class="nav-link sub-nav-link" href="{{ route('categories.index') }}">
                                        <span class="sub-item-content">
                                            <i class="fe fe-tag sub-item-icon"></i>
                                            <span class="item-text">الفئات</span>
                                        </span>
                                    </a>
                                </li>
                                @endcan
                                @can('images.view')
                                <li class="nav-item w-100 {{ request()->routeIs('dashboard.images.*') ? 'active' : '' }}">
                                    <a class="nav-link sub-nav-link" href="{{ route('dashboard.images.index') }}">
                                        <span class="sub-item-content">
                                            <i class="fe fe-image sub-item-icon"></i>
                                            <span class="item-text">مكتبة الصور</span>
                                        </span>
                                    </a>
                                </li>
                                @endcan
                                @can('stories.view')
                                @if(Route::has('stories.index'))
                                <li class="nav-item w-100 {{ request()->routeIs('stories.*') ? 'active' : '' }}">
                                    <a class="nav-link sub-nav-link" href="{{ route('stories.index') }}">
                                        <span class="sub-item-content">
                                            <i class="fe fe-book-open sub-item-icon"></i>
                                            <span class="item-text">القصص</span>
                                        </span>
                                    </a>
                                </li>
                                @endif
                                @endcan
                                @can('site-content.view')
                                <li class="nav-item w-100 {{ request()->routeIs('dashboard.important-links.*') ? 'active' : '' }}">
                                    <a class="nav-link sub-nav-link" href="{{ route('dashboard.important-links.index') }}">
                                        <span class="sub-item-content">
                                            <i class="fe fe-link sub-item-icon"></i>
                                            <span class="item-text">الروابط المهمة</span>
                                        </span>
                                        @if ($pendingImportantLinksCount > 0)
                                            <span class="badge badge-danger rounded-pill">{{ $pendingImportantLinksCount }}</span>
                                        @endif
                                    </a>
                                </li>
                                @endcan
                                <li class="nav-item w-100 {{ request()->routeIs('dashboard.quran-competitions.*') ? 'active' : '' }}">
                                    <a class="nav-link sub-nav-link" href="{{ route('dashboard.quran-competitions.index') }}">
                                        <span class="sub-item-content">
                                            <i class="fe fe-star sub-item-icon"></i>
                                            <span class="item-text">مسابقة القرآن الكريم</span>
                                        </span>
                                    </a>
                                </li>
                                <li class="nav-item w-100 {{ request()->routeIs('dashboard.competitions.*') ? 'active' : '' }}">
                                    <a class="nav-link sub-nav-link" href="{{ route('dashboard.competitions.index') }}">
                                        <span class="sub-item-content">
                                            <i class="fe fe-zap sub-item-icon"></i>
                                            <span class="item-text">المسابقات</span>
                                        </span>
                                    </a>
                                </li>
                                <li class="nav-item w-100 {{ request()->routeIs('dashboard.quiz-competitions.*') ? 'active' : '' }}">
                                    <a class="nav-link sub-nav-link" href="{{ route('dashboard.quiz-competitions.index') }}">
                                        <span class="sub-item-content">
                                            <i class="fe fe-bar-chart-2 sub-item-icon"></i>
                                            <span class="item-text">استطلاعات الرأي</span>
                                        </span>
                                    </a>
                                </li>
                                <li class="nav-item w-100 {{ request()->routeIs('dashboard.sponsors.*') ? 'active' : '' }}">
                                    <a class="nav-link sub-nav-link" href="{{ route('dashboard.sponsors.index') }}">
                                        <span class="sub-item-content">
                                            <i class="fe fe-briefcase sub-item-icon"></i>
                                            <span class="item-text">رعاة المسابقات</span>
                                        </span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </li>
            </ul>
        @endif

        {{-- ==========================================
             المتجر والأدوات (dropdown)
        =========================================== --}}
        @can('products.view')
            @php $productsActive = request()->routeIs(['products.*', 'dashboard.products.*']); @endphp

            <ul class="navbar-nav flex-fill w-100 mb-1">
                <li class="nav-item w-100 {{ $productsActive ? 'active' : '' }}">
                    <a class="nav-link {{ $productsActive ? '' : 'collapsed' }}"
                       href="#collapseProducts"
                       data-toggle="collapse"
                       aria-expanded="{{ $productsActive ? 'true' : 'false' }}"
                       aria-controls="collapseProducts">
                        <span class="nav-link-content">
                            <i class="fe fe-shopping-bag item-icon"></i>
                            <span class="item-text">المتجر والأدوات</span>
                        </span>
                        <i class="fe fe-chevron-down nav-chevron"></i>
                    </a>
                    <div id="collapseProducts" class="collapse {{ $productsActive ? 'show' : '' }}">
                        <div class="sub-nav-wrapper">
                            <ul class="navbar-nav flex-fill w-100 sub-nav-list">
                                @if(Route::has('products.index'))
                                <li class="nav-item w-100 {{ request()->routeIs('products.*') && !request()->routeIs('products.categories.*') ? 'active' : '' }}">
                                    <a class="nav-link sub-nav-link" href="{{ route('products.index') }}">
                                        <span class="sub-item-content">
                                            <i class="fe fe-box sub-item-icon"></i>
                                            <span class="item-text">المنتجات</span>
                                        </span>
                                    </a>
                                </li>
                                @endif
                                @if(Route::has('products.categories.index'))
                                <li class="nav-item w-100 {{ request()->routeIs('products.categories.*') ? 'active' : '' }}">
                                    <a class="nav-link sub-nav-link" href="{{ route('products.categories.index') }}">
                                        <span class="sub-item-content">
                                            <i class="fe fe-tag sub-item-icon"></i>
                                            <span class="item-text">الفئات الرئيسية</span>
                                        </span>
                                    </a>
                                </li>
                                @endif
                            </ul>
                        </div>
                    </div>
                </li>
            </ul>
        @endcan

        {{-- ==========================================
             الصحة واللياقة (dropdown)
        =========================================== --}}
        @php
            $healthActive = request()->routeIs(['dashboard.rental-requests.*', 'dashboard.health-websites.*', 'dashboard.walking.*']);
            $pendingRentalRequestsCount = $pendingRentalRequestsCount ?? 0;
        @endphp

        <ul class="navbar-nav flex-fill w-100 mb-1">
            <li class="nav-item w-100 {{ $healthActive ? 'active' : '' }}">
                <a class="nav-link {{ $healthActive ? '' : 'collapsed' }}"
                   href="#collapseHealth"
                   data-toggle="collapse"
                   aria-expanded="{{ $healthActive ? 'true' : 'false' }}"
                   aria-controls="collapseHealth">
                    <span class="nav-link-content">
                        <i class="fe fe-activity item-icon"></i>
                        <span class="item-text">الصحة واللياقة</span>
                        @if ($pendingRentalRequestsCount > 0)
                            <span class="badge badge-danger rounded-pill">{{ $pendingRentalRequestsCount > 99 ? '99+' : $pendingRentalRequestsCount }}</span>
                        @endif
                    </span>
                    <i class="fe fe-chevron-down nav-chevron"></i>
                </a>
                <div id="collapseHealth" class="collapse {{ $healthActive ? 'show' : '' }}">
                    <div class="sub-nav-wrapper">
                        <ul class="navbar-nav flex-fill w-100 sub-nav-list">
                            @can('walking-program.view')
                            <li class="nav-item w-100 {{ request()->routeIs('dashboard.walking.index') || request()->routeIs('dashboard.walking.groups.*') ? 'active' : '' }}">
                                <a class="nav-link sub-nav-link" href="{{ route('dashboard.walking.index') }}">
                                    <span class="sub-item-content">
                                        <i class="fe fe-trending-up sub-item-icon"></i>
                                        <span class="item-text">برنامج المشي والتحديات</span>
                                    </span>
                                </a>
                            </li>
                            <li class="nav-item w-100 {{ request()->routeIs('dashboard.walking.treks.*') ? 'active' : '' }}">
                                <a class="nav-link sub-nav-link" href="{{ route('dashboard.walking.treks.index') }}">
                                    <span class="sub-item-content">
                                        <i class="fe fe-map sub-item-icon"></i>
                                        <span class="item-text">مسارات ورحلات المملكة 🇸🇦</span>
                                    </span>
                                </a>
                            </li>
                            @endcan
                            <li class="nav-item w-100 {{ request()->routeIs('dashboard.rental-requests.*') ? 'active' : '' }}">
                                <a class="nav-link sub-nav-link" href="{{ route('dashboard.rental-requests.index') }}">
                                    <span class="sub-item-content">
                                        <i class="fe fe-home sub-item-icon"></i>
                                        <span class="item-text">طلبات الاستعارة</span>
                                    </span>
                                    @if ($pendingRentalRequestsCount > 0)
                                        <span class="badge badge-danger rounded-pill">{{ $pendingRentalRequestsCount }}</span>
                                    @endif
                                </a>
                            </li>
                            <li class="nav-item w-100 {{ request()->routeIs('dashboard.health-websites.*') ? 'active' : '' }}">
                                <a class="nav-link sub-nav-link" href="{{ route('dashboard.health-websites.index') }}">
                                    <span class="sub-item-content">
                                        <i class="fe fe-heart sub-item-icon"></i>
                                        <span class="item-text">المواقع الصحية</span>
                                    </span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </li>
        </ul>

        {{-- ==========================================
             الإشعارات (dropdown)
        =========================================== --}}
        @can('notifications.view')
            @php
                $notifActive = request()->routeIs(['dashboard.notifications.*', 'dashboard.notification-groups.*', 'dashboard.backgrounds.*']);
                $pendingInvitationsCount = $pendingInvitationsCount ?? 0;
            @endphp

            <ul class="navbar-nav flex-fill w-100 mb-1">
                <li class="nav-item w-100 {{ $notifActive ? 'active' : '' }}">
                    <a class="nav-link {{ $notifActive ? '' : 'collapsed' }}"
                       href="#collapseNotifications"
                       data-toggle="collapse"
                       aria-expanded="{{ $notifActive ? 'true' : 'false' }}"
                       aria-controls="collapseNotifications">
                        <span class="nav-link-content">
                            <i class="fe fe-bell item-icon"></i>
                            <span class="item-text">الإشعارات</span>
                            @if ($pendingInvitationsCount > 0)
                                <span class="badge badge-warning text-dark font-weight-bold ml-2 px-2 rounded-pill">{{ $pendingInvitationsCount }}</span>
                            @endif
                        </span>
                        <i class="fe fe-chevron-down nav-chevron"></i>
                    </a>
                    <div id="collapseNotifications" class="collapse {{ $notifActive ? 'show' : '' }}">
                        <div class="sub-nav-wrapper">
                            <ul class="navbar-nav flex-fill w-100 sub-nav-list">
                                <li class="nav-item w-100 {{ request()->routeIs('dashboard.notifications.index') ? 'active' : '' }}">
                                    <a class="nav-link sub-nav-link" href="{{ route('dashboard.notifications.index') }}">
                                        <span class="sub-item-content">
                                            <i class="fe fe-home sub-item-icon"></i>
                                            <span class="item-text">الرئيسية</span>
                                        </span>
                                    </a>
                                </li>
                                @can('notifications.view')
                                <li class="nav-item w-100 {{ request()->routeIs('dashboard.notifications.pending') ? 'active' : '' }}">
                                    <a class="nav-link sub-nav-link" href="{{ route('dashboard.notifications.pending') }}">
                                        <span class="sub-item-content">
                                            <i class="fe fe-inbox sub-item-icon text-warning"></i>
                                            <span class="item-text">الدعوات المعلقة</span>
                                        </span>
                                        @if ($pendingInvitationsCount > 0)
                                            <span class="badge badge-warning text-dark font-weight-bold rounded-pill">{{ $pendingInvitationsCount }}</span>
                                        @endif
                                    </a>
                                </li>
                                @endcan
                                @can('notifications.send')
                                <li class="nav-item w-100 {{ request()->routeIs('dashboard.notifications.send') ? 'active' : '' }}">
                                    <a class="nav-link sub-nav-link" href="{{ route('dashboard.notifications.send') }}">
                                        <span class="sub-item-content">
                                            <i class="fe fe-send sub-item-icon"></i>
                                            <span class="item-text">إرسال دعوة واتساب</span>
                                        </span>
                                    </a>
                                </li>
                                @endcan
                                @can('notifications.manage-groups')
                                <li class="nav-item w-100 {{ request()->routeIs('dashboard.notification-groups.*') ? 'active' : '' }}">
                                    <a class="nav-link sub-nav-link" href="{{ route('dashboard.notification-groups.index') }}">
                                        <span class="sub-item-content">
                                            <i class="fe fe-users sub-item-icon"></i>
                                            <span class="item-text">المجموعات</span>
                                        </span>
                                    </a>
                                </li>
                                @endcan
                                @if(Route::has('dashboard.backgrounds.index'))
                                <li class="nav-item w-100 {{ request()->routeIs('dashboard.backgrounds.*') ? 'active' : '' }}">
                                    <a class="nav-link sub-nav-link" href="{{ route('dashboard.backgrounds.index') }}">
                                        <span class="sub-item-content">
                                            <i class="fe fe-image sub-item-icon"></i>
                                            <span class="item-text">خلفيات الدعوات</span>
                                        </span>
                                    </a>
                                </li>
                                @endif
                                
                                @if(Route::has('dashboard.notifications.logs'))
                                <li class="nav-item w-100 {{ request()->routeIs('dashboard.notifications.logs*') ? 'active' : '' }}">
                                    <a class="nav-link sub-nav-link" href="{{ route('dashboard.notifications.logs') }}">
                                        <span class="sub-item-content">
                                            <i class="fe fe-clock sub-item-icon"></i>
                                            <span class="item-text">سجل الإرسال</span>
                                        </span>
                                    </a>
                                </li>
                                @endif
                                @if(Route::has('dashboard.notifications.settings') && auth()->user()?->can('notifications.settings'))
                                <li class="nav-item w-100 {{ request()->routeIs('dashboard.notifications.settings*') ? 'active' : '' }}">
                                    <a class="nav-link sub-nav-link" href="{{ route('dashboard.notifications.settings') }}">
                                        <span class="sub-item-content">
                                            <i class="fe fe-settings sub-item-icon"></i>
                                            <span class="item-text">الإعدادات</span>
                                        </span>
                                    </a>
                                </li>
                                @endif
                            </ul>
                        </div>
                    </div>
                </li>
            </ul>
        @endcan

        {{-- ==========================================
             رسائل وبلاغات التواصل (Top-Level Inbox)
        =========================================== --}}
        @if (auth()->user()->can('site-content.view') || auth()->user()->can('dashboard.view'))
            @php
                $pendingContactCount = \App\Models\ContactMessage::where('status', 'pending')->count();
                $contactActive = request()->routeIs('dashboard.contact-messages.*');
            @endphp
            <ul class="navbar-nav flex-fill w-100 mb-1">
                <li class="nav-item w-100 {{ $contactActive ? 'active' : '' }}">
                    <a class="nav-link" href="{{ route('dashboard.contact-messages.index') }}">
                        <span class="nav-link-content">
                            <i class="fe fe-inbox item-icon text-warning"></i>
                            <span class="item-text font-weight-bold">رسائل وبلاغات التواصل</span>
                            @if ($pendingContactCount > 0)
                                <span class="badge badge-warning text-dark font-weight-bold ml-2 px-2 rounded-pill">
                                    {{ $pendingContactCount > 99 ? '99+' : $pendingContactCount }}
                                </span>
                            @endif
                        </span>
                    </a>
                </li>
            </ul>
        @endif

        {{-- ==========================================
             مراقبة المحادثات (Chat Moderation)
        =========================================== --}}
        @if (auth()->user()->can('chat.moderation.view'))
            @php
                $chatPendingReports = \App\Models\ChatReport::query()->pending()->count();
                $chatActive = request()->routeIs('dashboard.chat.*');
            @endphp
            <ul class="navbar-nav flex-fill w-100 mb-1">
                <li class="nav-item w-100 {{ $chatActive ? 'active' : '' }}">
                    <a class="nav-link" href="{{ route('dashboard.chat.overview') }}">
                        <span class="nav-link-content">
                            <i class="fe fe-message-square item-icon text-primary"></i>
                            <span class="item-text font-weight-bold">مراقبة المحادثات</span>
                            @if ($chatPendingReports > 0)
                                <span class="badge badge-danger text-white font-weight-bold ml-2 px-2 rounded-pill">
                                    {{ $chatPendingReports > 99 ? '99+' : $chatPendingReports }}
                                </span>
                            @endif
                        </span>
                    </a>
                </li>
            </ul>
        @endif

        {{-- ==========================================
             النظام والإعدادات (dropdown)
        =========================================== --}}
        @if (auth()->user()->can('padges.view') ||
                auth()->user()->can('roles.manage') ||
                auth()->user()->can('users.manage') ||
                auth()->user()->can('site-content.view') ||
                auth()->user()->can('ai.settings') ||
                auth()->user()->can('ai.usage.view'))

            @php
                $systemActive = request()->routeIs([
                    'padges.*', 'roles.*', 'users.*',
                    'dashboard.settings.*',
                    'dashboard.site-password-settings.*', 'dashboard.support.*',
                    'dashboard.ai.*',
                ]);
            @endphp

            <ul class="navbar-nav flex-fill w-100 mb-1">
                <li class="nav-item w-100 {{ $systemActive ? 'active' : '' }}">
                    <a class="nav-link {{ $systemActive ? '' : 'collapsed' }}"
                       href="#collapseSystem"
                       data-toggle="collapse"
                       aria-expanded="{{ $systemActive ? 'true' : 'false' }}"
                       aria-controls="collapseSystem">
                        <span class="nav-link-content">
                            <i class="fe fe-settings item-icon"></i>
                            <span class="item-text">النظام والإعدادات</span>
                        </span>
                        <i class="fe fe-chevron-down nav-chevron"></i>
                    </a>
                    <div id="collapseSystem" class="collapse {{ $systemActive ? 'show' : '' }}">
                        <div class="sub-nav-wrapper">
                            <ul class="navbar-nav flex-fill w-100 sub-nav-list">
                                @can('padges.view')
                                @if(Route::has('padges.index'))
                                <li class="nav-item w-100 {{ request()->routeIs('padges.*') ? 'active' : '' }}">
                                    <a class="nav-link sub-nav-link" href="{{ route('padges.index') }}">
                                        <span class="sub-item-content">
                                            <i class="fe fe-award sub-item-icon"></i>
                                            <span class="item-text">الشارات</span>
                                        </span>
                                    </a>
                                </li>
                                @endif
                                @endcan
                                @can('roles.manage')
                                <li class="nav-item w-100 {{ request()->routeIs('roles.*') ? 'active' : '' }}">
                                    <a class="nav-link sub-nav-link" href="{{ route('roles.index') }}">
                                        <span class="sub-item-content">
                                            <i class="fe fe-shield sub-item-icon"></i>
                                            <span class="item-text">الأدوار</span>
                                        </span>
                                    </a>
                                </li>
                                @endcan
                                @can('users.manage')
                                <li class="nav-item w-100 {{ request()->routeIs('users.*') ? 'active' : '' }}">
                                    <a class="nav-link sub-nav-link" href="{{ route('users.index') }}">
                                        <span class="sub-item-content">
                                            <i class="fe fe-user-check sub-item-icon"></i>
                                            <span class="item-text">المستخدمون</span>
                                        </span>
                                    </a>
                                </li>
                                @endcan
                                @can('site-content.view')
                                <li class="nav-item w-100 {{ request()->routeIs('dashboard.settings.*') ? 'active' : '' }}">
                                    <a class="nav-link sub-nav-link" href="{{ route('dashboard.settings.index') }}">
                                        <span class="sub-item-content">
                                            <i class="fe fe-sliders sub-item-icon text-primary"></i>
                                            <span class="item-text font-weight-bold">إعدادات الموقع والتطبيق</span>
                                        </span>
                                        <span class="badge badge-primary rounded-pill px-2 py-0">جديد</span>
                                    </a>
                                </li>
                                <li class="nav-item w-100 {{ request()->routeIs('dashboard.contact-messages.*') ? 'active' : '' }}">
                                    <a class="nav-link sub-nav-link" href="{{ route('dashboard.contact-messages.index') }}">
                                        <span class="sub-item-content">
                                            <i class="fe fe-inbox sub-item-icon text-info"></i>
                                            <span class="item-text">رسائل وبلاغات التواصل</span>
                                        </span>
                                        @php
                                            $pendingMsgCount = \App\Models\ContactMessage::where('status', 'pending')->count();
                                        @endphp
                                        @if($pendingMsgCount > 0)
                                            <span class="badge badge-warning rounded-pill px-2 py-0">{{ $pendingMsgCount }}</span>
                                        @endif
                                    </a>
                                </li>
                                <li class="nav-item w-100 {{ request()->routeIs('dashboard.support.*') ? 'active' : '' }}">
                                    <a class="nav-link sub-nav-link" href="{{ route('dashboard.support.index') }}">
                                        <span class="sub-item-content">
                                            <i class="fe fe-help-circle sub-item-icon"></i>
                                            <span class="item-text">الدعم الفني</span>
                                        </span>
                                    </a>
                                </li>
                                <li class="nav-item w-100 {{ request()->routeIs('dashboard.site-password-settings.*') ? 'active' : '' }}">
                                    <a class="nav-link sub-nav-link" href="{{ route('dashboard.site-password-settings.index') }}">
                                        <span class="sub-item-content">
                                            <i class="fe fe-lock sub-item-icon"></i>
                                            <span class="item-text">إعدادات حماية الموقع</span>
                                        </span>
                                    </a>
                                </li>
                                @endcan
                                @if(auth()->user()->can('ai.settings') || auth()->user()->can('ai.usage.view'))
                                @if(Route::has('dashboard.ai.index'))
                                <li class="nav-item w-100 {{ request()->routeIs('dashboard.ai.*') ? 'active' : '' }}">
                                    <a class="nav-link sub-nav-link" href="{{ route('dashboard.ai.index') }}">
                                        <span class="sub-item-content">
                                            <i class="fe fe-cpu sub-item-icon"></i>
                                            <span class="item-text">الذكاء الاصطناعي</span>
                                        </span>
                                    </a>
                                </li>
                                @endif
                                @endif
                            </ul>
                        </div>
                    </div>
                </li>
            </ul>
        @endif

        <div class="sidebar-divider"></div>

        {{-- ==========================================
             سجل الزيارات
        =========================================== --}}
        @can('visit-logs.view')
        <ul class="navbar-nav flex-fill w-100 mb-1">
            <li class="nav-item w-100 {{ request()->routeIs('dashboard.visit-logs.*') ? 'active' : '' }}">
                <a class="nav-link" href="{{ route('dashboard.visit-logs.index') }}">
                    <span class="nav-link-content">
                        <i class="fe fe-eye item-icon"></i>
                        <span class="item-text">سجل الزيارات</span>
                    </span>
                </a>
            </li>
        </ul>
        @endcan

        {{-- ==========================================
             سجلات النظام
        =========================================== --}}
        @if (auth()->user()->can('logs.view') || auth()->user()->can('system.logs.view') || auth()->user()->can('audit.view'))
            <ul class="navbar-nav flex-fill w-100 mb-1">
                <li class="nav-item w-100 {{ request()->routeIs('logs.*') ? 'active' : '' }}">
                    <a class="nav-link" href="{{ route('logs.index') }}">
                        <span class="nav-link-content">
                            <i class="fe fe-terminal item-icon"></i>
                            <span class="item-text">سجلات النظام</span>
                        </span>
                    </a>
                </li>
            </ul>
        @endif

        {{-- ==========================================
             رابط عرض الموقع
        =========================================== --}}
        <ul class="navbar-nav flex-fill w-100 mt-2 mb-4">
            <li class="nav-item w-100">
                <a class="nav-link" href="{{ Route::has('home') ? route('home') : url('/') }}" target="_blank" rel="noopener noreferrer">
                    <span class="nav-link-content">
                        <i class="fe fe-external-link item-icon text-muted"></i>
                        <span class="item-text">عرض الموقع</span>
                    </span>
                </a>
            </li>
        </ul>

    </nav>
</aside>