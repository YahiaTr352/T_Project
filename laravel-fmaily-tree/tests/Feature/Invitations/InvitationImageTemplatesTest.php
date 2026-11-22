<?php

namespace Tests\Feature\Invitations;

use App\Jobs\SendWhatsAppInvitationJob;
use App\Models\CreativeTemplate;
use App\Models\Notification;
use App\Models\Person;
use App\Models\User;
use App\Services\Invitations\InvitationCardRenderer;
use App\Services\SiteMobileAccessToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InvitationImageTemplatesTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jU1sAAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        config(['invitations.delivery_enabled' => false, 'site.password_protection_enabled' => false]);
        Queue::fake();
        Http::preventStrayRequests();
        Storage::fake('public');
    }

    public function test_admin_can_upload_and_reclassify_images_and_api_filters_by_invitation_type(): void
    {
        $admin = User::factory()->createQuietly();
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));
        $this->actingAs($admin)->postJson('/dashboard/creative-templates', [
            'image' => $this->image(),
            'name' => 'تهنئة تخرج',
            'invitation_type' => 'greeting',
        ])->assertCreated()->assertJsonPath('data.template.invitation_type', 'greeting');
        $creative = CreativeTemplate::sole();
        $this->patchJson("/dashboard/creative-templates/{$creative->id}", [
            'invitation_type' => 'condolences',
        ])->assertOk();
        $this->patchJson("/dashboard/creative-templates/{$creative->id}", [
            'invitation_type' => 'unknown',
        ])->assertUnprocessable();

        $legacy = CreativeTemplate::create([
            'name' => 'تصميم عام', 'image_path' => 'legacy.png', 'status' => 'approved',
        ]);
        $sender = $this->person();
        $this->asIdentity($sender);
        $this->getJson('/api/v1/invitations/creatives?invitation_type=condolences')
            ->assertOk()->assertJsonCount(1, 'data.templates')
            ->assertJsonPath('data.templates.0.id', $creative->id);
        $this->getJson('/api/v1/invitations/creatives?invitation_type=greeting')
            ->assertOk()->assertJsonCount(0, 'data.templates');
        $this->getJson('/api/v1/invitations/creatives')
            ->assertOk()->assertJsonCount(2, 'data.templates');
        $this->assertNull($legacy->invitation_type);
        $this->get('/dashboard/creative-templates?status=approved')->assertOk()->assertSee('حفظ التصنيف');
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    public function test_local_member_upload_preserves_category_without_sending_review_notices(): void
    {
        $this->asIdentity($this->person());
        $this->postJson('/api/v1/invitations/creatives', ['image' => $this->image()])
            ->assertUnprocessable()->assertJsonValidationErrors('invitation_type');
        $this->postJson('/api/v1/invitations/creatives', [
            'image' => $this->image(),
            'invitation_type' => 'condolences',
        ])->assertCreated()->assertJsonPath('data.template.status', 'pending');
        $this->assertSame('condolences', CreativeTemplate::sole()->invitation_type);
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    public function test_approved_wedding_submission_becomes_a_personalized_attachment(): void
    {
        $sender = $this->person();
        $recipient = $this->person();
        $this->asIdentity($sender);
        $this->post('/invitations/creatives', [
            'image' => UploadedFile::fake()->image('wedding.png', 500, 900),
            'name' => 'بطاقة زفاف', 'invitation_type' => 'wedding',
        ])->assertRedirect(route('invitations.send'))->assertSessionHas('creative_success');

        $design = CreativeTemplate::sole();
        $this->getJson('/api/v1/invitations/creatives?invitation_type=wedding')->assertJsonCount(0, 'data.templates');
        $admin = User::factory()->createQuietly();
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));
        $this->actingAs($admin)->postJson("/dashboard/creative-templates/{$design->id}/approve")
            ->assertOk()->assertJsonPath('data.template.invitation_type', 'wedding');
        $this->getJson('/api/v1/invitations/creatives?invitation_type=wedding')->assertJsonPath('data.templates.0.id', $design->id);
        $this->getJson('/api/v1/invitations/creatives?invitation_type=greeting')->assertJsonCount(0, 'data.templates');

        $payload = [
            'template_key' => 'wedding', 'creative_id' => $design->id,
            'title' => 'دعوة زفاف', 'body' => 'زفاف {groom} في {location}',
            'template_vars' => ['hosts' => 'عائلة إبراهيم', 'groom' => 'خالد', 'date' => '2026-12-01', 'time' => '8:00', 'location' => 'قاعة الفرح'],
        ];
        $this->postJson('/api/v1/invitations/preview', array_replace($payload, ['template_key' => 'greeting']))
            ->assertUnprocessable()->assertJsonPath('code', 'CREATIVE_TYPE_MISMATCH');
        $preview = $this->postJson('/api/v1/invitations/preview', $payload)->assertOk()->json('data.card_image_url');
        $this->postJson('/invitations/preview-message', $payload)->assertOk()->assertJsonPath('card_image_url', $preview);
        $this->postJson('/api/v1/invitations/send', $payload + [
            'channels' => ['push', 'whatsapp'], 'recipient_type' => 'persons', 'person_ids' => [$recipient->id],
        ])->assertCreated();

        $notification = Notification::sole();
        $this->assertSame('image', $notification->media_type);
        $this->assertTrue($notification->targeting_filters['creative_card_rendered']);
        $this->assertSame(Storage::disk('public')->url($notification->media_path), $preview);
        $firstImage = Storage::disk('public')->get($notification->media_path);
        $this->assertNotSame(Storage::disk('public')->get($design->image_path), $firstImage);
        $this->assertNull(app(InvitationCardRenderer::class)->renderForNotification($notification, 'زفاف خالد', $recipient->id, ''));

        $payload['template_vars']['groom'] = 'أحمد';
        $another = $this->postJson('/api/v1/invitations/preview', $payload)->assertOk()->json('data.card_image_url');
        $this->assertNotSame($preview, $another);
        $this->assertNotSame($firstImage, Storage::disk('public')->get('notifications/cards/'.basename($another)));
        $notification->update(['status' => 'sent', 'sent_at' => now()]);
        $this->flushSession();
        $this->asIdentity($recipient);
        $this->getJson('/api/notifications')->assertOk()
            ->assertJsonPath('data.data.0.body', 'زفاف خالد في قاعة الفرح')
            ->assertJsonPath('data.data.0.media_url', asset('storage/'.$notification->media_path));
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    public function test_image_only_invitations_work_from_web_and_api_and_display_to_recipient(): void
    {
        $sender = $this->person();
        $recipient = $this->person();
        $sourceImage = UploadedFile::fake()->image('greeting.png', 500, 650)->getContent();
        Storage::disk('public')->put('greeting.png', $sourceImage);
        $creative = CreativeTemplate::create([
            'name' => 'صورة تهنئة',
            'image_path' => 'greeting.png',
            'invitation_type' => 'greeting',
            'status' => 'approved',
        ]);
        $payload = [
            'channels' => ['push'], 'recipient_type' => 'persons', 'person_ids' => [$recipient->id],
            'template_key' => 'greeting', 'creative_id' => $creative->id, 'body' => '',
        ];
        $this->asIdentity($sender);
        $this->get('/invitations/send')->assertOk()->assertSee('الصورة والتصميم')
            ->assertSee('id="creativeOpen"', false)->assertSee('id="creativeDialog"', false)
            ->assertDontSee('قوالب الصور لهذا النوع')
            ->assertSee('data-type="greeting"', false);
        $this->post('/invitations/send', array_replace($payload, [
            'body' => 'دعوة بالخط المختار', 'font_family' => 'amiri', 'font_weight' => 'bold',
        ]))->assertRedirect(route('invitations.logs'));
        $webInvitation = Notification::sole();
        $this->assertSame('amiri', $webInvitation->targeting_filters['font_family']);
        $this->assertSame('bold', $webInvitation->targeting_filters['font_weight']);
        $this->postJson('/api/v1/invitations/send', $payload + ['font_family' => 'tajawal', 'font_weight' => 'regular'])
            ->assertCreated()->assertJsonPath('data.notification.status', 'delivery_disabled');
        $invitation = Notification::latest('id')->firstOrFail();
        $this->assertSame('', $invitation->body);
        $this->assertSame('image', $invitation->media_type);
        Storage::disk('public')->assertExists($invitation->media_path);
        $this->assertSame($sourceImage, Storage::disk('public')->get($invitation->media_path));
        $this->assertNull(app(InvitationCardRenderer::class)->renderForNotification($invitation, '', $recipient->id, ''));
        $this->postJson('/api/v1/invitations/preview', [
            'creative_id' => $creative->id, 'template_key' => 'greeting', 'body' => '', 'title' => 'تهنئة',
        ])->assertOk()->assertJsonPath('data.preview', '')->assertJsonPath('data.card_image_url', null);
        $this->get('/invitations/logs')->assertOk()
            ->assertSee('alt="صورة الدعوة"', false)
            ->assertSee('data-invitation-font="amiri" data-invitation-weight="bold"', false);
        Queue::assertNothingPushed();

        // Simulate successful delivery only in the isolated database.
        $invitation->update(['status' => 'sent', 'sent_at' => now()]);
        $this->flushSession();
        $this->asIdentity($recipient);
        $this->get('/invitations/logs?direction=incoming')->assertOk()
            ->assertSee($invitation->media_url, false)
            ->assertSee('data-invitation-font="tajawal" data-invitation-weight="regular"', false);
        $this->getJson('/api/v1/invitations/logs?direction=incoming')->assertOk()
            ->assertJsonPath('data.notifications.data.0.media_url', $invitation->media_url);
        Http::assertNothingSent();
    }

    public function test_attached_images_text_and_empty_content_are_handled_by_both_entry_points(): void
    {
        $sender = $this->person();
        $recipient = $this->person();
        $this->asIdentity($sender);
        $base = ['channels' => ['push'], 'recipient_type' => 'persons', 'person_ids' => [$recipient->id]];
        $this->postJson('/api/v1/invitations/send', $base + ['body' => ''])
            ->assertUnprocessable()->assertJsonPath('code', 'CONTENT_REQUIRED');
        $this->from('/invitations/send')->post('/invitations/send', $base + ['body' => ''])
            ->assertRedirect('/invitations/send')->assertSessionHas('error');
        $this->assertDatabaseCount('notifications', 0);
        $this->post('/invitations/send', $base + ['body' => '', 'media' => $this->image()])
            ->assertRedirect(route('invitations.logs'));
        $this->postJson('/api/v1/invitations/send', $base + ['body' => '', 'media' => $this->image()])
            ->assertCreated();
        $this->postJson('/api/v1/invitations/send', $base + ['body' => 'رسالة مع الصورة', 'media' => $this->image()])
            ->assertCreated();
        $this->postJson('/api/v1/invitations/send', $base + ['body' => 'رسالة نصية'])
            ->assertCreated();
        $this->assertDatabaseCount('notifications', 4);
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    public function test_whatsapp_image_job_has_an_empty_caption_for_image_only_invitation(): void
    {
        config(['invitations.delivery_enabled' => true]);
        $sender = $this->person();
        $this->asIdentity($sender);
        $this->postJson('/api/v1/invitations/send', [
            'channels' => ['whatsapp'], 'recipient_type' => 'external', 'external_name' => 'ضيف تجريبي',
            'external_phone' => '2025550148', 'external_country_code' => '1',
            'body' => '', 'media' => $this->image(),
        ])->assertCreated()->assertJsonPath('data.notification.status', 'sending');
        Queue::assertPushed(SendWhatsAppInvitationJob::class, function ($job) {
            return $job->messageBody === '' && $job->mediaType === 'image';
        });
        Http::assertNothingSent();
    }

    private function person(): Person
    {
        return Person::factory()->createQuietly(['birth_date' => '1990-01-01', 'death_date' => null]);
    }

    private function image(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('card.png', base64_decode(self::PNG));
    }

    private function asIdentity(Person $person): void
    {
        $token = SiteMobileAccessToken::issueIdentityToken($person->id, 'whatsapp')['token'];
        $this->withToken($token)->withSession([
            'site_person_id' => $person->id, 'site_auth_method' => 'whatsapp', 'site_password_verified' => true,
        ]);
    }
}
