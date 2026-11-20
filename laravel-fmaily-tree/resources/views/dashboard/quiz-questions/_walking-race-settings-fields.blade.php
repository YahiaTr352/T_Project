@php
    $ws = $ws ?? [];
    $prefix = $prefix ?? 'walking_settings';
    $dotPrefix = str_replace(['[', ']'], ['.', ''], $prefix);

    $raceEnabled = (bool) old($dotPrefix . '.race_enabled', $ws['race_enabled'] ?? false);
    $raceCalcMode = old($dotPrefix . '.race_calc_mode', $ws['race_calc_mode'] ?? 'cumulative');
    $raceSpeedMs = (int) old($dotPrefix . '.race_speed_ms', $ws['race_speed_ms'] ?? 2800);
    $raceMaxRanks = (int) old($dotPrefix . '.race_max_ranks', $ws['race_max_ranks'] ?? 10);
    $raceTitle = old($dotPrefix . '.race_title', $ws['race_title'] ?? '');
    $raceSubtitle = old($dotPrefix . '.race_subtitle', $ws['race_subtitle'] ?? '');
    $raceButtonText = old($dotPrefix . '.race_button_text', $ws['race_button_text'] ?? '');
    $uid = $uid ?? 'qform';
@endphp

<!-- ── Daily Animated Bar Chart Race Settings ─────────────────── -->
<div class="col-12 mt-4">
    <div class="card border-0 bg-white shadow-sm overflow-hidden" style="border-radius: 16px; border: 2px solid #1e3a8a !important;">
        <div class="card-header py-3 d-flex align-items-center justify-content-between" style="background: linear-gradient(135deg, #0f2942, #1e3a8a); color: #ffffff;">
            <div class="d-flex align-items-center">
                <i class="fas fa-chart-bar ml-2 text-warning" style="color: #fbbf24 !important;"></i>
                <div>
                    <h6 class="mb-0 font-weight-bold text-white" style="color: #ffffff !important;">نافذة سباق الترتيب اليومي المتحرك (Bar Chart Race)</h6>
                    <small class="d-block" style="color: rgba(255, 255, 255, 0.9) !important;">عرض تطور ترتيب المتسابقين وخطواتهم يومياً منذ بداية المسابقة على الويب والموبايل</small>
                </div>
            </div>
            <div class="custom-control custom-switch">
                <input type="hidden" name="{{ $prefix }}[race_enabled]" value="0">
                <input type="checkbox"
                       class="custom-control-input"
                       id="race_enabled_{{ $uid }}"
                       name="{{ $prefix }}[race_enabled]"
                       value="1"
                       {{ $raceEnabled ? 'checked' : '' }}
                       onchange="document.getElementById('raceFieldsBody_{{ $uid }}').style.opacity = this.checked ? '1' : '0.6';">
                <label class="custom-control-label font-weight-bold text-white" for="race_enabled_{{ $uid }}">
                    إظهار الزر
                </label>
            </div>
        </div>

        <div class="card-body p-4" id="raceFieldsBody_{{ $uid }}" style="{{ $raceEnabled ? '' : 'opacity: 0.72;' }}">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="modern-label font-weight-bold text-gray-800">
                        <i class="fas fa-calculator ml-1 text-primary"></i> طريقة ترتيب السباق
                    </label>
                    <select name="{{ $prefix }}[race_calc_mode]" class="form-control modern-input font-weight-bold">
                        <option value="cumulative" {{ $raceCalcMode === 'cumulative' ? 'selected' : '' }}>
                            تراكمي يوم بيوم (يجمع كل يوم على السابق)
                        </option>
                        <option value="daily" {{ $raceCalcMode === 'daily' ? 'selected' : '' }}>
                            نقاط كل يوم على حدة
                        </option>
                    </select>
                    <small class="text-muted d-block mt-1">التراكمي يجمع خطوات كل يوم على مجموع الأيام السابقة فيتغير الترتيب العام.</small>
                </div>

                <div class="col-md-4 mb-3">
                    <label class="modern-label font-weight-bold text-gray-800">
                        <i class="fas fa-tachometer-alt ml-1 text-primary"></i> سرعة انتقال الأيام
                    </label>
                    <select name="{{ $prefix }}[race_speed_ms]" class="form-control modern-input font-weight-bold">
                        <option value="4000" {{ $raceSpeedMs >= 3600 ? 'selected' : '' }}>بطيء (4.0 ثوانٍ لكل يوم)</option>
                        <option value="2800" {{ $raceSpeedMs > 2100 && $raceSpeedMs < 3600 ? 'selected' : '' }}>متوسط (2.8 ثانية لكل يوم)</option>
                        <option value="1800" {{ $raceSpeedMs <= 2100 ? 'selected' : '' }}>سريع (1.8 ثانية لكل يوم)</option>
                    </select>
                </div>

                <div class="col-md-4 mb-3">
                    <label class="modern-label font-weight-bold text-gray-800" for="race_max_ranks_{{ $uid }}">
                        <i class="fas fa-list-ol ml-1 text-primary"></i> عدد المراكز المعروضة في السباق
                    </label>
                    <div class="input-group">
                        <input type="number"
                               id="race_max_ranks_{{ $uid }}"
                               name="{{ $prefix }}[race_max_ranks]"
                               class="form-control modern-input font-weight-bold"
                               value="{{ $raceMaxRanks }}"
                               min="0"
                               max="500"
                               oninput="window.syncRaceRankPreset && window.syncRaceRankPreset('{{ $uid }}', this.value)">
                        <div class="input-group-append">
                            <span class="input-group-text font-weight-bold bg-light text-muted" style="font-size: 0.78rem;">0 = الكل</span>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap align-items-center mt-2" style="gap: 6px;" data-race-preset-group="{{ $uid }}">
                        <button type="button"
                                class="btn btn-sm font-weight-bold {{ $raceMaxRanks === 10 ? 'btn-primary' : 'btn-outline-primary' }}"
                                style="border-radius: 8px; padding: 2px 10px;"
                                onclick="window.setRaceMaxRanksPreset('{{ $uid }}', 10)">10 مراكز</button>
                        <button type="button"
                                class="btn btn-sm font-weight-bold {{ $raceMaxRanks === 20 ? 'btn-primary' : 'btn-outline-primary' }}"
                                style="border-radius: 8px; padding: 2px 10px;"
                                onclick="window.setRaceMaxRanksPreset('{{ $uid }}', 20)">20 مركز</button>
                        <button type="button"
                                class="btn btn-sm font-weight-bold {{ $raceMaxRanks === 0 ? 'btn-primary' : 'btn-outline-primary' }}"
                                style="border-radius: 8px; padding: 2px 10px;"
                                onclick="window.setRaceMaxRanksPreset('{{ $uid }}', 0)">كل المتسابقين</button>
                    </div>
                </div>

                <div class="col-md-4 mb-2">
                    <label class="modern-label font-weight-bold text-gray-800">
                        <i class="fas fa-heading ml-1 text-secondary"></i> العنوان الرئيسي أعلى النافذة (اختياري)
                    </label>
                    <input type="text" name="{{ $prefix }}[race_title]" class="form-control modern-input"
                           value="{{ $raceTitle }}" placeholder="تلقائي: عنوان المسابقة (مثلاً: مسابقة الصحة أولاً)">
                </div>

                <div class="col-md-4 mb-2">
                    <label class="modern-label font-weight-bold text-gray-800">
                        <i class="fas fa-quote-right ml-1 text-secondary"></i> العنوان الفرعي أسفل العنوان (اختياري)
                    </label>
                    <input type="text" name="{{ $prefix }}[race_subtitle]" class="form-control modern-input"
                           value="{{ $raceSubtitle }}" placeholder="ماهي مجرد مسابقة... بل بداية عادة">
                </div>

                <div class="col-md-4 mb-2">
                    <label class="modern-label font-weight-bold text-gray-800">
                        <i class="fas fa-mouse-pointer ml-1 text-secondary"></i> نص الزر في المسابقة (اختياري)
                    </label>
                    <input type="text" name="{{ $prefix }}[race_button_text]" class="form-control modern-input"
                           value="{{ $raceButtonText }}" placeholder="سباق الترتيب اليومي">
                </div>
            </div>
        </div>
    </div>
</div>

@once
<script>
    window.syncRaceRankPreset = function (uid, rawVal) {
        var val = parseInt(rawVal, 10);
        var group = document.querySelector('[data-race-preset-group="' + uid + '"]');
        if (!group) return;
        var buttons = group.querySelectorAll('button');
        var presets = [10, 20, 0];
        buttons.forEach(function (btn, idx) {
            var isMatch = !isNaN(val) && val === presets[idx];
            btn.classList.toggle('btn-primary', isMatch);
            btn.classList.toggle('btn-outline-primary', !isMatch);
        });
    };

    window.setRaceMaxRanksPreset = function (uid, val) {
        var input = document.getElementById('race_max_ranks_' + uid) || document.getElementById('modalRaceMaxRanks' + uid);
        if (input) {
            input.value = val;
        }
        window.syncRaceRankPreset(uid, val);
    };
</script>
@endonce
