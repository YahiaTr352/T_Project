import {
  extractInvitationError,
  fetchInvitationTemplates,
  isIdentityRequired,
  isProfileIncomplete,
  isUnderageBlocked,
  type InvitationTemplate,
} from '@/lib/api/invitations';
import { useCallback, useEffect, useState } from 'react';

export function useInvitationTemplates(enabled: boolean) {
  const [templates, setTemplates] = useState<InvitationTemplate[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [identityRequired, setIdentityRequired] = useState(false);
  const [profileIncomplete, setProfileIncomplete] = useState(false);
  const [underageBlocked, setUnderageBlocked] = useState(false);

  const load = useCallback(async () => {
    if (!enabled) return;
    setLoading(true);
    setError(null);
    const res = await fetchInvitationTemplates();
    if (isIdentityRequired(res) || isProfileIncomplete(res) || isUnderageBlocked(res)) {
      setIdentityRequired(isIdentityRequired(res));
      setProfileIncomplete(isProfileIncomplete(res));
      setUnderageBlocked(isUnderageBlocked(res));
      setTemplates([]);
      setError(res.message ?? null);
      setLoading(false);
      return;
    }
    if (!res.success) {
      setError(extractInvitationError(res));
      setTemplates([]);
      setLoading(false);
      return;
    }
    setIdentityRequired(false);
    setProfileIncomplete(false);
    setUnderageBlocked(false);
    const rawTemplates = res.data?.templates ?? [];
    setTemplates(
      rawTemplates.filter(
        (t) => t.key !== 'fund_collection' && !t.name?.includes('تذكير مالي')
      )
    );
    setLoading(false);
  }, [enabled]);

  useEffect(() => {
    void load();
  }, [load]);

  return {
    templates,
    loading,
    error,
    identityRequired,
    profileIncomplete,
    underageBlocked,
    blocked: identityRequired || profileIncomplete || underageBlocked,
    reload: load,
  };
}
