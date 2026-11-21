import WalkingRaceModal from '@/components/quiz/WalkingRaceModal';
import { fetchWalkingRaceTimeline } from '@/lib/api/quiz';
import { render, screen } from '@testing-library/react-native';

jest.mock('@/lib/api/quiz', () => ({ fetchWalkingRaceTimeline: jest.fn() }));
jest.mock('@/lib/api/alsorayiaClient', () => ({ WEB_BASE_URL: 'http://127.0.0.1:8000' }));
jest.mock('@expo/vector-icons', () => ({ Ionicons: () => null }));
jest.mock('expo-image', () => ({ Image: () => null }));
jest.mock('react-native-reanimated', () => ({
  __esModule: true,
  default: { View: jest.requireActual('react-native').View },
  Easing: { out: jest.fn() },
  useAnimatedStyle: jest.fn(),
  useSharedValue: (value: number) => jest.requireActual('react').useRef({ value }).current,
  withTiming: (value: number) => value,
}));

test.each(['daily', 'cumulative'] as const)('renders the correct race totals in %s mode', async (mode) => {
  jest.mocked(fetchWalkingRaceTimeline).mockResolvedValue({
    success: true,
    data: {
      config: {
        enabled: true,
        calc_mode: mode,
        ...(mode === 'daily' ? { metric: 'points' } : {}),
        speed_ms: 2800,
        max_ranks: 10,
        title: 'سباق الترتيب اليومي',
        subtitle: '',
        button_text: '',
      },
      competition_scope: 'individuals',
      target_gender: 'male',
      can_view_female: false,
      access_denied: false,
      default_section: 'male',
      total_competition_days: 1,
      elapsed_days_count: 1,
      sections: {
        male: {
          label: 'ترتيب الرجال',
          days: [{
            day_index: 0,
            day_number: 1,
            date: '2026-11-20',
            date_label: '٢٠ نوفمبر',
            max_steps: 15000,
            max_points: 20,
            top_entries: [{
              entry_id: 'p_1', person_id: 1, rank: 1, name: 'أحمد', avatar: null,
              steps: 15000, prev_steps: 7000, daily_steps: 8000, points: 20, prev_points: 10,
              color: '#2176c7', is_anonymous: false, is_viewer: false,
            }],
          }],
        },
      },
    },
  });

  render(<WalkingRaceModal visible questionId={209} onClose={jest.fn()} />);

  expect(await screen.findByText('١٥,٠٠٠ خطوة')).toBeOnTheScreen();
  expect(screen.queryByText('٨,٠٠٠ خطوة')).toBeNull();
  if (mode === 'daily') {
    expect(screen.getByText('٢٠ نقطة')).toBeOnTheScreen();
  } else {
    expect(screen.queryByText('٢٠ نقطة')).toBeNull();
  }
});
