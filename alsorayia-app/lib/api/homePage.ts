import { api } from '@/lib/api/alsorayiaClient';

/** يطابق `HomePageApiResource` في Laravel — الحقول المستخدمة في التطبيق. */
export interface HomeSlide {
  id: number;
  title: string;
  description: string | null;
  link: string | null;
  image_url: string | null;
  is_active?: boolean;
}

export interface HomeGalleryItem {
  type: string;
  id: number;
  name: string;
  image_url: string | null;
}

export interface HomeProgramImage {
  id: number;
  name: string;
  description: string | null;
  image_url: string | null;
  youtube_url?: string | null;
  program_title: string | null;
  program_description: string | null;
  is_program?: boolean;
}

export interface HomeProgramCategoryGroup {
  title?: string;
  programs?: HomeProgramImage[];
}

export interface HomeCouncilImage {
  id: number;
  image_url: string | null;
  thumbnail_url?: string | null;
}

export interface HomeCouncil {
  id: number;
  name: string;
  description: string | null;
  address: string | null;
  google_map_url: string | null;
  working_days_from: string | null;
  working_days_to: string | null;
  display_order: string | number;
  is_active: boolean;
  images: HomeCouncilImage[];
}

export interface HomeEvent {
  id: number;
  title: string;
  description: string | null;
  city: string | null;
  /** قد يكون رابط خرائط (مثل Google Maps) */
  location: string | null;
  location_name?: string | null;
  event_date: string | null;
  show_countdown?: boolean;
  display_order?: string | number;
  is_active: boolean;
}

export interface HomeCourse {
  id: number;
  title: string;
  description: string | null;
  instructor: string | null;
  duration: string | null;
  students: number | null;
  link: string | null;
  image_url: string | null;
  is_active?: boolean;
}

export interface HomeFamilyNewsItem {
  id: number;
  url: string;
  title: string;
  summary: string | null;
  published_at: string | null;
  main_image_url: string | null;
  is_active?: boolean;
}

export interface HomeImportantLinkMedia {
  id: number;
  kind: string;
  title: string | null;
  description: string | null;
  sort_order: number;
  file_url: string | null;
}

export interface HomeImportantLink {
  id: number;
  title: string;
  url: string | null;
  url_ios: string | null;
  url_android: string | null;
  type: string;
  icon?: string | null;
  description: string | null;
  order: string | number;
  is_active: boolean;
  open_in_new_tab?: boolean;
  status?: string | null;
  image_url: string | null;
  media?: HomeImportantLinkMedia[];
}

export interface HomeImportantLinkCategory {
  id: number | null;
  name: string;
  description: string | null;
  icon: string | null;
  sort_order: number;
  is_active: boolean;
  links: HomeImportantLink[];
}

export interface HomeDynamicSectionSettings {
  subtitle?: string | null;
  description?: string | null;
  icon?: string | null;
  show_title?: boolean;
  background_color?: string | null;
  text_color?: string | null;
  padding_top?: string | number | null;
  padding_bottom?: string | number | null;
  layout_style?: 'grid' | 'horizontal' | 'vertical' | string | null;
  columns?: string | number | null;
  title_color?: string | null;
  title_size?: string | null;
  title_weight?: string | null;
  title_align?: string | null;
  subtitle_color?: string | null;
  subtitle_size?: string | null;
  subtitle_weight?: string | null;
  subtitle_align?: string | null;
  description_color?: string | null;
  description_size?: string | null;
  description_align?: string | null;
  carousel_autoplay?: boolean;
  [key: string]: any;
}

export interface HomeDynamicSectionItem {
  id: number;
  item_type: 'html' | 'rich_text' | 'text' | 'image' | 'video' | 'table' | 'card' | 'button' | 'spacer' | 'countdown' | string;
  display_order: number;
  content?: {
    html?: string;
    text?: string;
    title?: string;
    description?: string;
    link?: string;
    url?: string;
    alt?: string;
    caption?: string;
    button_text?: string;
    button_url?: string;
    target_date?: string;
    badge_text?: string;
    expired_message?: string;
    icon?: string;
    table_data?: any[][];
    [key: string]: any;
  } | string | null;
  settings?: any;
  youtube_url?: string | null;
  image_url?: string | null;
  video_url?: string | null;
  content_format?: string | null;
  link_absolute?: string | null;
  url_absolute?: string | null;
  button_url_absolute?: string | null;
}

export interface HomeDynamicSection {
  id: number;
  title: string;
  section_type: 'custom' | 'gallery' | 'cards' | 'stats' | 'text_with_image' | string;
  display_order: number;
  is_active: boolean;
  settings?: HomeDynamicSectionSettings | null;
  css_classes?: string | null;
  content_source_type?: string | null;
  items: HomeDynamicSectionItem[];
  content_source_items?: any[] | null;
}

export interface HomePageData {
  success: boolean;
  slides: HomeSlide[];
  gallery: HomeGalleryItem[];
  familyBrief: string | null;
  whatsNew: string;
  courses: HomeCourse[];
  programs: HomeProgramImage[];
  program_categories: HomeProgramCategoryGroup[];
  councils?: HomeCouncil[];
  events: HomeEvent[];
  familyNews: HomeFamilyNewsItem[];
  importantLinks?: HomeImportantLink[];
  importantLinkCategories?: HomeImportantLinkCategory[];
  dynamicSections?: HomeDynamicSection[];
}

/**
 * `GET /api/home` — قاعدة axios تتضمن `/api`، لذا المسار هنا `/home`.
 * أثقل endpoint في الـ API (شرائح + برامج + مجالس + مسابقات بأسئلتها كاملة)،
 * لذا مهلة أطول (60 ثانية) بدل الافتراضية (15 ثانية) حتى لا يُجهض التحميل.
 * @see docs في مشروع Laravel: `frontend-home-api.md`
 */
export async function fetchHomePage(): Promise<HomePageData | null> {
  const raw = (await api.get('/home', { timeout: 60000 })) as unknown;
  if (!raw || typeof raw !== 'object') return null;
  const d = raw as Partial<HomePageData>;
  if (d.success === false) return null;
  return raw as HomePageData;
}
