<?php

namespace App\Services;

use App\Jobs\NotifyEditorsOfPendingProfileUpdatesJob;
use App\Models\Location;
use App\Models\Marriage;
use App\Models\Notification;
use App\Models\Person;
use App\Models\PersonContactAccount;
use App\Models\PersonLocation;
use App\Models\ProfileUpdateRequest;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\ExpoNotificationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProfileUpdateRequestService
{
    protected ExpoNotificationService $expoService;

    public function __construct(
        protected AdminAlertDispatcherService $dispatcher,
        protected TreeCacheService $treeCache,
        ?ExpoNotificationService $expoService = null
    ) {
        $this->expoService = $expoService ?? app(ExpoNotificationService::class);
    }

    /**
     * Submit profile info changes for admin moderation.
     *
     * @param  array<string, mixed>  $data
     */
    public function submitProfileUpdate(
        Person $person,
        array $data,
        UploadedFile|string|null $photo = null,
        string $authMethod = 'whatsapp'
    ): ProfileUpdateRequest {
        $photoInput = $photo ?? ($data['photo'] ?? ($data['photo_base64'] ?? null));
        unset($data['photo'], $data['photo_base64']);
        foreach (ProfileUpdateRequest::PROFILE_INFO_LOCKED_FIELDS as $lockedField) {
            unset($data[$lockedField]);
        }

        // Snapshot raw DB values (not cast objects) so dates are stored as Y-m-d, and keep only genuine changes:
        // clients submit the whole form, and unchanged keys must never surface as "excluded" during review.
        $oldData = [];
        $changedData = [];
        $personAttributes = $person->getAttributes();
        foreach ($data as $key => $val) {
            $currentValue = array_key_exists($key, $personAttributes)
                ? $person->getRawOriginal($key)
                : ($person->{$key} ?? null);

            if (ProfileUpdateRequest::normalizeComparableValue($currentValue) === ProfileUpdateRequest::normalizeComparableValue($val)) {
                continue;
            }

            $oldData[$key] = $currentValue;
            $changedData[$key] = $val;
        }

        $attachedFiles = [];
        if ($photoInput instanceof UploadedFile && $photoInput->isValid()) {
            $tempPath = $photoInput->store('temp-profile-requests', 'public');
            $attachedFiles['photo_url'] = $tempPath;
        } elseif (is_string($photoInput) && !empty($photoInput)) {
            $tempPath = $this->storeBase64OrUriImage($photoInput, 'temp-profile-requests');
            if ($tempPath) {
                $attachedFiles['photo_url'] = $tempPath;
            }
        }

        if (empty($changedData) && empty($attachedFiles)) {
            throw ValidationException::withMessages([
                'data' => ['لم يتم إجراء أي تعديل على البيانات الحالية.'],
            ]);
        }

        $oldData['photo_url'] = $person->photo_url;

        $request = ProfileUpdateRequest::create([
            'person_id' => $person->id,
            'requested_by_auth' => $authMethod,
            'change_type' => 'profile_info',
            'target_id' => null,
            'old_data' => $oldData,
            'proposed_data' => $changedData,
            'attached_files' => !empty($attachedFiles) ? $attachedFiles : null,
            'status' => 'pending',
        ]);

        NotifyEditorsOfPendingProfileUpdatesJob::dispatch($request->id);

        return $request;
    }

    /**
     * Submit contact account addition, update, or deletion for moderation.
     *
     * @param  array<string, mixed>  $data
     */
    public function submitContactChange(
        Person $person,
        string $action, // 'add', 'update', 'delete'
        array $data = [],
        ?PersonContactAccount $contact = null,
        string $authMethod = 'whatsapp'
    ): ProfileUpdateRequest {
        $changeType = 'contact_' . $action;
        $oldData = null;
        $proposedData = $data;

        if ($action === 'update' || $action === 'delete') {
            if (!$contact || $contact->person_id !== $person->id) {
                abort(403, 'لا تملك صلاحية تعديل هذا الحساب.');
            }

            $oldData = [
                'type' => $contact->type,
                'value' => $contact->value,
                'label' => $contact->label,
                'sort_order' => $contact->sort_order,
            ];
        }

        if ($action !== 'delete' && isset($proposedData['type'], $proposedData['value'])) {
            $proposedData['value'] = $this->normalizeContactValue($proposedData['type'], $proposedData['value']);
        }

        $request = ProfileUpdateRequest::create([
            'person_id' => $person->id,
            'requested_by_auth' => $authMethod,
            'change_type' => $changeType,
            'target_id' => $contact?->id,
            'old_data' => $oldData,
            'proposed_data' => $proposedData,
            'status' => 'pending',
        ]);

        NotifyEditorsOfPendingProfileUpdatesJob::dispatch($request->id);

        return $request;
    }

    /**
     * Submit child addition for moderation.
     *
     * @param  array<string, mixed>  $data
     */
    public function submitChildAdd(
        Person $owner,
        array $data,
        UploadedFile|string|null $photo = null,
        string $authMethod = 'whatsapp'
    ): ProfileUpdateRequest {
        if ($owner->gender === 'male') {
            $data['parent_id'] = $owner->id;
            if (empty($data['mother_id'])) {
                throw ValidationException::withMessages([
                    'mother_id' => ['يجب اختيار الأم عند إضافة ابن/ابنة.'],
                ]);
            }
            $this->assertIsWife((int) $owner->id, (int) $data['mother_id']);
        } else {
            $data['mother_id'] = $owner->id;
            if (empty($data['parent_id'])) {
                throw ValidationException::withMessages([
                    'parent_id' => ['يجب اختيار الأب عند إضافة ابن/ابنة.'],
                ]);
            }
            $this->assertIsHusband((int) $data['parent_id'], (int) $owner->id);
        }

        $photoInput = $photo ?? ($data['photo'] ?? ($data['photo_base64'] ?? null));
        unset($data['photo'], $data['photo_base64']);

        $attachedFiles = [];
        if ($photoInput instanceof UploadedFile && $photoInput->isValid()) {
            $tempPath = $photoInput->store('temp-profile-requests', 'public');
            $attachedFiles['photo_url'] = $tempPath;
        } elseif (is_string($photoInput) && !empty($photoInput)) {
            $tempPath = $this->storeBase64OrUriImage($photoInput, 'temp-profile-requests');
            if ($tempPath) {
                $attachedFiles['photo_url'] = $tempPath;
            }
        }

        if (!empty($data['location']) && empty($data['location_id'])) {
            $location = Location::findOrCreateByName($data['location']);
            $data['location_id'] = $location->id;
        }
        unset($data['location']);

        $request = ProfileUpdateRequest::create([
            'person_id' => $owner->id,
            'requested_by_auth' => $authMethod,
            'change_type' => 'child_add',
            'target_id' => null,
            'old_data' => null,
            'proposed_data' => $data,
            'attached_files' => !empty($attachedFiles) ? $attachedFiles : null,
            'status' => 'pending',
        ]);

        NotifyEditorsOfPendingProfileUpdatesJob::dispatch($request->id);

        return $request;
    }

    /**
     * Submit marriage addition or update for moderation.
     *
     * @param  array<string, mixed>  $data
     */
    public function submitMarriageChange(
        Person $owner,
        string $action, // 'add', 'update'
        array $data = [],
        ?Marriage $marriage = null,
        UploadedFile|string|null $photo = null,
        string $authMethod = 'whatsapp'
    ): ProfileUpdateRequest {
        $changeType = 'marriage_' . $action;
        $oldData = null;
        $attachedFiles = [];

        // توحيد ترتيب الزوجة: رقم صحيح 1..100 أو null (تلقائي بالتاريخ)
        if (array_key_exists('sort_order', $data)) {
            if ($data['sort_order'] === '' || $data['sort_order'] === 0 || $data['sort_order'] === '0') {
                $data['sort_order'] = null;
            } elseif ($data['sort_order'] !== null) {
                $data['sort_order'] = max(1, min(100, (int) $data['sort_order']));
            }
        }

        if ($action === 'add') {
            $spouseType = $data['spouse_type'] ?? 'existing';
            $isNewSpouse = in_array($spouseType, ['outside_family', 'inside_family', 'new'], true) 
                || (!empty($data['first_name']) && empty($data['wife_id']) && empty($data['husband_id']));

            if ($isNewSpouse) {
                if (empty($data['first_name'])) {
                    throw ValidationException::withMessages(['first_name' => ['يجب إدخال اسم الزوج/الزوجة.']]);
                }

                $data['spouse_type'] = $spouseType === 'inside_family' ? 'inside_family' : 'outside_family';
                $data['from_outside_the_family'] = $data['spouse_type'] === 'outside_family';

                // Handle spouse photo if supplied
                $photoInput = $photo ?? ($data['photo'] ?? ($data['photo_base64'] ?? null));
                unset($data['photo'], $data['photo_base64']);

                if ($photoInput instanceof UploadedFile && $photoInput->isValid()) {
                    $tempPath = $photoInput->store('temp-profile-requests', 'public');
                    $attachedFiles['photo_url'] = $tempPath;
                } elseif (is_string($photoInput) && !empty($photoInput)) {
                    $tempPath = $this->storeBase64OrUriImage($photoInput, 'temp-profile-requests');
                    if ($tempPath) {
                        $attachedFiles['photo_url'] = $tempPath;
                    }
                }
            } else {
                // Existing person in family tree
                if ($owner->gender === 'male') {
                    $data['husband_id'] = $owner->id;
                    if (empty($data['wife_id'])) {
                        throw ValidationException::withMessages(['wife_id' => ['يجب اختيار الزوجة.']]);
                    }
                } else {
                    $data['wife_id'] = $owner->id;
                    if (empty($data['husband_id'])) {
                        throw ValidationException::withMessages(['husband_id' => ['يجب اختيار الزوج.']]);
                    }
                }

                $exists = Marriage::where('husband_id', $data['husband_id'])
                    ->where('wife_id', $data['wife_id'])
                    ->exists();

                if ($exists) {
                    throw ValidationException::withMessages(['marriage' => ['هذا الزواج مسجّل بالفعل.']]);
                }
            }
        } elseif ($action === 'update') {
            if (!$marriage || ($marriage->husband_id !== $owner->id && $marriage->wife_id !== $owner->id)) {
                abort(403, 'لا تملك صلاحية تعديل هذا الزواج.');
            }

            $oldData = [
                'married_at' => optional($marriage->married_at)?->format('Y-m-d'),
                'divorced_at' => optional($marriage->divorced_at)?->format('Y-m-d'),
                'is_divorced' => (bool) $marriage->is_divorced,
                'sort_order' => $marriage->sort_order,
                'notes' => $marriage->notes ?? null,
            ];
            unset($data['husband_id'], $data['wife_id']);
            // لا ترسل sort_order إذا لم يتغير (حتى لا يظهر كتعديل وهمي)
            if (array_key_exists('sort_order', $data) && $data['sort_order'] === $marriage->sort_order) {
                unset($data['sort_order']);
            }
        }

        $request = ProfileUpdateRequest::create([
            'person_id' => $owner->id,
            'requested_by_auth' => $authMethod,
            'change_type' => $changeType,
            'target_id' => $marriage?->id,
            'old_data' => $oldData,
            'proposed_data' => $data,
            'attached_files' => !empty($attachedFiles) ? $attachedFiles : null,
            'status' => 'pending',
        ]);

        NotifyEditorsOfPendingProfileUpdatesJob::dispatch($request->id);

        return $request;
    }

    /**
     * Submit location addition, update, or deletion for moderation.
     *
     * @param  array<string, mixed>  $data
     */
    public function submitLocationChange(
        Person $person,
        string $action, // 'add', 'update', 'delete'
        array $data = [],
        ?PersonLocation $location = null,
        string $authMethod = 'whatsapp'
    ): ProfileUpdateRequest {
        $changeType = 'location_' . $action;
        $oldData = null;

        if ($action === 'update' || $action === 'delete') {
            if (!$location || $location->person_id !== $person->id) {
                abort(403, 'لا تملك صلاحية تعديل هذا الموقع.');
            }

            $oldData = [
                'location_id' => $location->location_id,
                'location_name' => $location->location?->name,
                'label' => $location->label,
                'url' => $location->url,
                'is_primary' => $location->is_primary,
                'sort_order' => $location->sort_order,
            ];
        }

        $request = ProfileUpdateRequest::create([
            'person_id' => $person->id,
            'requested_by_auth' => $authMethod,
            'change_type' => $changeType,
            'target_id' => $location?->id,
            'old_data' => $oldData,
            'proposed_data' => $data,
            'status' => 'pending',
        ]);

        NotifyEditorsOfPendingProfileUpdatesJob::dispatch($request->id);

        return $request;
    }

    /**
     * Approve a request and apply the changes to the real database records.
     * Supports approving pending requests as well as reopening & approving previously rejected requests.
     * Supports granular/selective field-level approval for profile_info changes.
     *
     * @param  array<string>|null  $approvedFields  List of specific field keys approved by admin (null = approve all)
     */
    public function approve(
        ProfileUpdateRequest $request,
        User $reviewer,
        bool $isReopened = false,
        ?array $approvedFields = null,
        ?string $exclusionNote = null
    ): void {
        if ($request->status === 'approved') {
            throw new \RuntimeException('تم اعتماد هذا الطلب مسبقاً.');
        }

        $wasRejected = $request->status === 'rejected' || $isReopened;
        $appliedFields = [];
        $excludedFields = [];

        DB::transaction(function () use ($request, $reviewer, $approvedFields, $exclusionNote, &$appliedFields, &$excludedFields) {
            $person = $request->person;
            $proposed = $request->proposed_data ?? [];
            $files = $request->attached_files ?? [];

            switch ($request->change_type) {
                case 'profile_info':
                    $hasPhoto = !empty($files['photo_url']);
                    $shouldApprovePhoto = $hasPhoto && ($approvedFields === null || in_array('photo_url', $approvedFields, true) || in_array('photo', $approvedFields, true));

                    if ($shouldApprovePhoto && Storage::disk('public')->exists($files['photo_url'])) {
                        $extension = pathinfo($files['photo_url'], PATHINFO_EXTENSION);
                        $permanentPath = 'people-photos/' . uniqid('photo_') . '.' . $extension;
                        Storage::disk('public')->move($files['photo_url'], $permanentPath);

                        // The member's original upload (kept aside while the reviewer was editing) is no longer needed.
                        $this->deleteStoredFile($files['photo_original_url'] ?? null);

                        if ($person->photo_url) {
                            Storage::disk('public')->delete($person->photo_url);
                        }
                        $proposed['photo_url'] = $permanentPath;
                        $appliedFields[] = 'photo_url';
                    } else {
                        if ($hasPhoto && !$shouldApprovePhoto) {
                            $excludedFields[] = 'photo_url';
                            $this->deleteStoredFile($files['photo_url']);
                            $this->deleteStoredFile($files['photo_original_url'] ?? null);
                        }
                        unset($proposed['photo_url']);
                    }

                    // Only genuinely changed, member-editable fields are reviewable; anything else in the payload
                    // (unchanged values, legacy first_name/last_name) is silently ignored rather than reported as excluded.
                    $fieldsToUpdate = [];
                    foreach ($request->changedProfileFields() as $field) {
                        if ($approvedFields === null || in_array($field, $approvedFields, true)) {
                            $fieldsToUpdate[$field] = $proposed[$field];
                            $appliedFields[] = $field;
                        } else {
                            $excludedFields[] = $field;
                        }
                    }

                    if (isset($proposed['photo_url']) && $shouldApprovePhoto) {
                        $fieldsToUpdate['photo_url'] = $proposed['photo_url'];
                    }

                    if (!empty($fieldsToUpdate)) {
                        $person->update($fieldsToUpdate);
                    }
                    break;

                case 'contact_add':
                    $maxOrder = $person->contactAccounts()->max('sort_order');
                    $proposed['sort_order'] = $proposed['sort_order'] ?? ($maxOrder ? $maxOrder + 1 : 0);
                    $person->contactAccounts()->create($proposed);
                    break;

                case 'contact_update':
                    $contact = PersonContactAccount::find($request->target_id);
                    if ($contact && $contact->person_id === $person->id) {
                        $contact->update($proposed);
                    }
                    break;

                case 'contact_delete':
                    $contact = PersonContactAccount::find($request->target_id);
                    if ($contact && $contact->person_id === $person->id) {
                        $contact->delete();
                    }
                    break;

                case 'child_add':
                    if (!empty($files['photo_url']) && Storage::disk('public')->exists($files['photo_url'])) {
                        $extension = pathinfo($files['photo_url'], PATHINFO_EXTENSION);
                        $permanentPath = 'people-photos/' . uniqid('child_') . '.' . $extension;
                        Storage::disk('public')->move($files['photo_url'], $permanentPath);
                        $proposed['photo_url'] = $permanentPath;
                    }

                    $parentId = $proposed['parent_id'] ?? null;
                    $child = Person::create($proposed);
                    if ($parentId) {
                        $parent = Person::find($parentId);
                        if ($parent) {
                            $child->appendToNode($parent)->save();
                        }
                    }
                    $this->treeCache->forgetPerson($child->id);
                    if ($child->parent_id) {
                        $this->treeCache->forgetPerson($child->parent_id);
                    }
                    if ($child->mother_id) {
                        $this->treeCache->forgetPerson($child->mother_id);
                    }
                    break;

                case 'marriage_add':
                    $spouseType = $proposed['spouse_type'] ?? 'existing';
                    $isNewSpouse = in_array($spouseType, ['outside_family', 'inside_family', 'new'], true) 
                        || (!empty($proposed['first_name']) && empty($proposed['wife_id']) && empty($proposed['husband_id']));

                    if ($isNewSpouse) {
                        $isWife = $person->gender === 'male';
                        $spouseGender = $isWife ? 'female' : 'male';
                        $isOutside = ($spouseType === 'outside_family') || !empty($proposed['from_outside_the_family']);

                        $spouseData = [
                            'first_name' => trim($proposed['first_name']),
                            'last_name' => !empty($proposed['last_name']) ? trim($proposed['last_name']) : null,
                            'gender' => $spouseGender,
                            'birth_date' => $proposed['birth_date'] ?? null,
                            'from_outside_the_family' => $isOutside,
                            'parent_id' => (!$isOutside && !empty($proposed['parent_id'])) ? (int) $proposed['parent_id'] : null,
                        ];

                        if (!empty($files['photo_url']) && Storage::disk('public')->exists($files['photo_url'])) {
                            $extension = pathinfo($files['photo_url'], PATHINFO_EXTENSION);
                            $permanentPath = 'people-photos/' . uniqid('spouse_') . '.' . $extension;
                            Storage::disk('public')->move($files['photo_url'], $permanentPath);
                            $spouseData['photo_url'] = $permanentPath;
                        }

                        $newSpouse = Person::create($spouseData);

                        if (!empty($spouseData['parent_id'])) {
                            $father = Person::find($spouseData['parent_id']);
                            if ($father) {
                                $newSpouse->appendToNode($father)->save();
                            }
                        }

                        $husbandId = $isWife ? $person->id : $newSpouse->id;
                        $wifeId = $isWife ? $newSpouse->id : $person->id;

                        $explicitSort = isset($proposed['sort_order']) && $proposed['sort_order']
                            ? max(1, min(100, (int) $proposed['sort_order']))
                            : null;
                        if ($explicitSort) {
                            app(MarriageOrderService::class)->makeRoomForSortOrder($husbandId, $explicitSort);
                            $newSortOrder = $explicitSort;
                        } else {
                            $newSortOrder = (int) (Marriage::where('husband_id', $husbandId)->max('sort_order') ?? 0) + 1;
                        }

                        Marriage::create([
                            'husband_id' => $husbandId,
                            'wife_id' => $wifeId,
                            'married_at' => $proposed['married_at'] ?? null,
                            'divorced_at' => $proposed['divorced_at'] ?? null,
                            'is_divorced' => !empty($proposed['is_divorced']),
                            'sort_order' => $newSortOrder,
                        ]);

                        $this->treeCache->forgetPerson($husbandId);
                        $this->treeCache->forgetPerson($wifeId);
                        $this->treeCache->forgetMarriage($husbandId, $wifeId);
                        app(MarriageOrderService::class)->clearMarriageCaches($husbandId, $wifeId);
                    } else {
                        $marriageData = array_intersect_key($proposed, array_flip(['husband_id', 'wife_id', 'married_at', 'divorced_at', 'is_divorced', 'sort_order']));
                        if (empty($marriageData['sort_order'])) {
                            $marriageData['sort_order'] = (int) (Marriage::where('husband_id', $marriageData['husband_id'] ?? null)->max('sort_order') ?? 0) + 1;
                        } else {
                            $marriageData['sort_order'] = max(1, min(100, (int) $marriageData['sort_order']));
                            app(MarriageOrderService::class)->makeRoomForSortOrder((int) $marriageData['husband_id'], (int) $marriageData['sort_order']);
                        }
                        $created = Marriage::create($marriageData);
                        $this->treeCache->forgetMarriage($proposed['husband_id'] ?? null, $proposed['wife_id'] ?? null);
                        app(MarriageOrderService::class)->clearMarriageCaches((int) $created->husband_id, (int) $created->wife_id);
                    }
                    break;

                case 'marriage_update':
                    $marriage = Marriage::find($request->target_id);
                    if ($marriage) {
                        $marriageData = array_intersect_key($proposed, array_flip(['married_at', 'divorced_at', 'is_divorced', 'sort_order']));
                        if (array_key_exists('sort_order', $marriageData)) {
                            if (empty($marriageData['sort_order'])) {
                                unset($marriageData['sort_order']);
                            } else {
                                $marriageData['sort_order'] = max(1, min(100, (int) $marriageData['sort_order']));
                            }
                        }
                        if (!empty($marriageData)) {
                            $marriage->update($marriageData);
                        }
                        $this->treeCache->forgetMarriage($marriage->husband_id, $marriage->wife_id);
                        app(MarriageOrderService::class)->clearMarriageCaches((int) $marriage->husband_id, (int) $marriage->wife_id);
                    }
                    break;

                case 'location_add':
                    if ($proposed['is_primary'] ?? false) {
                        $person->personLocations()->update(['is_primary' => false]);
                    }
                    $maxOrder = $person->personLocations()->max('sort_order');
                    $proposed['sort_order'] = $proposed['sort_order'] ?? ($maxOrder ? $maxOrder + 1 : 0);
                    $person->personLocations()->create($proposed);
                    break;

                case 'location_update':
                    $location = PersonLocation::find($request->target_id);
                    if ($location && $location->person_id === $person->id) {
                        if ($proposed['is_primary'] ?? false) {
                            $person->personLocations()->where('id', '!=', $location->id)->update(['is_primary' => false]);
                        }
                        $location->update($proposed);
                    }
                    break;

                case 'location_delete':
                    $location = PersonLocation::find($request->target_id);
                    if ($location && $location->person_id === $person->id) {
                        $location->delete();
                    }
                    break;
            }

            // Always invalidate cache for the owner and the tree
            $this->treeCache->forgetPersonAndTree($person);

            $reason = !empty($excludedFields)
                ? ($exclusionNote ?: 'اعتماد جزئي للحقول المحددة')
                : null;

            $request->update([
                'status' => 'approved',
                'rejection_reason' => $reason,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ]);
        });

        // Notify member via In-App, Push & WhatsApp (with custom message if reopened or partial!)
        $this->createInAppApprovalNotification($request, $wasRejected, $appliedFields, $excludedFields, $exclusionNote);
        $this->sendPushApprovalNotification($request, $wasRejected, $appliedFields, $excludedFields, $exclusionNote);
        $this->notifyMemberResult(
            request: $request,
            approved: true,
            reason: $exclusionNote,
            isReopened: $wasRejected,
            appliedFields: $appliedFields,
            excludedFields: $excludedFields
        );
    }

    /**
     * Reopen a rejected request back to pending state for re-review.
     */
    public function reopen(ProfileUpdateRequest $request, User $reviewer): void
    {
        if ($request->status !== 'rejected') {
            throw new \RuntimeException('لا يمكن إعادة فتح طلب غير مرفوض.');
        }

        $request->update([
            'status' => 'pending',
            'rejection_reason' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);
    }

    /**
     * Reopen and directly approve a previously rejected or pending request in one step.
     */
    public function reopenAndApprove(
        ProfileUpdateRequest $request,
        User $reviewer,
        ?array $approvedFields = null,
        ?string $exclusionNote = null
    ): void {
        if ($request->status === 'approved') {
            throw new \RuntimeException('تم اعتماد هذا الطلب مسبقاً.');
        }

        $this->approve(
            request: $request,
            reviewer: $reviewer,
            isReopened: true,
            approvedFields: $approvedFields,
            exclusionNote: $exclusionNote
        );
    }

    /**
     * Reject a pending request with an optional reason.
     * Note: We deliberately do not delete attached temp files so that accidental rejections can be cleanly undone.
     */
    public function reject(ProfileUpdateRequest $request, User $reviewer, ?string $reason = null): void
    {
        if ($request->status !== 'pending') {
            throw new \RuntimeException('لا يمكن رفض طلب تمت معالجته مسبقاً.');
        }

        $request->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);

        // 1. In-App Notification (Database Inbox)
        $this->createInAppRejectionNotification($request, $reason);

        // 2. Mobile Push Notification (Expo)
        $this->sendPushRejectionNotification($request, $reason);

        // 3. WhatsApp Notification with rejection reason
        $this->notifyMemberResult($request, approved: false, reason: $reason);
    }

    /**
     * Replace the staged photo of a pending profile-info request with an admin-reviewed version
     * (a crop/rotation produced by the reviewer's browser, or a completely different upload).
     *
     * The member's original upload is preserved once in `photo_original_url` so the reviewer can
     * restore it before approving.
     */
    public function replaceAttachedPhoto(
        ProfileUpdateRequest $request,
        UploadedFile|string|null $photo,
        User $reviewer
    ): ProfileUpdateRequest {
        $this->assertPhotoEditable($request);

        if ($photo === null || (is_string($photo) && trim($photo) === '')) {
            throw ValidationException::withMessages([
                'photo' => ['لم يتم استلام صورة للتعديل.'],
            ]);
        }

        $files = $request->attached_files ?? [];
        $activePath = $files['photo_url'] ?? null;

        if (empty($activePath)) {
            throw ValidationException::withMessages([
                'photo' => ['لا توجد صورة مرفقة بهذا الطلب لتعديلها.'],
            ]);
        }

        $newPath = $this->storeEditedPhoto($photo);
        $originalPath = $files['photo_original_url'] ?? $activePath;

        // Drop the previously edited file, never the member's original.
        if ($activePath !== $originalPath) {
            $this->deleteStoredFile($activePath);
        }

        $files['photo_url'] = $newPath;
        $files['photo_original_url'] = $originalPath;
        $files['photo_edited_at'] = now()->toDateTimeString();
        $files['photo_edited_by'] = $reviewer->id;

        $request->update(['attached_files' => $files]);

        return $request->refresh();
    }

    /**
     * Restore the member's original staged photo, discarding the reviewer's edited version.
     */
    public function resetAttachedPhoto(ProfileUpdateRequest $request): ProfileUpdateRequest
    {
        $this->assertPhotoEditable($request);

        $files = $request->attached_files ?? [];
        $originalPath = $files['photo_original_url'] ?? null;

        if (empty($originalPath) || !Storage::disk('public')->exists($originalPath)) {
            throw ValidationException::withMessages([
                'photo' => ['لا تتوفر نسخة أصلية من صورة العضو لاستعادتها.'],
            ]);
        }

        if (($files['photo_url'] ?? null) !== $originalPath) {
            $this->deleteStoredFile($files['photo_url'] ?? null);
        }

        $files['photo_url'] = $originalPath;
        unset($files['photo_original_url'], $files['photo_edited_at'], $files['photo_edited_by']);

        $request->update(['attached_files' => $files]);

        return $request->refresh();
    }

    /**
     * Only pending profile-info requests carry an editable personal photo.
     */
    protected function assertPhotoEditable(ProfileUpdateRequest $request): void
    {
        if ($request->change_type !== 'profile_info') {
            throw ValidationException::withMessages([
                'photo' => ['لا يمكن تعديل الصورة لهذا النوع من الطلبات.'],
            ]);
        }

        if ($request->status !== 'pending') {
            throw ValidationException::withMessages([
                'photo' => ['لا يمكن تعديل صورة طلب تمت معالجته مسبقاً.'],
            ]);
        }
    }

    /**
     * Validate and store an edited/replacement photo inside the temporary review area.
     */
    protected function storeEditedPhoto(UploadedFile|string $photo): string
    {
        $contents = $photo instanceof UploadedFile
            ? file_get_contents($photo->getRealPath())
            : $this->decodeImagePayload($photo);

        if ($contents === false || $contents === '') {
            throw ValidationException::withMessages([
                'photo' => ['تعذّر قراءة الصورة المرسلة، يرجى إعادة المحاولة.'],
            ]);
        }

        $info = @getimagesizefromstring($contents);
        if ($info === false) {
            throw ValidationException::withMessages([
                'photo' => ['الملف المرسل ليس صورة صالحة.'],
            ]);
        }

        [$width, $height] = $info;
        if ($width < 1 || $height < 1 || $width > 6000 || $height > 6000 || ($width * $height) > 40000000) {
            throw ValidationException::withMessages([
                'photo' => ['أبعاد الصورة كبيرة جداً، يرجى استخدام صورة أصغر.'],
            ]);
        }

        $extension = match ($info[2] ?? null) {
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_WEBP => 'webp',
            default => 'jpg',
        };

        $path = 'temp-profile-requests/edited_' . uniqid() . '.' . $extension;
        Storage::disk('public')->put($path, $contents);

        return $path;
    }

    /**
     * Decode a data-uri or raw base64 image payload submitted by the browser.
     */
    protected function decodeImagePayload(string $payload): string|false
    {
        $payload = trim($payload);

        if (preg_match('/^data:image\/\w+;base64,/', $payload)) {
            $payload = substr($payload, strpos($payload, ',') + 1);
        }

        if (strlen($payload) < 16) {
            return false;
        }

        return base64_decode($payload, true);
    }

    /**
     * Delete a stored temporary/permanent file when it exists locally.
     */
    protected function deleteStoredFile(?string $path): void
    {
        if (empty($path) || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return;
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * Create an in-app database notification for the member when their request is rejected.
     */
    protected function createInAppRejectionNotification(ProfileUpdateRequest $request, ?string $reason = null): void
    {
        try {
            $person = $request->person;
            if (!$person) {
                return;
            }

            $changeTypeLabel = $request->change_type_label ?? 'تعديل البيانات';
            $title = "طلب تعديل البيانات: تم الرفض 📋";
            $body = "نعتذر منك، لم تتم الموافقة على طلبك ({$changeTypeLabel}).\n";
            if ($reason) {
                $body .= "📋 سبب الرفض: {$reason}\n";
            }
            $body .= "يمكنك مراجعة البيانات وتقديم طلب جديد بالبيانات الصحيحة.";

            $notification = Notification::create([
                'title'             => $title,
                'body'              => $body,
                'status'            => 'sent',
                'sent_at'           => now(),
                'targeting_filters' => [
                    'action_url' => '/(app)/my-profile',
                    'data' => [
                        'type'       => 'profile_update_rejected',
                        'request_id' => $request->id,
                    ],
                ],
            ]);

            $notification->recipients()->create([
                'person_id' => $person->id,
            ]);
        } catch (\Throwable $e) {
            Log::channel('single')->error('[ProfileUpdateRequestService] createInAppRejectionNotification error: ' . $e->getMessage(), [
                'request_id' => $request->id,
            ]);
        }
    }

    /**
     * Send mobile push notification via Expo for the rejection.
     */
    protected function sendPushRejectionNotification(ProfileUpdateRequest $request, ?string $reason = null): void
    {
        try {
            $person = $request->person;
            if (!$person) {
                return;
            }

            $tokens = UserDevice::where('person_id', $person->id)
                ->whereNotNull('expo_push_token')
                ->where('expo_push_token', '!=', '')
                ->pluck('expo_push_token')
                ->toArray();

            if (empty($tokens)) {
                return;
            }

            $changeTypeLabel = $request->change_type_label ?? 'تعديل البيانات';
            $title = "📋 نعتذر، لم يتم قبول طلب التعديل";
            $body = $reason
                ? "طلب ({$changeTypeLabel}): {$reason}"
                : "تمت مراجعة طلبك ({$changeTypeLabel}) ولم تتم الموافقة عليه.";

            $this->expoService->send($tokens, $title, $body, [
                'type'       => 'profile_update_rejected',
                'request_id' => $request->id,
            ]);
        } catch (\Throwable $e) {
            Log::channel('single')->error('[ProfileUpdateRequestService] sendPushRejectionNotification error: ' . $e->getMessage(), [
                'request_id' => $request->id,
            ]);
        }
    }

    public function getFriendlyFieldLabel(string $field): string
    {
        return match ($field) {
            'photo_url', 'photo' => 'الصورة الشخصية',
            'first_name' => 'الاسم الأول',
            'last_name' => 'اسم العائلة / الشهرة',
            'birth_date' => 'تاريخ الميلاد',
            'birth_place' => 'مكان الميلاد',
            'death_date' => 'تاريخ الوفاة',
            'death_place' => 'مكان الوفاة',
            'cemetery' => 'المقبرة',
            'cemetery_location' => 'موقع المقبرة',
            'grave_number' => 'رقم القبر',
            'occupation' => 'المهنة / التخصص',
            'biography' => 'النبذة التعريفية',
            'location_id' => 'الموقع الرئيسي',
            default => $field,
        };
    }

    /**
     * Create an in-app database notification for the member when their request is approved.
     */
    protected function createInAppApprovalNotification(
        ProfileUpdateRequest $request,
        bool $isReopened = false,
        array $appliedFields = [],
        array $excludedFields = [],
        ?string $exclusionNote = null
    ): void {
        try {
            $person = $request->person;
            if (!$person) {
                return;
            }

            $isPartial = !empty($excludedFields);
            $changeTypeLabel = $request->change_type_label ?? 'تعديل البيانات';

            if ($isPartial) {
                $appliedLabels = array_map([$this, 'getFriendlyFieldLabel'], $appliedFields);
                $title = "اعتماد جزئي لتعديل البيانات ✨";
                $body = "تمت مراجعة طلبك واعتماد (" . implode('، ', $appliedLabels) . ") وتحديثها في شجرة العائلة.";
                if ($exclusionNote) {
                    $body .= " ملاحظة الإدارة: " . $exclusionNote;
                }
            } else {
                $title = $isReopened
                    ? "إعادة فتح واعتماد تعديل البيانات ✨"
                    : "اعتماد تعديل البيانات بنجاح ✨";
                $body = $isReopened
                    ? "تمت إعادة فتح ومراجعة طلبك ({$changeTypeLabel}) والموافقة عليه واعتماده بنجاح من قِبل إدارة العائلة، وتحديث بياناتك في شجرة العائلة."
                    : "تمت مراجعة واعتماد طلبك ({$changeTypeLabel}) بنجاح، وتم تحديث بياناتك في شجرة العائلة.";
            }

            $notification = Notification::create([
                'title'             => $title,
                'body'              => $body,
                'status'            => 'sent',
                'sent_at'           => now(),
                'targeting_filters' => [
                    'action_url' => '/(app)/my-profile',
                    'data' => [
                        'type'       => 'profile_update_approved',
                        'request_id' => $request->id,
                    ],
                ],
            ]);

            $notification->recipients()->create([
                'person_id' => $person->id,
            ]);
        } catch (\Throwable $e) {
            Log::channel('single')->error('[ProfileUpdateRequestService] createInAppApprovalNotification error: ' . $e->getMessage(), [
                'request_id' => $request->id,
            ]);
        }
    }

    /**
     * Send mobile push notification via Expo for the approval.
     */
    protected function sendPushApprovalNotification(
        ProfileUpdateRequest $request,
        bool $isReopened = false,
        array $appliedFields = [],
        array $excludedFields = [],
        ?string $exclusionNote = null
    ): void {
        try {
            $person = $request->person;
            if (!$person) {
                return;
            }

            $tokens = UserDevice::where('person_id', $person->id)
                ->whereNotNull('expo_push_token')
                ->where('expo_push_token', '!=', '')
                ->pluck('expo_push_token')
                ->toArray();

            if (empty($tokens)) {
                return;
            }

            $isPartial = !empty($excludedFields);
            $changeTypeLabel = $request->change_type_label ?? 'تعديل البيانات';

            if ($isPartial) {
                $title = "✨ اعتماد جزئي لتعديل البيانات";
                $body = "تمت مراجعة طلبك وتطبيق الحقول المعتمدة بنجاح في شجرة العائلة.";
            } else {
                $title = $isReopened
                    ? "✨ تم إعادة فتح وقبول طلبك"
                    : "✨ تم اعتماد تحديث البيانات";
                $body = $isReopened
                    ? "تمت الموافقة على طلبك ({$changeTypeLabel}) واعتماده في شجرة العائلة بعد إعادة مراجعته."
                    : "تم اعتماد طلبك ({$changeTypeLabel}) وتحديثه في شجرة العائلة.";
            }

            $this->expoService->send($tokens, $title, $body, [
                'type'       => 'profile_update_approved',
                'request_id' => $request->id,
            ]);
        } catch (\Throwable $e) {
            Log::channel('single')->error('[ProfileUpdateRequestService] sendPushApprovalNotification error: ' . $e->getMessage(), [
                'request_id' => $request->id,
            ]);
        }
    }

    /**
     * Send WhatsApp notification to member regarding the approval or rejection of their request.
     */
    protected function notifyMemberResult(
        ProfileUpdateRequest $request,
        bool $approved,
        ?string $reason = null,
        bool $isReopened = false,
        array $appliedFields = [],
        array $excludedFields = []
    ): void {
        try {
            $person = $request->person;
            if (!$person) {
                return;
            }

            // Look for member phone in contact accounts
            $phoneContact = $person->contactAccounts()
                ->whereIn('type', ['whatsapp', 'phone'])
                ->whereNotNull('value')
                ->where('value', '!=', '')
                ->orderByRaw("CASE WHEN type = 'whatsapp' THEN 0 ELSE 1 END")
                ->first();

            $phoneNumber = $phoneContact?->value;

            if (!$phoneNumber) {
                return;
            }

            $memberName = $person->first_name;
            $changeTypeLabel = $request->change_type_label ?? 'تعديل البيانات';
            $isPartial = !empty($excludedFields);
            $appliedLabels = array_map([$this, 'getFriendlyFieldLabel'], $appliedFields);
            $excludedLabels = array_map([$this, 'getFriendlyFieldLabel'], $excludedFields);

            if ($approved) {
                if ($isPartial) {
                    $msg = "مرحباً {$memberName} 🌸\n";
                    $msg .= "تمت مراجعة طلبك (*{$changeTypeLabel}*) واعتماد التعديلات المحددة بنجاح في شجرة العائلة ✨\n\n";
                    if (!empty($appliedLabels)) {
                        $msg .= "✅ *تم اعتماد:* " . implode('، ', $appliedLabels) . "\n";
                    }
                    if (!empty($excludedLabels)) {
                        $msg .= "⚠️ *تم استبعاد:* " . implode('، ', $excludedLabels) . "\n";
                    }
                    if ($reason) {
                        $msg .= "📋 *ملاحظة الإدارة:* {$reason}\n";
                    }
                    $msg .= "\nشكراً لمشاركتكم وحرصكم على تحديث شجرة العائلة 🌿";
                } elseif ($isReopened) {
                    $msg = "مرحباً {$memberName} 🌸\n";
                    $msg .= "يسعدنا إبلاغك بأنه قد تمت إعادة فتح ومراجعة طلبك (*{$changeTypeLabel}*) وتدقيقه، وتمت الموافقة عليه واعتماده بنجاح من قِبل إدارة العائلة، وتحديث بياناتك في شجرة العائلة ✨\n\n";
                    $msg .= "نعتذر عن أي لبس سابق، وشكراً لحرصكم الدائم ومشاركتكم في تحديث شجرة العائلة 🌿";
                } else {
                    $msg = "مرحباً {$memberName} 🌸\n";
                    $msg .= "تمت مراجعة واعتماد طلبك (*{$changeTypeLabel}*) بنجاح من قِبل إدارة العائلة، وتم تحديث بياناتك في الشجرة.\n\n";
                    $msg .= "شكراً لاهتمامك ومشاركتك في تحديث شجرة العائلة ✨";
                }
            } else {
                $msg = "مرحباً {$memberName} 🌸\n";
                $msg .= "نعتذر منك، لم تتم الموافقة على طلبك (*{$changeTypeLabel}*).\n\n";
                if ($reason) {
                    $msg .= "📋 *سبب الرفض المدوّن:* {$reason}\n\n";
                }
                $msg .= "يمكنك التواصل مع إدارة العائلة أو مراجعة شاشتك بالتطبيق وإعادة تقديم الطلب بالبيانات الصحيحة 📲";
            }

            $this->dispatcher->sendDirectWhatsApp(
                phone: $phoneNumber,
                message: $msg,
                logContext: 'ProfileUpdateRequestResultNotification'
            );
        } catch (\Throwable $e) {
            Log::channel('single')->error('[ProfileUpdateRequestService] notifyMemberResult error: ' . $e->getMessage(), [
                'request_id' => $request->id,
                'person_id'  => $request->person_id,
            ]);
        }
    }

    protected function assertIsWife(int $husbandId, int $wifeId): void
    {
        $isWife = Marriage::where('husband_id', $husbandId)
            ->where('wife_id', $wifeId)
            ->exists();

        if (!$isWife) {
            throw ValidationException::withMessages([
                'mother_id' => ['يجب أن تكون الأم من زوجاتك المسجّلات.'],
            ]);
        }
    }

    protected function assertIsHusband(int $husbandId, int $wifeId): void
    {
        $isHusband = Marriage::where('husband_id', $husbandId)
            ->where('wife_id', $wifeId)
            ->exists();

        if (!$isHusband) {
            throw ValidationException::withMessages([
                'parent_id' => ['يجب أن يكون الأب من أزواجكِ المسجّلين في سجلات الزواج. يرجى توثيق عقد الزواج أولاً.'],
            ]);
        }
    }

    protected function normalizeContactValue(string $type, string $value): string
    {
        if (!in_array($type, ['whatsapp', 'phone'], true)) {
            return trim($value);
        }

        $lookup = app(\App\Services\Auth\WhatsappPersonLookup::class);
        $canonical = $lookup->canonicalMobile($value);

        return $canonical ? '+' . $canonical : trim($value);
    }

    /**
     * Decode and store base64 or data-uri images into public storage.
     */
    protected function storeBase64OrUriImage(string $imageData, string $directory): ?string
    {
        $imageData = trim($imageData);
        if (preg_match('/^data:image\/(\w+);base64,/', $imageData, $type)) {
            $imageData = substr($imageData, strpos($imageData, ',') + 1);
            $type = strtolower($type[1]);
            if (!in_array($type, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                $type = 'jpg';
            }
            $decoded = base64_decode($imageData);
            if ($decoded === false) {
                return null;
            }
            $filename = uniqid('photo_') . '.' . $type;
            $path = $directory . '/' . $filename;
            Storage::disk('public')->put($path, $decoded);
            return $path;
        }

        // If raw base64 string
        $decoded = base64_decode($imageData, true);
        if ($decoded !== false && strlen($imageData) > 100) {
            $filename = uniqid('photo_') . '.jpg';
            $path = $directory . '/' . $filename;
            Storage::disk('public')->put($path, $decoded);
            return $path;
        }

        return null;
    }
}
