<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PersonSelectResource;
use App\Models\Person;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PersonSearchController extends Controller
{
    /**
     * Search persons endpoint for Select2 AJAX and dynamic form selectors.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        // إذا تم إرسال ID محدد للتحميل المسبق (مثلاً في نماذج التعديل)
        if ($request->filled('id')) {
            $person = Person::query()
                ->with(['parent.parent.parent.parent.parent', 'location'])
                ->find($request->input('id'));

            if ($person) {
                return response()->json([
                    'results' => [
                        (new PersonSelectResource($person))->resolve($request),
                    ],
                ]);
            }

            return response()->json(['results' => []]);
        }

        $term = (string) $request->input('term', $request->input('q', ''));

        // لا نبحث إذا كان النص فارغاً
        if (mb_strlen(trim($term), 'UTF-8') < 1) {
            return response()->json(['results' => []]);
        }

        $query = Person::query()
            ->with(['parent.parent.parent.parent.parent', 'location']);

        // فلترة الجنس (ذكر / أنثى)
        $gender = $request->input('gender');
        if (in_array($gender, ['male', 'female'], true)) {
            $query->where('gender', $gender);
        }

        // استبعاد معرفات معينة (مثل الشخص الحالي لتجنب ربطه بنفسه)
        if ($request->filled('exclude_id')) {
            $excludeIds = is_array($request->input('exclude_id'))
                ? $request->input('exclude_id')
                : explode(',', (string) $request->input('exclude_id'));

            $excludeIds = array_filter(array_map('intval', $excludeIds));
            if (!empty($excludeIds)) {
                $query->whereNotIn('id', $excludeIds);
            }
        }

        // تضمين أو استبعاد الأشخاص من خارج العائلة
        $includeOutside = $request->has('include_outside') ? $request->boolean('include_outside') : true;
        if (!$includeOutside) {
            $query->where('from_outside_the_family', false);
        }

        // تطبيق البحث المتقدم بالاسم الكامل وسلسلة النسب وترتيب الأسبقية
        $searchService = app(\App\Services\PersonSearchService::class);
        $query->searchByFullName($term);

        // حد أعلى للمرشحين قبل الترتيب: الاسم الكامل (توكنز متعددة) يحتاج نافذة أوسع
        // حتى لا يُقصى التطابق الدقيق خارجها (مشكلة الأسماء الشائعة مثل محمد/عبدالله).
        $tokenCount = count($searchService->extractTokens($term));
        $limit = $tokenCount >= 2 ? 200 : 60;

        $people = $query->limit($limit)->get();
        $rankedPeople = $searchService->rankCollection($people, $term)->take(50);

        return response()->json([
            'results' => PersonSelectResource::collection($rankedPeople)->resolve($request),
        ]);
    }
}


