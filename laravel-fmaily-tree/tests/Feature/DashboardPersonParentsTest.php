<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DashboardPersonParentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('people.create'));
        $this->actingAs($user);
    }

    public function test_dashboard_addition_requires_both_parents(): void
    {
        $this->get(route('people.create'))
            ->assertOk()
            ->assertSee('name="parent_id" class="form-control ppl-form-control js-required-parent" required', false)
            ->assertSee('name="mother_id" class="form-control ppl-form-control js-required-parent" required', false);

        $this->post(route('people.store'), [
            'first_name' => 'Dashboard member',
            'gender' => 'male',
            'from_outside_the_family' => '0',
            'source_page' => 'add_self',
        ])->assertSessionHasErrors(['parent_id', 'mother_id']);

        $this->assertDatabaseMissing('persons', ['first_name' => 'Dashboard member']);
    }

    public function test_outside_member_cannot_be_saved_with_only_one_parent(): void
    {
        $father = Person::factory()->create(['gender' => 'male']);

        $this->post(route('persons.store.outside'), [
            'first_name' => 'Outside member',
            'gender' => 'female',
            'parent_id' => $father->id,
        ])->assertSessionHasErrors('mother_id')
            ->assertSessionDoesntHaveErrors('parent_id');

        $this->assertDatabaseMissing('persons', ['first_name' => 'Outside member']);
    }

    public function test_profile_addition_forms_require_the_parents_that_are_not_prefilled(): void
    {
        $father = Person::create(['first_name' => 'Father', 'gender' => 'male']);
        $mother = Person::create(['first_name' => 'Mother', 'gender' => 'female']);

        $this->get(route('people.show', $father))->assertOk()
            ->assertSee('type="hidden" name="parent_id" value="' . $father->id . '"', false)
            ->assertSee('id="child_mother_id" name="mother_id" class="form-control ppl-form-control js-required-parent" required', false)
            ->assertSee('id="outside_parent_id" name="parent_id" class="form-control ppl-form-control js-required-parent" required', false)
            ->assertSee('id="outside_mother_id" name="mother_id" class="form-control ppl-form-control js-required-parent" required', false);

        $this->get(route('people.show', $mother))->assertOk()
            ->assertSee('type="hidden" name="mother_id" value="' . $mother->id . '"', false)
            ->assertSee('id="child_parent_id" name="parent_id" class="form-control ppl-form-control js-required-parent" required', false);
    }

    public function test_both_dashboard_addition_routes_save_the_selected_parents(): void
    {
        $father = Person::factory()->create(['gender' => 'male', 'from_outside_the_family' => true]);
        $mother = Person::factory()->create(['gender' => 'female', 'from_outside_the_family' => true]);
        $parents = ['parent_id' => $father->id, 'mother_id' => $mother->id];

        $this->post(route('people.store'), $parents + [
            'first_name' => 'Inside member',
            'gender' => 'male',
            'from_outside_the_family' => '0',
        ])->assertSessionHas('success');

        $this->post(route('persons.store.outside'), $parents + [
            'first_name' => 'Outside member',
            'gender' => 'female',
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('persons', $parents + ['first_name' => 'Inside member', 'from_outside_the_family' => false]);
        $this->assertDatabaseHas('persons', $parents + ['first_name' => 'Outside member', 'from_outside_the_family' => true]);
        $this->assertSame(2, $father->refresh()->descendants()->count());
    }

    public function test_marriages_page_also_requires_parents_when_adding_an_outside_member(): void
    {
        $this->app['auth']->user()->givePermissionTo(Permission::findOrCreate('marriages.view'));

        $this->get(route('marriages.index'))->assertOk()
            ->assertSee('id="outside_parent_id" name="parent_id" class="form-control ppl-form-control js-required-parent" required', false)
            ->assertSee('id="outside_mother_id" name="mother_id" class="form-control ppl-form-control js-required-parent" required', false);
    }

    public function test_website_self_registration_keeps_parents_optional(): void
    {
        $this->post(route('people.store.self'), [
            'first_name' => 'Self registration',
            'gender' => 'male',
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('persons', ['first_name' => 'Self registration', 'parent_id' => null, 'mother_id' => null]);
    }
}
