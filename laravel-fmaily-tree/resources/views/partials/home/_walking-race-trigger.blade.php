@if (isset($quizCompetition, $quizQuestion) && $quizQuestion->isWalkingRaceEnabled())
    @php
        $raceCfg = $quizQuestion->getWalkingRaceConfig();
        $raceEndpoint = route('quiz-competitions.walking-race', [$quizCompetition, $quizQuestion]);
    @endphp
    <div class="flex justify-center mb-4">
        <button type="button"
                data-race-url="{{ $raceEndpoint }}"
                data-race-title="{{ $raceCfg['title'] }}"
                data-race-subtitle="{{ $raceCfg['subtitle'] }}"
                onclick="window.openWalkingRaceModal(this.dataset.raceUrl, this.dataset.raceTitle, this.dataset.raceSubtitle)"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs sm:text-sm font-extrabold shadow-sm transition-all hover:opacity-95 active:scale-95"
                style="background: linear-gradient(135deg, #0f2942, #1e3a8a); color: #ffffff; border: 1px solid #1e3a8a;">
            <i class="fas fa-chart-bar text-amber-300"></i>
            <span>{{ $raceCfg['button_text'] }}</span>
        </button>
    </div>
@endif
