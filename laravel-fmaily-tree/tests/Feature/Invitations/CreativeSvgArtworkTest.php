<?php

namespace Tests\Feature\Invitations;

use App\Models\CreativeTemplate;
use App\Models\Notification;
use App\Models\Person;
use App\Models\User;
use App\Services\SiteMobileAccessToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CreativeSvgArtworkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['invitations.delivery_enabled' => false, 'site.password_protection_enabled' => false]);
        Queue::fake();
        Http::preventStrayRequests();
        Storage::fake('local');
        Storage::fake('public');
    }

    public function test_editable_svg_can_be_reviewed_and_sent_from_the_website(): void
    {
        $member = $this->identity();
        $this->get('/invitations/creatives')->assertOk()->assertSee('accept=".jpg,.jpeg,.png,.webp,.svg,image/svg+xml"', false);
        $this->upload('ملف الدعوة', '<style>.name{font-family:Tajawal;fill:#990033}</style>
            <rect width="800" height="420" fill="#ffe5c8"/><text class="name" x="380" y="250" text-anchor="middle"><tspan>أحمد محمد</tspan></text>');
        $template = CreativeTemplate::sole();
        $this->assertSame('pending', $template->status);
        $this->assertSame(800, $template->svg_inspection['width']);
        $this->assertSame(['Tajawal'], $template->svg_inspection['fonts']);
        Storage::disk('local')->assertExists($template->svg_path);
        Storage::disk('public')->assertMissing($template->svg_path);
        $this->getJson("/invitations/creatives/{$template->id}/svg-source")->assertNotFound();

        $this->editor();
        $review = "/dashboard/creative-templates/{$template->id}/svg";
        $this->get($review)->assertOk()->assertSee('معاينة النص البديل');
        $this->getJson($review.'/source')->assertOk()->assertJsonPath('fields.creative_text_1', 'أحمد محمد');
        $this->postJson("/dashboard/creative-templates/{$template->id}/approve")
            ->assertUnprocessable()->assertJsonPath('code', 'CREATIVE_SVG_REVIEW_REQUIRED');
        $fields = ['creative_text_1' => ['label' => 'اسم المدعو', 'sample' => 'سارة', 'x' => 20, 'y' => 40, 'width' => 50, 'height' => 8]];
        $this->postJson($review, [
            'fields' => $fields, 'original' => $this->png(), 'preview' => $this->png(0xe8d5bf, 20),
        ])->assertUnprocessable();
        $this->postJson($review, [
            'fields' => $fields, 'original' => $this->png(), 'preview' => $this->png(0xe8d5bf),
        ])->assertOk();
        $template->refresh();
        $this->assertSame('approved', $template->status);
        Storage::disk('public')->assertExists($template->image_path);

        $recipient = $this->identity();
        $this->identity($member);
        $this->get('/invitations/send')->assertOk()->assertSee('data-svg-source=', false)->assertSee('اسم المدعو');
        $this->getJson("/invitations/creatives/{$template->id}/svg-source")->assertOk()
            ->assertJsonPath('revision', $template->artworkRevision());
        $this->getJson('/api/v1/invitations/creatives')->assertJsonCount(0, 'data.templates');
        $this->getJson('/api/v1/invitations/creatives/mine')->assertJsonCount(0, 'data.templates');

        $params = [
            'creative_id' => $template->id, 'template_key' => 'greeting',
            'creative_edit_source' => $template->artworkRevision(),
            'template_vars' => ['creative_text_1' => 'سارة'],
        ];
        $this->postJson('/invitations/preview-message', $params)->assertUnprocessable();
        $this->postJson('/invitations/preview-message', $params + ['svg_card' => $this->png(0xe8d5bf, 20)])->assertUnprocessable();
        $cardBytes = $this->png(0xe8d5bf)->get();
        $preview = $this->postJson('/invitations/preview-message', $params + ['svg_card' => $this->png(0xe8d5bf)])
            ->assertOk()->json('web_card_image_url');
        $this->post('/invitations/send', $params + [
            'svg_card' => $this->png(0xe8d5bf), 'channels' => ['push'], 'recipient_type' => 'persons', 'person_ids' => [$recipient->id],
        ])->assertRedirect(route('invitations.logs'));
        $path = Notification::where('person_id', $member->id)->sole()->media_path;
        $this->assertStringEndsWith($path, $preview);
        $this->assertSame($cardBytes, Storage::disk('public')->get($path));
    }

    public function test_converted_svg_and_missing_fonts_show_review_warnings(): void
    {
        $this->identity();
        $png = base64_encode($this->png()->get());
        $this->upload('صورة محولة', '<image x="0" y="0" width="800" height="420" href="data:image/png;base64,'.$png.'"/>');
        $this->upload('خط مفقود', '<text font-family="Missing Family, serif">النص</text>');
        $this->upload('وزن خط مفقود', '<g style="font-family:Tajawal;font-weight:600"><text y="20">النص</text></g>');
        $this->get('/invitations/creatives')->assertOk()->assertSee('ما في نص قابل للتعديل')->assertSee('الخط الأصلي غير متاح');

        $this->editor();
        $this->get('/dashboard/creative-templates')->assertOk()->assertSee('ما في نص قابل للتعديل')
            ->assertSee('Missing Family')->assertSee('وزن الخط أو نمطه غير متاح');
        foreach (CreativeTemplate::all() as $template) {
            $this->postJson("/dashboard/creative-templates/{$template->id}/approve")->assertUnprocessable();
        }
        $this->postJson('/dashboard/creative-templates', [
            'image' => $this->svg('<image href="https://example.test/image.png"/>'), 'invitation_type' => 'greeting', 'design_mode' => 'svg',
        ])->assertUnprocessable()->assertJsonPath('message', 'ضمّن صور الخلفية داخل ملف SVG وأزل الروابط الخارجية قبل الرفع.');
        $this->assertDatabaseCount('creative_templates', 3);
    }

    public function test_member_prepares_fields_on_the_original_image_in_the_web_editor(): void
    {
        $member = $this->identity();
        $page = $this->get('/invitations/creatives')->assertOk()->assertSee('فتح محرّر المتغيّرات')
            ->assertSee('معاينة التصميم')->assertDontSee('قالب قابل للتخصيص')
            ->assertDontSee('نص بديل للمعاينة')->assertDontSee('استبدال الكتابة المطبوعة');
        $page->assertDontSee('name="design_mode"', false);
        $source = fn () => $this->rasterDesign();
        $layout = [
            ['id' => 1, 'label' => 'اسم المدعو', 'text' => 'أحمد', 'sample' => 'سارة', 'x' => 10, 'y' => 40,
                'width' => 42, 'height' => 19, 'fontFamily' => 'amiri', 'fontSize' => 5, 'bold' => false,
                'alignment' => 'center', 'color' => '#213529', 'replacePrinted' => true],
            ['id' => 2, 'label' => 'المكان', 'text' => 'القاعة', 'sample' => 'الورد', 'x' => 52, 'y' => 67,
                'width' => 39, 'height' => 25, 'fontFamily' => 'tajawal', 'fontSize' => 5, 'bold' => true,
                'alignment' => 'center', 'color' => '#16463b', 'replacePrinted' => false],
        ];
        $fields = json_encode($layout);
        $preview = $this->postJson('/invitations/creatives/field-preview', ['image' => $source(), 'fields' => $fields])->assertOk()->json();
        $this->assertStringStartsWith('data:image/png;base64,', $preview['design']);
        $this->assertStringStartsWith('data:image/png;base64,', $preview['image']);
        $this->assertNotSame($preview['design'], $preview['image']);
        $original = imagecreatefromstring($source()->get());
        $mockup = imagecreatefromstring(base64_decode(explode(',', $preview['image'], 2)[1]));
        $this->assertSame([1300, 840], [imagesx($mockup), imagesy($mockup)]);
        $this->assertSame(imagecolorat($original, 45, 112), imagecolorat($mockup, 45, 112));
        $this->assertSame(imagecolorat($original, 925, 765), imagecolorat($mockup, 925, 765));
        $this->assertNotSame(imagecolorat($original, 162, 425), imagecolorat($mockup, 162, 425));
        $this->assertDatabaseCount('creative_templates', 0);

        $form = ['image' => $source(), 'fields' => $fields, 'invitation_type' => 'greeting', 'publication_consent' => 1];
        $this->postJson('/invitations/creatives', $form)->assertUnprocessable()->assertJsonPath('code', 'CREATIVE_ARTWORK_PREVIEW_REQUIRED');
        $tampered = $layout;
        $tampered[0]['color'] = '#ffffff';
        $this->postJson('/invitations/creatives', array_replace($form, ['fields' => json_encode($tampered), 'proof' => $preview['proof']]))
            ->assertUnprocessable()->assertJsonPath('code', 'CREATIVE_ARTWORK_PREVIEW_REQUIRED');
        $this->identity();
        $this->postJson('/invitations/creatives', $form + ['proof' => $preview['proof']])->assertUnprocessable();
        $this->identity($member);
        $this->postJson('/invitations/creatives', $form + ['proof' => $preview['proof']])->assertCreated();
        $template = CreativeTemplate::sole();
        $this->assertSame('pending', $template->status);
        $this->assertSame(CreativeTemplate::MODE_WEB_ARTWORK, $template->designMode());
        $this->assertSame($source()->get(), Storage::disk('public')->get($template->image_path));
        $this->assertSame('المكان', $template->editable_text['creative_text_2']['label']);

        $this->editor();
        $this->get('/dashboard/creative-templates')->assertOk()
            ->assertSee('data-visual-preview-fields=', false)->assertSee('data-visual-preview-regions', false)->assertSee('اسم المدعو');
        $this->postJson("/dashboard/creative-templates/{$template->id}/approve")->assertOk();
        $this->identity($member);
        $this->get('/invitations/send')->assertOk()->assertSee('اسم المدعو')->assertSee('data-prepared-artwork="true"', false);
        $this->getJson('/api/v1/invitations/creatives')->assertJsonCount(0, 'data.templates');
        $request = [
            'creative_id' => $template->id, 'template_key' => 'greeting',
            'creative_edit_source' => $template->artworkRevision(),
            'template_vars' => ['creative_text_1' => 'سارة', 'creative_text_2' => 'الورد'],
        ];
        $this->postJson('/invitations/preview-artwork', $request)->assertOk()->assertJsonPath('artwork_image', $preview['image']);
        $recipient = $this->identity();
        $this->identity($member);
        $this->post('/invitations/send', $request + [
            'channels' => ['push'], 'recipient_type' => 'persons', 'person_ids' => [$recipient->id],
        ])->assertRedirect(route('invitations.logs'));
        $sent = Notification::where('person_id', $member->id)->sole()->media_path;
        $this->assertSame('data:image/png;base64,'.base64_encode(Storage::disk('public')->get($sent)), $preview['image']);
    }

    public function test_web_member_can_preview_and_submit_a_field_with_one_text_value(): void
    {
        $this->identity();
        $fields = json_encode([[
            'id' => 1, 'label' => 'اسم المدعو', 'text' => 'أحمد', 'x' => 22.15, 'y' => 30.32,
            'width' => 35.21, 'height' => 16.16, 'fontFamily' => 'amiri', 'fontSize' => 4, 'bold' => false,
            'alignment' => 'center', 'color' => '#123456',
        ]]);
        $preview = $this->postJson('/invitations/creatives/field-preview', [
            'image' => $this->png(), 'fields' => $fields,
        ])->assertOk()->assertJsonMissingPath('image')->json();
        $this->assertStringStartsWith('data:image/png;base64,', $preview['design']);
        $this->postJson('/invitations/creatives', [
            'image' => $this->png(), 'fields' => $fields, 'proof' => $preview['proof'],
            'invitation_type' => 'greeting', 'publication_consent' => 1,
        ])->assertCreated();

        $template = CreativeTemplate::sole();
        $field = $template->editable_text['creative_text_1'];
        $this->assertSame('أحمد', $field['text']);
        $this->assertFalse($field['replacePrinted']);
        $this->assertSame(url('storage/'.$template->image_path), $template->image_url);
        $this->assertSame(
            Storage::disk('public')->get($template->image_path),
            Storage::disk('public')->get($template->editable_image_path),
        );
    }

    public function test_member_can_submit_an_image_without_adding_fields(): void
    {
        $this->identity();
        $this->postJson('/invitations/creatives', [
            'image' => $this->png(), 'invitation_type' => 'greeting', 'publication_consent' => 1,
        ])->assertCreated();
        $template = CreativeTemplate::sole();
        $this->assertNull($template->editable_text);
        $this->editor();
        $this->postJson("/dashboard/creative-templates/{$template->id}/approve")->assertOk();
        $this->get('/invitations/send')->assertOk()->assertSee('data-prepared-artwork="false"', false);
    }

    public function test_admin_can_preview_fields_before_uploading_and_approving_an_image(): void
    {
        $member = $this->identity();
        $fields = json_encode([[
            'id' => 1, 'label' => 'اسم المدعو', 'text' => 'أحمد', 'x' => 51.44, 'y' => 30.32,
            'width' => 35.21, 'height' => 16.16, 'fontFamily' => 'amiri', 'fontSize' => 4, 'bold' => false,
            'alignment' => 'center', 'color' => '#123456',
        ]]);
        $memberProof = $this->postJson('/invitations/creatives/field-preview', [
            'image' => $this->png(), 'fields' => $fields,
        ])->assertOk()->json('proof');

        $this->editor();
        $this->get('/dashboard/creative-templates')->assertOk()
            ->assertSee('data-admin-upload', false)
            ->assertSee('data-preview-url="/dashboard/creative-templates/field-preview"', false)
            ->assertSee('id="creativeFieldEditor"', false)
            ->assertSee('فتح محرّر المتغيّرات')
            ->assertDontSee('data-creative-design-mode', false);
        $preview = $this->postJson('/dashboard/creative-templates/field-preview', [
            'image' => $this->png(), 'fields' => $fields,
        ])->assertOk()->assertJsonMissingPath('image')->json();
        $this->assertStringStartsWith('data:image/png;base64,', $preview['design']);
        $this->assertDatabaseCount('creative_templates', 0);

        $payload = ['fields' => $fields, 'invitation_type' => 'greeting', 'name' => 'قالب من الإدارة'];
        $this->postJson('/dashboard/creative-templates', $payload + ['image' => $this->png(), 'proof' => $memberProof])
            ->assertUnprocessable()->assertJsonPath('code', 'CREATIVE_ARTWORK_PREVIEW_REQUIRED');
        $this->postJson('/dashboard/creative-templates', $payload + ['image' => $this->png(0xffffff), 'proof' => $preview['proof']])
            ->assertUnprocessable()->assertJsonPath('code', 'CREATIVE_ARTWORK_PREVIEW_REQUIRED');
        $id = $this->postJson('/dashboard/creative-templates', $payload + ['image' => $this->png(), 'proof' => $preview['proof']])
            ->assertCreated()->assertJsonPath('data.template.editable_text_fields.0.x', 51.44)->json('data.template.id');

        $template = CreativeTemplate::findOrFail($id);
        $this->assertSame(CreativeTemplate::STATUS_APPROVED, $template->status);
        $this->assertNull($template->uploaded_by);
        Storage::disk('public')->assertExists($template->editable_image_path);
        $this->get('/dashboard/creative-templates?status=approved&source=admin')
            ->assertOk()->assertSee('data-visual-preview-fields=', false)->assertSee('اسم المدعو');
        $this->identity($member);
        $this->getJson('/api/v1/invitations/creatives?editable_only=1&member_fields=1')
            ->assertOk()->assertJsonPath('data.templates.0.id', $id);
    }

    public function test_admin_upload_detects_svg_without_choosing_a_mode(): void
    {
        $this->editor();
        $redirect = $this->postJson('/dashboard/creative-templates', [
            'image' => $this->svg('<text x="100" y="80" font-family="Tajawal" font-size="28">النص</text>'),
            'invitation_type' => 'greeting',
        ])->assertCreated()->json('data.redirect_url');

        $template = CreativeTemplate::sole();
        $this->assertSame(CreativeTemplate::MODE_SVG, $template->designMode());
        $this->assertSame(CreativeTemplate::STATUS_PENDING, $template->status);
        $this->assertSame(route('dashboard.creatives.svg.edit', $template, absolute: false), $redirect);
    }

    public function test_mobile_member_fields_are_reviewed_in_dashboard_and_can_be_used_in_the_app(): void
    {
        $member = $this->identity();
        $fields = [[
            'id' => 1, 'label' => 'اسم المدعو', 'text' => 'أحمد', 'x' => 51.44, 'y' => 30.32,
            'width' => 35.21, 'height' => 16.16, 'fontFamily' => 'amiri', 'fontSize' => 4, 'bold' => false,
            'alignment' => 'center', 'color' => '#123456',
        ]];
        $form = [
            'fields' => json_encode($fields), 'invitation_type' => 'greeting',
            'publication_consent' => 1,
        ];
        $preview = $this->postJson('/api/v1/invitations/creatives/field-preview', [
            'image' => $this->png(), 'fields' => $form['fields'],
        ])->assertOk()->assertJsonMissingPath('data.image')->json('data');
        $this->assertStringStartsWith('data:image/png;base64,', $preview['design']);
        $this->postJson('/api/v1/invitations/creatives', $form + ['image' => $this->png()])
            ->assertUnprocessable()->assertJsonPath('code', 'CREATIVE_ARTWORK_PREVIEW_REQUIRED');
        $this->postJson('/api/v1/invitations/creatives', $form + ['image' => $this->png(0xffffff), 'proof' => $preview['proof']])
            ->assertUnprocessable()->assertJsonPath('code', 'CREATIVE_ARTWORK_PREVIEW_REQUIRED');

        $id = $this->postJson('/api/v1/invitations/creatives', $form + ['image' => $this->png(), 'proof' => $preview['proof']])
            ->assertCreated()->assertJsonPath('data.template.status', 'pending')->json('data.template.id');
        $this->getJson('/api/v1/invitations/creatives/mine?member_fields=1')->assertOk()
            ->assertJsonPath('data.templates.0.editable_text_fields.0.x', 51.44);
        $this->getJson('/api/v1/invitations/creatives?member_fields=1')->assertOk()->assertJsonCount(0, 'data.templates');

        $this->editor();
        $this->get('/dashboard/creative-templates')->assertOk()
            ->assertSee('data-visual-preview-fields=', false)->assertSee('51.44')->assertSee('اسم المدعو');
        $this->postJson("/dashboard/creative-templates/{$id}/approve")->assertOk();
        $recipient = $this->identity();
        $this->identity($member);
        $this->getJson('/api/v1/invitations/creatives?editable_only=1&member_fields=1')->assertOk()
            ->assertJsonPath('data.templates.0.id', $id)
            ->assertJsonPath('data.templates.0.editable_text_fields.0.default_text', 'أحمد');
        $this->getJson('/api/v1/invitations/creatives')->assertOk()->assertJsonCount(0, 'data.templates');

        $payload = [
            'creative_id' => $id, 'template_key' => 'greeting',
            'template_vars' => ['creative_text_1' => 'سارة'],
            'channels' => ['push'], 'recipient_type' => 'persons', 'person_ids' => [$recipient->id],
        ];
        $rendered = $this->postJson('/api/v1/invitations/preview-artwork', $payload)->assertOk()->json('data.artwork_image');
        $this->assertStringStartsWith('data:image/png;base64,', $rendered);
        $this->assertNotSame($preview['design'], $rendered);
        $this->postJson('/api/v1/invitations/send', $payload)->assertCreated();
        $sent = Notification::where('person_id', $member->id)->sole()->media_path;
        $this->assertSame($rendered, 'data:image/png;base64,'.base64_encode(Storage::disk('public')->get($sent)));
    }

    private function identity(?Person $person = null): Person
    {
        $person ??= Person::factory()->createQuietly(['birth_date' => '1990-01-01', 'death_date' => null]);
        $token = SiteMobileAccessToken::issueIdentityToken($person->id, 'whatsapp')['token'];
        $this->withToken($token)->withSession([
            'site_person_id' => $person->id, 'site_auth_method' => 'whatsapp', 'site_password_verified' => true,
        ]);

        return $person;
    }

    private function editor(): void
    {
        $admin = User::factory()->createQuietly();
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));
        $this->actingAs($admin);
    }

    private function upload(string $name, string $contents): void
    {
        $this->post('/invitations/creatives', [
            'image' => $this->svg($contents), 'name' => $name, 'invitation_type' => 'greeting', 'publication_consent' => 1,
        ])->assertRedirect('/invitations/creatives')->assertSessionHasNoErrors();
    }

    private function svg(string $contents): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('design.svg',
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 420">'.$contents.'</svg>');
    }

    private function png(int $color = 0xffe5c8, int $width = 800): UploadedFile
    {
        $image = imagecreatetruecolor($width, 420);
        imagefill($image, 0, 0, $color);
        ob_start();
        imagepng($image);

        return UploadedFile::fake()->createWithContent('design.png', ob_get_clean());
    }

    private function rasterDesign(): UploadedFile
    {
        $image = imagecreatetruecolor(1300, 840);
        imagefill($image, 0, 0, 0xffe5c8);
        imagefilledrectangle($image, 25, 100, 60, 122, 0x993355);
        imagefilledrectangle($image, 150, 419, 180, 435, 0x443455);
        imagefilledrectangle($image, 900, 754, 984, 767, 0x12624c);
        ob_start();
        imagepng($image);

        return UploadedFile::fake()->createWithContent('design.png', ob_get_clean());
    }
}
