<?php

namespace Tests\Feature;

use App\Models\Marriage;
use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PersonProfileAvatarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    /** @test */
    public function it_renders_female_hijab_avatar_for_female_relatives_without_photos()
    {
        $father = Person::factory()->create([
            'first_name' => 'ناصر',
            'last_name' => 'السريّع',
            'gender' => 'male',
            'photo_url' => null,
        ]);

        $mother = Person::factory()->create([
            'first_name' => 'منى',
            'last_name' => 'الناصر',
            'gender' => 'female',
            'photo_url' => null,
        ]);

        $mainPerson = Person::factory()->create([
            'first_name' => 'ممدوح',
            'last_name' => 'السريّع',
            'gender' => 'male',
            'parent_id' => $father->id,
            'mother_id' => $mother->id,
            'photo_url' => 'people-photos/test_mamdouh.jpg',
        ]);

        $sister = Person::factory()->create([
            'first_name' => 'البندري',
            'last_name' => 'السريّع',
            'gender' => 'female',
            'parent_id' => $father->id,
            'mother_id' => $mother->id,
            'photo_url' => null,
        ]);

        $wife = Person::factory()->create([
            'first_name' => 'سارة',
            'last_name' => 'الخالد',
            'gender' => 'female',
            'photo_url' => null,
        ]);

        Marriage::create([
            'husband_id' => $mainPerson->id,
            'wife_id' => $wife->id,
            'order' => 1,
        ]);

        $daughter = Person::factory()->create([
            'first_name' => 'نورة',
            'last_name' => 'السريّع',
            'gender' => 'female',
            'parent_id' => $mainPerson->id,
            'mother_id' => $wife->id,
            'photo_url' => null,
        ]);

        $response = $this->get(route('people.profile.show', $mainPerson->id));

        $response->assertStatus(200);

        // Verify that the female avatar image (with hijab) is used for mother, sister, wife, and daughter
        $response->assertSee('assets/img/avatar-female-hejap-1.png');

        // Verify that the male avatar image is used for father without photo
        $response->assertSee('assets/img/avatar-male.png');

        // Verify that no placeholder text/icons like <i class="fas fa-female"> are used instead of images
        $response->assertDontSee('<i class="fas fa-female text-pink-600 text-2xl"></i>', false);
        $response->assertDontSee('<i class="fas fa-female text-pink-600 text-3xl"></i>', false);
    }

    /** @test */
    public function it_renders_uploaded_photo_when_available_and_masks_when_private()
    {
        $father = Person::factory()->create([
            'first_name' => 'ناصر',
            'last_name' => 'السريّع',
            'gender' => 'male',
            'photo_url' => 'people-photos/father_photo.jpg',
            'privacy_settings' => [
                'photo' => 'public',
            ],
        ]);

        $mother = Person::factory()->create([
            'first_name' => 'حصة',
            'last_name' => 'السريّع',
            'gender' => 'female',
            'photo_url' => 'people-photos/mother_private.jpg',
            'privacy_settings' => [
                'photo' => 'private',
            ],
        ]);

        $mainPerson = Person::factory()->create([
            'first_name' => 'فهد',
            'last_name' => 'السريّع',
            'gender' => 'male',
            'parent_id' => $father->id,
            'mother_id' => $mother->id,
            'photo_url' => null,
        ]);

        // As a guest, father's public photo should be seen, but mother's private photo should be masked to default female avatar
        $response = $this->get(route('people.profile.show', $mainPerson->id));

        $response->assertStatus(200);
        $response->assertSee('people-photos/father_photo.jpg');
        $response->assertDontSee('people-photos/mother_private.jpg');
        $response->assertSee('assets/img/avatar-female-hejap-1.png');
    }
}
