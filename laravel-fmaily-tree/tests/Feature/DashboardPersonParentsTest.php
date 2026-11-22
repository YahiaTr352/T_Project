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

    public function test_adding_an_inside_member_requires_both_parents(): void
    {
        $this->withSession(['_old_input' => ['from_outside_the_family' => '0']])->get(route('people.create'))
            ->assertOk()
            ->assertSee('name="parent_id" class="form-control ppl-form-control js-parent-select" required', false)
            ->assertSee('name="mother_id" class="form-control ppl-form-control js-parent-select" required', false);

        $this->post(route('people.store'), [
            'first_name' => 'Dashboard member',
            'gender' => 'male',
            'from_outside_the_family' => '0',
            'source_page' => 'add_self',
        ])->assertSessionHasErrors(['parent_id', 'mother_id']);

        $this->postJson(route('people.store'), [
            'first_name' => 'Dashboard member',
            'gender' => 'male',
            'from_outside_the_family' => false,
        ])->assertUnprocessable()->assertJsonValidationErrors(['parent_id', 'mother_id']);

        $this->assertDatabaseMissing('persons', ['first_name' => 'Dashboard member']);
    }

    public function test_both_dashboard_routes_can_add_an_outside_member_without_parents(): void
    {
        $this->withSession(['_old_input' => ['from_outside_the_family' => '1']])->get(route('people.create'))
            ->assertOk()
            ->assertSee('id="parent_id" name="parent_id" class="form-control ppl-form-control js-parent-select" >', false)
            ->assertSee('id="mother_id" name="mother_id" class="form-control ppl-form-control js-parent-select" >', false);

        $this->post(route('people.store'), [
            'first_name' => 'Outside member from People',
            'gender' => 'male',
            'from_outside_the_family' => '1',
            'parent_id' => '',
            'mother_id' => '',
        ])->assertSessionHas('success');

        $this->post(route('persons.store.outside'), [
            'first_name' => 'Outside member from Marriages',
            'gender' => 'female',
        ])->assertSessionHas('success');

        $expected = ['from_outside_the_family' => true, 'parent_id' => null, 'mother_id' => null];
        $this->assertDatabaseHas('persons', $expected + ['first_name' => 'Outside member from People']);
        $this->assertDatabaseHas('persons', $expected + ['first_name' => 'Outside member from Marriages']);
    }

    public function test_outside_member_can_be_saved_with_one_valid_parent(): void
    {
        $father = Person::factory()->create(['gender' => 'male']);

        $this->post(route('persons.store.outside'), [
            'first_name' => 'Outside member',
            'gender' => 'female',
            'parent_id' => $father->id,
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('persons', ['first_name' => 'Outside member', 'parent_id' => $father->id, 'mother_id' => null]);
    }

    public function test_profile_requires_the_other_parent_for_children_and_keeps_outside_parents_optional(): void
    {
        $father = Person::create(['first_name' => 'Father', 'gender' => 'male']);
        $mother = Person::create(['first_name' => 'Mother', 'gender' => 'female']);

        $this->get(route('people.show', $father))->assertOk()
            ->assertSee('type="hidden" name="parent_id" value="' . $father->id . '"', false)
            ->assertSee('id="child_mother_id" name="mother_id" class="form-control ppl-form-control js-parent-select" required', false)
            ->assertSee('id="outside_parent_id" name="parent_id" class="form-control ppl-form-control js-parent-select" >', false)
            ->assertSee('id="outside_mother_id" name="mother_id" class="form-control ppl-form-control js-parent-select" >', false);

        $this->get(route('people.show', $mother))->assertOk()
            ->assertSee('type="hidden" name="mother_id" value="' . $mother->id . '"', false)
            ->assertSee('id="child_parent_id" name="parent_id" class="form-control ppl-form-control js-parent-select" required', false);
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

    public function test_marriages_page_keeps_parents_optional_when_adding_an_outside_member(): void
    {
        $this->app['auth']->user()->givePermissionTo(Permission::findOrCreate('marriages.view'));

        $this->get(route('marriages.index'))->assertOk()
            ->assertSee('id="outside_parent_id" name="parent_id" class="form-control ppl-form-control js-parent-select" >', false)
            ->assertSee('id="outside_mother_id" name="mother_id" class="form-control ppl-form-control js-parent-select" >', false);
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
