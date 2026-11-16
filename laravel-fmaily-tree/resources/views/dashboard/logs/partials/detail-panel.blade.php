<div class="logs-detail-panel p-4">
    @if($row['has_diff'])
        <h6 class="font-bold notif-title text-xs mb-3 flex items-center gap-1.5">
            <i class="fas fa-code-compare text-blue-600 dark:text-blue-400" aria-hidden="true"></i>
            التغييرات
        </h6>
        <div class="logs-diff-wrap mb-4">
            <table class="logs-diff-table">
                <thead>
                    <tr>
                        <th>الحقل</th>
                        <th>قبل</th>
                        <th>بعد</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($row['diff_rows'] as $diff)
                        <tr>
                            <td class="font-bold notif-title">{{ $diff['label'] }}</td>
                            <td class="text-rose-700 dark:text-rose-300 font-mono">{{ $diff['old'] }}</td>
                            <td class="text-emerald-700 dark:text-emerald-400 font-mono">{{ $diff['new'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="text-xs notif-muted mb-4">لا توجد تغييرات مسجّلة لهذا الحدث.</p>
    @endif

    <h6 class="font-bold notif-title text-xs mb-3 flex items-center gap-1.5">
        <i class="fas fa-network-wired text-purple-600 dark:text-purple-400" aria-hidden="true"></i>
        بيانات الجلسة
    </h6>
    <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
        <div class="md:col-span-5">
            <div class="p-3 bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl h-100">
                <strong class="text-xs font-bold notif-title block mb-2">
                    <i class="fas fa-map-marker-alt text-rose-500 ml-1"></i> عنوان IP والموقع
                </strong>
                <div class="font-mono text-xs text-blue-600 dark:text-blue-400 font-bold mb-2">
                    {{ $row['meta']['ip'] ?: 'غير متوفر' }}
                </div>
                @if(!empty($row['meta']['geo']))
                    <ul class="list-unstyled text-xs space-y-1 notif-subtitle font-medium m-0 pt-2 border-t border-slate-100 dark:border-slate-800">
                        <li>الدولة: <strong class="notif-title">{{ $row['meta']['geo']['country'] ?? 'غير معروف' }}</strong></li>
                        <li>المدينة: <strong class="notif-title">{{ $row['meta']['geo']['city'] ?? 'غير معروف' }}</strong></li>
                        <li>مزود الخدمة: <strong class="notif-title">{{ $row['meta']['geo']['isp'] ?? 'غير معروف' }}</strong></li>
                    </ul>
                @endif
            </div>
        </div>
        <div class="md:col-span-7">
            <div class="p-3 bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl h-100">
                <strong class="text-xs font-bold notif-title block mb-2">
                    <i class="fas fa-desktop text-blue-500 ml-1"></i> بيانات المتصفح
                </strong>
                <p class="font-mono text-[11px] notif-muted break-all m-0 bg-slate-50 dark:bg-slate-900 p-2 rounded-lg border border-slate-200 dark:border-slate-800">
                    {{ $row['meta']['user_agent'] ?: 'غير متوفر' }}
                </p>
                @if($row['meta']['request_id'])
                    <p class="text-[11px] notif-subtitle mt-2 mb-0">
                        <strong class="notif-title">Request ID:</strong>
                        <span class="font-mono">{{ $row['meta']['request_id'] }}</span>
                    </p>
                @endif
                @if($row['description'])
                    <p class="text-[11px] notif-subtitle mt-2 mb-0">
                        <strong class="notif-title">الوصف:</strong> {{ $row['description'] }}
                    </p>
                @endif
            </div>
        </div>
    </div>
</div>
