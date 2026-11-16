@php
    $settings = $quizQuestion->walking_settings ?? [];
    $targetGender = $quizQuestion->getWalkingTargetGender();
    $scoringMethod = $quizQuestion->getWalkingScoringMethod();
    $isDailyPoints = in_array($scoringMethod, ['daily_rank_points', 'daily_points'], true);
    $minDailySteps = (int) ($settings['min_daily_steps'] ?? 0);
    $prizes = $quizQuestion->prize ?? [];
    if (!is_array($prizes) && !empty($prizes)) {
        $prizes = [$prizes];
    }
    // صدارة النساء تُعرض للنساء فقط حتى في الوضع المشترك (both_segregated)
    $walkViewerGender = null;
    try {
        $walkIdentity = app(\App\Services\SiteIdentityResolver::class)->resolve(request());
        $walkViewerId = $walkIdentity['person_id'] ?? null;
        $walkViewer = $walkViewerId
            ? \App\Models\Person::find($walkViewerId)
            : (auth()->check() ? auth()->user()?->person : null);
        $walkViewerGender = $walkViewer?->gender;
    } catch (\Throwable $e) {
        $walkViewerGender = null;
    }
    $canViewFemaleTrack = $walkViewerGender === 'female';
    $showFemaleTrack = $targetGender === 'female'
        || $targetGender === 'all'
        || ($targetGender === 'both_segregated' && $canViewFemaleTrack);
@endphp

<div class="space-y-6">
    {{-- بطاقة إحصائيات المتسابق الشخصية --}}
    @if(auth()->check() && $viewerStanding)
        @php
            $vRank = is_object($viewerStanding) ? ($viewerStanding->rank ?? null) : ($viewerStanding['rank'] ?? null);
            $vTotalSteps = is_object($viewerStanding) ? ($viewerStanding->total_steps ?? 0) : ($viewerStanding['total_steps'] ?? 0);
            $vTodaySteps = is_object($viewerStanding) ? ($viewerStanding->today_steps ?? 0) : ($viewerStanding['today_steps'] ?? 0);
            $vActualPts = is_object($viewerStanding) ? ($viewerStanding->total_points ?? ($viewerStanding->actual_points ?? 0)) : ($viewerStanding['total_points'] ?? ($viewerStanding['actual_points'] ?? 0));
            $vProjPts = is_object($viewerStanding) ? ($viewerStanding->projected_points ?? ($viewerStanding->projected_score ?? 0)) : ($viewerStanding['projected_points'] ?? ($viewerStanding['projected_score'] ?? 0));
            $vTotalPts = is_object($viewerStanding) ? ($viewerStanding->total_points ?? $vActualPts) : ($viewerStanding['total_points'] ?? $vActualPts);
        @endphp
        <div class="glass-effect rounded-3xl p-6 md:p-8 border-2 border-emerald-300 shadow-xl relative overflow-hidden bg-gradient-to-br from-emerald-500/10 via-teal-500/5 to-transparent">
            <div class="flex items-center justify-between mb-4">
                <span class="inline-flex items-center gap-2 text-xs font-bold px-3 py-1 rounded-full bg-emerald-100 text-emerald-800">
                    <i class="fas fa-id-badge text-emerald-600"></i>
                    بطاقتك في التحدي
                </span>
                @if($vRank)
                    <span class="text-sm font-black text-emerald-700 bg-white/90 px-3 py-1 rounded-xl shadow-sm border border-emerald-200">
                        المركز: #{{ $vRank }}
                    </span>
                @endif
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-center">
                <div class="bg-white/80 backdrop-blur rounded-2xl p-3 sm:p-4 border border-emerald-100 shadow-sm">
                    <p class="text-[11px] sm:text-xs text-gray-500 mb-1">النقاط</p>
                    <p class="text-lg sm:text-2xl font-black text-amber-700">
                        ⭐ {{ number_format($vActualPts) }}
                    </p>
                    <span class="text-[9px] text-gray-400">حتى منتصف الليل</span>
                </div>
                <div class="bg-white/80 backdrop-blur rounded-2xl p-3 sm:p-4 border border-emerald-100 shadow-sm">
                    <p class="text-[11px] sm:text-xs text-gray-500 mb-1">نقاط اليوم المتوقعة</p>
                    <p class="text-lg sm:text-2xl font-black text-emerald-600">
                        +{{ number_format($vProjPts) }}
                    </p>
                    <span class="text-[9px] text-emerald-700">تُضاف عند 12:00</span>
                </div>
                <div class="bg-white/80 backdrop-blur rounded-2xl p-3 sm:p-4 border border-emerald-100 shadow-sm">
                    <p class="text-[11px] sm:text-xs text-gray-500 mb-1">إجمالي النقاط</p>
                    <p class="text-lg sm:text-2xl font-black text-amber-600">
                        {{ number_format($vTotalPts) }}
                    </p>
                    <span class="text-[9px] text-gray-400">نقاط + متوقعة</span>
                </div>
                <div class="bg-white/80 backdrop-blur rounded-2xl p-3 sm:p-4 border border-emerald-100 shadow-sm">
                    <p class="text-[11px] sm:text-xs text-gray-500 mb-1">خطوات اليوم</p>
                    <p class="text-lg sm:text-2xl font-black text-teal-700">
                        {{ number_format($vTodaySteps) }}
                    </p>
                    <span class="text-[9px] text-gray-400">إجمالي: {{ number_format($vTotalSteps) }}</span>
                </div>
            </div>

            @if($minDailySteps > 0 && $vTodaySteps < $minDailySteps)
                <p class="mt-4 text-xs text-amber-700 bg-amber-50 rounded-xl p-2.5 border border-amber-200 flex items-center gap-2">
                    <i class="fas fa-info-circle"></i>
                    تحتاج إلى {{ number_format($minDailySteps - $vTodaySteps) }} خطوة إضافية اليوم لتحقيق الحد الأدنى ({{ number_format($minDailySteps) }} خطوة).
                </p>
            @endif
        </div>
    @elseif(!auth()->check())
        <div class="glass-effect rounded-2xl p-5 border border-emerald-200 text-center flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-3 text-right">
                <div class="w-12 h-12 rounded-2xl bg-emerald-100 text-emerald-600 flex items-center justify-center flex-shrink-0 text-xl">
                    <i class="fas fa-user-check"></i>
                </div>
                <div>
                    <h4 class="font-bold text-gray-800">هل تشارك في التحدي؟</h4>
                    <p class="text-xs text-gray-500">سجل الدخول لمعرفة ترتيبك وعدد خطواتك ونقاطك الشخصية.</p>
                </div>
            </div>
            <a href="{{ route('login') }}" class="px-5 py-2.5 rounded-xl bg-emerald-600 text-white text-sm font-bold shadow hover:bg-emerald-700 transition">
                تسجيل الدخول
            </a>
        </div>
    @endif

    {{-- نظام التحدي وقواعد النقاط --}}
    <div class="glass-effect rounded-2xl p-5 md:p-6 border border-gray-100">
        <h3 class="font-bold text-gray-800 mb-3 flex items-center gap-2">
            <i class="fas fa-circle-info text-emerald-600"></i>
            نظام التحدي والجوائز
        </h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs md:text-sm">
            <div class="flex items-center gap-2 text-gray-700 bg-gray-50/70 p-3 rounded-xl border border-gray-100">
                <i class="fas fa-calculator text-emerald-500"></i>
                <span>طريقة الاحتساب:
                    <strong class="text-emerald-700">
                        {{ $scoringMethod === 'daily_points' ? 'نقاط الترتيب اليومي (منتصف الليل)' : 'مجموع الخطوات التراكمي' }}
                    </strong>
                </span>
            </div>
            <div class="flex items-center gap-2 text-gray-700 bg-gray-50/70 p-3 rounded-xl border border-gray-100">
                <i class="fas fa-shoe-prints text-emerald-500"></i>
                <span>الحد الأدنى اليومي:
                    <strong class="text-emerald-700">
                        {{ $minDailySteps > 0 ? number_format($minDailySteps) . ' خطوة' : 'لا يوجد حد أدنى' }}
                    </strong>
                </span>
            </div>
            <div class="flex items-center gap-2 text-gray-700 bg-gray-50/70 p-3 rounded-xl border border-gray-100">
                <i class="fas fa-venus-mars text-emerald-500"></i>
                <span>المسار المستهدف:
                    <strong class="text-emerald-700">
                        @if($targetGender === 'both_segregated')
                            مساران منفصلان (جوائز مستقلة للرجال والنساء)
                        @elseif($targetGender === 'male')
                            مسار الرجال فقط
                        @elseif($targetGender === 'female')
                            مسار النساء فقط
                        @else
                            مسار موحد للجميع
                        @endif
                    </strong>
                </span>
            </div>
            <div class="flex items-center gap-2 text-gray-700 bg-gray-50/70 p-3 rounded-xl border border-gray-100">
                <i class="fas fa-clock text-amber-500"></i>
                <span>تحديث النقاط:
                    <strong class="text-gray-800">يومياً الساعة 12:00 منتصف الليل بتوقيت مكة</strong>
                </span>
            </div>
        </div>

        @if(!empty($prizes))
            <div class="mt-4 pt-4 border-t border-gray-100">
                <p class="text-xs font-bold text-gray-500 mb-2 flex items-center gap-1.5">
                    <i class="fas fa-trophy text-amber-500"></i>
                    الجوائز المرصودة للمراكز الأولى:
                </p>
                <div class="flex flex-wrap gap-2">
                    @foreach($prizes as $idx => $prz)
                        <span class="inline-flex items-center gap-1.5 bg-amber-50 text-amber-900 border border-amber-200 px-3 py-1.5 rounded-xl text-xs font-bold">
                            <span class="w-5 h-5 rounded-full bg-amber-400 text-amber-900 flex items-center justify-center text-[10px] font-black">
                                {{ $idx + 1 }}
                            </span>
                            {{ $prz }}
                        </span>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- تبويبات المسارات وقائمة المتصدرين --}}
    <div class="glass-effect rounded-3xl p-6 md:p-8 border border-gray-100 shadow-sm" x-data="{ activeTab: '{{ ($targetGender === 'female') ? 'female' : 'male' }}' }">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h3 class="text-xl md:text-2xl font-bold text-gray-800 flex items-center gap-2">
                    <i class="fas fa-fire text-amber-500"></i>
                    قائمة المتصدرين الحالية
                </h3>
                <p class="text-xs text-gray-500 mt-1">تحديث دوري لترتيب المتسابقين في التحدي</p>
            </div>

            @include('partials.home._walking-race-trigger', [
                'quizCompetition' => $quizCompetition,
                'quizQuestion' => $quizQuestion,
            ])

            @if($targetGender === 'both_segregated' && $canViewFemaleTrack)
                <div class="flex items-center p-1 bg-gray-100 rounded-2xl border border-gray-200 self-start sm:self-auto">
                    <button type="button"
                            onclick="switchWalkingTab('male')"
                            id="walkingTabBtn_male"
                            class="px-4 py-2 rounded-xl text-xs md:text-sm font-bold transition-all bg-emerald-600 text-white shadow-sm">
                        <i class="fas fa-mars ml-1"></i> مسار الرجال ({{ $maleStandings->count() }})
                    </button>
                    <button type="button"
                            onclick="switchWalkingTab('female')"
                            id="walkingTabBtn_female"
                            class="px-4 py-2 rounded-xl text-xs md:text-sm font-bold transition-all text-gray-600 hover:text-emerald-700">
                        <i class="fas fa-venus ml-1"></i> مسار النساء ({{ $femaleStandings->count() }})
                    </button>
                </div>
            @endif
        </div>

        {{-- قائمة الرجال --}}
        @if($targetGender === 'all' || $targetGender === 'male' || $targetGender === 'both_segregated')
            <div id="walkingLeaderboard_male" class="space-y-3">
                @forelse($maleStandings as $row)
                    @include('web-site.quiz-competitions._walking-leaderboard-row', ['row' => $row, 'scoringMethod' => $scoringMethod])
                @empty
                    <div class="text-center py-10 bg-gray-50/50 rounded-2xl border border-dashed border-gray-200">
                        <i class="fas fa-person-walking text-gray-300 text-4xl mb-3 block"></i>
                        <p class="text-gray-500 text-sm">لا توجد خطوات مسجلة لمسار الرجال حتى الآن</p>
                    </div>
                @endforelse
            </div>
        @endif

        {{-- قائمة النساء --}}
        @if($showFemaleTrack)
            <div id="walkingLeaderboard_female" class="space-y-3 {{ $targetGender === 'both_segregated' ? 'hidden' : '' }}">
                @forelse($femaleStandings as $row)
                    @include('web-site.quiz-competitions._walking-leaderboard-row', ['row' => $row, 'scoringMethod' => $scoringMethod])
                @empty
                    <div class="text-center py-10 bg-gray-50/50 rounded-2xl border border-dashed border-gray-200">
                        <i class="fas fa-person-walking text-gray-300 text-4xl mb-3 block"></i>
                        <p class="text-gray-500 text-sm">لا توجد خطوات مسجلة لمسار النساء حتى الآن</p>
                    </div>
                @endforelse
            </div>
        @endif
    </div>

    {{-- تنبيه المشاركة عبر التطبيق --}}
    <div class="rounded-2xl p-5 bg-gradient-to-r from-emerald-600 to-teal-700 text-white flex flex-col sm:flex-row items-center justify-between gap-4 shadow-lg">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-white/10 backdrop-blur flex items-center justify-center text-2xl flex-shrink-0">
                <i class="fas fa-mobile-screen-button"></i>
            </div>
            <div>
                <h4 class="font-bold text-base md:text-lg">سجّل خطواتك عبر تطبيق العائلة</h4>
                <p class="text-xs md:text-sm text-emerald-100">افتح التطبيق يومياً لمزامنة خطواتك من Apple Health أو Google Fit واحتسابها بالتحدي.</p>
            </div>
        </div>
        <span class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-white text-emerald-800 text-xs font-black shadow-md flex-shrink-0">
            <i class="fas fa-sync-alt"></i> مزامنة تلقائية
        </span>
    </div>
</div>

<script>
    function switchWalkingTab(gender) {
        var maleList = document.getElementById('walkingLeaderboard_male');
        var femaleList = document.getElementById('walkingLeaderboard_female');
        var maleBtn = document.getElementById('walkingTabBtn_male');
        var femaleBtn = document.getElementById('walkingTabBtn_female');

        if (!maleList || !femaleList) return;

        if (gender === 'male') {
            maleList.classList.remove('hidden');
            femaleList.classList.add('hidden');
            if (maleBtn) {
                maleBtn.className = 'px-4 py-2 rounded-xl text-xs md:text-sm font-bold transition-all bg-emerald-600 text-white shadow-sm';
            }
            if (femaleBtn) {
                femaleBtn.className = 'px-4 py-2 rounded-xl text-xs md:text-sm font-bold transition-all text-gray-600 hover:text-emerald-700';
            }
        } else {
            femaleList.classList.remove('hidden');
            maleList.classList.add('hidden');
            if (femaleBtn) {
                femaleBtn.className = 'px-4 py-2 rounded-xl text-xs md:text-sm font-bold transition-all bg-emerald-600 text-white shadow-sm';
            }
            if (maleBtn) {
                maleBtn.className = 'px-4 py-2 rounded-xl text-xs md:text-sm font-bold transition-all text-gray-600 hover:text-emerald-700';
            }
        }
    }
</script>
@include('partials.home._walking-race-modal')
