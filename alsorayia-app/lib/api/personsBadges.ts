import { api } from '@/lib/api/alsorayiaClient';

/** وسام مرتبط بالشخص — اسم الحقل `padges` كما في الخادم. */
export interface PersonPadge {
  id: number;
  name: string;
  description: string | null;
  color: string | null;
  sort_order: number;
  is_active: boolean;
  image_url: string | null;
  pivot_is_active: boolean;
}

export interface PersonWithBadges {
  id: number;
  first_name: string;
  last_name: string;
  full_name: string;
  avatar_url: string | null;
  profile_url: string;
  padges: PersonPadge[];
}

export interface PersonsBadgesResponse {
  success: boolean;
  persons: PersonWithBadges[];
}

/**
 * `GET /api/persons/badges` — أشخاص لديهم وسام واحد على الأقل.
 * @see Laravel docs: `frontend-persons-badges-api.md`
 */
export async function fetchPersonsBadges(): Promise<PersonsBadgesResponse | null> {
  const raw = (await api.get('/persons/badges')) as unknown;
  if (!raw || typeof raw !== 'object') return null;
  const d = raw as Partial<PersonsBadgesResponse>;
  if (d.success === false) return null;
  if (!Array.isArray(d.persons)) return null;
  return raw as PersonsBadgesResponse;
}
