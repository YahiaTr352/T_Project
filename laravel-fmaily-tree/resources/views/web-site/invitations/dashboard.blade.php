@extends('web-site.invitations.layout')

@section('title', 'لوحة الدعوات')

@section('content')
    <div class="space-y-6">
        <section class="inv-hero">
            <img src="{{ asset('images/invitations/event-invitation.jpg') }}" alt="لوحة الدعوات" class="inv-hero-img">
            <div class="inv-hero-overlay">
                <h2 class="text-xl sm:text-2xl font-extrabold text-white">لوحة الدعوات</h2>
                <p class="text-xs sm:text-sm text-emerald-50/90 mt-1 font-medium">
                    مرحباً <strong class="text-white">{{ $person->first_name }}</strong> — أرسل دعواتك لمجموعاتك أو لأشخاص تختارهم من الشجرة
                </p>
            </div>
        </section>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="inv-card rounded-3xl p-5 flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl flex items-center justify-center shrink-0" style="background: var(--inv-surface-soft);">
                    <i class="fas fa-users text-xl inv-text-primary" aria-hidden="true"></i>
                </div>
                <div>
                    <p class="text-xs inv-muted font-bold">مجموعاتك</p>
                    <p class="text-2xl font-black mt-0.5" style="color: var(--inv-text)">{{ $total_groups }}</p>
                </div>
            </div>
            <div class="inv-card rounded-3xl p-5 flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl flex items-center justify-center shrink-0" style="background: var(--inv-surface-soft);">
                    <i class="fas fa-paper-plane text-xl inv-text-primary" aria-hidden="true"></i>
                </div>
                <div>
                    <p class="text-xs inv-muted font-bold">الدعوات المرسلة</p>
                    <p class="text-2xl font-black mt-0.5" style="color: var(--inv-text)">{{ $total_sent }}</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <a href="{{ route('invitations.send') }}" class="inv-card rounded-3xl p-6 text-center hover:-translate-y-0.5 transition">
                <div class="w-14 h-14 rounded-2xl inv-btn-primary flex items-center justify-center mx-auto mb-3.5">
                    <i class="fas fa-paper-plane text-xl text-white" aria-hidden="true"></i>
                </div>
                <h3 class="text-base font-extrabold" style="color: var(--inv-text)">إرسال دعوة</h3>
                <p class="text-xs inv-muted mt-1">واتساب و/أو إشعار التطبيق</p>
            </a>
            <a href="{{ route('invitations.groups.index') }}" class="inv-card rounded-3xl p-6 text-center hover:-translate-y-0.5 transition">
                <div class="w-14 h-14 rounded-2xl inv-btn-primary flex items-center justify-center mx-auto mb-3.5">
                    <i class="fas fa-users text-xl text-white" aria-hidden="true"></i>
                </div>
                <h3 class="text-base font-extrabold" style="color: var(--inv-text)">مجموعاتك</h3>
                <p class="text-xs inv-muted mt-1">سمِّها واختر الأعضاء من الشجرة</p>
            </a>
            <a href="{{ route('invitations.logs') }}" class="inv-card rounded-3xl p-6 text-center hover:-translate-y-0.5 transition">
                <div class="w-14 h-14 rounded-2xl inv-btn-primary flex items-center justify-center mx-auto mb-3.5">
                    <i class="fas fa-clock text-xl text-white" aria-hidden="true"></i>
                </div>
                <h3 class="text-base font-extrabold" style="color: var(--inv-text)">السجل</h3>
                <p class="text-xs inv-muted mt-1">تتبع إرسالاتك والردود</p>
            </a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="inv-card rounded-3xl p-5 sm:p-6">
                <div class="inv-section-heading">
                    <span class="inv-section-title"><span class="inv-dot"></span>مجموعاتك</span>
                    <a href="{{ route('invitations.groups.create') }}" class="inv-pill">+ مجموعة جديدة</a>
                </div>
                @forelse($groups as $group)
                    <a href="{{ route('invitations.groups.edit', $group) }}"
                       class="flex items-center justify-between p-3.5 rounded-2xl inv-inset mb-2.5 hover:opacity-90 transition">
                        <div>
                            <p class="font-extrabold text-sm" style="color: var(--inv-text)">{{ $group->name }}</p>
                            <p class="text-xs inv-muted mt-0.5">{{ $group->persons_count }} شخص</p>
                        </div>
                        <i class="fas fa-chevron-left text-xs inv-text-primary" aria-hidden="true"></i>
                    </a>
                @empty
                    <p class="text-sm inv-muted text-center py-8">لم تنشئ أي مجموعة بعد.</p>
                @endforelse
            </div>

            <div class="inv-card rounded-3xl p-5 sm:p-6">
                <div class="inv-section-heading">
                    <span class="inv-section-title"><span class="inv-dot"></span>آخر الإرسالات</span>
                    <a href="{{ route('invitations.logs') }}" class="text-xs font-bold inv-text-primary">عرض الكل</a>
                </div>
                @forelse($recent_notifications as $notification)
                    <div class="flex items-center justify-between gap-3 p-3.5 rounded-2xl inv-inset mb-2.5">
                        <div class="flex-1 min-w-0">
                            <p class="font-extrabold text-sm truncate" style="color: var(--inv-text)">{{ $notification->title ?: 'دعوة عائلية' }}</p>
                            <p class="text-xs inv-muted mt-0.5">{{ $notification->recipients->count() }} مستلم · {{ $notification->created_at->diffForHumans() }}</p>
                        </div>
                        @if($notification->status === 'pending_approval')
                            <span class="text-xs px-2.5 py-1 rounded-full inv-badge-warning font-bold">معلقة</span>
                        @elseif($notification->status === 'approved')
                            <span class="text-xs px-2.5 py-1 rounded-full inv-badge-info font-bold">معتمدة</span>
                        @elseif($notification->status === 'rejected')
                            <span class="text-xs px-2.5 py-1 rounded-full inv-badge-danger font-bold">مرفوضة</span>
                        @elseif($notification->status === 'sent')
                            <span class="text-xs px-2.5 py-1 rounded-full inv-badge-success font-bold">تم</span>
                        @elseif($notification->status === 'failed')
                            <span class="text-xs px-2.5 py-1 rounded-full inv-badge-danger font-bold">فشل</span>
                        @else
                            <span class="text-xs px-2.5 py-1 rounded-full inv-badge-info font-bold">جارٍ</span>
                        @endif
                    </div>
                @empty
                    <p class="text-sm inv-muted text-center py-8">لم ترسل أي دعوة بعد.</p>
                @endforelse
            </div>
        </div>
    </div>
@endsection
