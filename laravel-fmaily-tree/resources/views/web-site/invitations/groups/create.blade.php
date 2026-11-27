@extends('web-site.invitations.layout')

@section('title', 'إنشاء مجموعة')

@section('content')
    <div class="max-w-5xl mx-auto space-y-5">
        <section class="inv-hero">
            <img src="{{ asset('images/invitations/reminder.jpg') }}" alt="إنشاء مجموعة" class="inv-hero-img">
            <div class="inv-hero-overlay">
                <h2 class="text-xl sm:text-2xl font-extrabold text-white">إنشاء مجموعة</h2>
                <p class="text-xs sm:text-sm text-emerald-50/90 mt-1 font-medium">سمِّ المجموعة واختر من الشجرة من لديهم واتساب</p>
            </div>
        </section>

        <form action="{{ route('invitations.groups.store') }}" method="POST" class="space-y-5">
            @csrf
            <div class="inv-card rounded-3xl p-5 sm:p-6 space-y-4">
                <div class="inv-section-heading">
                    <span class="inv-section-title"><span class="inv-dot"></span>بيانات المجموعة</span>
                </div>
                <div>
                    <label class="block text-xs font-bold inv-muted mb-1.5">اسم المجموعة</label>
                    <input type="text" name="name" required value="{{ old('name') }}" class="inv-input w-full px-4 py-3 text-sm font-medium" placeholder="مثال: أعمامي وكبار العائلة">
                </div>
                <div>
                    <label class="block text-xs font-bold inv-muted mb-1.5">الوصف (اختياري)</label>
                    <textarea name="description" rows="3" class="inv-input w-full px-4 py-3 text-sm font-medium" placeholder="مثال: للمناسبات الرسمية">{{ old('description') }}</textarea>
                </div>
            </div>

            <div class="inv-card rounded-3xl p-5 sm:p-6">
                <div class="inv-section-heading">
                    <span class="inv-section-title"><span class="inv-dot"></span>الأعضاء</span>
                </div>
                @include('web-site.invitations.partials.tree-picker', ['pickerId' => 'groupCreatePicker', 'selectedIds' => old('person_ids', [])])
            </div>

            <div class="flex flex-col sm:flex-row gap-3">
                <button type="submit" class="inv-btn-primary flex-1 py-3.5 px-6 text-base cursor-pointer">
                    <i class="fas fa-check-circle ml-1.5" aria-hidden="true"></i> إنشاء المجموعة
                </button>
                <a href="{{ route('invitations.groups.index') }}" class="inv-btn-outline px-6 py-3.5 text-center text-sm">إلغاء</a>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    @include('web-site.invitations.partials.tree-picker-script')
@endsection
