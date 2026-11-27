<?php

namespace Tests\Feature\Invitations;

use App\Jobs\IndexPersonEmbeddingJob;
use App\Jobs\SendExpoPushNotificationJob;
use App\Jobs\SendWhatsAppInvitationJob;
use App\Models\Notification;
use App\Models\NotificationGroup;
use App\Models\Person;
use App\Models\PersonContactAccount;
use App\Models\UserDevice;
use App\Services\ExpoNotificationService;
use App\Services\SiteMobileAccessToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class InvitationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['site.password_protection_enabled' => false]);
        Bus::fake([IndexPersonEmbeddingJob::class]);
    }

    public function test_pin_token_is_rejected(): void
    {
        $pin = SiteMobileAccessToken::issue()['token'];

        $this->withHeader('Authorization', 'Bearer '.$pin)
            ->getJson('/api/v1/invitations/groups')
            ->assertStatus(403)
            ->assertJsonPath('code', 'IDENTITY_REQUIRED_FOR_INVITATIONS');
    }

    public function test_identity_can_crud_group_and_reject_person_without_whatsapp(): void
    {
        $me = Person::factory()->create(['gender' => 'male', 'death_date' => null]);
        $withWa = Person::factory()->create(['first_name' => 'فهد', 'gender' => 'male', 'death_date' => null]);
        $noWa = Person::factory()->create(['first_name' => 'بدون', 'gender' => 'male', 'death_date' => null]);
        $this->giveWhatsapp($withWa);

        $headers = $this->identityHeaders($me);

        $create = $this->withHeaders($headers)
            ->postJson('/api/v1/invitations/groups', [
                'name' => 'مجموعة فهد',
                'description' => 'وصف',
                'person_ids' => [$withWa->id],
            ])
            ->assertCreated()
            ->assertJsonPath('success', true);

        $groupId = $create->json('data.group.id');

        $this->withHeaders($headers)
            ->postJson("/api/v1/invitations/groups/{$groupId}/persons", [
                'person_id' => $noWa->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'PERSON_HAS_NO_WHATSAPP');

        $this->withHeaders($headers)
            ->getJson('/api/v1/invitations/groups')
            ->assertOk()
            ->assertJsonPath('data.groups.0.name', 'مجموعة فهد');
    }

    public function test_tree_picker_marks_parent_without_whatsapp_and_counts_child(): void
    {
        $me = Person::factory()->create(['gender' => 'male', 'from_outside_the_family' => false, 'death_date' => null]);
        $father = Person::factory()->create([
            'first_name' => 'الأب',
            'gender' => 'male',
            'parent_id' => null,
            'from_outside_the_family' => false,
            'death_date' => null,
        ]);
        $son = Person::factory()->create([
            'first_name' => 'الابن',
            'gender' => 'male',
            'parent_id' => $father->id,
            'from_outside_the_family' => false,
            'death_date' => null,
        ]);
        $this->giveWhatsapp($son);

        $tree = $this->withHeaders($this->identityHeaders($me))
            ->getJson('/api/v1/invitations/tree-picker')
            ->assertOk()
            ->json('data.tree');

        $fatherNode = $this->findNode($tree, $father->id);
        $this->assertNotNull($fatherNode);
        $this->assertFalse($fatherNode['has_whatsapp']);
        $this->assertFalse($fatherNode['selectable']);
        $this->assertGreaterThan(0, $fatherNode['whatsapp_descendant_count']);
        $this->assertTrue($fatherNode['expandable']);

        $sonNode = $this->findNode($fatherNode['children'], $son->id);
        $this->assertTrue($sonNode['has_whatsapp']);
        $this->assertTrue($sonNode['selectable']);
    }

    public function test_persons_search_finds_patronymic_full_name(): void
    {
        $me = Person::factory()->create(['gender' => 'male', 'death_date' => null]);
        $father = Person::factory()->create([
            'first_name' => 'ناصر',
            'gender' => 'male',
            'parent_id' => null,
            'from_outside_the_family' => false,
            'death_date' => null,
        ]);
        $son = Person::factory()->create([
            'first_name' => 'ممدوح',
            'gender' => 'male',
            'parent_id' => $father->id,
            'from_outside_the_family' => false,
            'death_date' => null,
        ]);
        $this->giveWhatsapp($son);

        $persons = $this->withHeaders($this->identityHeaders($me))
            ->getJson('/api/v1/invitations/persons?q='.urlencode('ممدوح بن ناصر'))
            ->assertOk()
            ->json('data.persons');

        $ids = collect($persons)->pluck('id')->all();
        $this->assertContains($son->id, $ids);

        $match = collect($persons)->firstWhere('id', $son->id);
        $this->assertStringContainsString('ممدوح', $match['full_name']);
        $this->assertStringContainsString('ناصر', $match['full_name']);
        $this->assertTrue($match['selectable']);
        $this->assertNotEmpty($match['photo_url']);
        $this->assertStringContainsString('http', $match['photo_url']);
    }

    public function test_persons_search_returns_full_avatar_url(): void
    {
        $me = Person::factory()->create(['gender' => 'male', 'death_date' => null]);
        $target = Person::factory()->create([
            'first_name' => 'سعود',
            'gender' => 'male',
            'photo_url' => 'persons/saud.jpg',
            'death_date' => null,
        ]);
        $this->giveWhatsapp($target);

        $persons = $this->withHeaders($this->identityHeaders($me))
            ->getJson('/api/v1/invitations/persons?q='.urlencode('سعود'))
            ->assertOk()
            ->json('data.persons');

        $match = collect($persons)->firstWhere('id', $target->id);
        $this->assertNotNull($match);
        $this->assertStringContainsString('http', $match['photo_url']);
        $this->assertStringContainsString('storage/', $match['photo_url']);
        $this->assertStringContainsString('persons/saud.jpg', $match['photo_url']);
    }

    public function test_send_queues_whatsapp_job(): void
    {
        Queue::fake();
        config(['invitations.delivery_enabled' => true]);

        $me = Person::factory()->create(['gender' => 'male', 'death_date' => null]);
        $target = Person::factory()->create(['gender' => 'male', 'death_date' => null]);
        $this->giveWhatsapp($me);
        $this->giveWhatsapp($target);

        $group = NotificationGroup::create([
            'name' => 'مجموعة',
            'person_id' => $me->id,
        ]);
        $group->persons()->attach($me->id, ['sort_order' => 1]);
        $group->persons()->attach($target->id, ['sort_order' => 2]);

        $this->withHeaders($this->identityHeaders($me))
            ->postJson('/api/v1/invitations/send', [
                'channels' => ['whatsapp'],
                'recipient_type' => 'group',
                'group_id' => $group->id,
                'body' => 'مرحبا {name}',
            ])
            ->assertCreated();

        Queue::assertPushed(SendWhatsAppInvitationJob::class, 1);
        $this->assertSame([$target->id], Notification::sole()->recipients()->pluck('person_id')->all());
    }

    public function test_member_cannot_send_an_invitation_to_their_own_account_or_number(): void
    {
        Queue::fake();
        $sender = Person::factory()->create(['birth_date' => '1990-01-01', 'death_date' => null]);
        PersonContactAccount::create(['person_id' => $sender->id, 'type' => 'whatsapp', 'value' => '050 000 0142']);
        $this->withHeaders($this->identityHeaders($sender));

        $this->postJson('/api/v1/invitations/send', [
            'channels' => ['push'], 'recipient_type' => 'persons',
            'person_ids' => [$sender->id], 'body' => 'مرحبا',
        ])->assertUnprocessable()->assertJsonPath('code', 'SELF_INVITATION');

        $this->postJson('/api/v1/invitations/send', [
            'channels' => ['whatsapp'], 'recipient_type' => 'external', 'external_name' => 'العضو',
            'external_phone' => '500000142', 'external_country_code' => '+966', 'body' => 'مرحبا',
        ])->assertUnprocessable()->assertJsonPath('code', 'SELF_INVITATION');

        $this->assertDatabaseCount('notifications', 0);
        Queue::assertNothingPushed();
    }

    public function test_web_send_picker_and_group_preview_exclude_the_sender(): void
    {
        $adult = ['birth_date' => '1990-01-01', 'death_date' => null];
        $sender = Person::factory()->create($adult + ['first_name' => 'معاذ']);
        $recipient = Person::factory()->create($adult + ['first_name' => 'ياسر', 'parent_id' => $sender->id]);
        $this->giveWhatsapp($sender);
        $this->giveWhatsapp($recipient);
        $group = NotificationGroup::create(['name' => 'مجموعة مختلطة', 'person_id' => $sender->id]);
        $group->persons()->attach([$sender->id => ['sort_order' => 1], $recipient->id => ['sort_order' => 2]]);

        $this->withHeaders($this->identityHeaders($sender))->withSession([
            'site_person_id' => $sender->id, 'site_auth_method' => 'whatsapp', 'site_password_verified' => true,
        ]);
        $this->get('/invitations/send')->assertOk()
            ->assertSee('data-excluded-person-id="'.$sender->id.'"', false)
            ->assertSee('مجموعة مختلطة (1 شخص)');
        $search = '/invitations/persons-search?q='.urlencode('معاذ');
        $this->getJson($search)->assertOk()->assertJsonFragment(['id' => $sender->id]);
        $this->getJson($search.'&exclude_sender=1')->assertOk()->assertJsonMissing(['id' => $sender->id]);

        $response = $this->postJson('/invitations/preview-message', [
            'recipient_type' => 'group', 'group_id' => $group->id, 'body' => 'للمستلم {first_name}',
        ])->assertOk();
        $this->assertStringContainsString('للمستلم ياسر', $response->json('preview'));
    }

    public function test_cannot_send_from_someone_elses_group(): void
    {
        $me = Person::factory()->create(['gender' => 'male', 'death_date' => null]);
        $other = Person::factory()->create(['gender' => 'male', 'death_date' => null]);
        $group = NotificationGroup::create([
            'name' => 'ليست لي',
            'person_id' => $other->id,
        ]);

        $this->withHeaders($this->identityHeaders($me))
            ->postJson('/api/v1/invitations/send', [
                'channels' => ['whatsapp'],
                'recipient_type' => 'group',
                'group_id' => $group->id,
                'body' => 'مرحبا',
            ])
            ->assertStatus(403)
            ->assertJsonPath('code', 'GROUP_NOT_OWNED');
    }

    public function test_invitation_history_separates_directions_and_protects_recipient_details(): void
    {
        Queue::fake();

        $adult = ['birth_date' => '1990-01-01', 'death_date' => null];
        $sender = Person::factory()->create($adult + ['first_name' => 'فهد', 'last_name' => 'التجريبي']);
        $recipient = Person::factory()->create($adult);
        $other = Person::factory()->create($adult);
        $sent = Notification::create([
            'person_id' => $sender->id,
            'title' => 'لقاء العائلة',
            'body' => 'أهلاً بكم',
            'status' => 'sent',
            'targeting_filters' => [
                'font_family' => 'amiri',
                'font_weight' => 'regular',
                'external_recipients' => [['phone' => '+12025550199']],
            ],
        ]);
        $sent->recipients()->create(['person_id' => $recipient->id]);
        $sent->recipients()->create(['recipient_name' => 'ضيف آخر', 'recipient_phone' => '+12025550199']);
        $pending = Notification::create(['person_id' => $sender->id, 'status' => 'pending_approval']);
        $pending->recipients()->create(['person_id' => $recipient->id]);
        $generic = Notification::create(['status' => 'sent']);
        $generic->recipients()->create(['person_id' => $recipient->id]);

        $this->withHeaders($this->identityHeaders($sender))
            ->getJson('/api/v1/invitations/logs')
            ->assertOk()
            ->assertJsonCount(2, 'data.notifications.data');
        $this->getJson("/api/v1/invitations/logs/{$sent->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data.notification.recipients');

        $this->flushSession()->withHeaders($this->identityHeaders($recipient))
            ->getJson('/api/v1/invitations/logs')
            ->assertOk()
            ->assertJsonCount(0, 'data.notifications.data');

        $this->getJson('/api/v1/invitations/logs?direction=incoming')
            ->assertOk()
            ->assertJsonCount(1, 'data.notifications.data')
            ->assertJsonPath('data.notifications.data.0.id', $sent->id)
            ->assertJsonPath('data.notifications.data.0.sender_name', 'فهد التجريبي')
            ->assertJsonPath('data.notifications.data.0.font_family', 'amiri')
            ->assertJsonPath('data.notifications.data.0.font_weight', 'regular')
            ->assertJsonMissingPath('data.notifications.data.0.recipients')
            ->assertJsonMissingPath('data.notifications.data.0.targeting_filters');

        $this->getJson("/api/v1/invitations/logs/{$sent->id}?direction=incoming")
            ->assertOk()
            ->assertJsonPath('data.notification.body', 'أهلاً بكم')
            ->assertJsonPath('data.notification.font_family', 'amiri')
            ->assertJsonPath('data.notification.font_weight', 'regular')
            ->assertJsonMissingPath('data.notification.recipients')
            ->assertJsonMissingPath('data.notification.targeting_filters');
        $this->getJson("/api/v1/invitations/logs/{$pending->id}?direction=incoming")->assertForbidden();
        $this->getJson("/api/v1/invitations/logs/{$sent->id}?direction=outgoing")->assertForbidden();

        $this->flushSession()->withHeaders($this->identityHeaders($other))
            ->getJson("/api/v1/invitations/logs/{$sent->id}?direction=incoming")
            ->assertForbidden();
    }

    public function test_recipient_can_save_own_response_once_and_notify_the_sender_in_app(): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        config(['invitations.delivery_enabled' => false]);

        $adult = ['birth_date' => '1990-01-01', 'death_date' => null];
        $sender = Person::factory()->createQuietly($adult);
        $recipient = Person::factory()->createQuietly($adult);
        $otherRecipient = Person::factory()->createQuietly($adult);
        $invitation = Notification::create([
            'person_id' => $sender->id,
            'title' => 'لقاء العائلة',
            'status' => 'sent',
            'channels' => ['whatsapp'],
        ]);
        $ownRow = $invitation->recipients()->create(['person_id' => $recipient->id]);
        $otherRow = $invitation->recipients()->create(['person_id' => $otherRecipient->id]);
        $pending = Notification::create(['person_id' => $sender->id, 'status' => 'pending_approval']);
        $pending->recipients()->create(['person_id' => $recipient->id]);

        $logUrl = "/api/v1/invitations/logs/{$invitation->id}";
        $this->withHeaders($this->identityHeaders($sender))
            ->putJson("{$logUrl}/response", ['response' => 'accepted'])->assertForbidden();

        $this->flushSession()->withHeaders($this->identityHeaders($recipient));
        $this->getJson("{$logUrl}?direction=incoming")
            ->assertOk()->assertJsonPath('data.notification.invitation_response', null);
        $this->putJson("/api/v1/invitations/logs/{$pending->id}/response", ['response' => 'accepted'])
            ->assertForbidden();
        $this->putJson("{$logUrl}/response", ['response' => 'invalid'])->assertUnprocessable();
        $this->putJson("{$logUrl}/response", ['response' => 'accepted', 'person_id' => $otherRecipient->id])
            ->assertOk()->assertJsonPath('data.invitation_response', 'accepted');
        $this->putJson("{$logUrl}/response", ['response' => 'accepted'])->assertOk();
        $this->putJson("{$logUrl}/response", ['response' => 'declined'])
            ->assertConflict()->assertJsonPath('code', 'INVITATION_RESPONSE_ALREADY_RECORDED');

        $this->assertSame('accepted', $ownRow->fresh()->invitation_response);
        $this->assertNull($otherRow->fresh()->invitation_response);
        $this->getJson('/api/v1/invitations/logs?direction=incoming')
            ->assertOk()->assertJsonPath('data.notifications.data.0.invitation_response', 'accepted')
            ->assertJsonMissingPath('data.notifications.data.0.recipients');

        $this->flushSession()->withHeaders($this->identityHeaders($otherRecipient));
        $this->putJson("{$logUrl}/response", ['response' => 'declined'])->assertOk();
        $this->putJson("{$logUrl}/response", ['response' => 'accepted'])->assertConflict();
        $this->getJson('/api/notifications')->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.id', $invitation->id);

        $this->flushSession()->withHeaders($this->identityHeaders($sender))
            ->getJson("{$logUrl}?direction=outgoing")
            ->assertOk()
            ->assertJsonFragment(['id' => $ownRow->id, 'invitation_response' => 'accepted'])
            ->assertJsonFragment(['id' => $otherRow->id, 'invitation_response' => 'declined']);

        $notices = $this->getJson('/api/notifications')->assertOk()
            ->assertJsonCount(2, 'data.data')
            ->json('data.data');
        $byTitle = collect($notices)->keyBy('title');
        $this->assertSame("تأكيد الحضور من {$recipient->full_name} لدعوتك «لقاء العائلة».", $byTitle['قبول الدعوة']['body']);
        $this->assertSame("اعتذار {$otherRecipient->full_name} عن حضور دعوتك «لقاء العائلة».", $byTitle['رفض الدعوة']['body']);
        foreach ($notices as $notice) {
            $this->assertNull($notice['invitation']);
            $this->assertFalse($notice['read']);
        }
        $this->getJson('/api/notifications/unread-count')->assertOk()->assertJsonPath('data.unread_count', 2);
        $this->getJson('/api/v1/invitations/logs?direction=outgoing')->assertOk()
            ->assertJsonCount(2, 'data.notifications.data');

        $sender->deleteQuietly();
        $this->flushSession()->flushHeaders()
            ->getJson('/api/notifications')->assertOk()->assertJsonCount(0, 'data.data');

        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    public function test_acceptance_and_rejection_push_only_to_the_invitation_sender(): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        config(['invitations.delivery_enabled' => true]);

        $adult = ['birth_date' => '1990-01-01', 'death_date' => null];
        $sender = Person::factory()->createQuietly($adult);
        $recipient = Person::factory()->createQuietly($adult);
        $senderDevice = UserDevice::create(['person_id' => $sender->id, 'expo_push_token' => 'ExponentPushToken[inviter]']);
        UserDevice::create(['person_id' => $recipient->id, 'expo_push_token' => 'ExponentPushToken[invitee]']);

        $this->withHeaders($this->identityHeaders($recipient));
        foreach (['accepted' => 'whatsapp', 'declined' => 'push'] as $response => $channel) {
            $invitation = Notification::create([
                'person_id' => $sender->id, 'title' => 'لقاء العائلة', 'status' => 'sending', 'channels' => [$channel],
            ]);
            $invitation->recipients()->create(['person_id' => $recipient->id]);
            $url = "/api/v1/invitations/logs/{$invitation->id}/response";
            $this->putJson($url, ['response' => $response])->assertOk();
            $this->putJson($url, ['response' => $response])->assertOk();
        }

        Queue::assertNotPushed(SendWhatsAppInvitationJob::class);
        Queue::assertPushed(SendExpoPushNotificationJob::class, 2);
        $jobs = Queue::pushed(SendExpoPushNotificationJob::class)->keyBy('title');
        $expo = $this->mock(ExpoNotificationService::class);
        foreach (['قبول الدعوة', 'رفض الدعوة'] as $title) {
            $job = $jobs[$title];
            $notice = Notification::findOrFail($job->notificationId);
            $this->assertSame($sender->id, $job->personId);
            $this->assertSame($senderDevice->expo_push_token, $job->expoPushToken);
            $this->assertTrue($job->isSenderNotice);
            $this->assertTrue($job->afterCommit);
            $this->assertSame($sender->id, $notice->recipients()->sole()->person_id);
            $expo->shouldReceive('send')->once()
                ->with($senderDevice->expo_push_token, $title, $notice->body, [
                    'type' => Notification::INVITATION_RESPONSE_TYPE,
                    'notification_id' => $notice->id,
                ])->andReturn(['success' => true, 'sent_count' => 1]);
            $job->handle($expo);
        }

        config(['invitations.delivery_enabled' => false]);
        $jobs['قبول الدعوة']->handle($expo);
        config(['invitations.delivery_enabled' => true]);
        $senderDevice->update(['person_id' => $recipient->id]);
        $jobs['رفض الدعوة']->handle($expo);

        $this->flushSession()->withHeaders($this->identityHeaders($sender))
            ->getJson('/api/notifications')->assertOk()->assertJsonCount(2, 'data.data');
        Http::assertNothingSent();
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return array<string, mixed>|null
     */
    private function findNode(array $nodes, int $id): ?array
    {
        foreach ($nodes as $node) {
            if ((int) $node['id'] === $id) {
                return $node;
            }
            $found = $this->findNode($node['children'] ?? [], $id);
            if ($found) {
                return $found;
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function identityHeaders(Person $person): array
    {
        $token = SiteMobileAccessToken::issueIdentityToken($person->id, 'whatsapp')['token'];

        return [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ];
    }

    private function giveWhatsapp(Person $person): void
    {
        PersonContactAccount::create([
            'person_id' => $person->id,
            'type' => 'whatsapp',
            'value' => '9665'.str_pad((string) $person->id, 8, '0', STR_PAD_LEFT),
        ]);
    }
}
