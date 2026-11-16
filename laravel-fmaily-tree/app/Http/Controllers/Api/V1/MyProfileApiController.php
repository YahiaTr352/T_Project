<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Marriage;
use App\Models\Person;
use App\Models\PersonContactAccount;
use App\Models\PersonLocation;
use App\Services\MyProfileService;
use App\Services\ProfileUpdateRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class MyProfileApiController extends Controller
{
    public function __construct(
        protected MyProfileService $profileService,
        protected ProfileUpdateRequestService $updateRequestService
    ) {}

    public function show(Request $request): JsonResponse
    {
        /** @var Person $person */
        $person = $request->attributes->get('identity_person');
        $authMethod = $request->attributes->get('identity_auth_method');

        try {
            return $this->ok(
                'تم جلب بيانات الملف الشخصي.',
                $this->profileService->profileApiPayload($person, $authMethod)
            );
        } catch (\Throwable $e) {
            // تسجيل مفصّل حتى يظهر السبب الحقيقي في storage/logs/laravel.log على السيرفر
            \Illuminate\Support\Facades\Log::error('[MyProfileApi.show] failed', [
                'person_id' => $person?->id,
                'auth_method' => $authMethod,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
                'trace' => substr($e->getTraceAsString(), 0, 2000),
            ]);
            throw $e;
        }
    }

    public function update(Request $request): JsonResponse
    {
        /** @var Person $person */
        $person = $request->attributes->get('identity_person');
        $authMethod = (string) $request->attributes->get('identity_auth_method', 'api');

        $validated = $request->validate([
            'birth_date' => 'nullable|date',
            'birth_place' => 'nullable|string|max:150',
            'death_date' => 'nullable|date|after_or_equal:birth_date',
            'death_place' => 'nullable|string|max:150',
            'cemetery' => 'nullable|string|max:150',
            'cemetery_location' => 'nullable|string|max:150',
            'grave_number' => 'nullable|string|max:50',
            'biography' => 'nullable|string',
            'occupation' => 'nullable|string|max:100',
            'location_id' => 'nullable|exists:locations,id',
            'photo' => 'nullable',
            'photo_base64' => 'nullable|string',
        ]);
        unset($validated['first_name'], $validated['last_name']);

        $photo = $request->file('photo');
        if (!$photo && str_contains((string) $request->header('Content-Type'), 'multipart/form-data')) {
            $photo = $this->extractUploadedFileFromRawInput($request, 'photo');
        }
        if (!$photo && !empty($validated['photo_base64'])) {
            $photo = $validated['photo_base64'];
        } elseif (!$photo && is_string($request->input('photo')) && strlen((string)$request->input('photo')) > 100) {
            $photo = (string) $request->input('photo');
        }

        $req = $this->updateRequestService->submitProfileUpdate(
            person: $person,
            data: $validated,
            photo: $photo,
            authMethod: $authMethod
        );

        return $this->ok('تم إرسال طلب تعديل البيانات وهو قيد مراجعة واعتماد الإدارة.', [
            'request_id' => $req->id,
            'status' => 'pending',
        ]);
    }

    public function listContacts(Request $request): JsonResponse
    {
        /** @var Person $person */
        $person = $request->attributes->get('identity_person');

        return $this->ok('حسابات التواصل.', [
            'contacts' => $person->contactAccounts,
        ]);
    }

    public function updatePrivacy(Request $request): JsonResponse
    {
        /** @var Person $person */
        $person = $request->attributes->get('identity_person');

        $validated = $request->validate([
            'contacts' => 'nullable|in:public,family,private',
            'photo' => 'nullable|in:public,family,private',
            'birth_date' => 'nullable|in:public,family,private',
            'location' => 'nullable|in:public,family,private',
            'biography' => 'nullable|in:public,family,private',
            'occupation' => 'nullable|in:public,family,private',
            'contact_visibilities' => 'nullable|array',
            'contact_visibilities.*.id' => 'required_with:contact_visibilities|integer',
            'contact_visibilities.*.visibility' => 'required_with:contact_visibilities|in:public,family,private',
        ]);

        $settings = array_intersect_key($validated, array_flip([
            'contacts', 'photo', 'birth_date', 'location', 'biography', 'occupation'
        ]));

        $updatedSettings = $this->profileService->updatePrivacySettings($person, $settings);

        if (!empty($validated['contact_visibilities'])) {
            foreach ($validated['contact_visibilities'] as $cv) {
                PersonContactAccount::where('id', $cv['id'])
                    ->where('person_id', $person->id)
                    ->update(['visibility' => $cv['visibility']]);
            }
        }

        return $this->ok('تم حفظ إعدادات الخصوصية بنجاح.', [
            'privacy_settings' => $updatedSettings,
            'contacts' => $person->fresh()->contactAccounts,
        ]);
    }

    public function storeContact(Request $request): JsonResponse
    {
        /** @var Person $person */
        $person = $request->attributes->get('identity_person');
        $authMethod = (string) $request->attributes->get('identity_auth_method', 'api');

        $validated = $request->validate([
            'type' => 'required|in:phone,whatsapp,email,facebook,instagram,twitter,linkedin,telegram,other',
            'value' => 'required|string|max:255',
            'visibility' => 'nullable|in:public,family,private',
            'label' => 'nullable|string|max:100',
        ]);

        $req = $this->updateRequestService->submitContactChange(
            person: $person,
            action: 'add',
            data: $validated,
            authMethod: $authMethod
        );

        return $this->ok('تم إرسال طلب إضافة حساب التواصل للمراجعة والاعتماد.', ['request_id' => $req->id, 'status' => 'pending'], 201);
    }

    public function updateContact(Request $request, PersonContactAccount $contact): JsonResponse
    {
        /** @var Person $person */
        $person = $request->attributes->get('identity_person');
        $authMethod = (string) $request->attributes->get('identity_auth_method', 'api');

        $validated = $request->validate([
            'type' => 'sometimes|required|in:phone,whatsapp,email,facebook,instagram,twitter,linkedin,telegram,other',
            'value' => 'sometimes|required|string|max:255',
            'visibility' => 'nullable|in:public,family,private',
            'label' => 'nullable|string|max:100',
        ]);

        // إذا تم إرسال تغيير في مستوى الخصوصية للحساب فقط أو مع التحديث
        if (!empty($validated['visibility']) && $contact->person_id === $person->id) {
            $contact->update(['visibility' => $validated['visibility']]);
        }

        $req = $this->updateRequestService->submitContactChange(
            person: $person,
            action: 'update',
            data: $validated,
            contact: $contact,
            authMethod: $authMethod
        );

        return $this->ok('تم إرسال طلب تعديل حساب التواصل للمراجعة والاعتماد.', ['request_id' => $req->id, 'status' => 'pending']);
    }

    public function destroyContact(Request $request, PersonContactAccount $contact): JsonResponse
    {
        /** @var Person $person */
        $person = $request->attributes->get('identity_person');
        $authMethod = (string) $request->attributes->get('identity_auth_method', 'api');

        $req = $this->updateRequestService->submitContactChange(
            person: $person,
            action: 'delete',
            contact: $contact,
            authMethod: $authMethod
        );

        return $this->ok('تم إرسال طلب حذف حساب التواصل للمراجعة والاعتماد.', ['request_id' => $req->id, 'status' => 'pending']);
    }

    public function storeChild(Request $request): JsonResponse
    {
        /** @var Person $person */
        $person = $request->attributes->get('identity_person');
        $authMethod = (string) $request->attributes->get('identity_auth_method', 'api');

        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'gender' => 'required|in:male,female',
            'birth_date' => 'nullable|date',
            'birth_place' => 'nullable|string|max:150',
            'mother_id' => 'nullable|exists:persons,id',
            'parent_id' => 'nullable|exists:persons,id',
            'photo' => 'nullable',
            'photo_base64' => 'nullable|string',
        ]);

        $photo = $request->file('photo');
        if (!$photo && str_contains((string) $request->header('Content-Type'), 'multipart/form-data')) {
            $photo = $this->extractUploadedFileFromRawInput($request, 'photo');
        }
        if (!$photo && !empty($validated['photo_base64'])) {
            $photo = $validated['photo_base64'];
        } elseif (!$photo && is_string($request->input('photo')) && strlen((string)$request->input('photo')) > 100) {
            $photo = (string) $request->input('photo');
        }

        $req = $this->updateRequestService->submitChildAdd(
            owner: $person,
            data: $validated,
            photo: $photo,
            authMethod: $authMethod
        );

        return $this->ok('تم إرسال طلب إضافة الابن/الابنة للمراجعة والاعتماد.', ['request_id' => $req->id, 'status' => 'pending'], 201);
    }

    public function listMarriages(Request $request): JsonResponse
    {
        /** @var Person $person */
        $person = $request->attributes->get('identity_person');
        $payload = $this->profileService->profilePayload($person);

        return $this->ok('سجلات الزواج.', ['marriages' => $payload['marriages']]);
    }

    public function storeMarriage(Request $request): JsonResponse
    {
        /** @var Person $person */
        $person = $request->attributes->get('identity_person');
        $authMethod = (string) $request->attributes->get('identity_auth_method', 'api');

        $validated = $request->validate([
            'spouse_type' => 'nullable|in:existing,outside_family,inside_family',
            'husband_id' => 'nullable|exists:persons,id',
            'wife_id' => 'nullable|exists:persons,id',
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'parent_id' => 'nullable|exists:persons,id',
            'birth_date' => 'nullable|date',
            'married_at' => 'nullable|date',
            'divorced_at' => 'nullable|date|after_or_equal:married_at',
            'is_divorced' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:1|max:100',
            'photo' => 'nullable',
            'photo_base64' => 'nullable|string',
        ]);

        $photo = $request->file('photo');
        if (!$photo && str_contains((string) $request->header('Content-Type'), 'multipart/form-data')) {
            $photo = $this->extractUploadedFileFromRawInput($request, 'photo');
        }
        if (!$photo && !empty($validated['photo_base64'])) {
            $photo = $validated['photo_base64'];
        } elseif (!$photo && is_string($request->input('photo')) && strlen((string) $request->input('photo')) > 100) {
            $photo = (string) $request->input('photo');
        }

        $req = $this->updateRequestService->submitMarriageChange(
            owner: $person,
            action: 'add',
            data: $validated,
            marriage: null,
            photo: $photo,
            authMethod: $authMethod
        );

        return $this->ok('تم إرسال طلب إضافة سجل الزواج للمراجعة والاعتماد.', ['request_id' => $req->id, 'status' => 'pending'], 201);
    }

    public function updateMarriage(Request $request, Marriage $marriage): JsonResponse
    {
        /** @var Person $person */
        $person = $request->attributes->get('identity_person');
        $authMethod = (string) $request->attributes->get('identity_auth_method', 'api');

        $validated = $request->validate([
            'married_at' => 'nullable|date',
            'divorced_at' => 'nullable|date|after_or_equal:married_at',
            'is_divorced' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:1|max:100',
        ]);

        $req = $this->updateRequestService->submitMarriageChange(
            owner: $person,
            action: 'update',
            data: $validated,
            marriage: $marriage,
            authMethod: $authMethod
        );

        return $this->ok('تم إرسال طلب تعديل سجل الزواج للمراجعة والاعتماد.', ['request_id' => $req->id, 'status' => 'pending']);
    }

    public function listLocations(Request $request): JsonResponse
    {
        /** @var Person $person */
        $person = $request->attributes->get('identity_person');
        $payload = $this->profileService->profilePayload($person);

        return $this->ok('أماكن الإقامة.', ['locations' => $payload['locations']]);
    }

    public function storeLocation(Request $request): JsonResponse
    {
        /** @var Person $person */
        $person = $request->attributes->get('identity_person');
        $authMethod = (string) $request->attributes->get('identity_auth_method', 'api');

        $validated = $request->validate([
            'location_id' => 'required|exists:locations,id',
            'label' => 'nullable|string|max:100',
            'url' => 'nullable|url|max:1000',
            'is_primary' => 'nullable|boolean',
        ]);

        $req = $this->updateRequestService->submitLocationChange(
            person: $person,
            action: 'add',
            data: $validated,
            authMethod: $authMethod
        );

        return $this->ok('تم إرسال طلب إضافة الموقع للمراجعة والاعتماد.', ['request_id' => $req->id, 'status' => 'pending'], 201);
    }

    public function updateLocation(Request $request, PersonLocation $location): JsonResponse
    {
        /** @var Person $person */
        $person = $request->attributes->get('identity_person');
        $authMethod = (string) $request->attributes->get('identity_auth_method', 'api');

        $validated = $request->validate([
            'location_id' => 'sometimes|required|exists:locations,id',
            'label' => 'nullable|string|max:100',
            'url' => 'nullable|url|max:1000',
            'is_primary' => 'nullable|boolean',
        ]);

        $req = $this->updateRequestService->submitLocationChange(
            person: $person,
            action: 'update',
            data: $validated,
            location: $location,
            authMethod: $authMethod
        );

        return $this->ok('تم إرسال طلب تعديل الموقع للمراجعة والاعتماد.', ['request_id' => $req->id, 'status' => 'pending']);
    }

    public function destroyLocation(Request $request, PersonLocation $location): JsonResponse
    {
        /** @var Person $person */
        $person = $request->attributes->get('identity_person');
        $authMethod = (string) $request->attributes->get('identity_auth_method', 'api');

        $req = $this->updateRequestService->submitLocationChange(
            person: $person,
            action: 'delete',
            location: $location,
            authMethod: $authMethod
        );

        return $this->ok('تم إرسال طلب حذف الموقع للمراجعة والاعتماد.', ['request_id' => $req->id, 'status' => 'pending']);
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    protected function ok(string $message, ?array $data = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'errors' => null,
         ], $status);
    }

    /**
     * Fallback parser for multipart form-data on PUT/PATCH requests when PHP does not populate $_FILES.
     */
    protected function extractUploadedFileFromRawInput(Request $request, string $fieldName): ?UploadedFile
    {
        $rawContent = $request->getContent();
        if (empty($rawContent)) {
            return null;
        }

        $contentType = (string) $request->header('Content-Type', '');
        if (!preg_match('/boundary=(.*)$/i', $contentType, $matches)) {
            return null;
        }

        $boundary = trim($matches[1], '"');
        $blocks = explode('--' . $boundary, $rawContent);

        foreach ($blocks as $block) {
            if (str_contains($block, 'name="' . $fieldName . '"') && preg_match('/filename="([^"]+)"/i', $block, $fileMatches)) {
                $originalName = $fileMatches[1];

                $mimeType = 'image/jpeg';
                if (preg_match('/Content-Type:\s*([^\r\n]+)/i', $block, $mimeMatches)) {
                    $mimeType = trim($mimeMatches[1]);
                }

                $pos = strpos($block, "\r\n\r\n");
                $headerLength = ($pos !== false) ? 4 : 2;
                if ($pos === false) {
                    $pos = strpos($block, "\n\n");
                }

                if ($pos !== false) {
                    $fileContent = substr($block, $pos + $headerLength);
                    $fileContent = rtrim($fileContent, "\r\n-");

                    if (!empty($fileContent)) {
                        $tempFilePath = tempnam(sys_get_temp_dir(), 'put_upload_');
                        file_put_contents($tempFilePath, $fileContent);

                        return new UploadedFile(
                            $tempFilePath,
                            $originalName,
                            $mimeType,
                            null,
                            true
                        );
                    }
                }
            }
        }

        return null;
    }
}
