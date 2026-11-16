@php
    $types = [
        [
            'key' => 'person',
            'icon' => 'fa-user',
            'label' => 'عن شخص',
            'hint' => 'ابحث عن فرد أو سيرة',
            'question' => 'من هو أكبر فرد مسجّل في الشجرة؟',
        ],
        [
            'key' => 'story',
            'icon' => 'fa-book-open',
            'label' => 'قصة عائلية',
            'hint' => 'قصص وأحداث',
            'question' => 'احكِ لي عن قصة من قصص العائلة',
        ],
        [
            'key' => 'kinship',
            'icon' => 'fa-people-arrows',
            'label' => 'صلة قرابة',
            'hint' => 'من يرتبط بمن',
            'question' => 'إيه صلة القرابة بين شخصين في العائلة؟',
        ],
        [
            'key' => 'stats',
            'icon' => 'fa-chart-pie',
            'label' => 'إحصائيات',
            'hint' => 'أعداد وتقارير',
            'question' => 'كم عدد أفراد العائلة المسجلين؟',
        ],
    ];
@endphp

<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 md:gap-4 mb-6">
    @foreach($types as $type)
        <button type="button"
                class="ai-type-card glass-card rounded-2xl p-4 text-right w-full"
                :class="{ 'is-active': activeType === @js($type['key']) }"
                @click="pickType(@js($type['key']), @js($type['question']))">
            <div class="w-11 h-11 rounded-xl gradient-primary flex items-center justify-center text-white text-lg mb-3 shadow-md">
                <i class="fas {{ $type['icon'] }}"></i>
            </div>
            <div class="text-sm font-bold text-gray-800">{{ $type['label'] }}</div>
            <div class="text-[11px] text-gray-500 mt-1">{{ $type['hint'] }}</div>
        </button>
    @endforeach
</div>
