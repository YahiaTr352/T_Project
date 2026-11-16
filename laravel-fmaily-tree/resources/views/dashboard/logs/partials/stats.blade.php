<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="dashboard-notif-card h-100 flex flex-col justify-between">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[11px] font-bold text-blue-700 dark:text-blue-400 uppercase tracking-wider">إجمالي السجلات</span>
                <div class="w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-950 text-blue-700 dark:text-blue-300 flex items-center justify-center text-sm">
                    <i class="fas fa-database"></i>
                </div>
            </div>
            <h2 class="text-3xl font-black notif-title m-0">{{ number_format($stats['total']) }}</h2>
            <span class="text-[10px] notif-muted font-bold mt-1 block">عملية مطابقة للفلاتر</span>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="dashboard-notif-card h-100 flex flex-col justify-between">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[11px] font-bold text-emerald-700 dark:text-emerald-400 uppercase tracking-wider">عمليات الإنشاء</span>
                <div class="w-8 h-8 rounded-lg bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 flex items-center justify-center text-sm">
                    <i class="fas fa-plus"></i>
                </div>
            </div>
            <h2 class="text-3xl font-black notif-title m-0 text-emerald-600 dark:text-emerald-400">{{ number_format($stats['created']) }}</h2>
            <span class="text-[10px] notif-muted font-bold mt-1 block">سجل جديد</span>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="dashboard-notif-card h-100 flex flex-col justify-between">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[11px] font-bold text-amber-700 dark:text-amber-400 uppercase tracking-wider">عمليات التحديث</span>
                <div class="w-8 h-8 rounded-lg bg-amber-100 dark:bg-amber-950 text-amber-700 dark:text-amber-300 flex items-center justify-center text-sm">
                    <i class="fas fa-pen-to-square"></i>
                </div>
            </div>
            <h2 class="text-3xl font-black notif-title m-0 text-amber-600 dark:text-amber-400">{{ number_format($stats['updated']) }}</h2>
            <span class="text-[10px] notif-muted font-bold mt-1 block">تعديل بيانات</span>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="dashboard-notif-card h-100 flex flex-col justify-between">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[11px] font-bold text-rose-700 dark:text-rose-400 uppercase tracking-wider">عمليات الحذف</span>
                <div class="w-8 h-8 rounded-lg bg-rose-100 dark:bg-rose-950 text-rose-700 dark:text-rose-300 flex items-center justify-center text-sm">
                    <i class="fas fa-trash-can"></i>
                </div>
            </div>
            <h2 class="text-3xl font-black notif-title m-0 text-rose-600 dark:text-rose-400">{{ number_format($stats['deleted']) }}</h2>
            <span class="text-[10px] notif-muted font-bold mt-1 block">عنصر محذوف</span>
        </div>
    </div>
</div>
