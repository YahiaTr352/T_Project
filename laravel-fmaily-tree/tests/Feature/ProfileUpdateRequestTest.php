<?php

namespace Tests\Feature;

use App\Jobs\NotifyEditorsOfPendingProfileUpdatesJob;
use App\Models\Person;
use App\Models\PersonContactAccount;
use App\Models\ProfileUpdateRequest;
use App\Models\User;
use App\Services\AdminAlertDispatcherService;
use App\Services\ProfileUpdateRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProfileUpdateRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['site.password_protection_enabled' => false]);
        $embeddingsMock = $this->mock(\App\Services\AI\EmbeddingsService::class);
        $embeddingsMock->shouldReceive('upsertResult')->byDefault()->andReturn(['wrote' => false, 'skipped' => true]);
        $embeddingsMock->shouldReceive('deleteFor')->byDefault()->andReturn(true);
    }

    public function test_member_submitting_profile_info_update_creates_pending_request_and_does_not_mutate_db_immediately(): void
    {
        Queue::fake();

        $person = Person::factory()->create([
            'first_name' => 'محمد',
            'last_name' => 'الغامدي',
            'occupation' => 'طبيب',
        ]);

        $service = app(ProfileUpdateRequestService::class);

        $request = $service->submitProfileUpdate(
            person: $person,
            data: [
                'first_name' => 'محمد المعدل',
                'last_name' => 'الغامدي',
                'occupation' => 'استشاري جراحة',
            ],
            authMethod: 'whatsapp'
        );

        $this->assertInstanceOf(ProfileUpdateRequest::class, $request);
        $this->assertEquals('pending', $request->status);
        $this->assertEquals('profile_info', $request->change_type);
        $this->assertArrayNotHasKey('first_name', $request->proposed_data);
        $this->assertArrayNotHasKey('last_name', $request->proposed_data);
        $this->assertEquals('استشاري جراحة', $request->proposed_data['occupation']);

        // Person in database should still retain original data
        $person->refresh();
        $this->assertEquals('محمد', $person->first_name);
        $this->assertEquals('طبيب', $person->occupation);

        // NotifyEditors Job should be dispatched
        Queue::assertPushed(NotifyEditorsOfPendingProfileUpdatesJob::class, function ($job) use ($request) {
            return $job->requestId === $request->id;
        });
    }

    public function test_member_submitting_contact_addition_creates_pending_request(): void
    {
        Queue::fake();

        $person = Person::factory()->create();
        $service = app(ProfileUpdateRequestService::class);

        $request = $service->submitContactChange(
            person: $person,
            action: 'add',
            data: [
                'type' => 'twitter',
                'value' => 'https://twitter.com/family_member',
                'label' => 'حسابي في تويتر',
            ],
            authMethod: 'face'
        );

        $this->assertEquals('pending', $request->status);
        $this->assertEquals('contact_add', $request->change_type);
        $this->assertEquals('face', $request->requested_by_auth);

        // Database should have 0 contacts before approval
        $this->assertDatabaseMissing('person_contact_accounts', [
            'person_id' => $person->id,
            'type' => 'twitter',
        ]);
    }

    public function test_admin_approval_applies_changes_to_real_records(): void
    {
        $person = Person::factory()->create([
            'first_name' => 'خالد',
            'occupation' => 'مهندس',
        ]);

        $reviewer = User::factory()->create();

        $service = app(ProfileUpdateRequestService::class);
        $request = $service->submitProfileUpdate(
            person: $person,
            data: [
                'occupation' => 'كبير مهندسين',
                'biography' => 'مهندس استشاري معتمد',
            ],
            authMethod: 'whatsapp'
        );

        $service->approve($request, $reviewer);

        $request->refresh();
        $this->assertEquals('approved', $request->status);
        $this->assertEquals($reviewer->id, $request->reviewed_by);
        $this->assertNotNull($request->reviewed_at);

        // Person in database should now be updated
        $person->refresh();
        $this->assertEquals('خالد', $person->first_name);
        $this->assertEquals('كبير مهندسين', $person->occupation);
        $this->assertEquals('مهندس استشاري معتمد', $person->biography);
    }

    public function test_admin_rejection_marks_request_as_rejected_with_reason(): void
    {
        $person = Person::factory()->create([
            'first_name' => 'عبدالله',
        ]);

        $reviewer = User::factory()->create();

        $service = app(ProfileUpdateRequestService::class);
        $request = $service->submitContactChange(
            person: $person,
            action: 'add',
            data: [
                'type' => 'whatsapp',
                'value' => '0500000000',
            ]
        );

        $service->reject($request, $reviewer, 'الرقم غير مسجل باسم العضو.');

        $request->refresh();
        $this->assertEquals('rejected', $request->status);
        $this->assertEquals('الرقم غير مسجل باسم العضو.', $request->rejection_reason);
        $this->assertEquals($reviewer->id, $request->reviewed_by);

        // Contact should NOT be created
        $this->assertDatabaseMissing('person_contact_accounts', [
            'person_id' => $person->id,
            'value' => '0500000000',
        ]);
    }

    public function test_admin_dashboard_shows_pending_update_requests(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'people.view', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->givePermissionTo($permission);

        $person = Person::factory()->create(['first_name' => 'سارة']);
        ProfileUpdateRequest::create([
            'person_id' => $person->id,
            'requested_by_auth' => 'whatsapp',
            'change_type' => 'profile_info',
            'old_data' => ['first_name' => 'سارة'],
            'proposed_data' => ['first_name' => 'سارة المحدثة'],
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('dashboard.profile-updates.index'));

        $response->assertOk();
        $response->assertSee('طلبات تعديل بيانات الأعضاء');
        $response->assertSee('سارة');
        $response->assertSee('تعديل البيانات الشخصية');
    }

    public function test_member_submitting_photo_update_stages_photo_and_approval_moves_it(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $person = Person::factory()->create(['first_name' => 'ياسر']);
        $file = \Illuminate\Http\UploadedFile::fake()->image('avatar.jpg');

        $service = app(ProfileUpdateRequestService::class);
        $request = $service->submitProfileUpdate(
            person: $person,
            data: ['first_name' => 'ياسر'],
            photo: $file,
            authMethod: 'face'
        );

        $this->assertTrue($request->has_photo_update);
        $this->assertNotEmpty($request->attached_files['photo_url']);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($request->attached_files['photo_url']);

        $reviewer = User::factory()->create();
        $service->approve($request, $reviewer);

        $person->refresh();
        $this->assertNotNull($person->photo_url);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($person->photo_url);
    }

    public function test_member_submitting_base64_photo_creates_pending_request_with_photo(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $person = Person::factory()->create(['first_name' => 'ريم']);
        // Valid 1x1 png base64
        $base64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        $service = app(ProfileUpdateRequestService::class);
        $request = $service->submitProfileUpdate(
            person: $person,
            data: ['first_name' => 'ريم', 'photo_base64' => $base64],
            photo: null,
            authMethod: 'whatsapp'
        );

        $this->assertTrue($request->has_photo_update);
        $this->assertNotEmpty($request->attached_files['photo_url']);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($request->attached_files['photo_url']);
    }

    public function test_admin_can_reopen_rejected_request(): void
    {
        $person = Person::factory()->create(['first_name' => 'فيصل']);
        $reviewer = User::factory()->create();
        $service = app(ProfileUpdateRequestService::class);

        $request = $service->submitProfileUpdate(
            person: $person,
            data: ['first_name' => 'فيصل الجديد'],
            authMethod: 'whatsapp'
        );

        $service->reject($request, $reviewer, 'تم الرفض بالخطأ');
        $this->assertEquals('rejected', $request->fresh()->status);
        $this->assertEquals('تم الرفض بالخطأ', $request->fresh()->rejection_reason);

        // Reopen
        $service->reopen($request->fresh(), $reviewer);

        $request->refresh();
        $this->assertEquals('pending', $request->status);
        $this->assertNull($request->rejection_reason);
        $this->assertNull($request->reviewed_by);
    }

    public function test_admin_can_reopen_and_approve_previously_rejected_request(): void
    {
        $person = Person::factory()->create(['first_name' => 'طلال', 'occupation' => 'محاسب', 'biography' => 'نبذة أولية']);
        $reviewer = User::factory()->create();
        $service = app(ProfileUpdateRequestService::class);

        $request = $service->submitProfileUpdate(
            person: $person,
            data: ['first_name' => 'طلال المحدث', 'occupation' => 'مدير مالي', 'biography' => 'مدير الحسابات المعتمد'],
            authMethod: 'whatsapp'
        );

        // First reject it
        $service->reject($request, $reviewer, 'سبب رفض مؤقت');
        $this->assertEquals('rejected', $request->fresh()->status);

        // Now reopen and approve
        $service->reopenAndApprove($request->fresh(), $reviewer);

        $request->refresh();
        $this->assertEquals('approved', $request->status);
        $this->assertNull($request->rejection_reason);
        $this->assertEquals($reviewer->id, $request->reviewed_by);

        // Person record must be updated in DB, with immutable name preserved
        $person->refresh();
        $this->assertEquals('طلال', $person->first_name);
        $this->assertEquals('مدير مالي', $person->occupation);
        $this->assertEquals('مدير الحسابات المعتمد', $person->biography);
    }

    public function test_controller_reopen_and_reopen_and_approve_endpoints(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'people.update', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->givePermissionTo($permission);

        $person = Person::factory()->create(['first_name' => 'فهد']);
        $updateRequest = ProfileUpdateRequest::create([
            'person_id' => $person->id,
            'requested_by_auth' => 'whatsapp',
            'change_type' => 'profile_info',
            'old_data' => ['first_name' => 'فهد'],
            'proposed_data' => ['first_name' => 'فهد المحدث'],
            'status' => 'rejected',
            'rejection_reason' => 'رفض تجريبي',
        ]);

        // Test Reopen endpoint
        $response = $this->actingAs($admin)
            ->post(route('dashboard.profile-updates.reopen', $updateRequest->id));

        $response->assertRedirect(route('dashboard.profile-updates.show', $updateRequest->id));
        $this->assertEquals('pending', $updateRequest->fresh()->status);

        // Re-reject for reopen-and-approve test
        $updateRequest->update(['status' => 'rejected', 'rejection_reason' => 'رفض ثان']);

        // Test Reopen and Approve endpoint
        $response2 = $this->actingAs($admin)
            ->post(route('dashboard.profile-updates.reopen-and-approve', $updateRequest->id));

        $response2->assertRedirect(route('dashboard.profile-updates.index'));
        $this->assertEquals('approved', $updateRequest->fresh()->status);
        $this->assertEquals('فهد المحدث', $person->fresh()->first_name);
    }

    public function test_admin_can_approve_selected_fields_only_and_exclude_others(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'people.update', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->givePermissionTo($permission);

        $person = Person::factory()->create([
            'first_name' => 'سالم',
            'occupation' => 'معلم',
            'birth_place' => 'جدة',
            'biography' => 'نبذة قديمة',
        ]);

        $updateRequest = ProfileUpdateRequest::create([
            'person_id' => $person->id,
            'requested_by_auth' => 'whatsapp',
            'change_type' => 'profile_info',
            'old_data' => [
                'occupation' => 'معلم',
                'birth_place' => 'جدة',
                'biography' => 'نبذة قديمة',
            ],
            'proposed_data' => [
                'occupation' => 'مدير مدرسة',
                'birth_place' => 'الرياض',
                'biography' => 'نبذة جديدة ومحدثة',
            ],
            'status' => 'pending',
        ]);

        // Admin approves occupation and biography, but excludes birth_place
        $response = $this->actingAs($admin)
            ->post(route('dashboard.profile-updates.approve', $updateRequest->id), [
                'has_granular_selection' => '1',
                'approved_fields' => ['occupation', 'biography'],
                'exclusion_reason' => 'تم استبعاد مكان الميلاد لعدم توفر إثبات رسمي',
            ]);

        $response->assertRedirect(route('dashboard.profile-updates.index'));
        $updateRequest->refresh();
        $person->refresh();

        $this->assertEquals('approved', $updateRequest->status);
        $this->assertEquals('تم استبعاد مكان الميلاد لعدم توفر إثبات رسمي', $updateRequest->rejection_reason);

        // Occupation and biography should be updated
        $this->assertEquals('مدير مدرسة', $person->occupation);
        $this->assertEquals('نبذة جديدة ومحدثة', $person->biography);

        // Birth place must remain unchanged!
        $this->assertEquals('جدة', $person->birth_place);
    }

    /* ---------------------------------------------------------------------
     * تحرير الصورة الشخصية من صفحة المراجعة (قص / استبدال / استعادة)
     * ------------------------------------------------------------------- */

    protected function createProfilePhotoRequest(Person $person): ProfileUpdateRequest
    {
        return app(ProfileUpdateRequestService::class)->submitProfileUpdate(
            person: $person,
            data: ['first_name' => $person->first_name],
            photo: UploadedFile::fake()->image('member-photo.jpg', 900, 900),
            authMethod: 'whatsapp'
        );
    }

    protected function reviewerWithPhotoEditPermission(): User
    {
        $permission = Permission::firstOrCreate(['name' => 'people.update', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->givePermissionTo($permission);

        return $admin;
    }

    public function test_admin_can_save_edited_photo_on_pending_profile_request(): void
    {
        Storage::fake('public');
        $admin = $this->reviewerWithPhotoEditPermission();
        $person = Person::factory()->create(['first_name' => 'نايف']);
        $request = $this->createProfilePhotoRequest($person);

        $originalPath = $request->attached_files['photo_url'];
        Storage::disk('public')->assertExists($originalPath);

        $response = $this->actingAs($admin)->post(
            route('dashboard.profile-updates.photo.update', $request->id),
            ['photo' => UploadedFile::fake()->image('edited.jpg', 400, 400)],
            ['Accept' => 'application/json']
        );

        $response->assertOk();
        $response->assertJson(['ok' => true]);

        $request->refresh();
        $editedPath = $request->attached_files['photo_url'];

        $this->assertNotSame($originalPath, $editedPath);
        $this->assertSame($originalPath, $request->attached_files['photo_original_url']);
        $this->assertTrue($request->has_edited_photo);
        $this->assertSame((int) $admin->id, (int) $request->attached_files['photo_edited_by']);
        $this->assertNotNull($request->photo_original_url);
        Storage::disk('public')->assertExists($editedPath);
        Storage::disk('public')->assertExists($originalPath);
    }

    public function test_admin_can_reset_edited_photo_to_member_original(): void
    {
        Storage::fake('public');
        $admin = $this->reviewerWithPhotoEditPermission();
        $person = Person::factory()->create(['first_name' => 'هاني']);
        $request = $this->createProfilePhotoRequest($person);
        $originalPath = $request->attached_files['photo_url'];

        $this->actingAs($admin)->post(
            route('dashboard.profile-updates.photo.update', $request->id),
            ['photo' => UploadedFile::fake()->image('edited.jpg', 400, 400)],
            ['Accept' => 'application/json']
        )->assertOk();

        $editedPath = $request->fresh()->attached_files['photo_url'];
        $this->assertNotSame($originalPath, $editedPath);

        $response = $this->actingAs($admin)->post(
            route('dashboard.profile-updates.photo.reset', $request->id),
            [],
            ['Accept' => 'application/json']
        );

        $response->assertOk();
        $response->assertJson(['ok' => true]);

        $request->refresh();
        $this->assertSame($originalPath, $request->attached_files['photo_url']);
        $this->assertArrayNotHasKey('photo_original_url', $request->attached_files);
        $this->assertArrayNotHasKey('photo_edited_at', $request->attached_files);
        $this->assertFalse($request->has_edited_photo);
        Storage::disk('public')->assertMissing($editedPath);
        Storage::disk('public')->assertExists($originalPath);
    }

    public function test_photo_edit_is_rejected_for_processed_requests(): void
    {
        Storage::fake('public');
        $admin = $this->reviewerWithPhotoEditPermission();
        $person = Person::factory()->create(['first_name' => 'بدر']);

        $stagedPath = 'temp-profile-requests/member-approved.jpg';
        Storage::disk('public')->put($stagedPath, 'binary-content');

        $updateRequest = ProfileUpdateRequest::create([
            'person_id' => $person->id,
            'requested_by_auth' => 'whatsapp',
            'change_type' => 'profile_info',
            'old_data' => ['first_name' => 'بدر'],
            'proposed_data' => ['first_name' => 'بدر'],
            'attached_files' => ['photo_url' => $stagedPath],
            'status' => 'approved',
        ]);

        $response = $this->actingAs($admin)->post(
            route('dashboard.profile-updates.photo.update', $updateRequest->id),
            ['photo' => UploadedFile::fake()->image('edited.jpg', 400, 400)],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertJson(['ok' => false]);
        $this->assertSame($stagedPath, $updateRequest->fresh()->attached_files['photo_url']);
    }

    public function test_photo_edit_requires_an_attached_photo(): void
    {
        Storage::fake('public');
        $admin = $this->reviewerWithPhotoEditPermission();
        $person = Person::factory()->create(['first_name' => 'مشعل']);

        $updateRequest = ProfileUpdateRequest::create([
            'person_id' => $person->id,
            'requested_by_auth' => 'whatsapp',
            'change_type' => 'profile_info',
            'old_data' => ['first_name' => 'مشعل'],
            'proposed_data' => ['first_name' => 'مشعل'],
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->post(
            route('dashboard.profile-updates.photo.update', $updateRequest->id),
            ['photo' => UploadedFile::fake()->image('edited.jpg', 400, 400)],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertJson(['ok' => false]);
    }

    public function test_photo_edit_endpoints_require_people_update_permission(): void
    {
        Storage::fake('public');
        $viewPermission = Permission::firstOrCreate(['name' => 'people.view', 'guard_name' => 'web']);
        $viewer = User::factory()->create();
        $viewer->givePermissionTo($viewPermission);

        $person = Person::factory()->create(['first_name' => 'سعود']);
        $request = $this->createProfilePhotoRequest($person);

        $this->actingAs($viewer)->post(
            route('dashboard.profile-updates.photo.update', $request->id),
            ['photo' => UploadedFile::fake()->image('edited.jpg', 400, 400)],
            ['Accept' => 'application/json']
        )->assertStatus(403);

        $this->actingAs($viewer)->post(
            route('dashboard.profile-updates.photo.reset', $request->id),
            [],
            ['Accept' => 'application/json']
        )->assertStatus(403);
    }

    public function test_approval_applies_edited_photo_and_cleans_temp_original(): void
    {
        Storage::fake('public');
        $admin = $this->reviewerWithPhotoEditPermission();
        $person = Person::factory()->create(['first_name' => 'طلال']);
        $request = $this->createProfilePhotoRequest($person);
        $originalPath = $request->attached_files['photo_url'];

        $this->actingAs($admin)->post(
            route('dashboard.profile-updates.photo.update', $request->id),
            ['photo' => UploadedFile::fake()->image('edited.jpg', 400, 400)],
            ['Accept' => 'application/json']
        )->assertOk();

        $request->refresh();
        $editedPath = $request->attached_files['photo_url'];
        $editedContents = Storage::disk('public')->get($editedPath);

        app(ProfileUpdateRequestService::class)->approve($request, $admin);

        $person->refresh();
        $this->assertNotNull($person->photo_url);
        Storage::disk('public')->assertExists($person->photo_url);
        $this->assertSame($editedContents, Storage::disk('public')->get($person->photo_url));

        // The edited temp file was moved, and the member's original temp file was cleaned up.
        Storage::disk('public')->assertMissing($editedPath);
        Storage::disk('public')->assertMissing($originalPath);
    }

    public function test_photo_edit_rejects_non_image_uploads(): void
    {
        Storage::fake('public');
        $admin = $this->reviewerWithPhotoEditPermission();
        $person = Person::factory()->create(['first_name' => 'راكان']);
        $request = $this->createProfilePhotoRequest($person);

        $response = $this->actingAs($admin)->post(
            route('dashboard.profile-updates.photo.update', $request->id),
            ['photo' => UploadedFile::fake()->create('document.pdf', 20, 'application/pdf')],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(422);
        $response->assertJsonPath('errors.photo.0', 'الملف المرسل ليس صورة صالحة.');
    }

    public function test_admin_can_save_edited_photo_as_base64_payload(): void
    {
        Storage::fake('public');
        $admin = $this->reviewerWithPhotoEditPermission();
        $person = Person::factory()->create(['first_name' => 'تركي']);
        $request = $this->createProfilePhotoRequest($person);
        $originalPath = $request->attached_files['photo_url'];

        // Valid 1x1 png base64 (same payload style the mobile clients send).
        $base64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        $response = $this->actingAs($admin)->post(
            route('dashboard.profile-updates.photo.update', $request->id),
            ['image_base64' => $base64],
            ['Accept' => 'application/json']
        );

        $response->assertOk();

        $request->refresh();
        $this->assertTrue($request->has_edited_photo);
        $this->assertSame($originalPath, $request->attached_files['photo_original_url']);
        $this->assertStringEndsWith('.png', $request->attached_files['photo_url']);
        Storage::disk('public')->assertExists($request->attached_files['photo_url']);
    }

    public function test_show_page_exposes_photo_editor_for_editors_only(): void
    {
        Storage::fake('public');

        $viewPermission = Permission::firstOrCreate(['name' => 'people.view', 'guard_name' => 'web']);
        $updatePermission = Permission::firstOrCreate(['name' => 'people.update', 'guard_name' => 'web']);

        $editor = User::factory()->create();
        $editor->givePermissionTo([$viewPermission, $updatePermission]);

        $viewer = User::factory()->create();
        $viewer->givePermissionTo($viewPermission);

        $person = Person::factory()->create(['first_name' => 'وليد']);
        $request = $this->createProfilePhotoRequest($person);

        $this->actingAs($editor)
            ->get(route('dashboard.profile-updates.show', $request->id))
            ->assertOk()
            ->assertSee('قص وتعديل الصورة')
            ->assertSee('photoEditorModal');

        $this->actingAs($viewer)
            ->get(route('dashboard.profile-updates.show', $request->id))
            ->assertOk()
            ->assertDontSee('قص وتعديل الصورة');
    }

    public function test_show_page_renders_edited_photo_state_with_restore_controls(): void
    {
        Storage::fake('public');
        $admin = $this->reviewerWithPhotoEditPermission();
        $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'people.view', 'guard_name' => 'web']));
        $person = Person::factory()->create(['first_name' => 'مازن']);
        $request = $this->createProfilePhotoRequest($person);

        $this->actingAs($admin)->post(
            route('dashboard.profile-updates.photo.update', $request->id),
            ['photo' => UploadedFile::fake()->image('edited.jpg', 400, 400)],
            ['Accept' => 'application/json']
        )->assertOk();

        $response = $this->actingAs($admin)->get(route('dashboard.profile-updates.show', $request->id));

        $response->assertOk();
        $response->assertSee('معدّلة بواسطة المراجع');
        $response->assertSee('صورة العضو الأصلية المرسلة');
        $response->assertSee('استعادة صورة العضو');
        $response->assertSee('قص وتعديل الصورة');
        $response->assertSee('hasEditedPhoto&quot;:true', false);
        $response->assertSee('/photo/reset', false);
    }

    public function test_show_page_renders_without_photo_and_for_processed_requests(): void
    {
        Storage::fake('public');

        $editor = $this->reviewerWithPhotoEditPermission();
        $viewPermission = Permission::firstOrCreate(['name' => 'people.view', 'guard_name' => 'web']);
        $editor->givePermissionTo($viewPermission);

        $person = Person::factory()->create(['first_name' => 'أحمد']);

        $withoutPhoto = ProfileUpdateRequest::create([
            'person_id' => $person->id,
            'requested_by_auth' => 'whatsapp',
            'change_type' => 'profile_info',
            'old_data' => ['first_name' => 'أحمد'],
            'proposed_data' => ['first_name' => 'أحمد المحدث'],
            'status' => 'pending',
        ]);

        $this->actingAs($editor)
            ->get(route('dashboard.profile-updates.show', $withoutPhoto->id))
            ->assertOk()
            ->assertSee('لم يطلب العضو تعديل صورته الشخصية')
            ->assertDontSee('قص وتعديل الصورة');

        $approved = $this->createProfilePhotoRequest($person);
        $approved->update(['status' => 'approved', 'reviewed_by' => $editor->id, 'reviewed_at' => now()]);

        $this->actingAs($editor)
            ->get(route('dashboard.profile-updates.show', $approved->id))
            ->assertOk()
            ->assertDontSee('قص وتعديل الصورة')
            ->assertSee('تم اعتماد هذا الطلب');
    }
}
