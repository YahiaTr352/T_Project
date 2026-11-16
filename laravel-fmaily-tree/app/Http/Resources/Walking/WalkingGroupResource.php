<?php

namespace App\Http\Resources\Walking;

use App\Services\SiteIdentityResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalkingGroupResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $viewerPersonId = $request->attributes->get('viewer_person_id');
        if (! $viewerPersonId) {
            $resolver = app(SiteIdentityResolver::class);
            $identity = $resolver->resolve($request);
            $viewerPersonId = $identity ? (int) $identity['person_id'] : null;
        }

        $isMember = false;
        $isCreator = false;
        $myMembership = null;

        if ($viewerPersonId) {
            $isCreator = (int) $this->created_by_person_id === (int) $viewerPersonId;

            if ($this->relationLoaded('memberships')) {
                $myMembership = $this->memberships->first(
                    fn ($m) => (int) $m->person_id === (int) $viewerPersonId && $m->status === 'accepted'
                );
                $isMember = (bool) $myMembership;
            } else {
                $myMembership = $this->memberships()->where('person_id', $viewerPersonId)->where('status', 'accepted')->first();
                $isMember = (bool) $myMembership;
            }
        }

        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'description'     => $this->description,
            'cover_image'     => $this->cover_image,
            'target_steps'    => $this->target_steps,
            'start_date'      => $this->start_date?->toDateString(),
            'end_date'        => $this->end_date?->toDateString(),
            'is_public'       => (bool) $this->is_public,
            'status'          => $this->status,
            'archived_at'     => $this->archived_at?->toDateTimeString(),
            'archived_reason' => $this->archived_reason,
            'invite_code'     => $this->invite_code,
            'member_count'    => $this->members_count ?? $this->members()->count(),
            'is_member'       => $isMember,
            'is_creator'      => $isCreator,
            'is_admin'        => $viewerPersonId ? $this->isAdmin($viewerPersonId) : false,
            'current_member_settings' => $myMembership ? [
                'is_anonymous'          => (bool) ($myMembership->is_anonymous ?? false),
                'share_details'         => (bool) ($myMembership->share_details ?? true),
                'notifications_enabled' => (bool) ($myMembership->notifications_enabled ?? true),
            ] : null,
            'members'         => $this->whenLoaded('memberships', fn () => WalkingGroupMemberResource::collection($this->memberships)),
            'created_at'      => $this->created_at?->toDateString(),
        ];
    }
}
