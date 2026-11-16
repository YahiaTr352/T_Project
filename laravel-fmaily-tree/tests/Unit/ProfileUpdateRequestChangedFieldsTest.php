<?php

namespace Tests\Unit;

use App\Models\ProfileUpdateRequest;
use Tests\TestCase;

class ProfileUpdateRequestChangedFieldsTest extends TestCase
{
    private function makeRequest(array $old, array $proposed, string $type = 'profile_info'): ProfileUpdateRequest
    {
        return new ProfileUpdateRequest([
            'change_type' => $type,
            'old_data' => $old,
            'proposed_data' => $proposed,
        ]);
    }

    public function test_only_genuinely_changed_fields_are_reported(): void
    {
        $request = $this->makeRequest(
            old: ['birth_place' => 'الرياض', 'occupation' => 'مهندس', 'biography' => null, 'birth_date' => '1990-05-05'],
            proposed: ['birth_place' => 'الرياض', 'occupation' => ' مهندس ', 'biography' => '', 'birth_date' => '1991-01-01'],
        );

        $this->assertSame(['birth_date'], $request->changedProfileFields());
    }

    public function test_locked_name_fields_are_never_reviewable(): void
    {
        $request = $this->makeRequest(
            old: ['first_name' => 'ريم', 'last_name' => null],
            proposed: ['first_name' => 'ريما', 'last_name' => 'السريع', 'parent_id' => 5, 'mother_id' => 7],
        );

        $this->assertSame([], $request->changedProfileFields());
    }

    public function test_legacy_iso_utc_date_snapshot_matches_plain_date(): void
    {
        config(['app.timezone' => 'Asia/Riyadh']);

        $request = $this->makeRequest(
            old: ['birth_date' => '1990-05-04T21:00:00.000000Z'],
            proposed: ['birth_date' => '1990-05-05'],
        );

        $this->assertSame([], $request->changedProfileFields());
    }

    public function test_photo_key_is_handled_separately(): void
    {
        $request = $this->makeRequest(old: ['photo_url' => 'a.jpg'], proposed: ['photo_url' => 'b.jpg']);

        $this->assertSame([], $request->changedProfileFields());
    }

    public function test_non_profile_info_requests_report_nothing(): void
    {
        $request = $this->makeRequest(old: ['value' => '1'], proposed: ['value' => '2'], type: 'contact_update');

        $this->assertSame([], $request->changedProfileFields());
    }

    public function test_system_requested_name_fields_are_reviewable(): void
    {
        $request = new ProfileUpdateRequest([
            'change_type'       => 'profile_info',
            'requested_by_auth' => 'system',
            'old_data'          => ['first_name' => 'عبد الرحمن', 'last_name' => null],
            'proposed_data'     => ['first_name' => 'عبدالرحمن', 'last_name' => 'الغامدي', 'parent_id' => 5],
        ]);

        $this->assertSame(['first_name', 'last_name'], $request->changedProfileFields());
    }
}
