<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Marriage;
use App\Models\Person;
use App\Models\PersonContactAccount;
use App\Models\PersonLocation;
use App\Models\ProfileUpdateRequest;
use App\Services\MyProfileService;
use App\Services\ProfileUpdateRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MyProfileController extends Controller
{
    public function __construct(
        protected MyProfileService $profileService,
        protected ProfileUpdateRequestService $updateRequestService
    ) {}

    public function show(Request $request): View
    {
        /** @var Person $person */
        $person = $request->attributes->get('identity_person');
        $payload = $this->profileService->profilePayload($person);

        $locations = Location::orderBy('name')->get(['id', 'name']);
        $spouseCandidates = $this->spouseCandidates($person);
        $wifeCandidates = $person->gender === 'male'
            ? app(\App\Services\MarriageOrderService::class)->orderedWives((int) $person->id)
            : collect();
        $husbandCandidates = $person->gender === 'female'
            ? $this->profileService->husbandCandidates($person)
            : collect();
        $fatherCandidates = Person::where('gender', 'male')
            ->where('from_outside_the_family', false)
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name']);

        $pendingRequests = ProfileUpdateRequest::where('person_id', $person->id)
            ->where('status', 'pending')
            ->latest()
            ->get();

        return view('web-site.my-profile', array_merge($payload, [
            'locationsList' => $locations,
            'spouseCandidates' => $spouseCandidates,
            'wifeCandidates' => $wifeCandidates,
            'husbandCandidates' => $husbandCandidates,
            'fatherCandidates' => $fatherCandidates,
            'authMethod' => $request->attributes->get('identity_auth_method'),
            'pendingRequests' => $pendingRequests,
        ]));
    }

    public function update(Request $request): RedirectResponse
    {
        /** @var Person $person */
        $person = $request->attributes->get('identity_person');
        $authMethod = (string) $request->attributes->get('identity_auth_method', 'whatsapp');

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
            'photo' => 'nullable|image|max:2048',
        ]);
        unset($validated['first_name'], $validated['last_name']);

        $this->updateRequestService->submitProfileUpdate(
            person: $person,
            data: $validated,
            photo: $request->file('photo'),
            authMethod: $authMethod
        );

        return back()->with('success', 'تم إرسال طلب تعديل البيانات بنجاح وهو قيد مراجعة واعتماد إدارة العائلة.');
    }

    public function updatePrivacy(Request $request): RedirectResponse
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
            'contact_visibilities.*' => 'in:public,family,private',
        ]);

        $settings = array_intersect_key($validated, array_flip([
            'contacts', 'photo', 'birth_date', 'location', 'biography', 'occupation'
        ]));

        $this->profileService->updatePrivacySettings($person, $settings);

        if (!empty($validated['contact_visibilities'])) {
            foreach ($validated['contact_visibilities'] as $contactId => $vis) {
                PersonContactAccount::where('id', $contactId)
                    ->where('person_id', $person->id)
                    ->update(['visibility' => $vis]);
            }
        }

        return back()->with('success', 'تم حفظ وتحديث إعدادات الخصوصية بنجاح.');
    }

    public function storeContact(Request $request): RedirectResponse
    {
        /** @var Person $person */
        $person = $request->attributes->get('identity_person');
        $authMethod = (string) $request->attributes->get('identity_auth_method', 'whatsapp');

        $validated = $request->validate([
            'type' => 'required|in:phone,whatsapp,email,facebook,instagram,twitter,linkedin,telegram,other',
            'value' => 'required|string|max:255',
            'visibility' => 'nullable|in:public,family,private',
            'label' => 'nullable|string|max:100',
        ]);

        $this->updateRequestService->submitContactChange(
            person: $person,
            action: 'add',
            data: $validated,
            authMethod: $authMethod
        );

        return back()->with('success', 'تم إرسال طلب إضافة حساب التواصل وهو قيد مراجعة واعتماد إدارة العائلة.');
    }

    public function updateContact(Request $request, PersonContactAccount $contact): RedirectResponse
    {
        /** @var Person $person */
        $person = $request->attributes->get('identity_person');
        $authMethod = (string) $request->attributes->get('identity_auth_method', 'whatsapp');

        $validated = $request->validate([
            'type' => 'required|in:phone,whatsapp,email,facebook,instagram,twitter,linkedin,telegram,other',
            'value' => 'required|string|max:255',
            'label' => 'nullable|string|max:100',
        ]);

        $this->updateRequestService->submitContactChange(
            person: $person,
            action: 'update',
            data: $validated,
            contact: $contact,
            authMethod: $authMethod
        );

        return back()->with('success', 'تم إرسال طلب تعديل حساب التواصل وهو قيد مراجعة واعتماد إدارة العائلة.');
    }

    public function destroyContact(Request $request, PersonContactAccount $contact): RedirectResponse
    {
        /** @var Person $person */
        $person = $request->attributes->get('identity_person');
        $authMethod = (string) $request->attributes->get('identity_auth_method', 'whatsapp');

        $this->updateRequestService->submitContactChange(
            person: $person,
            action: 'delete',
            contact: $contact,
            authMethod: $authMethod
        );

        return back()->with('success', 'تم إرسال طلب حذف حساب التواصل وهو قيد مراجعة واعتماد إدارة العائلة.');
    }

    public function storeChild(Request $request): RedirectResponse
    {
        /** @var Person $person */
        $person = $request->attributes->get('identity_person');
        $authMethod = (string) $request->attributes->get('identity_auth_method', 'whatsapp');

        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'gender' => 'required|in:male,female',
            'birth_date' => 'nullable|date',
            'birth_place' => 'nullable|string|max:150',
            'mother_id' => 'nullable|exists:persons,id',
            'parent_id' => 'nullable|exists:persons,id',
            'photo' => 'nullable|image|max:2048',
        ]);

        $this->updateRequestService->submitChildAdd(
            owner: $person,
            data: $validated,
            photo: $request->file('photo'),
            authMethod: $authMethod
        );

        return back()->with('success', 'تم إرسال طلب إضافة الابن/الابنة بنجاح وهو قيد مراجعة واعتماد إدارة العائلة.');
    }

    public function storeMarriage(Request $request): RedirectResponse
    {
        /** @var Person $person */
        $person = $request->attributes->get('identity_person');
        $authMethod = (string) $request->attributes->get('identity_auth_method', 'whatsapp');

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
            'photo' => 'nullable|image|max:2048',
        ]);

        $this->updateRequestService->submitMarriageChange(
            owner: $person,
            action: 'add',
            data: $validated,
            marriage: null,
            photo: $request->file('photo'),
            authMethod: $authMethod
        );

        return back()->with('success', 'تم إرسال طلب إضافة عقد الزواج وهو قيد مراجعة واعتماد إدارة العائلة.');
    }

    public function updateMarriage(Request $request, Marriage $marriage): RedirectResponse
    {
        /** @var Person $person */
        $person = $request->attributes->get('identity_person');
        $authMethod = (string) $request->attributes->get('identity_auth_method', 'whatsapp');

        $validated = $request->validate([
            'married_at' => 'nullable|date',
            'divorced_at' => 'nullable|date|after_or_equal:married_at',
            'is_divorced' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:1|max:100',
        ]);

        $this->updateRequestService->submitMarriageChange(
            owner: $person,
            action: 'update',
            data: $validated,
            marriage: $marriage,
            authMethod: $authMethod
        );

        return back()->with('success', 'تم إرسال طلب تعديل عقد الزواج وهو قيد مراجعة واعتماد إدارة العائلة.');
    }

    public function storeLocation(Request $request): RedirectResponse
    {
        /** @var Person $person */
        $person = $request->attributes->get('identity_person');
        $authMethod = (string) $request->attributes->get('identity_auth_method', 'whatsapp');

        $validated = $request->validate([
            'location_id' => 'required|exists:locations,id',
            'label' => 'nullable|string|max:100',
            'url' => 'nullable|url|max:1000',
            'is_primary' => 'nullable|boolean',
        ]);

        $this->updateRequestService->submitLocationChange(
            person: $person,
            action: 'add',
            data: $validated,
            authMethod: $authMethod
        );

        return back()->with('success', 'تم إرسال طلب إضافة الموقع وهو قيد مراجعة واعتماد إدارة العائلة.');
    }

    public function updateLocation(Request $request, PersonLocation $location): RedirectResponse
    {
        /** @var Person $person */
        $person = $request->attributes->get('identity_person');
        $authMethod = (string) $request->attributes->get('identity_auth_method', 'whatsapp');

        $validated = $request->validate([
            'location_id' => 'required|exists:locations,id',
            'label' => 'nullable|string|max:100',
            'url' => 'nullable|url|max:1000',
            'is_primary' => 'nullable|boolean',
        ]);

        $this->updateRequestService->submitLocationChange(
            person: $person,
            action: 'update',
            data: $validated,
            location: $location,
            authMethod: $authMethod
        );

        return back()->with('success', 'تم إرسال طلب تعديل الموقع وهو قيد مراجعة واعتماد إدارة العائلة.');
    }

    public function destroyLocation(Request $request, PersonLocation $location): RedirectResponse
    {
        /** @var Person $person */
        $person = $request->attributes->get('identity_person');
        $authMethod = (string) $request->attributes->get('identity_auth_method', 'whatsapp');

        $this->updateRequestService->submitLocationChange(
            person: $person,
            action: 'delete',
            location: $location,
            authMethod: $authMethod
        );

        return back()->with('success', 'تم إرسال طلب حذف الموقع وهو قيد مراجعة واعتماد إدارة العائلة.');
    }

    /**
     * @return \Illuminate\Support\Collection<int, Person>
     */
    protected function spouseCandidates(Person $person)
    {
        if ($person->gender === 'male') {
            return Person::where('gender', 'female')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'gender']);
        }

        return Person::where('gender', 'male')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'gender']);
    }
}
