@extends('web-site.invitations.layout')

@section('title', 'إنشاء مجموعة')

@section('content')
    <div class="max-w-5xl mx-auto">
        <h1 class="text-3xl font-extrabold mb-2">إنشاء مجموعة</h1>
        <p class="text-slate-600 mb-8">الاسم والوصف، ثم اختر من الشجرة من لديهم واتساب</p>

        <form action="{{ route('invitations.groups.store') }}" method="POST" class="space-y-6">
            @csrf
            <div class="glass-card rounded-3xl p-6 space-y-4">
                <div>
                    <label class="block text-sm font-bold mb-2">اسم المجموعة</label>
                    <input type="text" name="name" required value="{{ old('name') }}" class="w-full px-4 py-3 border rounded-xl" placeholder="مثال: أعمامي في الرياض">
                </div>
                <div>
                    <label class="block text-sm font-bold mb-2">الوصف (اختياري)</label>
                    <textarea name="description" rows="3" class="w-full px-4 py-3 border rounded-xl">{{ old('description') }}</textarea>
                </div>
            </div>
            <div class="glass-card rounded-3xl p-6">
                <h2 class="font-bold mb-4">الأعضاء</h2>
                @include('web-site.invitations.partials.tree-picker', ['pickerId' => 'groupCreatePicker', 'selectedIds' => old('person_ids', [])])
            </div>
            <div class="flex gap-3">
                <button class="flex-1 py-4 rounded-xl bg-emerald-600 text-white font-bold">إنشاء المجموعة</button>
                <a href="{{ route('invitations.groups.index') }}" class="px-6 py-4 rounded-xl bg-gray-100 font-medium">إلغاء</a>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    @include('web-site.invitations.partials.tree-picker-script')
@endsection
