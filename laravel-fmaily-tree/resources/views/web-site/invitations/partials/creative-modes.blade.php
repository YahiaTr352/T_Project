<fieldset class="mb-4">
    <legend class="mb-2 font-bold text-sm">طريقة عرض القالب</legend>
    <div class="grid gap-2 sm:grid-cols-2">
        @foreach ([
            'ready' => ['fa-image', 'تُرسل الصورة كما هي، وتظهر الرسالة معها.'],
            'overlay' => ['fa-pen-to-square', 'رتّب النص والمتغيرات التي تظهر داخل الصورة.'],
        ] as $mode => [$icon, $description])
            <label class="cursor-pointer">
                <input type="radio" name="{{ $fieldName }}" value="{{ $mode }}" class="peer sr-only"
                    @checked(($selectedMode ?? null) === $mode) @required($required ?? false)>
                <span class="flex h-full items-start gap-2 rounded-xl border border-slate-200 bg-white p-3 text-sm peer-checked:border-emerald-600 peer-checked:bg-emerald-50 peer-focus-visible:outline peer-focus-visible:outline-emerald-700">
                    <i class="fas {{ $icon }} mt-1 text-emerald-700" aria-hidden="true"></i>
                    <span><strong class="block">{{ \App\Models\CreativeTemplate::MODES[$mode] }}</strong>
                        <span class="block text-xs text-slate-600">{{ $description }}</span></span>
                </span>
            </label>
        @endforeach
    </div>
</fieldset>
