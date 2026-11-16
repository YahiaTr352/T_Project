<?php

namespace Tests\Feature;

use App\Models\AiProvider;
use App\Models\AiSetting;
use App\Services\AI\AiManager;
use App\Services\AI\Drivers\FakeAiDriver;
use App\Services\SiteMobileAccessToken;
use App\Support\AiSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiChatApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        AiSetting::setValue('features', [
            'chat' => ['enabled' => true, 'model' => 'fake-model'],
            'semantic_search' => ['enabled' => true, 'model' => null],
        ]);
        AiSetting::setValue('budget', ['monthly_usd' => 100, 'soft_stop' => true]);
        AiSettings::forgetCache();

        AiProvider::query()->updateOrCreate(
            ['driver' => 'openai'],
            [
                'name' => 'Fake OpenAI',
                'api_key' => 'test-key',
                'models' => ['chat' => 'fake-model', 'embedding' => 'fake-embed'],
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        $this->app->bind(AiManager::class, function () {
            return new class extends AiManager
            {
                public function resolve(?string $preferredDriver = null, ?string $purpose = null): \App\Services\AI\Contracts\AiProviderInterface
                {
                    return new FakeAiDriver;
                }

                public function withFallback(callable $callback, ?string $purpose = null)
                {
                    return $callback(new FakeAiDriver, new AiProvider([
                        'driver' => 'fake',
                        'name' => 'Fake',
                        'models' => [],
                    ]));
                }

                public function modelForFeature(string $feature, $driver = null): ?string
                {
                    return 'fake-model';
                }
            };
        });
    }

    public function test_chat_requires_site_token(): void
    {
        $this->postJson('/api/v1/ai/chat', ['message' => 'من هو فلان؟'])
            ->assertStatus(401);
    }

    public function test_chat_returns_answer_with_valid_token(): void
    {
        $token = SiteMobileAccessToken::issue()['token'];

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/ai/chat', ['message' => 'كم عدد أفراد العائلة؟']);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['answer', 'conversation_id', 'citations']);
    }

    public function test_suggestions_endpoint(): void
    {
        $token = SiteMobileAccessToken::issue()['token'];

        $this->withHeader('X-Site-Access-Token', $token)
            ->getJson('/api/v1/ai/suggestions')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['suggestions']);
    }

    public function test_authenticated_user_asking_who_am_i_returns_user_citation(): void
    {
        $person = \App\Models\Person::create([
            'first_name' => 'إبراهيم',
            'last_name' => 'السريّع',
            'gender' => 'male',
            'birth_date' => '1990-05-15',
        ]);

        $token = SiteMobileAccessToken::issueIdentityToken($person->id, 'face')['token'];

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/ai/chat', ['message' => 'من أنا؟']);

        $response->assertOk()
            ->assertJsonPath('ok', true);

        $citations = $response->json('citations');
        $this->assertNotEmpty($citations);
        $this->assertEquals($person->id, $citations[0]['id']);
        $this->assertEquals('person', $citations[0]['type']);
    }

    public function test_unknown_question_does_not_return_random_stories_or_random_citations(): void
    {
        \App\Models\Person::create([
            'first_name' => 'شخص',
            'last_name' => 'عشوائي',
            'gender' => 'male',
            'birth_date' => '2020-01-01',
        ]);
        \App\Models\Story::create([
            'title' => 'قصة عشوائية قديمة',
            'content' => 'محتوى قصة عشوائية',
        ]);

        $token = SiteMobileAccessToken::issue()['token'];

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/ai/chat', ['message' => 'ما هي عاصمة البرازيل؟']);

        $response->assertOk()
            ->assertJsonPath('ok', true);

        $citations = $response->json('citations');
        $this->assertEmpty($citations);
    }

    public function test_mobile_app_passing_explicit_person_id_resolves_viewer(): void
    {
        $person = \App\Models\Person::create([
            'first_name' => 'فهد',
            'last_name' => 'السريّع',
            'gender' => 'male',
            'birth_date' => '1985-10-20',
        ]);

        $token = SiteMobileAccessToken::issue()['token'];

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/ai/chat', [
                'message' => 'كم عمري؟',
                'person_id' => $person->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('ok', true);

        $citations = $response->json('citations');
        $this->assertNotEmpty($citations);
        $this->assertEquals($person->id, $citations[0]['id']);
    }

    public function test_first_person_kinship_calculates_relationship(): void
    {
        $grandfather = \App\Models\Person::create([
            'first_name' => 'إبراهيم',
            'last_name' => 'السريّع',
            'gender' => 'male',
        ]);

        $father = \App\Models\Person::create([
            'first_name' => 'محمد',
            'last_name' => 'السريّع',
            'parent_id' => $grandfather->id,
            'gender' => 'male',
        ]);

        $viewer = \App\Models\Person::create([
            'first_name' => 'عبد الله',
            'last_name' => 'السريّع',
            'parent_id' => $father->id,
            'gender' => 'male',
        ]);

        $uncle = \App\Models\Person::create([
            'first_name' => 'فهد',
            'last_name' => 'السريّع',
            'parent_id' => $grandfather->id,
            'gender' => 'male',
        ]);

        $cousin = \App\Models\Person::create([
            'first_name' => 'سعد',
            'last_name' => 'السريّع',
            'parent_id' => $uncle->id,
            'gender' => 'male',
        ]);

        $token = SiteMobileAccessToken::issueIdentityToken($viewer->id, 'face')['token'];

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/ai/chat', ['message' => 'إيش يقرب لي سعد بن فهد؟']);

        $response->assertOk()
            ->assertJsonPath('ok', true);

        $citations = $response->json('citations');
        $this->assertNotEmpty($citations);
        $ids = collect($citations)->pluck('id')->all();
        $this->assertContains($cousin->id, $ids);
    }
}

