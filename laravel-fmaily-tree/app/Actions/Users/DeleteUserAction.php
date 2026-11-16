<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Support\AccessControl;
use Illuminate\Support\Facades\DB;

class DeleteUserAction
{
    public function execute(User $actor, User $target): void
    {
        AccessControl::assertCanDeleteUser($actor, $target);

        DB::transaction(fn () => $target->delete());
    }
}
