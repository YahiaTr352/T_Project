<div class="modal fade" id="deleteUserModal{{ $user->id }}" tabindex="-1" role="dialog" aria-labelledby="deleteUserModalLabel{{ $user->id }}" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 rounded-2xl overflow-hidden shadow-2xl bg-white dark:bg-slate-900">
            <div class="modal-header border-b border-rose-100 dark:border-rose-950/60 bg-rose-50/80 dark:bg-rose-950/30 px-4 py-3">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-xl bg-rose-500/10 text-rose-600 flex items-center justify-center">
                        <i class="fas fa-exclamation-triangle text-sm" aria-hidden="true"></i>
                    </div>
                    <h5 class="modal-title text-sm font-bold text-rose-800 dark:text-rose-300 m-0" id="deleteUserModalLabel{{ $user->id }}">
                        تأكيد حذف المستخدم
                    </h5>
                </div>
                <button type="button" class="close text-rose-400 hover:text-rose-600 dark:hover:text-rose-200" data-dismiss="modal" aria-label="إغلاق">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body p-4 space-y-3">
                <p class="text-xs notif-subtitle mb-0 leading-relaxed">
                    هل أنت متأكد تماماً من رغبتك في حذف حساب المستخدم <strong class="text-rose-600 dark:text-rose-400">«{{ $user->name }}»</strong>؟
                </p>
                <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 text-[11px] notif-muted space-y-1">
                    <div class="flex items-center gap-1.5 text-amber-600 dark:text-amber-400 font-bold">
                        <i class="fas fa-shield-alt"></i>
                        <span>تنبيه أمني:</span>
                    </div>
                    <p class="m-0">سيتم إلغاء كافة صلاحيات الدخول وإزالة ارتباطات المستخدم بالنظام نهائياً ولا يمكن التراجع عن هذا الإجراء.</p>
                </div>
            </div>

            <div class="modal-footer border-t border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 px-4 py-3 flex justify-end gap-2">
                <button type="button" class="btn btn-light btn-sm rounded-xl border px-4 text-xs font-bold" data-dismiss="modal">إلغاء</button>
                <form action="{{ route('users.destroy', $user) }}" method="POST" class="m-0">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-sm rounded-xl px-4 text-xs font-bold border-0 shadow-md shadow-rose-500/20">
                        <i class="fas fa-trash-alt mr-1" aria-hidden="true"></i> حذف المستخدم نهائياً
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
