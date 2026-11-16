<?php

namespace Tests\Feature\Dashboard;

use App\Models\Notification;
use App\Models\NotificationGroup;
use App\Models\Person;
use App\Models\PersonContactAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class NotificationPendingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_page_renders_with_detailed_recipients(): void
    {
        $this->withoutExceptionHandling();
        \Illuminate\Support\Facades\Queue::fake();
        config(['site.password_protection_enabled' => false]);

        $viewPerm = Permission::firstOrCreate(['name' => 'notifications.view', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->givePermissionTo([$viewPerm]);

        $sender = Person::create([
            'first_name' => 'سلطان',
            'last_name' => 'السريّع',
            'gender' => 'male',
        ]);

        $r1 = Person::create(['first_name' => 'فهد', 'last_name' => 'السريّع', 'gender' => 'male']);
        PersonContactAccount::create([
            'person_id' => $r1->id,
            'type' => 'whatsapp',
            'value' => '966501234567',
        ]);
        $r2 = Person::create(['first_name' => 'نورة', 'last_name' => 'السريّع', 'gender' => 'female']);

        $group = NotificationGroup::create(['name' => 'مجموعة الاختبار', 'user_id' => $admin->id]);

        // دعوة أفراد
        $n1 = Notification::create([
            'user_id' => null,
            'person_id' => $sender->id,
            'title' => 'دعوة زواج',
            'body' => 'حياكم الله يا {name}',
            'channels' => ['whatsapp', 'push'],
            'targeting_filters' => ['recipient_type' => 'persons', 'exclude_deceased' => true],
            'status' => 'pending_approval',
        ]);
        $n1->recipients()->create(['person_id' => $r1->id]);
        $n1->recipients()->create(['person_id' => $r2->id]);

        // دعوة مجموعة
        $n2 = Notification::create([
            'user_id' => $admin->id,
            'person_id' => null,
            'title' => 'دعوة مجموعة',
            'body' => 'اجتماع العائلة',
            'channels' => ['push'],
            'targeting_filters' => ['recipient_type' => 'group', 'group_id' => $group->id],
            'status' => 'pending_approval',
        ]);
        $n2->recipients()->create(['person_id' => $r1->id]);

        // دعوة خارجية
        $n3 = Notification::create([
            'user_id' => null,
            'person_id' => $sender->id,
            'title' => 'دعوة ضيف',
            'body' => 'أهلاً {name}',
            'channels' => ['whatsapp'],
            'targeting_filters' => ['recipient_type' => 'external', 'external_recipients' => []],
            'status' => 'pending_approval',
        ]);
        $n3->recipients()->create([
            'person_id' => null,
            'recipient_name' => 'أبو فهد الشمري',
            'recipient_phone' => '966501234567',
        ]);

        $response = $this->actingAs($admin)->get(route('dashboard.notifications.pending'));

        $response->assertOk();
        $response->assertSee('الرسالة رايحة لمين');
        $response->assertSee('مجموعة الاختبار');
        $response->assertSee('أبو فهد الشمري');
    }
}
