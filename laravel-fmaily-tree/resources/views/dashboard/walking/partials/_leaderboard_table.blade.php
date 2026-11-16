<div class="table-responsive">
    <table class="table table-bordered table-hover align-middle mb-0" id="{{ $tableId }}">
        <thead class="thead-light">
            <tr>
                <th width="70" class="text-center">الترتيب</th>
                <th>المشارك</th>
                <th>إجمالي الخطوات</th>
                <th>المسافة</th>
                <th>المدة</th>
                <th>السعرات</th>
                <th>الأيام النشطة</th>
                <th width="100" class="text-center">الإجراءات</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rankings as $index => $item)
            <tr class="{{ $item->rank == 1 ? 'table-warning-light font-weight-bold' : '' }}" data-participant-type="{{ $item->type }}" data-participant-id="{{ $item->id }}">
                <td class="text-center font-weight-bold">
                    @if($item->rank == 1)
                        <span class="badge badge-warning text-dark font-weight-bold" style="font-size: 1rem;">
                            <i class="fas fa-crown"></i> 1
                        </span>
                    @elseif($item->rank == 2)
                        <span class="badge badge-secondary font-weight-bold" style="font-size: 0.95rem;">
                            <i class="fas fa-medal"></i> 2
                        </span>
                    @elseif($item->rank == 3)
                        <span class="badge font-weight-bold text-white" style="background-color: #cd7f32; font-size: 0.95rem;">
                            <i class="fas fa-medal"></i> 3
                        </span>
                    @else
                        <span class="text-muted font-weight-bold">{{ $item->rank }}</span>
                    @endif
                </td>
                <td>
                    <div class="d-flex align-items-center">
                        <img src="{{ $item->avatar ?? ($item->photo_url ? asset($item->photo_url) : asset('admin-assets/assets/avatars/default.png')) }}"
                             alt="{{ $item->name }}" class="avatar-sm ml-2 mr-2">
                        <div>
                            <div class="font-weight-bold text-gray-900">{{ $item->full_name }}</div>
                            @if($item->type === 'person')
                                <small class="badge badge-pill badge-primary font-weight-normal">شجرة العائلة</small>
                            @else
                                <small class="badge badge-pill badge-secondary font-weight-normal">حساب إداري</small>
                            @endif
                            @if(($item->walking_leaderboard_mode ?? 'visible') === 'anonymous')
                                <small class="badge badge-pill badge-warning text-dark font-weight-bold" title="متسابق متخفي في تطبيق الجوال"><i class="fas fa-user-secret"></i> متخفي 🥷</small>
                            @elseif(($item->walking_leaderboard_mode ?? 'visible') === 'hidden')
                                <small class="badge badge-pill badge-danger font-weight-bold" title="مستبعد من لوحة المتصدرين في تطبيق الجوال"><i class="fas fa-eye-slash"></i> مستبعد من الصدارة 🚫</small>
                            @endif
                        </div>
                    </div>
                </td>
                <td>
                    <span class="h6 mb-0 font-weight-bold text-primary participant-total-steps" data-participant-type="{{ $item->type }}" data-participant-id="{{ $item->id }}">{{ number_format($item->total_steps) }}</span>
                    <small class="text-muted d-block">خطوة</small>
                </td>
                <td>
                    <strong>{{ number_format($item->total_distance, 1) }}</strong>
                    <small class="text-muted">كم</small>
                </td>
                <td>
                    <span>{{ $item->total_duration }}</span>
                    <small class="text-muted">دقيقة</small>
                </td>
                <td>
                    <span class="text-danger font-weight-bold">{{ number_format($item->total_calories) }}</span>
                    <small class="text-muted">kcal</small>
                </td>
                <td>
                    <span class="badge badge-light border">{{ $item->days_count }} يوم</span>
                </td>
                <td class="text-center align-middle">
                    @can('walking-program.update')
                    <button type="button" 
                            class="btn btn-xs btn-outline-primary shadow-sm font-weight-bold btn-open-person-month-steps"
                            data-person-id="{{ $item->type === 'person' ? $item->id : '' }}"
                            data-user-id="{{ $item->type === 'user' ? $item->id : '' }}"
                            data-name="{{ $item->full_name }}"
                            data-avatar="{{ $item->avatar ?? ($item->photo_url ? asset($item->photo_url) : asset('admin-assets/assets/avatars/default.png')) }}"
                            title="تعديل خطوات أيام الشهر للشخص">
                        <i class="fas fa-calendar-alt ml-1"></i> الأيام
                    </button>
                    @endcan
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="8" class="text-center py-5">
                    <div class="py-3">
                        <div class="mx-auto mb-3" style="background: rgba(78, 115, 223, 0.1); width: 64px; height: 64px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                            <i class="fas fa-shoe-prints fa-2x text-primary"></i>
                        </div>
                        <h6 class="font-weight-bold text-gray-800 mb-1">لا توجد خطوات مسجلة لهذه الفترة حتى الآن</h6>
                        <p class="text-muted small mb-3">يمكنك الانتقال إلى فترات زمنية أخرى كالأسبوعي أو الترتيب الكلي، أو تسجيل خطوات جديدة يدوياً لأفراد العائلة.</p>
                        <div class="d-flex justify-content-center flex-wrap" style="gap: 8px;">
                            @if($tableId !== 'table-alltime')
                            <button type="button" class="btn btn-outline-primary btn-sm switch-period-btn" data-pill-target="#lead-alltime">
                                <i class="fas fa-globe-americas mr-1"></i> عرض الترتيب الكلي
                            </button>
                            @endif
                            @if($tableId !== 'table-weekly')
                            <button type="button" class="btn btn-outline-success btn-sm switch-period-btn" data-pill-target="#lead-weekly">
                                <i class="fas fa-calendar-week mr-1"></i> عرض هذا الأسبوع
                            </button>
                            @endif
                            @can('walking-program.create')
                            <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addRecordModal">
                                <i class="fas fa-plus-circle mr-1"></i> تسجيل مشي يدوي
                            </button>
                            @endcan
                        </div>
                    </div>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>
