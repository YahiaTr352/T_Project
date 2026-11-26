<?php

namespace Tests\Feature\Invitations;

use App\Jobs\SendWhatsAppInvitationJob;
use App\Jobs\SendExpoPushNotificationJob;
use App\Models\CreativeTemplate;
use App\Models\Notification;
use App\Models\Person;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\ExpoNotificationService;
use App\Services\Invitations\InvitationCardRenderer;
use App\Services\Invitations\CreativeTextLayout;
use App\Services\SiteMobileAccessToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
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

    public function test_admin_can_review_and_reclassify_images_and_api_filters_by_invitation_type(): void
    {
        $admin = User::factory()->createQuietly();
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));
        $upload = $this->actingAs($admin)->postJson('/dashboard/creative-templates', [
            'image' => UploadedFile::fake()->image('card.png', 600, 400),
            'invitation_type' => 'greeting',
        ])->assertCreated();
        $creative = CreativeTemplate::sole();
        $upload->assertJsonPath('data.redirect_url', '/dashboard/creative-templates?status=approved&invitation_type=greeting&source=admin');
        $this->assertSame(CreativeTemplate::STATUS_APPROVED, $creative->status);
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

    public function test_prepared_artwork_keeps_its_fixed_text_style_from_review_to_delivery(): void
    {
        $member = $this->person();
        $this->asIdentity($member);
        $image = imagecreatetruecolor(600, 400);
        imagefill($image, 0, 0, 0xffffff);
        imagefilledrectangle($image, 100, 170, 180, 189, 0x6e1238);
        imagefilledrectangle($image, 440, 140, 480, 175, 0x005c45);
        ob_start();
        imagepng($image);
        $original = ob_get_clean();
        $id = $this->postJson('/api/v1/invitations/creatives', [
            'image' => UploadedFile::fake()->createWithContent('artwork.png', $original),
            'invitation_type' => 'greeting', 'publication_consent' => true, 'design_mode' => 'artwork',
        ])->assertCreated()->assertJsonPath('data.template.status', 'pending')->json('data.template.id');
        $this->getJson('/api/v1/invitations/creatives')->assertJsonCount(0, 'data.templates');

        $admin = User::factory()->createQuietly();
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));
        $this->actingAs($admin)->postJson("/dashboard/creative-templates/{$id}/approve")
            ->assertUnprocessable()->assertJsonPath('code', 'CREATIVE_ARTWORK_REVIEW_REQUIRED');
        $endpoint = "/dashboard/creative-templates/{$id}/artwork";
        $this->get($endpoint)->assertOk()->assertSee('نص للتجربة');
        $this->get($endpoint.'/source')->assertOk()->assertHeader('Content-Type', 'image/png');
        $field = [
            'id' => 1, 'label' => 'اسم المدعو', 'text' => 'أحمد محمد', 'sample' => 'سارة',
            'x' => 13, 'y' => 35, 'width' => 48, 'height' => 20,
            'fontSize' => 5, 'fontFamily' => 'amiri', 'bold' => true, 'alignment' => 'right',
            'color' => '#6e1238', 'sourceColor' => '',
        ];
        $draft = ['fields' => json_encode([$field], JSON_UNESCAPED_UNICODE)];
        $this->postJson($endpoint, $draft)->assertUnprocessable()->assertJsonValidationErrors('proof');
        $preview = $this->postJson($endpoint.'/preview', $draft)->assertOk()->json();
        $this->assertStringStartsWith('data:image/jpeg;base64,', $preview['image']);
        $this->postJson($endpoint, [
            'fields' => json_encode([array_replace($field, ['fontSize' => 7])]),
            'proof' => $preview['proof'],
        ])->assertUnprocessable()->assertJsonPath('code', 'CREATIVE_ARTWORK_PREVIEW_REQUIRED');
        $this->postJson($endpoint, $draft + ['proof' => $preview['proof']])->assertOk();
        $this->postJson($endpoint, $draft + ['proof' => $preview['proof']])->assertUnprocessable();

        $creative = CreativeTemplate::findOrFail($id);
        $this->assertSame('approved', $creative->status);
        $this->assertSame($original, Storage::disk('public')->get($creative->image_path));
        Storage::disk('public')->assertExists($creative->editable_image_path);

        $sender = $this->person();
        $recipient = $this->person();
        $this->asIdentity($sender);
        $this->get('/invitations/send')->assertOk()->assertSee('data-prepared-artwork="true"', false);
        $this->getJson('/api/v1/invitations/creatives?editable_only=1')->assertOk()
            ->assertJsonPath('data.templates.0.editable_text_fields.0.default_text', 'أحمد محمد')
            ->assertJsonPath('data.templates.0.editable_text_fields.0.x', 13);
        $payload = ['template_key' => 'greeting', 'creative_id' => $id, 'body' => ''];
        $key = 'creative_text_1';
        $edit = $payload + ['template_vars' => [$key => 'سارة']];
        $this->postJson('/api/v1/invitations/preview-artwork', $edit)
            ->assertOk()->assertJsonPath('data.artwork_image', $preview['image']);
        $this->postJson('/invitations/preview-artwork', $edit)
            ->assertOk()->assertJsonPath('artwork_image', $preview['image']);
        $card = $this->postJson('/api/v1/invitations/preview', $edit)->assertOk()->json('data.card_image_url');
        $this->postJson('/invitations/preview-message', $edit)->assertOk()->assertJsonPath('card_image_url', $card);
        $cardBytes = Storage::disk('public')->get('notifications/cards/'.basename($card));
        $this->assertSame($preview['image'], 'data:image/jpeg;base64,'.base64_encode($cardBytes));

        $rendered = imagecreatefromstring($cardBytes);
        $this->assertGreaterThan(230, (imagecolorat($rendered, 243, 324) >> 8) & 255);
        $this->assertLessThan(30, (imagecolorat($rendered, 828, 279) >> 16) & 255);
        $unchanged = $this->postJson('/api/v1/invitations/preview', $payload + ['template_vars' => [$key => $field['text']]])
            ->assertOk()->json('data.card_image_url');
        $unchangedImage = imagecreatefromstring(Storage::disk('public')->get('notifications/cards/'.basename($unchanged)));
        $this->assertLessThan(40, (imagecolorat($unchangedImage, 243, 324) >> 8) & 255);

        $delivery = ['channels' => ['push'], 'recipient_type' => 'persons', 'person_ids' => [$recipient->id]];
        $overflow = $payload + ['template_vars' => [$key => str_repeat('محمد ', 40)]];
        $this->postJson('/api/v1/invitations/preview-artwork', $overflow)
            ->assertUnprocessable()->assertJsonPath('code', 'CREATIVE_TEXT_OVERFLOW');
        $this->postJson('/api/v1/invitations/send', $overflow + $delivery)
            ->assertUnprocessable()->assertJsonPath('code', 'CREATIVE_TEXT_OVERFLOW');
        $this->postJson('/api/v1/invitations/send', $edit + $delivery + ['creative_edit_source' => str_repeat('0', 64)])
            ->assertUnprocessable()->assertJsonPath('code', 'CREATIVE_EDIT_SOURCE_CHANGED');
        $this->postJson('/api/v1/invitations/send', $edit + $delivery + ['creative_edit_source' => $creative->artworkRevision()])->assertCreated();
        $this->assertSame($card, url('storage/'.Notification::where('person_id', $sender->id)->sole()->media_path));

        $updatedDraft = [
            'fields' => json_encode([array_replace($field, ['label' => 'اسم الضيف'])]),
            'reuse_background' => true,
        ];
        $updatedPreview = $this->actingAs($admin)->postJson($endpoint.'/preview', $updatedDraft)->assertOk()->json();
        $this->assertSame($preview['image'], $updatedPreview['image']);
        $this->postJson($endpoint, $updatedDraft + ['proof' => $updatedPreview['proof']])->assertOk();
        $this->assertNotSame($creative->artworkRevision(), $creative->fresh()->artworkRevision());
        $this->assertSame($cardBytes, Storage::disk('public')->get('notifications/cards/'.basename($card)));
    }

    public function test_ready_and_overlay_templates_are_filtered_and_delivered_in_their_respective_modes(): void
    {
        $admin = User::factory()->createQuietly();
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));
        $this->actingAs($admin);
        $ready = ['name' => 'قالب بصورة جاهزة', 'invitation_type' => 'greeting', 'design_mode' => 'ready'];
        $readyId = $this->postJson('/dashboard/creative-templates', $ready + [
            'image' => $this->image(),
        ])->assertCreated()->assertJsonPath('data.template.design_mode', 'ready')->json('data.template.id');

        $overlay = ['name' => 'قالب بانعكاس الرسالة', 'invitation_type' => 'greeting', 'design_mode' => 'overlay'];
        $this->postJson('/dashboard/creative-templates', $overlay + ['image' => $this->image()])
            ->assertUnprocessable()->assertJsonValidationErrors('text_layout');
        $layout = array_column(CreativeTextLayout::editableFields('greeting'), 'box', 'key');
        $legacyNote = ['x' => 8, 'y' => 56, 'width' => 84, 'height' => 10, 'fontSize' => 5, 'text' => 'أهلًا وسهلًا'];
        $overlayId = $this->postJson('/dashboard/creative-templates', $overlay + [
            'image' => UploadedFile::fake()->image('overlay.png', 900, 740),
            'text_layout' => json_encode($layout + ['note' => $legacyNote]),
        ])->assertCreated()->assertJsonPath('data.template.design_mode', 'overlay')->json('data.template.id');
        $this->assertEquals($layout, CreativeTemplate::findOrFail($overlayId)->text_layout);
        $this->get('/dashboard/creative-templates?status=approved&invitation_type=greeting&source=admin&design_mode=ready')
            ->assertOk()->assertSee($ready['name'])->assertDontSee($overlay['name']);
        $this->get("/dashboard/creative-templates/{$readyId}/text-layout")->assertNotFound();

        $sender = $this->person();
        $recipient = $this->person();
        $this->asIdentity($sender);
        $this->getJson('/api/v1/invitations/creatives?invitation_type=greeting&design_mode=ready')
            ->assertOk()->assertJsonCount(1, 'data.templates')->assertJsonPath('data.templates.0.id', $readyId)
            ->assertJsonPath('data.templates.0.preview_image_url', null);
        $this->getJson('/api/v1/invitations/creatives?invitation_type=greeting&design_mode=overlay')
            ->assertOk()->assertJsonCount(1, 'data.templates')->assertJsonPath('data.templates.0.id', $overlayId)
            ->assertJsonPath('data.templates.0.variable_keys', ['occasion']);
        $readyPayload = ['template_key' => 'greeting', 'creative_id' => $readyId, 'body' => ''];
        $this->postJson('/api/v1/invitations/preview', $readyPayload)
            ->assertOk()->assertJsonPath('data.card_image_url', null)->assertJsonPath('data.creative.design_mode', 'ready')
            ->assertJsonPath('data.preview', '');
        $this->postJson('/api/v1/invitations/send', array_replace($readyPayload, ['body' => 'نص الدعوة الخاص']) + [
            'channels' => ['whatsapp'], 'recipient_type' => 'persons', 'person_ids' => [$recipient->id],
        ])->assertCreated();
        $notification = Notification::where('person_id', $sender->id)->sole();
        $this->assertSame('نص الدعوة الخاص', $notification->body);
        $this->assertSame(
            Storage::disk('public')->get(CreativeTemplate::findOrFail($readyId)->image_path),
            Storage::disk('public')->get($notification->media_path),
        );
        $this->assertNull(app(InvitationCardRenderer::class)->renderForNotification($notification, $notification->body, $recipient->id, ''));

        $overlayPayload = [
            'template_key' => 'greeting', 'creative_id' => $overlayId, 'body' => 'نتشرف بحضوركم',
            'template_vars' => ['occasion' => 'التخرج'],
        ];
        $preview = $this->postJson('/api/v1/invitations/preview', $overlayPayload)->assertOk()->json('data.card_image_url');
        $this->assertNotEmpty($preview);
        $this->postJson('/api/v1/invitations/preview', array_replace($overlayPayload, [
            'title' => 'عنوان مخصص', 'body' => 'نرحب بكم جميعًا',
        ]))->assertOk()->assertJsonPath('data.card_image_url', $preview);

        $this->actingAs($admin)->get("/dashboard/creative-templates/{$overlayId}/text-layout")
            ->assertOk()->assertSee('ضبط الموضع بالأرقام')->assertSee('مائل')->assertSee('تحته خط')
            ->assertDontSee('كتابة ثابتة');
        $invalid = $layout;
        $invalid['message']['x'] = 99;
        $this->putJson("/dashboard/creative-templates/{$overlayId}/text-layout", [
            'invitation_type' => 'greeting', 'text_layout' => json_encode($invalid),
        ])->assertUnprocessable()->assertJsonPath('errors.text_layout.0', 'نص الدعوة خارج إطار الصورة. عدّل الموضع أو الأبعاد.');
        $original = Storage::disk('public')->get('notifications/cards/'.basename($preview));
        foreach (['bold', 'italic', 'underline'] as $style) {
            $formatted = $layout;
            $formatted['message']['inline']['occasion'][$style] = true;
            $this->putJson("/dashboard/creative-templates/{$overlayId}/text-layout", [
                'invitation_type' => 'greeting', 'text_layout' => json_encode($formatted),
            ])->assertOk();
            $this->assertEquals($formatted, CreativeTemplate::findOrFail($overlayId)->text_layout);
            $formattedPreview = $this->postJson('/api/v1/invitations/preview', $overlayPayload)
                ->assertOk()->json('data.card_image_url');
            $this->assertNotSame($original, Storage::disk('public')->get('notifications/cards/'.basename($formattedPreview)));
        }
        $moved = $layout;
        $moved['message']['inline']['occasion'] = ['offsetX' => -3, 'offsetY' => -4, 'fontSize' => 7];
        $this->putJson("/dashboard/creative-templates/{$overlayId}/text-layout", [
            'invitation_type' => 'greeting', 'text_layout' => json_encode($moved),
        ])->assertOk();
        $this->assertEquals($moved, CreativeTemplate::findOrFail($overlayId)->text_layout);
        $movedPreview = $this->postJson('/api/v1/invitations/preview', $overlayPayload)->assertOk()->json('data.card_image_url');
        $this->assertNotSame($original, Storage::disk('public')->get('notifications/cards/'.basename($movedPreview)));
        $moved['message']['inline']['occasion']['offsetX'] = 99;
        $this->postJson("/dashboard/creative-templates/{$overlayId}/text-layout/preview", [
            'invitation_type' => 'greeting', 'text_layout' => json_encode($moved),
        ])->assertUnprocessable()->assertJsonPath('message', 'العنصر «المناسبة» خارج إطار الصورة. عدّل إزاحته.');
        $newVariable = array_replace($overlayPayload, ['template_vars' => ['occasion' => 'الترقية']]);
        $next = $this->postJson('/api/v1/invitations/preview', $newVariable)->assertOk()->json('data.card_image_url');
        $this->assertNotSame(
            Storage::disk('public')->get('notifications/cards/'.basename($movedPreview)),
            Storage::disk('public')->get('notifications/cards/'.basename($next)),
        );
    }

    public function test_local_member_upload_preserves_category_without_sending_review_notices(): void
    {
        $this->asIdentity($this->person());
        $this->postJson('/api/v1/invitations/creatives', ['image' => $this->image()])
            ->assertUnprocessable()->assertJsonValidationErrors('invitation_type');
        $this->postJson('/api/v1/invitations/creatives', [
            'image' => $this->image(), 'invitation_type' => 'condolences',
        ])->assertUnprocessable()->assertJsonValidationErrors('publication_consent');
        $this->postJson('/api/v1/invitations/creatives', [
            'image' => $this->image(),
            'invitation_type' => 'condolences',
            'publication_consent' => true,
        ])->assertCreated()->assertJsonPath('data.template.status', 'pending');
        $design = CreativeTemplate::sole();
        $this->assertSame('condolences', $design->invitation_type);
        $this->assertNotNull($design->publication_consented_at);
        $this->deleteJson("/api/v1/invitations/creatives/{$design->id}")
            ->assertForbidden()->assertJsonPath('code', 'CREATIVE_DELETE_FORBIDDEN');
        $this->assertDatabaseHas('creative_templates', ['id' => $design->id]);
        Storage::disk('public')->assertExists($design->image_path);
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    public function test_gallery_previews_show_saved_invitation_text_with_placeholder_codes(): void
    {
        $member = $this->person();
        $this->asIdentity($member);
        $fields = CreativeTextLayout::editableFields('event_invitation');
        $this->assertStringContainsString('{event_name}', $fields[0]['sample']);
        $this->assertStringContainsString('{name}', $fields[0]['sample']);
        $layout = array_column($fields, 'box', 'key');
        $layout['message']['inline']['event_name'] = ['bold' => true, 'italic' => true, 'underline' => true, 'offsetY' => -2, 'fontSize' => 6];
        $id = $this->postJson('/api/v1/invitations/creatives', [
            'image' => UploadedFile::fake()->image('overlay.png', 900, 740),
            'name' => 'معاينة الدعوة',
            'invitation_type' => 'event_invitation',
            'design_mode' => 'overlay',
            'text_layout' => json_encode($layout),
            'publication_consent' => true,
        ])->assertCreated()->json('data.template.id');
        $own = $this->getJson('/api/v1/invitations/creatives/mine')
            ->assertOk()->assertJsonPath('data.templates.0.id', $id)->json('data.templates.0');
        $preview = $own['preview_image_url'];
        $this->assertNotEmpty($preview);
        $this->assertNotSame($own['image_url'], $preview);
        Storage::disk('public')->assertExists('notifications/cards/'.basename($preview));
        $this->getJson('/api/v1/invitations/creatives?invitation_type=event_invitation')
            ->assertOk()->assertJsonCount(0, 'data.templates');

        $admin = User::factory()->createQuietly();
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));
        $this->actingAs($admin)->get('/dashboard/creative-templates?status=pending')
            ->assertOk()->assertSee('data-image="'.$preview.'"', false);
        $this->postJson("/dashboard/creative-templates/{$id}/approve")->assertOk();
        $this->getJson('/api/v1/invitations/creatives?invitation_type=event_invitation')
            ->assertOk()->assertJsonPath('data.templates.0.preview_image_url', $preview);
        $payload = [
            'template_key' => 'event_invitation', 'creative_id' => $id,
            'template_vars' => ['event_name' => 'اللقاء السنوي'],
            'font_family' => 'amiri', 'font_weight' => 'bold',
        ];
        $base = $this->postJson('/api/v1/invitations/preview', $payload)->assertOk()->json('data.card_image_url');
        $this->assertNotSame($preview, $base);
    }

    public function test_member_layout_must_fit_the_image_and_match_the_invitation_type(): void
    {
        $this->asIdentity($this->person());
        $layout = CreativeTextLayout::defaults('wedding');
        $payload = ['invitation_type' => 'wedding', 'publication_consent' => true];
        foreach ([
            array_replace($layout, ['groom' => $layout['title']]),
            array_replace($layout, ['groom' => ['x' => 95] + $layout['groom']]),
            CreativeTextLayout::defaults('greeting'),
        ] as $invalid) {
            $this->postJson('/api/v1/invitations/creatives', $payload + [
                'image' => $this->image(), 'text_layout' => json_encode($invalid),
            ])->assertUnprocessable()->assertJsonValidationErrors('text_layout');
        }
        $this->assertDatabaseCount('creative_templates', 0);
        $this->postJson('/api/v1/invitations/creatives', $payload + [
            'image' => $this->image(), 'text_layout' => json_encode($layout),
        ])->assertCreated();
        $this->assertEquals($layout, CreativeTemplate::sole()->text_layout);
    }

    public function test_dashboard_combines_invitation_type_and_status_filters_and_shows_the_type_in_the_card(): void
    {
        $admin = User::factory()->createQuietly();
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));
        $this->actingAs($admin);

        $pending = CreativeTemplate::create([
            'name' => 'Pending wedding design', 'image_path' => 'wedding.png',
            'invitation_type' => 'wedding', 'status' => 'pending',
        ]);
        CreativeTemplate::create([
            'name' => 'Approved wedding design', 'image_path' => 'approved.png',
            'invitation_type' => 'wedding', 'status' => 'approved',
        ]);
        CreativeTemplate::create([
            'name' => 'Greeting design', 'image_path' => 'greeting.png',
            'invitation_type' => 'greeting', 'status' => 'pending',
        ]);
        CreativeTemplate::create([
            'name' => 'General design', 'image_path' => 'general.png', 'status' => 'pending',
        ]);

        $filtered = $this->get('/dashboard/creative-templates?status=pending&invitation_type=wedding');
        $filtered->assertOk()->assertSeeInOrder([
            'data-visual-card="'.$pending->id.'"', 'Pending wedding design', 'data-category-label>زفاف', 'تعديل النوع', '</article>',
        ], false)->assertSee('status=approved&amp;invitation_type=wedding', false)
            ->assertDontSee('Approved wedding design')->assertDontSee('Greeting design')->assertDontSee('General design')
            ->assertSee('id="visualUploadModal"', false)->assertSee('اسم التصميم (اختياري)')->assertSee('الصورة غير متاحة');

        $this->get('/dashboard/creative-templates?status=approved&invitation_type=wedding')
            ->assertOk()->assertSee('Approved wedding design')->assertDontSee('Pending wedding design');
        $this->get('/dashboard/creative-templates?status=pending&invitation_type=unclassified')
            ->assertOk()->assertSee('General design')->assertDontSee('Greeting design');
    }

    public function test_dashboard_paginates_designs_and_keeps_the_filters_on_the_next_page(): void
    {
        $admin = User::factory()->createQuietly();
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));
        $this->actingAs($admin);

        CreativeTemplate::insert(array_map(fn (int $index) => [
            'name' => sprintf('Wedding card %02d', $index),
            'image_path' => 'wedding.png',
            'invitation_type' => 'wedding',
            'status' => CreativeTemplate::STATUS_PENDING,
            'created_at' => now(),
        ], range(1, 25)));
        CreativeTemplate::create([
            'name' => 'Greeting card', 'image_path' => 'greeting.png',
            'invitation_type' => 'greeting', 'status' => CreativeTemplate::STATUS_PENDING,
        ]);

        $filtered = '/dashboard/creative-templates?status=pending&invitation_type=wedding&source=admin';
        $this->get($filtered)->assertOk()
            ->assertViewHas('creatives', fn ($designs) => $designs->count() === 24 && $designs->total() === 25)
            ->assertSee('status=pending&amp;invitation_type=wedding&amp;source=admin&amp;page=2', false)
            ->assertSee('Wedding card 25')->assertDontSee('Wedding card 01');

        $this->get($filtered.'&page=2')->assertOk()
            ->assertViewHas('creatives', fn ($designs) => $designs->currentPage() === 2 && $designs->count() === 1)
            ->assertSee('Wedding card 01')->assertDontSee('Wedding card 25')->assertDontSee('Greeting card');
    }

    public function test_dashboard_separates_admin_uploads_from_member_designs(): void
    {
        $reviewer = User::factory()->createQuietly();
        $reviewer->givePermissionTo(Permission::findOrCreate('notifications.view', 'web'));
        $this->actingAs($reviewer);
        $template = [
            'invitation_type' => 'wedding', 'status' => CreativeTemplate::STATUS_APPROVED, 'image_path' => 'wedding.png',
        ];

        CreativeTemplate::create($template + ['name' => 'Admin wedding']);
        CreativeTemplate::create($template + ['name' => 'Member wedding', 'uploaded_by' => $this->person()->id]);
        CreativeTemplate::create($template + ['name' => 'Former member wedding', 'publication_consented_at' => now()]);

        $filtered = '/dashboard/creative-templates?status=approved&invitation_type=wedding';
        $this->get($filtered.'&source=admin')->assertOk()
            ->assertSee('Admin wedding')->assertDontSee('Member wedding')->assertDontSee('Former member wedding')
            ->assertDontSee('id="visualUploadModal"', false);
        $this->get($filtered.'&source=members')->assertOk()
            ->assertSee('Member wedding')->assertSee('Former member wedding')->assertDontSee('Admin wedding')
            ->assertSee('status=pending&amp;invitation_type=wedding&amp;source=members', false);
        $this->postJson('/dashboard/creative-templates', ['image' => $this->image(), 'invitation_type' => 'wedding'])
            ->assertForbidden();
    }

    public function test_legacy_wedding_layout_becomes_a_personalized_attachment(): void
    {
        $sender = $this->person();
        $recipient = $this->person();
        $this->asIdentity($sender);
        $this->postJson('/api/v1/invitations/creatives', [
            'image' => UploadedFile::fake()->image('wedding.png', 900, 330),
            'name' => 'بطاقة زفاف', 'invitation_type' => 'wedding', 'publication_consent' => '1',
        ])->assertCreated();

        $design = CreativeTemplate::sole();
        $design->update(['design_mode' => null]);
        $this->get('/invitations/creatives')->assertOk()->assertSee('تصاميمي')->assertSee('بطاقة زفاف')
            ->assertSee(route('invitations.creatives.preview-image', $design->id));
        $this->get(route('invitations.creatives.preview-image', $design->id))->assertRedirect();
        $this->getJson('/api/v1/invitations/creatives?invitation_type=wedding')->assertJsonCount(0, 'data.templates');
        $admin = User::factory()->createQuietly();
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));
        $layout = CreativeTextLayout::defaults('wedding');
        $layout['groom']['fontSize'] = 11;
        $layout['groom']['x'] = 35;
        $layout['groom']['width'] = 30;
        $this->actingAs($admin)->get("/dashboard/creative-templates/{$design->id}/text-layout")
            ->assertOk()->assertSee('حفظ توزيع النص');
        $proof = $this->postJson("/dashboard/creative-templates/{$design->id}/text-layout/preview", [
            'invitation_type' => 'wedding', 'text_layout' => json_encode($layout),
        ])->assertOk()->json('image_url');
        Storage::disk('public')->assertExists('notifications/cards/'.basename($proof));
        $this->assertNull($design->fresh()->text_layout);
        $this->putJson("/dashboard/creative-templates/{$design->id}/text-layout", [
            'invitation_type' => 'wedding', 'text_layout' => json_encode($layout),
        ])->assertOk();
        $this->assertEquals($layout, $design->fresh()->text_layout);
        $this->actingAs($admin)->postJson("/dashboard/creative-templates/{$design->id}/approve")
            ->assertOk()->assertJsonPath('data.template.invitation_type', 'wedding');
        $this->getJson('/api/notifications')->assertOk()->assertJsonPath('data.data.0.title', 'تم اعتماد تصميمك');
        $this->getJson('/api/v1/invitations/creatives?invitation_type=wedding')->assertJsonPath('data.templates.0.id', $design->id);
        $this->getJson('/api/v1/invitations/creatives?invitation_type=greeting')->assertJsonCount(0, 'data.templates');

        $payload = [
            'template_key' => 'wedding', 'creative_id' => $design->id,
            'title' => 'دعوة زفاف', 'body' => 'زفاف {groom} في {location}',
            'template_vars' => ['hosts' => 'عائلة إبراهيم', 'groom' => 'خالد', 'date' => '2026-12-01', 'time' => '8:00', 'location' => 'قاعة الفرح'],
            'font_family' => 'amiri', 'font_weight' => 'bold',
        ];
        $this->postJson('/api/v1/invitations/preview', array_replace($payload, ['template_key' => 'greeting']))
            ->assertUnprocessable()->assertJsonPath('code', 'CREATIVE_TYPE_MISMATCH');
        $tooLong = $payload;
        $tooLong['template_vars']['groom'] = str_repeat('م', 200);
        $this->postJson('/api/v1/invitations/preview', $tooLong)
            ->assertUnprocessable()->assertJsonPath('code', 'CREATIVE_TEXT_OVERFLOW');
        $preview = $this->postJson('/api/v1/invitations/preview', $payload)->assertOk()->json('data.card_image_url');
        $this->postJson('/invitations/preview-message', array_replace($payload, [
            'font_family' => 'tajawal', 'font_weight' => 'regular',
        ]))->assertOk()->assertJsonPath('card_image_url', $preview);
        $this->postJson('/api/v1/invitations/send', $payload + [
            'channels' => ['push', 'whatsapp'], 'recipient_type' => 'persons', 'person_ids' => [$recipient->id],
        ])->assertCreated();

        $notification = Notification::where('person_id', $sender->id)->sole();
        $this->assertSame('image', $notification->media_type);
        $this->assertTrue($notification->targeting_filters['creative_card_rendered']);
        $this->assertSame(url('storage/'.$notification->media_path), $preview);
        $firstImage = Storage::disk('public')->get($notification->media_path);
        [$width, $height] = getimagesizefromstring($firstImage);
        $this->assertEqualsWithDelta(900 / 330, $width / $height, 0.01);
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

    public function test_review_creates_a_private_notice_and_queues_push_for_the_design_owner(): void
    {
        $owner = $this->person();
        $other = $this->person();
        $ownerToken = 'ExponentPushToken[creative-owner]';
        UserDevice::create(['person_id' => $owner->id, 'expo_push_token' => $ownerToken, 'os_platform' => 'android']);
        UserDevice::create(['person_id' => $other->id, 'expo_push_token' => 'ExponentPushToken[other-member]', 'os_platform' => 'ios']);
        $draft = CreativeTemplate::create([
            'uploaded_by' => $owner->id, 'image_path' => 'draft.png', 'name' => 'بطاقة',
            'invitation_type' => 'wedding', 'status' => CreativeTemplate::STATUS_PENDING,
            'design_mode' => 'overlay', 'text_layout' => CreativeTextLayout::defaults('wedding'),
        ]);
        $rejected = $draft->replicate();
        $rejected->save();
        $admin = User::factory()->createQuietly();
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));
        $this->actingAs($admin)->postJson("/dashboard/creative-templates/{$draft->id}/approve")
            ->assertUnprocessable()->assertJsonPath('code', 'PUBLICATION_CONSENT_REQUIRED');
        $this->asIdentity($other);
        $this->postJson("/api/v1/invitations/creatives/{$draft->id}/publication-consent", ['publication_consent' => true])->assertNotFound();
        $this->asIdentity($owner);
        $this->postJson("/api/v1/invitations/creatives/{$draft->id}/publication-consent", ['publication_consent' => true])->assertOk();

        config(['invitations.delivery_enabled' => true]);
        $this->actingAs($admin)->postJson("/dashboard/creative-templates/{$draft->id}/approve")->assertOk();
        $this->postJson("/dashboard/creative-templates/{$rejected->id}/reject", ['reason' => 'النص غير واضح'])->assertOk();
        $this->postJson("/dashboard/creative-templates/{$draft->id}/approve")->assertStatus(422);
        $notices = Notification::all();
        $this->assertCount(2, $notices);
        Queue::assertPushed(SendExpoPushNotificationJob::class, 2);
        Queue::assertPushed(SendExpoPushNotificationJob::class, fn ($job) => $job->expoPushToken === $ownerToken
            && $job->personId === $owner->id && $job->isSenderNotice && $job->data['decision'] === 'approved');
        Queue::assertPushed(SendExpoPushNotificationJob::class, fn ($job) => $job->expoPushToken === $ownerToken
            && $job->personId === $owner->id && $job->isSenderNotice && $job->data['decision'] === 'rejected');
        $expo = $this->mock(ExpoNotificationService::class);
        foreach (['approved' => 'تم اعتماد تصميمك', 'rejected' => 'تم رفض تصميمك'] as $decision => $title) {
            $expo->shouldReceive('send')->once()
                ->withArgs(fn ($token, $pushTitle, $body, $data) => $token === $ownerToken && $pushTitle === $title
                    && $data['decision'] === $decision && isset($data['notification_id']))
                ->andReturn(['success' => true, 'sent_count' => 1]);
        }
        Queue::pushed(SendExpoPushNotificationJob::class)->each(fn ($job) => $job->handle($expo));
        Queue::assertNotPushed(SendWhatsAppInvitationJob::class);
        $this->getJson('/api/notifications')->assertOk()->assertJsonCount(2, 'data.data')
            ->assertJsonFragment(['body' => 'تم رفض التصميم بطاقة. السبب: النص غير واضح']);
        $this->asIdentity($other);
        $this->getJson('/api/notifications')->assertOk()->assertJsonCount(0, 'data.data');
        $notices->each(fn ($notice) => $notice->recipients()->delete());
        $this->getJson('/api/notifications')->assertOk()->assertJsonCount(0, 'data.data');
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
            'body' => 'دعوة بالخط الافتراضي', 'font_family' => 'tajawal', 'font_weight' => 'regular',
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
