import {
  attachPersonToGroup,
  createInvitationGroup,
  deleteInvitationGroup,
  detachPersonFromGroup,
  extractInvitationError,
  fetchInvitationGroups,
  isIdentityRequired,
  isProfileIncomplete,
  isUnderageBlocked,
  updateInvitationGroup,
  type CreateGroupInput,
  type InvitationGroup,
  type UpdateGroupInput,
} from '@/lib/api/invitations';
import { useCallback, useEffect, useState } from 'react';

export function useInvitationGroups(enabled: boolean) {
  const [groups, setGroups] = useState<InvitationGroup[]>([]);
  const [loading, setLoading] = useState(false);
  const [refreshing, setRefreshing] = useState(false);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [identityRequired, setIdentityRequired] = useState(false);
  const [profileIncomplete, setProfileIncomplete] = useState(false);
  const [underageBlocked, setUnderageBlocked] = useState(false);

  const load = useCallback(
    async (isRefresh = false) => {
      if (!enabled) return;
      if (isRefresh) setRefreshing(true);
      else setLoading(true);
      setError(null);
      const res = await fetchInvitationGroups();
      if (isIdentityRequired(res) || isProfileIncomplete(res) || isUnderageBlocked(res)) {
        setIdentityRequired(isIdentityRequired(res));
        setProfileIncomplete(isProfileIncomplete(res));
        setUnderageBlocked(isUnderageBlocked(res));
        setGroups([]);
        setError(res.message ?? null);
        setLoading(false);
        setRefreshing(false);
        return;
      }
      if (!res.success) {
        setError(extractInvitationError(res));
        if (!isRefresh) setGroups([]);
        setLoading(false);
        setRefreshing(false);
        return;
      }
      setIdentityRequired(false);
      setProfileIncomplete(false);
      setUnderageBlocked(false);
      setGroups(res.data?.groups ?? []);
      setLoading(false);
      setRefreshing(false);
    },
    [enabled]
  );

  useEffect(() => {
    void load(false);
  }, [load]);

  const runMutation = useCallback(
    async (action: () => Promise<{ success: boolean; message?: string; code?: string }>) => {
      setSaving(true);
      try {
        const res = await action();
        if (isIdentityRequired(res) || isProfileIncomplete(res as any) || isUnderageBlocked(res as any)) {
          if (isIdentityRequired(res)) setIdentityRequired(true);
          if (isProfileIncomplete(res as any)) setProfileIncomplete(true);
          if (isUnderageBlocked(res as any)) setUnderageBlocked(true);
          return { ok: false as const, message: extractInvitationError(res) };
        }
        if (!res.success) {
          return { ok: false as const, message: extractInvitationError(res) };
        }
        await load(true);
        return { ok: true as const, message: res.message ?? 'تم الحفظ بنجاح.' };
      } finally {
        setSaving(false);
      }
    },
    [load]
  );

  return {
    groups,
    loading,
    refreshing,
    saving,
    error,
    identityRequired,
    profileIncomplete,
    underageBlocked,
    blocked: identityRequired || profileIncomplete || underageBlocked,
    reload: () => load(false),
    refresh: () => load(true),
    createGroup: (input: CreateGroupInput) => runMutation(() => createInvitationGroup(input)),
    updateGroup: (id: number, input: UpdateGroupInput) =>
      runMutation(() => updateInvitationGroup(id, input)),
    deleteGroup: (id: number) => runMutation(() => deleteInvitationGroup(id)),
    attachPerson: (groupId: number, personId: number) =>
      runMutation(() => attachPersonToGroup(groupId, personId)),
    detachPerson: (groupId: number, personId: number) =>
      runMutation(() => detachPersonFromGroup(groupId, personId)),
  };
}
