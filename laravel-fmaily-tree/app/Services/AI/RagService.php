<?php

namespace App\Services\AI;

use App\Models\Article;
use App\Models\Person;
use App\Models\Story;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class RagService
{
    public function __construct(
        private readonly EmbeddingsService $embeddings,
    ) {
    }

    /**
     * Build context for the AI conversation.
     * In the Dynamic Agentic Architecture, domain queries (kinship, age extremes,
     * family members, peers, statistics) are handled dynamically via Function Calling (Tools).
     * RAG's sole responsibility is providing the authenticated viewer's identity and
     * semantic lore retrieval for open-ended heritage questions.
     *
     * @return array{context: string, citations: array<int, array<string, mixed>>}
     */
    public function buildContext(string $question, ?int $viewerPersonId = null, ?string $previousSubject = null): array
    {
        if ($this->looksLikeSocialGreeting($question)) {
            return ['context' => '', 'citations' => []];
        }

        $citations = [];
        $blocks = [];

        // 1. Authenticated User Identity Context (Always ground the assistant with current user's profile)
        if ($viewerPersonId) {
            $viewer = Person::query()->withoutGlobalScopes()->find($viewerPersonId);
            if ($viewer) {
                $blocks[] = "[سياق المستخدم المتصل حالياً (صاحب الحساب/الجلسة)]\n" . $this->formatViewerDetails($viewer);
                $citation = $this->citationFor($viewer);
                if ($citation) {
                    $citations[$citation['key']] = $citation;
                }
            }
        }

        // 2. Semantic Lore Retrieval (Embeddings for open-ended historical/story queries)
        if ($this->looksLikeStoryQuestion($question)) {
            try {
                $hits = $this->embeddings->search($question);
                foreach ($hits as $hit) {
                    if (($hit['score'] ?? 0) < 0.52) {
                        continue;
                    }
                    /** @var \App\Models\AiEmbedding $embedding */
                    $embedding = $hit['embedding'];
                    $model = $embedding->embeddable;
                    if (!$model) {
                        continue;
                    }

                    if (($hit['score'] ?? 0) >= 0.58) {
                        $citation = $this->citationFor($model);
                        if ($citation) {
                            $citations[$citation['key']] = $citation;
                        }
                    }

                    $text = $embedding->content;
                    if ($model instanceof Person && $model->gender === 'female') {
                        $text = preg_replace('/(تاريخ|سنة)\\s+الميلاد:[^\n]+/u', '', $text);
                    }
                    $blocks[] = "[مقتطف تاريخي موثق score=" . round($hit['score'], 3) . "]\n" . trim($text);
                }
            } catch (\Throwable) {
                // Embeddings may be unavailable — continue
            }
        }

        $context = implode("\n\n---\n\n", array_slice($blocks, 0, 8));
        $max = (int) config('ai.rag.max_context_chars', 12000);
        if (mb_strlen($context) > $max) {
            $context = mb_substr($context, 0, $max) . "\n…";
        }

        return [
            'context' => $context,
            'citations' => array_values(array_filter($citations)),
        ];
    }

    /**
     * Format verified details of the logged-in viewer.
     */
    public function formatViewerDetails(Person $viewer): string
    {
        $viewer->loadMissing(['parent', 'mother', 'location', 'children', 'wives', 'husband', 'childrenFromMother']);

        $lines = [];
        $lines[] = "الاسم الكامل: {$viewer->full_name} (معرّف السجل: #{$viewer->id})";
        $lines[] = "الجنس: " . ($viewer->gender === 'female' ? 'أنثى' : 'ذكر');

        if ($viewer->birth_date) {
            $age = Carbon::parse($viewer->birth_date)->age;
            $lines[] = "تاريخ الميلاد: " . $viewer->birth_date->format('Y-m-d') . " (العمر الحالي: {$age} سنة)";
        }
        if ($viewer->birth_place) {
            $lines[] = "مكان الميلاد: {$viewer->birth_place}";
        }
        if ($viewer->occupation) {
            $lines[] = "المهنة: {$viewer->occupation}";
        }
        if ($viewer->location?->name) {
            $lines[] = "المدينة/المقر: {$viewer->location->name}";
        }
        if ($viewer->parent?->full_name) {
            $lines[] = "الأب: {$viewer->parent->full_name}";
        }
        if ($viewer->mother?->full_name) {
            $lines[] = "الأم: {$viewer->mother->full_name}";
        }

        // الإخوة والأخوات
        if ($viewer->parent_id) {
            $siblings = Person::query()
                ->withoutGlobalScopes()
                ->where('parent_id', $viewer->parent_id)
                ->where('id', '!=', $viewer->id)
                ->get();
            if ($siblings->isNotEmpty()) {
                $siblingNames = $siblings->map(fn($s) => $s->full_name ?: $s->first_name)->implode('، ');
                $lines[] = "الإخوة والأخوات ({$siblings->count()}): {$siblingNames}";
            }
        }

        // الأبناء
        $children = $viewer->gender === 'female' ? $viewer->childrenFromMother : $viewer->children;
        if ($children && $children->isNotEmpty()) {
            $childrenNames = $children->map(fn($c) => $c->full_name ?: $c->first_name)->implode('، ');
            $lines[] = "الأبناء والبنات ({$children->count()}): {$childrenNames}";
        }

        // الزوج / الزوجات
        if ($viewer->gender === 'male' && $viewer->wives && $viewer->wives->isNotEmpty()) {
            $wivesNames = $viewer->wives->pluck('full_name')->filter()->implode('، ');
            if (filled($wivesNames)) {
                $lines[] = "الزوجات: {$wivesNames}";
            }
        } elseif ($viewer->gender === 'female' && $viewer->husband) {
            $lines[] = "الزوج: {$viewer->husband->full_name}";
        }

        return implode("\n", array_map(fn($l) => "- " . $l, $lines));
    }

    public function citationFor(object $model): ?array
    {
        if ($model instanceof Person) {
            return [
                'key' => 'person-'.$model->id,
                'type' => 'person',
                'id' => $model->id,
                'title' => $model->full_name,
                'url' => url('/person/'.$model->id),
            ];
        }

        if ($model instanceof Story) {
            return [
                'key' => 'story-'.$model->id,
                'type' => 'story',
                'id' => $model->id,
                'title' => $model->title,
                'url' => route('public.stories.show', $model),
            ];
        }

        if ($model instanceof Article) {
            return [
                'key' => 'article-'.$model->id,
                'type' => 'article',
                'id' => $model->id,
                'title' => $model->title,
                'url' => url('/gallery/articles/'.$model->id),
            ];
        }

        return null;
    }

    public function looksLikeStoryQuestion(string $q): bool
    {
        return (bool) preg_match('/قصة|قصص|سيرة|حكاية|موقف|تاريخ|حدث|رواية|بطول/u', $q);
    }

    public function looksLikeSocialGreeting(string $q): bool
    {
        return (bool) preg_match('/^(?:السلام عليكم|مرحبا|أهلا|اهلا|صباح الخير|مساء الخير|كيفك|شخبارك|كيف الحال|طمني عليك)[\s!؟?.]*$/u', trim($q));
    }
}
