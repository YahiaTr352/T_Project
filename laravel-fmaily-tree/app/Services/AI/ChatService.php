<?php

namespace App\Services\AI;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Person;
use App\Services\AI\DTO\ChatMessage;
use App\Services\AI\Tools\ToolRegistry;
use App\Support\AiSettings;
use Illuminate\Support\Str;

class ChatService
{
    public function __construct(
        private readonly AiManager $aiManager,
        private readonly AiFeatureGate $gate,
        private readonly AiUsageTracker $usageTracker,
        private readonly RagService $rag,
        private readonly ToolRegistry $toolRegistry,
    ) {
    }

    /**
     * @return array{
     *     conversation: AiConversation,
     *     messages: array<int, mixed>,
     *     citations: array,
     *     viewerPerson: ?Person,
     * }
     */
    private function prepareConversation(
        string $question,
        ?string $conversationUuid,
        ?int $userId,
        ?int $personId,
        ?string $sessionKey,
        string $channel,
        ?string $ip,
        ?string $additionalContext = null,
    ): array {
        $this->gate->authorize('chat', $userId, $ip);

        $question = trim($question);
        if ($question === '') {
            throw new Exceptions\AiException('السؤال فارغ.');
        }

        $conversation = $this->resolveConversation($conversationUuid, $userId, $personId, $sessionKey, $channel);

        // Determine previous topic / subject from previous conversation turns
        $previousMessages = $conversation->messages()
            ->reorder('id', 'desc')
            ->take(8)
            ->get()
            ->reverse()
            ->values();

        $previousSubject = null;
        $lastAssistant = $previousMessages->where('role', 'assistant')->last();
        if ($lastAssistant && !empty($lastAssistant->citations)) {
            $firstPersonCitation = collect($lastAssistant->citations)->firstWhere('type', 'person');
            if ($firstPersonCitation && !empty($firstPersonCitation['title'])) {
                $previousSubject = (string) $firstPersonCitation['title'];
            }
        }

        AiMessage::query()->create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $question,
        ]);

        if (!filled($conversation->title)) {
            $conversation->title = mb_substr($question, 0, 80);
            $conversation->save();
        }

        $rag = $this->rag->buildContext($question, $personId, $previousSubject);

        $viewerPerson = $personId ? Person::query()->withoutGlobalScopes()->find($personId) : null;

        $system = AiSettings::prompt('chat');
        $system .= "\n\nقواعد التعامل مع الهوية والمحادثة:\n";

        if ($viewerPerson) {
            $viewerDetails = $this->rag->formatViewerDetails($viewerPerson);
            $system .= "[المستخدم المتصل حالياً بالدردشة (صاحب الحساب/الجلسة)]:\n" . $viewerDetails . "\n\n";
            $system .= "إرشادات حاسمة بخصوص المستخدم المتصل:\n";
            $system .= "- هذا المستخدم موثق الهوية ومعروف لديك تماماً باسم: {$viewerPerson->full_name}.\n";
            $system .= "- إذا سأل المستخدم بصيغة المتكلم عن نفسه (مثل: «من أنا؟»، «عرفني بنفسي»، «أنا مين؟»، «من هو والدي/أمي/إخواني/أبنائي؟»، «كم عمري؟»، «متى ولدت؟»، «ما نسبي؟»، «ما هو فرعي؟»):\n";
            $system .= "  * أجب عنه مباشرة وبكل ترحيب ودفء واعتزاز وفخر مستنداً إلى بياناته الموضحة أعلاه.\n";
            $system .= "  * اذكر اسمه الكامل، ووالديه، وإخوانه، وتاريخ ميلاده وعمره بدقة.\n";
            $system .= "  * يُمنع منعاً باتاً أن تقول له «أنا لا أعرفك» أو تطلب منه التعريف بنفسه طالما أن بياناته متوفرة أعلاه!\n";
            $system .= "- إذا سأل المستخدم عن عمره أو تاريخ ميلاده، أجب بالسنوات وتاريخ الميلاد الموجود في بياناته أعلاه (حتى وإن كان المستخدم أنثى لأنها تسأل عن نفسها بحق أصيل).\n";
            $system .= "- إذا سأل «ما صلة قرابتي بـ [فلان]» أو «يقربلي إيه فلان»، استند إلى نتائج أداة القرابة الموضحة في السياق.\n\n";
        } else {
            $system .= "[حالة المستخدم]: المستخدم يتصفح حالياً كزائر/ضيف ولم يسجل الدخول بعد ببصمة الوجه أو الواتساب.\n";
            $system .= "- إذا سأل «من أنا؟» أو «كم عمري؟» دون ذكر اسمه، وضّح له بلطف وترحيب أنه يتصفح حالياً كزائر/ضيف غير مسجل، ويمكنه تسجيل الدخول ببصمة الوجه أو الواتساب ليتعرف عليه المساعد تلقائياً ويجيبه عن بياناته ونسبه، أو أن يكتب اسمه للبحث عنه في الشجرة.\n\n";
        }

        $system .= "قواعد انسيابية الحوار واستخدام الأدوات البرمجية الذكية (Dynamic Tools & Function Calling):\n";
        $system .= "- لديك ترسانة أدوات برمجية رسمية متصلة مباشرة بقاعدة بيانات شجرة العائلة. يجب عليك دائماً استدعاء الأدوات للوصول للحقائق والبيانات الموثقة بدلاً من التخمين أو التأليف، وبأي لغة أو لهجة يكتب بها المستخدم:\n";
        $system .= "  1. calculate_kinship: لحساب صلة القرابة الدقيقة والجد المشترك وسلسلة النسب بين شخصين أو بين المستخدم المتصل وشخص آخر.\n";
        $system .= "  2. get_family_members: لجلب أفراد الأسرة (الأبناء والبنات، الزوجات، الزوج، الإخوة والأخوات، الوالدان) للمستخدم المتصل (عبر person_name='me') أو لأي شخص بالاسم (مثل: من هم أبناء فلان؟ من هي زوجة فلان؟).\n";
        $system .= "  3. find_birthday_or_age_peers: للبحث عن أقران السن ومواليد نفس السنة، أو من ولدوا في نفس يوم وشهر الميلاد (Birthday Twins) للمستخدم المتصل أو لشخص محدد.\n";
        $system .= "  4. get_family_age_extremes: لجلب الأكبر سناً أو المعمرين أو الأصغر سناً. إذا سأل المستخدم أو تابع بـ (الأحياء فقط / حي / عايش / ليس ميت)، ضع living_only=true فوراً.\n";
        $system .= "  5. lookup_family_member: للبحث عن سيرة وتاريخ وتفاصيل أي فرد في الشجرة بالاسم.\n";
        $system .= "  6. get_family_statistics: للإحصائيات الشجرية وتعداد الفروع.\n";
        $system .= "  7. search_family_lore: للبحث في قصص ومواقف وتاريخ العائلة الموثق.\n";
        $system .= "- التعامل الذكي والديناميكي مع الرسائل المركبة (تحية أو مجاملة + سؤال أو استفسار):\n";
        $system .= "  إذا تضمنت رسالة المستخدم سلاماً أو تحية أو اطمئناناً مع استفسار أو سؤال (مثل: «الحمد لله، طمني عليك، سؤالي هو من هو أكبر شخص في العائلة؟»):\n";
        $system .= "  * رُد بلباقة واقتضاب شديد على التحية في مستهل كلامك (مثل: «الحمد لله بخير وعافية!»).\n";
        $system .= "  * انتقل فوراً ومباشرة في نفس الرد للإجابة الكاملة والدقيقة والمفصلة عن السؤال المطروح من واقع نتائج الأدوات.\n";
        $system .= "- انسيابية الأسئلة والأسئلة التابعة وتعديل الشروط (Constraint Refinement):\n";
        $system .= "  إذا سأل المستخدم سؤالاً استكمالياً أو تقييدياً في ضوء الرسائل السابقة (مثل: «لا، أقصد الأحياء وليس الأموات»، «حي فقط؟»، «عايش؟»، «أين ولد؟»، «من هم أولاده؟»)، افهم السياق التراكمي ونفذ الأداة المناسبة فوراً (مثلاً: استدعاء get_family_age_extremes مع living_only=true، أو استدعاء get_family_members للشخص المذكور في الدور السابق).\n";
        $system .= "- استقلالية المواضيع الجديدة: إذا انتقل المستخدم إلى موضوع جديد أو شخص مختلف، أجب عن الموضوع الجديد مباشرة دون خلط الأسماء.\n";
        $system .= "- خصوصية نساء وبنات العائلة خط أحمر صارم: يُمنع منعاً باتاً الإفصاح عن أعمار أو تواريخ ميلاد الإناث (النساء/البنات) للغير. الاستثناء الوحيد هو عندما تسأل المرأة المستخدمة المتصلة عن عمرها هي شخصياً.\n";
        $system .= "- لا تفصح عن أرقام هواتف أو وسائل تواصل شخصية.\n";
        $system .= "- التزم حصراً بالبيانات الرسمية لشجرة العائلة، ولا تخمّن أو تؤلف صلات قرابة أو أشخاصاً غير موجودين.\n";

        if ($additionalContext) {
            $system .= "\n\n" . $additionalContext;
        }

        if ($rag['context'] !== '') {
            $system .= "\n\nسياق تمهيدي من شجرة العائلة:\n" . $rag['context'];
        }

        $history = $conversation->messages()
            ->reorder('id', 'desc')
            ->take(8)
            ->get()
            ->reverse()
            ->values();

        $messages = [new ChatMessage('system', $system)];

        $previousTurns = $history->slice(0, -1);
        $currentUserMessage = $history->last();

        foreach ($previousTurns as $msg) {
            if ($msg->role === 'system') {
                continue;
            }
            $messages[] = new ChatMessage($msg->role, $msg->content);
        }

        if ($currentUserMessage && $currentUserMessage->role === 'user') {
            $messages[] = new ChatMessage('user', $currentUserMessage->content);
        }

        $citations = $rag['citations'];
        if ($viewerPerson) {
            $citation = $this->rag->citationFor($viewerPerson);
            if ($citation) {
                $citations = array_values(array_filter(array_unique(array_merge([$citation], $citations), SORT_REGULAR)));
            }
        }

        return [
            'conversation' => $conversation,
            'messages' => $messages,
            'citations' => $citations,
            'viewerPerson' => $viewerPerson,
        ];
    }

    /**
     * @return array{conversation: AiConversation, message: AiMessage, answer: string, citations: array}
     */
    public function ask(
        string $question,
        ?string $conversationUuid = null,
        ?int $userId = null,
        ?int $personId = null,
        ?string $sessionKey = null,
        string $channel = 'web',
        ?string $ip = null,
        ?string $additionalContext = null,
    ): array {
        $prep = $this->prepareConversation(
            $question,
            $conversationUuid,
            $userId,
            $personId,
            $sessionKey,
            $channel,
            $ip,
            $additionalContext
        );

        $conversation = $prep['conversation'];
        $messages = $prep['messages'];
        $citations = $prep['citations'];

        $tools = $this->toolRegistry->getOpenAiToolDefinitions();
        $geminiTools = $this->toolRegistry->getGeminiToolDeclarations();

        $result = $this->aiManager->withFallback(function ($driver) use ($messages, $tools, $geminiTools) {
            $model = $this->aiManager->modelForFeature('chat', $driver);

            $options = [
                'model' => $model,
                'temperature' => 0.3,
                'max_tokens' => 1200,
            ];

            if ($driver->driver() === 'openai') {
                $options['tools'] = $tools;
            } elseif ($driver->driver() === 'gemini') {
                $options['tools'] = $geminiTools;
            }

            return $driver->chat($messages, $options);
        });

        // Agentic Tool Execution Loop
        if (!empty($result->toolCalls)) {
            $messages[] = [
                'role' => 'assistant',
                'content' => $result->content ?: null,
                'tool_calls' => $result->toolCalls,
            ];

            \Log::info("LLM called tools", ['toolCalls' => $result->toolCalls]);
            foreach ($result->toolCalls as $call) {
                $toolName = $call['function']['name'] ?? '';
                $toolArgs = json_decode($call['function']['arguments'] ?? '{}', true) ?: [];

                $toolResult = $this->toolRegistry->execute($toolName, $toolArgs, $personId);
                \Log::info("Tool executed: {$toolName}", ['args' => $toolArgs, 'output' => mb_substr($toolResult['content'], 0, 200)]);

                if (!empty($toolResult['citations'])) {
                    $citations = array_merge($citations, $toolResult['citations']);
                }

                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $call['id'],
                    'content' => $toolResult['content'],
                ];
            }

            // Call model again with tool outputs
            $result = $this->aiManager->withFallback(function ($driver) use ($messages) {
                $model = $this->aiManager->modelForFeature('chat', $driver);

                return $driver->chat($messages, [
                    'model' => $model,
                    'temperature' => 0.3,
                    'max_tokens' => 1200,
                ]);
            });
        }

        $this->usageTracker->recordChat('chat', $result, $userId, $sessionKey, [
            'conversation_id' => $conversation->id,
        ]);

        $citations = array_values(array_unique($citations, SORT_REGULAR));

        $assistant = AiMessage::query()->create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => $result->content,
            'citations' => $citations,
            'meta' => [
                'provider' => $result->provider,
                'model' => $result->model,
            ],
        ]);

        return [
            'conversation' => $conversation->fresh('messages'),
            'message' => $assistant,
            'answer' => $result->content,
            'citations' => $citations,
        ];
    }

    /**
     * Real-time SSE streaming agentic chat execution.
     *
     * @param callable(string $event, array|string $data): void $emitter
     */
    public function askStream(
        string $question,
        ?string $conversationUuid,
        ?int $userId,
        ?int $personId,
        ?string $sessionKey,
        string $channel,
        ?string $ip,
        callable $emitter,
    ): void {
        $prep = $this->prepareConversation(
            $question,
            $conversationUuid,
            $userId,
            $personId,
            $sessionKey,
            $channel,
            $ip
        );

        $conversation = $prep['conversation'];
        $messages = $prep['messages'];
        $citations = $prep['citations'];

        $emitter('thinking', [
            'status' => 'thinking',
            'message' => 'جاري التفكير وتحليل السؤال...',
        ]);

        $tools = $this->toolRegistry->getOpenAiToolDefinitions();
        $geminiTools = $this->toolRegistry->getGeminiToolDeclarations();
        $driver = $this->aiManager->resolve(null, 'chat');
        $model = $this->aiManager->modelForFeature('chat', $driver);

        $streamMethodAvailable = method_exists($driver, 'chatStream');

        if (! $streamMethodAvailable) {
            // Fallback for non-streaming drivers
            $result = $this->ask(
                question: $question,
                conversationUuid: $conversation->uuid,
                userId: $userId,
                personId: $personId,
                sessionKey: $sessionKey,
                channel: $channel,
                ip: $ip
            );
            $emitter('delta', ['delta' => $result['answer']]);
            $emitter('citations', ['citations' => $result['citations']]);
            $emitter('done', [
                'conversation_id' => $result['conversation']->uuid,
                'message_id' => $result['message']->id,
                'answer' => $result['answer'],
            ]);
            return;
        }

        // Pass 1: Execute stream model call with dynamic tools attached
        $pass1Content = '';
        $streamResult = $driver->chatStream(
            $messages,
            [
                'model' => $model,
                'temperature' => 0.3,
                'max_tokens' => 1200,
                'tools' => $driver->driver() === 'gemini' ? $geminiTools : $tools,
            ],
            function (string $delta) use (&$pass1Content) {
                $pass1Content .= $delta;
            }
        );

        $finalAnswer = $pass1Content;
        $toolCalls = $streamResult['tool_calls'] ?? [];

        if (!empty($toolCalls)) {
            $emitter('thinking', [
                'status' => 'searching',
                'message' => 'جاري استرجاع سجلات شجرة العائلة...',
            ]);

            $messages[] = [
                'role' => 'assistant',
                'content' => $pass1Content ?: null,
                'tool_calls' => $toolCalls,
            ];

            foreach ($toolCalls as $call) {
                $toolName = $call['function']['name'] ?? '';
                $toolArgs = json_decode($call['function']['arguments'] ?? '{}', true) ?: [];

                $toolResult = $this->toolRegistry->execute($toolName, $toolArgs, $personId);

                if (!empty($toolResult['citations'])) {
                    $citations = array_merge($citations, $toolResult['citations']);
                }

                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $call['id'],
                    'content' => $toolResult['content'],
                ];
            }

            // Pass 2: Stream final synthesized answer live to client with tool data
            $pass2Content = '';
            $driver->chatStream(
                $messages,
                [
                    'model' => $model,
                    'temperature' => 0.3,
                    'max_tokens' => 1200,
                ],
                function (string $delta) use (&$pass2Content, $emitter) {
                    $pass2Content .= $delta;
                    $emitter('delta', ['delta' => $delta]);
                }
            );

            $finalAnswer = $pass2Content;
        } else {
            // Direct response without tools: emit pass 1 content
            if (filled($pass1Content)) {
                $emitter('delta', ['delta' => $pass1Content]);
            }
        }

        $citations = array_values(array_unique($citations, SORT_REGULAR));

        $assistant = AiMessage::query()->create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => $finalAnswer,
            'citations' => $citations,
            'meta' => [
                'provider' => $driver->driver(),
                'model' => $model,
            ],
        ]);

        $emitter('citations', ['citations' => $citations]);
        $emitter('done', [
            'conversation_id' => $conversation->uuid,
            'message_id' => $assistant->id,
            'answer' => $finalAnswer,
        ]);
    }

    /**
     * Handle voice note transcription then ask the AI model.
     *
     * @return array{conversation: AiConversation, message: AiMessage, answer: string, citations: array, transcription: string}
     */
    public function askVoice(
        string $audioPath,
        ?string $conversationUuid = null,
        ?int $userId = null,
        ?int $personId = null,
        ?string $sessionKey = null,
        string $channel = 'api',
        ?string $ip = null,
    ): array {
        $driver = $this->aiManager->resolve(null, 'transcription');
        $transcription = $driver->transcribe($audioPath, [
            'language' => 'ar',
        ]);

        $transcription = trim($transcription);
        if ($transcription === '') {
            throw new Exceptions\AiException('لم نتمكن من التقاط الصوت بوضوح، يرجى التحدث بوضوح وإعادة المحاولة.');
        }

        $result = $this->ask(
            question: $transcription,
            conversationUuid: $conversationUuid,
            userId: $userId,
            personId: $personId,
            sessionKey: $sessionKey,
            channel: $channel,
            ip: $ip,
        );

        $result['transcription'] = $transcription;

        return $result;
    }

    public function suggestions(): array
    {
        return [
            'من هو أكبر فرد مسجّل في الشجرة؟',
            'احكِ لي عن قصة من قصص العائلة',
            'إيه صلة القرابة بين شخصين؟',
            'كم عدد أفراد العائلة المسجلين؟',
            'مين اتولد في نفس جيلي تقريباً؟',
        ];
    }

    private function resolveConversation(
        ?string $uuid,
        ?int $userId,
        ?int $personId,
        ?string $sessionKey,
        string $channel,
    ): AiConversation {
        if ($uuid) {
            $existing = AiConversation::query()->where('uuid', $uuid)->first();
            if ($existing) {
                if ($personId && $existing->person_id !== $personId) {
                    $existing->person_id = $personId;
                    $existing->save();
                }
                if ($userId && $existing->user_id !== $userId) {
                    $existing->user_id = $userId;
                    $existing->save();
                }
                return $existing;
            }
        }

        return AiConversation::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $userId,
            'person_id' => $personId,
            'session_key' => $sessionKey,
            'channel' => $channel,
        ]);
    }
}
