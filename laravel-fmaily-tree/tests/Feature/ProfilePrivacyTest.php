<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\PersonContactAccount;
use App\Services\AI\EmbeddingsService;
use App\Services\ProfilePrivacyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProfilePrivacyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Prevent all queued jobs (AI embedding indexing, face recognition)
        // from being dispatched during tests — they require external AI providers.
        Queue::fake();

        // Additionally mock EmbeddingsService directly so that any synchronous
        // call (e.g. PersonObserver::deleted()) is a no-op instead of an exception.
        $this->mock(EmbeddingsService::class, function ($mock) {
            $mock->shouldIgnoreMissing();
        });
    }

    public function test_default_privacy_settings_are_applied()
    {
        $person = Person::factory()->create([
            'from_outside_the_family' => false,
        ]);

        $settings = $person->getResolvedPrivacySettings();

        $this->assertEquals('family', $settings['contacts']);
        $this->assertEquals('public', $settings['photo']);
        $this->assertEquals('family', $settings['birth_date']);
        $this->assertEquals('public', $settings['location']);
    }

    public function test_full_name_is_always_viewable_regardless_of_settings()
    {
        $privacyService = app(ProfilePrivacyService::class);
        $person = Person::factory()->create([
            'from_outside_the_family' => false,
            'privacy_settings' => [
                'contacts' => 'private',
                'photo' => 'private',
                'birth_date' => 'private',
            ],
        ]);

        $guestViewer = Person::factory()->create(['from_outside_the_family' => true]);

        $this->assertTrue($privacyService->canViewField($person, 'full_name', explicitViewerPersonId: $guestViewer->id));
        $this->assertTrue($privacyService->canViewField($person, 'first_name', explicitViewerPersonId: $guestViewer->id));
    }

    public function test_family_member_can_view_family_level_contacts_but_outside_family_cannot()
    {
        $privacyService = app(ProfilePrivacyService::class);

        $target = Person::factory()->create([
            'from_outside_the_family' => false,
            'privacy_settings' => ['contacts' => 'family'],
        ]);

        $familyViewer = Person::factory()->create(['from_outside_the_family' => false]);
        $outsideViewer = Person::factory()->create(['from_outside_the_family' => true]);

        $this->assertTrue($privacyService->canViewField($target, 'contacts', explicitViewerPersonId: $familyViewer->id));
        $this->assertFalse($privacyService->canViewField($target, 'contacts', explicitViewerPersonId: $outsideViewer->id));
    }

    public function test_private_visibility_is_only_visible_to_owner_and_admin()
    {
        $privacyService = app(ProfilePrivacyService::class);

        $target = Person::factory()->create([
            'from_outside_the_family' => false,
            'privacy_settings' => ['contacts' => 'private'],
        ]);

        $familyViewer = Person::factory()->create(['from_outside_the_family' => false]);

        // Family member cannot view private contacts
        $this->assertFalse($privacyService->canViewField($target, 'contacts', explicitViewerPersonId: $familyViewer->id));

        // Owner can view own private contacts
        $this->assertTrue($privacyService->canViewField($target, 'contacts', explicitViewerPersonId: $target->id));
    }

    public function test_granular_contact_visibility_overrides_work()
    {
        $privacyService = app(ProfilePrivacyService::class);

        $target = Person::factory()->create([
            'from_outside_the_family' => false,
            'privacy_settings' => ['contacts' => 'family'],
        ]);

        $publicContact = PersonContactAccount::create([
            'person_id' => $target->id,
            'type' => 'email',
            'value' => 'public@example.com',
            'visibility' => 'public',
        ]);

        $familyContact = PersonContactAccount::create([
            'person_id' => $target->id,
            'type' => 'phone',
            'value' => '0555555555',
            'visibility' => 'family',
        ]);

        $outsideViewer = Person::factory()->create(['from_outside_the_family' => true]);
        $familyViewer = Person::factory()->create(['from_outside_the_family' => false]);

        // Outside viewer: can view public contact but NOT family contact
        $this->assertTrue($privacyService->canViewContactAccount($publicContact, $target, explicitViewerPersonId: $outsideViewer->id));
        $this->assertFalse($privacyService->canViewContactAccount($familyContact, $target, explicitViewerPersonId: $outsideViewer->id));

        // Family viewer: can view both
        $this->assertTrue($privacyService->canViewContactAccount($publicContact, $target, explicitViewerPersonId: $familyViewer->id));
        $this->assertTrue($privacyService->canViewContactAccount($familyContact, $target, explicitViewerPersonId: $familyViewer->id));
    }

    public function test_filter_person_payload_masks_hidden_fields()
    {
        $privacyService = app(ProfilePrivacyService::class);

        $target = Person::factory()->create([
            'from_outside_the_family' => false,
            'first_name' => 'محمد',
            'last_name' => 'السريع',
            'birth_date' => '1990-01-01',
            'occupation' => 'مهندس',
            'biography' => 'نبذة خاصة',
            'privacy_settings' => [
                'contacts' => 'private',
                'photo' => 'private',
                'birth_date' => 'private',
                'occupation' => 'private',
                'biography' => 'private',
            ],
        ]);

        $rawData = [
            'id' => $target->id,
            'full_name' => $target->full_name,
            'first_name' => $target->first_name,
            'photo_url' => 'https://example.com/photo.jpg',
            'birth_date' => '1990/01/01',
            'occupation' => 'مهندس',
            'biography' => 'نبذة خاصة',
            'contact_accounts' => [
                ['id' => 1, 'type' => 'phone', 'value' => '0500000000', 'visibility' => 'family'],
            ],
        ];

        // An outside guest viewer
        $filtered = $privacyService->filterPersonPayload($rawData, $target);

        // Name is always preserved
        $this->assertEquals('محمد', $filtered['first_name']);

        // Hidden fields are masked
        $this->assertNull($filtered['birth_date']);
        $this->assertNull($filtered['occupation']);
        $this->assertNull($filtered['biography']);
        $this->assertEmpty($filtered['contact_accounts']);
        $this->assertTrue($filtered['is_photo_hidden']);
    }
}
