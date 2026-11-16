<?php

namespace App\Http\Resources\Walking;

use App\Services\SiteIdentityResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalkingGroupMemberResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var \App\Models\WalkingGroupMember $this */
        $person = $this->person;

        $viewerPersonId = $request->attributes->get('viewer_person_id');
        if (! $viewerPersonId) {
            $resolver = app(SiteIdentityResolver::class);
            $identity = $resolver->resolve($request);
            $viewerPersonId = $identity ? (int) $identity['person_id'] : null;
        }

        $isSelf = $viewerPersonId && ((int) $this->person_id === (int) $viewerPersonId);

        $group = $this->relationLoaded('group') ? $this->group : $this->group()->first();
        $isGroupPrivate = $group && ! (bool) $group->is_public;

        // Effective Anonymity: Explicit membership override > Inherited from person global mode.
        // Private challenges/groups strictly forbid hidden participation; anonymity is always forced to false.
        if ($isGroupPrivate) {
            $isInherited = false;
            $isAnonymous = false;
        } else {
            $isInherited = is_null($this->is_anonymous);
            $isAnonymous = ! $isInherited
                ? (bool) $this->is_anonymous
                : in_array($person?->walking_leaderboard_mode, ['hidden', 'anonymous'], true);
        }

        // Check if the viewer is the group admin/creator
        $isViewerAdmin = false;
        if ($viewerPersonId && $group) {
            $isViewerAdmin = $group->isAdmin($viewerPersonId);
        }

        // Mask identity if anonymous and viewer is neither self nor group admin
        $masked = $isAnonymous && ! $isSelf && ! $isViewerAdmin;

        return [
            'id'                       => $this->id,
            'membership_id'            => $this->id,
            'person_id'                => $masked ? null : $this->person_id,
            'role'                     => $this->role,
            'status'                   => $this->status,
            'is_anonymous'             => $isAnonymous,
            'is_inherited'             => $isInherited,
            'is_self'                  => $isSelf,
            'share_details'            => (bool) ($this->share_details ?? true),
            'notifications_enabled'    => (bool) ($this->notifications_enabled ?? true),
            'joined_at'                => $this->joined_at?->toDateTimeString(),
            'completed_at'             => $this->completed_at?->toDateTimeString(),
            'is_goal_reached'          => (bool) ($this->completed_at !== null),
            'completion_duration_text' => $this->completion_duration_text,
            'total_steps_in_challenge'  => (int) ($this->total_steps_in_challenge ?? 0),
            'total_points_in_challenge' => (int) ($this->total_points_in_challenge ?? 0),
            'person'                   => $masked ? [
                'id'     => 0,
                'name'   => 'متسابق متخفي',
                'avatar' => \App\Models\Person::anonymousAvatarFor($person?->gender),
                'gender' => $person?->gender ?? 'male',
            ] : ($person ? [
                'id'     => $person->id,
                'name'   => $isSelf
                    ? ($isAnonymous ? "{$person->full_name} (أنت - متخفي)" : $person->full_name)
                    : ($isAnonymous ? "{$person->full_name} (متخفي)" : $person->full_name),
                'avatar' => $person->avatar,
                'gender' => $person->gender ?? 'male',
            ] : null),
        ];
    }
}
