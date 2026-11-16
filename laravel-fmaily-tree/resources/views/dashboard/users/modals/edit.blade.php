@php
    $userRoles = $user->roles->pluck('name')->all();
    $editRoles = $assignableRoles
        ->merge($roles->whereIn('name', $userRoles))
        ->unique('id')
        ->values();
@endphp

<div class="modal fade" id="editUserModal{{ $user->id }}" tabindex="-1" role="dialog" aria-labelledby="editUserModalLabel{{ $user->id }}" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content border-0 rounded-2xl overflow-hidden shadow-2xl bg-white dark:bg-slate-900">
            <div class="modal-header border-b border-slate-100 dark:border-slate-800 bg-slate-50/80 dark:bg-slate-900/80 px-4 py-3">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-xl bg-blue-500/10 text-blue-600 flex items-center justify-center">
                        <i class="fas fa-user-edit text-sm" aria-hidden="true"></i>
                    </div>
                    <div>
                        <h5 class="modal-title text-sm font-bold notif-title m-0" id="editUserModalLabel{{ $user->id }}">
                            تعديل بيانات المستخدم: {{ $user->name }}
                        </h5>
                        <p class="text-[11px] notif-muted m-0">تحديث المعلومات الشخصية، الصلاحيات، وحالة الحساب</p>
                    </div>
                </div>
                <button type="button" class="close text-slate-400 hover:text-slate-600 dark:hover:text-slate-200" data-dismiss="modal" aria-label="إغلاق">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <form action="{{ route('users.update', $user) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body p-4 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5">
                        {{-- Name --}}
                        <div>
                            <label for="edit_name_{{ $user->id }}" class="text-xs font-bold notif-subtitle mb-1.5 flex items-center gap-1">
                                <span>الاسم الكامل</span>
                                <span class="text-rose-500">*</span>
                            </label>
                            <input type="text"
                                   class="dashboard-input text-xs"
                                   id="edit_name_{{ $user->id }}"
                                   name="name"
                                   value="{{ old('name', $user->name) }}"
                                   required>
                        </div>

                        {{-- Email --}}
                        <div>
                            <label for="edit_email_{{ $user->id }}" class="text-xs font-bold notif-subtitle mb-1.5 flex items-center gap-1">
                                <span>البريد الإلكتروني</span>
                                <span class="text-rose-500">*</span>
                            </label>
                            <input type="email"
                                   class="dashboard-input text-xs font-mono"
                                   id="edit_email_{{ $user->id }}"
                                   name="email"
                                   value="{{ old('email', $user->email) }}"
                                   dir="ltr"
                                   required>
                        </div>

                        {{-- WhatsApp Phone --}}
                        <div class="md:col-span-2">
                            <label for="edit_phone_{{ $user->id }}" class="text-xs font-bold notif-subtitle mb-1.5 flex items-center gap-1">
                                <i class="fab fa-whatsapp text-emerald-500"></i>
                                <span>رقم هاتف الواتساب</span>
                            </label>
                            <input type="text"
                                   class="dashboard-input text-xs font-mono"
                                   id="edit_phone_{{ $user->id }}"
                                   name="phone"
                                   value="{{ old('phone', $user->phone) }}"
                                   placeholder="9665XXXXXXXX"
                                   dir="ltr">
                        </div>

                        {{-- Password --}}
                        <div>
                            <label for="edit_password_{{ $user->id }}" class="text-xs font-bold notif-subtitle mb-1.5 block">
                                <span>كلمة مرور جديدة</span>
                                <span class="text-[10px] notif-muted font-normal mr-1">(اتركها فارغة للإبقاء على الحالية)</span>
                            </label>
                            <div class="relative">
                                <input type="password"
                                       class="dashboard-input pl-10 text-xs font-mono"
                                       id="edit_password_{{ $user->id }}"
                                       name="password"
                                       autocomplete="new-password">
                                <button type="button"
                                        class="absolute inset-y-0 left-0 flex items-center px-3 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                                        onclick="togglePasswordVisibility('edit_password_{{ $user->id }}', this)">
                                    <i class="far fa-eye text-xs"></i>
                                </button>
                            </div>
                        </div>

                        {{-- Password Confirmation --}}
                        <div>
                            <label for="edit_password_confirmation_{{ $user->id }}" class="text-xs font-bold notif-subtitle mb-1.5 block">
                                <span>تأكيد كلمة المرور الجديدة</span>
                            </label>
                            <div class="relative">
                                <input type="password"
                                       class="dashboard-input pl-10 text-xs font-mono"
                                       id="edit_password_confirmation_{{ $user->id }}"
                                       name="password_confirmation"
                                       autocomplete="new-password">
                                <button type="button"
                                        class="absolute inset-y-0 left-0 flex items-center px-3 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                                        onclick="togglePasswordVisibility('edit_password_confirmation_{{ $user->id }}', this)">
                                    <i class="far fa-eye text-xs"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Verified / Active Switch --}}
                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700">
                        <label class="flex items-center justify-between cursor-pointer mb-0">
                            <div>
                                <span class="text-xs font-bold notif-title block">تفعيل الحساب والبريد</span>
                                <span class="text-[11px] notif-muted block">الحسابات المفعّلة فقط يمكنها تسجيل الدخول للنظام</span>
                            </div>
                            <input type="checkbox"
                                   name="email_verified"
                                   value="1"
                                   class="w-4 h-4 accent-emerald-600 rounded cursor-pointer"
                                   @checked(old('email_verified', (bool) $user->email_verified_at))>
                        </label>
                    </div>

                    {{-- Roles --}}
                    <div class="pt-2 border-t border-slate-100 dark:border-slate-800">
                        <label class="text-xs font-bold notif-subtitle mb-2 block flex items-center justify-between">
                            <span class="flex items-center gap-1">
                                <i class="fas fa-shield-alt text-indigo-500"></i>
                                <span>الأدوار الممنوحة</span>
                                <span class="text-rose-500">*</span>
                            </span>
                        </label>
                        @include('dashboard.users.partials._roles-checkboxes', [
                            'assignableRoles' => $editRoles,
                            'selected' => old('roles', $userRoles),
                            'idPrefix' => 'edit_role_' . $user->id,
                        ])
                    </div>
                </div>

                <div class="modal-footer border-t border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 px-4 py-3">
                    <button type="button" class="btn btn-light btn-sm rounded-xl border px-4 text-xs font-bold" data-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary btn-sm rounded-xl px-5 text-xs font-bold border-0 shadow-md shadow-blue-500/20">
                        <i class="fas fa-save mr-1" aria-hidden="true"></i> حفظ التغييرات
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
