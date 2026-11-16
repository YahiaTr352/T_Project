import { useAuth } from '@/contexts/auth-context';
import {
  deleteMyContact,
  deleteMyLocation,
  extractApiError,
  fetchMyProfile,
  storeMyChild,
  storeMyContact,
  storeMyLocation,
  storeMyMarriage,
  updateMyMarriage,
  updateMyPrivacy,
  updateMyProfile,
  type MyProfilePayload,
  type UpdatePrivacyInput,
  type StoreChildInput,
  type StoreContactInput,
  type StoreLocationInput,
  type StoreMarriageInput,
  type UpdateMarriageInput,
  type UpdateProfileInput,
} from '@/lib/api/myProfile';
import { useCallback, useEffect, useState } from 'react';

export type MyProfileStatus = 'loading' | 'ready' | 'forbidden' | 'error';

export function useMyProfile() {
  const { refreshIdentityProfile } = useAuth();
  const [status, setStatus] = useState<MyProfileStatus>('loading');
  const [data, setData] = useState<MyProfilePayload | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  const load = useCallback(async () => {
    setStatus('loading');
    setErrorMessage(null);
    const res = await fetchMyProfile();
    if (res.code === 'PROFILE_IDENTITY_REQUIRED') {
      setStatus('forbidden');
      setData(null);
      setErrorMessage(res.message ?? 'يلزم توثيق الهوية ببصمة الوجه أو واتساب.');
      return;
    }
    if (!res.success || !res.data) {
      setStatus('error');
      setData(null);
      setErrorMessage(extractApiError(res));
      return;
    }
    setData(res.data);
    setStatus('ready');
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const runMutation = useCallback(
    async (action: () => Promise<{ success: boolean; message?: string; code?: string }>, successMsg?: string) => {
      setSaving(true);
      try {
        const res = await action();
        if (!res.success) {
          return { ok: false as const, message: extractApiError(res) };
        }
        await load();
        await refreshIdentityProfile();
        return { ok: true as const, message: successMsg ?? res.message ?? 'تم الحفظ بنجاح.' };
      } finally {
        setSaving(false);
      }
    },
    [load, refreshIdentityProfile]
  );

  return {
    status,
    data,
    errorMessage,
    saving,
    reload: load,
    updateProfile: (input: UpdateProfileInput) => runMutation(() => updateMyProfile(input)),
    addContact: (input: StoreContactInput) => runMutation(() => storeMyContact(input)),
    removeContact: (id: number) => runMutation(() => deleteMyContact(id), 'تم حذف حساب التواصل.'),
    addChild: (input: StoreChildInput) => runMutation(() => storeMyChild(input), 'تم إضافة الابن/الابنة.'),
    addMarriage: (input: StoreMarriageInput) => runMutation(() => storeMyMarriage(input), 'تم إضافة سجل الزواج.'),
    patchMarriage: (id: number, input: UpdateMarriageInput) =>
      runMutation(() => updateMyMarriage(id, input), 'تم تحديث سجل الزواج.'),
    addLocation: (input: StoreLocationInput) => runMutation(() => storeMyLocation(input), 'تم إضافة الموقع.'),
    removeLocation: (id: number) => runMutation(() => deleteMyLocation(id), 'تم حذف الموقع.'),
    updatePrivacy: (input: UpdatePrivacyInput) =>
      runMutation(() => updateMyPrivacy(input), 'تم حفظ إعدادات الخصوصية بنجاح.'),
  };
}
