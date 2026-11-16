<?php

namespace Tests\Feature\Invitations;

use App\Jobs\SendWhatsAppInvitationJob;
use App\Models\Notification;
use App\Models\NotificationGroup;
use App\Models\Person;
use App\Models\PersonContactAccount;
use App\Services\SiteMobileAccessToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class InvitationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['site.password_protection_enabled' => false]);
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
        $this->giveWhatsapp($target);

        $group = NotificationGroup::create([
            'name' => 'مجموعة',
            'person_id' => $me->id,
        ]);
        $group->persons()->attach($target->id, ['sort_order' => 1]);

        $this->withHeaders($this->identityHeaders($me))
            ->postJson('/api/v1/invitations/send', [
                'channels' => ['whatsapp'],
                'recipient_type' => 'group',
                'group_id' => $group->id,
                'body' => 'مرحبا {name}',
            ])
            ->assertCreated();

        Queue::assertPushed(SendWhatsAppInvitationJob::class);
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
            'targeting_filters' => ['external_recipients' => [['phone' => '+12025550199']]],
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
            ->assertJsonMissingPath('data.notifications.data.0.recipients')
            ->assertJsonMissingPath('data.notifications.data.0.targeting_filters');

        $this->getJson("/api/v1/invitations/logs/{$sent->id}?direction=incoming")
            ->assertOk()
            ->assertJsonPath('data.notification.body', 'أهلاً بكم')
            ->assertJsonMissingPath('data.notification.recipients')
            ->assertJsonMissingPath('data.notification.targeting_filters');
        $this->getJson("/api/v1/invitations/logs/{$pending->id}?direction=incoming")->assertForbidden();
        $this->getJson("/api/v1/invitations/logs/{$sent->id}?direction=outgoing")->assertForbidden();

        $this->flushSession()->withHeaders($this->identityHeaders($other))
            ->getJson("/api/v1/invitations/logs/{$sent->id}?direction=incoming")
            ->assertForbidden();
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
