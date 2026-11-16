<div class="space-y-3">
    @foreach($rows as $row)
        @php
            $detailId = 'log-mobile-' . $row['tab'] . '-' . $row['id'];
        @endphp
        <article class="logs-mobile-card">
            <div class="flex items-start justify-between gap-2 mb-2">
                <div>
                    <div class="font-bold notif-title text-sm font-mono">{{ $row['time'] }}</div>
                    <div class="text-[10px] notif-muted">{{ $row['time_human'] }}</div>
                </div>
                @include('dashboard.logs.partials._event-badge-inline', ['row' => $row])
            </div>

            <div class="mb-2">
                @if($row['actor_name'])
                    <div class="text-xs font-bold notif-title">
                        <i class="fas fa-user-circle text-slate-400 ml-1"></i>{{ $row['actor_name'] }}
                    </div>
                @else
                    <div class="text-xs notif-muted">نظام تلقائي</div>
                @endif
                <div class="text-[11px] notif-subtitle mt-1">{{ $row['subject_label'] }}</div>
            </div>

            <p class="text-xs notif-subtitle m-0 mb-3 leading-relaxed">{{ $row['summary'] }}</p>

            <button class="btn btn-sm btn-outline-primary btn-block rounded-lg text-[11px] font-bold"
                    type="button"
                    data-toggle="collapse"
                    data-target="#{{ $detailId }}"
                    aria-expanded="false"
                    aria-controls="{{ $detailId }}">
                <i class="fas fa-chevron-down ml-1"></i> عرض التفاصيل
            </button>

            <div id="{{ $detailId }}" class="collapse mt-3">
                @include('dashboard.logs.partials.detail-panel', ['row' => $row, 'detailId' => $detailId])
            </div>
        </article>
    @endforeach
</div>
