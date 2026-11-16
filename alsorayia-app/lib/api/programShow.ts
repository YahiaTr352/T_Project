import { api } from '@/lib/api/alsorayiaClient';

/** يطابق `GET /api/programs/{id}` — انظر `frontend-program-show-api.md` */

export interface ProgramShowCategoryRef {
  id: number;
  name: string;
}

export interface ProgramShowCore {
  id: number;
  kind: string;
  name: string;
  program_title: string | null;
  program_description: string | null;
  proud_of_title?: string | null;
  hero_image_url: string | null;
  thumbnail_url: string | null;
  page_url: string;
  program_category: ProgramShowCategoryRef | null;
  is_program: boolean;
  is_proud_of: boolean;
}

export interface ProgramGalleryMediaItem {
  id: number;
  name?: string | null;
  full_name?: string | null;
  description?: string | null;
  media_type?: string | null;
  image_url?: string | null;
  thumbnail_url?: string | null;
  youtube_url?: string | null;
  program_media_order?: number;
}

export interface ProgramVideoMediaItem {
  id: number;
  name?: string | null;
  description?: string | null;
  youtube_url?: string | null;
  youtube_embed_url?: string | null;
  youtube_thumbnail_url?: string | null;
}

export interface ProgramExternalLink {
  title: string;
  url: string;
  description?: string | null;
  link_order?: number;
}

export interface ProgramGalleryImage {
  id: number;
  name?: string | null;
  image_url?: string | null;
  thumbnail_url?: string | null;
}

export interface ProgramNestedGallery {
  title: string;
  description?: string | null;
  gallery_order?: number;
  images?: ProgramGalleryImage[];
}

export interface SubProgramItem {
  id: number;
  program_title?: string | null;
  program_description?: string | null;
  image_url?: string | null;
  page_url: string;
  program_is_active?: boolean;
}

export interface CompetitionCategory {
  id: number;
  name: string;
}

export interface CompetitionParticipantIndividual {
  user_id: number;
  name: string;
  categories?: CompetitionCategory[];
}

export interface CompetitionTeamMember {
  user_id: number;
  name: string;
  role?: string;
  categories?: CompetitionCategory[];
}

export interface CompetitionTeam {
  id: number;
  name: string;
  is_complete?: boolean;
  members?: CompetitionTeamMember[];
}

export interface CompetitionParticipants {
  individuals?: CompetitionParticipantIndividual[];
  teams?: CompetitionTeam[];
}

export interface ProgramCompetition {
  id?: number;
  title: string;
  description?: string | null;
  game_type?: string | null;
  team_size?: number | string | null;
  start_date?: string | null;
  end_date?: string | null;
  is_active?: boolean;
  is_registration_open?: boolean;
  categories?: CompetitionCategory[];
  participants?: CompetitionParticipants;
}

export interface ProgramShowResponse {
  success: boolean;
  program: ProgramShowCore;
  name?: string | null;
  gallery_media?: ProgramGalleryMediaItem[];
  video_media?: ProgramVideoMediaItem[];
  program_links?: ProgramExternalLink[];
  program_galleries?: ProgramNestedGallery[];
  sub_programs?: SubProgramItem[];
  competitions?: ProgramCompetition[];
  competition_filter_categories?: ProgramShowCategoryRef[];
  selected_category_id?: number | null;
}

function sortNum(a: number | undefined, b: number | undefined): number {
  return (a ?? 0) - (b ?? 0);
}

/**
 * `GET /api/programs/{id}` — اختياريًا `?category=` لفلترة المسابقات.
 */
export async function fetchProgramShow(
  id: number,
  categoryId?: number | null
): Promise<ProgramShowResponse | null> {
  if (!Number.isFinite(id)) return null;
  const params: Record<string, number> = {};
  if (categoryId != null && Number.isFinite(categoryId)) {
    params.category = categoryId;
  }
  const raw = (await api.get(`/programs/${id}`, { params: Object.keys(params).length ? params : undefined })) as unknown;
  if (!raw || typeof raw !== 'object') return null;
  const d = raw as Partial<ProgramShowResponse>;
  if (d.success === false || !d.program || typeof d.program.id !== 'number') return null;
  return raw as ProgramShowResponse;
}

export function orderedGalleryMedia(list: ProgramGalleryMediaItem[]): ProgramGalleryMediaItem[] {
  return [...list].sort((a, b) => sortNum(a.program_media_order, b.program_media_order));
}

export function orderedProgramLinks(list: ProgramExternalLink[]): ProgramExternalLink[] {
  return [...list].sort((a, b) => sortNum(a.link_order, b.link_order));
}

export function orderedProgramGalleries(list: ProgramNestedGallery[]): ProgramNestedGallery[] {
  return [...list].sort((a, b) => sortNum(a.gallery_order, b.gallery_order));
}

/** عنوان العرض حسب نوع السجل والوثائق. */
export function displayProgramTitle(p: ProgramShowCore): string {
  const main =
    p.kind === 'proud_of'
      ? (p.proud_of_title?.trim() || p.program_title?.trim() || p.name)
      : (p.program_title?.trim() || p.name);
  return main || 'برنامج';
}
