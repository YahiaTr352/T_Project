<?php

namespace Tests\Feature\Dashboard;

use App\Models\Marriage;
use App\Models\Person;
use App\Models\User;
use App\Services\MarriageOrderService;
use App\Services\TreeCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ChildOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_change_to_child_order_requires_the_same_confirmation(): void
    {
        config(['site.password_protection_enabled' => false]);
        Queue::fake();
        $admin = User::factory()->createQuietly();
        foreach (['people.view', 'people.update'] as $permission) {
            $admin->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        $father = Person::factory()->create(['gender' => 'male']);
        $mother = Person::factory()->create(['gender' => 'female']);
        $older = Person::factory()->create([
            'parent_id' => $father->id, 'mother_id' => $mother->id, 'birth_date' => '1990-01-01',
        ]);
        $younger = Person::factory()->create([
            'parent_id' => $father->id, 'mother_id' => $mother->id, 'birth_date' => '2000-01-01',
        ]);
        $unknown = Person::factory()->create([
            'parent_id' => $father->id, 'mother_id' => $mother->id, 'birth_date' => null, 'display_order' => 62,
        ]);
        $outsider = Person::factory()->createQuietly(['birth_date' => null]);
        $reorder = route('people.children.reorder', $father);
        $this->actingAs($admin)->get(route('people.show', $father))
            ->assertOk()->assertViewHas('children', fn ($children) => $children->modelKeys() === [$older->id, $younger->id, $unknown->id]);
        $this->get(route('sila'))->assertOk();

        $fatherChildren = "/api/person/{$father->id}/children";
        $fatherDetails = "/api/person/{$father->id}/children-details";
        $motherDetails = "/api/person/{$mother->id}/children-details";
        foreach ([$fatherChildren, $fatherDetails] as $endpoint) {
            $this->getJson($endpoint)->assertOk()
                ->assertJsonPath('children.*.id', [$older->id, $younger->id, $unknown->id]);
            $this->getJson($endpoint)->assertOk()->assertJsonPath('cached', true);
        }
        $this->getJson($motherDetails)->assertOk()
            ->assertJsonPath('children.*.id', [$unknown->id, $older->id, $younger->id]);
        $this->getJson($motherDetails)->assertOk()->assertJsonPath('cached', true);

        $this->postJson($reorder, ['order' => [$older->id, $younger->id, $unknown->id]])->assertOk();
        $this->postJson($reorder, ['order' => [$unknown->id, $older->id, $younger->id]])
            ->assertConflict()->assertJsonPath('code', 'CHILD_ORDER_CONFIRMATION_REQUIRED');
        $this->assertDatabaseCount('dashboard_child_orders', 0);

        $this->postJson($reorder, [
            'order' => array_map('strval', [$unknown->id, $older->id, $younger->id]), 'confirmed' => true,
        ])->assertOk();
        $this->get(route('people.show', $father))
            ->assertOk()->assertViewHas('children', fn ($children) => $children->modelKeys() === [$unknown->id, $older->id, $younger->id]);
        $this->postJson($reorder, ['order' => [$older->id, $unknown->id, $younger->id], 'confirmed' => true])->assertOk();

        $this->postJson($reorder, ['order' => [$younger->id, $unknown->id, $older->id]])
            ->assertConflict()->assertJsonPath('code', 'CHILD_ORDER_CONFIRMATION_REQUIRED');
        $this->postJson($reorder, ['order' => [$older->id, $outsider->id, $younger->id], 'confirmed' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('order');
        $this->get(route('people.show', $father))
            ->assertOk()->assertViewHas('children', fn ($children) => $children->modelKeys() === [$older->id, $unknown->id, $younger->id]);

        $this->assertEquals(62, $unknown->fresh()->display_order);
        $this->assertSame([$older->id, $younger->id, $unknown->id], app(MarriageOrderService::class)->orderedChildrenForFather($father->id)->modelKeys());
        $this->get(route('people.show', $mother))
            ->assertOk()->assertViewHas('children', fn ($children) => $children->modelKeys() === [$unknown->id, $older->id, $younger->id]);
        $motherOrder = [$unknown->id, $younger->id, $older->id];
        $this->postJson(route('people.children.reorder', $mother), [
            'order' => $motherOrder, 'confirmed' => true,
        ])->assertOk();
        $this->get(route('people.show', $mother))
            ->assertOk()->assertViewHas('children', fn ($children) => $children->modelKeys() === $motherOrder);
        $this->getJson($motherDetails)->assertOk()->assertJsonPath('cached', false)
            ->assertJsonPath('children.*.id', $motherOrder);
        $this->getJson("/api/person/{$mother->id}/children")->assertOk()->assertJsonCount(0, 'children');

        $baseline = [$older->id, $unknown->id, $younger->id];
        $override = [$younger->id, $unknown->id, $older->id];
        $this->postJson($reorder, [
            'order' => $override, 'previous_order' => $baseline, 'confirmed' => true,
        ])->assertOk();
        $storedOrder = DB::table('dashboard_child_orders')->where('parent_id', $father->id)->value('positions');
        $this->assertSame(['order' => $override], json_decode($storedOrder, true, flags: JSON_THROW_ON_ERROR));
        $this->get(route('people.show', $father))
            ->assertOk()->assertViewHas('children', fn ($children) => $children->modelKeys() === $override);
        foreach ([$fatherChildren, $fatherDetails] as $endpoint) {
            $this->getJson($endpoint)->assertOk()->assertJsonPath('cached', false)
                ->assertJsonPath('children.*.id', $override);
            $this->getJson($endpoint)->assertOk()->assertJsonPath('cached', true)
                ->assertJsonPath('children.*.id', $override);
        }
        $this->getJson($motherDetails)->assertOk()->assertJsonPath('cached', true)
            ->assertJsonPath('children.*.id', $motherOrder);
        $this->get(route('people.profile.show', $father))
            ->assertOk()->assertViewHas('children', fn ($children) => $children->modelKeys() === $override);

        $newPosition = [$younger->id, $older->id, $unknown->id];
        $this->postJson($reorder, ['order' => $newPosition, 'previous_order' => $override, 'confirmed' => true])->assertOk();
        $this->postJson($reorder, [
            'order' => $override, 'previous_order' => $baseline, 'confirmed' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('order');
        $this->postJson($reorder, ['order' => $baseline, 'previous_order' => $newPosition, 'confirmed' => true])->assertOk();

        DB::table('dashboard_child_orders')->where('parent_id', $father->id)
            ->update(['positions' => json_encode([$unknown->id => 1], JSON_THROW_ON_ERROR)]);
        app(TreeCacheService::class)->forgetChildren($father->id);
        $this->get(route('people.show', $father))
            ->assertOk()->assertViewHas('children', fn ($children) => $children->modelKeys() === $baseline);
        $this->getJson($fatherChildren)->assertOk()->assertJsonPath('children.*.id', $baseline);

        $unknown->updateQuietly(['birth_date' => '2005-01-01']);
        $this->get(route('people.show', $father))
            ->assertOk()->assertViewHas('children', fn ($children) => $children->modelKeys() === [$older->id, $younger->id, $unknown->id]);
    }

    public function test_public_and_dashboard_keep_their_previous_defaults_with_no_saved_order(): void
    {
        config(['site.password_protection_enabled' => false]);
        Queue::fake();
        $admin = User::factory()->createQuietly();
        $admin->givePermissionTo(Permission::findOrCreate('people.view', 'web'));
        $father = Person::factory()->create(['gender' => 'male']);
        $firstWife = Person::factory()->create(['gender' => 'female']);
        $secondWife = Person::factory()->create(['gender' => 'female']);
        Marriage::create(['husband_id' => $father->id, 'wife_id' => $firstWife->id, 'sort_order' => 1]);
        Marriage::create(['husband_id' => $father->id, 'wife_id' => $secondWife->id, 'sort_order' => 2]);
        $firstChild = Person::factory()->create([
            'parent_id' => $father->id, 'mother_id' => $secondWife->id, 'birth_date' => null, 'display_order' => 1,
        ]);
        $secondChild = Person::factory()->create([
            'parent_id' => $father->id, 'mother_id' => $firstWife->id, 'birth_date' => null, 'display_order' => 2,
        ]);
        $publicOrder = [$secondChild->id, $firstChild->id];
        $dashboardOrder = [$firstChild->id, $secondChild->id];

        $this->actingAs($admin)->get(route('people.show', $father))->assertOk()
            ->assertViewHas('children', fn ($children) => $children->modelKeys() === $dashboardOrder);
        $this->get(route('people.profile.show', $father))->assertOk()
            ->assertViewHas('children', fn ($children) => $children->modelKeys() === $publicOrder);
        foreach (['children', 'children-details'] as $path) {
            $this->getJson("/api/person/{$father->id}/{$path}")->assertOk()
                ->assertJsonPath('children.*.id', $publicOrder);
        }
        $this->assertDatabaseCount('dashboard_child_orders', 0);
    }
}
