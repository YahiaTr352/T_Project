@extends('web-site.invitations.layout')

@section('title', 'تعديل المجموعة')

@section('content')
    <div class="max-w-5xl mx-auto">
        <div class="flex items-center justify-between mb-8">
            <h1 class="text-3xl font-extrabold">تعديل: {{ $group->name }}</h1>
            <a href="{{ route('invitations.groups.index') }}" class="px-4 py-2 rounded-xl bg-white border">رجوع</a>
        </div>

        <form action="{{ route('invitations.groups.update', $group) }}" method="POST" class="space-y-6">
            @csrf @method('PUT')
            <div class="glass-card rounded-3xl p-6 space-y-4">
                <div>
                    <label class="block text-sm font-bold mb-2">اسم المجموعة</label>
                    <input type="text" name="name" required value="{{ old('name', $group->name) }}" class="w-full px-4 py-3 border rounded-xl">
                </div>
                <div>
                    <label class="block text-sm font-bold mb-2">الوصف</label>
                    <textarea name="description" rows="3" class="w-full px-4 py-3 border rounded-xl">{{ old('description', $group->description) }}</textarea>
                </div>
            </div>
            <div class="glass-card rounded-3xl p-6">
                <h2 class="font-bold mb-4">الأعضاء من الشجرة</h2>
                @include('web-site.invitations.partials.tree-picker', [
                    'pickerId' => 'groupEditPicker',
                    'selectedIds' => old('person_ids', $group->persons->pluck('id')->all()),
                ])
            </div>
            <button class="w-full py-4 rounded-xl bg-emerald-600 text-white font-bold">حفظ التغييرات</button>
        </form>
    </div>
@endsection

@section('scripts')
    @include('web-site.invitations.partials.tree-picker-script')
@endsection
