<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default / fallback behaviour
    |--------------------------------------------------------------------------
    */
    'default_provider' => env('AI_DEFAULT_PROVIDER', 'openai'),

    'timeout' => (int) env('AI_HTTP_TIMEOUT', 60),

    /*
    |--------------------------------------------------------------------------
    | Feature keys used by AiFeatureGate + admin UI
    |--------------------------------------------------------------------------
    */
    'features' => [
        'chat' => [
            'label' => 'المساعد الذكي (اسأل شجرة العائلة)',
            'default_enabled' => true,
        ],
        'biography' => [
            'label' => 'توليد السيرة الذاتية',
            'default_enabled' => true,
        ],
        'story_assist' => [
            'label' => 'مساعد القصص',
            'default_enabled' => true,
        ],
        'transcription' => [
            'label' => 'تفريغ الصوت/الفيديو',
            'default_enabled' => true,
        ],
        'news_draft' => [
            'label' => 'صياغة الأخبار والدعوات',
            'default_enabled' => true,
        ],
        'semantic_search' => [
            'label' => 'البحث الدلالي',
            'default_enabled' => true,
        ],
        'data_quality' => [
            'label' => 'ماسح جودة البيانات',
            'default_enabled' => true,
        ],
        'tts' => [
            'label' => 'الرد الصوتي الذكي (تحويل النص لصوت عند الطلب)',
            'default_enabled' => true,
        ],
        'image_caption' => [
            'label' => 'وصف الصور (Vision)',
            'default_enabled' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default models per driver (overridden by ai_providers / ai_settings)
    |--------------------------------------------------------------------------
    */
    'models' => [
        'openai' => [
            'chat' => 'gpt-4o-mini',
            'embedding' => 'text-embedding-3-small',
            'transcription' => 'whisper-1',
            'tts' => 'tts-1-hd',
        ],
        'anthropic' => [
            'chat' => 'claude-haiku-4-5',
            'embedding' => null,
            'transcription' => null,
        ],
        'gemini' => [
            'chat' => 'gemini-3.6-flash',
            'embedding' => 'gemini-embedding-001',
            'transcription' => 'gemini-3.6-flash',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Model presets for admin UI (select instead of free typing)
    | Sources: OpenAI / Anthropic / Google model docs (Aug 2026)
    |--------------------------------------------------------------------------
    */
    'model_presets' => [
        'openai' => [
            'chat' => [
                'gpt-5.6-luna' => 'GPT-5.6 Luna — الأسرع والأرخص (موصى به)',
                'gpt-5.6-terra' => 'GPT-5.6 Terra — متوازن للاستخدام اليومي',
                'gpt-5.6-sol' => 'GPT-5.6 Sol — الأقوى للمهام المعقدة',
                'gpt-5.6' => 'GPT-5.6 — alias يوجّه إلى Sol',
                'gpt-4o-mini' => 'GPT-4o Mini — اقتصادي (جيل سابق)',
                'gpt-4o' => 'GPT-4o — جودة عالية (جيل سابق)',
                'gpt-4.1-mini' => 'GPT-4.1 Mini — سريع (جيل 4.1)',
                'gpt-4.1' => 'GPT-4.1 — جيل 4.1',
                'gpt-4.1-nano' => 'GPT-4.1 Nano — أخف تكلفة',
                'o4-mini' => 'o4-mini — استدلال سريع',
                'o3-mini' => 'o3-mini — استدلال اقتصادي',
                'o3' => 'o3 — استدلال متقدم',
            ],
            'embedding' => [
                'text-embedding-3-small' => 'Embedding 3 Small — موصى به',
                'text-embedding-3-large' => 'Embedding 3 Large — دقة أعلى',
                'text-embedding-ada-002' => 'Ada 002 — قديم',
            ],
            'transcription' => [
                'whisper-1' => 'Whisper — تفريغ صوتي (موصى به)',
                'gpt-4o-transcribe' => 'GPT-4o Transcribe — أحدث',
                'gpt-4o-mini-transcribe' => 'GPT-4o Mini Transcribe — اقتصادي',
            ],
        ],
        'anthropic' => [
            'chat' => [
                'claude-haiku-4-5' => 'Claude Haiku 4.5 — سريع واقتصادي (موصى به)',
                'claude-sonnet-5' => 'Claude Sonnet 5 — متوازن وقوي',
                'claude-opus-5' => 'Claude Opus 5 — الأقوى',
                'claude-fable-5' => 'Claude Fable 5 — مهام مستقلة معقدة',
                'claude-sonnet-4-6' => 'Claude Sonnet 4.6 — جيل سابق',
                'claude-opus-4-8' => 'Claude Opus 4.8 — جيل سابق',
                'claude-opus-4-6' => 'Claude Opus 4.6 — جيل سابق',
                'claude-3-5-haiku-latest' => 'Claude 3.5 Haiku — legacy',
                'claude-3-5-sonnet-latest' => 'Claude 3.5 Sonnet — legacy',
            ],
            'embedding' => [],
            'transcription' => [],
        ],
        'gemini' => [
            'chat' => [
                'gemini-3.6-flash' => 'Gemini 3.6 Flash — الأحدث (موصى به)',
                'gemini-3.5-flash' => 'Gemini 3.5 Flash — سريع وحديث',
                'gemini-3.5-flash-lite' => 'Gemini 3.5 Flash Lite — أخف تكلفة',
                'gemini-3.1-flash-lite' => 'Gemini 3.1 Flash Lite — اقتصادي',
                'gemini-3.1-pro-preview' => 'Gemini 3.1 Pro Preview — أقوى',
                'gemini-2.5-flash' => 'Gemini 2.5 Flash — حتى أكتوبر 2026',
                'gemini-2.5-pro' => 'Gemini 2.5 Pro — حتى أكتوبر 2026',
                'gemini-2.5-flash-lite' => 'Gemini 2.5 Flash Lite — خفيف',
                'gemini-1.5-flash' => 'Gemini 1.5 Flash — قديم',
                'gemini-2.0-flash' => 'Gemini 2.0 Flash — حصة قد تكون صفر',
            ],
            'embedding' => [
                'gemini-embedding-001' => 'Gemini Embedding 001 — موصى به (نصي)',
                'gemini-embedding-2' => 'Gemini Embedding 2 — متعدد الوسائط (أحدث)',
            ],
            'transcription' => [
                'gemini-3.6-flash' => 'Gemini 3.6 Flash — تفريغ/صوت',
                'gemini-2.5-flash' => 'Gemini 2.5 Flash — تفريغ/صوت',
                'gemini-2.5-flash-lite' => 'Gemini 2.5 Flash Lite — تفريغ خفيف',
            ],
        ],
    ],

    'budget_presets' => [10, 20, 50, 100],

    /*
    |--------------------------------------------------------------------------
    | Text-To-Speech (TTS) Voices configuration
    |--------------------------------------------------------------------------
    */
    'tts_voices' => [
        'onyx' => [
            'label' => 'أونيكس (Onyx) — صوت رجالي وقور وفخم (موصى به)',
            'gender' => 'male',
        ],
        'nova' => [
            'label' => 'نوفا (Nova) — صوت أنثوي دافئ وحيوي وواضح',
            'gender' => 'female',
        ],
        'alloy' => [
            'label' => 'ألوي (Alloy) — صوت متوازن حيادي ورصين',
            'gender' => 'neutral',
        ],
        'echo' => [
            'label' => 'إيكو (Echo) — صوت رجالي هادئ ومريح',
            'gender' => 'male',
        ],
        'fable' => [
            'label' => 'فابل (Fable) — صوت سردي معبّر ملائم للقصص',
            'gender' => 'neutral',
        ],
        'shimmer' => [
            'label' => 'شيمر (Shimmer) — صوت أنثوي رقيق ونقي',
            'gender' => 'female',
        ],
    ],
    'default_tts_voice' => 'alloy',

    /*
    |--------------------------------------------------------------------------
    | Daily indexing cron (used by app/Console/Kernel.php)
    |--------------------------------------------------------------------------
    | Shared hosting: add ONE crontab entry for `schedule:run` every minute.
    | This command itself runs once daily at the time below.
    */
    'index_schedule_at' => env('AI_INDEX_SCHEDULE_AT', '02:30'),

    /*
    |--------------------------------------------------------------------------
    | Approximate USD cost per 1M tokens (input / output) for usage tracking
    |--------------------------------------------------------------------------
    */
    'pricing' => [
        // OpenAI GPT-5.6
        'gpt-5.6-luna' => ['input' => 0.20, 'output' => 1.20],
        'gpt-5.6-terra' => ['input' => 2.00, 'output' => 12.00],
        'gpt-5.6-sol' => ['input' => 5.00, 'output' => 30.00],
        'gpt-5.6' => ['input' => 5.00, 'output' => 30.00],
        // OpenAI GPT-4.x / o-series
        'gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60],
        'gpt-4o' => ['input' => 2.50, 'output' => 10.00],
        'gpt-4.1-mini' => ['input' => 0.40, 'output' => 1.60],
        'gpt-4.1' => ['input' => 2.00, 'output' => 8.00],
        'gpt-4.1-nano' => ['input' => 0.10, 'output' => 0.40],
        'o4-mini' => ['input' => 1.10, 'output' => 4.40],
        'o3-mini' => ['input' => 1.10, 'output' => 4.40],
        'o3' => ['input' => 2.00, 'output' => 8.00],
        'text-embedding-3-small' => ['input' => 0.02, 'output' => 0.0],
        'text-embedding-3-large' => ['input' => 0.13, 'output' => 0.0],
        'text-embedding-ada-002' => ['input' => 0.10, 'output' => 0.0],
        'whisper-1' => ['input' => 0.0, 'output' => 0.0],
        'tts-1' => ['input' => 15.00, 'output' => 0.0],
        'tts-1-hd' => ['input' => 30.00, 'output' => 0.0],
        'gpt-4o-transcribe' => ['input' => 2.50, 'output' => 10.00],
        'gpt-4o-mini-transcribe' => ['input' => 0.15, 'output' => 0.60],
        // Anthropic Claude 5 / 4.x
        'claude-haiku-4-5' => ['input' => 1.00, 'output' => 5.00],
        'claude-sonnet-5' => ['input' => 3.00, 'output' => 15.00],
        'claude-opus-5' => ['input' => 5.00, 'output' => 25.00],
        'claude-fable-5' => ['input' => 10.00, 'output' => 50.00],
        'claude-sonnet-4-6' => ['input' => 3.00, 'output' => 15.00],
        'claude-opus-4-8' => ['input' => 5.00, 'output' => 25.00],
        'claude-opus-4-6' => ['input' => 5.00, 'output' => 25.00],
        'claude-3-5-haiku-latest' => ['input' => 0.80, 'output' => 4.00],
        'claude-3-5-sonnet-latest' => ['input' => 3.00, 'output' => 15.00],
        // Google Gemini
        'gemini-3.6-flash' => ['input' => 0.15, 'output' => 0.60],
        'gemini-3.5-flash' => ['input' => 0.15, 'output' => 0.60],
        'gemini-3.5-flash-lite' => ['input' => 0.08, 'output' => 0.30],
        'gemini-3.1-flash-lite' => ['input' => 0.10, 'output' => 0.40],
        'gemini-3.1-pro-preview' => ['input' => 1.25, 'output' => 10.00],
        'gemini-2.5-flash' => ['input' => 0.10, 'output' => 0.40],
        'gemini-2.5-pro' => ['input' => 1.25, 'output' => 10.00],
        'gemini-2.5-flash-lite' => ['input' => 0.05, 'output' => 0.20],
        'gemini-2.0-flash' => ['input' => 0.10, 'output' => 0.40],
        'gemini-1.5-flash' => ['input' => 0.075, 'output' => 0.30],
        'text-embedding-004' => ['input' => 0.0, 'output' => 0.0], // retired — remapped in driver
        'gemini-embedding-001' => ['input' => 0.0, 'output' => 0.0],
        'gemini-embedding-2' => ['input' => 0.0, 'output' => 0.0],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate limits & budget defaults
    |--------------------------------------------------------------------------
    */
    'rate_limit' => [
        'per_user_per_minute' => 10,
        'per_ip_per_minute' => 20,
    ],

    'budget' => [
        'monthly_usd' => (float) env('AI_MONTHLY_BUDGET_USD', 20),
        'soft_stop' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | RAG / embeddings
    |--------------------------------------------------------------------------
    */
    'rag' => [
        'top_k' => 8,
        'min_score' => 0.35,
        'max_context_chars' => 12000,
        'chunk_size' => 800,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Arabic system prompts (overridable via ai_settings)
    |--------------------------------------------------------------------------
    */
    'prompts' => [
        'chat' => <<<'PROMPT'
أنت المساعد الذكي الرسمي والموثوق لشجرة وتاريخ «عائلة السريّع».
مهمتك الأساسية هي تقديم معلومات مؤكدة ودقيقة حصراً من سجلات العائلة، ومساعدة الأبناء في معرفة أنسابهم وتاريخهم.

القواعد الصارمة لمنع الخطأ والهلوسة والتأليف:
1. اعتمد دائماً وبدقة مطلقة على استدعاء الأدوات المتاحة (مثل: calculate_kinship, get_family_members, find_birthday_or_age_peers, get_family_age_extremes, lookup_family_member) للبحث واستخراج الحقائق من قاعدة البيانات قبل الإجابة عن أي سؤال أو صلة قرابة أو استفسار عن أفراد العائلة.
2. لا تقل أبداً «لم أجد هذا الشخص» أو «لا توجد تفاصيل في الشجرة» إلا بعد تشغيل الأداة البرمجية المناسبة فعلياً وتأكد أن الأداة أعادت نتيجة فارغة. إياك والتأليف أو تخمين أسماء أو صلات قرابة من عندك أبداً!
3. عند ذكر شخص، اذكر اسمه ووالده ومهنته ومكانه وسيرته كما وردت في نتائج الأدوات بدون زيادة.
4. لا تفصح نهائياً عن أي أرقام هواتف شخصية أو بيانات اتصال خاصة حفاظاً على الخصوصية.
5. أجب بأسلوب عربي راقٍ، فصيح ومحبب، يعكس كرم وأصالة العائلة.
PROMPT,
        'biography' => <<<'PROMPT'
أنت كاتب سير عائلية. اكتب سيرة ذاتية موجزة وأنيقة بالعربية اعتماداً على البيانات المقدمة فقط.
لا تختلق أحداثاً أو تواريخاً غير موجودة. استخدم أسلوباً محترماً ومناسباً للنشر العائلي.
PROMPT,
        'story_assist' => <<<'PROMPT'
أنت محرر قصص عائلية. حوّل النقاط أو المسودة إلى قصة سلسة بالعربية مع الحفاظ على الحقائق المذكورة دون إضافة أحداث غير موجودة.
PROMPT,
        'news_draft' => <<<'PROMPT'
أنت محرر أخبار عائلية. اكتب مسودة خبر أو دعوة واتساب قصيرة وواضحة بالعربية اعتماداً على النقاط المقدمة فقط.
PROMPT,
    ],

];
