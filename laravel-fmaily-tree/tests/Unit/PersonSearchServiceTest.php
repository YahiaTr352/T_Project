<?php

namespace Tests\Unit;

use App\Models\Person;
use App\Services\PersonSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonSearchServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PersonSearchService $service;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Queue::fake();
        $this->service = app(PersonSearchService::class);
    }

    public function test_preprocess_query_fuses_al_particles(): void
    {
        $this->assertEquals('منى ناصر الإبراهيم', $this->service->preprocessQuery('منى ناصر آل إبراهيم'));
        $this->assertEquals('منى ناصر السريع', $this->service->preprocessQuery('منى ناصر آل سريع'));
        $this->assertEquals('خالد المحمد', $this->service->preprocessQuery('خالد ال محمد'));
    }

    public function test_extract_tokens_ignores_isolated_family_particles(): void
    {
        $tokens = $this->service->extractTokens('منى ناصر آل');
        $this->assertEquals(['مني', 'ناصر'], $tokens);
    }

    public function test_search_mona_naser_al_abdulaziz_returns_exact_person(): void
    {
        $root = Person::create([
            'first_name' => 'عبدالعزيز',
            'gender'     => 'male',
        ]);

        $father = Person::create([
            'first_name' => 'ناصر',
            'gender'     => 'male',
            'parent_id'  => $root->id,
        ]);

        $mona = Person::create([
            'first_name' => 'منى',
            'last_name'  => 'السريع',
            'gender'     => 'female',
            'parent_id'  => $father->id,
        ]);

        $monira = Person::create([
            'first_name' => 'منيرة',
            'last_name'  => 'السريع',
            'gender'     => 'female',
            'parent_id'  => $father->id,
        ]);

        $query = Person::query();
        $this->service->applySearch($query, 'منى ناصر العبدالعزيز');
        $results = $this->service->rankCollection($query->get(), 'منى ناصر العبدالعزيز');

        $this->assertNotEmpty($results);
        $this->assertEquals($mona->id, $results->first()->id);
        $this->assertFalse($results->contains('id', $monira->id));
    }

    public function test_search_mona_with_trailing_space_does_not_return_monira(): void
    {
        $mona = Person::create([
            'first_name' => 'منى',
            'last_name'  => 'السريع',
            'gender'     => 'female',
        ]);

        $monira = Person::create([
            'first_name' => 'منيرة',
            'last_name'  => 'السريع',
            'gender'     => 'female',
        ]);

        $query = Person::query();
        $this->service->applySearch($query, 'منى ');
        $results = $this->service->rankCollection($query->get(), 'منى ');

        $this->assertNotEmpty($results);
        $this->assertTrue($results->contains('id', $mona->id));
        $this->assertFalse($results->contains('id', $monira->id));
    }

    public function test_search_mona_naser_al_sorayia_matches_with_last_name(): void
    {
        $father = Person::create([
            'first_name' => 'ناصر',
            'gender'     => 'male',
        ]);

        $mona = Person::create([
            'first_name' => 'منى',
            'last_name'  => 'السريع',
            'gender'     => 'female',
            'parent_id'  => $father->id,
        ]);

        $query = Person::query();
        $this->service->applySearch($query, 'منى ناصر السريع');
        $results = $this->service->rankCollection($query->get(), 'منى ناصر السريع');

        $this->assertNotEmpty($results);
        $this->assertEquals($mona->id, $results->first()->id);
    }

    public function test_search_al_prefix_fuses_al_ibrahim(): void
    {
        $grandfather = Person::create([
            'first_name' => 'ابراهيم',
            'gender'     => 'male',
        ]);

        $father = Person::create([
            'first_name' => 'ناصر',
            'gender'     => 'male',
            'parent_id'  => $grandfather->id,
        ]);

        $monira = Person::create([
            'first_name' => 'منيرة',
            'last_name'  => 'السريع',
            'gender'     => 'female',
            'parent_id'  => $father->id,
        ]);

        $query = Person::query();
        $this->service->applySearch($query, 'منيرة ناصر آل إبراهيم');
        $results = $this->service->rankCollection($query->get(), 'منيرة ناصر آل إبراهيم');

        $this->assertNotEmpty($results);
        $this->assertEquals($monira->id, $results->first()->id);
    }
}
