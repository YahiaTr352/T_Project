<?php

namespace App\Services\AI;

use App\Models\AiGeneration;
use App\Models\Person;
use App\Models\Story;
use App\Services\AI\Content\PersonContentBuilder;
use App\Services\AI\DTO\ChatMessage;
use App\Support\AiSettings;
use Illuminate\Support\Facades\Storage;

class GenerationService
{
    public function __construct(
        private readonly AiManager $aiManager,
        private readonly AiFeatureGate $gate,
        private readonly AiUsageTracker $usageTracker,
        private readonly PersonContentBuilder $personContentBuilder,
    ) {
    }

    public function generateBiography(Person $person, ?int $userId = null, ?string $ip = null): AiGeneration
    {
        $this->gate->authorize('biography', $userId, $ip);

        $facts = $this->personContentBuilder->build($person);
        $system = AiSettings::prompt('biography');
        $userPrompt = "اكتب سيرة ذاتية مناسبة للنشر اعتماداً على البيانات التالية فقط:\n\n{$facts}";

        $result = $this->chat('biography', $system, $userPrompt, $userId);

        return AiGeneration::query()->create([
            'feature' => 'biography',
            'target_type' => $person->getMorphClass(),
            'target_id' => $person->id,
            'status' => AiGeneration::STATUS_PENDING,
            'prompt' => $userPrompt,
            'output' => $result->content,
            'meta' => ['provider' => $result->provider, 'model' => $result->model],
            'created_by' => $userId,
        ]);
    }

    public function assistStory(string $notes, ?string $title = null, ?int $userId = null, ?string $ip = null): AiGeneration
    {
        $this->gate->authorize('story_assist', $userId, $ip);

        $system = AiSettings::prompt('story_assist');
        $userPrompt = "حوّل النقاط التالية إلى قصة عائلية".($title ? " بعنوان مقترح «{$title}»" : '').":\n\n{$notes}\n\nأرجع أيضاً 3 عناوين مقترحة في النهاية.";

        $result = $this->chat('story_assist', $system, $userPrompt, $userId);

        return AiGeneration::query()->create([
            'feature' => 'story_assist',
            'status' => AiGeneration::STATUS_PENDING,
            'prompt' => $userPrompt,
            'output' => $result->content,
            'meta' => ['provider' => $result->provider, 'model' => $result->model, 'title' => $title],
            'created_by' => $userId,
        ]);
    }

    public function draftNews(string $notes, string $kind = 'news', ?int $userId = null, ?string $ip = null): AiGeneration
    {
        $this->gate->authorize('news_draft', $userId, $ip);

        $system = AiSettings::prompt('news_draft');
        $label = $kind === 'whatsapp' ? 'دعوة واتساب قصيرة' : 'خبر عائلي';
        $userPrompt = "اكتب {$label} اعتماداً على النقاط التالية فقط:\n\n{$notes}";

        $result = $this->chat('news_draft', $system, $userPrompt, $userId);

        return AiGeneration::query()->create([
            'feature' => 'news_draft',
            'status' => AiGeneration::STATUS_PENDING,
            'prompt' => $userPrompt,
            'output' => $result->content,
            'meta' => ['provider' => $result->provider, 'model' => $result->model, 'kind' => $kind],
            'created_by' => $userId,
        ]);
    }

    public function transcribeStory(Story $story, ?int $userId = null, ?string $ip = null): AiGeneration
    {
        $this->gate->authorize('transcription', $userId, $ip);

        $path = $story->audio_path ?: $story->video_path;
        if (! filled($path)) {
            throw new Exceptions\AiException('لا يوجد ملف صوتي/فيديو لهذه القصة.');
        }

        $absolute = Storage::disk('public')->path($path);
        if (! is_file($absolute)) {
            $absolute = storage_path('app/'.$path);
        }
        if (! is_file($absolute)) {
            throw new Exceptions\AiException('تعذر العثور على ملف الوسائط على القرص.');
        }

        $text = $this->aiManager->withFallback(function ($driver) use ($absolute) {
            $model = $this->aiManager->modelForFeature('transcription', $driver);

            return $driver->transcribe($absolute, ['model' => $model, 'language' => 'ar']);
        }, 'transcription');

        return AiGeneration::query()->create([
            'feature' => 'transcription',
            'target_type' => $story->getMorphClass(),
            'target_id' => $story->id,
            'status' => AiGeneration::STATUS_PENDING,
            'prompt' => 'تفريغ وسائط القصة #'.$story->id,
            'output' => $text,
            'created_by' => $userId,
        ]);
    }

    public function approve(AiGeneration $generation, int $reviewerId): AiGeneration
    {
        $generation->status = AiGeneration::STATUS_APPROVED;
        $generation->reviewed_by = $reviewerId;
        $generation->reviewed_at = now();
        $generation->save();

        if ($generation->feature === 'biography' && $generation->target instanceof Person) {
            $generation->target->biography = $generation->output;
            $generation->target->save();
        }

        if ($generation->feature === 'transcription' && $generation->target instanceof Story) {
            if (! filled($generation->target->content)) {
                $generation->target->content = $generation->output;
                $generation->target->save();
            }
        }

        activity()
            ->causedBy(\App\Models\User::find($reviewerId))
            ->performedOn($generation)
            ->log('ai.generation.approved');

        return $generation;
    }

    public function reject(AiGeneration $generation, int $reviewerId): AiGeneration
    {
        $generation->status = AiGeneration::STATUS_REJECTED;
        $generation->reviewed_by = $reviewerId;
        $generation->reviewed_at = now();
        $generation->save();

        activity()
            ->causedBy(\App\Models\User::find($reviewerId))
            ->performedOn($generation)
            ->log('ai.generation.rejected');

        return $generation;
    }

    private function chat(string $feature, string $system, string $userPrompt, ?int $userId)
    {
        $result = $this->aiManager->withFallback(function ($driver) use ($feature, $system, $userPrompt) {
            $model = $this->aiManager->modelForFeature($feature, $driver);

            return $driver->chat([
                new ChatMessage('system', $system),
                new ChatMessage('user', $userPrompt),
            ], [
                'model' => $model,
                'temperature' => 0.5,
                'max_tokens' => 1600,
            ]);
        });

        $this->usageTracker->recordChat($feature, $result, $userId);

        return $result;
    }
}
