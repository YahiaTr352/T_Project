<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Marriage;
use App\Models\Person;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;


class MarriageController extends Controller
{
    public function index(Request $request)
    {
        $query = Marriage::with(['husband.parent.parent', 'wife.parent.parent'])
            ->select('marriages.*')
            ->join('persons as husband', 'husband.id', '=', 'marriages.husband_id')
            ->join('persons as wife', 'wife.id', '=', 'marriages.wife_id');

        // التصفية حسب الحالة
        $status = $request->input('status', 'all');

        if ($status === 'active') {
            $query->whereNotNull('married_at')
                ->whereNull('divorced_at')
                ->where('is_divorced', false);
        } elseif ($status === 'divorced') {
            $query->where(function($q) {
                $q->whereNotNull('divorced_at')
                  ->orWhere('is_divorced', true);
            });
        } elseif ($status === 'unknown') {
            $query->whereNull('married_at');
        }

        // البحث حسب الزوج
        if ($request->filled('husband_id')) {
            $query->where('husband_id', $request->husband_id);
        }

        // البحث حسب الزوجة
        if ($request->filled('wife_id')) {
            $query->where('wife_id', $request->wife_id);
        }

        // البحث حسب نطاق التاريخ
        if ($request->filled('date_range')) {
            $dates = explode(' - ', $request->date_range);
            if (count($dates) == 2) {
                $startDate = Carbon::parse($dates[0])->startOfDay();
                $endDate = Carbon::parse($dates[1])->endOfDay();

                $query->where(function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('married_at', [$startDate, $endDate])
                        ->orWhereBetween('divorced_at', [$startDate, $endDate]);
                });
            }
        }

        // الترتيب: عند فلترة بزوج واحد نرتب بترتيب الزوجات (الأولى ثم الثانية...)
        // وإلا نرتب بالأحدث أولاً كما كان
        if ($request->filled('husband_id')) {
            $query->orderByRaw('CASE WHEN marriages.sort_order IS NULL OR marriages.sort_order = 0 THEN 1 ELSE 0 END ASC')
                ->orderBy('marriages.sort_order', 'asc')
                ->orderByRaw('CASE WHEN marriages.married_at IS NULL THEN 1 ELSE 0 END ASC')
                ->orderBy('marriages.married_at', 'asc')
                ->orderBy('marriages.id', 'asc');
        } else {
            $query->orderBy('married_at', 'desc');
        }

        $marriages = $query->paginate(15);

        // خريطة ترتيب الزوجات عند الفلترة بزوج واحد لعرض شارة الأولى/الثانية
        $wifeOrderLabels = [];
        if ($request->filled('husband_id')) {
            $orderService = app(\App\Services\MarriageOrderService::class);
            $map = $orderService->wifeOrderMap((int) $request->husband_id);
            foreach ($map as $wifeId => $idx) {
                $wifeOrderLabels[$wifeId] = $orderService->arabicOrdinal($idx, 'الزوجة');
            }
        }

        // الإحصائيات
        $totalMarriages = Marriage::count();
        $activeMarriages = Marriage::whereNotNull('married_at')
            ->whereNull('divorced_at')
            ->where('is_divorced', false)
            ->count();
        $divorcedMarriages = Marriage::where(function($q) {
            $q->whereNotNull('divorced_at')
              ->orWhere('is_divorced', true);
        })->count();
        $unknownStatusMarriages = Marriage::whereNull('married_at')->count();

        // الأشخاص المختارون للفلترة (لتعبئة Select2 المبدئية فقط)
        $selectedHusband = $request->filled('husband_id') ? Person::find($request->husband_id) : null;
        $selectedWife = $request->filled('wife_id') ? Person::find($request->wife_id) : null;

        return view('dashboard.marriages.index', compact(
            'marriages',
            'status',
            'totalMarriages',
            'activeMarriages',
            'divorcedMarriages',
            'unknownStatusMarriages',
            'selectedHusband',
            'selectedWife',
            'wifeOrderLabels'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'husband_id' => 'required|exists:persons,id',
            'wife_id' => 'required|exists:persons,id|different:husband_id',
            'married_at' => 'nullable|date',
            'divorced_at' => 'nullable|date|after_or_equal:married_at',
            'is_divorced' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:1|max:100',
        ]);

        // =================== بداية منطق التحقق الجديد ===================

        // 1. التحقق مما إذا كانت الزوجة لديها زواج حالي نشط
        $activeMarriage = Marriage::where('wife_id', $request->wife_id)
            ->whereNull('divorced_at') // الزواج لم ينتهِ بالطلاق
            ->first();

        if ($activeMarriage) {
            // 2. إذا كان هناك زواج نشط، تحقق من حالة الزوج السابق
            $previousHusband = Person::find($activeMarriage->husband_id);

            // إذا كان الزوج السابق موجودًا وعلى قيد الحياة (ليس له تاريخ وفاة)
            if ($previousHusband && is_null($previousHusband->death_date)) {
                return redirect()->back()
                    ->withErrors(['error' => 'لا يمكن إتمام الزواج. الزوجة مرتبطة بزواج آخر لم ينتهِ بعد (بالطلاق أو بوفاة الزوج).'])
                    ->withInput();
            }
        }

        // =================== نهاية منطق التحقق الجديد ===================


        // التحقق من عدم وجود سجل زواج مكرر لنفس الشخصين
        $existingMarriage = Marriage::where('husband_id', $request->husband_id)
            ->where('wife_id', $request->wife_id)
            ->exists();

        if ($existingMarriage) {
            return redirect()->back()->withErrors(['error' => 'هذا الزواج مسجل بالفعل!'])->withInput();
        }

        DB::beginTransaction();
        try {
            $orderService = app(\App\Services\MarriageOrderService::class);
            $data = $request->only(['husband_id', 'wife_id', 'married_at', 'divorced_at', 'is_divorced', 'sort_order']);
            if (empty($data['sort_order'])) {
                $max = Marriage::where('husband_id', $data['husband_id'])->max('sort_order');
                $data['sort_order'] = ((int) $max) + 1;
            } else {
                $data['sort_order'] = max(1, min(100, (int) $data['sort_order']));
                // إفساح مكان: إزاحة من عندهم نفس الرقم أو أكبر
                $orderService->makeRoomForSortOrder((int) $data['husband_id'], (int) $data['sort_order']);
            }
            $marriage = Marriage::create($data);

            app(\App\Services\MarriageOrderService::class)
                ->clearMarriageCaches((int) $marriage->husband_id, (int) $marriage->wife_id);

            DB::commit();
            return redirect()->route('marriages.index')->with('success', 'تم إضافة سجل الزواج بنجاح.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['error' => 'حدث خطأ أثناء حفظ البيانات: ' . $e->getMessage()])->withInput();
        }
    }
    public function show(Marriage $marriage)
    {
        return view('dashboard.marriages.show', compact('marriage'));
    }

    public function update(Request $request, Marriage $marriage)
    {
        $request->validate([
            'husband_id' => 'required|exists:persons,id',
            'wife_id' => 'required|exists:persons,id|different:husband_id',
            'married_at' => 'nullable|date',
            'divorced_at' => 'nullable|date|after_or_equal:married_at',
            'is_divorced' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:1|max:100',
        ]);

        // التحقق من عدم وجود زواج سابق بين نفس الشخصين (باستثناء السجل الحالي)
        $existingMarriage = Marriage::where('id', '!=', $marriage->id)
            ->where(function ($query) use ($request) {
                $query->where('husband_id', $request->husband_id)
                    ->where('wife_id', $request->wife_id);
            })->orWhere(function ($query) use ($request) {
                $query->where('husband_id', $request->wife_id)
                    ->where('wife_id', $request->husband_id);
            })->exists();

        if ($existingMarriage) {
            return redirect()->back()->withErrors(['error' => 'هذا الزواج مسجل بالفعل!'])->withInput();
        }

        DB::beginTransaction();
        try {
            $oldHusband = (int) $marriage->husband_id;
            $oldWife = (int) $marriage->wife_id;
            $marriage->update($request->only(['husband_id', 'wife_id', 'married_at', 'divorced_at', 'is_divorced', 'sort_order']));

            $orderService = app(\App\Services\MarriageOrderService::class);
            $orderService->clearMarriageCaches($oldHusband, $oldWife);
            $orderService->clearMarriageCaches((int) $marriage->husband_id, (int) $marriage->wife_id);

            DB::commit();
            return redirect()->route('marriages.index')->with('success', 'تم تحديث سجل الزواج بنجاح.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['error' => 'حدث خطأ أثناء تحديث البيانات: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * حفظ ترتيب الزوجات عند زوج واحد (من الداشبورد — سحب وإفلات أو أسهم).
     * POST dashboard/marriages/reorder { husband_id, ordered_ids: [marriageId...] }
     */
    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'husband_id' => 'required|exists:persons,id',
            'ordered_ids' => 'required|array|min:1',
            'ordered_ids.*' => 'integer|exists:marriages,id',
        ]);

        $husbandId = (int) $validated['husband_id'];
        $marriages = Marriage::where('husband_id', $husbandId)
            ->whereIn('id', $validated['ordered_ids'])
            ->pluck('id')
            ->all();

        if (count($marriages) !== count($validated['ordered_ids'])) {
            return response()->json(['success' => false, 'message' => 'بعض الزيجات لا تخص هذا الزوج.'], 422);
        }

        DB::transaction(function () use ($validated) {
            foreach ($validated['ordered_ids'] as $index => $mid) {
                Marriage::where('id', $mid)->update(['sort_order' => $index + 1]);
            }
        });

        $orderService = app(\App\Services\MarriageOrderService::class);
        $orderService->clearMarriageCaches($husbandId);
        foreach (Marriage::where('husband_id', $husbandId)->pluck('wife_id') as $wid) {
            $orderService->clearMarriageCaches($husbandId, (int) $wid);
        }
        // مسح كاش صلة القرابة المرتبط بهذا الزوج
        foreach (['kinship_v7_'] as $prefix) {
            // لا نمسح كل المفاتيح لثقلها — الاعتماد على TTL 30 دقيقة للباقي
            unset($prefix);
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'تم حفظ الترتيب بنجاح.']);
        }

        return redirect()->back()->with('success', 'تم حفظ ترتيب الزوجات بنجاح.');
    }

    public function destroy(Marriage $marriage)
    {
        DB::beginTransaction();
        try {
            $husbandId = (int) $marriage->husband_id;
            $wifeId = (int) $marriage->wife_id;
            $marriage->delete();

            app(\App\Services\MarriageOrderService::class)
                ->clearMarriageCaches($husbandId, $wifeId);

            DB::commit();
            return redirect()->route('marriages.index')->with('success', 'تم حذف سجل الزواج بنجاح.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['error' => 'حدث خطأ أثناء حذف البيانات: ' . $e->getMessage()]);
        }
    }
}
