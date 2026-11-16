@php
    $driver = $provider->driver;
    $presets = $modelPresets[$driver] ?? [];
    $currentChat = old("providers.{$index}.models.chat", data_get($provider->models, 'chat'))
        ?: config("ai.models.{$driver}.chat");
    $currentEmbed = old("providers.{$index}.models.embedding", data_get($provider->models, 'embedding'))
        ?: config("ai.models.{$driver}.embedding");
    $currentTrans = old("providers.{$index}.models.transcription", data_get($provider->models, 'transcription'))
        ?: config("ai.models.{$driver}.transcription");
    $driverIcons = ['openai' => 'fa-bolt', 'anthropic' => 'fa-feather', 'gemini' => 'fa-gem'];
    $driverColors = ['openai' => '#10a37f', 'anthropic' => '#d97706', 'gemini' => '#4285f4'];
@endphp

<div class="ai-provider-card" id="provider-{{ $provider->id }}">
    <div class="ai-provider-head">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-xl d-flex align-items-center justify-content-center text-white font-bold"
                 style="width:44px;height:44px;background:{{ $driverColors[$driver] ?? '#64748b' }};">
                <i class="fas {{ $driverIcons[$driver] ?? 'fa-robot' }}"></i>
            </div>
            <div>
                <div class="font-bold notif-title">{{ $provider->name }}</div>
                <div class="text-[10px] notif-muted">{{ $knownDrivers[$driver] ?? $driver }}</div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3 flex-wrap">
            @if($provider->hasApiKey())
                @include('dashboard.notifications.partials._status-badge', ['status' => 'sent'])
            @else
                @include('dashboard.notifications.partials._status-badge', ['status' => 'failed'])
            @endif
            <div class="custom-control custom-switch">
                <input type="hidden" name="providers[{{ $index }}][is_active]" value="0">
                <input type="checkbox" class="custom-control-input" id="provActive{{ $index }}"
                       name="providers[{{ $index }}][is_active]" value="1"
                       {{ $provider->is_active ? 'checked' : '' }}>
                <label class="custom-control-label text-xs font-bold" for="provActive{{ $index }}">مفعّل</label>
            </div>
        </div>
    </div>

    <div class="ai-provider-body">
        <input type="hidden" name="providers[{{ $index }}][id]" value="{{ $provider->id }}">
        <input type="hidden" name="providers[{{ $index }}][driver]" value="{{ $provider->driver }}">
        <input type="hidden" name="providers[{{ $index }}][name]" value="{{ $provider->name }}">
        <input type="hidden" name="providers[{{ $index }}][sort_order]" value="{{ $provider->sort_order }}">

        <div class="row">
            <div class="col-lg-6 mb-3">
                @include('dashboard.notifications.partials._settings-input', [
                    'name' => "providers[{$index}][api_key]",
                    'id' => "provKey{$index}",
                    'label' => 'مفتاح API',
                    'type' => 'password',
                    'value' => '',
                    'placeholder' => $provider->hasApiKey() ? '******** — اتركه فارغاً للإبقاء على المفتاح الحالي' : 'الصق المفتاح هنا',
                    'help' => 'لا يُعرض بعد الحفظ. الصق مفتاحاً جديداً فقط عند التغيير.',
                    'autocomplete' => 'new-password',
                ])
            </div>
            <div class="col-lg-6 mb-3">
                <label class="text-xs font-bold notif-subtitle mb-1.5 block">موديل الشات</label>
                @if(!empty($presets['chat']))
                    <select name="providers[{{ $index }}][models][chat]" class="dashboard-input py-1.5 text-xs">
                        @if($currentChat && ! array_key_exists($currentChat, $presets['chat']))
                            <option value="{{ $currentChat }}" selected>{{ $currentChat }} — (الموديل الحالي)</option>
                        @endif
                        @foreach($presets['chat'] as $modelId => $modelLabel)
                            <option value="{{ $modelId }}" @selected($currentChat === $modelId)>{{ $modelLabel }}</option>
                        @endforeach
                    </select>
                    <p class="text-[10px] notif-muted m-0 mt-1">اختر من القائمة — لا حاجة لكتابة اسم الموديل يدوياً.</p>
                @else
                    <input type="text" name="providers[{{ $index }}][models][chat]" class="dashboard-input py-1.5 text-xs"
                           value="{{ $currentChat }}" placeholder="اسم الموديل">
                @endif
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2 align-items-center">
            <button type="button" class="btn btn-sm btn-outline-primary ai-test-btn rounded-lg font-bold"
                    data-url="{{ route('dashboard.ai.providers.test', $provider) }}">
                <i class="fas fa-plug mr-1"></i> اختبار الاتصال
            </button>
            <button type="button" class="btn btn-sm btn-link text-xs" data-toggle="collapse"
                    data-target="#provAdvanced{{ $index }}">
                إعدادات متقدمة (Embeddings / تفريغ)
            </button>
        </div>
        <div class="ai-test-result small mt-2" aria-live="polite"></div>

        <div class="collapse mt-3" id="provAdvanced{{ $index }}">
            <div class="p-3 rounded-xl border border-slate-200 dark:border-slate-700">
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <label class="text-xs font-bold notif-subtitle">موديل Embeddings</label>
                        @if(!empty($presets['embedding']))
                            <select name="providers[{{ $index }}][models][embedding]" class="dashboard-input py-1.5 text-xs">
                                @if($currentEmbed && ! array_key_exists($currentEmbed, $presets['embedding']))
                                    <option value="{{ $currentEmbed }}" selected>{{ $currentEmbed }} — (الحالي — قد يكون متوقفاً)</option>
                                @endif
                                @foreach($presets['embedding'] as $modelId => $modelLabel)
                                    <option value="{{ $modelId }}" @selected($currentEmbed === $modelId || ($currentEmbed === 'text-embedding-004' && $modelId === 'gemini-embedding-001'))>{{ $modelLabel }}</option>
                                @endforeach
                            </select>
                        @else
                            <input type="text" class="dashboard-input py-1.5 text-xs text-muted" disabled
                                   placeholder="غير مدعوم لهذا المزود">
                            <input type="hidden" name="providers[{{ $index }}][models][embedding]" value="">
                        @endif
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="text-xs font-bold notif-subtitle">موديل التفريغ الصوتي</label>
                        @if(!empty($presets['transcription']))
                            <select name="providers[{{ $index }}][models][transcription]" class="dashboard-input py-1.5 text-xs">
                                @if($currentTrans && ! array_key_exists($currentTrans, $presets['transcription']))
                                    <option value="{{ $currentTrans }}" selected>{{ $currentTrans }} — (الحالي)</option>
                                @endif
                                @foreach($presets['transcription'] as $modelId => $modelLabel)
                                    <option value="{{ $modelId }}" @selected($currentTrans === $modelId)>{{ $modelLabel }}</option>
                                @endforeach
                            </select>
                        @else
                            <input type="text" class="dashboard-input py-1.5 text-xs text-muted" disabled
                                   placeholder="غير مدعوم — استخدم OpenAI Whisper">
                            <input type="hidden" name="providers[{{ $index }}][models][transcription]" value="">
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
