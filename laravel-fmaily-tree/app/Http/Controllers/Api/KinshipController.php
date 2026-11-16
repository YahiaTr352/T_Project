<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\KinshipCalculatorService;
use App\Services\SiteIdentityResolver;
use App\Models\Person;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class KinshipController extends Controller
{
    public function __construct(
        protected KinshipCalculatorService $kinshipService,
        protected SiteIdentityResolver $identityResolver
    ) {}

    /**
     * Calculate kinship degree, LCA, path, and details between two family members.
     *
     * GET /api/v1/kinship/calculate
     * Query Parameters:
     * - person_a_id (optional): ID of Person A. Defaults to verified face/WhatsApp identity.
     * - person_b_id (required): ID of Person B to compare against.
     */
    public function calculate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'person_a_id' => ['nullable', 'integer', 'exists:persons,id'],
            'person_b_id' => ['required', 'integer', 'exists:persons,id'],
        ], [
            'person_b_id.required' => 'يجب تحديد الشخص المراد مقارنته.',
            'person_b_id.exists' => 'الشخص المختار غير موجود في شجرة العائلة.',
            'person_a_id.exists' => 'الشخص الأول غير موجود في شجرة العائلة.',
        ]);

        $personA = null;

        if (!empty($validated['person_a_id'])) {
            $personA = Person::find($validated['person_a_id']);
        } else {
            $personA = $this->kinshipService->resolvePersonForUser();
        }

        if (!$personA) {
            return response()->json([
                'success' => false,
                'data' => [
                    'requires_person_a_selection' => true,
                    'requires_identity' => ! $this->identityResolver->hasVerifiedIdentity($request),
                ],
                'message' => 'ادخل ببصمة الوجه أو واتساب لمقارنة صلة القرابة معك، أو اختر الطرف الأول يدوياً.',
                'errors' => null,
            ], 200);
        }

        $personBId = (int) $validated['person_b_id'];

        if ((int) $personA->id === $personBId) {
            return response()->json([
                'success' => false,
                'data' => [
                    'is_self' => true,
                ],
                'message' => 'لا يمكن مقارنة الشخص بنفسه.',
                'errors' => null,
            ], 200);
        }

        $personB = Person::find($personBId);
        if (!$personB) {
            return response()->json([
                'success' => false,
                'message' => 'الشخص المراد مقارنته غير موجود في النظام.',
                'errors' => null,
            ], 404);
        }

        $resultDTO = $this->kinshipService->calculateKinship($personA->id, $personBId);

        return response()->json([
            'success' => true,
            'data' => $resultDTO->toArray(),
            'message' => 'تم حساب صلة القرابة والنسب بنجاح.',
            'errors' => null,
        ]);
    }
}
