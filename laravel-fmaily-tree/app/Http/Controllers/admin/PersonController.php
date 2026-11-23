<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Person;
use App\Models\Marriage;
use App\Services\ChildOrderService;
use App\Services\TreeCacheService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Exports\PersonsExport;
use Maatwebsite\Excel\Facades\Excel;


class PersonController extends Controller
{
    public function index(Request $request)
    {
        $search = (string) $request->input('search', '');
        $gender = $request->input('gender');
        $familyStatus = $request->input('family_status');

        // بناء الاستعلام الأساسي مع الفلاتر
        $query = Person::query()
            ->when($gender, fn($q) => $q->where('gender', $gender))
            ->when($familyStatus !== null, function ($query) use ($familyStatus) {
                if ($familyStatus === '1') {
                    // داخل العائلة
                    $query->where('from_outside_the_family', false);
                } elseif ($familyStatus === '0') {
                    // خارج العائلة
                    $query->where('from_outside_the_family', true);
                }
            });

        // إذا كان هناك بحث، نطبق محرك البحث الأنسابي المتقدم
        if (trim($search) !== '') {
            $query->searchByFullName($search);
        }

        // تحميل علاقات parent بشكل متداخل حتى عمق 10 لمطابقة full_name accessor
        // نستخدم eager loading متداخل لضمان تحميل جميع العلاقات المطلوبة
        $query->with([
            'location', // تحميل علاقة location
            'parent' => function($q1) {
                $q1->with([
                    'parent' => function($q2) {
                        $q2->with([
                            'parent' => function($q3) {
                                $q3->with([
                                    'parent' => function($q4) {
                                        $q4->with([
                                            'parent' => function($q5) {
                                                $q5->with([
                                                    'parent' => function($q6) {
                                                        $q6->with([
                                                            'parent' => function($q7) {
                                                                $q7->with([
                                                                    'parent' => function($q8) {
                                                                        $q8->with([
                                                                            'parent' => function($q9) {
                                                                                $q9->with('parent');
                                                                            }
                                                                        ]);
                                                                    }
                                                                ]);
                                                            }
                                                        ]);
                                                    }
                                                ]);
                                            }
                                        ]);
                                    }
                                ]);
                            }
                        ]);
                    }
                ]);
            }
        ]);

        // جلب جميع الأشخاص مع علاقات parent محملة
        $allPeople = $query->get();

        // ترتيب النتائج بحسب الصلة والأولوية النسبية ("الأقرب ثم الأقرب ثم الأبعد")
        if (trim($search) !== '') {
            $allPeople = app(\App\Services\PersonSearchService::class)->rankCollection($allPeople, $search);
        }

        // إعادة pagination يدوياً باستخدام LengthAwarePaginator
        $currentPage = request()->get('page', 1);
        $perPage = 10;
        $currentItems = $allPeople->slice(($currentPage - 1) * $perPage, $perPage)->values();
        $people = new \Illuminate\Pagination\LengthAwarePaginator(
            $currentItems,
            $allPeople->count(),
            $perPage,
            $currentPage,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );

        // لو جدولك الحقيقي اسمه "persons" فثبّت اسم الجدول في الموديل:
        // protected $table = 'persons';

        $males = Person::where('gender', 'male')->get();
        $females = Person::where('gender', 'female')->get();

        $stats = Cache::remember('people_stats', now()->addMinutes(60), function () {
            return (array) DB::table('persons')->selectRaw("
            COUNT(*) as total,
            COUNT(CASE WHEN gender = 'male' THEN 1 END) as male,
            COUNT(CASE WHEN gender = 'female' THEN 1 END) as female,
            COUNT(CASE WHEN death_date IS NULL THEN 1 END) as living,
            COUNT(CASE WHEN photo_url IS NOT NULL THEN 1 END) as with_photos
        ")->first();
        });

        return view('dashboard.people.index', compact('people', 'stats', 'males', 'females'));
    }

    public function create()
    {
        $people = Person::allWithParentLinks();

        return view('dashboard.people.create', [
            'males' => $people->where('gender', 'male'),
            'females' => $people->where('gender', 'female'),
        ]);
    }

    public function store(Request $request)
    {
        $isSelfRegistration = $request->routeIs('people.store.self');
        $requiresParents = in_array($request->input('from_outside_the_family'), [false, 0, '0'], true);

        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'birth_date' => 'nullable|date',
            'birth_place' => 'nullable|string|max:150',
            'death_date' => 'nullable|date|after_or_equal:birth_date',
            'death_place' => 'nullable|string|max:150',
            'cemetery' => 'nullable|string|max:150',
            'cemetery_location' => 'nullable|string|max:150',
            'grave_number' => 'nullable|string|max:50',
            'gender' => 'required|in:male,female',
            'from_outside_the_family' => $isSelfRegistration ? 'nullable|boolean' : 'required|boolean',
            'photo' => 'nullable|image|max:2048',
            'biography' => 'nullable|string',
            'occupation' => 'nullable|string|max:100',
            'location_id' => 'nullable|exists:locations,id',
            'parent_id' => $isSelfRegistration
                ? 'nullable|exists:persons,id'
                : [Rule::requiredIf($requiresParents), 'nullable', 'integer', Rule::exists('persons', 'id')->where('gender', 'male')],
            'mother_id' => $isSelfRegistration
                ? 'nullable|exists:persons,id'
                : [Rule::requiredIf($requiresParents), 'nullable', 'integer', Rule::exists('persons', 'id')->where('gender', 'female')],
        ], [
            'from_outside_the_family.required' => 'يرجى اختيار الانتماء للعائلة.',
            'parent_id.required' => 'يرجى اختيار الأب.',
            'mother_id.required' => 'يرجى اختيار الأم.',
            'parent_id.exists' => 'يرجى اختيار أب مسجل.',
            'mother_id.exists' => 'يرجى اختيار أم مسجلة.',
        ]);

        // تأكد من أن اسم الحقل في قاعدة البيانات هو 'photo'
        if ($request->hasFile('photo')) {
            $validated['photo'] = $request->file('photo')->store('people-photos', 'public');
        }

        // معالجة location_id - إذا تم إرسال location_id، نستخدمه
        // إذا تم إرسال location كنص فقط، نستخدم findOrCreateByName
        if ($request->has('location_id') && $request->input('location_id')) {
            $validated['location_id'] = $request->input('location_id');
        } elseif ($request->has('location') && $request->input('location')) {
            $location = \App\Models\Location::findOrCreateByName($request->input('location'));
            $validated['location_id'] = $location->id;
        }

        // استخدام create لإنشاء الشخص مباشرة
        $person = Person::create($validated);

        // ربط الشخص بوالده إذا تم اختياره
        if (!empty($validated['parent_id'])) {
            $parent = Person::find($validated['parent_id']);
            if ($parent) {
                $person->appendToNode($parent)->save();
            }
        }

        // ✨ التحقق من مصدر الطلب لتحديد وجهة إعادة التوجيه
        if ($isSelfRegistration) {
            // إذا كان الطلب من صفحة "إضافة نفسك"، وجهه إلى صفحة عرض الشخص الجديد
            return redirect()->route('people.show', $person->id)
                ->with('success', 'تمت إضافة بياناتك بنجاح! مرحباً بك في تواصل العائلة.');
        } elseif ($request->input('redirect_to') === 'show') {
            return redirect()->route('people.show', $person->id)
                ->with('success', 'تمت إضافة الشخص بنجاح');
        } else {
            // إذا كان الطلب من أي مكان آخر (مثل المودال)، أعده إلى الصفحة السابقة
            return redirect()->back()->with('success', 'تمت إضافة الشخص بنجاح');
        }
    }

    public function edit(Person $person)
    {
        $person->load(['parent.wives']);
        $males = Person::where('gender', 'male')->where('id', '!=', $person->id)->get();

        return view('dashboard.people.edit', compact('person', 'males'));
    }

    public function update(Request $request, Person $person)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'birth_date' => 'nullable|date',
            'birth_place' => 'nullable|string|max:150',
            'death_date' => 'nullable|date|after_or_equal:birth_date',
            'death_place' => 'nullable|string|max:150',
            'cemetery' => 'nullable|string|max:150',
            'cemetery_location' => 'nullable|string|max:150',
            'grave_number' => 'nullable|string|max:50',
            'gender' => 'required|in:male,female',
            'from_outside_the_family' => 'nullable|boolean',
            'photo' => 'nullable|image|max:2048',
            'biography' => 'nullable|string',
            'occupation' => 'nullable|string|max:100',
            'location_id' => 'nullable|exists:locations,id',
            'parent_id' => 'nullable|exists:persons,id',
            'mother_id' => 'nullable|exists:persons,id', // إذا كنت تريد استخدام هذا الحقل
        ]);

        if ($request->hasFile('photo')) {
            if ($person->photo_url) {
                Storage::disk('public')->delete($person->photo_url);
            }
            $validated['photo_url'] = $request->file('photo')->store('people-photos', 'public');
        }

        // معالجة location_id - إذا تم إرسال location_id، نستخدمه
        // إذا تم إرسال location كنص فقط، نستخدم findOrCreateByName
        if ($request->has('location_id') && $request->input('location_id')) {
            $validated['location_id'] = $request->input('location_id');
        } elseif ($request->has('location') && $request->input('location')) {
            $location = \App\Models\Location::findOrCreateByName($request->input('location'));
            $validated['location_id'] = $location->id;
        }

        $person->update($validated);

        if (!empty($validated['parent_id'])) {
            $parent = Person::find($validated['parent_id']);
            $person->appendToNode($parent)->save();
        }

        if ($request->input('redirect_to') === 'show') {
            return redirect()->route('people.show', $person->id)->with('success', 'تم تحديث بيانات الشخص بنجاح');
        }

        return redirect()->back()->with('success', 'تم تحديث بيانات الشخص بنجاح');
    }

    public function destroy(Person $person)
    {
        if ($person->photo_url) {
            Storage::disk('public')->delete($person->photo_url);
        }

        $person->delete();
        return redirect()->back()->with('success', 'تم حذف الشخص بنجاح');
    }

    public function removePhoto(Person $person)
    {
        if ($person->photo_url) {
            Storage::disk('public')->delete($person->photo_url);
            $person->update(['photo_url' => null]);
        }

        return redirect()->back()->with('success', 'تم حذف صورة الشخص بنجاح');
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'order' => 'required|array',
            'order.*.id' => 'required|integer|exists:persons,id',
            'order.*.order' => 'required|integer',
        ]);

        try {
            DB::transaction(function () use ($request) {
                foreach ($request->order as $personData) {
                    Person::where('id', $personData['id'])->update(['display_order' => $personData['order']]);
                }
            });

            // مسح الكاش للشجرة وللآباء/الأمهات المتأثرين لضمان انعكاس الترتيب الجديد فوراً
            $orderService = app(\App\Services\MarriageOrderService::class);
            $personIds = collect($request->order)->pluck('id');
            $parents = Person::whereIn('id', $personIds)
                ->select(['parent_id', 'mother_id'])
                ->get();

            foreach ($parents->pluck('parent_id')->filter()->unique() as $fatherId) {
                $orderService->clearMarriageCaches((int) $fatherId);
            }
            foreach ($parents->pluck('mother_id')->filter()->unique() as $motherId) {
                $orderService->clearMarriageCaches((int) $motherId);
            }
            cache()->forget('family_tree_data');

            return response()->json(['status' => 'success', 'message' => 'تم تحديث الترتيب بنجاح.']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'حدث خطأ أثناء تحديث الترتيب.'], 500);
        }
    }

    public function reorderChildren(Request $request, Person $person)
    {
        $validated = $request->validate([
            'order' => 'required|array|list',
            'order.*' => 'required|integer|distinct',
            'previous_order' => 'sometimes|required|array|list',
            'previous_order.*' => 'required|integer|distinct',
            'confirmed' => 'sometimes|boolean',
        ]);
        $orderedIds = array_map('intval', $validated['order']);
        $expectedOrder = isset($validated['previous_order']) ? array_map('intval', $validated['previous_order']) : null;
        $confirmed = (bool) ($validated['confirmed'] ?? false);

        return DB::transaction(function () use ($person, $orderedIds, $expectedOrder, $confirmed) {
            $parent = Person::lockForUpdate()->findOrFail($person->id);
            $previousOrder = $this->dashboardChildren($parent, lock: true)->modelKeys();
            if (count($orderedIds) !== count($previousOrder) || array_diff($orderedIds, $previousOrder)) {
                throw ValidationException::withMessages(['order' => 'تغيّرت قائمة الأبناء. حدّث الصفحة وحاول مرة ثانية.']);
            }

            if ($expectedOrder !== null && $expectedOrder !== $previousOrder) {
                throw ValidationException::withMessages(['order' => 'تغيّر ترتيب الأبناء. حدّث الصفحة وحاول مرة ثانية.']);
            }

            if ($orderedIds === $previousOrder) {
                return response()->json(['message' => 'ترتيب الأبناء محفوظ.']);
            }
            if (! $confirmed) {
                return response()->json([
                    'code' => 'CHILD_ORDER_CONFIRMATION_REQUIRED',
                    'message' => 'يرجى تأكيد تغيير ترتيب الأبناء قبل الحفظ.',
                ], 409);
            }

            DB::table('dashboard_child_orders')->updateOrInsert(
                ['parent_id' => $parent->id],
                ['positions' => json_encode(['order' => $orderedIds], JSON_THROW_ON_ERROR)],
            );

            DB::afterCommit(fn () => app(TreeCacheService::class)->forgetChildren((int) $parent->id));

            return response()->json(['message' => 'تم حفظ ترتيب الأبناء.']);
        });
    }

    public function search(Request $request)
    {
        $searchTerm = trim((string) $request->input('term', ''));

        // نقوم بالبحث فقط إذا أدخل المستخدم حرفين على الأقل
        if (mb_strlen($searchTerm, 'UTF-8') < 2) {
            return response()->json(['results' => []]);
        }

        try {
            $searchService = app(\App\Services\PersonSearchService::class);
            $limit = count($searchService->extractTokens($searchTerm)) >= 2 ? 200 : 60;

            // توحيد البحث القديم مع محرك البحث الأنسابي (يدعم الأسماء المركبة
            // وعبد/التطبيع والنسخ الكامل من الملف الشخصي).
            $people = Person::query()
                ->with(['parent.parent.parent']) // تحميل حتى 3 مستويات للحصول على full_name
                ->searchByFullName($searchTerm)
                ->limit($limit)
                ->get();

            $people = $searchService->rankCollection($people, $searchTerm);

            // استخدام full_name accessor للعرض
            $results = $people->map(function ($person) {
                try {
                    return [
                        'id' => $person->id,
                        'text' => $person->full_name ?? ($person->first_name . ' ' . $person->last_name),
                        'full_name' => $person->full_name ?? ($person->first_name . ' ' . $person->last_name),
                        'first_name' => $person->first_name ?? '',
                        'last_name' => $person->last_name ?? '',
                        'photo_url' => $person->photo_url ?? null,
                        'gender' => $person->gender ?? 'male',
                    ];
                } catch (\Exception $e) {
                    // في حالة الخطأ، إرجاع البيانات الأساسية
                    return [
                        'id' => $person->id,
                        'text' => ($person->first_name ?? '') . ' ' . ($person->last_name ?? ''),
                        'full_name' => ($person->first_name ?? '') . ' ' . ($person->last_name ?? ''),
                        'first_name' => $person->first_name ?? '',
                        'last_name' => $person->last_name ?? '',
                        'photo_url' => $person->photo_url ?? null,
                        'gender' => $person->gender ?? 'male',
                    ];
                }
            });

            // إرجاع النتائج بالصيغة المطلوبة لمكتبة Select2
            return response()->json(['results' => $results->values()->all()]);
        } catch (\Exception $e) {
            // في حالة الخطأ، إرجاع مصفوفة فارغة
            \Log::error('Error in PersonController@search: ' . $e->getMessage());
            return response()->json(['results' => []], 500);
        }
    }

    public function show(Person $person)
    {
        // تحميل علاقة location
        $person->load(['location', 'contactAccounts', 'personLocations', 'locations']);
        
        $children = $this->dashboardChildren($person);

        // جلب الزوجات أو الزوج مع تفاصيل الزواج مرتبة (الأولى ثم الثانية...)
        $spouses = collect(); // إنشاء collection فارغة
        $marriages = collect(); // إنشاء collection للزواج

        if ($person->gender === 'male') {
            // جلب الزوجات مع تفاصيل الزواج مرتبة
            $marriages = Marriage::where('husband_id', $person->id)
                ->with('wife')
                ->ordered()
                ->get();
            $spouses = $marriages->pluck('wife');
        } elseif (is_object($person->husband)) {
            // جلب الزوج مع تفاصيل الزواج مرتبة
            $marriages = Marriage::where('wife_id', $person->id)
                ->with('husband')
                ->ordered()
                ->get();
            $spouses = $marriages->pluck('husband');
        }

        $persons = Person::allWithParentLinks();

        // تمرير كل البيانات اللازمة إلى الـ view
        return view('dashboard.people.show', [
            'person'   => $person,
            'persons'  => $persons,
            'children' => $children,
            'spouses'  => $spouses,
            'marriages' => $marriages, // إضافة تفاصيل الزواج
            'males'    => $persons->where('gender', 'male'),
            'females'  => $persons->where('gender', 'female'),
        ]);
    }

    private function dashboardChildren(Person $parent, bool $lock = false): Collection
    {
        $query = Person::where($parent->gender === 'female' ? 'mother_id' : 'parent_id', $parent->id);
        if ($lock) {
            $query->lockForUpdate();
        }
        $children = $query->get();

        return app(ChildOrderService::class)->orderForParent($parent, $children);
    }



    public function getWives(Person $father)
    {
        // زوجات مرتبة (الأولى ثم الثانية...) مع بيانات الزواج
        $orderService = app(\App\Services\MarriageOrderService::class);
        $marriages = $orderService->orderedMarriagesForHusband((int) $father->id)->keyBy('wife_id');
        $wives = $orderService->orderedWives((int) $father->id);

        $payload = $wives->values()->map(function ($wife, $index) use ($marriages, $orderService) {
            $m = $marriages->get($wife->id);
            $arr = $wife->toArray();
            $arr['order_index'] = $index + 1;
            $arr['order_label'] = $orderService->arabicOrdinal($index + 1, 'الزوجة');
            $arr['marriage_id'] = $m?->id;
            $arr['married_at'] = $m?->married_at?->format('Y-m-d');
            $arr['sort_order'] = $m?->sort_order;
            return $arr;
        })->values();

        return response()->json($payload);
    }

    public function export(Request $request)
    {
        $search = (string) $request->input('search', '');
        $gender = $request->input('gender');
        $familyStatus = $request->input('family_status');

        // بناء الاستعلام الأساسي مع الفلاتر (نفس منطق index)
        $query = Person::query()
            ->when($gender, fn($q) => $q->where('gender', $gender))
            ->when($familyStatus !== null, function ($query) use ($familyStatus) {
                if ($familyStatus === '1') {
                    $query->where('from_outside_the_family', false);
                } elseif ($familyStatus === '0') {
                    $query->where('from_outside_the_family', true);
                }
            });

        if (trim($search) !== '') {
            $query->searchByFullName($search);
        }

        // تحميل العلاقات المطلوبة لـ full_name accessor
        $query->with([
            'location',
            'parent' => function($q1) {
                $q1->with([
                    'parent' => function($q2) {
                        $q2->with([
                            'parent' => function($q3) {
                                $q3->with([
                                    'parent' => function($q4) {
                                        $q4->with([
                                            'parent' => function($q5) {
                                                $q5->with([
                                                    'parent' => function($q6) {
                                                        $q6->with([
                                                            'parent' => function($q7) {
                                                                $q7->with([
                                                                    'parent' => function($q8) {
                                                                        $q8->with([
                                                                            'parent' => function($q9) {
                                                                                $q9->with('parent');
                                                                            }
                                                                        ]);
                                                                    }
                                                                ]);
                                                            }
                                                        ]);
                                                    }
                                                ]);
                                            }
                                        ]);
                                    }
                                ]);
                            }
                        ]);
                    }
                ]);
            },
            'mother'
        ]);

        // جلب جميع الأشخاص
        $allPeople = $query->get();

        // ترتيب النتائج بحسب الصلة والأولوية النسبية ("الأقرب ثم الأقرب ثم الأبعد")
        if (trim($search) !== '') {
            $allPeople = app(\App\Services\PersonSearchService::class)->rankCollection($allPeople, $search);
        }

        // إنشاء Export class مع الاستعلام المفلتر
        $export = new PersonsExport($allPeople);

        // تصدير الملف
        $fileName = 'persons_' . date('Y-m-d_His') . '.xlsx';
        return Excel::download($export, $fileName);
    }
}
