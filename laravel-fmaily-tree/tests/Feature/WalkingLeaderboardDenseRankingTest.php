<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Person;
use App\Models\WalkingRecord;
use App\Services\Walking\WalkingLeaderboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WalkingLeaderboardDenseRankingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        config(['site.password_protection_enabled' => false]);
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    public function test_dense_ranking_does_not_skip_ranks_when_multiple_participants_tie(): void
    {
        $today = now()->toDateString();
        $persons = [];

        // Create 25 male persons
        for ($i = 1; $i <= 25; $i++) {
            $persons[$i] = Person::create([
                'first_name' => "متسابق $i",
                'last_name'  => 'السريّع',
                'gender'     => 'male',
            ]);
        }

        // Give them steps:
        // Rank 1: Person 1 (25000)
        // Rank 2: Person 2 (24000)
        // Rank 3: Person 3 (23000)
        // Rank 4: Person 4 (22000)
        // Rank 5: Person 5 (21000)
        // Rank 6: Person 6 (20000)
        // Rank 7: Person 7 (19000)
        // Rank 8: Person 8 (18000)
        // Rank 9: Person 9 (17000)
        // Rank 10: Persons 10, 11, 12, 13, 14, 15 (tied at 10000)
        // In the old broken logic, the next person would jump to Rank 16!
        // In Dense Ranking, the next person MUST be Rank 11!
        for ($i = 1; $i <= 9; $i++) {
            WalkingRecord::create([
                'person_id' => $persons[$i]->id,
                'date'      => $today,
                'steps'     => 26000 - ($i * 1000),
            ]);
        }

        // Tied at rank 10
        for ($i = 10; $i <= 15; $i++) {
            WalkingRecord::create([
                'person_id' => $persons[$i]->id,
                'date'      => $today,
                'steps'     => 10000,
            ]);
        }

        // Subsequent persons
        for ($i = 16; $i <= 25; $i++) {
            WalkingRecord::create([
                'person_id' => $persons[$i]->id,
                'date'      => $today,
                'steps'     => 10000 - (($i - 15) * 500),
            ]);
        }

        $service = app(WalkingLeaderboardService::class);

        // Page 1: 10 items
        $page1 = $service->build('today', null, null, null, null, 1, 10);
        $this->assertCount(10, $page1['top']);
        $p1Ranks = $page1['top']->pluck('rank')->toArray();
        // 1, 2, 3, 4, 5, 6, 7, 8, 9, 10
        $this->assertEquals([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], $p1Ranks);

        // Page 2: 10 items (offset 10)
        $page2 = $service->build('today', null, null, null, null, 2, 10);
        $this->assertCount(10, $page2['top']);
        $p2Ranks = $page2['top']->pluck('rank')->toArray();

        // Persons 11..15 are also tied at rank 10, Person 16 MUST BE RANK 11 (Dense Ranking)!
        // Old broken competition ranking would jump Person 16 to rank 16!
        $this->assertEquals(10, $p2Ranks[0]); // Person 11
        $this->assertEquals(10, $p2Ranks[4]); // Person 15
        $this->assertEquals(11, $p2Ranks[5]); // Person 16: MUST BE 11, NOT 16 OR 21!
        $this->assertEquals(12, $p2Ranks[6]); // Person 17: MUST BE 12
        $this->assertEquals(13, $p2Ranks[7]); // Person 18: MUST BE 13
        $this->assertEquals(14, $p2Ranks[8]); // Person 19: MUST BE 14
        $this->assertEquals(15, $p2Ranks[9]); // Person 20: MUST BE 15
    }
}
