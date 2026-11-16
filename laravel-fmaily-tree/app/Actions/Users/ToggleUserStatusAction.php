<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Support\AccessControl;
use Illuminate\Support\Facades\DB;

class ToggleUserStatusAction
{
    /**
     * @return array{status: string, email_verified_at: mixed}
     */
    public function execute(User $actor, User $target): array
    {
        AccessControl::assertCanToggleUser($actor, $target);

        return DB::transaction(function () use ($target) {
            $target->refresh();

            if ($target->email_verified_at) {
                $target->update(['email_verified_at' => null]);
                $status = 'inactive';
            } else {
                $target->update(['email_verified_at' => now()]);
                $status = 'active';
            }

            $target->refresh();

            return [
                'status' => $status,
                'email_verified_at' => $target->email_verified_at,
            ];
        });
    }
}
