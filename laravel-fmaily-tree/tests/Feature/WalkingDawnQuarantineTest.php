<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\WalkingRecord;
use App\Services\AI\EmbeddingsService;
use App\Services\SiteMobileAccessToken;
use App\Services\Walking\WalkingLeaderboardEligibilityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WalkingDawnQuarantineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        config(['site.password_protection_enabled' => false]);

        $this->mock(EmbeddingsService::class, function ($mock) {
            $mock->shouldIgnoreMissing();
        });

        app(WalkingLeaderboardEligibilityService::class)->clearCache();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function identityHeaders(Person $person): array
    {
        $issued = SiteMobileAccessToken::issue(1440, [
            'person_id'   => $person->id,
            'auth_method' => 'face',
        ]);

        return [
            'X-Site-Access-Token' => $issued['token'],
            'Accept'              => 'application/json',
        ];
    }

    protected function makePerson(string $name = 'Walker'): Person
    {
        return Person::factory()->create([
            'gender'                  => 'male',
            'from_outside_the_family' => false,
            'first_name'              => $name,
        ]);
    }

    /**
     * 1. sync يرفض تاريخ الغد (ثغرة addDay مغلقة).
     */
    public function test_sync_rejects_tomorrow_date(): void
    {
        $person = $this->makePerson();
        $tomorrow = Carbon::today('Asia/Riyadh')->addDay()->toDateString();

        $response = $this->withHeaders($this->identityHeaders($person))
            ->postJson('/api/v1/walking/sync', [
                'date'  => $tomorrow,
                'steps' => 11000,
            ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('walking_records', [
            'person_id' => $person->id,
            'date'      => $tomorrow,
        ]);
    }

    /**
     * 2. حماية الفجر: رقم مستحيل فيزيائياً بعد منتصف الليل مباشرة (11,000 خطوة بعد 10 دقائق) يُرفض ولا يُخزن.
     */
    public function test_dawn_quarantine_rejects_impossible_steps_after_midnight(): void
    {
        $person = $this->makePerson();
        $today = Carbon::today('Asia/Riyadh');
        Carbon::setTestNow($today->copy()->setTime(0, 10));

        $response = $this->withHeaders($this->identityHeaders($person))
            ->postJson('/api/v1/walking/sync', [
                'date'   => $today->toDateString(),
                'steps'  => 11000,
                'source' => 'health_connect',
            ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('walking_records', [
            'person_id' => $person->id,
            'date'      => $today->toDateString(),
        ]);
    }

    /**
     * 2b. التحقق من قبول خطوات المشي الواقعية فجراً (مثل 1,900 خطوة الساعة 01:37 صباحاً بتوقيت السعودية).
     */
    public function test_dawn_quarantine_accepts_realistic_steps_at_1_37_am(): void
    {
        $person = $this->makePerson();
        $today = Carbon::today('Asia/Riyadh');
        Carbon::setTestNow($today->copy()->setTime(1, 37));

        $response = $this->withHeaders($this->identityHeaders($person))
            ->postJson('/api/v1/walking/sync', [
                'date'   => $today->toDateString(),
                'steps'  => 1900,
                'source' => 'apple_health',
            ]);

        $response->assertOk();
        $record = WalkingRecord::where('person_id', $person->id)->whereDate('date', $today->toDateString())->first();
        $this->assertNotNull($record);
        $this->assertEquals(1900, (int) $record->steps);
    }

    /**
     * 3. رقم صغير فجراً مقبول، والرقم الكبير يُقبل بعد 6 صباحاً (تعافٍ تلقائي).
     */
    public function test_small_dawn_sync_accepted_and_big_sync_accepted_after_6am(): void
    {
        $person = $this->makePerson();
        $today = Carbon::today('Asia/Riyadh');

        Carbon::setTestNow($today->copy()->setTime(4, 13));
        $this->withHeaders($this->identityHeaders($person))
            ->postJson('/api/v1/walking/sync', [
                'date'  => $today->toDateString(),
                'steps' => 300,
            ])->assertOk();

        Carbon::setTestNow($today->copy()->setTime(7, 30));
        $this->withHeaders($this->identityHeaders($person))
            ->postJson('/api/v1/walking/sync', [
                'date'  => $today->toDateString(),
                'steps' => 11000,
            ])->assertOk();

        $stored = WalkingRecord::where('person_id', $person->id)
            ->whereDate('date', $today->toDateString())
            ->first();
        $this->assertNotNull($stored);
        $this->assertEquals(11000, (int) $stored->steps);
    }

    /**
     * 4. سباق اليوم لا يسرب خطوات الأمس: من له 11 ألف أمس فقط لا يظهر في سباق اليوم.
     */
    public function test_today_leaderboard_does_not_leak_yesterday_steps(): void
    {
        $person = $this->makePerson();
        $today = Carbon::today('Asia/Riyadh');
        $yesterday = $today->copy()->subDay()->toDateString();

        WalkingRecord::create([
            'person_id' => $person->id,
            'date'      => $yesterday,
            'steps'     => 11000,
        ]);

        $response = $this->withHeaders($this->identityHeaders($person))
            ->getJson('/api/v1/walking/leaderboard?period=today');

        $response->assertOk();
        $topIds = collect($response->json('data.top'))->pluck('person_id')->all();
        $this->assertNotContains($person->id, $topIds);
    }

    /**
     * 5. تاريخ عميل خارج النافذة يُتجاهل لصالح اليوم السعودي.
     */
    public function test_out_of_window_client_date_falls_back_to_ksa_today(): void
    {
        $person = $this->makePerson();

        $response = $this->withHeaders($this->identityHeaders($person))
            ->getJson('/api/v1/walking/leaderboard?period=today&date=2000-01-01&timezone=Asia%2FRiyadh');

        $response->assertOk();
        $this->assertEquals(
            Carbon::today('Asia/Riyadh')->toDateString(),
            Carbon::parse($response->json('data.date_range.from'))->toDateString()
        );
    }

    /**
     * 6. أمر المراجعة اليومي يعمل ويرصد السجلات المشبوهة (قراءة فقط).
     */
    public function test_rollover_verify_command_runs_and_flags_suspicious_records(): void
    {
        $person = $this->makePerson();
        $today = Carbon::today('Asia/Riyadh');

        $record = WalkingRecord::create([
            'person_id' => $person->id,
            'date'      => $today->toDateString(),
            'steps'     => 11000,
            'source'    => 'manual',
        ]);
        WalkingRecord::where('id', $record->id)
            ->update(['created_at' => $today->copy()->setTime(2, 0)]);

        $this->artisan('walking:rollover-verify')->assertSuccessful();

        // قراءة فقط — السجل موجود كما هو
        $this->assertDatabaseHas('walking_records', ['id' => $record->id, 'steps' => 11000]);
    }

    /**
     * 7. التحقق من قبول خطوات تتجاوز السقف القديم (65,000 خطوة) دون رفض أو تصفير.
     */
    public function test_sync_accepts_steps_exceeding_previous_65k_limit(): void
    {
        $person = $this->makePerson();
        $today = Carbon::today('Asia/Riyadh');
        Carbon::setTestNow($today->copy()->setTime(17, 0)); // 5:00 PM KSA

        $response = $this->withHeaders($this->identityHeaders($person))
            ->postJson('/api/v1/walking/sync', [
                'date'        => $today->toDateString(),
                'steps'       => 78500,
                'distance_km' => 59.6,
                'source'      => 'apple_health',
            ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'data'    => [
                'steps' => 78500,
            ],
        ]);

        $stored = WalkingRecord::where('person_id', $person->id)
            ->whereDate('date', $today->toDateString())
            ->first();
        $this->assertNotNull($stored);
        $this->assertEquals(78500, (int) $stored->steps);

        // التأكد من أن جلب الإحصائيات الشخصية لا يحذف السجل
        $statsResponse = $this->withHeaders($this->identityHeaders($person))
            ->getJson('/api/v1/walking/my-stats');

        $statsResponse->assertOk();
        $this->assertEquals(78500, $statsResponse->json('data.today_steps'));
    }

    /**
     * 9. تسرب رقم أمس فجراً يُشفى تلقائياً بأول قراءة موثوقة أصغر (بدون مسح يدوي)،
     *    بينما القراءة غير الموثوقة لا تُنزّل الرقم — والعداء الصاعد لا يتأثر.
     */
    public function test_authoritative_dawn_downward_correction_heals_carryover(): void
    {
        $person = $this->makePerson();
        $yesterday = Carbon::today('Asia/Riyadh')->subDay()->toDateString();
        $today = Carbon::today('Asia/Riyadh');

        WalkingRecord::create([
            'person_id' => $person->id,
            'date'      => $yesterday,
            'steps'     => 13513,
            'source'    => 'apple_health',
        ]);

        // أول مزامنة فجراً برقم أمس المتسرب — تُقبل وتُخزن (كانت تلتصق للأبد قبل الفيكس)
        Carbon::setTestNow($today->copy()->setTime(1, 44));
        $this->withHeaders($this->identityHeaders($person))
            ->postJson('/api/v1/walking/sync', [
                'date'             => $today->toDateString(),
                'steps'            => 13513,
                'source'           => 'apple_health',
                'is_authoritative' => true,
            ])->assertOk();

        $this->assertEquals(
            13513,
            (int) WalkingRecord::where('person_id', $person->id)->whereDate('date', $today->toDateString())->value('steps')
        );

        // قراءة غير موثوقة أصغر لا تُنزّل الرقم
        Carbon::setTestNow($today->copy()->setTime(1, 45));
        $this->withHeaders($this->identityHeaders($person))
            ->postJson('/api/v1/walking/sync', [
                'date'   => $today->toDateString(),
                'steps'  => 100,
                'source' => 'device',
            ])->assertOk();

        $this->assertEquals(
            13513,
            (int) WalkingRecord::where('person_id', $person->id)->whereDate('date', $today->toDateString())->value('steps')
        );

        // قراءة موثوقة أصغر تُصحح الرقم تلقائياً
        $this->withHeaders($this->identityHeaders($person))
            ->postJson('/api/v1/walking/sync', [
                'date'             => $today->toDateString(),
                'steps'            => 100,
                'source'           => 'apple_health',
                'is_authoritative' => true,
            ])->assertOk();

        $this->assertEquals(
            100,
            (int) WalkingRecord::where('person_id', $person->id)->whereDate('date', $today->toDateString())->value('steps')
        );

        // العداء الصاعد بعدها طبيعي: 100 -> 8500
        Carbon::setTestNow($today->copy()->setTime(2, 30));
        $this->withHeaders($this->identityHeaders($person))
            ->postJson('/api/v1/walking/sync', [
                'date'             => $today->toDateString(),
                'steps'            => 8500,
                'source'           => 'apple_health',
                'is_authoritative' => true,
            ])->assertOk();

        $this->assertEquals(
            8500,
            (int) WalkingRecord::where('person_id', $person->id)->whereDate('date', $today->toDateString())->value('steps')
        );
    }

    /**
     * 8. التحقق من قبول المزامنة المجمعة (batch-sync) لخطوات تفوق 65 ألف.
     */
    public function test_batch_sync_accepts_steps_over_65k(): void
    {
        $person = $this->makePerson();
        $yesterday = Carbon::today('Asia/Riyadh')->subDay()->toDateString();

        $response = $this->withHeaders($this->identityHeaders($person))
            ->postJson('/api/v1/walking/batch-sync', [
                'records' => [
                    [
                        'date'        => $yesterday,
                        'steps'       => 92000,
                        'distance_km' => 70.0,
                        'source'      => 'health_connect',
                    ],
                ],
            ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'data'    => [
                'synced'  => 1,
                'skipped' => 0,
            ],
        ]);

        $stored = WalkingRecord::where('person_id', $person->id)
            ->whereDate('date', $yesterday)
            ->first();
        $this->assertNotNull($stored);
        $this->assertEquals(92000, (int) $stored->steps);
    }
}
