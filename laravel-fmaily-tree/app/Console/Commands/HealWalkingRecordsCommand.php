<?php

namespace App\Console\Commands;

use App\Models\Person;
use App\Models\User;
use App\Models\WalkingRecord;
use Illuminate\Console\Command;

class HealWalkingRecordsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'walking:heal';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Heals unlinked walking records by attributing them to the correct Person in the family tree';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting walking records healing process...');

        $unlinked = WalkingRecord::whereNull('person_id')
            ->whereNotNull('user_id')
            ->get();

        if ($unlinked->isEmpty()) {
            $this->info('No unlinked walking records found. Database is clean!');
            return Command::SUCCESS;
        }

        $this->info("Found {$unlinked->count()} unlinked record(s). Attempting to resolve Persons...");

        $userIds = $unlinked->pluck('user_id')->unique();
        $users = User::whereIn('id', $userIds)->get()->keyBy('id');

        $healedCount = 0;

        foreach ($unlinked as $record) {
            $user = $users->get($record->user_id);
            if (! $user) {
                continue;
            }

            // 1. Direct link on User model
            if (! empty($user->person_id)) {
                $record->update(['person_id' => $user->person_id]);
                $healedCount++;
                continue;
            }

            // 2. Phone match via PersonContactAccount
            $person = null;
            if (! empty($user->phone)) {
                $clean = substr(preg_replace('/[^0-9]/', '', $user->phone), -9);
                if (strlen($clean) >= 9) {
                    try {
                        $contact = \App\Models\PersonContactAccount::whereIn('type', ['phone', 'whatsapp'])
                            ->where('value', 'like', "%{$clean}%")
                            ->first();
                        if ($contact && $contact->person_id) {
                            $person = Person::withoutGlobalScopes()->find($contact->person_id);
                        }
                    } catch (\Throwable $e) {}
                }
            }

            // 3. Name match
            if (! $person && ! empty($user->name)) {
                $trimmed = trim($user->name);
                try {
                    $person = Person::withoutGlobalScopes()
                        ->where(function ($q) use ($trimmed) {
                            $q->where('first_name', 'like', "%{$trimmed}%")
                                ->orWhere('last_name', 'like', "%{$trimmed}%");
                        })->first();
                } catch (\Throwable $e) {}
            }

            if ($person) {
                $user->update(['person_id' => $person->id]);
                $record->update(['person_id' => $person->id]);
                $this->line("Linked User [{$user->name}] to Person [{$person->full_name}] (ID: {$person->id})");
                $healedCount++;
            }
        }

        $this->info("Successfully healed {$healedCount} of {$unlinked->count()} records.");

        return Command::SUCCESS;
    }
}
