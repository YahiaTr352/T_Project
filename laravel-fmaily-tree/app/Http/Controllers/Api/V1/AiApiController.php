<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Services\AI\ChatService;
use App\Services\AI\TtsService;
use App\Models\AiMessage;
use App\Services\AI\Exceptions\AiException;
use App\Services\AI\SemanticSearchService;
use App\Services\KinshipCalculatorService;
use App\Services\FaceRecognitionServiceInterface;
use App\Models\Person;
use App\Services\SiteMobileAccessToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use Symfony\Component\HttpFoundation\StreamedResponse;

class AiApiController extends Controller
{
    public function __construct(
        private readonly ChatService $chatService,
        private readonly SemanticSearchService $searchService,
        private readonly KinshipCalculatorService $kinshipCalculator,
        private readonly TtsService $ttsService,
        private readonly FaceRecognitionServiceInterface $faceService,
    ) {
    }

    public function chat(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessToken($request)) {
            return $deny;
        }

        $validated = $request->validate([
            'message' => 'required|string|max:2000',
            'conversation_id' => 'nullable|uuid',
            'person_id' => 'nullable|integer',
        ]);

        try {
            $person = $this->kinshipCalculator->resolvePersonForUser($request->user(), $validated['person_id'] ?? null, $request);

            $result = $this->chatService->ask(
                question: $validated['message'],
                conversationUuid: $validated['conversation_id'] ?? null,
                userId: $request->user()?->id,
                personId: $person?->id,
                sessionKey: $this->sessionKey($request),
                channel: 'api',
                ip: $request->ip(),
            );

            return response()->json([
                'ok' => true,
                'conversation_id' => $result['conversation']->uuid,
                'message_id' => $result['message']->id,
                'answer' => $result['answer'],
                'citations' => $result['citations'],
            ]);
        } catch (AiException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        } catch (\Illuminate\Database\QueryException $e) {
            \Log::error('AI API chat database error', ['message' => $e->getMessage()]);
            return response()->json([
                'ok' => false,
                'message' => 'تعذر الاتصال بقاعدة البيانات حالياً.',
            ], 503);
        } catch (\Throwable $e) {
            \Log::error('AI API chat error', ['message' => $e->getMessage()]);
            return response()->json(['ok' => false, 'message' => 'حدث خطأ غير متوقع.'], 500);
        }
    }

    public function chatStream(Request $request): StreamedResponse|JsonResponse
    {
        if ($deny = $this->denyUnlessToken($request)) {
            return $deny;
        }

        $validated = $request->validate([
            'message' => 'required|string|max:2000',
            'conversation_id' => 'nullable|uuid',
            'person_id' => 'nullable|integer',
        ]);

        $user = $request->user();
        $person = $this->kinshipCalculator->resolvePersonForUser($user, $validated['person_id'] ?? null, $request);
        $sessionKey = $this->sessionKey($request);
        $ip = $request->ip();

        return response()->stream(function () use ($validated, $user, $person, $sessionKey, $ip) {
            $sendSse = function (string $event, mixed $data): void {
                echo "event: {$event}\n";
                echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            };

            try {
                $this->chatService->askStream(
                    question: $validated['message'],
                    conversationUuid: $validated['conversation_id'] ?? null,
                    userId: $user?->id,
                    personId: $person?->id,
                    sessionKey: $sessionKey,
                    channel: 'api',
                    ip: $ip,
                    emitter: $sendSse,
                );
            } catch (AiException $e) {
                $sendSse('error', ['message' => $e->getMessage()]);
            } catch (\Throwable $e) {
                \Log::error('AI API chatStream error', ['message' => $e->getMessage()]);
                $sendSse('error', ['message' => 'حدث خطأ غير متوقع أثناء معالجة السؤال.']);
            } finally {
                echo "event: end\ndata: [DONE]\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }


    public function voiceChat(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessToken($request)) {
            return $deny;
        }

        $request->validate([
            'audio' => 'required|file|max:25600',
            'conversation_id' => 'nullable|uuid',
            'person_id' => 'nullable|integer',
        ]);

        try {
            $file = $request->file('audio');
            $allowedExtensions = ['mp3', 'm4a', 'mp4', 'wav', 'aac', 'ogg', 'webm', '3gp', '3gpp'];
            $ext = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'm4a');
            if (!in_array($ext, $allowedExtensions)) {
                return response()->json(['ok' => false, 'message' => 'نوع الملف الصوتي غير مدعوم.'], 422);
            }
            $person = $this->kinshipCalculator->resolvePersonForUser(
                $request->user(),
                $request->input('person_id') ? (int) $request->input('person_id') : null,
                $request
            );

            $result = $this->chatService->askVoice(
                audioPath: $file->getRealPath(),
                conversationUuid: $request->input('conversation_id'),
                userId: $request->user()?->id,
                personId: $person?->id,
                sessionKey: $this->sessionKey($request),
                channel: 'api',
                ip: $request->ip(),
            );

            return response()->json([
                'ok' => true,
                'transcription' => $result['transcription'],
                'conversation_id' => $result['conversation']->uuid,
                'message_id' => $result['message']->id,
                'answer' => $result['answer'],
                'citations' => $result['citations'],
            ]);
        } catch (AiException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            \Log::error('AI API voice chat error', ['message' => $e->getMessage()]);
            return response()->json(['ok' => false, 'message' => 'تعذر معالجة الرسالة الصوتية حالياً.'], 500);
        }
    }

    public function photoChat(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessToken($request)) {
            return $deny;
        }

        // Support base64 image data payload if provided
        $base64Data = $request->input('image_base64') ?? ($request->isJson() && is_string($request->input('image')) ? $request->input('image') : null);
        if ($base64Data && is_string($base64Data)) {
            $ext = 'jpg';
            if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $type)) {
                $base64Data = substr($base64Data, strpos($base64Data, ',') + 1);
                $ext = strtolower($type[1]);
                if ($ext === 'jpeg') {
                    $ext = 'jpg';
                }
            }

            $decoded = base64_decode($base64Data, true);
            if ($decoded !== false && strlen($decoded) > 0) {
                $tmpFilePath = tempnam(sys_get_temp_dir(), 'face_') . '.' . $ext;
                file_put_contents($tmpFilePath, $decoded);

                $file = new \Illuminate\Http\UploadedFile(
                    $tmpFilePath,
                    'search.' . $ext,
                    'image/' . ($ext === 'jpg' ? 'jpeg' : $ext),
                    null,
                    true
                );

                $request->files->set('image', $file);
            }
        }

        $request->validate([
            'image' => 'required|image|max:10240',
            'message' => 'nullable|string|max:1000',
            'conversation_id' => 'nullable|uuid',
            'person_id' => 'nullable|integer',
        ]);

        try {
            $imageFile = $request->file('image');
            $userQuestion = trim((string) $request->input('message', ''));
            $conversationUuid = $request->input('conversation_id');
            
            $person = null;
            try {
                $person = $this->kinshipCalculator->resolvePersonForUser(
                    $request->user(),
                    $request->input('person_id') ? (int) $request->input('person_id') : null,
                    $request
                );
            } catch (\Throwable $e) {
                \Log::warning('Kinship resolve error in photo-chat: ' . $e->getMessage());
            }

            // 1. البحث في مجموعة وجوه شجرة العائلة عبر AWS Rekognition بحماية كاملة
            $matches = [];
            try {
                if ($imageFile && file_exists($imageFile->getRealPath())) {
                    $matches = $this->faceService->searchByImage($imageFile->getRealPath(), 75.0);
                }
            } catch (\Throwable $rekognitionErr) {
                \Log::warning('AWS Rekognition photo search error: ' . $rekognitionErr->getMessage());
                $matches = [];
            }

            $matchedPerson = null;
            $similarity = 0.0;
            $kinshipText = null;

            if (!empty($matches)) {
                $bestMatch = collect($matches)->sortByDesc('similarity')->first();
                $personIdFound = (int) ($bestMatch['person_id'] ?? 0);
                if ($personIdFound > 0) {
                    $matchedPerson = Person::find($personIdFound);
                    $similarity = round((float) ($bestMatch['similarity'] ?? 0), 1);

                    if ($matchedPerson && $person && $matchedPerson->id !== $person->id) {
                        try {
                            $kinshipResult = $this->kinshipCalculator->calculateKinship($person->id, $matchedPerson->id);
                            if ($kinshipResult && !empty($kinshipResult->relationship)) {
                                if (is_array($kinshipResult->relationship)) {
                                    $kinshipText = (string) ($kinshipResult->relationship['title'] ?? $kinshipResult->relationship['summary_text'] ?? '');
                                } elseif (is_string($kinshipResult->relationship)) {
                                    $kinshipText = $kinshipResult->relationship;
                                }
                            }
                        } catch (\Throwable $e) {
                            \Log::warning('Kinship calculation error in AI photo-chat: ' . $e->getMessage());
                            $kinshipText = null;
                        }
                    }
                }
            }

            // 2. صياغة السياق الإضافي وسؤال العرض النظيف
            if ($matchedPerson) {
                $displayQuestion = $userQuestion ?: "من هو {$matchedPerson->full_name} في شجرة العائلة؟";

                $additionalContext = "[سياق مؤكد ورسمي من نظام التعرف البيومتري AWS Rekognition وقاعدة بيانات الشجرة]:\n";
                $additionalContext .= "- تم التعرف على وجه الشخص في شجرة عائلة السريّع بنسبة تطابق ({$similarity}%).\n";
                $additionalContext .= "- معرف الشخص في الشجرة (ID): #{$matchedPerson->id}.\n";
                $additionalContext .= "- الاسم الكامل: {$matchedPerson->full_name}.\n";
                if ($matchedPerson->parent_id) {
                    $father = Person::find($matchedPerson->parent_id);
                    if ($father) {
                        $additionalContext .= "- الأب: {$father->full_name}.\n";
                    }
                }
                if ($matchedPerson->mother_id) {
                    $mother = Person::find($matchedPerson->mother_id);
                    if ($mother) {
                        $additionalContext .= "- الأم: {$mother->full_name}.\n";
                    }
                }
                if ($matchedPerson->birth_date) {
                    $additionalContext .= "- تاريخ الميلاد: " . $matchedPerson->birth_date->format('Y-m-d') . ".\n";
                }
                if ($matchedPerson->birth_place) {
                    $additionalContext .= "- مكان الميلاد: {$matchedPerson->birth_place}.\n";
                }
                if ($matchedPerson->death_date) {
                    $additionalContext .= "- تاريخ الوفاة: " . $matchedPerson->death_date->format('Y-m-d') . " (رحمه الله).\n";
                }
                if ($matchedPerson->occupation) {
                    $additionalContext .= "- المهنة / النشاط: {$matchedPerson->occupation}.\n";
                }
                if (!empty($kinshipText)) {
                    $additionalContext .= "- صلة القرابة بالسائل: {$kinshipText}.\n";
                }
                if (!empty($matchedPerson->biography) && is_string($matchedPerson->biography)) {
                    $additionalContext .= "- نبذة تعريفية: {$matchedPerson->biography}.\n";
                }
                $additionalContext .= "\nتعليمات صارمة للذكاء الاصطناعي: هذا الشخص مسجل ومؤكد 100% في شجرة عائلة السريّع برقم معرف #{$matchedPerson->id}. أجب بفخر واعتزاز ودفء عن مكانته في عائلة السريّع وعن نسبه ومعلوماته أعلاه، ولا تقل أبداً «لم أجد هذا الشخص» لأنه موثق ورسمي ومطابق بيومترياً.";
            } else {
                $displayQuestion = $userQuestion ?: "من هذا الشخص؟";

                $additionalContext = "[سياق التعرف على الوجه عبر AWS Rekognition]: تم فحص ملامح الوجه في قاعدة بيانات وصور أفراد عائلة السريّع ولكن لم يُعثر على تطابق مع أي فرد مسجل في الشجرة حالياً.\n";
                $additionalContext .= "المطلوب: وضّح للمستخدم بلطف واعتزاز أنه لم يتم التعرف على هذا الوجه ضمن أفراد شجرة عائلة السريّع المسجلين حالياً، واقترح عليه التأكد من وضوح زاوية الصورة أو البحث بالاسم ثلاثياً أو رباعياً.";
            }

            // 3. استدعاء ChatService مع حماية تامة ضد أية أخطاء في الـ AI LLM
            $personMatch = null;
            if ($matchedPerson) {
                $personMatch = [
                    'id' => $matchedPerson->id,
                    'full_name' => $matchedPerson->full_name,
                    'first_name' => $matchedPerson->first_name,
                    'avatar' => $matchedPerson->avatar,
                    'similarity' => $similarity,
                    'kinship' => $kinshipText,
                    'branch' => null,
                ];
            }

            try {
                $result = $this->chatService->ask(
                    question: $displayQuestion,
                    conversationUuid: $conversationUuid,
                    userId: $request->user()?->id,
                    personId: $matchedPerson ? $matchedPerson->id : $person?->id,
                    sessionKey: $this->sessionKey($request),
                    channel: 'api',
                    ip: $request->ip(),
                    additionalContext: $additionalContext,
                );

                $citations = $result['citations'] ?? [];

                if ($matchedPerson) {
                    $hasCitation = collect($citations)->contains(fn ($c) => ($c['type'] ?? '') === 'person' && ($c['id'] ?? 0) === $matchedPerson->id);
                    if (!$hasCitation) {
                        $citations[] = [
                            'type' => 'person',
                            'id' => $matchedPerson->id,
                            'title' => $matchedPerson->full_name,
                            'url' => null,
                        ];
                    }
                }

                return response()->json([
                    'ok' => true,
                    'conversation_id' => $result['conversation']->uuid,
                    'message_id' => $result['message']->id,
                    'answer' => $result['answer'],
                    'citations' => $citations,
                    'person_match' => $personMatch,
                ]);
            } catch (\Throwable $aiException) {
                \Log::warning('ChatService ask fallback in photo-chat: ' . $aiException->getMessage());

                // رد بديل راقٍ وذاتي التضمين إذا تعذر الوصول لنموذج الذكاء الاصطناعي
                $fallbackAnswer = $matchedPerson
                    ? "تم التعرف على الوجه بنجاح بنسبة تطابق ({$similarity}%): هذا هو {$matchedPerson->full_name}" . ($kinshipText ? " (صلة القرابة: {$kinshipText})" : "") . ".\n\nيمكنك الاطلاع على كافة تفاصيله وشجرته بالنقر على البطاقة أدناه."
                    : "تم فحص ملامح الوجه في قاعدة بيانات شجرة عائلة السريّع عبر AWS Rekognition ولكن لم يُعثر على تطابق مع أي فرد مسجل حالياً. يرجى التأكد من وضوح زاوية الصورة أو البحث بالاسم ثلاثياً.";

                return response()->json([
                    'ok' => true,
                    'conversation_id' => $conversationUuid ?: (string) \Illuminate\Support\Str::uuid(),
                    'message_id' => null,
                    'answer' => $fallbackAnswer,
                    'citations' => $matchedPerson ? [[
                        'type' => 'person',
                        'id' => $matchedPerson->id,
                        'title' => $matchedPerson->full_name,
                        'url' => null,
                    ]] : [],
                    'person_match' => $personMatch,
                ]);
            }
        } catch (\Throwable $e) {
            \Log::error('AI API photo chat error', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'ok' => false,
                'message' => 'تعذر معالجة صورة الوجه: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function tts(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessToken($request)) {
            return $deny;
        }

        $validated = $request->validate([
            'message_id' => 'nullable|integer|exists:ai_messages,id',
            'text' => 'required_without:message_id|nullable|string|max:4000',
            'voice' => 'nullable|string|in:onyx,nova,alloy,echo,fable,shimmer',
        ]);

        try {
            $message = !empty($validated['message_id']) ? AiMessage::query()->find($validated['message_id']) : null;
            $text = $message ? $message->content : ($validated['text'] ?? '');

            $result = $this->ttsService->synthesize(
                text: $text,
                message: $message,
                voice: $validated['voice'] ?? null,
                userId: $request->user()?->id,
                sessionKey: $this->sessionKey($request),
                ip: $request->ip(),
            );

            return response()->json([
                'ok' => true,
                'audio_url' => $result['url'],
                'cached' => $result['cached'],
                'duration_approx' => $result['duration_approx'],
                'voice' => $result['voice'],
            ]);
        } catch (AiException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            \Log::error('AI API TTS error', ['message' => $e->getMessage()]);
            return response()->json(['ok' => false, 'message' => 'تعذر توليد الصوت حالياً.'], 500);
        }
    }

    public function conversations(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessToken($request)) {
            return $deny;
        }

        $sessionKey = $this->sessionKey($request);

        $items = AiConversation::query()
            ->when($request->user(), fn ($q) => $q->where('user_id', $request->user()->id))
            ->when(! $request->user(), fn ($q) => $q->where('session_key', $sessionKey))
            ->where('channel', 'api')
            ->latest()
            ->limit(30)
            ->get(['uuid', 'title', 'created_at', 'updated_at']);

        return response()->json(['ok' => true, 'conversations' => $items]);
    }

    public function showConversation(Request $request, string $uuid): JsonResponse
    {
        if ($deny = $this->denyUnlessToken($request)) {
            return $deny;
        }

        $conversation = AiConversation::query()
            ->where('uuid', $uuid)
            ->with(['messages' => fn ($q) => $q->orderBy('id')])
            ->first();

        if (! $conversation) {
            return response()->json(['ok' => false, 'message' => 'المحادثة غير موجودة'], 404);
        }

        return response()->json([
            'ok' => true,
            'conversation' => [
                'id' => $conversation->uuid,
                'title' => $conversation->title,
                'messages' => $conversation->messages->map(fn ($m) => [
                    'role' => $m->role,
                    'content' => $m->content,
                    'citations' => $m->citations,
                    'created_at' => $m->created_at,
                ]),
            ],
        ]);
    }

    public function suggestions(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessToken($request)) {
            return $deny;
        }

        return response()->json([
            'ok' => true,
            'suggestions' => $this->chatService->suggestions(),
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessToken($request)) {
            return $deny;
        }

        $validated = $request->validate([
            'q' => 'required|string|max:500',
        ]);

        try {
            $results = $this->searchService->search($validated['q'], $request->user()?->id, $request->ip());

            return response()->json(['ok' => true, 'results' => $results]);
        } catch (AiException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage(), 'results' => []], 422);
        }
    }

    private function denyUnlessToken(Request $request): ?JsonResponse
    {
        $token = SiteMobileAccessToken::extractFromRequest($request);
        if (! SiteMobileAccessToken::valid($token)) {
            return response()->json([
                'ok' => false,
                'message' => 'غير مصرح. يلزم رمز دخول الموقع.',
            ], 401);
        }

        return null;
    }

    private function sessionKey(Request $request): string
    {
        $token = SiteMobileAccessToken::extractFromRequest($request) ?? $request->ip();

        return substr(hash('sha256', (string) $token), 0, 64);
    }
}
