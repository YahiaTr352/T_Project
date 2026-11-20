import { api } from '@/lib/api/alsorayiaClient';
import * as Crypto from 'expo-crypto';

// Shared secret key for request signature (corresponds to backend configuration)
const API_SECRET_KEY = 'family_quiz_secret_2026';

export interface QuizChoice {
  id: number;
  choice_text: string;
  image_url: string | null;
  video_url: string | null;
  group_name: string | null;
  person_id?: number | null;
  avatar_url?: string | null;
}

export interface VoterEligibility {
  require_identity?: boolean;
  require_whatsapp?: boolean;
  allowed_gender?: 'all' | 'male' | 'female';
  min_age?: number | null;
  require_ancestry?: boolean;
  custom_message?: string | null;
}

export interface ViewerEligibility {
  eligible: boolean;
  code: string;
  message: string;
}

export interface QuizSurveyItem {
  id: number;
  sort_order: number;
  block_type: 'text' | 'image' | 'video';
  body_text: string | null;
  media_url: string | null;
  youtube_url: string | null;
  response_kind: 'rating' | 'number' | 'text';
  rating_max: number;
  number_min: number;
  number_max: number;
}


export interface WalkingStandingRow {
  rank: number;
  person_id: number;
  name: string;
  gender: string;
  total_score: number;
  total_steps: number;
  today_steps?: number;
  daily_points?: number;
  actual_points?: number;
  projected_points?: number;
  total_points?: number;
  actual_score?: number;
  projected_score?: number;
  today_rank?: number | null;
  is_anonymous: boolean;
  is_current_user: boolean;
  photo_url?: string | null;
  avatar?: string | null;
  prize?: string | null;
  has_met_min_steps?: boolean;
}

export interface ViewerWalkingStanding {
  rank: number | null;
  person_id?: number;
  name?: string;
  gender?: string;
  photo_url?: string | null;
  actual_points?: number;
  projected_points?: number;
  total_points?: number;
  actual_score?: number;
  projected_score?: number;
  total_score: number;
  total_steps: number;
  today_steps: number;
  today_rank?: number | null;
  has_met_min_steps?: boolean;
  prize?: string | null;
}

export interface GroupTopMember {
  person_id: number;
  name: string;
  photo_url?: string | null;
  steps: number;
  today_steps?: number;
  is_viewer?: boolean;
}

export interface GroupMemberBreakdown {
  person_id: number;
  name: string;
  photo_url?: string | null;
  steps: number;
  today_steps?: number;
  intra_rank: number;
  contribution_pct: number;
  is_viewer?: boolean;
}

export interface GroupWalkingStandingRow {
  rank: number;
  group_id: number;
  name: string;
  description?: string | null;
  cover_image: string;
  total_steps: number;
  today_steps: number;
  average_steps: number;
  total_members_count: number;
  active_members_count: number;
  score: number;
  is_leading: boolean;
  top_member?: GroupTopMember | null;
  members_breakdown?: GroupMemberBreakdown[];
  invite_code?: string;
}

export interface ViewerGroupStanding {
  group_id: number;
  group_name: string;
  group_rank: number;
  user_steps: number;
  user_today_steps: number;
  user_rank_in_group: number;
  contribution_pct: number;
}

export interface GroupBattleMeta {
  is_head_to_head?: boolean;
  group_a_id?: number;
  group_a_pct?: number;
  group_b_id?: number;
  group_b_pct?: number;
  leader_group_id?: number;
  leader_group_name?: string;
  lead_difference?: number;
  lead_text?: string;
}

export interface WalkingChallengeSettings {
  scoring_method: 'daily_points' | 'cumulative_steps';
  target_gender: 'both_segregated' | 'male' | 'female' | 'all';
  min_daily_steps: number;
  daily_points_scheme: number[];
  competition_scope?: 'individuals' | 'groups';
  competing_group_ids?: number[];
  group_scoring_mode?: string;
  min_member_steps?: number;
}

export interface PredictionStandingRow {
  answer_id: number;
  person_id: number | null;
  user_id: number | null;
  name: string;
  photo_url: string | null;
  avatar: string | null;
  gender: string;
  is_anonymous: boolean;
  is_viewer: boolean;
  is_current_user: boolean;
  first_voted_at: string;
  matches_count: number;
  rank: number;
  actual_points: number;
  projected_points: number;
  total_points: number;
  actual_score: number;
  projected_score: number;
  total_score: number;
}

export interface PredictionScoringTier {
  rank: number;
  points: number;
  members_count: number;
  members: PredictionStandingRow[];
}

export interface WalkingPredictionData {
  standings: PredictionStandingRow[];
  top_standings: PredictionStandingRow[];
  contenders: PredictionStandingRow[];
  scoring_tiers?: PredictionScoringTier[];
  zero_points_members?: PredictionStandingRow[];
  viewer_standing: PredictionStandingRow | null;
  is_ended: boolean;
  scoring_method: string;
  podium_top_count: number;
  table_rows_count: number;
  rank_points_map: Record<number, number>;
}

export interface WalkingRaceConfig {
  enabled: boolean;
  calc_mode: 'cumulative' | 'daily';
  metric?: 'steps' | 'points';
  speed_ms: number;
  max_ranks: number;
  title: string;
  subtitle: string;
  button_text: string;
}

export interface WalkingRaceEntry {
  entry_id: string;
  person_id: number;
  rank: number;
  name: string;
  avatar: string | null;
  steps: number;
  prev_steps: number;
  daily_steps: number;
  points?: number;
  prev_points?: number;
  color: string;
  is_anonymous: boolean;
  is_viewer: boolean;
}

export interface WalkingRaceDay {
  day_index: number;
  day_number: number;
  date: string;
  date_label: string;
  max_steps: number;
  max_points?: number;
  top_entries: WalkingRaceEntry[];
}

export interface WalkingRaceSection {
  label: string;
  days: WalkingRaceDay[];
}

export interface WalkingRaceTimelineData {
  config: WalkingRaceConfig;
  competition_scope: 'individuals' | 'groups';
  target_gender: string;
  can_view_female: boolean;
  access_denied: boolean;
  default_section: string | null;
  total_competition_days: number;
  elapsed_days_count: number;
  sections: Record<string, WalkingRaceSection>;
}

export interface WalkingChallengeData {
  competition_scope?: 'individuals' | 'groups';
  scoring_mode_label?: string;
  groups_standings?: GroupWalkingStandingRow[];
  battle_meta?: GroupBattleMeta;
  viewer_group?: ViewerGroupStanding | null;
  walking_race_config?: WalkingRaceConfig;
  settings?: WalkingChallengeSettings | any;
  scoring_method?: string;
  winners_count?: number;
  podium_top_count?: number;
  table_rows_count?: number;
  daily_points_map?: Record<number, number>;
  resolved_gender?: 'male' | 'female';
  viewer_standing?: ViewerWalkingStanding | null;
  top_standings?: WalkingStandingRow[];
  today_standings?: WalkingStandingRow[];
  prizes?: string[];
}

export interface CandidateRules {
  nomination_type?: 'open_tree' | 'predefined';
  allowed_gender?: 'all' | 'male' | 'female';
  require_whatsapp?: boolean;
  exclude_deceased?: boolean;
  candidate_scope?: 'all' | 'family_and_ansab' | 'original_family_only' | 'maternal_only' | 'breastfed_only' | 'custom' | string;
  exclude_outside_family?: boolean;
  exclude_maternal_children?: boolean;
  exclude_breastfed?: boolean;
  exclude_original_family?: boolean;
}

export interface UserVoteInfo {
  can_edit: boolean;
  needs_completion?: boolean;
  first_voted_at: string;
  edit_until: string | null;
  seconds_remaining: number | null;
  selected_choices: number[];
  selected_person_ids?: number[];
  locked_choices?: number[];
  locked_person_ids?: number[];
}

export interface QuizQuestion {
  user_vote?: UserVoteInfo | null;
  id: number;
  question_text: string;
  description: string | null;
  answer_type: 'vote' | 'multiple_choice' | 'ordering' | 'true_false' | 'fill_blank' | 'survey' | 'custom' | 'walking' | 'walking_prediction';
  is_multiple_selections: boolean;
  vote_max_selections: number;
  vote_edit_duration_hours?: number | null;
  linked_walking_question_id?: number | null;
  is_walking_prediction?: boolean;
  prediction_data?: WalkingPredictionData | null;
  prediction_rank_points?: Record<number, number> | null;
  require_prior_registration: boolean;
  prize: string[] | string | null;
  has_answered: boolean;
  choices: QuizChoice[];
  survey_items: QuizSurveyItem[];
  media_url?: string | null;
  video_url?: string | null;
  youtube_url?: string | null;
  image_url?: string | null;
  walking_data?: WalkingChallengeData | null;
  voter_eligibility?: VoterEligibility | null;
  viewer_eligibility?: ViewerEligibility | null;
  vote_results?: VoteResultsData | null;
  candidate_rules?: CandidateRules | null;
  has_chat?: boolean;
  chat_room?: {
    id: number;
    messages_count: number;
    last_message_at: string | null;
    last_message_preview: string | null;
  } | null;
}

export interface QuizCompetition {
  id: number;
  title: string;
  description: string | null;
  start_at: string | null;
  end_at: string | null;
  show_draw_only: boolean;
  questions_visible_at: string | null;
  is_locked: boolean;
  questions: QuizQuestion[];
}

export interface UpcomingQuizEvent {
  type: string;
  target_at: string;
  title: string;
  description: string | null;
  section_title: string;
}

export interface ActiveQuizData {
  active_competition: QuizCompetition | null;
  active_competitions?: QuizCompetition[];
  upcoming_event: UpcomingQuizEvent | null;
}

export interface QuizAnswerPayload {
  name?: string;
  phone?: string;
  mother_name?: string;
  is_from_ancestry?: boolean;
  answer?: any;
  person_ids?: number | number[];
  survey_item?: Record<number, any>;
}

export interface VoteResultsChoice {
  id: number;
  text: string;
  count: number;
  percent: number;
  image_url: string | null;
  avatar_url?: string | null;
  person_id?: number | null;
  gender?: string | null;
  profile_url?: string | null;
  rank?: number;
}

export interface VoteResultsData {
  total_votes: number;
  results: VoteResultsChoice[];
}

/**
 * Generate a salted SHA-256 signature for API security verification
 */
export async function generateSignature(timestamp: number, questionId: number, phone?: string): Promise<string> {
  const rawString = API_SECRET_KEY + timestamp + questionId + (phone || '');
  return await Crypto.digestStringAsync(Crypto.CryptoDigestAlgorithm.SHA256, rawString);
}

/**
 * Fetch the currently active quiz competition or upcoming event
 */
export async function fetchActiveCompetition(phone?: string): Promise<ActiveQuizData | null> {
  const url = phone ? `/v1/quiz-competitions/active?phone=${encodeURIComponent(phone)}` : '/v1/quiz-competitions/active';
  const response = (await api.get(url)) as any;
  if (!response || response.status === 'error') {
    return null;
  }
  return response.data as ActiveQuizData;
}

/**
 * Submit user answer / vote to the server.
 * Handles automatic cryptographic signing.
 */
export async function submitQuizAnswer(
  competitionId: number,
  questionId: number,
  payload: QuizAnswerPayload
): Promise<{
  success: boolean;
  message: string;
  is_correct?: boolean;
  cooldown_ends_at?: string;
  user_vote?: UserVoteInfo | null;
} | null> {
  try {
    const timestamp = Math.floor(Date.now() / 1000);
    const signature = await generateSignature(timestamp, questionId, payload.phone || '');

    const config = {
      headers: {
        'X-App-Signature': signature,
        'X-App-Timestamp': String(timestamp),
      },
      skipGlobalErrorHandler: true, // we want to handle the validation errors manually in UI
    };

    const response = (await api.post(
      `/v1/quiz-competitions/${competitionId}/questions/${questionId}/answer`,
      payload,
      config
    )) as any;

    if (!response) {
      return { success: false, message: 'عذراً، لم نتمكن من إرسال الإجابة حالياً.' };
    }

    if (response.status === 'error') {
      return { success: false, message: response.message || 'فشل إرسال الإجابة.' };
    }

    return {
      success: true,
      message: response.message || 'تم تسجيل مشاركتك بنجاح.',
      is_correct: response.data?.is_correct,
      cooldown_ends_at: response.data?.cooldown_ends_at,
      user_vote: response.data?.user_vote ?? null,
    };
  } catch (error: any) {
    let message = 'حدث خطأ غير متوقع أثناء الاتصال بالخادم.';
    if (error.response?.data?.message) {
      message = error.response.data.message;
    }
    return { success: false, message };
  }
}

/**
 * Fetch live poll results for a vote-type question
 */
export async function fetchVoteResults(competitionId: number, questionId: number): Promise<VoteResultsData | null> {
  const response = (await api.get(`/v1/quiz-competitions/${competitionId}/questions/${questionId}/vote-results`)) as any;
  if (!response || response.status === 'error') {
    return null;
  }
  return response.data as VoteResultsData;
}

export interface CandidateTreeNode {
  id: number;
  name: string;
  first_name: string;
  gender: 'male' | 'female' | string;
  photo_url: string | null;
  has_whatsapp: boolean;
  children_count: number;
  is_deceased: boolean;
  candidate_category?: 'original_family' | 'maternal_children' | 'breastfed_children' | 'outside_family' | string;
  candidate_category_label?: string;
  candidate_category_badge?: string;
  selectable: boolean;
  unselectable_reason?: string | null;
}

/**
 * Fetch family tree nodes for open tree candidate nomination
 */
export async function fetchCandidateTreeNodes(params: {
  parentId?: number | null;
  q?: string;
  gender?: 'male' | 'female' | 'all';
  hasWhatsappOnly?: boolean;
  excludeDeceased?: boolean;
  excludeOutsideFamily?: boolean;
  excludeMaternalChildren?: boolean;
  excludeBreastfed?: boolean;
  excludeOriginalFamily?: boolean;
  questionId?: number | null;
}): Promise<CandidateTreeNode[]> {
  const query = new URLSearchParams();
  if (params.parentId) query.append('parent_id', String(params.parentId));
  if (params.q) query.append('q', params.q);
  if (params.gender && params.gender !== 'all') query.append('gender', params.gender);
  if (params.hasWhatsappOnly) query.append('has_whatsapp_only', '1');
  if (params.excludeDeceased !== undefined) query.append('exclude_deceased', params.excludeDeceased ? '1' : '0');
  if (params.excludeOutsideFamily !== undefined) query.append('exclude_outside_family', params.excludeOutsideFamily ? '1' : '0');
  if (params.excludeMaternalChildren !== undefined) query.append('exclude_maternal_children', params.excludeMaternalChildren ? '1' : '0');
  if (params.excludeBreastfed !== undefined) query.append('exclude_breastfed', params.excludeBreastfed ? '1' : '0');
  if (params.excludeOriginalFamily !== undefined) query.append('exclude_original_family', params.excludeOriginalFamily ? '1' : '0');
  if (params.questionId) query.append('question_id', String(params.questionId));

  const res = (await api.get<{ status: string; data: { nodes: CandidateTreeNode[] } }>(
    `/v1/quiz-competitions/candidates/tree-nodes?${query.toString()}`
  )) as any;

  return res?.data?.nodes || [];
}

/**
 * Fetch the day-by-day animated Bar Chart Race timeline for a walking challenge question.
 */
export async function fetchWalkingRaceTimeline(
  questionId: number
): Promise<{ success: boolean; data: WalkingRaceTimelineData | null; message?: string }> {
  const res = (await api.get<{
    status: string;
    success: boolean;
    data: WalkingRaceTimelineData | null;
    message?: string;
  }>(`/v1/quiz-competitions/walking-race/${questionId}`)) as any;

  return {
    success: Boolean(res?.success || res?.status === 'success'),
    data: res?.data ?? null,
    message: res?.message,
  };
}

