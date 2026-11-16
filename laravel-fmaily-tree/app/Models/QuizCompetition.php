<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizCompetition extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'is_active',
        'display_order',
        'start_at',
        'end_at',
        'reveal_delay_seconds',
        'show_draw_only',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'display_order' => 'integer',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'reveal_delay_seconds' => 'integer',
        'show_draw_only' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saved(function () {
            \Illuminate\Support\Facades\Cache::forget('api_active_quiz_competition');
        });
        static::deleted(function () {
            \Illuminate\Support\Facades\Cache::forget('api_active_quiz_competition');
        });
    }

    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('display_order');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(QuizRegistration::class);
    }

    public function sponsors()
    {
        return $this->belongsToMany(Sponsor::class, 'quiz_competition_sponsor');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('created_at', 'desc');
    }

    /**
     * Time at which questions become visible to visitors (start_at + configured delay in seconds).
     * Returns null if start_at is not set.
     */
    public function getQuestionsVisibleAt(): ?Carbon
    {
        if (!$this->start_at) {
            return null;
        }

        $delaySeconds = $this->reveal_delay_seconds ?? config('quiz.question_visible_after_seconds', 60);

        return $this->start_at->copy()->addSeconds($delaySeconds);
    }

    /**
     * التحقق مما إذا كانت المسابقة مرئية للزائر/المستخدم في الصفحة الرئيسية
     * في حال احتوت المسابقة على أسئلة ومسابقات محجوبة فقط عن الزائر، تُحجب المسابقة كلياً
     */
    public function isVisibleTo(?Person $person, ?string $authMethod = null): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $questions = $this->relationLoaded('questions') ? $this->questions : $this->questions()->get();
        if ($questions->isEmpty()) {
            return true;
        }

        return $questions->contains(fn (QuizQuestion $q) => $q->isVisibleTo($person, $authMethod));
    }

    /**
     * الحصول على أقرب حدث قادم مرئي للزائر الحالي (مسابقة - وقت السؤال يحدد من المسابقة)
     */
    public static function getNextUpcomingEvent(?Person $person = null): ?array
    {
        $upcomingCompetitions = self::where('is_active', true)
            ->whereNotNull('start_at')
            ->where('start_at', '>', now())
            ->orderBy('start_at')
            ->with('questions')
            ->get();

        $nextCompetition = $upcomingCompetitions->first(function (QuizCompetition $comp) use ($person) {
            return $comp->isVisibleTo($person);
        });

        if (!$nextCompetition) {
            return null;
        }

        $visibleQuestions = $nextCompetition->questions->filter(fn (QuizQuestion $q) => $q->isVisibleTo($person));
        $answerTypes = $visibleQuestions->pluck('answer_type')->unique();

        if ($nextCompetition->title) {
            $sectionTitle = $nextCompetition->title;
        } elseif ($answerTypes->contains('walking')) {
            $sectionTitle = 'تحدي المشي والخطوات';
        } elseif ($answerTypes->count() === 1) {
            $type = $answerTypes->first();
            if ($type === 'vote') {
                $sectionTitle = 'التصويت';
            } elseif ($type === 'survey') {
                $sectionTitle = 'الاستبيان';
            } else {
                $sectionTitle = 'المسابقات والفعاليات';
            }
        } else {
            $sectionTitle = 'المسابقات والفعاليات';
        }

        return [
            'type' => 'competition',
            'target_at' => $nextCompetition->start_at,
            'title' => $nextCompetition->title,
            'description' => $nextCompetition->description,
            'section_title' => $sectionTitle,
        ];
    }
}
