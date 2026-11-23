<?php

namespace Tests\Feature\Dashboard;

use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PersonCalendarDateTest extends TestCase
{
    use RefreshDatabase;

    public function test_person_details_preserve_the_saved_calendar_day_in_both_calendars(): void
    {
        config(['site.password_protection_enabled' => false]);
        $user = User::factory()->createQuietly();
        $user->givePermissionTo(Permission::findOrCreate('people.view', 'web'));
        $person = Person::factory()->createQuietly([
            'birth_date' => '2026-11-23',
            'death_date' => null,
        ]);
        $storedBirthDate = $person->getRawOriginal('birth_date');

        $this->actingAs($user)->get(route('people.show', $person))
            ->assertOk()
            ->assertSee('٢٣ نوفمبر ٢٠٢٦')
            ->assertSee('١٣ جمادى الآخرة ١٤٤٨ هـ');

        $this->assertSame($storedBirthDate, $person->fresh()->getRawOriginal('birth_date'));
    }
}
