@php
    $groupedPermissions = $groupedPermissions ?? collect();
    $groupLabels = $groupLabels ?? [];
    $rolePermissions = $rolePermissions ?? [];
    $matrixId = $matrixId ?? 'perm-matrix';
    $readonly = $readonly ?? false;

    // Helper mapping for permission categories
    $categories = [
        'all' => 'جميع الصلاحيات',
        'family' => 'العائلة والأشخاص',
        'content' => 'المحتوى والإعلام',
        'competitions' => 'المسابقات والفعاليات',
        'system' => 'النظام والإدارة',
        'chat' => 'الشات والدردشة',
        'ai' => 'الذكاء الاصطناعي',
    ];

    $categoryGroups = [
        'family' => ['people', 'marriages', 'breastfeeding', 'friendships', 'locations', 'padges'],
        'content' => ['articles', 'categories', 'stories', 'images', 'site-content', 'slideshow', 'home-gallery', 'courses', 'programs', 'products', 'councils', 'walking-program', 'important-links'],
        'competitions' => ['competitions', 'quran-competitions', 'quiz-competitions', 'quiz-questions'],
        'system' => ['roles', 'users', 'notifications', 'logs', 'system', 'audit', 'visit-logs', 'dashboard', 'reports', 'support'],
        'chat' => ['chat'],
        'ai' => ['ai'],
    ];

    function getCategoryForGroup($group, $categoryGroups) {
        foreach ($categoryGroups as $cat => $groups) {
            if (in_array($group, $groups, true)) {
                return $cat;
            }
        }
        return 'other';
    }

    $totalPermissionsCount = $groupedPermissions->flatten()->count();
@endphp

<div class="perm-matrix-wrapper" id="{{ $matrixId }}-wrapper">
    {{-- Category Tabs & Toolbar --}}
    <div class="mb-4 space-y-3">
        {{-- Category Pills --}}
        <div class="flex items-center gap-1.5 overflow-x-auto pb-1 border-b border-slate-100 dark:border-slate-800" id="{{ $matrixId }}-tabs">
            <button type="button"
                    class="perm-cat-btn px-3 py-1.5 rounded-xl text-xs font-bold transition-all shrink-0 bg-emerald-600 text-white shadow-sm"
                    data-cat="all"
                    data-matrix="{{ $matrixId }}">
                <i class="fas fa-layer-group mr-1 text-[10px]"></i> جميع الصلاحيات ({{ $totalPermissionsCount }})
            </button>
            <button type="button"
                    class="perm-cat-btn px-3 py-1.5 rounded-xl text-xs font-bold transition-all shrink-0 bg-slate-100 dark:bg-slate-800 notif-title hover:bg-slate-200 dark:hover:bg-slate-700"
                    data-cat="family"
                    data-matrix="{{ $matrixId }}">
                <i class="fas fa-users mr-1 text-[10px]"></i> العائلة والأشخاص
            </button>
            <button type="button"
                    class="perm-cat-btn px-3 py-1.5 rounded-xl text-xs font-bold transition-all shrink-0 bg-slate-100 dark:bg-slate-800 notif-title hover:bg-slate-200 dark:hover:bg-slate-700"
                    data-cat="content"
                    data-matrix="{{ $matrixId }}">
                <i class="fas fa-newspaper mr-1 text-[10px]"></i> المحتوى والإعلام
            </button>
            <button type="button"
                    class="perm-cat-btn px-3 py-1.5 rounded-xl text-xs font-bold transition-all shrink-0 bg-slate-100 dark:bg-slate-800 notif-title hover:bg-slate-200 dark:hover:bg-slate-700"
                    data-cat="competitions"
                    data-matrix="{{ $matrixId }}">
                <i class="fas fa-trophy mr-1 text-[10px]"></i> المسابقات
            </button>
            <button type="button"
                    class="perm-cat-btn px-3 py-1.5 rounded-xl text-xs font-bold transition-all shrink-0 bg-slate-100 dark:bg-slate-800 notif-title hover:bg-slate-200 dark:hover:bg-slate-700"
                    data-cat="system"
                    data-matrix="{{ $matrixId }}">
                <i class="fas fa-cog mr-1 text-[10px]"></i> النظام والإدارة
            </button>
            <button type="button"
                    class="perm-cat-btn px-3 py-1.5 rounded-xl text-xs font-bold transition-all shrink-0 bg-slate-100 dark:bg-slate-800 notif-title hover:bg-slate-200 dark:hover:bg-slate-700"
                    data-cat="chat"
                    data-matrix="{{ $matrixId }}">
                <i class="fas fa-comments mr-1 text-[10px]"></i> الشات والدردشة
            </button>
            <button type="button"
                    class="perm-cat-btn px-3 py-1.5 rounded-xl text-xs font-bold transition-all shrink-0 bg-slate-100 dark:bg-slate-800 notif-title hover:bg-slate-200 dark:hover:bg-slate-700"
                    data-cat="ai"
                    data-matrix="{{ $matrixId }}">
                <i class="fas fa-robot mr-1 text-[10px]"></i> الذكاء الاصطناعي
            </button>
        </div>

        {{-- Search & Preset Toolbar --}}
        <div class="flex flex-col md:flex-row gap-2.5 items-md-center justify-between">
            <div class="relative flex-1">
                <input type="search"
                       id="{{ $matrixId }}-search"
                       class="dashboard-input py-2 text-xs pr-9"
                       placeholder="ابحث بالاسم أو المجموعة أو نوع الصلاحية (مثال: view, people, delete)..."
                       autocomplete="off"
                       @if($readonly) disabled @endif>
                <span class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-slate-400">
                    <i class="fas fa-search text-xs"></i>
                </span>
            </div>

            @unless($readonly)
                <div class="flex flex-wrap items-center gap-1.5 shrink-0">
                    <button type="button"
                            class="btn btn-sm btn-outline-primary rounded-xl text-[11px] font-bold px-3 py-1.5"
                            data-perm-select-all="{{ $matrixId }}"
                            title="تحديد كل الصلاحيات الظاهرة">
                        <i class="fas fa-check-double mr-1"></i> تحديد الكل
                    </button>
                    <button type="button"
                            class="btn btn-sm btn-outline-info rounded-xl text-[11px] font-bold px-3 py-1.5"
                            data-perm-preset="readonly"
                            data-matrix="{{ $matrixId }}"
                            title="تحديد صلاحيات المشاهدة فقط">
                        <i class="fas fa-eye mr-1"></i> المشاهدة فقط
                    </button>
                    <button type="button"
                            class="btn btn-sm btn-outline-secondary rounded-xl text-[11px] font-bold px-3 py-1.5"
                            data-perm-clear-all="{{ $matrixId }}"
                            title="إلغاء تحديد كل الصلاحيات">
                        <i class="fas fa-times mr-1"></i> إلغاء التحديد
                    </button>
                </div>
            @endunless
        </div>
    </div>

    {{-- Permission Matrix Groups Container --}}
    <div class="space-y-4" id="{{ $matrixId }}" data-permission-matrix="{{ $matrixId }}">
        @forelse($groupedPermissions as $group => $permissions)
            @php
                $category = getCategoryForGroup($group, $categoryGroups);
                $groupLabel = $groupLabels[$group] ?? $group;
            @endphp
            <div class="border border-slate-200/80 dark:border-slate-800 rounded-2xl overflow-hidden perm-group bg-white dark:bg-slate-900/50 shadow-sm transition-all"
                 data-group="{{ $group }}"
                 data-category="{{ $category }}"
                 data-group-label="{{ $groupLabel }}">
                
                {{-- Group Header --}}
                <div class="px-4 py-3 bg-slate-50/80 dark:bg-slate-850/80 border-b border-slate-200/80 dark:border-slate-800 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <div class="w-7 h-7 rounded-lg bg-emerald-500/10 text-emerald-600 flex items-center justify-center font-bold text-xs">
                            <i class="fas fa-folder text-[11px]"></i>
                        </div>
                        <div>
                            <span class="text-xs font-bold notif-title block leading-tight">{{ $groupLabel }}</span>
                            <span class="text-[10px] notif-muted font-mono">{{ $group }}</span>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 shrink-0">
                        {{-- Checked counter in group --}}
                        <span class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-slate-200/60 dark:bg-slate-800 notif-title" data-group-count="{{ $group }}">
                            0 / {{ $permissions->count() }}
                        </span>

                        @unless($readonly)
                            <label class="mb-0 flex items-center gap-1.5 text-xs font-bold notif-subtitle cursor-pointer select-none">
                                <input type="checkbox"
                                       class="w-4 h-4 accent-emerald-600 rounded cursor-pointer"
                                       data-group-toggle="{{ $matrixId }}"
                                       data-group-key="{{ $group }}"
                                       aria-label="تحديد كل صلاحيات {{ $groupLabel }}">
                                <span>المجموعة</span>
                            </label>
                        @endunless
                    </div>
                </div>

                {{-- Group Permissions Grid --}}
                <div class="p-3.5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5">
                    @foreach($permissions as $permission)
                        @php
                            $checked = in_array((int) $permission->id, $rolePermissions, true);
                            $inputId = $matrixId . '_perm_' . $permission->id;
                            
                            // Determine action color badge
                            $actionName = 'إجراء';
                            $actionColor = 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300';
                            
                            if (str_contains($permission->name, 'view') || str_contains($permission->name, 'show')) {
                                $actionName = 'عرض';
                                $actionColor = 'bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800/60';
                            } elseif (str_contains($permission->name, 'create') || str_contains($permission->name, 'store') || str_contains($permission->name, 'add') || str_contains($permission->name, 'upload')) {
                                $actionName = 'إضافة';
                                $actionColor = 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60';
                            } elseif (str_contains($permission->name, 'update') || str_contains($permission->name, 'edit') || str_contains($permission->name, 'toggle') || str_contains($permission->name, 'reorder')) {
                                $actionName = 'تعديل';
                                $actionColor = 'bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800/60';
                            } elseif (str_contains($permission->name, 'delete') || str_contains($permission->name, 'destroy') || str_contains($permission->name, 'remove')) {
                                $actionName = 'حذف';
                                $actionColor = 'bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800/60';
                            } elseif (str_contains($permission->name, 'manage') || str_contains($permission->name, 'settings')) {
                                $actionName = 'إدارة كاملة';
                                $actionColor = 'bg-purple-50 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800/60';
                            }

                            $permissionLabel = match ($permission->name) {
                                'chat.moderation.view' => 'الدخول إلى مراقبة المحادثات',
                                'chat.moderation.messages' => 'إدارة الرسائل والحذف والتثبيت',
                                'chat.moderation.participants' => 'إدارة المشاركين والتحذيرات',
                                'chat.moderation.reports' => 'استقبال ومعالجة البلاغات',
                                'chat.moderation.bans' => 'إدارة الحظر',
                                'chat.moderation.settings' => 'إعدادات الشات والكلمات الممنوعة',
                                default => null,
                            };
                        @endphp

                        <label for="{{ $inputId }}"
                               class="perm-item flex items-start gap-2.5 p-2.5 rounded-xl border transition-all cursor-pointer select-none mb-0
                                      {{ $checked ? 'bg-emerald-500/[0.04] border-emerald-500/80 dark:border-emerald-600 shadow-sm' : 'border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700' }}"
                               data-perm-name="{{ $permission->name }}"
                               data-perm-group="{{ $group }}"
                               data-perm-action="{{ $actionName }}">
                            
                            <input type="checkbox"
                                   class="mt-1 w-4 h-4 accent-emerald-600 rounded cursor-pointer shrink-0"
                                   name="permissions[]"
                                   value="{{ $permission->id }}"
                                   id="{{ $inputId }}"
                                   data-perm-checkbox="{{ $matrixId }}"
                                   data-group-key="{{ $group }}"
                                   @checked($checked)
                                   @if($readonly) disabled @endif>

                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between gap-1 mb-0.5">
                                    <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-md {{ $actionColor }}">
                                        {{ $actionName }}
                                    </span>
                                </div>
                                <span class="block text-[11px] font-bold notif-title font-mono leading-tight truncate" title="{{ $permission->name }}">
                                    {{ $permission->name }}
                                </span>
                                @if($permissionLabel)
                                    <span class="block text-[10px] notif-muted leading-tight mt-0.5">
                                        {{ $permissionLabel }}
                                    </span>
                                @endif
                            </div>
                        </label>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="text-center py-12 border border-dashed border-slate-200 dark:border-slate-800 rounded-2xl">
                <p class="notif-muted text-xs m-0">لا توجد صلاحيات مسجّلة في النظام حالياً.</p>
            </div>
        @endforelse

        <div id="{{ $matrixId }}-empty" class="text-center py-12 border border-dashed border-slate-200 dark:border-slate-800 rounded-2xl" style="display: none;">
            <p class="notif-muted text-xs m-0">لا توجد صلاحيات مطابقة للقسم المحدد.</p>
        </div>
    </div>

    {{-- Sticky Floating Summary Bar --}}
    <div class="mt-4 p-3 rounded-2xl bg-slate-900 text-white shadow-xl flex flex-col sm:flex-row items-center justify-between gap-3 sticky bottom-4 z-20 border border-slate-800">
        <div class="flex items-center gap-3 w-full sm:w-auto">
            <div class="w-8 h-8 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center font-black text-xs shrink-0">
                <i class="fas fa-shield-alt"></i>
            </div>
            <div>
                <div class="text-xs font-bold flex items-center gap-1.5">
                    <span>الصلاحيات المحددة:</span>
                    <span class="text-emerald-400 font-mono font-black text-sm" data-perm-count="{{ $matrixId }}">0</span>
                    <span class="text-slate-400 text-[11px]">من إجمالي {{ $totalPermissionsCount }}</span>
                </div>
                <div class="w-36 h-1.5 rounded-full bg-slate-800 overflow-hidden mt-1">
                    <div id="{{ $matrixId }}-progressbar" class="h-full bg-emerald-500 transition-all duration-300" style="width: 0%;"></div>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
            <button type="submit" class="btn btn-emerald btn-sm rounded-xl px-5 py-2 text-xs font-bold bg-emerald-500 hover:bg-emerald-600 text-white border-0 shadow-lg shadow-emerald-500/20 flex items-center gap-1.5 w-full sm:w-auto justify-center">
                <i class="fas fa-save"></i>
                <span>حفظ بيانات الدور</span>
            </button>
        </div>
    </div>
</div>

@once
@push('scripts')
<script>
(function () {
    var totalPerms = {{ $totalPermissionsCount }};

    function refreshMatrix(matrixId) {
        var root = document.querySelector('[data-permission-matrix="' + matrixId + '"]');
        if (!root) return;

        var boxes = root.querySelectorAll('[data-perm-checkbox="' + matrixId + '"]');
        var checked = 0;

        boxes.forEach(function (box) {
            if (box.checked) checked++;
            var label = box.closest('.perm-item');
            if (label) {
                label.classList.toggle('bg-emerald-500/[0.04]', box.checked);
                label.classList.toggle('border-emerald-500/80', box.checked);
                label.classList.toggle('dark:border-emerald-600', box.checked);
                label.classList.toggle('shadow-sm', box.checked);
            }
        });

        // Update Total Counter
        var counter = document.querySelectorAll('[data-perm-count="' + matrixId + '"]');
        counter.forEach(function (c) { c.textContent = checked; });

        // Update Progress bar
        var progress = document.getElementById(matrixId + '-progressbar');
        if (progress && totalPerms > 0) {
            var pct = Math.round((checked / totalPerms) * 100);
            progress.style.width = pct + '%';
        }

        // Update each group header counter and toggle
        root.querySelectorAll('.perm-group').forEach(function (groupEl) {
            var groupKey = groupEl.getAttribute('data-group');
            var groupCheckboxes = groupEl.querySelectorAll('[data-perm-checkbox="' + matrixId + '"]');
            var groupChecked = 0;
            groupCheckboxes.forEach(function (b) { if (b.checked) groupChecked++; });

            // Count badge
            var countEl = groupEl.querySelector('[data-group-count="' + groupKey + '"]');
            if (countEl) {
                countEl.textContent = groupChecked + ' / ' + groupCheckboxes.length;
                countEl.classList.toggle('bg-emerald-500/10', groupChecked > 0);
                countEl.classList.toggle('text-emerald-600', groupChecked > 0);
            }

            // Group Toggle
            var toggle = groupEl.querySelector('[data-group-toggle="' + matrixId + '"]');
            if (toggle) {
                var allChecked = groupCheckboxes.length > 0 && groupChecked === groupCheckboxes.length;
                toggle.checked = allChecked;
                toggle.indeterminate = groupChecked > 0 && !allChecked;
            }
        });
    }

    function filterMatrix(matrixId) {
        var root = document.querySelector('[data-permission-matrix="' + matrixId + '"]');
        if (!root) return;

        var searchInput = document.getElementById(matrixId + '-search');
        var query = (searchInput ? searchInput.value : '').trim().toLowerCase();

        var activeTab = document.querySelector('#' + matrixId + '-tabs .perm-cat-btn.bg-emerald-600');
        var selectedCat = activeTab ? activeTab.getAttribute('data-cat') : 'all';
        var visibleGroups = 0;

        root.querySelectorAll('.perm-group').forEach(function (groupEl) {
            var groupCat = groupEl.getAttribute('data-category');
            var groupLabel = (groupEl.getAttribute('data-group-label') || '').toLowerCase();
            var groupKey = (groupEl.getAttribute('data-group') || '').toLowerCase();
            var catMatch = (selectedCat === 'all' || selectedCat === groupCat || selectedCat === groupKey);

            if (!catMatch) {
                groupEl.style.display = 'none';
                return;
            }

            var groupHasVisibleItems = false;

            groupEl.querySelectorAll('.perm-item').forEach(function (item) {
                var permName = (item.getAttribute('data-perm-name') || '').toLowerCase();
                var permAction = (item.getAttribute('data-perm-action') || '').toLowerCase();
                var matchesQuery = !query || permName.indexOf(query) !== -1 || groupLabel.indexOf(query) !== -1 || groupKey.indexOf(query) !== -1 || permAction.indexOf(query) !== -1;

                item.style.display = matchesQuery ? '' : 'none';
                if (matchesQuery) groupHasVisibleItems = true;
            });

            groupEl.style.display = groupHasVisibleItems ? '' : 'none';
            if (groupHasVisibleItems) visibleGroups++;
        });

        var emptyState = document.getElementById(matrixId + '-empty');
        if (emptyState) {
            emptyState.style.display = visibleGroups === 0 ? '' : 'none';
        }

        return visibleGroups;
    }

    // Category Tabs Switching
    document.addEventListener('click', function (e) {
        var catBtn = e.target.closest('.perm-cat-btn');
        if (catBtn) {
            var matrixId = catBtn.getAttribute('data-matrix');
            var tabsContainer = document.getElementById(matrixId + '-tabs');
            if (tabsContainer) {
                tabsContainer.querySelectorAll('.perm-cat-btn').forEach(function (btn) {
                    btn.className = 'perm-cat-btn px-3 py-1.5 rounded-xl text-xs font-bold transition-all shrink-0 bg-slate-100 dark:bg-slate-800 notif-title hover:bg-slate-200 dark:hover:bg-slate-700';
                });
                catBtn.className = 'perm-cat-btn px-3 py-1.5 rounded-xl text-xs font-bold transition-all shrink-0 bg-emerald-600 text-white shadow-sm';
            }

            var searchInput = document.getElementById(matrixId + '-search');
            if (searchInput && searchInput.value.trim() !== '') {
                searchInput.value = '';
            }

            var visibleGroups = filterMatrix(matrixId);
            if (catBtn.getAttribute('data-cat') !== 'all' && visibleGroups > 0) {
                var root = document.querySelector('[data-permission-matrix="' + matrixId + '"]');
                if (root && typeof root.scrollIntoView === 'function') {
                    root.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }
        }
    });

    // Checkbox change listener
    document.addEventListener('change', function (e) {
        var t = e.target;
        if (t.matches('[data-perm-checkbox]')) {
            refreshMatrix(t.getAttribute('data-perm-checkbox'));
        }
        if (t.matches('[data-group-toggle]')) {
            var matrixId = t.getAttribute('data-group-toggle');
            var key = t.getAttribute('data-group-key');
            var root = document.querySelector('[data-permission-matrix="' + matrixId + '"]');
            if (!root) return;

            var groupEl = root.querySelector('.perm-group[data-group="' + key + '"]');
            if (groupEl) {
                groupEl.querySelectorAll('[data-perm-checkbox="' + matrixId + '"]').forEach(function (box) {
                    if (!box.disabled) box.checked = t.checked;
                });
                refreshMatrix(matrixId);
            }
        }
    });

    // Presets & Select All Actions
    document.addEventListener('click', function (e) {
        // Select All
        var selectAllBtn = e.target.closest('[data-perm-select-all]');
        if (selectAllBtn) {
            var id = selectAllBtn.getAttribute('data-perm-select-all');
            document.querySelectorAll('[data-perm-checkbox="' + id + '"]').forEach(function (box) {
                if (!box.disabled && box.closest('.perm-item').style.display !== 'none') {
                    box.checked = true;
                }
            });
            refreshMatrix(id);
        }

        // Clear All
        var clearAllBtn = e.target.closest('[data-perm-clear-all]');
        if (clearAllBtn) {
            var id2 = clearAllBtn.getAttribute('data-perm-clear-all');
            document.querySelectorAll('[data-perm-checkbox="' + id2 + '"]').forEach(function (box) {
                if (!box.disabled && box.closest('.perm-item').style.display !== 'none') {
                    box.checked = false;
                }
            });
            refreshMatrix(id2);
        }

        // Read-only preset
        var presetBtn = e.target.closest('[data-perm-preset="readonly"]');
        if (presetBtn) {
            var matrixId3 = presetBtn.getAttribute('data-matrix');
            document.querySelectorAll('[data-perm-checkbox="' + matrixId3 + '"]').forEach(function (box) {
                if (box.disabled) return;
                var name = box.closest('.perm-item').getAttribute('data-perm-name') || '';
                var isView = name.indexOf('view') !== -1 || name.indexOf('show') !== -1 || name.indexOf('index') !== -1;
                box.checked = isView;
            });
            refreshMatrix(matrixId3);
        }
    });

    // Search input
    document.addEventListener('input', function (e) {
        if (!e.target.id || !e.target.id.endsWith('-search')) return;
        var matrixId = e.target.id.replace(/-search$/, '');
        if (document.querySelector('[data-permission-matrix="' + matrixId + '"]')) {
            filterMatrix(matrixId);
        }
    });

    // Initialize on DOM ready
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-permission-matrix]').forEach(function (el) {
            refreshMatrix(el.getAttribute('data-permission-matrix'));
        });
    });
})();
</script>
@endpush
@endonce
