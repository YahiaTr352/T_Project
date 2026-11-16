<?php

namespace Tests\Feature;

use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FamilyTreeSearchApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Queue::fake();
        config(['site.password_protection_enabled' => false]);
    }

    public function test_api_persons_search_returns_all_persons_when_no_term(): void
    {
        Person::factory()->count(3)->create([
            'from_outside_the_family' => false,
        ]);

        $response = $this->getJson('/api/persons/search');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'persons' => [
                    '*' => ['id', 'first_name', 'last_name', 'full_name', 'gender', 'from_outside_the_family']
                ],
                'data'
            ]);

        $this->assertCount(3, $response->json('persons'));
    }

    public function test_api_persons_search_ranks_relevance_closest_first(): void
    {
        // 1. Ancestor named ممدوح (Grandfather)
        $grandfather = Person::create([
            'first_name' => 'ممدوح',
            'last_name'  => 'السريّع',
            'gender'     => 'male',
            'from_outside_the_family' => false,
        ]);

        // 2. Father whose father is ممدوح (e.g. فهد بن ممدوح)
        $father = Person::create([
            'first_name' => 'فهد',
            'last_name'  => 'السريّع',
            'gender'     => 'male',
            'parent_id'  => $grandfather->id,
            'from_outside_the_family' => false,
        ]);

        // 3. Grandson whose grandfather is ممدوح (e.g. محمد بن فهد بن ممدوح)
        $grandson = Person::create([
            'first_name' => 'محمد',
            'last_name'  => 'السريّع',
            'gender'     => 'male',
            'parent_id'  => $father->id,
            'from_outside_the_family' => false,
        ]);

        // Search for "ممدوح"
        $response = $this->getJson('/api/persons/search?term=ممدوح');

        $response->assertOk();
        $results = $response->json('persons');
        $this->assertNotEmpty($results);

        // Grandfather (Self = ممدوح) MUST be #1
        $this->assertEquals($grandfather->id, $results[0]['id']);

        // Father (Child = فهد بن ممدوح) MUST be #2
        $this->assertEquals($father->id, $results[1]['id']);

        // Grandson (محمد بن فهد بن ممدوح) MUST be #3
        $this->assertEquals($grandson->id, $results[2]['id']);
    }

    public function test_api_persons_search_ranks_patronymic_compound_name(): void
    {
        $grandfather = Person::create([
            'first_name' => 'فهد',
            'last_name'  => 'السريّع',
            'gender'     => 'male',
            'from_outside_the_family' => false,
        ]);

        $mamdouh = Person::create([
            'first_name' => 'ممدوح',
            'last_name'  => 'السريّع',
            'gender'     => 'male',
            'parent_id'  => $grandfather->id, // ممدوح بن فهد
            'from_outside_the_family' => false,
        ]);

        $other = Person::create([
            'first_name' => 'عبدالله',
            'last_name'  => 'السريّع',
            'gender'     => 'male',
            'parent_id'  => $grandfather->id, // عبدالله بن فهد
            'from_outside_the_family' => false,
        ]);

        // Searching "ممدوح فهد" (without "بن")
        $response = $this->getJson('/api/persons/search?term=ممدوح فهد');
        $response->assertOk();
        $results = $response->json('persons');

        $this->assertNotEmpty($results);
        $this->assertEquals($mamdouh->id, $results[0]['id']);

        // Searching "ممدوح بن فهد"
        $response2 = $this->getJson('/api/persons/search?term=ممدوح بن فهد');
        $response2->assertOk();
        $results2 = $response2->json('persons');

        $this->assertNotEmpty($results2);
        $this->assertEquals($mamdouh->id, $results2[0]['id']);
    }
}
