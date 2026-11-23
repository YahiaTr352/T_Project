@extends('web-site.invitations.layout')

@section('title', 'مساحة إبداع')

@section('extra_styles')
    @include('web-site.invitations.partials.typography-fonts')
    [data-creative-layout] .dashboard-input { border: 1px solid #cbd5e1; border-radius: .625rem; padding: .4rem; }
    [data-creative-layout] h2, [data-creative-layout] legend { font-weight: 700; }
    [data-creative-layout] .btn { border: 1px solid #cbd5e1; border-radius: .625rem; padding: .45rem .7rem; }
    [data-creative-layout] .notif-muted { color: #475569; font-size: .875rem; margin-bottom: .5rem; }
@endsection

@section('content')
    <h1 class="mb-6 text-3xl font-extrabold">مساحة إبداع</h1>
    @if (session('creative_success'))
        <p role="status" class="mb-4 rounded-xl bg-emerald-50 p-3 text-emerald-800">{{ session('creative_success') }}</p>
    @endif
    @foreach ($errors->creative->all() as $error)
        <p role="alert" class="mb-3 rounded-xl bg-rose-50 p-3 text-rose-800">{{ $error }}</p>
    @endforeach

    <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
        <form action="{{ route('invitations.creatives.submit') }}" method="POST" enctype="multipart/form-data" class="glass-card space-y-3 rounded-3xl p-5 sm:p-6" id="creativeSubmission">
            @csrf
            <h2 class="text-xl font-bold">قدّم تصميمك</h2>
            @include('web-site.invitations.partials.creative-modes', ['fieldName' => 'design_mode', 'selectedMode' => old('design_mode'), 'required' => true])
            <input type="hidden" value="{{ old('design_mode') }}" data-creative-design-mode>
            <label class="block text-sm font-bold" for="submissionType">نوع الدعوة</label>
            <select id="submissionType" name="invitation_type" required class="w-full rounded-xl border p-2" data-visual-invitation-type>
                <option value="">اختر نوع الدعوة</option>
                @foreach ($templates as $tpl)
                    <option value="{{ $tpl['key'] }}" @selected(old('invitation_type') === $tpl['key'])>{{ $tpl['name'] }}</option>
                @endforeach
            </select>
            <label class="block text-sm font-bold" for="submissionName">اسم التصميم (اختياري)</label>
            <input id="submissionName" name="name" value="{{ old('name') }}" maxlength="255" class="w-full rounded-xl border p-2">
            <label class="block text-sm font-bold" for="submissionImage">صورة التصميم</label>
            <input id="submissionImage" name="image" type="file" accept="image/jpeg,image/png,image/webp,image/gif" required class="w-full" data-visual-file-input>
            <img id="submissionArtwork" class="mx-auto max-h-64 max-w-full rounded-xl" alt="صورة التصميم المختارة" hidden>
            <x-dashboard.visual.text-layout-editor
                :fields-by-type="collect($templates)->mapWithKeys(fn ($tpl) => [$tpl['key'] => \App\Services\Invitations\CreativeTextLayout::editableFields($tpl['key'])])->all()"
            />
            <label class="flex items-start gap-2 text-sm">
                <input type="checkbox" name="publication_consent" value="1" required @checked(old('publication_consent'))>
                <span>أوافق على عرض تصميمي للأعضاء في قوالب الصور إذا اعتمدته الإدارة.</span>
            </label>
            <button type="submit" class="rounded-xl bg-emerald-700 px-4 py-2 font-bold text-white">إرسال للمراجعة</button>
        </form>

        <section aria-labelledby="my-designs">
            <h2 id="my-designs" class="mb-3 text-xl font-bold">تصاميمي</h2>
            <div class="space-y-3">
                @php($typeNames = array_column($templates, 'name', 'key'))
                @forelse ($myCreatives as $design)
                    <article class="glass-card rounded-2xl p-4">
                        <div class="flex items-center gap-3">
                            @if ($design->image_path)
                                <img src="{{ route('invitations.creatives.preview-image', $design->id) }}" alt="{{ $design->name }}" loading="lazy" data-zoomable tabindex="0"
                                    class="h-20 w-20 shrink-0 rounded-xl object-contain">
                            @endif
                            <div class="min-w-0">
                                <h3 class="break-words font-bold">{{ $design->name }}</h3>
                                <p class="text-xs text-slate-600">{{ $typeNames[$design->invitation_type] ?? 'تصميم عام' }} · {{ \App\Models\CreativeTemplate::MODES[$design->designMode()] }}</p>
                                <p class="mt-1 text-sm font-bold">{{ $design->status_label }}</p>
                            </div>
                        </div>
                        @if ($design->rejection_reason)
                            <p class="mt-2 break-words text-sm text-rose-700">{{ $design->rejection_reason }}</p>
                        @endif
                        @if ($design->awaitsPublicationConsent())
                            <form method="POST" action="{{ route('invitations.creatives.consent', $design->id) }}" class="mt-3 space-y-2">
                                @csrf
                                <label class="flex items-start gap-2 text-sm">
                                    <input type="checkbox" name="publication_consent" value="1" required>
                                    <span>أوافق على عرض هذا التصميم في قوالب الصور بعد اعتماده.</span>
                                </label>
                                <button type="submit" class="font-bold text-emerald-700">حفظ الموافقة</button>
                            </form>
                        @endif
                    </article>
                @empty
                    <p class="text-sm text-slate-600">لم ترسل أي تصميم بعد.</p>
                @endforelse
            </div>
            @if ($myCreatives->hasPages())
                <div class="mt-4">{{ $myCreatives->links() }}</div>
            @endif
        </section>
    </div>
@endsection

@section('scripts')
    <script>
        (() => {
            const form = document.getElementById('creativeSubmission');
            const mode = form.querySelector('[data-creative-design-mode]');
            const file = form.querySelector('[data-visual-file-input]');
            const image = document.getElementById('submissionArtwork');
            let imageUrl;
            form.querySelectorAll('[name="design_mode"]').forEach((radio) => radio.addEventListener('change', () => {
                mode.value = radio.value;
                mode.dispatchEvent(new Event('change'));
                image.hidden = !imageUrl || mode.value !== 'ready';
            }));
            file.addEventListener('change', () => {
                if (imageUrl) URL.revokeObjectURL(imageUrl);
                imageUrl = file.files[0] ? URL.createObjectURL(file.files[0]) : null;
                if (imageUrl) image.src = imageUrl;
                else image.removeAttribute('src');
                image.hidden = !imageUrl || mode.value !== 'ready';
            });
        })();
    </script>
    <script src="{{ asset('js/dashboard/creative-text-layout.js') }}?v={{ filemtime(public_path('js/dashboard/creative-text-layout.js')) }}" defer></script>
@endsection
