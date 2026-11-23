@extends('web-site.invitations.layout')

@section('title', 'لوحة الدعوات')

@section('content')
    @include('web-site.invitations.partials.navigation')
    <div class="text-center mb-10">
        <div class="inline-flex items-center justify-center w-20 h-20 rounded-2xl bg-gradient-to-br from-emerald-400 to-emerald-600 shadow-xl mb-4">
            <i class="fas fa-paper-plane text-3xl text-white"></i>
        </div>
        <h1 class="text-3xl font-extrabold text-slate-900 mb-2">لوحة الدعوات</h1>
        <p class="text-slate-600">مرحباً <strong>{{ $person->first_name }}</strong> — أرسل دعواتك لمجموعاتك أو لأشخاص تختارهم من الشجرة</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <div class="glass-card rounded-2xl p-6">
            <p class="text-xs text-slate-500 mb-1 font-bold">مجموعاتك</p>
            <p class="text-3xl font-black text-slate-900">{{ $total_groups }}</p>
        </div>
        <div class="glass-card rounded-2xl p-6">
            <p class="text-xs text-slate-500 mb-1 font-bold">الدعوات المرسلة</p>
            <p class="text-3xl font-black text-slate-900">{{ $total_sent }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
        <a href="{{ route('invitations.send') }}" class="glass-card rounded-3xl p-6 text-center hover:-translate-y-1 transition">
            <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-emerald-400 to-emerald-600 flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-paper-plane text-2xl text-white"></i>
            </div>
            <h3 class="text-lg font-bold">إرسال دعوة</h3>
            <p class="text-xs text-slate-500 mt-1">واتساب و/أو إشعار التطبيق</p>
        </a>
        <a href="{{ route('invitations.groups.index') }}" class="glass-card rounded-3xl p-6 text-center hover:-translate-y-1 transition">
            <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-users text-2xl text-white"></i>
            </div>
            <h3 class="text-lg font-bold">مجموعاتك</h3>
            <p class="text-xs text-slate-500 mt-1">سمِّها واختر الأعضاء من الشجرة</p>
        </a>
        <a href="{{ route('invitations.logs') }}" class="glass-card rounded-3xl p-6 text-center hover:-translate-y-1 transition">
            <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-purple-400 to-purple-600 flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-clock-rotate-left text-2xl text-white"></i>
            </div>
            <h3 class="text-lg font-bold">السجل</h3>
            <p class="text-xs text-slate-500 mt-1">تتبع إرسالاتك</p>
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <div class="glass-card rounded-3xl p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-bold text-slate-800"><i class="fas fa-users text-blue-600 ml-1"></i> مجموعاتك</h2>
                <a href="{{ route('invitations.groups.create') }}" class="text-xs text-emerald-600 font-semibold">مجموعة جديدة</a>
            </div>
            @forelse($groups as $group)
                <a href="{{ route('invitations.groups.edit', $group) }}" class="flex items-center justify-between p-3 rounded-xl hover:bg-slate-50 mb-2">
                    <div>
                        <p class="font-bold text-slate-800">{{ $group->name }}</p>
                        <p class="text-xs text-slate-500">{{ $group->persons_count }} شخص</p>
                    </div>
                    <i class="fas fa-chevron-left text-slate-300"></i>
                </a>
            @empty
                <p class="text-sm text-slate-500 text-center py-8">لم تنشئ أي مجموعة بعد.</p>
            @endforelse
        </div>
        <div class="glass-card rounded-3xl p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-bold text-slate-800"><i class="fas fa-history text-emerald-600 ml-1"></i> آخر الإرسالات</h2>
                <a href="{{ route('invitations.logs') }}" class="text-xs text-emerald-600 font-semibold">عرض الكل</a>
            </div>
            @forelse($recent_notifications as $notification)
                <div class="flex items-start gap-3 p-3 rounded-xl bg-slate-50 mb-2">
                    <div class="flex-1 min-w-0">
                        <p class="font-bold text-sm truncate">{{ $notification->title ?: 'دعوة عائلية' }}</p>
                        <p class="text-xs text-slate-500">{{ $notification->recipients->count() }} مستلم · {{ $notification->created_at->diffForHumans() }}</p>
                    </div>
                    @if($notification->status === 'pending_approval')
                        <span class="text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 font-bold">
                            معلقة
                        </span>
                    @elseif($notification->status === 'approved')
                        <span class="text-xs px-2 py-0.5 rounded-full bg-sky-100 text-sky-800 font-bold">
                            معتمدة
                        </span>
                    @elseif($notification->status === 'rejected')
                        <span class="text-xs px-2 py-0.5 rounded-full bg-rose-100 text-rose-800 font-bold">
                            مرفوضة
                        </span>
                    @elseif($notification->status === 'sent')
                        <span class="text-xs px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800">
                            تم
                        </span>
                    @elseif($notification->status === 'failed')
                        <span class="text-xs px-2 py-0.5 rounded-full bg-rose-100 text-rose-800">
                            فشل
                        </span>
                    @else
                        <span class="text-xs px-2 py-0.5 rounded-full bg-blue-100 text-blue-800">
                            جارٍ
                        </span>
                    @endif
                </div>
            @empty
                <p class="text-sm text-slate-500 text-center py-8">لم ترسل أي دعوة بعد.</p>
            @endforelse
        </div>
    </div>
@endsection
