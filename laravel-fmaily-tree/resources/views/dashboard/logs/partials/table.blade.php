<div class="dashboard-table-container">
    <table class="dashboard-table text-xs">
        <thead>
            <tr>
                <th style="min-width: 130px;">الوقت</th>
                <th style="min-width: 140px;">المستخدم</th>
                <th style="min-width: 100px;">الحدث</th>
                <th style="min-width: 160px;">العنصر</th>
                <th>ملخص ذكي</th>
                <th class="text-center" style="width: 90px;">التفاصيل</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                @php
                    $detailId = 'log-detail-' . $row['tab'] . '-' . $row['id'];
                @endphp
                <tr>
                    <td class="align-middle">
                        <div class="font-bold notif-title font-mono">{{ $row['time'] }}</div>
                        <div class="text-[10px] notif-muted font-semibold">{{ $row['time_human'] }}</div>
                    </td>

                    <td class="align-middle">
                        @if($row['actor_name'])
                            <div class="font-bold notif-title flex items-center gap-1.5">
                                <i class="fas fa-user-circle text-slate-400" aria-hidden="true"></i>
                                {{ $row['actor_name'] }}
                            </div>
                            <div class="text-[10px] notif-muted font-mono">#{{ $row['actor_id'] }}</div>
                        @else
                            <span class="notif-muted font-semibold">— (نظام تلقائي)</span>
                        @endif
                    </td>

                    <td class="align-middle">
                        @include('dashboard.logs.partials._event-badge-inline', ['row' => $row])
                    </td>

                    <td class="align-middle">
                        <div class="font-bold notif-title">{{ $row['subject_label'] }}</div>
                        <div class="text-[10px] notif-muted font-mono">{{ $row['subject_class'] }} #{{ $row['subject_id'] }}</div>
                    </td>

                    <td class="align-middle">
                        <p class="m-0 notif-subtitle font-medium leading-relaxed">{{ $row['summary'] }}</p>
                        @if($row['description'])
                            <p class="m-0 mt-1 text-[10px] notif-muted">{{ $row['description'] }}</p>
                        @endif
                    </td>

                    <td class="align-middle text-center">
                        <button class="btn btn-sm btn-outline-primary rounded-lg text-[11px] font-bold px-2.5 py-1"
                                type="button"
                                data-toggle="collapse"
                                data-target="#{{ $detailId }}"
                                aria-expanded="false"
                                aria-controls="{{ $detailId }}">
                            <i class="fas fa-chevron-down ml-1"></i> عرض
                        </button>
                    </td>
                </tr>

                <tr class="logs-collapse-row">
                    <td colspan="6" class="logs-collapse-cell">
                        <div id="{{ $detailId }}" class="collapse logs-detail-collapse">
                            @include('dashboard.logs.partials.detail-panel', ['row' => $row, 'detailId' => $detailId])
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
