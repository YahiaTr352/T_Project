<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Marriage;
use App\Models\Person;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

class DashboardController extends Controller
{
  public function index()
    {
        // تصفية الأشخاص داخل العائلة فقط
        $familyQuery = Person::where('from_outside_the_family', false);
        
        // --- 1. الإحصائيات الرئيسية ---
        $totalPeople = $familyQuery->count();
        $totalMarriages = Marriage::whereHas('husband', function($q) {
            $q->where('from_outside_the_family', false);
        })->orWhereHas('wife', function($q) {
            $q->where('from_outside_the_family', false);
        })->count();

        // الأشخاص الأحياء والمتوفين
        $alivePeople = (clone $familyQuery)->whereNull('death_date')->count();
        $deceasedPeople = (clone $familyQuery)->whereNotNull('death_date')->count();
        
        // عدد الذكور والإناث
        $maleCount = (clone $familyQuery)->where('gender', 'male')->count();
        $femaleCount = (clone $familyQuery)->where('gender', 'female')->count();
        
        // الأشخاص المضافة هذا الشهر
        $peopleAddedThisMonth = (clone $familyQuery)
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->count();
        
        // الزيجات هذا الشهر (على الأقل أحد الزوجين من العائلة)
        $marriagesThisMonth = Marriage::whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->where(function($q) {
                $q->whereHas('husband', function($q2) {
                    $q2->where('from_outside_the_family', false);
                })->orWhereHas('wife', function($q2) {
                    $q2->where('from_outside_the_family', false);
                });
            })
            ->count();

        // حساب عدد الأجيال
        $roots = (clone $familyQuery)->whereNull('parent_id')->get();
        $totalGenerations = 0;
        foreach ($roots as $root) {
            $descendants = $root->descendants()->where('from_outside_the_family', false)->get();
            if ($descendants->isNotEmpty()) {
                $maxDepth = $descendants->max(function($person) {
                    return $person->depth ?? 0;
                }) ?? 0;
                $totalGenerations = max($totalGenerations, $maxDepth + 1);
            } else {
                $totalGenerations = max($totalGenerations, 1);
            }
        }

        // --- 2. أحداث اليوم في تاريخ العائلة ---
        $today = Carbon::today();
        $birthsToday = (clone $familyQuery)
            ->whereMonth('birth_date', $today->month)
            ->whereDay('birth_date', $today->day)
            ->get();

        $marriagesToday = Marriage::whereMonth('married_at', $today->month)
            ->whereDay('married_at', $today->day)
            ->where(function($q) {
                $q->whereHas('husband', function($q2) {
                    $q2->where('from_outside_the_family', false);
                })->orWhereHas('wife', function($q2) {
                    $q2->where('from_outside_the_family', false);
                });
            })
            ->with(['husband', 'wife'])
            ->get();

        // --- 3. أفراد أضيفوا مؤخراً ---
        $recentlyAdded = (clone $familyQuery)->latest()->take(5)->get();

        // --- 4. أعياد ميلاد قادمة هذا الشهر ---
        $upcomingBirthdays = (clone $familyQuery)
            ->whereNull('death_date')
            ->whereMonth('birth_date', $today->month)
            ->whereDay('birth_date', '>=', $today->day)
            ->orderByRaw('DAY(birth_date)')
            ->take(10)
            ->get();

        // --- 5. حقائق شيقة عن العائلة ---
        $mostChildrenParentData = DB::table('persons')
            ->select('parent_id', DB::raw('COUNT(id) as children_count'))
            ->where('from_outside_the_family', false)
            ->whereNotNull('parent_id')
            ->groupBy('parent_id')
            ->orderBy(DB::raw('COUNT(id)'), 'desc')
            ->first();

        $personWithMostChildren = null;
        if ($mostChildrenParentData) {
            $personWithMostChildren = Person::where('from_outside_the_family', false)
                ->find($mostChildrenParentData->parent_id);
            if ($personWithMostChildren) {
                $personWithMostChildren->children_count = $mostChildrenParentData->children_count;
            }
        }
        
        // أكبر شخص سناً (على قيد الحياة)
        $oldestPerson = (clone $familyQuery)
            ->whereNull('death_date')
            ->whereNotNull('birth_date')
            ->orderBy('birth_date', 'asc')
            ->first();
        
        // تحليل بيانات الزواج للحقائق الشيقة (حديث معاصر vs توثيق تاريخي)
        $marriageQuery = Marriage::where(function($q) {
            $q->whereHas('husband', function($q2) {
                $q2->where('from_outside_the_family', false);
            })->orWhereHas('wife', function($q2) {
                $q2->where('from_outside_the_family', false);
            });
        })->with(['husband', 'wife'])->whereNotNull('married_at');

        // 1. البحث عن أحدث زواج معاصر (خلال آخر 25 سنة)
        $recentMarriage = (clone $marriageQuery)
            ->where('married_at', '>=', Carbon::now()->subYears(25))
            ->latest('married_at')
            ->first();

        $marriageFact = null;
        $latestMarriage = null;

        if ($recentMarriage && $recentMarriage->married_at) {
            $marriageFact = [
                'type' => 'recent',
                'title' => 'أحدث عقد قران',
                'icon' => 'fe-heart',
                'iconColor' => 'text-danger',
                'badgeClass' => 'badge-soft-danger',
                'husband' => $recentMarriage->husband->full_name ?? 'العريس',
                'wife' => $recentMarriage->wife->full_name ?? 'العروس',
                'badge' => $recentMarriage->married_at->diffForHumans(),
                'subtitle' => 'بارك الله لهما وجمع بينهما في خير',
                'marriage' => $recentMarriage,
            ];
            $latestMarriage = $recentMarriage;
        } else {
            // 2. إذا كانت التواريخ كلها قديمة، نعرض أقدم زواج موثّق تاريخياً في شجرة العائلة
            $historicalMarriage = (clone $marriageQuery)
                ->oldest('married_at')
                ->first();

            if ($historicalMarriage && $historicalMarriage->married_at) {
                $yearsAgo = Carbon::now()->diffInYears($historicalMarriage->married_at);
                $marriageFact = [
                    'type' => 'historical',
                    'title' => 'أقدم زواج موثّق',
                    'icon' => 'fe-bookmark',
                    'iconColor' => 'text-warning',
                    'badgeClass' => 'badge-soft-warning',
                    'husband' => $historicalMarriage->husband->full_name ?? 'العريس',
                    'wife' => $historicalMarriage->wife->full_name ?? 'العروس',
                    'badge' => 'عام ' . $historicalMarriage->married_at->format('Y') . 'م (قبل ' . $yearsAgo . ' عاماً)',
                    'subtitle' => 'توثيق تاريخي مسجل في أرشيف العائلة',
                    'marriage' => $historicalMarriage,
                ];
                $latestMarriage = $historicalMarriage;
            }
        }
        
        // عدد الأشخاص المضافة هذا الأسبوع
        $peopleAddedThisWeek = (clone $familyQuery)
            ->whereBetween('created_at', [
                Carbon::now()->startOfWeek(),
                Carbon::now()->endOfWeek()
            ])->count();

        // --- 6. إحصائيات إضافية جديدة ---
        // عدد الأشخاص الذين لديهم أوسمة
        $peopleWithBadges = (clone $familyQuery)->whereHas('padges')->count();
        
        // عدد الصور المذكور فيها أشخاص من العائلة
        $totalImages = \App\Models\Image::whereHas('mentionedPersons', function($q) {
            $q->where('from_outside_the_family', false);
        })->count();
        
        // عدد المقالات
        $totalArticles = \App\Models\Article::whereHas('person', function($q) {
            $q->where('from_outside_the_family', false);
        })->count();
        
        // عدد البرامج النشطة
        $activePrograms = \App\Models\Image::where('is_program', true)
            ->where('program_is_active', true)
            ->count();
        
        // عدد المناسبات النشطة
        $activeEvents = \App\Models\FamilyEvent::where('is_active', true)->count();
        
        // عدد المجالس
        $totalCouncils = \App\Models\FamilyCouncil::where('is_active', true)->count();
        
        // عدد الأوسمة
        $totalBadges = \App\Models\Padge::where('is_active', true)->count();
        
        // عدد القصص
        $totalStories = \App\Models\Story::whereHas('storyOwner', function($q) {
            $q->where('from_outside_the_family', false);
        })->count();

        // --- 7. مؤشرات التوازن الديموغرافي واكتمال البيانات ---
        $malePercentage = $totalPeople > 0 ? round(($maleCount / $totalPeople) * 100, 1) : 0;
        $femalePercentage = $totalPeople > 0 ? round(($femaleCount / $totalPeople) * 100, 1) : 0;
        $alivePercentage = $totalPeople > 0 ? round(($alivePeople / $totalPeople) * 100, 1) : 0;
        $deceasedPercentage = $totalPeople > 0 ? round(($deceasedPeople / $totalPeople) * 100, 1) : 0;

        $peopleWithPhotos = (clone $familyQuery)->whereNotNull('photo_url')->where('photo_url', '!=', '')->count();
        $peopleWithBirthDate = (clone $familyQuery)->whereNotNull('birth_date')->count();
        $photoCompleteness = $totalPeople > 0 ? round(($peopleWithPhotos / $totalPeople) * 100, 1) : 0;
        $birthDateCompleteness = $totalPeople > 0 ? round(($peopleWithBirthDate / $totalPeople) * 100, 1) : 0;

        // حركة الزيارات
        $totalVisits = \App\Models\VisitLog::count();
        $todayVisits = \App\Models\VisitLog::whereDate('created_at', Carbon::today())->count();

        // المهام المعلقة والتنبيهات الإدارية
        $pendingProfileUpdates = \App\Models\ProfileUpdateRequest::where('status', 'pending')->count();
        $pendingRentalRequests = \App\Models\RentalRequest::where('status', 'pending')->count();
        $pendingImportantLinks = \App\Models\ImportantLink::where('status', 'pending')->count();
        $pendingInvitations = \App\Models\Notification::where('status', 'pending_approval')->count();
        $pendingTotal = $pendingProfileUpdates + $pendingRentalRequests + $pendingImportantLinks + $pendingInvitations;

        // --- إرسال جميع البيانات إلى الـ View ---
        return view('dashboard.index', [
            'stats' => [
                'totalPeople' => $totalPeople,
                'alivePeople' => $alivePeople,
                'deceasedPeople' => $deceasedPeople,
                'maleCount' => $maleCount,
                'femaleCount' => $femaleCount,
                'malePercentage' => $malePercentage,
                'femalePercentage' => $femalePercentage,
                'alivePercentage' => $alivePercentage,
                'deceasedPercentage' => $deceasedPercentage,
                'peopleWithPhotos' => $peopleWithPhotos,
                'photoCompleteness' => $photoCompleteness,
                'peopleWithBirthDate' => $peopleWithBirthDate,
                'birthDateCompleteness' => $birthDateCompleteness,
                'totalMarriages' => $totalMarriages,
                'totalGenerations' => $totalGenerations,
                'peopleAddedThisMonth' => $peopleAddedThisMonth,
                'marriagesThisMonth' => $marriagesThisMonth,
                'peopleAddedThisWeek' => $peopleAddedThisWeek,
                'peopleWithBadges' => $peopleWithBadges,
                'totalImages' => $totalImages,
                'totalArticles' => $totalArticles,
                'activePrograms' => $activePrograms,
                'activeEvents' => $activeEvents,
                'totalCouncils' => $totalCouncils,
                'totalBadges' => $totalBadges,
                'totalStories' => $totalStories,
                'totalVisits' => $totalVisits,
                'todayVisits' => $todayVisits,
                'pendingProfileUpdates' => $pendingProfileUpdates,
                'pendingRentalRequests' => $pendingRentalRequests,
                'pendingImportantLinks' => $pendingImportantLinks,
                'pendingInvitations' => $pendingInvitations,
                'pendingTotal' => $pendingTotal,
            ],
            'events' => [
                'birthsToday' => $birthsToday,
                'marriagesToday' => $marriagesToday,
            ],
            'recentlyAdded' => $recentlyAdded,
            'upcomingBirthdays' => $upcomingBirthdays,
            'funFact' => [
                'personWithMostChildren' => $personWithMostChildren,
                'oldestPerson' => $oldestPerson,
                'latestMarriage' => $latestMarriage,
                'marriageFact' => $marriageFact,
            ]
        ]);
    }

    /**
     * مسح التخزين المؤقت
     */
    public function clearCache(Request $request): \Illuminate\Http\JsonResponse
    {
        $type = $request->input('type', 'all');

        $commands = [
            'view'     => ['command' => 'view:clear',    'label' => 'ملفات العرض (Views)'],
            'config'   => ['command' => 'config:clear',  'label' => 'الإعدادات (Config)'],
            'route'    => ['command' => 'route:clear',   'label' => 'المسارات (Routes)'],
            'cache'    => ['command' => 'cache:clear',   'label' => 'التخزين المؤقت (Cache)'],
            'event'    => ['command' => 'event:clear',   'label' => 'الأحداث (Events)'],
            'compiled' => ['command' => 'clear-compiled','label' => 'الملفات المجمعة (Compiled)'],
        ];

        try {
            $cleared = [];

            if ($type === 'all') {
                foreach ($commands as $key => $cmd) {
                    Artisan::call($cmd['command']);
                    $cleared[] = $cmd['label'];
                }
                $message = 'تم مسح جميع أنواع التخزين المؤقت بنجاح';
            } elseif (isset($commands[$type])) {
                Artisan::call($commands[$type]['command']);
                $cleared[] = $commands[$type]['label'];
                $message = 'تم مسح ' . $commands[$type]['label'] . ' بنجاح';
            } else {
                return response()->json(['success' => false, 'message' => 'نوع غير معروف'], 400);
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'cleared' => $cleared,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: ' . $e->getMessage(),
            ], 500);
        }
    }
}
