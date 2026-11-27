<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Kalnoy\Nestedset\NodeTrait;
use App\Models\Scopes\OrderedScope;

class Person extends BaseModel
{
    use HasFactory, NodeTrait;
    protected $table = 'persons';
    protected $fillable = [
        'display_order', // أضف هذا الحقل
        'first_name',
        'last_name',
        'birth_date',
        'birth_place',
        'death_date',
        'death_place',
        'cemetery',
        'cemetery_location', // لوكيشن القبر
        'grave_number', // رقم القبر
        'gender',
        'from_outside_the_family',
        'photo_url',
        'biography',
        'occupation',
        'location_id', // المرجع الجديد لجدول locations
        'parent_id',
        'mother_id',
        'privacy_settings',
        'walking_leaderboard_mode',
    ];
    protected $appends = [
        'name',
        'full_name',
        'full_name_with_relations',
        'articles_count',
        'first_article_id',
        'avatar',
    ];
    protected $casts = [
        'birth_date' => 'date',
        'death_date' => 'date',
        'privacy_settings' => 'array',
    ];

    /**
     * الإعدادات الافتراضية للخصوصية
     *
     * @return array<string, string>
     */
    public static function defaultPrivacySettings(): array
    {
        return [
            'contacts' => 'family',
            'photo' => 'public',
            'birth_date' => 'family',
            'location' => 'public',
            'biography' => 'public',
            'occupation' => 'public',
            'walking_leaderboard_mode' => 'visible',
        ];
    }

    /**
     * استرجاع إعدادات الخصوصية مدمجة مع القيم الافتراضية
     *
     * @return array<string, string>
     */
    public function getResolvedPrivacySettings(): array
    {
        $custom = is_array($this->privacy_settings) ? $this->privacy_settings : [];
        return array_merge(self::defaultPrivacySettings(), $custom);
    }

    /**
     * جلب خصوصية حقل معين
     */
    public function getPrivacySetting(string $field, string $default = 'family'): string
    {
        $settings = $this->getResolvedPrivacySettings();
        return $settings[$field] ?? $default;
    }

    /**
     * هل الشخص مستبعد تماماً من لوحة المتصدرين؟
     */
    public function isHiddenFromLeaderboard(): bool
    {
        return ($this->walking_leaderboard_mode ?? 'visible') === 'hidden';
    }

    /**
     * هل الشخص متخفي (تظهر خطواته ولكن يُحجب اسمه وصورته)؟
     */
    public function isAnonymousOnLeaderboard(): bool
    {
        return ($this->walking_leaderboard_mode ?? 'visible') === 'anonymous';
    }
    protected static function booted()
    {
        // ✅ الخطوة 2: تطبيق الـ Scope بشكل دائم على الموديل
        static::addGlobalScope(new OrderedScope);

        // تحديث عداد الأشخاص عند ربط أو فك ربط شخص بمكان
        static::saved(function ($person) {
            if ($person->isDirty('location_id')) {
                // تحديث العداد للمكان القديم
                if ($person->getOriginal('location_id')) {
                    $oldLocation = Location::find($person->getOriginal('location_id'));
                    if ($oldLocation) {
                        $oldLocation->persons_count = $oldLocation->persons()->count();
                        $oldLocation->save();
                    }
                }

                // تحديث العداد للمكان الجديد
                if ($person->location_id) {
                    $newLocation = Location::find($person->location_id);
                    if ($newLocation) {
                        $newLocation->persons_count = $newLocation->persons()->count();
                        $newLocation->save();
                    }
                }
            }
        });

        static::deleted(function ($person) {
            if ($person->location_id) {
                $location = Location::find($person->location_id);
                if ($location) {
                    $location->persons_count = $location->persons()->count();
                    $location->save();
                }
            }
        });
    }


    /**
     * بناء سلسلة الاسم الكامل للشخص مع أو بدون كلمات النسب (بن / بنت).
     * يتم إلحاق أداة التعريف (الـ) بآخر اسم في السلسلة (الجد الأكبر/رأس الفرع) لتمثيل الفرع/العائلة (مثل: المحمد، الإبراهيم).
     * للأشخاص من خارج العائلة (المصاهرة/الأزواج والزوجات): يتم دمج الاسم الأول واسم العائلة (last_name).
     *
     * @param bool $withLineage إذا كانت true سيتم تضمين (بن / بنت)، وإذا false سيتم سرد الأسماء متتالية فقط
     * @return string
     */
    public function buildFullName(bool $withLineage = false): string
    {
        $firstName = trim((string) ($this->first_name ?? ''));
        $nameParts = $firstName !== '' ? [$firstName] : [];
        $isOutside = $this->isFromOutsideTheFamily();

        $currentPerson = $this;
        $ancestor = $this->resolveParentLink($this);
        $visited = array_filter([$this->id]); // حماية ضد الحلقات التكرارية غير المنتهية

        // جمع سلسلة الأجداد الذكور
        $ancestors = [];
        while ($ancestor && !in_array($ancestor->id, $visited, true)) {
            $visited[] = $ancestor->id;

            // التحقق من أن الجد ذكر لمواصلة سلسلة النسب الذكورية
            if ($ancestor->gender === 'male') {
                $ancestorFirstName = trim((string) ($ancestor->first_name ?? ''));

                if ($ancestorFirstName !== '') {
                    $ancestors[] = [
                        'child_gender' => $currentPerson->gender,
                        'first_name'   => $ancestorFirstName,
                        'ancestor'     => $ancestor,
                    ];
                }
            } else {
                // إذا كان الجد أنثى، فإن سلسلة النسب الذكورية تنقطع هنا
                break;
            }

            $currentPerson = $ancestor;
            $ancestor = $this->resolveParentLink($ancestor);
        }

        $ancestorCount = count($ancestors);

        // إذا لم يكن هناك سلسلة أجداد (مثل الأزواج والزوجات من خارج العائلة أو الجد المؤسس الأول)
        if ($ancestorCount === 0) {
            if ($isOutside) {
                $lastName = $this->getResolvedLastName();
                if ($lastName !== '' && ! $this->nameAlreadyContainsLastName($firstName, $lastName)) {
                    $nameParts[] = $lastName;
                }
            }

            return implode(' ', $nameParts);
        }

        foreach ($ancestors as $index => $item) {
            $isLastAncestor = ($index === $ancestorCount - 1);
            $ancestorModel = $item['ancestor'] ?? null;
            $isAncestorOutside = $ancestorModel ? $ancestorModel->isFromOutsideTheFamily() : false;

            if ($isLastAncestor && $isAncestorOutside) {
                // إذا كان الجد الأخير في السلسلة من خارج العائلة، ندمج لقبه العائلي إن وُجد
                $ancestorLastName = $ancestorModel ? $ancestorModel->getResolvedLastName() : '';
                if ($ancestorLastName !== '' && ! $this->nameAlreadyContainsLastName($item['first_name'], $ancestorLastName)) {
                    $ancestorName = $item['first_name'] . ' ' . $ancestorLastName;
                } else {
                    $ancestorName = $item['first_name'];
                }
            } else {
                $ancestorName = $isLastAncestor ? $this->prefixWithAl($item['first_name']) : $item['first_name'];
            }

            if ($withLineage) {
                // تحديد كلمة النسب بناءً على جنس الابن/الابنة في هذه العلاقة
                $relationWord = ($item['child_gender'] === 'female') ? 'بنت' : 'بن';
                $nameParts[] = $relationWord . ' ' . $ancestorName;
            } else {
                // الاسم الكامل المباشر بدون بن / بنت
                $nameParts[] = $ancestorName;
            }
        }

        return implode(' ', $nameParts);
    }

    /**
     * تحديد ما إذا كان الشخص من خارج العائلة (مصاهرة/أزواج وزوجات)،
     * مع استرجاع القيمة من قاعدة البيانات بأمان إذا كان النموذج محملاً بأعمدة محدودة.
     */
    public function isFromOutsideTheFamily(): bool
    {
        if (array_key_exists('from_outside_the_family', $this->attributes)) {
            return (bool) $this->attributes['from_outside_the_family'];
        }

        if ($this->id) {
            $val = (bool) static::whereKey($this->id)->value('from_outside_the_family');
            $this->attributes['from_outside_the_family'] = $val;
            return $val;
        }

        return false;
    }

    /**
     * استرجاع الاسم الأخير للشخص مع الاستعلام الخفيف إذا لم يُطلب في الحقول المحملة.
     */
    public function getResolvedLastName(): string
    {
        if (array_key_exists('last_name', $this->attributes)) {
            return trim((string) ($this->attributes['last_name'] ?? ''));
        }

        if ($this->id) {
            $val = trim((string) static::whereKey($this->id)->value('last_name'));
            $this->attributes['last_name'] = $val;
            return $val;
        }

        return '';
    }

    /**
     * التحقق مما إذا كان الاسم الأول يحتوي بالفعل على اسم العائلة/اللقب لتجنب التكرار
     * (مثل: الاسم الأول "سارة الدوسري" واسم العائلة "الدوسري" أو "بن عقيل" مع "العقيل").
     */
    public function nameAlreadyContainsLastName(string $firstName, string $lastName): bool
    {
        $first = trim($firstName);
        $last = trim($lastName);

        if ($first === '' || $last === '') {
            return false;
        }

        if ($first === $last || str_ends_with($first, $last)) {
            return true;
        }

        // فحص بالـ regex للتحقق من أن اسم العائلة هو الكلمة الأخيرة
        $quotedLast = preg_quote($last, '/');
        if (preg_match('/(?:\s|^)' . $quotedLast . '$/u', $first)) {
            return true;
        }

        // فحص بدون أداة التعريف (الـ / آل) إذا كان الاسم الأول ينتهي بنفس الاسم (مثلاً: "بن عقيل" و "العقيل" أو "بن سويدان" و "السويدان")
        $lastWithoutAl = preg_replace('/^(ال|آل|أل|إل)/u', '', $last);
        if ($lastWithoutAl !== '' && mb_strlen($lastWithoutAl) >= 3) {
            $quotedNoAl = preg_quote($lastWithoutAl, '/');
            if (preg_match('/(?:\s|^)(?:بن\s+|ابن\s+)?' . $quotedNoAl . '$/u', $first)) {
                return true;
            }
        }

        // تطبيع خفيف للأحرف العربية (الهمزات والياء/الألف المقصورة والتاء المربوطة) للمقارنة
        $normFirst = $this->normalizeArabicStringForComparison($first);
        $normLast = $this->normalizeArabicStringForComparison($last);

        if ($normFirst === $normLast || str_ends_with($normFirst, $normLast)) {
            return true;
        }

        $normLastNoAl = preg_replace('/^(ال|آل|أل|إل)/u', '', $normLast);
        if ($normLastNoAl !== '' && mb_strlen($normLastNoAl) >= 3) {
            $quotedNormNoAl = preg_quote($normLastNoAl, '/');
            if (preg_match('/(?:\s|^)(?:بن\s+|ابن\s+)?' . $quotedNormNoAl . '$/u', $normFirst)) {
                return true;
            }
        }

        $quotedNormLast = preg_quote($normLast, '/');
        return (bool) preg_match('/(?:\s|^)' . $quotedNormLast . '$/u', $normFirst);
    }

    /**
     * تطبيع خفيف للنصوص العربية لمقارنة الأسماء.
     */
    protected function normalizeArabicStringForComparison(string $text): string
    {
        $text = preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $text);
        $text = preg_replace('/[إأآا]/u', 'ا', $text);
        $text = preg_replace('/[ىي]/u', 'ي', $text);
        $text = preg_replace('/[ةه]$/u', 'ه', $text);

        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * يسترجع رابط الأب حتى لو كان النموذج محملاً بأعمدة محدودة بدون parent_id
     * (كما في بعض الاستعلامات المختزلة) — حتى لا تُقطع سلسلة النسب خطأً
     * فيُتوَّج جدٌّ أوسط بأداة التعريف (مثال: "ممدوح الناصر" بدل الاسم الكامل).
     */
    protected function resolveParentLink(Person $model): ?Person
    {
        if ($model->relationLoaded('parent')) {
            return $model->parent;
        }

        if (! array_key_exists('parent_id', $model->getAttributes()) && $model->id) {
            // parent_id لم يُطلب أصلاً في الاستعلام — نجلبه باستعلام خفيف واحد
            // بدل اعتباره جذراً وقطع السلسلة.
            $parentId = static::whereKey($model->id)->value('parent_id');
            $model->setRelation('parent', $parentId ? static::find($parentId) : null);

            return $model->parent;
        }

        return $model->parent;
    }

    /**
     * إضافة أداة التعريف (الـ) إلى اسم الجد الأخير لتمثيل اسم الفرع أو العائلة،
     * مع تجنب التكرار إذا كان الاسم معرفاً بالفعل بـ (الـ) أو (آل) أو مركباً.
     */
    public function prefixWithAl(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '';
        }

        // إذا كان الاسم يبدأ بالفعل بأداة تعريف أو همزة مد/وصل (الـ، آل، أل، إل)
        if (preg_match('/^(ال|آل|أل|إل)/u', $name)) {
            return $name;
        }

        // معالجة الأسماء المركبة
        $words = preg_split('/\s+/u', $name) ?: [];
        if (count($words) > 1) {
            $firstWord = reset($words);
            $lastWord = end($words);

            // إذا كان الاسم يبدأ بـ "عبد" مثل "عبد العزيز" أو "عبد الله" فإنه يعرّف بـ "العبد..."
            if ($firstWord === 'عبد') {
                return 'ال' . $name;
            }

            // إذا كانت الكلمة الأخيرة تبدأ بـ (الـ) أو (آل) مثل: "محمد الصانع"، "يوسف الهويري" فهو يحمل اسم عائلته بالفعل
            if (preg_match('/^(ال|آل|أل|إل)/u', $lastWord)) {
                return $name;
            }
        }

        // إذا كان الاسم يبدأ بألف مجردة (بدون همزة) مثل "ابراهيم" أو "احمد"،
        // نحذف الألف لتجنب تتابع "الا" ونحصل على "البراهيم" / "الحمد" وفق قواعد همزة الوصل
        if (mb_substr($name, 0, 1) === 'ا') {
            return 'ال' . mb_substr($name, 1);
        }

        return 'ال' . $name;
    }


    /**
     * Accessor: الاسم المباشر للشخص كـ alias لـ full_name لضمان التوافق البرمجي في جميع الموديلات والخدمات.
     */
    public function getNameAttribute(): string
    {
        return $this->full_name ?: ($this->first_name ?? '');
    }

    /**
     * Accessor: الاسم الكامل بدون كلمات النسب (بن / بنت).
     * مثال: "محمد أحمد علي" أو "فاطمة أحمد علي"
     */
    public function getFullNameAttribute(): string
    {
        return $this->buildFullName(withLineage: false);
    }

    /**
     * Accessor: الاسم الكامل متضمناً كلمات النسب الشرعية (بن / بنت).
     * مثال: "محمد بن أحمد بن علي" أو "فاطمة بنت أحمد بن علي"
     */
    public function getFullNameWithRelationsAttribute(): string
    {
        return $this->buildFullName(withLineage: true);
    }

    /**
     * Alias لـ full_name_with_relations لضمان التوافق البرمجي (Lineage).
     */
    public function getFullNameWithLineageAttribute(): string
    {
        return $this->getFullNameWithRelationsAttribute();
    }

    /**
     * Alias لـ full_name_with_relations (Bin).
     */
    public function getFullNameWithBinAttribute(): string
    {
        return $this->getFullNameWithRelationsAttribute();
    }

    public function getAgeAttribute(): ?int
    {
        if (!$this->birth_date) {
            return null;
        }

        $endDate = $this->death_date ?? now();
        return $this->birth_date->diffInYears($endDate);
    }

    /**
     * هل العضو يُصنّف كطفل (السن أقل من أو يساوي الحد الأقصى، افتراضياً 15 سنة)
     */
    public function isChild(?int $maxAge = null): bool
    {
        $limit = $maxAge ?? (int) config('auth.child_max_age', 15);
        $age = $this->age;

        return $age !== null && $age <= $limit;
    }

    /**
     * استرجاع ولي أمر الطفل الذي يمتلك رقم واتساب مسجل (الأب أولاً ثم الأم)
     *
     * @return array{parent: Person, relation: string, relation_label: string, whatsapp: string}|null
     */
    public function resolveGuardianWithWhatsapp(): ?array
    {
        // 1. فحص الأب أولاً (parent_id)
        $father = $this->relationLoaded('parent') ? $this->parent : $this->parent()->first();
        if ($father) {
            $fatherWa = $father->contactAccounts()
                ->where('type', 'whatsapp')
                ->whereNotNull('value')
                ->where('value', '!=', '')
                ->first();

            if ($fatherWa && trim((string) $fatherWa->value) !== '') {
                return [
                    'parent' => $father,
                    'relation' => 'father',
                    'relation_label' => 'الأب',
                    'whatsapp' => trim((string) $fatherWa->value),
                ];
            }
        }

        // 2. فحص الأم ثانياً (mother_id)
        $mother = $this->relationLoaded('mother') ? $this->mother : $this->mother()->first();
        if ($mother) {
            $motherWa = $mother->contactAccounts()
                ->where('type', 'whatsapp')
                ->whereNotNull('value')
                ->where('value', '!=', '')
                ->first();

            if ($motherWa && trim((string) $motherWa->value) !== '') {
                return [
                    'parent' => $mother,
                    'relation' => 'mother',
                    'relation_label' => 'الأم',
                    'whatsapp' => trim((string) $motherWa->value),
                ];
            }
        }

        return null;
    }

    /**
     * جلب الأبناء المؤهلين للتسجيل كأطفال (15 سنة فأقل) لهذا الشخص كولي أمر
     *
     * @return \Illuminate\Support\Collection<int, Person>
     */
    public function eligibleChildrenForGuardian(?int $maxAge = null)
    {
        $limit = $maxAge ?? (int) config('auth.child_max_age', 15);

        return static::query()
            ->where(function ($q) {
                $q->where('parent_id', $this->id)
                  ->orWhere('mother_id', $this->id);
            })
            ->whereNotNull('birth_date')
            ->whereNull('death_date')
            ->with(['contactAccounts'])
            ->get()
            ->filter(fn (Person $c) => $c->isChild($limit))
            ->values();
    }

    public function getLifeSpanAttribute(): ?string
    {
        if (!$this->birth_date) {
            return null;
        }

        $startDate = $this->birth_date->format('Y-m-d');
        $endDate = $this->death_date ? $this->death_date->format('Y-m-d') : 'حتى الآن';

        return $startDate . ' - ' . $endDate;
    }

    public function getAvatarAttribute(): ?string
    {
        if (! empty($this->photo_url)) {
            if (str_starts_with($this->photo_url, 'http://') || str_starts_with($this->photo_url, 'https://')) {
                return $this->photo_url;
            }

            $cleanPath = ltrim($this->photo_url, '/');
            if (str_starts_with($cleanPath, 'storage/')) {
                $cleanPath = substr($cleanPath, 8);
            }

            // رابط وسائط الإنتاج المحدد في .env
            $rawEnv = env('MEDIA_BASE_URL');
            $mediaBaseUrl = $rawEnv !== null ? trim((string) $rawEnv) : 'https://alsorayia.com';

            // إذا تم تعيين دومين وسائط خارجي وليس محلياً، نجلبه منه
            if (! empty($mediaBaseUrl) && ! str_contains($mediaBaseUrl, 'localhost') && ! str_contains($mediaBaseUrl, '127.0.0.1')) {
                return rtrim($mediaBaseUrl, '/') . '/storage/' . $cleanPath;
            }

            // التحقق من وجود الملف محلياً أو إرجاع رابط التخزين المحلي
            if (\Illuminate\Support\Facades\Storage::disk('public')->exists($cleanPath) || file_exists(public_path('storage/' . $cleanPath))) {
                return asset('storage/' . $cleanPath);
            }

            return asset('storage/' . $cleanPath);
        }

        // الافتراضي العام لمن لا صورة له — يبقى كما هو دائماً ولا يتأثر بصور المتخفين
        $fallback = $this->gender === 'female' ? 'assets/img/avatar-female-hejap-1.png' : 'assets/img/avatar-male.png';
        if (is_file(public_path($fallback))) {
            return asset($fallback) . '?v=' . filemtime(public_path($fallback));
        }

        return asset($fallback);
    }

    /**
     * الصورة الرمزية العامة للمتسابق المتخفي حسب النوع (بدون كشف الهوية —
     * لا تُستخدم الصورة الحقيقية هنا أبداً).
     * ملفات مخصصة: avatar-anonymous-male.png / avatar-anonymous-female.png
     * في public/assets/img — وإن لم تُرفع بعد يُستخدم الافتراضي العام.
     */
    public static function anonymousAvatarFor(?string $gender): string
    {
        $custom = $gender === 'female'
            ? 'assets/img/avatar-anonymous-female.png'
            : 'assets/img/avatar-anonymous-male.png';
        if (is_file(public_path($custom))) {
            return asset($custom) . '?v=' . filemtime(public_path($custom));
        }

        $fallback = $gender === 'female'
            ? 'assets/img/avatar-female-hejap-1.png'
            : 'assets/img/avatar-male.png';

        if (is_file(public_path($fallback))) {
            return asset($fallback) . '?v=' . filemtime(public_path($fallback));
        }

        return asset($fallback);
    }

    // Relationships

    public function children()
    {
        return $this->hasMany(Person::class, 'parent_id');
    }

    public function parent()
    {
        return $this->belongsTo(Person::class, 'parent_id');
    }

    public static function allWithParentLinks(): Collection
    {
        $people = static::all()->keyBy('id');

        foreach ($people as $person) {
            $person->setRelation('parent', $people->get($person->parent_id));
        }

        return $people;
    }

    public function mother()
    {
        return $this->belongsTo(Person::class, 'mother_id');
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * حسابات التواصل المتعددة للشخص
     */
    public function contactAccounts()
    {
        return $this->hasMany(PersonContactAccount::class)->orderBy('sort_order');
    }

    public function registeredContactPhone(): ?string
    {
        return $this->contactAccounts
            ->whereIn('type', ['whatsapp', 'phone'])
            ->sortBy(fn (PersonContactAccount $account) => $account->type === 'whatsapp' ? 0 : 1)
            ->map(fn (PersonContactAccount $account) => trim((string) $account->value))
            ->first(fn (string $value) => $value !== '');
    }

    /**
     * لوكيشنات متعددة للشخص
     */
    public function personLocations()
    {
        return $this->hasMany(PersonLocation::class)->orderBy('sort_order');
    }

    /**
     * العلاقة مع locations من خلال person_locations
     */
    public function locations()
    {
        return $this->belongsToMany(Location::class, 'person_locations')
                    ->withPivot('label', 'url', 'is_primary', 'sort_order')
                    ->withTimestamps()
                    ->orderBy('person_locations.sort_order');
    }

    public function childrenFromMother()
    {
        return $this->hasMany(Person::class, 'mother_id');
    }


    public function wives()
    {
        return $this->hasManyThrough(Person::class, Marriage::class, 'husband_id', 'id', 'id', 'wife_id');
    }

    // الزوجة (لها زوج واحد)
    public function husband()
    {
        return $this->hasOneThrough(Person::class, Marriage::class, 'wife_id', 'id', 'id', 'husband_id');
    }

    public function articles()
    {
        return $this->hasMany(Article::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'owner_id');
    }

    public function friendships()
    {
        return $this->hasMany(Friendship::class, 'person_id');
    }

    public function friends()
    {
        return $this->belongsToMany(Person::class, 'friendships', 'person_id', 'friend_id')
                    ->withPivot('description', 'friendship_story')
                    ->withTimestamps();
    }

    /**
     * مجموعات الاشعارات التي يتبع لها هذا الشخص
     */
    public function notificationGroups()
    {
        return $this->belongsToMany(NotificationGroup::class, 'notification_group_person', 'person_id', 'notification_group_id')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderBy('notification_group_person.sort_order');
    }

    public function mentionedImages()
    {
        return $this->belongsToMany(Image::class, 'image_mentions', 'person_id', 'image_id')
            ->withTimestamps();
    }

    /**
     * القصص التي يملكها هذا الشخص (صاحب القصة)
     */
    public function ownedStories()
    {
        return $this->hasMany(Story::class, 'story_owner_id');
    }

    /**
     * القصص التي رواها هذا الشخص (راوي)
     */
    public function narratedStories()
    {
        return $this->belongsToMany(Story::class, 'story_narrators')
            ->withTimestamps();
    }
    public function padges()
    {
        // جدول pivot عندك اسمه person_padges
        return $this->belongsToMany(Padge::class, 'person_padges', 'person_id', 'padge_id')
            ->withTimestamps()
            ->withPivot('is_active');
    }

    /**
     * Get breastfeeding relationships where this person is the nursing mother
     * العلاقات التي تكون فيها هذه الشخصية هي الأم المرضعة
     */
    public function nursingRelationships()
    {
        return $this->hasMany(Breastfeeding::class, 'nursing_mother_id');
    }

    /**
     * Get breastfeeding relationships where this person is the breastfed child
     * العلاقات التي تكون فيها هذه الشخصية هي الطفل المرتضع
     */
    public function breastfedRelationships()
    {
        return $this->hasMany(Breastfeeding::class, 'breastfed_child_id');
    }

    /**
     * Get all children that this person has breastfed
     * جميع الأطفال الذين أرضعتهم هذه الشخصية
     */
    public function breastfedChildren()
    {
        return $this->hasManyThrough(Person::class, Breastfeeding::class, 'nursing_mother_id', 'id', 'id', 'breastfed_child_id');
    }

    /**
     * Get all nursing mothers who have breastfed this person
     * جميع الأمهات المرضعات اللاتي أرضعن هذه الشخصية
     */
    public function nursingMothers()
    {
        return $this->hasManyThrough(Person::class, Breastfeeding::class, 'breastfed_child_id', 'id', 'id', 'nursing_mother_id');
    }

    /**
     * Scope for advanced name search including full name patterns
     * نطاق البحث المتقدم في الأسماء بما في ذلك أنماط الاسم الكامل
     */
    public function scopeSearchByName($query, $searchTerm)
    {
        if (empty($searchTerm)) {
            return $query;
        }

        return $query->where(function ($q) use ($searchTerm) {
            $q->where('first_name', 'like', '%' . $searchTerm . '%')
              ->orWhere('last_name', 'like', '%' . $searchTerm . '%')
              ->orWhereRaw("CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) LIKE ?", ['%' . $searchTerm . '%'])
              ->orWhereRaw("CONCAT(first_name, ' بن ', last_name) LIKE ?", ['%' . $searchTerm . '%'])
              ->orWhereRaw("CONCAT(first_name, ' بنت ', last_name) LIKE ?", ['%' . $searchTerm . '%']);
        });
    }

    /**
     * Scope for searching by full_name accessor
     * يبحث في الاسم الكامل مع سلسلة النسب الذكورية ويرتب النتائج بحسب الصلة والأولوية ("الأقرب ثم الأقرب ثم الأبعد")
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string|null $searchTerm
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearchByFullName($query, $searchTerm)
    {
        if (empty($searchTerm)) {
            return $query;
        }

        return app(\App\Services\PersonSearchService::class)->applySearch($query, (string) $searchTerm);
    }

    /**
     * Get active breastfeeding relationships where this person is the nursing mother
     * العلاقات النشطة التي تكون فيها هذه الشخصية هي الأم المرضعة
     */
    public function activeNursingRelationships()
    {
        return $this->nursingRelationships()->active();
    }

    /**
     * Get active breastfeeding relationships where this person is the breastfed child
     * العلاقات النشطة التي تكون فيها هذه الشخصية هي الطفل المرتضع
     */
    public function activeBreastfedRelationships()
    {
        return $this->breastfedRelationships()->active();
    }


    // Accessor لجلب عدد المقالات
    public function getArticlesCountAttribute()
    {
        // نستخدم الخاصية المحملة مسبقًا إذا كانت موجودة لتجنب استعلامات إضافية
        if ($this->relationLoaded('articles')) {
            return $this->articles->count();
        }
        // هذا يعمل كحل بديل إذا لم يتم تحميل العلاقة
        return $this->articles()->count();
    }

    // Accessor لجلب ID أول مقال
    public function getFirstArticleIdAttribute()
    {
        if ($this->relationLoaded('articles') && $this->articles->isNotEmpty()) {
            return $this->articles->first()->id;
        }
        // هذا يعمل كحل بديل إذا لم يتم تحميل العلاقة
        return optional($this->articles()->first())->id;
    }

    /**
     * Accessor لعرض مكان الإقامة بشكل منظم
     */
    public function getLocationDisplayAttribute(): ?string
    {
        if ($this->location) {
            return $this->location->display_name;
        }
        return null;
    }

    /**
     * العلاقة مع مسابقات القرآن الكريم (الفائزين)
     */
    public function quranCompetitionWinners()
    {
        return $this->hasMany(QuranCompetitionWinner::class, 'person_id');
    }

    /**
     * العلاقة مع القائمين على برامج القرآن (الفئات)
     */
    public function quranCategoryManagers()
    {
        return $this->hasMany(QuranCategoryManager::class);
    }

    /**
     * سجلات المشي المرتبطة بهذا الشخص عبر التطبيق
     */
    public function walkingRecords()
    {
        return $this->hasMany(WalkingRecord::class);
    }

    /**
     * المجموعات/التحديات التي ينتمي إليها هذا الشخص (أعضاء مقبولون فقط)
     */
    public function walkingGroups()
    {
        return $this->belongsToMany(WalkingGroup::class, 'walking_group_members', 'person_id', 'walking_group_id')
            ->withPivot('role', 'status', 'joined_at')
            ->withTimestamps()
            ->wherePivot('status', 'accepted');
    }

    /**
     * المجموعات/التحديات التي أنشأها هذا الشخص
     */
    public function createdWalkingGroups()
    {
        return $this->hasMany(WalkingGroup::class, 'created_by_person_id');
    }

    /**
     * الأجهزة المسجلة لهذا الشخص عبر تطبيق الجوال
     */
    public function devices()
    {
        return $this->hasMany(UserDevice::class, 'person_id');
    }

    public function trustedDevices()
    {
        return $this->hasMany(PersonTrustedDevice::class, 'person_id');
    }

    /**
     * حساب المستخدم المرتبط بهذا الشخص
     */
    public function user()
    {
        return $this->hasOne(User::class, 'person_id');
    }

    /**
     * استخراج وتوحيد رقم الجوال/الواتساب المرتبط بالشخص (بصيغة 05XXXXXXXX من 10 أرقام)
     * أو توليد رقم تعريفي ثابت للشخص عند عدم توفر رقم مسجل.
     */
    public function resolveQuizPhone(): string
    {
        $rawPhones = $this->contactAccounts()
            ->whereIn('type', ['whatsapp', 'phone'])
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->orderByRaw("CASE WHEN type = 'whatsapp' THEN 0 ELSE 1 END")
            ->pluck('value')
            ->all();

        foreach ($rawPhones as $raw) {
            $digits = preg_replace('/\D/', '', (string) $raw);
            if ($digits === '') {
                continue;
            }
            if (str_starts_with($digits, '966') && strlen($digits) === 12) {
                return '0' . substr($digits, 3);
            }
            if (strlen($digits) === 10 && str_starts_with($digits, '05')) {
                return $digits;
            }
            if (strlen($digits) === 9 && str_starts_with($digits, '5')) {
                return '0' . $digits;
            }
            if (strlen($digits) >= 10) {
                return substr($digits, -10);
            }
        }

        return '05' . str_pad((string) $this->id, 8, '0', STR_PAD_LEFT);
    }

    /**
     * جميع صيغ أرقام الهواتف المحتملة المرتبطة بالشخص للبحث في جدول المستخدمين (users.phone).
     *
     * @return array<int, string>
     */
    public function getQuizPhoneCandidates(): array
    {
        $candidates = [
            '05' . str_pad((string) $this->id, 8, '0', STR_PAD_LEFT),
        ];

        $rawPhones = $this->contactAccounts()
            ->whereIn('type', ['whatsapp', 'phone'])
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->pluck('value')
            ->all();

        foreach ($rawPhones as $raw) {
            $trimmed = trim((string) $raw);
            if ($trimmed !== '') {
                $candidates[] = $trimmed;
            }
            $digits = preg_replace('/\D/', '', $trimmed);
            if ($digits !== '') {
                $candidates[] = $digits;
                if (str_starts_with($digits, '966') && strlen($digits) === 12) {
                    $local = '0' . substr($digits, 3);
                    $candidates[] = $local;
                    $candidates[] = substr($digits, 3);
                } elseif (strlen($digits) === 10 && str_starts_with($digits, '05')) {
                    $candidates[] = '966' . substr($digits, 1);
                    $candidates[] = '+966' . substr($digits, 1);
                    $candidates[] = substr($digits, 1);
                } elseif (strlen($digits) === 9 && str_starts_with($digits, '5')) {
                    $candidates[] = '0' . $digits;
                    $candidates[] = '966' . $digits;
                    $candidates[] = '+966' . $digits;
                }
            }
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    /**
     * استرجاع معرفات المستخدمين (users.id) المرتبطين بهذا الشخص عبر أرقام هواتفه
     * بأمان تام دون افتراض وجود عمود person_id في جدول users.
     *
     * @return array<int, int>
     */
    public function getLinkedUserIds(): array
    {
        $phones = $this->getQuizPhoneCandidates();
        if (empty($phones)) {
            return [];
        }

        return User::whereIn('phone', $phones)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
