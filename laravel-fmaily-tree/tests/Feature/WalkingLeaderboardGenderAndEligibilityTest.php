<?php

namespace Tests\Feature;

use App\Models\Marriage;
use App\Models\Breastfeeding;
use App\Models\Person;
use App\Models\WalkingRecord;
use App\Services\AI\EmbeddingsService;
use App\Services\SiteMobileAccessToken;
use App\Services\Walking\WalkingLeaderboardEligibilityService;
use App\Services\Walking\WalkingLeaderboardService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WalkingLeaderboardGenderAndEligibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        config(['site.password_protection_enabled' => false]);

        $this->mock(EmbeddingsService::class, function ($mock) {
            $mock->shouldIgnoreMissing();
        });

        app(WalkingLeaderboardEligibilityService::class)->clearCache();
    }

    /**
     * Create an identity token header for a given Person.
     */
    protected function identityHeaders(Person $person): array
    {
        $issued = SiteMobileAccessToken::issue(60, [
            'person_id'   => $person->id,
            'auth_method' => 'face',
        ]);

        return [
            'X-Site-Access-Token' => $issued['token'],
            'Accept'              => 'application/json',
        ];
    }

    /**
     * 1. Male viewer CANNOT see female participants on leaderboard.
     */
    public function test_male_viewer_cannot_see_female_participants_on_leaderboard(): void
    {
        $today = Carbon::today()->toDateString();

        $maleMember = Person::factory()->create([
            'gender'                  => 'male',
            'from_outside_the_family' => false,
        ]);

        $femaleMember = Person::factory()->create([
            'gender'                  => 'female',
            'from_outside_the_family' => false,
        ]);

        $maleViewer = Person::factory()->create([
            'gender'                  => 'male',
            'from_outside_the_family' => false,
        ]);

        WalkingRecord::create([
            'person_id'        => $femaleMember->id,
            'steps'            => 15000,
            'date'             => $today,
            'distance_km'      => 10.0,
            'duration_minutes' => 90,
            'calories'         => 600,
        ]);

        WalkingRecord::create([
            'person_id'        => $maleMember->id,
            'steps'            => 10000,
            'date'             => $today,
            'distance_km'      => 7.0,
            'duration_minutes' => 60,
            'calories'         => 400,
        ]);

        $response = $this->withHeaders($this->identityHeaders($maleViewer))
            ->getJson('/api/v1/walking/leaderboard?period=today');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertEquals('male', $data['gender_visibility']['active_gender_filter']);
        $this->assertFalse($data['gender_visibility']['can_toggle_gender']);

        $topPersonIds = collect($data['top'])->pluck('person_id')->toArray();
        $this->assertContains($maleMember->id, $topPersonIds);
        $this->assertNotContains($femaleMember->id, $topPersonIds);

        // Male member should be rank 1 among males
        $maleEntry = collect($data['top'])->firstWhere('person_id', $maleMember->id);
        $this->assertEquals(1, $maleEntry['rank']);
    }

    /**
     * 2. Male viewer CANNOT bypass female privacy by passing query parameters.
     */
    public function test_male_viewer_cannot_override_gender_filter_to_see_females(): void
    {
        $today = Carbon::today()->toDateString();

        $femaleMember = Person::factory()->create([
            'gender'                  => 'female',
            'from_outside_the_family' => false,
        ]);

        $maleViewer = Person::factory()->create([
            'gender'                  => 'male',
            'from_outside_the_family' => false,
        ]);

        WalkingRecord::create([
            'person_id' => $femaleMember->id,
            'steps'     => 12000,
            'date'      => $today,
        ]);

        // Attempt override with gender_filter=all
        $resAll = $this->withHeaders($this->identityHeaders($maleViewer))
            ->getJson('/api/v1/walking/leaderboard?period=today&gender_filter=all');

        $resAll->assertStatus(200);
        $topIdsAll = collect($resAll->json('data.top'))->pluck('person_id')->toArray();
        $this->assertNotContains($femaleMember->id, $topIdsAll);

        // Attempt override with gender_filter=female
        $resFemale = $this->withHeaders($this->identityHeaders($maleViewer))
            ->getJson('/api/v1/walking/leaderboard?period=today&gender_filter=female');

        $resFemale->assertStatus(200);
        $topIdsFemale = collect($resFemale->json('data.top'))->pluck('person_id')->toArray();
        $this->assertNotContains($femaleMember->id, $topIdsFemale);
    }

    /**
     * 3. Female viewer CANNOT see male participants on leaderboard (strict segregation, no mix option).
     */
    public function test_female_viewer_cannot_see_male_participants_on_leaderboard(): void
    {
        $today = Carbon::today()->toDateString();

        $maleMember = Person::factory()->create([
            'gender'                  => 'male',
            'from_outside_the_family' => false,
        ]);

        $femaleMember = Person::factory()->create([
            'gender'                  => 'female',
            'from_outside_the_family' => false,
        ]);

        $femaleViewer = Person::factory()->create([
            'gender'                  => 'female',
            'from_outside_the_family' => false,
        ]);

        WalkingRecord::create(['person_id' => $femaleMember->id, 'steps' => 15000, 'date' => $today]);
        WalkingRecord::create(['person_id' => $maleMember->id, 'steps' => 10000, 'date' => $today]);

        // Attempting to query with gender_filter=all must NOT return males
        $response = $this->withHeaders($this->identityHeaders($femaleViewer))
            ->getJson('/api/v1/walking/leaderboard?period=today&gender_filter=all');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertFalse($data['gender_visibility']['can_toggle_gender']);
        $this->assertEquals('female', $data['gender_visibility']['active_gender_filter']);

        $topPersonIds = collect($data['top'])->pluck('person_id')->toArray();
        $this->assertContains($femaleMember->id, $topPersonIds);
        $this->assertNotContains($maleMember->id, $topPersonIds);
    }

    /**
     * 4. Female viewer can filter to females only.
     */
    public function test_female_viewer_can_see_only_females_when_selecting_female(): void
    {
        $today = Carbon::today()->toDateString();

        $maleMember = Person::factory()->create([
            'gender'                  => 'male',
            'from_outside_the_family' => false,
        ]);

        $femaleMember = Person::factory()->create([
            'gender'                  => 'female',
            'from_outside_the_family' => false,
        ]);

        $femaleViewer = Person::factory()->create([
            'gender'                  => 'female',
            'from_outside_the_family' => false,
        ]);

        WalkingRecord::create(['person_id' => $femaleMember->id, 'steps' => 15000, 'date' => $today]);
        WalkingRecord::create(['person_id' => $maleMember->id, 'steps' => 10000, 'date' => $today]);

        $response = $this->withHeaders($this->identityHeaders($femaleViewer))
            ->getJson('/api/v1/walking/leaderboard?period=today&gender_filter=female');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertEquals('female', $data['gender_visibility']['active_gender_filter']);

        $topPersonIds = collect($data['top'])->pluck('person_id')->toArray();
        $this->assertContains($femaleMember->id, $topPersonIds);
        $this->assertNotContains($maleMember->id, $topPersonIds);
    }

    /**
     * 5. Isolated outsider (no family marriage, no family parents) is strictly excluded.
     */
    public function test_isolated_outsider_is_strictly_excluded_from_leaderboard(): void
    {
        $today = Carbon::today()->toDateString();

        // Person from outside the family, no spouse from family, no parents from family
        $isolatedOutsider = Person::factory()->create([
            'gender'                  => 'male',
            'from_outside_the_family' => true,
            'parent_id'               => null,
            'mother_id'               => null,
        ]);

        $familyMale = Person::factory()->create([
            'gender'                  => 'male',
            'from_outside_the_family' => false,
        ]);

        WalkingRecord::create(['person_id' => $isolatedOutsider->id, 'steps' => 50000, 'date' => $today]);
        WalkingRecord::create(['person_id' => $familyMale->id, 'steps' => 10000, 'date' => $today]);

        $service = app(WalkingLeaderboardService::class);
        $leaderboard = $service->build('today', $familyMale->id);

        $topPersonIds = collect($leaderboard['top'])->pluck('person_id')->toArray();

        $this->assertNotContains($isolatedOutsider->id, $topPersonIds);
        $this->assertContains($familyMale->id, $topPersonIds);
    }

    /**
     * 6. In-law married to a family member IS included on leaderboard.
     */
    public function test_in_laws_married_to_family_are_included_on_leaderboard(): void
    {
        $today = Carbon::today()->toDateString();

        $familyWife = Person::factory()->create([
            'gender'                  => 'female',
            'from_outside_the_family' => false,
        ]);

        $outsideHusband = Person::factory()->create([
            'gender'                  => 'male',
            'from_outside_the_family' => true,
        ]);

        Marriage::create([
            'husband_id' => $outsideHusband->id,
            'wife_id'    => $familyWife->id,
            'married_at' => '2020-01-01',
        ]);

        app(WalkingLeaderboardEligibilityService::class)->clearCache();

        WalkingRecord::create(['person_id' => $outsideHusband->id, 'steps' => 20000, 'date' => $today]);

        $service = app(WalkingLeaderboardService::class);
        $leaderboard = $service->build('today', $outsideHusband->id);

        $topPersonIds = collect($leaderboard['top'])->pluck('person_id')->toArray();
        $this->assertContains($outsideHusband->id, $topPersonIds);
    }

    /**
     * 7. Outside person whose mother is from the family IS included on leaderboard.
     */
    public function test_outside_person_with_family_mother_is_included_on_leaderboard(): void
    {
        $today = Carbon::today()->toDateString();

        $familyMother = Person::factory()->create([
            'gender'                  => 'female',
            'from_outside_the_family' => false,
        ]);

        $outsideChild = Person::factory()->create([
            'gender'                  => 'male',
            'from_outside_the_family' => true,
            'mother_id'               => $familyMother->id,
        ]);

        app(WalkingLeaderboardEligibilityService::class)->clearCache();

        WalkingRecord::create(['person_id' => $outsideChild->id, 'steps' => 18000, 'date' => $today]);

        $service = app(WalkingLeaderboardService::class);
        $leaderboard = $service->build('today', $outsideChild->id);

        $topPersonIds = collect($leaderboard['top'])->pluck('person_id')->toArray();
        $this->assertContains($outsideChild->id, $topPersonIds);
    }

    /**
     * 8. Outside child (parents from outside) nursed by family mother IS included on leaderboard.
     */
    public function test_outside_person_breastfed_by_family_mother_is_included_on_leaderboard(): void
    {
        $today = Carbon::today()->toDateString();

        $familyMother = Person::factory()->create([
            'gender'                  => 'female',
            'from_outside_the_family' => false,
        ]);

        $outsideFather = Person::factory()->create([
            'gender'                  => 'male',
            'from_outside_the_family' => true,
        ]);

        $outsideBiologicalMother = Person::factory()->create([
            'gender'                  => 'female',
            'from_outside_the_family' => true,
        ]);

        // Child whose biological parents are strictly outside the family
        $outsideChild = Person::factory()->create([
            'gender'                  => 'male',
            'from_outside_the_family' => true,
            'parent_id'               => $outsideFather->id,
            'mother_id'               => $outsideBiologicalMother->id,
        ]);

        // Breastfed by mother from inside the family
        Breastfeeding::create([
            'nursing_mother_id'  => $familyMother->id,
            'breastfed_child_id' => $outsideChild->id,
            'is_active'          => true,
        ]);

        WalkingRecord::create(['person_id' => $outsideChild->id, 'steps' => 22000, 'date' => $today]);

        $service = app(WalkingLeaderboardService::class);
        $leaderboard = $service->build('today', $outsideChild->id);

        $topPersonIds = collect($leaderboard['top'])->pluck('person_id')->toArray();
        $this->assertContains($outsideChild->id, $topPersonIds);
    }

    /**
     * 9. Outside child with family breastfeeding father IS included on leaderboard.
     */
    public function test_outside_person_with_family_breastfeeding_father_is_included_on_leaderboard(): void
    {
        $today = Carbon::today()->toDateString();

        $familyFather = Person::factory()->create([
            'gender'                  => 'male',
            'from_outside_the_family' => false,
        ]);

        $outsideMother = Person::factory()->create([
            'gender'                  => 'female',
            'from_outside_the_family' => true,
        ]);

        $outsideChild = Person::factory()->create([
            'gender'                  => 'male',
            'from_outside_the_family' => true,
            'parent_id'               => null,
            'mother_id'               => null,
        ]);

        // Breastfeeding relationship with father from inside the family
        Breastfeeding::create([
            'nursing_mother_id'        => $outsideMother->id,
            'breastfeeding_father_id'  => $familyFather->id,
            'breastfed_child_id'       => $outsideChild->id,
            'is_active'                => true,
        ]);

        WalkingRecord::create(['person_id' => $outsideChild->id, 'steps' => 19000, 'date' => $today]);

        $service = app(WalkingLeaderboardService::class);
        $leaderboard = $service->build('today', $outsideChild->id);

        $topPersonIds = collect($leaderboard['top'])->pluck('person_id')->toArray();
        $this->assertContains($outsideChild->id, $topPersonIds);
    }

    /**
     * 10. Outside mother who nursed a family child IS included on leaderboard.
     */
    public function test_outside_mother_who_nursed_family_child_is_included_on_leaderboard(): void
    {
        $today = Carbon::today()->toDateString();

        $familyChild = Person::factory()->create([
            'gender'                  => 'male',
            'from_outside_the_family' => false,
        ]);

        $outsideNursingMother = Person::factory()->create([
            'gender'                  => 'female',
            'from_outside_the_family' => true,
            'parent_id'               => null,
            'mother_id'               => null,
        ]);

        Breastfeeding::create([
            'nursing_mother_id'  => $outsideNursingMother->id,
            'breastfed_child_id' => $familyChild->id,
            'is_active'          => true,
        ]);

        WalkingRecord::create(['person_id' => $outsideNursingMother->id, 'steps' => 14000, 'date' => $today]);

        $service = app(WalkingLeaderboardService::class);
        $leaderboard = $service->build('today', $outsideNursingMother->id);

        $topPersonIds = collect($leaderboard['top'])->pluck('person_id')->toArray();
        $this->assertContains($outsideNursingMother->id, $topPersonIds);
    }

    /**
     * 11. Isolated outsider without father, mother, spouse, or breastfeeding is strictly excluded.
     */
    public function test_isolated_outsider_without_any_connection_is_strictly_excluded(): void
    {
        $today = Carbon::today()->toDateString();

        $isolated = Person::factory()->create([
            'gender'                  => 'male',
            'from_outside_the_family' => true,
            'parent_id'               => null,
            'mother_id'               => null,
        ]);

        $familyMember = Person::factory()->create([
            'gender'                  => 'male',
            'from_outside_the_family' => false,
        ]);

        WalkingRecord::create(['person_id' => $isolated->id, 'steps' => 50000, 'date' => $today]);
        WalkingRecord::create(['person_id' => $familyMember->id, 'steps' => 10000, 'date' => $today]);

        $service = app(WalkingLeaderboardService::class);
        $leaderboard = $service->build('today', $familyMember->id);

        $topPersonIds = collect($leaderboard['top'])->pluck('person_id')->toArray();
        $this->assertNotContains($isolated->id, $topPersonIds);
        $this->assertContains($familyMember->id, $topPersonIds);
    }
}
