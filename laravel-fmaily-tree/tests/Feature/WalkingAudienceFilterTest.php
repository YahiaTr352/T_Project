<?php

namespace Tests\Feature;

use App\Services\Walking\PersonAudienceFilterService;
use Carbon\Carbon;
use Tests\TestCase;

class WalkingAudienceFilterTest extends TestCase
{
    protected PersonAudienceFilterService $filterService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filterService = app(PersonAudienceFilterService::class);
    }

    /**
     * Helper to normalize SQL by stripping backticks and double quotes.
     */
    protected function normalizeSql(string $sql): string
    {
        return str_replace(['`', '"'], '', strtolower($sql));
    }

    /**
     * Test female preset generates correct SQL constraints.
     */
    public function test_females_only_preset_applies_gender_and_safety_checks(): void
    {
        $query = $this->filterService->buildQuery(['preset' => 'females_only']);
        $sql = $this->normalizeSql($query->toSql());

        $this->assertStringContainsString('gender = ?', $sql);
        $this->assertStringContainsString('death_date is null', $sql);
        $this->assertStringContainsString('from_outside_the_family = ?', $sql);
        $this->assertContains('female', $query->getBindings());
    }

    /**
     * Test male preset generates correct SQL constraints.
     */
    public function test_males_only_preset_applies_gender(): void
    {
        $query = $this->filterService->buildQuery(['preset' => 'males_only']);
        $sql = $this->normalizeSql($query->toSql());

        $this->assertStringContainsString('gender = ?', $sql);
        $this->assertContains('male', $query->getBindings());
    }

    /**
     * Test above 30 preset calculates birth date boundary correctly.
     */
    public function test_above_30_preset_applies_correct_birth_date(): void
    {
        $query = $this->filterService->buildQuery(['preset' => 'above_30']);
        $sql = $this->normalizeSql($query->toSql());

        $expectedDate = Carbon::now()->subYears(30)->toDateString();
        $this->assertStringContainsString('birth_date <= ?', $sql);
        $this->assertContains($expectedDate, $query->getBindings());
    }

    /**
     * Test custom age range filters.
     */
    public function test_custom_age_range_applies_min_and_max_dates(): void
    {
        $query = $this->filterService->buildQuery([
            'min_age' => 20,
            'max_age' => 45,
        ]);
        $sql = $this->normalizeSql($query->toSql());

        $expectedMaxBirthDate = Carbon::now()->subYears(20)->toDateString();
        $expectedMinBirthDate = Carbon::now()->subYears(46)->addDay()->toDateString();

        $this->assertStringContainsString('birth_date <= ?', $sql);
        $this->assertStringContainsString('birth_date >= ?', $sql);
        $this->assertContains($expectedMaxBirthDate, $query->getBindings());
        $this->assertContains($expectedMinBirthDate, $query->getBindings());
    }

    /**
     * Test youth preset covers ages 18 to 35.
     */
    public function test_youth_preset_applies_18_to_35_boundary(): void
    {
        $query = $this->filterService->buildQuery(['preset' => 'youth']);
        $sql = $this->normalizeSql($query->toSql());

        $expectedMaxBirthDate = Carbon::now()->subYears(18)->toDateString();
        $expectedMinBirthDate = Carbon::now()->subYears(36)->addDay()->toDateString();

        $this->assertStringContainsString('birth_date <= ?', $sql);
        $this->assertStringContainsString('birth_date >= ?', $sql);
        $this->assertContains($expectedMaxBirthDate, $query->getBindings());
        $this->assertContains($expectedMinBirthDate, $query->getBindings());
    }
}
