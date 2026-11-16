{{-- =====================================================================
     VOTE NOMINEES & LIVE RESULTS COMPONENT (_vote-nominees-leaderboard.blade.php)
     Displays currently nominated candidates before and after voting.
     Includes candidate photo (clickable to Silah profile), vote counts,
     percentages, medals/ranks, and quick-select support.
     ===================================================================== --}}

@php
    $liveResults = $liveVoteResults ?? ($quizQuestion->answer_type === 'vote' ? $quizQuestion->getLiveVoteResultsData() : null);
    $totalVotes = $liveResults['total_votes'] ?? 0;
    $nominees = $liveResults['results'] ?? [];
    $isOpenTree = $quizQuestion->isOpenTreeVoting();
    $canVoteNow = $canAnswer ?? true;
@endphp

<div class="vote-nominees-leaderboard-section rounded-3xl p-5 md:p-6 mb-6 relative overflow-hidden" id="voteLeaderboardCard">
    <div class="leaderboard-glass-backdrop"></div>

    {{-- Header --}}
    <div class="relative z-10 flex flex-wrap items-center justify-between gap-3 mb-5 border-b border-emerald-100/70 pb-4">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center text-white shadow-md shadow-emerald-200">
                <i class="fas fa-poll-h text-xl"></i>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h4 class="text-base md:text-lg font-black text-gray-900 m-0">
                        المرشحون الحاليون في هذا التصويت
                    </h4>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        مباشر
                    </span>
                </div>
                <p class="text-xs text-gray-500 mt-1 mb-0">
                    اضغط على صورة أو اسم أي مرشح لعرض ملفه الشخصي 🌳
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <div class="bg-white/80 backdrop-blur-sm border border-emerald-200 px-3.5 py-1.5 rounded-xl shadow-sm flex items-center gap-2">
                <i class="fas fa-users text-emerald-600 text-sm"></i>
                <span class="text-xs text-gray-500">إجمالي الأصوات:</span>
                <span class="text-sm font-black text-emerald-700" id="leaderboardTotalVotes">{{ number_format($totalVotes) }}</span>
            </div>
            <button type="button" class="btn-refresh-nominees" onclick="refreshNomineesLeaderboard({{ $quizCompetition->id }}, {{ $quizQuestion->id }})" title="تحديث النتائج المباشرة">
                <i class="fas fa-sync-alt text-emerald-600 text-xs"></i>
            </button>
        </div>
    </div>

    {{-- Nominees List / Grid --}}
    <div class="relative z-10" id="nomineesListContainer">
        @if(count($nominees) > 0)
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3 md:gap-4" id="nomineesCardsGrid">
                @foreach($nominees as $index => $cand)
                    @php
                        $rank = $cand['rank'] ?? ($index + 1);
                        $profileUrl = $cand['profile_url'] ?? ($cand['person_id'] ? route('people.profile.show', $cand['person_id']) : null);
                        $questionGender = $quizQuestion->resolveEffectiveAllowedGender();
                        $fallbackGender = in_array($questionGender, ['male', 'female'], true) ? $questionGender : 'male';
                        $candGender = $cand['gender'] ?? $fallbackGender;
                        $defaultAvatar = \App\Models\Person::anonymousAvatarFor($candGender);
                        $avatarUrl = $cand['avatar_url'] ?: $defaultAvatar;
                        $rankClass = $rank === 1 ? 'rank-gold' : ($rank === 2 ? 'rank-silver' : ($rank === 3 ? 'rank-bronze' : 'rank-normal'));
                    @endphp
                    <div class="nominee-card {{ $rankClass }} p-3.5 md:p-4 rounded-2xl transition-all duration-200 flex flex-col justify-between items-center text-center">
                        {{-- Top bar: Rank + Quick select --}}
                        <div class="flex items-center justify-between w-full gap-1 mb-2.5">
                            <span class="nominee-rank-badge">
                                @if($rank === 1)
                                    <i class="fas fa-crown text-amber-500 ml-1"></i> الأول 🥇
                                @elseif($rank === 2)
                                    <i class="fas fa-medal text-slate-400 ml-1"></i> الثاني 🥈
                                @elseif($rank === 3)
                                    <i class="fas fa-medal text-amber-700 ml-1"></i> الثالث 🥉
                                @else
                                    <span class="font-bold">#{{ $rank }}</span>
                                @endif
                            </span>

                            @if($canVoteNow && $isOpenTree)
                                <button type="button" class="btn-quick-pick"
                                    data-cand-id="{{ $cand['person_id'] }}"
                                    data-cand-name="{{ $cand['full_name'] }}"
                                    data-cand-avatar="{{ $avatarUrl }}"
                                    data-cand-gender="{{ $cand['gender'] ?? 'male' }}"
                                    onclick="quickSelectNomineeFromCard(this)"
                                    title="اختيار هذا المرشح">
                                    <i class="fas fa-check-circle ml-1"></i> اختيار
                                </button>
                            @endif
                        </div>

                        {{-- Middle: Avatar + Name (Clickable directly to profile) --}}
                        <div class="nominee-avatar-wrap mb-2">
                            @if($profileUrl)
                                <a href="{{ $profileUrl }}" target="_blank" class="nominee-avatar-link group" title="{{ $cand['full_name'] }}">
                                    <img src="{{ $avatarUrl }}" alt="{{ $cand['full_name'] }}" class="nominee-avatar-img"
                                         onerror="this.src='{{ $defaultAvatar }}'">
                                </a>
                            @else
                                <div class="nominee-avatar-link">
                                    <img src="{{ $avatarUrl }}" alt="{{ $cand['full_name'] }}" class="nominee-avatar-img"
                                         onerror="this.src='{{ $defaultAvatar }}'">
                                </div>
                            @endif
                        </div>

                        <div class="w-full mb-2">
                            <h5 class="nominee-name text-xs sm:text-sm font-bold text-gray-900 leading-tight truncate mb-1" title="{{ $cand['full_name'] }}">
                                @if($profileUrl)
                                    <a href="{{ $profileUrl }}" target="_blank" class="hover:text-emerald-600 transition-colors">
                                        {{ $cand['full_name'] }}
                                    </a>
                                @else
                                    {{ $cand['full_name'] }}
                                @endif
                            </h5>
                            <div class="flex items-center justify-center gap-1.5 flex-wrap">
                                <span class="nominee-percent-chip">
                                    <strong class="cand-percent-num">{{ $cand['percent'] }}%</strong>
                                </span>
                                <span class="nominee-votes-chip">
                                    <strong class="cand-count-num">{{ $cand['count'] }}</strong> صوت
                                </span>
                            </div>
                        </div>

                        {{-- Progress Bar --}}
                        <div class="nominee-progress-wrap w-full">
                            <div class="nominee-progress-track">
                                <div class="nominee-progress-fill" style="width: {{ max(4, $cand['percent']) }}%;" data-percent="{{ $cand['percent'] }}%"></div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="empty-nominees-card p-8 rounded-2xl text-center bg-white/70 border border-dashed border-emerald-300">
                <div class="w-16 h-16 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-600 flex items-center justify-center mx-auto mb-3 shadow-inner">
                    <i class="fas fa-seedling text-2xl"></i>
                </div>
                <h5 class="text-base font-bold text-gray-800 mb-1">لم يتم ترشيح أي شخص حتى الآن</h5>
                <p class="text-xs text-gray-500 max-w-md mx-auto mb-0">
                    كن أول من يشارك ويرشح من شجرة العائلة (صلة). سيظهر اختيارك هنا لجميع المشاركين بعد إرسال صوتك مباشرة!
                </p>
            </div>
        @endif
    </div>
</div>

<style>
/* ─── VOTE NOMINEES LEADERBOARD STYLING ─── */
.vote-nominees-leaderboard-section {
    background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 50%, #f7fee7 100%);
    border: 1.5px solid #a7f3d0;
    box-shadow: 0 10px 25px -5px rgba(16, 185, 129, 0.08);
}
.leaderboard-glass-backdrop {
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at top right, rgba(255,255,255,0.7) 0%, transparent 70%);
    pointer-events: none;
}
.btn-refresh-nominees {
    width: 32px;
    height: 32px;
    border-radius: 10px;
    background: #ffffff;
    border: 1px solid #d1fae5;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
}
.btn-refresh-nominees:hover {
    background: #ecfdf5;
    transform: rotate(90deg);
}
.nominee-card {
    background: #ffffff;
    border: 1.5px solid #e5e7eb;
    box-shadow: 0 2px 6px rgba(0,0,0,0.03);
    position: relative;
    overflow: hidden;
}
.nominee-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 18px rgba(16, 185, 129, 0.12);
}
.nominee-card.rank-gold {
    border-color: #fde68a;
    background: linear-gradient(180deg, #fffbeb 0%, #ffffff 100%);
}
.nominee-card.rank-silver {
    border-color: #cbd5e1;
    background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%);
}
.nominee-card.rank-bronze {
    border-color: #fed7aa;
    background: linear-gradient(180deg, #fff7ed 0%, #ffffff 100%);
}
.nominee-rank-badge {
    font-size: 0.72rem;
    font-weight: 800;
    padding: 3px 9px;
    border-radius: 20px;
    background: #f3f4f6;
    color: #4b5563;
    display: inline-flex;
    align-items: center;
}
.nominee-card.rank-gold .nominee-rank-badge {
    background: #fef3c7;
    color: #92400e;
    border: 1px solid #fde68a;
}
.nominee-card.rank-silver .nominee-rank-badge {
    background: #f1f5f9;
    color: #334155;
    border: 1px solid #e2e8f0;
}
.nominee-card.rank-bronze .nominee-rank-badge {
    background: #ffedd5;
    color: #9a3412;
    border: 1px solid #fed7aa;
}
.btn-quick-pick {
    font-size: 0.72rem;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 8px;
    background: #ecfdf5;
    color: #047857;
    border: 1px solid #a7f3d0;
    cursor: pointer;
    transition: all 0.15s ease;
    display: inline-flex;
    align-items: center;
}
.btn-quick-pick:hover {
    background: #10b981;
    color: #ffffff;
    border-color: #10b981;
}
.nominee-avatar-wrap {
    position: relative;
    width: 62px;
    height: 62px;
    margin: 0 auto;
}
.nominee-avatar-link {
    position: relative;
    display: block;
    width: 100%;
    height: 100%;
    border-radius: 50%;
    overflow: hidden;
    border: 2.5px solid #10b981;
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.2);
    text-decoration: none;
}
.nominee-card.rank-gold .nominee-avatar-link {
    border-color: #f59e0b;
    box-shadow: 0 0 12px rgba(245, 158, 11, 0.35);
}
.nominee-card.rank-silver .nominee-avatar-link {
    border-color: #94a3b8;
}
.nominee-card.rank-bronze .nominee-avatar-link {
    border-color: #f97316;
}
.nominee-avatar-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.25s ease;
}
.nominee-avatar-link:hover .nominee-avatar-img {
    transform: scale(1.1);
}
.nominee-votes-chip {
    font-size: 0.72rem;
    background: #f0fdf4;
    color: #166534;
    padding: 2px 7px;
    border-radius: 6px;
    border: 1px solid #bbf7d0;
    display: inline-flex;
    align-items: center;
}
.nominee-percent-chip {
    font-size: 0.72rem;
    background: #ecfdf5;
    color: #065f46;
    padding: 2px 7px;
    border-radius: 6px;
    font-weight: 900;
}
.nominee-progress-wrap {
    width: 100%;
    margin-top: 4px;
}
.nominee-progress-track {
    width: 100%;
    height: 7px;
    background: #f1f5f9;
    border-radius: 20px;
    overflow: hidden;
}
.nominee-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #10b981 0%, #059669 100%);
    border-radius: 20px;
    transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}
.nominee-card.rank-gold .nominee-progress-fill {
    background: linear-gradient(90deg, #f59e0b 0%, #d97706 100%);
}
</style>

<script>
window.quickSelectNomineeFromCard = function(btn) {
    var pId = btn.getAttribute('data-cand-id');
    var pName = btn.getAttribute('data-cand-name');
    var pAvatar = btn.getAttribute('data-cand-avatar');
    var pGender = btn.getAttribute('data-cand-gender');

    if (!pId) return;

    var container = document.querySelector('.open-tree-slots-container');
    if (!container) return;

    var qId = container.getAttribute('data-question-id');
    var max = parseInt(container.getAttribute('data-max-selections') || '1');

    // Check if candidate already placed in a slot
    var alreadySelected = false;
    container.querySelectorAll('.slot-hidden-input').forEach(function(inp) {
        if (!inp.disabled && inp.value == pId) {
            alreadySelected = true;
        }
    });

    if (alreadySelected) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'info',
                title: 'تنبيه',
                text: 'تم اختيار هذا المرشح بالفعل في إحدى خانات التصويت!',
                confirmButtonColor: '#10b981',
                confirmButtonText: 'حسناً'
            });
        }
        return;
    }

    // Find first empty slot
    var emptySlotCard = null;
    var targetSlotIndex = null;
    for (var i = 1; i <= max; i++) {
        var card = document.getElementById('open-slot-' + qId + '-' + i);
        if (card && card.classList.contains('is-empty')) {
            emptySlotCard = card;
            targetSlotIndex = i;
            break;
        }
    }

    // If no empty slot, prompt to replace or replace first if max==1
    if (!emptySlotCard) {
        if (max === 1) {
            targetSlotIndex = 1;
        } else {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'warning',
                    title: 'جميع الخانات ممتلئة',
                    text: 'لقد قمت باختيار الحد الأقصى (' + max + ' مرشحين). قم بحذف أحد المرشحين أولاً لتغييره.',
                    confirmButtonColor: '#10b981',
                    confirmButtonText: 'حسناً'
                });
            }
            return;
        }
    }

    // Set state for modal handler and call selectWebTreeCandidate
    if (typeof window.selectWebTreeCandidate === 'function') {
        window._webTreeState = window._webTreeState || {};
        window._webTreeState.currentQuestionId = qId;
        window._webTreeState.currentSlotIndex = targetSlotIndex;

        window.selectWebTreeCandidate({
            id: pId,
            name: pName,
            first_name: pName,
            photo_url: pAvatar,
            gender: pGender,
            selectable: true
        });

        // Flash slot card
        var card = document.getElementById('open-slot-' + qId + '-' + targetSlotIndex);
        if (card) {
            card.scrollIntoView({ behavior: 'smooth', block: 'center' });
            card.style.outline = '3px solid #10b981';
            setTimeout(function() { card.style.outline = ''; }, 1200);
        }
    }
};

window.refreshNomineesLeaderboard = function(compId, qId) {
    var icon = document.querySelector('.btn-refresh-nominees i');
    if (icon) icon.classList.add('fa-spin');

    var url = '/quiz-competitions/' + compId + '/questions/' + qId + '/vote-results';
    fetch(url)
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (icon) icon.classList.remove('fa-spin');
            if (!data.results) return;

            var totalEl = document.getElementById('leaderboardTotalVotes');
            if (totalEl) totalEl.textContent = data.total_votes || data.total || 0;

            // Re-render cards if container exists
            var grid = document.getElementById('nomineesCardsGrid');
            if (!grid) {
                // If previously empty, reload page cleanly to build grid
                window.location.reload();
                return;
            }

            // Update stats in place
            data.results.forEach(function(cand, idx) {
                var rank = cand.rank || (idx + 1);
                var card = grid.children[idx];
                if (card) {
                    var countEl = card.querySelector('.cand-count-num');
                    if (countEl) countEl.textContent = cand.count;
                    var pctEl = card.querySelector('.cand-percent-num');
                    if (pctEl) pctEl.textContent = cand.percent + '%';
                    var fillEl = card.querySelector('.nominee-progress-fill');
                    if (fillEl) fillEl.style.width = Math.max(4, cand.percent) + '%';
                }
            });
        })
        .catch(function() {
            if (icon) icon.classList.remove('fa-spin');
        });
};
</script>
