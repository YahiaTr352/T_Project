<?php

namespace App\Http\Controllers;

use App\Services\AI\ChatService;
use App\Services\AI\TtsService;
use App\Models\AiMessage;
use App\Services\AI\Exceptions\AiException;
use App\Services\KinshipCalculatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiChatController extends Controller
{
    public function __construct(
        private readonly ChatService $chatService,
        private readonly KinshipCalculatorService $kinshipCalculator,
        private readonly TtsService $ttsService,
    ) {
    }

    public function index(): View
    {
        return view('web-site.ai.ask', [
            'suggestions' => $this->chatService->suggestions(),
        ]);
    }

    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:2000',
            'conversation_id' => 'nullable|uuid',
            'person_id' => 'nullable|integer',
        ]);

        try {
            $person = $this->kinshipCalculator->resolvePersonForUser(
                $request->user(),
                $validated['person_id'] ?? null,
                $request
            );
            $result = $this->chatService->ask(
                question: $validated['message'],
                conversationUuid: $validated['conversation_id'] ?? null,
                userId: $request->user()?->id,
                personId: $person?->id,
                sessionKey: substr(hash('sha256', (string) $request->session()->getId()), 0, 64),
                channel: 'web',
                ip: $request->ip(),
            );

            return response()->json([
                'ok' => true,
                'conversation_id' => $result['conversation']->uuid,
                'answer' => $result['answer'],
                'citations' => $result['citations'],
            ]);
        } catch (AiException $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Illuminate\Database\QueryException $e) {
            \Log::error('AI chat database error', ['message' => $e->getMessage()]);
            return response()->json([
                'ok' => false,
                'message' => 'تعذر الاتصال بقاعدة البيانات حالياً. تأكد من تشغيل خادم MySQL ثم حاول مرة أخرى.',
            ], 503);
        } catch (\Throwable $e) {
            \Log::error('AI chat error', ['message' => $e->getMessage()]);
            return response()->json([
                'ok' => false,
                'message' => 'حدث خطأ غير متوقع. حاول مرة أخرى لاحقاً.',
            ], 500);
        }
    }


    public function voiceChat(Request $request): JsonResponse
    {
        $request->validate([
            'audio' => 'required|file|mimes:mp3,m4a,mp4,wav,aac,ogg,webm|max:25600',
            'conversation_id' => 'nullable|uuid',
            'person_id' => 'nullable|integer',
        ]);

        try {
            $file = $request->file('audio');
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
                sessionKey: substr(hash('sha256', (string) $request->session()->getId()), 0, 64),
                channel: 'web',
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
            \Log::error('Web AI voice chat error', ['message' => $e->getMessage()]);
            return response()->json(['ok' => false, 'message' => 'تعذر معالجة الرسالة الصوتية حالياً.'], 500);
        }
    }

    public function tts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message_id' => 'nullable|integer|exists:ai_messages,id',
            'text' => 'required_without:message_id|nullable|string|max:4000',
        ]);

        try {
            $message = !empty($validated['message_id']) ? AiMessage::query()->find($validated['message_id']) : null;
            $text = $message ? $message->content : ($validated['text'] ?? '');

            $result = $this->ttsService->synthesize(
                text: $text,
                message: $message,
                userId: $request->user()?->id,
                sessionKey: substr(hash('sha256', (string) $request->session()->getId()), 0, 64),
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
            \Log::error('Web AI TTS error', ['message' => $e->getMessage()]);
            return response()->json(['ok' => false, 'message' => 'تعذر توليد الصوت حالياً.'], 500);
        }
    }

    public function suggestions(): JsonResponse
    {
        return response()->json([
            'suggestions' => $this->chatService->suggestions(),
        ]);
    }
}
