<div>
    {{-- مؤشرات سريعة --}}
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">إجمالي المحادثات</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ number_format($overview['conversations_total']) }}</div>
                            <small class="text-muted">مقفلة: {{ number_format($overview['conversations_locked']) }}</small>
                        </div>
                        <div class="col-auto"><i class="fas fa-comments fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">رسائل اليوم</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ number_format($overview['messages_today']) }}</div>
                            <small class="text-muted">آخر ٧ أيام: {{ number_format($overview['messages_week']) }}</small>
                        </div>
                        <div class="col-auto"><i class="fas fa-comment-dots fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">بلاغات مفتوحة</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ number_format($overview['reports_pending']) }}</div>
                            <small class="text-muted">إجمالي البلاغات: {{ number_format($overview['reports_total']) }}</small>
                        </div>
                        <div class="col-auto"><i class="fas fa-flag fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">محظورون نشطون</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ number_format($overview['bans_active']) }}</div>
                            <small class="text-muted">حظر عام: {{ number_format($overview['bans_global']) }}</small>
                        </div>
                        <div class="col-auto"><i class="fas fa-user-slash fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- مؤشرات ثانوية --}}
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card shadow h-100 py-2">
                <div class="card-body py-3">
                    <div class="text-xs font-weight-bold text-muted text-uppercase mb-1">مشاركون نشطون (٧ أيام)</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800">{{ number_format($overview['active_senders_week']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card shadow h-100 py-2">
                <div class="card-body py-3">
                    <div class="text-xs font-weight-bold text-muted text-uppercase mb-1">رسائل مُخفاة</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800">{{ number_format($overview['messages_hidden']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card shadow h-100 py-2">
                <div class="card-body py-3">
                    <div class="text-xs font-weight-bold text-muted text-uppercase mb-1">رسائل مُعلَّمة</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800">{{ number_format($overview['messages_flagged']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card shadow h-100 py-2">
                <div class="card-body py-3">
                    <div class="text-xs font-weight-bold text-muted text-uppercase mb-1">رسائل محذوفة</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800">{{ number_format($overview['messages_deleted']) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        {{-- حجم الرسائل --}}
        <div class="col-xl-8 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3 d-flex align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-chart-bar mr-1"></i> حجم الرسائل خلال {{ $days }} يوم</h6>
                    <select wire:model.live="days" class="form-control form-control-sm" style="width: auto;">
                        <option value="7">٧ أيام</option>
                        <option value="14">١٤ يوم</option>
                        <option value="30">٣٠ يوم</option>
                    </select>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-end" style="height: 180px; gap: 4px;">
                        @foreach ($volume as $day)
                            @php
                                $height = $volumeMax > 0 ? max(4, (int) round(($day['count'] / $volumeMax) * 160)) : 4;
                            @endphp
                            <div class="flex-fill d-flex flex-column align-items-center justify-content-end" style="min-width: 10px;"
                                title="{{ $day['date'] }}: {{ $day['count'] }} رسالة">
                                <div class="w-100 bg-primary rounded-top"
                                    style="height: {{ $height }}px; max-width: 34px;"></div>
                            </div>
                        @endforeach
                    </div>
                    <div class="d-flex justify-content-between mt-2">
                        <small class="text-muted">{{ $volume[0]['date'] ?? '' }}</small>
                        <small class="text-muted">{{ $volume[count($volume) - 1]['date'] ?? '' }}</small>
                    </div>
                </div>
            </div>
        </div>

        {{-- التوزيع --}}
        <div class="col-xl-4 mb-4">
            <div class="card shadow h-100 mb-3">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-sitemap mr-1"></i> المحادثات حسب المالك</h6>
                </div>
                <div class="card-body py-2">
                    @forelse ($byOwner as $row)
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <span class="small">{{ $row['type'] }}</span>
                            <span class="badge badge-primary badge-pill">{{ $row['count'] }}</span>
                        </div>
                    @empty
                        <p class="text-muted small mb-0 py-2">لا توجد بيانات.</p>
                    @endforelse
                </div>
            </div>

            <div class="card shadow h-100">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-file-alt mr-1"></i> الرسائل حسب النوع</h6>
                </div>
                <div class="card-body py-2">
                    @forelse ($byType as $row)
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <span class="small">{{ $row['type'] }}</span>
                            <span class="badge badge-info badge-pill">{{ $row['count'] }}</span>
                        </div>
                    @empty
                        <p class="text-muted small mb-0 py-2">لا توجد بيانات.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        {{-- أكثر المحادثات نشاطاً --}}
        <div class="col-xl-4 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-fire mr-1"></i> أكثر المحادثات نشاطاً</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0 text-center">
                            <thead class="thead-light">
                                <tr><th>المحادثة</th><th>الرسائل</th><th></th></tr>
                            </thead>
                            <tbody>
                                @forelse ($topConversations as $conv)
                                    <tr>
                                        <td class="text-right small">
                                            <div class="font-weight-bold text-dark">{{ \Illuminate\Support\Str::limit($conv->title, 40) }}</div>
                                            <small class="text-muted">{{ class_basename($conv->conversable_type) }}</small>
                                        </td>
                                        <td><span class="badge badge-primary">{{ $conv->messages_count }}</span></td>
                                        <td>
                                            <a href="{{ route('dashboard.chat.conversation.show', $conv) }}"
                                                class="btn btn-sm btn-outline-primary" title="فتح">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-muted small py-3">لا توجد محادثات.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- أكثر المرسلين --}}
        <div class="col-xl-4 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-user-friends mr-1"></i> أكثر المرسلين (٣٠ يوم)</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0 text-center">
                            <thead class="thead-light">
                                <tr><th>الاسم</th><th>الرسائل</th></tr>
                            </thead>
                            <tbody>
                                @forelse ($topSenders as $sender)
                                    <tr>
                                        <td class="text-right small">{{ $sender['name'] }}</td>
                                        <td><span class="badge badge-success">{{ $sender['count'] }}</span></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="2" class="text-muted small py-3">لا توجد بيانات.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- أكثر المبلّغين --}}
        <div class="col-xl-4 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-flag mr-1"></i> الأكثر بلاغاً</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0 text-center">
                            <thead class="thead-light">
                                <tr><th>الاسم</th><th>البلاغات</th></tr>
                            </thead>
                            <tbody>
                                @forelse ($topReporters as $reporter)
                                    <tr>
                                        <td class="text-right small">{{ $reporter['name'] }}</td>
                                        <td><span class="badge badge-warning">{{ $reporter['count'] }}</span></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="2" class="text-muted small py-3">لا توجد بيانات.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
