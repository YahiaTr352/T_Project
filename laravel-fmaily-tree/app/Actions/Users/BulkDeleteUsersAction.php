<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Support\AccessControl;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class BulkDeleteUsersAction
{
    /**
     * @param  array<int, int>  $ids
     * @return array{deleted: int, errors: array<int, string>}
     */
    public function execute(User $actor, array $ids): array
    {
        if (! AccessControl::isAdminOrAbove($actor)) {
            throw new AuthorizationException('ليس لديك صلاحية لحذف المستخدمين.');
        }

        if (in_array($actor->id, $ids, true)) {
            throw new AuthorizationException('لا يمكنك حذف حسابك الخاص.');
        }

        return DB::transaction(function () use ($actor, $ids) {
            $deleted = 0;
            $errors = [];

            $users = User::query()->whereIn('id', $ids)->get();

            foreach ($users as $user) {
                try {
                    AccessControl::assertCanDeleteUser($actor, $user);
                    $user->delete();
                    $deleted++;
                } catch (AuthorizationException $e) {
                    $errors[] = "{$user->name}: {$e->getMessage()}";
                }
            }

            return ['deleted' => $deleted, 'errors' => $errors];
        });
    }
}
