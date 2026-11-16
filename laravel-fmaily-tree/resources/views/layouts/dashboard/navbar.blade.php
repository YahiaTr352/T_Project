<nav class="topnav navbar navbar-light">
    @php
        $user = auth()->user();

        // 1. Resolve avatar with intelligent fallback chain:
        //    a. User uploaded avatar (Storage public disk)
        //    b. Direct URL avatar
        //    c. Linked Person avatar from family tree
        //    d. Default asset avatar
        $avatarUrl = asset('admin-assets/assets/avatars/default.png');
        if ($user) {
            if (!empty($user->avatar)) {
                $avatarUrl = filter_var($user->avatar, FILTER_VALIDATE_URL)
                    ? $user->avatar
                    : asset('storage/' . $user->avatar);
            } elseif (!empty($user->avatar_url)) {
                $avatarUrl = $user->avatar_url;
            } elseif ($user->relationLoaded('person') && $user->person && !empty($user->person->avatar)) {
                $avatarUrl = filter_var($user->person->avatar, FILTER_VALIDATE_URL)
                    ? $user->person->avatar
                    : asset('storage/' . $user->person->avatar);
            }
        }

        // 2. Unread notifications count
        $unreadNotificationsCount = 0;
        try {
            if ($user && method_exists($user, 'unreadNotifications')) {
                $unreadNotificationsCount = $user->unreadNotifications()->count();
            }
        } catch (\Throwable $e) {
            $unreadNotificationsCount = 0;
        }

        // 3. User Role Detection & Arabic Translation
        $userRoleName = $user ? ($user->roles->first()?->name ?? 'user') : 'user';
        $userRoleLabel = 'عضو';
        if (class_exists(\App\Support\TranslationHelper::class)) {
            $userRoleLabel = \App\Support\TranslationHelper::userRole($userRoleName);
        } else {
            $userRoleLabel = match($userRoleName) {
                'super_admin' => 'مدير عام النظام',
                'admin'       => 'مدير النظام',
                'editor'      => 'محرر ومراجع',
                'viewer'      => 'مستعرض',
                default       => 'عضو العائلة',
            };
        }

        // 4. Linked Person in the Family Tree
        $linkedPerson = null;
        try {
            if ($user && !empty($user->person_id)) {
                $linkedPerson = $user->person ?? \App\Models\Person::find($user->person_id);
            }
        } catch (\Throwable $e) {
            $linkedPerson = null;
        }

        // 5. Public tree / website target URL
        $treeUrl = safe_route('sila');
        if ($treeUrl === '#') {
            $treeUrl = safe_route('home');
        }
    @endphp

    {{-- Sidebar Toggle Button --}}
    <div class="d-flex align-items-center">
        <button type="button" class="topnav-collapse-btn collapseSidebar" id="sidebarCollapseBtn" aria-label="تبديل القائمة الجانبية" title="تبديل القائمة الجانبية">
            <i class="fe fe-menu fe-18"></i>
        </button>
    </div>

    {{-- Topnav Action Controls & User Capsule --}}
    <ul class="nav">
        {{-- Quick link to interactive tree --}}
        <li class="nav-item">
            <a class="nav-link topnav-icon-btn" href="{{ $treeUrl }}" target="_blank" title="استعراض شجرة العائلة (صلة)">
                <i class="fe fe-share-2"></i>
            </a>
        </li>

        {{-- Dark / Light Mode Switcher --}}
        <li class="nav-item">
            <a class="nav-link topnav-icon-btn" href="#" id="modeSwitcher" data-mode="dark" title="تبديل المظهر (ليلي / نهاري)">
                <i class="fe fe-sun mode-icon-sun"></i>
                <i class="fe fe-moon mode-icon-moon"></i>
            </a>
        </li>

        {{-- Quick Shortcuts Modal Trigger --}}
        <li class="nav-item">
            <a class="nav-link topnav-icon-btn" href="./#" data-toggle="modal" data-target=".modal-shortcut" title="الاختصارات السريعة">
                <i class="fe fe-grid"></i>
            </a>
        </li>

        {{-- Notifications Modal Trigger --}}
        <li class="nav-item nav-notif">
            <a class="nav-link topnav-icon-btn position-relative" href="#" data-toggle="modal" data-target=".modal-notif" title="الإشعارات والتنبيهات">
                <i class="fe fe-bell"></i>
                @if($unreadNotificationsCount > 0)
                    <span class="topnav-notif-badge">
                        {{ $unreadNotificationsCount > 99 ? '99+' : $unreadNotificationsCount }}
                    </span>
                @endif
            </a>
        </li>

        {{-- Executive User Profile Pill & Dropdown --}}
        <li class="nav-item dropdown header-user-item">
            <a class="nav-link dropdown-toggle header-user-trigger" href="#" id="navbarDropdownMenuLink" role="button"
                data-toggle="dropdown" data-display="static" aria-haspopup="true" aria-expanded="false">
                
                {{-- User Avatar --}}
                <div class="header-avatar-container">
                    <img src="{{ $avatarUrl }}" alt="{{ $user->name ?? 'المستخدم' }}" class="header-avatar-img">
                </div>

                {{-- User Name & Role Tag (Desktop) - Aligned to the right --}}
                <div class="header-user-meta d-none d-md-flex align-items-center">
                    <span class="header-user-display-name text-truncate">
                        {{ $user->name ?? 'مدير النظام' }}
                    </span>
                    <span class="header-user-role-tag">
                        {{ $userRoleLabel }}
                    </span>
                </div>

                {{-- Explicit Inline Chevron (Guaranteed non-wrapping across all pages) --}}
                <span class="header-chevron-container">
                    <i class="fe fe-chevron-down header-user-chevron"></i>
                </span>
            </a>

            {{-- Clean, Handcrafted User Dropdown Menu --}}
            <div class="dropdown-menu dropdown-menu-left user-dropdown-menu border-0" aria-labelledby="navbarDropdownMenuLink">
                
                {{-- 1. User Identity Header (Role strictly on the right with the name) --}}
                <div class="user-dropdown-header">
                    <div class="user-dropdown-avatar-wrap">
                        <img src="{{ $avatarUrl }}" alt="{{ $user->name ?? 'المستخدم' }}" class="user-dropdown-avatar">
                    </div>
                    <div class="user-dropdown-meta">
                        <div class="user-dropdown-name text-truncate" title="{{ $user->name ?? 'مدير النظام' }}">
                            {{ $user->name ?? 'مدير النظام' }}
                        </div>
                        <div class="user-dropdown-sub text-truncate">
                            <span class="user-dropdown-role-tag">{{ $userRoleLabel }}</span>
                            @if(!empty($user->email))
                                <span class="user-dropdown-dot">•</span>
                                <span class="user-dropdown-email" title="{{ $user->email }}">{{ $user->email }}</span>
                            @endif
                        </div>
                        @if($linkedPerson)
                            <div class="user-dropdown-tree-link text-truncate" title="مرتبط بشخصية في الشجرة">
                                <i class="fe fe-user-check"></i>
                                <span>مرتبط بالشجرة: <strong>{{ $linkedPerson->name }}</strong></span>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="user-dropdown-divider"></div>

                {{-- 2. Clean, Native-Style Menu Actions --}}
                <div class="user-dropdown-nav">
                    @if(\Illuminate\Support\Facades\Route::has('profile.edit'))
                        <a class="user-dropdown-link" href="{{ route('profile.edit') }}">
                            <i class="fe fe-user"></i>
                            <span>الملف الشخصي</span>
                        </a>

                        <a class="user-dropdown-link" href="{{ route('profile.edit') }}#change-password">
                            <i class="fe fe-lock"></i>
                            <span>كلمة المرور والأمان</span>
                        </a>
                    @elseif(\Illuminate\Support\Facades\Route::has('my-profile.show'))
                        <a class="user-dropdown-link" href="{{ route('my-profile.show') }}">
                            <i class="fe fe-user"></i>
                            <span>الملف الشخصي</span>
                        </a>
                    @endif

                    @if($treeUrl !== '#')
                        <a class="user-dropdown-link" href="{{ $treeUrl }}" target="_blank">
                            <i class="fe fe-share-2"></i>
                            <span>شجرة العائلة (صلة)</span>
                            <i class="fe fe-external-link user-dropdown-ext-icon"></i>
                        </a>
                    @endif
                </div>

                <div class="user-dropdown-divider"></div>

                {{-- 3. Logout Item --}}
                <div class="user-dropdown-nav">
                    <a class="user-dropdown-link user-dropdown-link-danger" href="#" data-toggle="modal" data-target="#logoutModal">
                        <i class="fe fe-log-out"></i>
                        <span>تسجيل الخروج</span>
                    </a>
                </div>
            </div>
        </li>
    </ul>
</nav>

{{-- Clean, Handcrafted Logout Modal --}}
<div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="logoutModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 380px;">
        <div class="modal-content clean-logout-modal-content border-0 shadow-lg">
            <div class="modal-body p-4 text-center">
                <div class="clean-logout-icon mx-auto mb-3">
                    <i class="fe fe-log-out text-danger fe-20"></i>
                </div>
                <h5 class="font-weight-bold mb-2 clean-logout-title" id="logoutModalLabel">تسجيل الخروج</h5>
                <p class="text-muted mb-4 small" style="line-height: 1.6;">
                    هل أنت متأكد من رغبتك في تسجيل الخروج من لوحة التحكم؟
                </p>
                <div class="d-flex align-items-center justify-content-center gap-2">
                    <button type="button" class="btn btn-secondary px-3 py-2 font-weight-bold" data-dismiss="modal" style="border-radius: 10px; font-size: 0.85rem;">
                        إلغاء
                    </button>
                    <a class="btn btn-danger px-3 py-2 font-weight-bold shadow-sm" href="{{ route('logout') }}"
                        onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
                        style="border-radius: 10px; font-size: 0.85rem;">
                        تأكيد الخروج
                    </a>
                </div>
                <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                    @csrf
                </form>
            </div>
        </div>
    </div>
</div>
