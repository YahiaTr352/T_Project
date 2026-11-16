@extends('web-site.invitations.layout')

@section('title', 'إرسال دعوة')

@section('content')
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8 gap-4">
        <div>
            <h1 class="text-3xl font-extrabold text-slate-900">إرسال دعوة</h1>
            <p class="text-slate-600 mt-1">اختر مجموعتك أو أشخاصاً من الشجرة، ثم أرسل واتساب و/أو إشعار التطبيق</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('invitations.index') }}" class="px-4 py-2 rounded-xl bg-white border font-medium">الرئيسية</a>
            <a href="{{ route('invitations.groups.index') }}" class="px-4 py-2 rounded-xl bg-white border font-medium">المجموعات</a>
        </div>
    </div>

    <form action="{{ route('invitations.send.submit') }}" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        @csrf
        <div class="lg:col-span-8 space-y-6">
            <div class="glass-card rounded-3xl p-6">
                <h2 class="text-xl font-bold mb-4">١. قنوات الإرسال</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <label class="relative flex flex-col items-center p-5 rounded-2xl border-2 border-emerald-400 bg-white cursor-pointer">
                        <input type="checkbox" name="channels[]" value="whatsapp" checked class="absolute top-4 right-4 w-5 h-5 accent-emerald-600">
                        <i class="fab fa-whatsapp text-4xl text-emerald-500 mb-2"></i>
                        <span class="font-bold">واتساب</span>
                    </label>
                    <label class="relative flex flex-col items-center p-5 rounded-2xl border-2 border-blue-300 bg-white cursor-pointer">
                        <input type="checkbox" name="channels[]" value="push" class="absolute top-4 right-4 w-5 h-5 accent-blue-600">
                        <i class="fas fa-bell text-4xl text-blue-500 mb-2"></i>
                        <span class="font-bold">إشعار التطبيق</span>
                        <span class="text-xs text-slate-500 mt-1">اختياري مع الدعوة</span>
                    </label>
                </div>
            </div>

            <div class="glass-card rounded-3xl p-6">
                <h2 class="text-xl font-bold mb-4">٢. المستلمون</h2>
                <div class="flex flex-wrap gap-3 mb-4">
                    <label class="flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-50 hover:bg-slate-100 cursor-pointer transition">
                        <input type="radio" name="recipient_type" value="group" checked class="accent-emerald-600">
                        <span class="font-semibold text-sm">مجموعة جاهزة</span>
                    </label>
                    <label class="flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-50 hover:bg-slate-100 cursor-pointer transition">
                        <input type="radio" name="recipient_type" value="persons" class="accent-emerald-600">
                        <span class="font-semibold text-sm">أشخاص من الشجرة</span>
                    </label>
                    <label class="flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-50/70 border border-emerald-200 hover:bg-emerald-100/70 cursor-pointer transition">
                        <input type="radio" name="recipient_type" value="external" class="accent-emerald-600">
                        <span class="font-semibold text-sm text-emerald-800 flex items-center gap-1.5">
                            <i class="fab fa-whatsapp text-emerald-600"></i>
                            شخص من خارج العائلة
                        </span>
                    </label>
                </div>
                <div id="wrapperGroup">
                    <select name="group_id" class="w-full px-4 py-3 border rounded-xl">
                        <option value="">— اختر مجموعة —</option>
                        @foreach($groups as $g)
                            <option value="{{ $g->id }}">{{ $g->name }} ({{ $g->persons_count }} شخص)</option>
                        @endforeach
                    </select>
                    @if($groups->isEmpty())
                        <p class="text-sm text-slate-500 mt-2">لا توجد مجموعات. <a href="{{ route('invitations.groups.create') }}" class="text-emerald-600 font-bold">أنشئ مجموعة</a></p>
                    @endif
                </div>
                <div id="wrapperPersons" class="hidden">
                    @include('web-site.invitations.partials.tree-picker', ['pickerId' => 'sendPicker', 'inputName' => 'person_ids[]'])
                </div>
                <div id="wrapperExternal" class="hidden">
                    <div class="p-4 bg-emerald-50/70 border border-emerald-200 rounded-2xl space-y-3">
                        <div class="flex items-center gap-2.5">
                            <span class="w-8 h-8 rounded-xl bg-emerald-600 text-white flex items-center justify-center text-sm shadow-sm">
                                <i class="fab fa-whatsapp"></i>
                            </span>
                            <div>
                                <h3 class="font-bold text-sm text-slate-900">دعوة ضيف من خارج العائلة عبر واتساب</h3>
                                <p class="text-xs text-slate-600 m-0">أدخل اسم الضيف ورقم الواتساب لإرسال الدعوة باسمه الشخصي مباشرة.</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
                            <div>
                                <label for="external_name" class="block text-xs font-bold text-slate-700 mb-1">اسم الضيف / المدعو <span class="text-rose-500">*</span></label>
                                <input type="text" name="external_name" id="external_name" value="{{ old('external_name') }}" class="w-full px-4 py-2.5 bg-white border border-slate-300 rounded-xl text-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500" placeholder="مثال: أبو فهد أو عبدالله الشمري">
                            </div>
                            <div>
                                <label for="external_phone" class="block text-xs font-bold text-slate-700 mb-1">رقم الواتساب <span class="text-rose-500">*</span></label>
                                <div class="flex items-center gap-2" dir="ltr">
                                    <select name="external_country_code" id="external_country_code" class="px-3 py-2.5 bg-white border border-slate-300 rounded-xl text-xs font-mono focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                                        <option value="+966" {{ old('external_country_code', '+966') === '+966' ? 'selected' : '' }}>🇸🇦 +966 (السعودية)</option>
                                        <option value="+971" {{ old('external_country_code') === '+971' ? 'selected' : '' }}>🇦🇪 +971 (الإمارات)</option>
                                        <option value="+965" {{ old('external_country_code') === '+965' ? 'selected' : '' }}>🇰🇼 +965 (الكويت)</option>
                                        <option value="+974" {{ old('external_country_code') === '+974' ? 'selected' : '' }}>🇶🇦 +974 (قطر)</option>
                                        <option value="+973" {{ old('external_country_code') === '+973' ? 'selected' : '' }}>🇧🇭 +973 (البحرين)</option>
                                        <option value="+968" {{ old('external_country_code') === '+968' ? 'selected' : '' }}>🇴🇲 +968 (عُمان)</option>
                                        <option value="+20" {{ old('external_country_code') === '+20' ? 'selected' : '' }}>🇪🇬 +20 (مصر)</option>
                                        <option value="+962" {{ old('external_country_code') === '+962' ? 'selected' : '' }}>🇯🇴 +962 (الأردن)</option>
                                        <option value="+964" {{ old('external_country_code') === '+964' ? 'selected' : '' }}>🇮🇶 +964 (العراق)</option>
                                        <option value="+963" {{ old('external_country_code') === '+963' ? 'selected' : '' }}>🇸🇾 +963 (سوريا)</option>
                                        <option value="+961" {{ old('external_country_code') === '+961' ? 'selected' : '' }}>🇱🇧 +961 (لبنان)</option>
                                        <option value="+967" {{ old('external_country_code') === '+967' ? 'selected' : '' }}>🇾🇪 +967 (اليمن)</option>
                                        <option value="+212" {{ old('external_country_code') === '+212' ? 'selected' : '' }}>🇲🇦 +212 (المغرب)</option>
                                        <option value="+213" {{ old('external_country_code') === '+213' ? 'selected' : '' }}>🇩🇿 +213 (الجزائر)</option>
                                        <option value="+216" {{ old('external_country_code') === '+216' ? 'selected' : '' }}>🇹🇳 +216 (تونس)</option>
                                        <option value="+249" {{ old('external_country_code') === '+249' ? 'selected' : '' }}>🇸🇩 +249 (السودان)</option>
                                        <option value="+90" {{ old('external_country_code') === '+90' ? 'selected' : '' }}>🇹🇷 +90 (تركيا)</option>
                                        <option value="+44" {{ old('external_country_code') === '+44' ? 'selected' : '' }}>🇬🇧 +44 (بريطانيا)</option>
                                        <option value="+1" {{ old('external_country_code') === '+1' ? 'selected' : '' }}>🇺🇸 +1 (أمريكا/كندا)</option>
                                    </select>
                                    <input type="text" name="external_phone" id="external_phone" value="{{ old('external_phone') }}" class="flex-1 px-4 py-2.5 bg-white border border-slate-300 rounded-xl text-sm font-mono text-left focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500" placeholder="501234567">
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-1.5 text-[11px] text-emerald-800 font-medium pt-1">
                            <i class="fas fa-circle-check text-emerald-600"></i>
                            <span>سيتم استبدال {name} باسم الضيف عند إرسال الدعوة عبر واتساب.</span>
                        </div>
                    </div>
                </div>
                <div id="deceasedWrapper">
                    <label class="mt-4 flex items-center justify-between pt-4 border-t">
                        <span class="text-sm font-bold">استبعاد المتوفين</span>
                        <input type="checkbox" name="exclude_deceased" checked class="w-5 h-5 accent-rose-500">
                    </label>
                </div>
            </div>

            <div class="glass-card rounded-3xl p-6">
                <h2 class="text-xl font-bold mb-4">٣. محتوى الرسالة</h2>
                <label class="block text-sm font-bold mb-2">قالب جاهز أو اكتب رسالتك</label>
                <select id="template_select" class="w-full px-4 py-3 border rounded-xl mb-4">
                    <option value="">— رسالة مخصصة —</option>
                    @foreach($templates as $tpl)
                        <option value="{{ $tpl['key'] }}" data-title="{{ $tpl['title'] }}" data-body="{{ $tpl['body'] }}">{{ $tpl['name'] }}</option>
                    @endforeach
                </select>
                <input type="hidden" name="template_key" id="template_key" value="">
                <div id="templateVars" class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4 hidden"></div>
                <input type="text" name="title" id="title" value="{{ old('title') }}" placeholder="عنوان الإشعار" class="w-full px-4 py-3 border rounded-xl mb-3">
                <textarea name="body" id="body" rows="7" placeholder="اكتب نص الرسالة..." class="w-full px-4 py-3 border rounded-xl">{{ old('body') }}</textarea>
                <p class="text-xs text-slate-400 mt-2">المتغيرات: {name} {full_name} {sender_name}</p>
            </div>

                        <div class="glass-card rounded-3xl p-6">
                <h2 class="text-xl font-bold mb-4">مساحة الإبداع (اختياري)</h2>
                @if($backgrounds->isEmpty())
                    <p class="text-sm text-slate-400">لا توجد خلفيات معتمدة بعد.</p>
                @else
                    <input type="hidden" name="background_id" id="background_id" value="{{ old('background_id') }}">
                    <div class="grid grid-cols-3 gap-3">
                        @foreach($backgrounds as $bg)
                            <button type="button" data-bg-id="{{ $bg->id }}" data-bg-url="{{ $bg->image_url }}"
                                class="bg-pick rounded-2xl overflow-hidden border-2 border-transparent focus:outline-none"
                                title="{{ $bg->name }}">
                                <img src="{{ $bg->image_url }}" class="w-full h-24 object-cover" alt="">
                            </button>
                        @endforeach
                    </div>
                    <button type="button" id="bgClear" class="mt-2 text-xs text-slate-400 underline">ازالة الاختيار</button>
                @endif
                    
            </div>

            <script>
            (function () {
                var input = document.getElementById('background_id');
                if (!input) return;
                var sim = document.getElementById('simBg');
                function paint() {
                    document.querySelectorAll('.bg-pick').forEach(function (b) {
                        var on = String(b.getAttribute('data-bg-id')) === String(input.value || '');
                        b.classList.toggle('ring-2', on);
                        b.classList.toggle('ring-emerald-500', on);
                    });
                    var cur = document.querySelector('.bg-pick[data-bg-id="' + (input.value || '') + '"]');
                    if (sim) {
                        if (cur && input.value) { sim.src = cur.getAttribute('data-bg-url'); sim.classList.remove('hidden'); }
                        else { sim.removeAttribute('src'); sim.classList.add('hidden'); }
                    }
                }
                document.querySelectorAll('.bg-pick').forEach(function (b) {
                    b.addEventListener('click', function () {
                        input.value = (input.value === b.getAttribute('data-bg-id')) ? '' : b.getAttribute('data-bg-id');
                        paint();
                        paint();
                    });
                });
                var clear = document.getElementById('bgClear');
                if (clear) clear.addEventListener('click', function () { input.value = ''; paint(); });
                paint();
                    });
                });
                cpaint();
            })();
            </script>

<div class="glass-card rounded-3xl p-6">
                <h2 class="text-xl font-bold mb-4">٤. مرفق اختياري</h2>
                <input type="file" name="media" accept="image/*,video/*,audio/*" class="w-full">
                <input type="hidden" name="media_type" id="media_type" value="">
            </div>

            <button type="submit" class="w-full py-4 rounded-2xl bg-emerald-600 text-white font-bold text-lg hover:bg-emerald-700">
                إرسال الدعوة
            </button>
        </div>

        <div class="lg:col-span-4">
            <div class="glass-card rounded-3xl p-6 sticky top-24">
                <h3 class="font-bold mb-3">معاينة</h3>
                <div class="rounded-[28px] bg-slate-900 p-3 text-white min-h-[280px]">
                    <img id="simBg" class="hidden w-full rounded-2xl mb-2" alt="">
                    <p id="simTitle" class="font-bold text-sm mb-2">تنبيه عائلي</p>
                    <p id="simBody" class="text-sm whitespace-pre-wrap text-slate-200">السلام عليكم...</p>
                </div>
            </div>
        </div>
    </form>
@endsection

@section('scripts')
    @include('web-site.invitations.partials.tree-picker-script')
    <script>
        const templates = @json($templates);
        const senderName = @json($person->full_name ?: $person->first_name);

        document.querySelectorAll('input[name="recipient_type"]').forEach(function (el) {
            el.addEventListener('change', function () {
                const val = this.value;
                document.getElementById('wrapperGroup').classList.toggle('hidden', val !== 'group');
                document.getElementById('wrapperPersons').classList.toggle('hidden', val !== 'persons');
                document.getElementById('wrapperExternal').classList.toggle('hidden', val !== 'external');
                document.getElementById('deceasedWrapper').classList.toggle('hidden', val === 'external');

                if (val === 'external') {
                    const waInput = document.querySelector('input[name="channels[]"][value="whatsapp"]');
                    if (waInput) waInput.checked = true;
                }
                updatePreview();
            });
        });

        function extractVars(body) {
            const found = body.match(/\{[a-z_]+\}/g) || [];
            return [...new Set(found)].filter(function (v) {
                return ['{name}','{first_name}','{full_name}','{sender_name}'].indexOf(v) === -1;
            });
        }

        function renderTemplateVars(body) {
            const box = document.getElementById('templateVars');
            const vars = extractVars(body);
            box.innerHTML = '';
            if (!vars.length) { box.classList.add('hidden'); return; }
            box.classList.remove('hidden');
            vars.forEach(function (v) {
                const key = v.replace(/[{}]/g, '');
                const wrap = document.createElement('div');
                wrap.innerHTML = '<label class="text-xs font-bold text-slate-500 mb-1 block">' + key + '</label>' +
                    '<input name="template_vars[' + key + ']" data-var="' + v + '" class="tpl-var w-full px-3 py-2 border rounded-xl" placeholder="' + v + '">';
                box.appendChild(wrap);
            });
            box.querySelectorAll('.tpl-var').forEach(function (input) {
                input.addEventListener('input', updatePreview);
            });
        }

        function applyVars(text) {
            const rType = document.querySelector('input[name="recipient_type"]:checked')?.value || 'group';
            let targetName = 'أحمد';
            let targetFullName = 'أحمد بن محمد';

            if (rType === 'external') {
                const extName = (document.getElementById('external_name')?.value || '').trim();
                targetName = extName || 'ضيفنا العزيز';
                targetFullName = extName || 'زائرنا الكريم';
            }

            let out = text.replaceAll('{name}', targetName)
                .replaceAll('{first_name}', targetName)
                .replaceAll('{full_name}', targetFullName)
                .replaceAll('{sender_name}', senderName)
                .replaceAll('{{name}}', targetName)
                .replaceAll('{{first_name}}', targetName)
                .replaceAll('{{full_name}}', targetFullName)
                .replaceAll('{{sender_name}}', senderName)
                .replaceAll(':name', targetName)
                .replaceAll(':first_name', targetName)
                .replaceAll(':full_name', targetFullName)
                .replaceAll(':sender_name', senderName);

            out = out.replace(/(مرحباً|أهلاً|عزيزي|عزيزتي|الغالي\/ة|الغالي|الغالية|حياك)\s+name\b/gu, '$1 ' + targetName);
            out = out.replace(/\bname\b/gu, targetName);

            document.querySelectorAll('.tpl-var').forEach(function (input) {
                out = out.replaceAll(input.dataset.var, input.value || input.dataset.var);
            });
            return out;
        }

        function updatePreview() {
            document.getElementById('simTitle').textContent = document.getElementById('title').value || 'تنبيه عائلي';
            document.getElementById('simBody').textContent = applyVars(document.getElementById('body').value || 'السلام عليكم...');
        }

        const extNameInput = document.getElementById('external_name');
        if (extNameInput) {
            extNameInput.addEventListener('input', updatePreview);
        }

        document.getElementById('template_select').addEventListener('change', function () {
            const key = this.value;
            document.getElementById('template_key').value = key;
            if (!key) { renderTemplateVars(document.getElementById('body').value); updatePreview(); return; }
            const tpl = templates.find(function (t) { return t.key === key; });
            if (!tpl) return;
            document.getElementById('title').value = tpl.title || '';
            document.getElementById('body').value = tpl.body || '';
            renderTemplateVars(tpl.body || '');
            updatePreview();
        });

        document.getElementById('title').addEventListener('input', updatePreview);
        document.getElementById('body').addEventListener('input', function () {
            if (!document.getElementById('template_key').value) renderTemplateVars(this.value);
            updatePreview();
        });
        updatePreview();
    </script>
@endsection
