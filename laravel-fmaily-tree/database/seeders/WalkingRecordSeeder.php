<?php

namespace Database\Seeders;

use App\Models\Person;
use App\Models\User;
use App\Models\WalkingRecord;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class WalkingRecordSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $persons = Person::withoutGlobalScopes()->where('from_outside_the_family', false)->limit(15)->get();
        $users = User::all();

        $notes = [
            'مشي صباحي في الحديقة',
            'تمارين مشي مسائية',
            'مشي مع العائلة',
            'جولة في الحي',
            'مشي للمدارس',
            'تنزه على الشاطئ',
            null,
        ];

        $recordCount = 0;

        // Seed for Family Persons
        if ($persons->isNotEmpty()) {
            foreach ($persons as $person) {
                $recordsCount = rand(5, 12);
                for ($i = 0; $i < $recordsCount; $i++) {
                    $date = Carbon::today()->subDays($i)->format('Y-m-d');
                    $steps = rand(2500, 18000);
                    $distanceKm = round($steps * 0.000762, 2);
                    $durationMinutes = (int) round($steps / 90);
                    $calories = (int) round($steps * 0.04);

                    WalkingRecord::updateOrCreate(
                        [
                            'person_id' => $person->id,
                            'date'      => $date,
                        ],
                        [
                            'steps'            => $steps,
                            'distance_km'      => $distanceKm,
                            'duration_minutes' => $durationMinutes,
                            'calories'         => $calories,
                            'source'           => 'device',
                            'notes'            => $notes[array_rand($notes)],
                        ]
                    );
                    $recordCount++;
                }
            }
        }

        // Seed for Users (Legacy/Admin)
        if ($users->isNotEmpty()) {
            foreach ($users as $user) {
                $recordsCount = rand(3, 7);
                for ($i = 0; $i < $recordsCount; $i++) {
                    $date = Carbon::today()->subDays($i)->format('Y-m-d');
                    $steps = rand(2000, 12000);
                    $distanceKm = round($steps * 0.000762, 2);
                    $durationMinutes = (int) round($steps / 90);
                    $calories = (int) round($steps * 0.04);

                    WalkingRecord::updateOrCreate(
                        [
                            'user_id' => $user->id,
                            'date'    => $date,
                        ],
                        [
                            'steps'            => $steps,
                            'distance_km'      => $distanceKm,
                            'duration_minutes' => $durationMinutes,
                            'calories'         => $calories,
                            'source'           => 'dashboard',
                            'notes'            => $notes[array_rand($notes)],
                        ]
                    );
                    $recordCount++;
                }
            }
        }

        $this->command->info("تم إنشاء وتحديث {$recordCount} سجل مشي بنجاح.");
    }
}
