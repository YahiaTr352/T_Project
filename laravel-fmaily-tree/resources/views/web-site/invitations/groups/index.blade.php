@extends('web-site.invitations.layout')

@section('title', 'مجموعات الدعوات')

@section('content')
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8 gap-4">
        <div>
            <h1 class="text-3xl font-extrabold">مجموعاتك</h1>
            <p class="text-slate-600">سمِّ المجموعة واختر الأعضاء من شجرة العائلة</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('invitations.groups.create') }}" class="px-5 py-3 rounded-xl bg-emerald-600 text-white font-bold">مجموعة جديدة</a>
            <a href="{{ route('invitations.index') }}" class="px-5 py-3 rounded-xl bg-white border font-medium">الرئيسية</a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        @forelse($groups as $group)
            <div class="glass-card rounded-2xl p-6">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-bold">{{ $group->name }}</h3>
                        <p class="text-sm text-slate-500 mt-1">{{ $group->description ?: 'بدون وصف' }}</p>
                        <p class="text-xs text-emerald-700 font-bold mt-2">{{ $group->persons_count }} شخص</p>
                    </div>
                    <div class="flex gap-2">
                        <a href="{{ route('invitations.groups.edit', $group) }}" class="w-9 h-9 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center"><i class="fas fa-pen"></i></a>
                        <form action="{{ route('invitations.groups.destroy', $group) }}" method="POST" onsubmit="return confirm('حذف هذه المجموعة؟')">
                            @csrf @method('DELETE')
                            <button class="w-9 h-9 rounded-full bg-rose-100 text-rose-700"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="md:col-span-2 glass-card rounded-3xl p-12 text-center">
                <p class="text-slate-500 mb-4">لا توجد مجموعات بعد.</p>
                <a href="{{ route('invitations.groups.create') }}" class="inline-flex px-6 py-3 rounded-xl bg-emerald-600 text-white font-bold">إنشاء مجموعة</a>
            </div>
        @endforelse
    </div>
@endsection
