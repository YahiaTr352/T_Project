<?php

namespace App\Services\AI\Tools;

use App\Models\Article;
use App\Models\Person;
use App\Models\Story;
use App\Services\AI\EmbeddingsService;
use App\Services\KinshipCalculatorService;
use Illuminate\Support\Collection;

class ToolRegistry
{
    public function __construct(
        private readonly PersonAgeTool $personAge,
        private readonly PersonLookupTool $personLookup,
        private readonly KinshipTool $kinshipTool,
        private readonly FamilyMembersTool $familyMembers,
        private readonly BranchStatsTool $branchStats,
        private readonly StorySearchTool $storySearch,
        private readonly EmbeddingsService $embeddings,
    ) {
    }

    /**
     * Get tool definitions formatted for OpenAI (tools array) or Gemini (functionDeclarations).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getOpenAiToolDefinitions(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_family_age_extremes',
                    'description' => 'جلب الأشخاص الأكبر سناً أو المعمرين أو الأصغر سناً في سجلات شجرة عائلة السريّع مع تواريخهم وحالتهم (أحياء أو متوفين). استخدم هذه الأداة عندما يسأل المستخدم عن أكبر أو أصغر شخص أو معمر في العائلة، أو عند المتابعة لتحديد الأحياء فقط.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'type' => [
                                'type' => 'string',
                                'enum' => ['oldest', 'youngest'],
                                'description' => 'حدد oldest للأكبر سناً، أو youngest للأصغر سناً أو المواليد الجدد.',
                            ],
                            'living_only' => [
                                'type' => 'boolean',
                                'description' => 'ضع true إذا كان السؤال أو المتابعة تطلب الأحياء فقط (مثل: عايشين، على قيد الحياة، ليس ميت، الأحياء)، أو false إذا كان عن كل السجلات التاريخية.',
                            ],
                            'gender' => [
                                'type' => 'string',
                                'enum' => ['male', 'female', 'any'],
                                'description' => 'تحديد الجنس إذا خصص السائل رجلاً أو امرأة.',
                            ],
                        ],
                        'required' => ['type'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_family_members',
                    'description' => 'جلب أفراد الأسرة (الأبناء، البنات، الزوجات، الزوج، الإخوة، الأخوات، أو الوالدان) لأي شخص في الشجرة أو للمستخدم المتصل. استخدم هذه الأداة عند السؤال عن: من هم أبناء فلان؟ من هي زوجة فلان؟ من هم إخوان فلان؟ أو عند سؤال المستخدم عن أبنائه أو زوجاته أو إخوانه.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'person_name' => [
                                'type' => 'string',
                                'description' => 'اسم الشخص المراد معرفة أسرته (اتركه فارغاً أو ضع "me" إذا كان السؤال عن أسرة المستخدم المتصل مثل: أبنائي، زوجاتي).',
                            ],
                            'relation_type' => [
                                'type' => 'string',
                                'enum' => ['children', 'spouses', 'siblings', 'parents', 'all'],
                                'description' => 'نوع القرابة المطلوبة: children (الأبناء والبنات)، spouses (الزوجات أو الزوج)، siblings (الإخوة والأخوات)، parents (الأب والأم)، all (الكل).',
                            ],
                        ],
                        'required' => ['relation_type'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'find_birthday_or_age_peers',
                    'description' => 'البحث عن أقران السن ومن ولدوا في نفس يوم وشهر الميلاد (Birthday Twins) أو في نفس سنة الميلاد لشخص معين أو للمستخدم المتصل بالدردشة. استخدم هذه الأداة عندما يسأل المستخدم: من هم المولودين في نفس اليوم عندي؟ أو من في نفس عمري؟ أو من أقراني في السن؟ أو من هم مواليد سنة معينة.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'target' => [
                                'type' => 'string',
                                'description' => 'حدد "me" إذا كان الاستفسار عن المستخدم المتصل (عندي، في عمري، أقراني)، أو اكتب اسم الشخص المطلوب.',
                            ],
                            'peer_type' => [
                                'type' => 'string',
                                'enum' => ['same_day', 'same_year', 'exact_date', 'both'],
                                'description' => 'same_day (مواليد نفس اليوم والشهر)، same_year (أقران السن ونفس سنة الميلاد)، exact_date (نفس تاريخ الميلاد بالكامل)، both (الكل).',
                            ],
                            'year' => [
                                'type' => 'integer',
                                'description' => 'سنة الميلاد إذا سأل المستخدم عن مواليد سنة محددة (مثل: 1988).',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'lookup_family_member',
                    'description' => 'البحث عن أفراد شجرة عائلة السريّع وسيرهم وبياناتهم العائلية وتفاصيل أنسابهم بالاسم.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => [
                                'type' => 'string',
                                'description' => 'اسم الشخص المراد البحث عنه في شجرة العائلة (مثل: محمد بن سريع، عبدالله الحمدان، إلخ).',
                            ],
                        ],
                        'required' => ['name'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'calculate_kinship',
                    'description' => 'احتساب صلة القرابة والجد المشترك وسلسلة النسب الدقيقة بين شخصين في العائلة أو بين المستخدم المتصل وشخص آخر في الشجرة.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'person_a' => [
                                'type' => 'string',
                                'description' => 'اسم الشخص الأول (اتركه فارغاً أو ضع "me" إذا كان السؤال عن صلة قرابة المستخدم المتصل مع شخص آخر).',
                            ],
                            'person_b' => [
                                'type' => 'string',
                                'description' => 'اسم الشخص الثاني المراد معرفة صلة القرابة به.',
                            ],
                        ],
                        'required' => ['person_b'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_family_statistics',
                    'description' => 'جلب إحصائيات شجرة العائلة الرسمية (إجمالي عدد الأفراد، الفروع، الأجيال والتوزيع).',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'branch_name' => [
                                'type' => 'string',
                                'description' => 'اسم الفرع إذا كان الاستفسار عن فرع محدد (مثل: حمدان، ناصر)، أو اتركه فارغاً لكامل العائلة.',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_family_lore',
                    'description' => 'البحث في موروث وقصص ومواقف ومقالات وتاريخ عائلة السريّع الموثقة.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => [
                                'type' => 'string',
                                'description' => 'موضوع القصة أو كلمات البحث التاريخية أو الموقف المطلوب.',
                            ],
                        ],
                        'required' => ['query'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Get tool definitions formatted for Google Gemini (functionDeclarations format).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getGeminiToolDeclarations(): array
    {
        $openAiTools = $this->getOpenAiToolDefinitions();
        $declarations = [];

        foreach ($openAiTools as $tool) {
            $fn = $tool['function'];
            $params = $fn['parameters'] ?? ['type' => 'object', 'properties' => []];

            $geminiProperties = [];
            foreach (($params['properties'] ?? []) as $propKey => $propVal) {
                $pType = strtoupper($propVal['type'] ?? 'STRING');
                if ($pType === 'BOOLEAN') {
                    $geminiProperties[$propKey] = [
                        'type' => 'BOOLEAN',
                        'description' => $propVal['description'] ?? '',
                    ];
                } elseif ($pType === 'INTEGER') {
                    $geminiProperties[$propKey] = [
                        'type' => 'INTEGER',
                        'description' => $propVal['description'] ?? '',
                    ];
                } elseif (!empty($propVal['enum'])) {
                    $geminiProperties[$propKey] = [
                        'type' => 'STRING',
                        'description' => $propVal['description'] ?? '',
                        'enum' => $propVal['enum'],
                    ];
                } else {
                    $geminiProperties[$propKey] = [
                        'type' => 'STRING',
                        'description' => $propVal['description'] ?? '',
                    ];
                }
            }

            $declarations[] = [
                'name' => $fn['name'],
                'description' => $fn['description'],
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => $geminiProperties,
                    'required' => $params['required'] ?? [],
                ],
            ];
        }

        return [['functionDeclarations' => $declarations]];
    }

    /**
     * Execute a tool by name with arguments and return output string and verified citations.
     *
     * @param array<string, mixed> $arguments
     * @return array{content: string, citations: array<int, array<string, mixed>>}
     */
    public function execute(string $toolName, array $arguments, ?int $viewerPersonId = null): array
    {
        return match ($toolName) {
            'get_family_age_extremes' => $this->executeAgeExtremes($arguments),
            'get_family_members' => $this->executeFamilyMembers($arguments, $viewerPersonId),
            'find_birthday_or_age_peers' => $this->executeBirthdayOrAgePeers($arguments, $viewerPersonId),
            'lookup_family_member' => $this->executeLookupMember($arguments),
            'calculate_kinship' => $this->executeKinship($arguments, $viewerPersonId),
            'get_family_statistics' => $this->executeStatistics($arguments),
            'search_family_lore' => $this->executeLore($arguments),
            default => [
                'content' => "أداة غير معروفة: {$toolName}",
                'citations' => [],
            ],
        };
    }

    private function executeAgeExtremes(array $args): array
    {
        $type = $args['type'] ?? 'oldest';
        $livingOnly = (bool) ($args['living_only'] ?? false);
        $gender = !empty($args['gender']) && $args['gender'] !== 'any' ? $args['gender'] : null;

        if ($type === 'youngest') {
            $result = $this->personAge->youngestWithPeople(6, $gender);
        } else {
            $result = $this->personAge->oldestWithPeople(6, $livingOnly, $gender);
        }

        $citations = [];
        foreach ($result['people'] as $person) {
            $c = $this->citationFor($person);
            if ($c) {
                $citations[] = $c;
            }
        }

        return [
            'content' => $result['text'],
            'citations' => $citations,
        ];
    }

    private function executeFamilyMembers(array $args, ?int $viewerPersonId = null): array
    {
        $name = trim((string) ($args['person_name'] ?? ''));
        $relation = trim((string) ($args['relation_type'] ?? 'all'));

        $res = $this->familyMembers->getMembers($name, $relation, $viewerPersonId);

        $citations = [];
        foreach ($res['people'] as $person) {
            $c = $this->citationFor($person);
            if ($c) {
                $citations[] = $c;
            }
        }

        return [
            'content' => $res['text'],
            'citations' => $citations,
        ];
    }

    private function executeBirthdayOrAgePeers(array $args, ?int $viewerPersonId = null): array
    {
        $target = trim((string) ($args['target'] ?? 'me'));
        $peerType = trim((string) ($args['peer_type'] ?? 'both'));
        $year = !empty($args['year']) ? (int) $args['year'] : null;

        // Direct search by year
        if ($year) {
            $res = $this->personAge->findPeersByDateOrYear(year: $year, limit: 12);
            $citations = [];
            foreach ($res['people'] as $person) {
                $c = $this->citationFor($person);
                if ($c) $citations[] = $c;
            }
            return [
                'content' => $res['text'],
                'citations' => $citations,
            ];
        }

        $targetPersonId = null;
        if (in_array($target, ['me', 'أنا', 'انا', 'نفسي', 'المستخدم', ''], true)) {
            $targetPersonId = $viewerPersonId;
            if (!$targetPersonId) {
                return [
                    'content' => 'المستخدم الحالي يتصفح كزائر غير مسجل الهوية. لمعرفة أقرانك ومن ولدوا في نفس يومك، يرجى تسجيل الدخول أو تحديد اسمك في الشجرة.',
                    'citations' => [],
                ];
            }
        } else {
            $matched = $this->personLookup->search($target, 1)->first();
            if ($matched) {
                $targetPersonId = $matched->id;
            } else {
                return [
                    'content' => "لم يتم العثور على شخص باسم «{$target}» في شجرة العائلة لتحديد أقرانه.",
                    'citations' => [],
                ];
            }
        }

        $res = $this->personAge->findPeersForPerson($targetPersonId, $peerType, 12);

        $citations = [];
        foreach ($res['people'] as $person) {
            $c = $this->citationFor($person);
            if ($c) {
                $citations[] = $c;
            }
        }

        return [
            'content' => $res['text'],
            'citations' => $citations,
        ];
    }

    private function executeLookupMember(array $args): array
    {
        $name = trim((string) ($args['name'] ?? ''));
        if ($name === '') {
            return ['content' => 'يرجى تقديم اسم للبحث في شجرة العائلة.', 'citations' => []];
        }

        $people = $this->personLookup->search($name, 4);
        if ($people->isEmpty()) {
            return [
                'content' => "لم يتم العثور على شخص مسجل باسم «{$name}» في شجرة العائلة. اقترح على السائل كتابة الاسم ثلاثياً أو التأكد من الهجاء.",
                'citations' => [],
            ];
        }

        $citations = [];
        foreach ($people as $person) {
            $c = $this->citationFor($person);
            if ($c) {
                $citations[] = $c;
            }
        }

        return [
            'content' => $this->personLookup->format($people),
            'citations' => $citations,
        ];
    }

    private function executeKinship(array $args, ?int $viewerPersonId = null): array
    {
        $personA = trim((string) ($args['person_a'] ?? ''));
        $personB = trim((string) ($args['person_b'] ?? ''));

        if ($personB === '') {
            return ['content' => 'يرجى تحديد الشخص المراد معرفة صلة القرابة به.', 'citations' => []];
        }

        // إذا لم يُحدد الشخص الأول أو كان عن المستخدم
        if ($personA === '' || in_array($personA, ['me', 'المستخدم', 'أنا', 'انا'], true)) {
            if (!$viewerPersonId) {
                return [
                    'content' => "المستخدم الحالي يتصفح كضيف/زائر غير مسجل الهوية. لمعرفة صلة القرابة الدقيقة به يجب تسجيل الدخول أو تحديد اسمه في الشجرة.",
                    'citations' => [],
                ];
            }

            $other = $this->personLookup->search($personB, 1)->first();
            if (!$other) {
                return [
                    'content' => "لم يتم العثور على الشخص الثاني «{$personB}» في سجلات العائلة.",
                    'citations' => [],
                ];
            }

            $res = $this->kinshipTool->betweenIds($viewerPersonId, (int) $other->id);
        } else {
            // صلة القرابة بين اسمين محددين
            $res = $this->kinshipTool->betweenNames($personA, $personB);
        }

        $citations = [];
        foreach ($res['people'] as $person) {
            $c = $this->citationFor($person);
            if ($c) {
                $citations[] = $c;
            }
        }

        return [
            'content' => $res['content'],
            'citations' => array_values(array_unique($citations, SORT_REGULAR)),
        ];
    }

    private function executeStatistics(array $args): array
    {
        $branch = !empty($args['branch_name']) ? trim((string) $args['branch_name']) : null;
        $content = $this->branchStats->summary($branch);

        return [
            'content' => $content,
            'citations' => [],
        ];
    }

    private function executeLore(array $args): array
    {
        $query = trim((string) ($args['query'] ?? ''));
        if ($query === '') {
            return ['content' => 'يرجى تقديم كلمات بحث.', 'citations' => []];
        }

        $citations = [];
        $blocks = [];

        // 1. بحث بالقصص
        $stories = $this->storySearch->search($query, 3);
        if ($stories->isNotEmpty()) {
            $blocks[] = $this->storySearch->format($stories);
            foreach ($stories as $story) {
                $c = $this->citationFor($story);
                if ($c) {
                    $citations[] = $c;
                }
            }
        }

        // 2. بحث دلالي عبر الـ Embeddings
        try {
            $hits = $this->embeddings->search($query);
            foreach ($hits as $hit) {
                if (($hit['score'] ?? 0) < 0.52) {
                    continue;
                }
                $model = $hit['embedding']->embeddable ?? null;
                if ($model) {
                    $c = $this->citationFor($model);
                    if ($c) {
                        $citations[] = $c;
                    }
                    $blocks[] = "[مقتطف موثق score=" . round($hit['score'], 2) . "]:\n" . trim((string) $hit['embedding']->content);
                }
            }
        } catch (\Throwable) {
            // Embeddings fallback
        }

        if (empty($blocks)) {
            return [
                'content' => "لم نجد قصصاً أو أحداثاً مسجلة مطابقة تماماً لموضوع «{$query}» في الأرشيف المتاح حالياً.",
                'citations' => [],
            ];
        }

        return [
            'content' => implode("\n\n---\n\n", array_slice($blocks, 0, 4)),
            'citations' => array_values(array_unique($citations, SORT_REGULAR)),
        ];
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
}
