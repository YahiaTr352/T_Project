@extends('web-site.invitations.layout')

@section('title', 'مجموعات الدعوات')

@section('content')
    <div class="space-y-5">
        {{-- البانر التعريفي --}}
        <section class="inv-hero">
            <img src="{{ asset('images/invitations/reminder.jpg') }}" alt="مجموعات الدعوات" class="inv-hero-img">
            <div class="inv-hero-overlay">
                <h2 class="text-xl sm:text-2xl font-extrabold text-white">مجموعات الدعوات</h2>
                <p class="text-xs sm:text-sm text-emerald-50/90 mt-1 font-medium">أنشئ قوائم عائلية لإرسال الدعوات بسرعة</p>
            </div>
        </section>

        {{-- زر إنشاء مجموعة جديدة --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <a href="{{ route('invitations.groups.create') }}"
               class="inv-btn-primary w-full inline-flex items-center justify-center gap-2 py-3.5 px-6 text-base">
                <i class="fas fa-plus" aria-hidden="true"></i>
                <span>مجموعة جديدة</span>
            </a>
        </div>

        {{-- قائمة المجموعات --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @forelse($groups as $group)
                @php
                    $persons = $group->relationLoaded('persons') ? $group->persons : collect();
                    $previewPersons = $persons->take(2);
                    $remaining = max(0, $group->persons_count - $previewPersons->count());
                    $tones = ['#0b4d31', '#157347', '#269b5d'];
                    $countWord = ($group->persons_count >= 3 && $group->persons_count <= 10) ? 'أعضاء' : 'عضو';
                @endphp
                <div class="inv-card rounded-3xl p-4 sm:p-5 transition">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3 min-w-0 flex-1">
                            {{-- تكديس الحروف / الأعضاء --}}
                            <div class="flex items-center shrink-0">
                                <span class="w-10 h-10 rounded-full flex items-center justify-center text-white font-bold text-sm border-2 z-30"
                                      style="background: var(--inv-primary-dark); border-color: var(--inv-surface);">
                                    {{ mb_substr(trim($group->name), 0, 1) ?: 'ع' }}
                                </span>
                                @foreach($previewPersons as $idx => $member)
                                    <span class="w-8 h-8 rounded-full flex items-center justify-center text-white font-bold text-xs border-2 -mr-2.5 z-20"
                                          style="background: {{ $tones[($idx + 1) % 3] }}; border-color: var(--inv-surface);">
                                        {{ mb_substr(trim($member->first_name ?? ''), 0, 1) ?: 'ع' }}
                                    </span>
                                @endforeach
                                @if($remaining > 0)
                                    <span class="min-w-[1.9rem] h-7 px-1.5 rounded-full flex items-center justify-center font-extrabold text-[11px] border-2 -mr-2 z-10"
                                          style="background: var(--inv-pill-bg); color: var(--inv-pill-text); border-color: var(--inv-surface);">
                                        {{ $remaining }}+
                                    </span>
                                @endif
                            </div>

                            <div class="min-w-0 flex-1">
                                <h3 class="text-base font-extrabold truncate" style="color: var(--inv-text)">{{ $group->name }}</h3>
                                <p class="text-xs inv-muted truncate mt-0.5 font-medium">
                                    {{ $group->persons_count }} {{ $countWord }}
                                    @if($group->description) · {{ $group->description }} @endif
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <a href="{{ route('invitations.groups.edit', $group) }}"
                               aria-label="تعديل {{ $group->name }}"
                               class="w-9 h-9 rounded-xl inv-inset flex items-center justify-center inv-text-primary hover:opacity-80 transition">
                                <i class="fas fa-pen text-xs" aria-hidden="true"></i>
                            </a>
                            <form action="{{ route('invitations.groups.destroy', $group) }}" method="POST" onsubmit="return confirm('حذف هذه المجموعة؟')">
                                @csrf @method('DELETE')
                                <button type="submit" aria-label="حذف {{ $group->name }}"
                                        class="w-9 h-9 rounded-xl inv-badge-danger flex items-center justify-center hover:opacity-80 transition cursor-pointer">
                                    <i class="fas fa-trash text-xs" aria-hidden="true"></i>
                                </button>
                            </form>
                        </div>
                    </div>

                    @if($persons->isNotEmpty())
                        <details class="mt-3 pt-3 border-t" style="border-color: var(--inv-border);">
                            <summary class="cursor-pointer text-xs font-bold inv-text-primary select-none">
                                عرض الأعضاء ({{ $persons->count() }})
                            </summary>
                            <div class="mt-2.5 p-2 rounded-2xl inv-inset space-y-1.5">
                                @foreach($persons->take(12) as $idx => $member)
                                    <div class="flex items-center justify-between gap-2 px-3 py-2 rounded-xl inv-card">
                                        <div class="flex items-center gap-2.5 min-w-0">
                                            <span class="w-7 h-7 rounded-full flex items-center justify-center text-white font-bold text-xs shrink-0"
                                                  style="background: {{ $tones[$idx % 3] }}">
                                                {{ mb_substr(trim($member->first_name ?? ''), 0, 1) ?: 'ع' }}
                                            </span>
                                            <span class="text-sm font-bold truncate" style="color: var(--inv-text)">
                                                {{ $member->full_name ?: $member->first_name }}
                                            </span>
                                        </div>
                                        <form action="{{ route('invitations.groups.detach-person', [$group, $member]) }}" method="POST">
                                            @csrf @method('DELETE')
                                            <button type="submit" aria-label="إزالة {{ $member->first_name }}"
                                                    class="w-7 h-7 rounded-lg flex items-center justify-center inv-muted hover:opacity-75 cursor-pointer">
                                                <i class="fas fa-times text-xs" aria-hidden="true"></i>
                                            </button>
                                        </form>
                                    </div>
                                @endforeach
                            </div>
                        </details>
                    @endif
                </div>
            @empty
                <div class="md:col-span-2 inv-card rounded-3xl p-10 text-center">
                    <div class="w-16 h-16 rounded-full mx-auto mb-4 flex items-center justify-center" style="background: var(--inv-surface-soft)">
                        <i class="fas fa-users text-2xl inv-text-primary" aria-hidden="true"></i>
                    </div>
                    <h3 class="text-lg font-extrabold mb-1" style="color: var(--inv-text)">لا توجد مجموعات بعد</h3>
                    <p class="text-sm inv-muted mb-5">أنشئ مجموعة لإرسال الدعوات بسرعة لأفراد العائلة.</p>
                    <a href="{{ route('invitations.groups.create') }}" class="inv-btn-primary inline-flex items-center gap-2 px-6 py-3 text-sm">
                        <i class="fas fa-plus" aria-hidden="true"></i>
                        إنشاء مجموعة
                    </a>
                </div>
            @endforelse
        </div>
    </div>
@endsection
