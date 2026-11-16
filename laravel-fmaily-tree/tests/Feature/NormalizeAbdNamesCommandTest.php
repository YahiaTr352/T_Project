<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\ProfileUpdateRequest;
use App\Models\User;
use App\Services\AI\EmbeddingsService;
use App\Services\ProfileUpdateRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NormalizeAbdNamesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->mock(EmbeddingsService::class, function ($mock) {
            $mock->shouldReceive('createForPerson')->andReturn(null);
            $mock->shouldReceive('deleteForPerson')->andReturn(null);
        });
    }

    public function test_dry_run_does_not_modify_database_or_create_requests(): void
    {
        $person = Person::factory()->create([
            'first_name' => 'عبد الرحمن',
            'last_name'  => 'السريع',
        ]);

        $exitCode = Artisan::call('people:normalize-abd-names', ['--dry-run' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseHas('persons', [
            'id'         => $person->id,
            'first_name' => 'عبد الرحمن',
        ]);
        $this->assertDatabaseCount('profile_update_requests', 0);
    }

    public function test_default_mode_creates_pending_profile_update_request_without_direct_db_alteration(): void
    {
        $person = Person::factory()->create([
            'first_name' => 'عبد الله',
            'last_name'  => 'العقيل',
        ]);

        $exitCode = Artisan::call('people:normalize-abd-names');

        $this->assertSame(0, $exitCode);

        // Person table is NOT altered directly yet
        $this->assertDatabaseHas('persons', [
            'id'         => $person->id,
            'first_name' => 'عبد الله',
        ]);

        // A pending ProfileUpdateRequest was created for admin review
        $this->assertDatabaseHas('profile_update_requests', [
            'person_id'         => $person->id,
            'status'            => 'pending',
            'requested_by_auth' => 'system',
            'change_type'       => 'profile_info',
        ]);

        $req = ProfileUpdateRequest::where('person_id', $person->id)->firstOrFail();
        $this->assertSame('عبدالله', $req->proposed_data['first_name']);
        $this->assertSame(['first_name'], $req->changedProfileFields());
    }

    public function test_admin_approving_request_updates_person_name(): void
    {
        $person = Person::factory()->create([
            'first_name' => 'عبد الحميد',
            'last_name'  => 'الغانم',
        ]);

        Artisan::call('people:normalize-abd-names');

        $req = ProfileUpdateRequest::where('person_id', $person->id)->firstOrFail();
        $admin = User::factory()->create();

        /** @var ProfileUpdateRequestService $service */
        $service = app(ProfileUpdateRequestService::class);
        $service->approve($req, $admin);

        $person->refresh();
        $req->refresh();

        $this->assertSame('عبدالحميد', $person->first_name);
        $this->assertSame('approved', $req->status);
        $this->assertSame($admin->id, $req->reviewed_by);
    }

    public function test_apply_direct_updates_database_immediately(): void
    {
        $person = Person::factory()->create([
            'first_name' => 'عبد العزيز',
            'last_name'  => 'عبد الرحمن',
        ]);

        $exitCode = Artisan::call('people:normalize-abd-names', [
            '--apply-direct'   => true,
            '--no-interaction' => true,
        ]);

        $this->assertSame(0, $exitCode);

        $person->refresh();
        $this->assertSame('عبدالعزيز', $person->first_name);
        $this->assertSame('عبدالرحمن', $person->last_name);
        $this->assertDatabaseCount('profile_update_requests', 0);
    }

    public function test_regular_lineage_is_not_altered(): void
    {
        $person = Person::factory()->create([
            'first_name' => 'يوسف حمد يوسف مروان',
            'last_name'  => 'السريع',
        ]);

        $exitCode = Artisan::call('people:normalize-abd-names');

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseCount('profile_update_requests', 0);
        $this->assertDatabaseHas('persons', [
            'id'         => $person->id,
            'first_name' => 'يوسف حمد يوسف مروان',
        ]);
    }
}
