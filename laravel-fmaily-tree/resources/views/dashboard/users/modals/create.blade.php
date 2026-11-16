<div class="modal fade" id="createUserModal" tabindex="-1" role="dialog" aria-labelledby="createUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content border-0 rounded-2xl overflow-hidden shadow-2xl bg-white dark:bg-slate-900">
            <div class="modal-header border-b border-slate-100 dark:border-slate-800 bg-slate-50/80 dark:bg-slate-900/80 px-4 py-3">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center">
                        <i class="fas fa-user-plus text-sm" aria-hidden="true"></i>
                    </div>
                    <div>
                        <h5 class="modal-title text-sm font-bold notif-title m-0" id="createUserModalLabel">إضافة مستخدم جديد</h5>
                        <p class="text-[11px] notif-muted m-0">أنشئ حساباً جديداً وحدّد الصلاحيات والأدوار المناسبة</p>
                    </div>
                </div>
                <button type="button" class="close text-slate-400 hover:text-slate-600 dark:hover:text-slate-200" data-dismiss="modal" aria-label="إغلاق">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <form action="{{ route('users.store') }}" method="POST" id="createUserForm">
                @csrf
                <div class="modal-body p-4 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5">
                        {{-- Name --}}
                        <div>
                            <label for="create_name" class="text-xs font-bold notif-subtitle mb-1.5 flex items-center gap-1">
                                <span>الاسم الكامل</span>
                                <span class="text-rose-500">*</span>
                            </label>
                            <div class="relative">
                                <span class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-slate-400">
                                    <i class="fas fa-user text-xs"></i>
                                </span>
                                <input type="text"
                                       class="dashboard-input pr-9 text-xs"
                                       id="create_name"
                                       name="name"
                                       value="{{ old('name') }}"
                                       placeholder="مثال: محمد عبدالله السريّع"
                                       required>
                            </div>
                        </div>

                        {{-- Email --}}
                        <div>
                            <label for="create_email" class="text-xs font-bold notif-subtitle mb-1.5 flex items-center gap-1">
                                <span>البريد الإلكتروني</span>
                                <span class="text-rose-500">*</span>
                            </label>
                            <div class="relative">
                                <span class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-slate-400">
                                    <i class="fas fa-envelope text-xs"></i>
                                </span>
                                <input type="email"
                                       class="dashboard-input pr-9 text-xs font-mono"
                                       id="create_email"
                                       name="email"
                                       value="{{ old('email') }}"
                                       placeholder="user@alsorayia.com"
                                       dir="ltr"
                                       required>
                            </div>
                        </div>

                        {{-- Phone (WhatsApp) --}}
                        <div>
                            <label for="create_phone" class="text-xs font-bold notif-subtitle mb-1.5 flex items-center gap-1">
                                <i class="fab fa-whatsapp text-emerald-500"></i>
                                <span>رقم هاتف الواتساب</span>
                            </label>
                            <div class="relative">
                                <span class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-emerald-500">
                                    <i class="fas fa-phone text-xs"></i>
                                </span>
                                <input type="text"
                                       class="dashboard-input pr-9 text-xs font-mono"
                                       id="create_phone"
                                       name="phone"
                                       value="{{ old('phone') }}"
                                       placeholder="9665XXXXXXXX"
                                       dir="ltr">
                            </div>
                            <p class="text-[10px] notif-muted mt-1 mb-0">للتنبيهات والدعوات الرسمية (بالصيغة الدولية 966...)</p>
                        </div>

                        {{-- Quick Password generator helper --}}
                        <div class="flex items-end mb-1">
                            <button type="button"
                                    id="generateCreatePasswordBtn"
                                    class="btn btn-sm btn-outline-secondary rounded-xl text-[11px] font-bold w-full py-2 border-dashed">
                                <i class="fas fa-key mr-1 text-amber-500"></i> توليد كلمة مرور عشوائية قوية
                            </button>
                        </div>

                        {{-- Password --}}
                        <div>
                            <label for="create_password" class="text-xs font-bold notif-subtitle mb-1.5 flex items-center gap-1">
                                <span>كلمة المرور</span>
                                <span class="text-rose-500">*</span>
                            </label>
                            <div class="relative">
                                <input type="password"
                                       class="dashboard-input pl-10 text-xs font-mono"
                                       id="create_password"
                                       name="password"
                                       required
                                       autocomplete="new-password">
                                <button type="button"
                                        class="absolute inset-y-0 left-0 flex items-center px-3 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                                        onclick="togglePasswordVisibility('create_password', this)">
                                    <i class="far fa-eye text-xs"></i>
                                </button>
                            </div>
                        </div>

                        {{-- Password Confirmation --}}
                        <div>
                            <label for="create_password_confirmation" class="text-xs font-bold notif-subtitle mb-1.5 flex items-center gap-1">
                                <span>تأكيد كلمة المرور</span>
                                <span class="text-rose-500">*</span>
                            </label>
                            <div class="relative">
                                <input type="password"
                                       class="dashboard-input pl-10 text-xs font-mono"
                                       id="create_password_confirmation"
                                       name="password_confirmation"
                                       required
                                       autocomplete="new-password">
                                <button type="button"
                                        class="absolute inset-y-0 left-0 flex items-center px-3 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                                        onclick="togglePasswordVisibility('create_password_confirmation', this)">
                                    <i class="far fa-eye text-xs"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Roles Checkboxes --}}
                    <div class="pt-2 border-t border-slate-100 dark:border-slate-800">
                        <label class="text-xs font-bold notif-subtitle mb-2 block flex items-center justify-between">
                            <span class="flex items-center gap-1">
                                <i class="fas fa-shield-alt text-indigo-500"></i>
                                <span>الأدوار الممنوحة للمستخدم</span>
                                <span class="text-rose-500">*</span>
                            </span>
                            <span class="text-[10px] notif-muted font-normal">يمكن اختيار أكثر من دور</span>
                        </label>
                        @include('dashboard.users.partials._roles-checkboxes', [
                            'assignableRoles' => $assignableRoles,
                            'selected' => old('roles', []),
                            'idPrefix' => 'create_role',
                        ])
                    </div>
                </div>

                <div class="modal-footer border-t border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 px-4 py-3">
                    <button type="button" class="btn btn-light btn-sm rounded-xl border px-4 text-xs font-bold" data-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary btn-sm rounded-xl px-5 text-xs font-bold border-0 shadow-md shadow-emerald-500/20">
                        <i class="fas fa-check mr-1" aria-hidden="true"></i> حفظ وإنشاء المستخدم
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
