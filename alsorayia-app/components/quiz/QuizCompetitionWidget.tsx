import React, { useEffect, useState, useRef, useCallback, forwardRef, useImperativeHandle } from 'react';
import { CustomAlert } from '@/components/ui/AlertModal';
import {
  StyleSheet,
  View,
  ScrollView,
  TextInput,
  TouchableOpacity,
  ActivityIndicator,
} from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { useColorScheme } from '@/hooks/use-color-scheme';
import { IconSymbol } from '@/components/ui/icon-symbol';
import { LinearGradient } from 'expo-linear-gradient';
import { appStorage } from '@/lib/storage/appStorage';
import { useAuth } from '@/contexts/auth-context';
import type { ThemeColors } from '@/constants/theme';
import { HtmlContent } from '@/components/html-content';
import { Image } from 'expo-image';
import { Skeleton } from '@/components/ui/skeleton';
import { Ionicons } from '@expo/vector-icons';

// Import sub-components
import VoteOptionSelector from './VoteOptionSelector';
import OpenTreeCandidateSelector from './OpenTreeCandidateSelector';
import OrderingGrid from './OrderingGrid';
import FillInBlankSentence from './FillInBlankSentence';
import StarRatingInput from './StarRatingInput';
import TrueFalseList from './TrueFalseList';
import MediaLightbox from './MediaLightbox';
import PollResults from './PollResults';
import WalkingChallengeCard from './WalkingChallengeCard';
import WalkingGroupBattleCard from './WalkingGroupBattleCard';
import VoteNomineesLeaderboard from './VoteNomineesLeaderboard';
import WalkingPredictionCard from './WalkingPredictionCard';
import { QuestionChatSection } from './QuestionChatSection';

// Import API functions and interfaces
import {
  fetchActiveCompetition,
  submitQuizAnswer,
  fetchVoteResults,
  type QuizCompetition,
  type UpcomingQuizEvent,
  type QuizQuestion,
  type VoteResultsData,
} from '@/lib/api/quiz';
import { toEnglishDigits } from '@/lib/utils/numberFormat';
import {
  formatDuration,
  formatVoteCountdown,
  isRealHtml,
  isValidSaudiPhone,
} from './competition/quizCompetitionHelpers';
import {
  HTML_CONTENT_WIDTH,
  quizCompetitionStyles as styles,
} from './competition/quizCompetitionStyles';

export interface QuizCompetitionWidgetRef {
  refresh: () => Promise<void>;
}

interface QuizCompetitionWidgetProps {
  colors: ThemeColors;
  refreshTrigger?: number;
}

const QuizCompetitionWidget = forwardRef<QuizCompetitionWidgetRef, QuizCompetitionWidgetProps>(
  function QuizCompetitionWidget({ colors, refreshTrigger }, ref) {
  const colorScheme = useColorScheme();
  const isDark = colorScheme === 'dark';
  const { user, hasIdentity, lock } = useAuth();

  /**
   * Resolve person IDs for an open_tree vote without ever falling back to raw choice IDs.
   */
  const resolveOpenTreeVotePersonIds = (
    question: QuizQuestion,
    mode: 'selected' | 'locked',
    liveResultsOverride?: { id: number; person_id?: number | null }[]
  ): number[] => {
    const userVote = question.user_vote;
    if (!userVote) return [];

    const directIds =
      mode === 'selected' ? userVote.selected_person_ids : userVote.locked_person_ids;
    const validDirect = (directIds || [])
      .map(Number)
      .filter((id) => !Number.isNaN(id) && id > 0);
    if (validDirect.length > 0) {
      return Array.from(new Set(validDirect));
    }

    const choiceIds =
      mode === 'selected' ? userVote.selected_choices : userVote.locked_choices;
    if (!choiceIds || choiceIds.length === 0) return [];

    const choiceIdToPersonId = new Map<number, number>();
    (question.choices || []).forEach((c) => {
      if (c.person_id && Number(c.person_id) > 0) {
        choiceIdToPersonId.set(Number(c.id), Number(c.person_id));
      }
    });
    const resultRows = liveResultsOverride ?? question.vote_results?.results ?? [];
    resultRows.forEach((r) => {
      if (r.person_id && Number(r.person_id) > 0 && !choiceIdToPersonId.has(Number(r.id))) {
        choiceIdToPersonId.set(Number(r.id), Number(r.person_id));
      }
    });

    const resolved: number[] = [];
    choiceIds.forEach((cid) => {
      const pid = choiceIdToPersonId.get(Number(cid));
      if (pid && !resolved.includes(pid)) {
        resolved.push(pid);
      }
    });
    return resolved;
  };

  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [activeComp, setActiveComp] = useState<QuizCompetition | null>(null);
  const [activeCompetitions, setActiveCompetitions] = useState<QuizCompetition[]>([]);
  const [upcomingEvent, setUpcomingEvent] = useState<UpcomingQuizEvent | null>(null);
  
  // User credential inputs
  const [name, setName] = useState('');
  const [phone, setPhone] = useState('');
  const [motherName, setMotherName] = useState('');
  const [isFromAncestry] = useState(true);
  const [isSavedUser, setIsSavedUser] = useState(false);

  // Sync identity profile info into phone / name if authenticated
  useEffect(() => {
    if (hasIdentity && user) {
      if (user.phone && !phone) {
        setPhone(user.phone);
        phoneRef.current = user.phone;
      }
      if (user.full_name && !name) {
        setName(user.full_name);
      }
    }
  }, [hasIdentity, user]);

  // Solved states
  const [answeredQuestionIds, setAnsweredQuestionIds] = useState<number[]>([]);
  const [cooldowns, setCooldowns] = useState<Record<number, number>>({}); // questionId -> timestamp

  // Question answers states
  const [answers, setAnswers] = useState<Record<number, any>>({});
  const [editingVotes, setEditingVotes] = useState<Record<number, boolean>>({});
  const [voteEditTimeLeft, setVoteEditTimeLeft] = useState<Record<number, string>>({});
  const [surveyItems, setSurveyItems] = useState<Record<number, Record<number, any>>>({});

  // Live vote results
  const [voteResults, setVoteResults] = useState<Record<number, VoteResultsData>>({});
  const pollTimerRef = useRef<Record<number, any>>({});

  // Refs to avoid stale closures during imperative refresh
  const phoneRef = useRef(phone);
  const cooldownsRef = useRef(cooldowns);
  const activeCompRef = useRef(activeComp);
  const upcomingEventRef = useRef(upcomingEvent);
  const lastFetchedPhoneRef = useRef<string | null>(null);

  useEffect(() => {
    phoneRef.current = phone;
  }, [phone]);

  useEffect(() => {
    cooldownsRef.current = cooldowns;
  }, [cooldowns]);

  useEffect(() => {
    activeCompRef.current = activeComp;
  }, [activeComp]);

  useEffect(() => {
    upcomingEventRef.current = upcomingEvent;
  }, [upcomingEvent]);

  // Lightbox Media
  const [lightboxVisible, setLightboxVisible] = useState(false);
  const [lightboxUrl, setLightboxUrl] = useState<string | null>(null);
  const [lightboxTitle, setLightboxTitle] = useState('');
  const [lightboxIsVideo, setLightboxIsVideo] = useState(false);

  // General Countdown Timer States
  const [compTimesLeft, setCompTimesLeft] = useState<Record<number, string>>({});
  const [expandedResults, setExpandedResults] = useState<Record<number, boolean>>({});
  const [revealTimeLeft, setRevealTimeLeft] = useState<Record<number, string>>({});
  const [upcomingTimeLeft, setUpcomingTimeLeft] = useState('');

  // Offline queue
  const [offlineQueue, setOfflineQueue] = useState<{ competitionId: number; questionId: number; payload: any }[]>([]);
  const offlineQueueRef = useRef(offlineQueue);
  useEffect(() => {
    offlineQueueRef.current = offlineQueue;
  }, [offlineQueue]);

  const getVoteResultsData = useCallback(async (compId: number, questionId: number) => {
    const res = await fetchVoteResults(compId, questionId);
    if (res) {
      setVoteResults((prev) => ({ ...prev, [questionId]: res }));
    }
  }, []);

  const startVotePolling = useCallback((compId: number, questionId: number) => {
    if (pollTimerRef.current[questionId]) {
      clearInterval(pollTimerRef.current[questionId]);
    }
    pollTimerRef.current[questionId] = setInterval(() => {
      void getVoteResultsData(compId, questionId);
    }, 15000); // Poll every 15 seconds
  }, [getVoteResultsData]);

  const saveLocalAnswer = async (questionId: number) => {
    try {
      const newCooldowns = { ...cooldownsRef.current, [questionId]: Date.now() };
      cooldownsRef.current = newCooldowns;
      setCooldowns(newCooldowns);
      await appStorage.setItem('quiz_local_cooldowns', JSON.stringify(newCooldowns));

      setAnsweredQuestionIds((prev) => (prev.includes(questionId) ? prev : [...prev, questionId]));
    } catch (err) {
      console.log('Error saving local answer state', err);
    }
  };

  const attemptOfflineSync = useCallback(async (currentQueue: typeof offlineQueue) => {
    if (!currentQueue.length) return;
    const remaining = [];
    for (const item of currentQueue) {
      const res = await submitQuizAnswer(item.competitionId, item.questionId, item.payload);
      if (res && res.success) {
        await saveLocalAnswer(item.questionId);
      } else {
        remaining.push(item);
      }
    }
    offlineQueueRef.current = remaining;
    setOfflineQueue(remaining);
    await appStorage.setItem('quiz_offline_queue', JSON.stringify(remaining));
  }, []);

  const loadData = useCallback(async (isRefresh = false, overridePhone?: string) => {
    try {
      if (!isRefresh && !activeCompRef.current && !upcomingEventRef.current) {
        setLoading(true);
      }

      if (isRefresh && offlineQueueRef.current.length > 0) {
        await attemptOfflineSync(offlineQueueRef.current);
      }

      const targetPhone = (overridePhone ?? phoneRef.current ?? user?.phone ?? '').trim();
      lastFetchedPhoneRef.current = targetPhone;

      const res = await fetchActiveCompetition(targetPhone || undefined);
      if (res) {
        // Clear previous polling timers before registering fresh ones
        Object.values(pollTimerRef.current).forEach(clearInterval);
        pollTimerRef.current = {};

        const comps = (res.active_competitions && res.active_competitions.length > 0)
          ? res.active_competitions
          : (res.active_competition ? [res.active_competition] : []);

        setActiveCompetitions(comps);

        if (comps.length > 0) {
          setActiveComp((prev) => {
            if (prev) {
              const matched = comps.find((c) => c.id === prev.id);
              return matched ?? comps[0];
            }
            return comps[0];
          });
        } else {
          setActiveComp(null);
        }

        setUpcomingEvent(res.upcoming_event);

        // Map initial answers & load vote results for already answered vote questions across all active competitions
        if (comps.length > 0) {
          let currentCooldowns = cooldownsRef.current;
          try {
            const savedCooldowns = await appStorage.getItem('quiz_local_cooldowns');
            if (savedCooldowns) {
              currentCooldowns = JSON.parse(savedCooldowns);
              cooldownsRef.current = currentCooldowns;
              setCooldowns(currentCooldowns);
            }
          } catch {
            // Ignore SecureStore read errors
          }

          const allQuestions = comps.flatMap((c) => c.questions);

          // Map backend answered questions + active local cooldowns
          const answered = allQuestions
            .filter((q) => q.has_answered)
            .map((q) => q.id);

          const activeQuestionIds = new Set(allQuestions.map((q) => q.id));
          const localAnswered = Object.keys(currentCooldowns)
            .map(Number)
            .filter((qId) => activeQuestionIds.has(qId));

          setAnsweredQuestionIds(Array.from(new Set([...answered, ...localAnswered])));

          // Fetch vote results for all vote questions across all active competitions
          const votePromises: Promise<void>[] = [];

          comps.forEach((comp) => {
            comp.questions.forEach((q) => {
              if (q.answer_type === 'vote') {
                if (q.vote_results) {
                  setVoteResults((prev) => ({ ...prev, [q.id]: q.vote_results! }));
                }
                votePromises.push(getVoteResultsData(comp.id, q.id));
                startVotePolling(comp.id, q.id);
              }
            });
          });

          if (votePromises.length > 0) {
            await Promise.allSettled(votePromises);
          }

          // Compute immediate countdown values for all answered vote questions
          const initialVoteTimes: Record<number, string> = {};
          const initialPartialAnswers: Record<number, any> = {};
          allQuestions.forEach((q) => {
            if (q.answer_type === 'vote' && q.user_vote) {
              if (q.user_vote.needs_completion) {
                const isOpenTree = q.candidate_rules?.nomination_type === 'open_tree';
                initialPartialAnswers[q.id] = isOpenTree
                  ? resolveOpenTreeVotePersonIds(q, 'selected')
                  : q.user_vote.selected_choices;
                initialVoteTimes[q.id] = 'مفتوح';
                return;
              }

              const isForbidden = q.vote_edit_duration_hours === 0 || q.user_vote.can_edit === false;
              if (isForbidden) {
                initialVoteTimes[q.id] = 'منتهية';
                q.user_vote.can_edit = false;
              } else if (q.user_vote.edit_until) {
                const editUntilMs = new Date(q.user_vote.edit_until).getTime();
                const diff = editUntilMs - Date.now();
                if (diff > 0 && q.user_vote.can_edit) {
                  initialVoteTimes[q.id] = formatVoteCountdown(diff);
                } else {
                  initialVoteTimes[q.id] = 'منتهية';
                  q.user_vote.can_edit = false;
                }
              } else if (q.user_vote.can_edit) {
                initialVoteTimes[q.id] = 'مفتوح';
              }
            }
          });
          if (Object.keys(initialPartialAnswers).length > 0) {
            setAnswers((prev) => ({ ...prev, ...initialPartialAnswers }));
          }
          setVoteEditTimeLeft(initialVoteTimes);
        } else {
          setAnsweredQuestionIds([]);
          setVoteResults({});
          setVoteEditTimeLeft({});
        }
      }
    } catch (err) {
      console.log('Error loading competition data', err);
    } finally {
      setLoading(false);
    }
  }, [attemptOfflineSync, getVoteResultsData, startVotePolling]);

  useImperativeHandle(
    ref,
    () => ({
      refresh: () => loadData(true),
    }),
    [loadData]
  );

  useEffect(() => {
    let isMounted = true;
    (async () => {
      const savedPhone = await loadSavedCredentials();
      const initialPhone = (hasIdentity && user?.phone) ? user.phone : savedPhone;
      await loadLocalAnswers();
      await loadOfflineQueue();
      if (isMounted) {
        await loadData(false, initialPhone);
      }
    })();

    return () => {
      isMounted = false;
      // Clear all active timers on unmount
      Object.values(pollTimerRef.current).forEach(clearInterval);
    };
  }, [loadData]);

  // Reload in background if user finishes typing a valid 10-digit Saudi phone number
  useEffect(() => {
    const trimmed = phone.trim();
    if (/^05[0-9]{8}$/.test(trimmed) && lastFetchedPhoneRef.current !== null && lastFetchedPhoneRef.current !== trimmed) {
      void loadData(true, trimmed);
    }
  }, [phone, loadData]);

  // Support optional declarative refreshTrigger prop
  useEffect(() => {
    if (refreshTrigger !== undefined && refreshTrigger > 0) {
      void loadData(true);
    }
  }, [refreshTrigger, loadData]);

  // Timers thread
  useEffect(() => {
    const timer = setInterval(() => {
      updateTimers();
    }, 1000);

    return () => clearInterval(timer);
  }, [activeCompetitions, upcomingEvent]);

  async function loadSavedCredentials(): Promise<string> {
    try {
      const savedName = await appStorage.getItem('quiz_user_name');
      const savedPhone = await appStorage.getItem('quiz_user_phone');
      const savedMotherName = await appStorage.getItem('quiz_user_mother_name');

      if (savedPhone) {
        setName(savedName || '');
        setPhone(savedPhone);
        phoneRef.current = savedPhone;
        setMotherName(savedMotherName || '');
        setIsSavedUser(true);
        return savedPhone;
      }
    } catch (err) {
      console.log('Error loading credentials from SecureStore', err);
    }
    return '';
  }

  async function loadLocalAnswers() {
    try {
      const savedCooldowns = await appStorage.getItem('quiz_local_cooldowns');
      if (savedCooldowns) {
        const parsed = JSON.parse(savedCooldowns);
        cooldownsRef.current = parsed;
        setCooldowns(parsed);
      }
    } catch (err) {
      console.log('Error loading local answered state', err);
    }
  }

  async function loadOfflineQueue() {
    try {
      const queue = await appStorage.getItem('quiz_offline_queue');
      if (queue) {
        const parsed = JSON.parse(queue);
        offlineQueueRef.current = parsed;
        setOfflineQueue(parsed);
        if (parsed.length > 0) {
          // Attempt sync if connected
          void attemptOfflineSync(parsed);
        }
      }
    } catch (err) {
      console.log('Error loading offline queue', err);
    }
  }

  const queueOfflineAnswer = async (competitionId: number, questionId: number, payload: any) => {
    try {
      const newQueue = [...offlineQueueRef.current, { competitionId, questionId, payload }];
      offlineQueueRef.current = newQueue;
      setOfflineQueue(newQueue);
      await appStorage.setItem('quiz_offline_queue', JSON.stringify(newQueue));
      CustomAlert.alert(
        'حفظ الإجابة محلياً',
        'تعذر الاتصال بالخادم. تم حفظ إجابتك على جهازك، وسنقوم بمزامنتها تلقائياً بمجرد استقرار شبكة الإنترنت.'
      );
    } catch (err) {
      console.log('Error queueing offline answer', err);
    }
  };

  function updateTimers() {
    if (activeCompetitions.length > 0) {
      const updatedCompTimes: Record<number, string> = {};
      const updatedReveals: Record<number, string> = {};
      const updatedVoteTimes: Record<number, string> = {};
      let shouldRefreshComps = false;

      activeCompetitions.forEach((comp) => {
        // Competition End timer
        if (comp.end_at) {
          const diff = new Date(comp.end_at).getTime() - Date.now();
          if (diff > 0) {
            const days = Math.max(0, Math.ceil(diff / 86400000));
            updatedCompTimes[comp.id] = `${toEnglishDigits(days)} يوم`;
          } else {
            updatedCompTimes[comp.id] = 'انتهت المسابقة';
          }
        }

        // Questions Lock Pre-reveal timers
        if (comp.questions_visible_at) {
          const diff = new Date(comp.questions_visible_at).getTime() - Date.now();
          if (diff > 0) {
            const formatted = toEnglishDigits(Math.ceil(diff / 1000)) + ' ثانية';
            comp.questions.forEach((q) => {
              updatedReveals[q.id] = formatted;
            });
          } else if (Object.keys(revealTimeLeft).length > 0) {
            setRevealTimeLeft({});
            void loadData(true);
          }
        }

        // 24-Hour Vote Edit Countdown timers
        comp.questions.forEach((q) => {
          if (q.answer_type === 'vote' && q.user_vote) {
            if (q.user_vote.needs_completion) {
              updatedVoteTimes[q.id] = 'مفتوح';
              return;
            }
            const isForbidden = q.vote_edit_duration_hours === 0 || q.user_vote.can_edit === false;
            if (isForbidden) {
              updatedVoteTimes[q.id] = 'منتهية';
              if (q.user_vote.can_edit) {
                q.user_vote.can_edit = false;
                shouldRefreshComps = true;
                setEditingVotes((prev) => (prev[q.id] ? { ...prev, [q.id]: false } : prev));
              }
            } else if (q.user_vote.edit_until) {
              const editUntilMs = new Date(q.user_vote.edit_until).getTime();
              const diff = editUntilMs - Date.now();

              if (diff > 0 && q.user_vote.can_edit) {
                updatedVoteTimes[q.id] = formatVoteCountdown(diff);
              } else {
                updatedVoteTimes[q.id] = 'منتهية';
                if (q.user_vote.can_edit) {
                  q.user_vote.can_edit = false;
                  shouldRefreshComps = true;
                  setEditingVotes((prev) => (prev[q.id] ? { ...prev, [q.id]: false } : prev));
                }
              }
            } else if (q.user_vote.can_edit) {
              updatedVoteTimes[q.id] = 'مفتوح';
            }
          }
        });
      });

      setCompTimesLeft(updatedCompTimes);
      setRevealTimeLeft(updatedReveals);
      setVoteEditTimeLeft(updatedVoteTimes);
      if (shouldRefreshComps) {
        setActiveCompetitions((prev) => [...prev]);
      }
    }

    if (upcomingEvent && upcomingEvent.target_at) {
      const diff = new Date(upcomingEvent.target_at).getTime() - Date.now();
      if (diff > 0) {
        setUpcomingTimeLeft(formatDuration(diff));
      } else {
        setUpcomingTimeLeft('');
        void loadData(true); // started
      }
    }
  }

  const startEditingVote = (question: QuizQuestion) => {
    const isOpenTree = question.candidate_rules?.nomination_type === 'open_tree';
    let prevAnswers = answers[question.id];

    if (question.user_vote) {
      if (isOpenTree) {
        const personIds = resolveOpenTreeVotePersonIds(
          question,
          'selected',
          voteResults[question.id]?.results
        );
        prevAnswers =
          question.vote_max_selections > 1 ? personIds : (personIds[0] ?? undefined);
      } else {
        prevAnswers =
          question.vote_max_selections > 1
            ? question.user_vote.selected_choices
            : question.user_vote.selected_choices[0];
      }
    }

    if (prevAnswers != null) {
      setAnswers((prev) => ({
        ...prev,
        [question.id]: prevAnswers,
      }));
    } else if (isOpenTree && question.user_vote) {
      setAnswers((prev) => ({
        ...prev,
        [question.id]: question.vote_max_selections > 1 ? [] : undefined,
      }));
    }
    setEditingVotes((prev) => ({ ...prev, [question.id]: true }));
  };

  const handleOpenVideo = (url: string, title: string) => {
    setLightboxUrl(url);
    setLightboxTitle(title);
    setLightboxIsVideo(true);
    setLightboxVisible(true);
  };

  const handleOpenImage = (url: string, title: string) => {
    setLightboxUrl(url);
    setLightboxTitle(title);
    setLightboxIsVideo(false);
    setLightboxVisible(true);
  };

  const validateCredentials = (): boolean => {
    if (hasIdentity && user) {
      return true;
    }
    const targetPhone = (phone || user?.phone || '').trim();
    if (!targetPhone) {
      CustomAlert.alert('خطأ', 'رقم الهاتف مطلوب للمشاركة للزوار.');
      return false;
    }
    if (!isValidSaudiPhone(targetPhone)) {
      CustomAlert.alert('خطأ', 'يجب إدخال رقم هاتف سعودي صحيح مكون من 10 أرقام ويبدأ بـ 05.');
      return false;
    }
    return true;
  };

  const handleFormSubmit = async (competitionId: number, question: QuizQuestion) => {
    if (!validateCredentials()) return;

    const effectivePhone = (hasIdentity && user?.phone ? user.phone : (phone || user?.phone || '')).trim();
    const effectiveName = (hasIdentity && (user?.full_name || user?.first_name)
      ? (user.full_name || user.first_name)
      : name).trim();

    // Save user info to SecureStore for future visits if not identity verified
    if (!hasIdentity) {
      try {
        await appStorage.setItem('quiz_user_name', name);
        await appStorage.setItem('quiz_user_phone', phone);
        await appStorage.setItem('quiz_user_mother_name', motherName);
        setIsSavedUser(true);
      } catch (err) {
        console.log('SecureStore save credentials error', err);
      }
    }

    // Cooldown verification (2 hours) - only for quiz/survey, voting is governed by backend 24h edit window
    const isEditingVote = Boolean(editingVotes[question.id]);
    const lastAnswered = cooldowns[question.id];
    if (question.answer_type !== 'vote' && !isEditingVote && lastAnswered && Date.now() - lastAnswered < 2 * 60 * 60 * 1000) {
      CustomAlert.alert('تنبيه', 'لقد أجبت على هذا السؤال مسبقاً.');
      return;
    }

    // Read answer based on question type
    let finalAnswer = answers[question.id];
    let finalSurvey = surveyItems[question.id];
    const isOpenTreeVote = question.answer_type === 'vote' && question.candidate_rules?.nomination_type === 'open_tree';

    if (question.answer_type === 'vote') {
      const requiredCount = Math.max(1, question.vote_max_selections || 1);
      const selectedList = Array.isArray(finalAnswer)
        ? finalAnswer.filter((id) => id !== null && id !== undefined && id !== '')
        : (finalAnswer !== null && finalAnswer !== undefined && finalAnswer !== '' ? [finalAnswer] : []);
      const selectedCount = selectedList.length;

      if (selectedCount === 0) {
        CustomAlert.alert(
          'تنبيه',
          isOpenTreeVote
            ? `يرجى تحديد ${requiredCount} متوقعين للفوز من شجرة العائلة لإتمام التصويت.`
            : `يرجى تحديد ${requiredCount} متوقعين للفوز لإتمام التصويت.`
        );
        return;
      }

      if (selectedCount < requiredCount) {
        const remaining = requiredCount - selectedCount;
        CustomAlert.alert(
          'التصويت غير مكتمل',
          `يجب تحديد ${requiredCount} متوقعين للفوز إجبارياً لإتمام التصويت. (فاضل ${remaining} ${remaining === 1 ? 'توقع' : 'توقعات'}).`
        );
        return;
      }

      if (selectedCount > requiredCount) {
        CustomAlert.alert(
          'تنبيه',
          `لا يمكنك تحديد أكثر من ${requiredCount} متوقعين للفوز.`
        );
        return;
      }
    } else if (question.answer_type === 'survey') {
      if (!finalSurvey || Object.keys(finalSurvey).length === 0) {
        CustomAlert.alert('خطأ', 'يرجى إكمال بيانات الاستبيان أولاً.');
        return;
      }
    } else {
      if (
        finalAnswer === undefined ||
        finalAnswer === null ||
        (Array.isArray(finalAnswer) && finalAnswer.length === 0)
      ) {
        CustomAlert.alert('خطأ', 'يرجى تحديد إجابة أولاً.');
        return;
      }
    }

    const payload = {
      name: effectiveName || undefined,
      phone: effectivePhone || undefined,
      mother_name: hasIdentity ? undefined : (motherName.trim() || undefined),
      is_from_ancestry: isFromAncestry,
      answer: finalAnswer,
      survey_item: finalSurvey,
      person_ids: isOpenTreeVote ? finalAnswer : undefined,
    };

    setSubmitting(true);
    try {
      const res = await submitQuizAnswer(competitionId, question.id, payload);

      if (res) {
        if (res.success) {
          saveLocalAnswer(question.id);
          
          if (question.answer_type === 'vote') {
            const finalChoices = Array.isArray(finalAnswer)
              ? finalAnswer
              : (finalAnswer != null ? [finalAnswer] : []);

            // Preserve initial first_voted_at from existing question.user_vote when editing
            const existingUserVote = question.user_vote;
            const rawFirstVotedAt =
              res.user_vote?.first_voted_at ||
              existingUserVote?.first_voted_at ||
              new Date().toISOString();
            const rawEditUntil =
              res.user_vote?.edit_until ??
              existingUserVote?.edit_until ??
              null;
            const diffMs = rawEditUntil ? new Date(rawEditUntil).getTime() - Date.now() : null;
            const canEditStill = res.user_vote?.can_edit ?? (diffMs !== null ? diffMs > 0 : true);

            const newUserVote = {
              can_edit: canEditStill,
              needs_completion: false,
              first_voted_at: rawFirstVotedAt,
              edit_until: rawEditUntil,
              seconds_remaining: diffMs !== null ? Math.max(0, Math.floor(diffMs / 1000)) : null,
              selected_choices: res.user_vote?.selected_choices ?? finalChoices,
              selected_person_ids: isOpenTreeVote
                ? (res.user_vote?.selected_person_ids ?? finalChoices)
                : undefined,
              locked_choices:
                res.user_vote?.locked_choices ?? existingUserVote?.locked_choices ?? [],
              locked_person_ids:
                res.user_vote?.locked_person_ids ?? existingUserVote?.locked_person_ids ?? [],
            };

            question.user_vote = newUserVote;
            if (diffMs !== null && diffMs > 0) {
              setVoteEditTimeLeft((prev) => ({
                ...prev,
                [question.id]: formatVoteCountdown(diffMs),
              }));
            } else if (canEditStill && rawEditUntil === null) {
              setVoteEditTimeLeft((prev) => ({
                ...prev,
                [question.id]: 'مفتوح',
              }));
            }
            setEditingVotes((prev) => ({ ...prev, [question.id]: false }));
            setActiveCompetitions((prev) =>
              prev.map((c) =>
                c.id === competitionId
                  ? {
                      ...c,
                      questions: c.questions.map((q) =>
                        q.id === question.id ? { ...q, user_vote: newUserVote, has_answered: true } : q
                      ),
                    }
                  : c
              )
            );

            getVoteResultsData(competitionId, question.id);
            startVotePolling(competitionId, question.id);
          }

          CustomAlert.alert(
            question.answer_type === 'vote' ? 'نجح التصويت' : 'نجح الإرسال',
            question.answer_type === 'vote'
              ? 'تم تسجيل صوتك بنجاح.'
              : (res.is_correct ? 'تم إرسال إجابتك بنجاح وهي إجابة صحيحة! 🎉' : 'تم استلام إجابتك بنجاح. شكراً لمشاركتك.')
          );
        } else {
          CustomAlert.alert('فشل الإرسال', res.message);
        }
      } else {
        // Network failure, queue offline
        await queueOfflineAnswer(competitionId, question.id, payload);
      }
    } catch (err) {
      console.log('Error submitting answer', err);
      CustomAlert.alert('خطأ', 'حدث خطأ غير متوقع أثناء إرسال إجابتك.');
    } finally {
      setSubmitting(false);
    }
  };


  if (loading) {
    return (
      <View
        style={[
          styles.widgetCard,
          {
            backgroundColor: colors.card,
            borderColor: colors.border,
            padding: 16,
          },
        ]}
      >
        <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
          <Skeleton width={110} height={16} borderRadius={6} />
          <Skeleton.Circle size={10} />
        </View>
        <Skeleton width="65%" height={16} borderRadius={4} style={{ marginBottom: 10 }} />
        <Skeleton.Text lines={2} lineHeight={12} gap={6} lastLineWidth="80%" />
      </View>
    );
  }

  // State 1: No active competition and no upcoming event
  if (activeCompetitions.length === 0 && !upcomingEvent) {
    return null;
  }

  // State 2: Upcoming Event countdown (only when no active competitions)
  if (upcomingEvent && activeCompetitions.length === 0) {
    return (
      <View style={[styles.widgetCard, { backgroundColor: colors.card, borderColor: colors.border }]}>
        <LinearGradient
          colors={[colors.primary + '11', 'transparent']}
          style={StyleSheet.absoluteFill}
          start={{ x: 1, y: 0 }}
          end={{ x: 0, y: 1 }}
        />
        <View style={styles.widgetHeader}>
          <IconSymbol name="calendar" size={20} color={colors.primary} />
          <ThemedText style={[styles.widgetTitle, { color: colors.text }]}>
            {upcomingEvent.title || upcomingEvent.section_title || 'المسابقات والفعاليات'}
          </ThemedText>
        </View>
        <View style={styles.upcomingContent}>
          <ThemedText style={[styles.upcomingTitle, { color: colors.text }]}>
            {upcomingEvent.title}
          </ThemedText>
          {upcomingEvent.description && (
            <ThemedText style={[styles.upcomingDesc, { color: colors.icon }]}>
              {upcomingEvent.description}
            </ThemedText>
          )}
          {upcomingTimeLeft !== '' && (
            <View style={styles.timerContainer}>
              <ThemedText style={[styles.timerLabel, { color: colors.icon }]}>يبدأ بعد:</ThemedText>
              <ThemedText style={styles.timerDigits}>{upcomingTimeLeft}</ThemedText>
            </View>
          )}
        </View>
      </View>
    );
  }

  // State 3: Active Competitions - Render ALL active competitions sequentially
  return (
    <View style={styles.competitionsListWrapper}>
      {activeCompetitions.map((comp) => {
        if ((!comp.questions || comp.questions.length === 0) && !comp.show_draw_only) {
          return null;
        }
        const compCountdown = compTimesLeft[comp.id] || '';
        const isWalkingComp = comp.questions.some((q) => q.answer_type === 'walking');

        return (
          <View
            key={comp.id}
            style={[
              styles.widgetCard,
              {
                backgroundColor: colors.card,
                borderColor: isDark ? colors.primary + '44' : '#a7f3d0',
              },
            ]}
          >
            {/* Absolute Backdrop Gradient */}
            <LinearGradient
              colors={['rgba(16, 185, 129, 0.04)', 'transparent']}
              style={StyleSheet.absoluteFill}
              start={{ x: 1, y: 0 }}
              end={{ x: 0, y: 1 }}
            />

            {/* Competition Title & Countdown Timer Row */}
            <View style={styles.compInfo}>
              {(Boolean(comp.title?.trim()) || compCountdown !== '') && (
                <View style={styles.titleTimerRow}>
                  {Boolean(comp.title?.trim()) && (
                    <ThemedText style={[styles.compTitle, { color: isDark ? '#34d399' : '#064e3b' }]}>
                      {comp.title}
                    </ThemedText>
                  )}

                  {compCountdown !== '' && (
                    <View
                      style={[
                        styles.timerBadge,
                        {
                          backgroundColor: isDark ? '#78350f30' : '#fef3c7',
                          borderColor: isDark ? '#b45309' : '#fde68a',
                        },
                      ]}
                    >
                      <IconSymbol name="hourglass" size={12} color={isDark ? '#fbbf24' : '#d97706'} />
                      <ThemedText style={[styles.timerBadgeText, { color: isDark ? '#fbbf24' : '#92400e' }]}>
                        ينتهي بعد: {compCountdown}
                      </ThemedText>
                    </View>
                  )}
                </View>
              )}

              {isRealHtml(comp.description) && (
                <HtmlContent
                  html={comp.description}
                  contentWidth={HTML_CONTENT_WIDTH}
                  textColor={colors.icon}
                />
              )}
            </View>

            {/* Questions List */}
            <View style={styles.questionsContainer}>
              {comp.questions.map((question) => {
                const hasAnswered = answeredQuestionIds.includes(question.id);
                const needsVoteCompletion = Boolean(
                  question.answer_type === 'vote' && question.user_vote?.needs_completion
                );
                const lockedVoteCount =
                  question.user_vote?.locked_person_ids?.length ||
                  question.user_vote?.locked_choices?.length ||
                  0;
                const isPreLocked = comp.is_locked;
                const isWalking = question.answer_type === 'walking';
                const isVoterIneligible = Boolean(
                  question.answer_type === 'vote' &&
                    question.viewer_eligibility &&
                    !question.viewer_eligibility.eligible
                );
                const currentResults = voteResults[question.id] || question.vote_results;
                const isOpenTree = question.candidate_rules?.nomination_type === 'open_tree';
                const isStandingsOpen = Boolean(expandedResults[question.id]);

                return (
                  <View key={question.id} style={styles.questionCard}>
                    {/* Question Text & Description */}
                    {isRealHtml(question.description) && question.description !== comp.description && (
                      <View style={{ width: '100%', marginBottom: 6 }}>
                        <HtmlContent
                          html={question.description}
                          contentWidth={HTML_CONTENT_WIDTH - 24}
                          textColor={colors.icon}
                        />
                      </View>
                    )}

                    {question.answer_type !== 'fill_blank' && Boolean(question.question_text?.trim()) && (
                      <ThemedText style={[styles.questionText, { color: colors.text }]}>
                        {question.question_text}
                      </ThemedText>
                    )}

                    {/* Question Media (Image / Video) */}
                    {question.image_url && (
                      <TouchableOpacity
                        style={styles.questionMediaCard}
                        onPress={() =>
                          handleOpenImage(question.image_url!, question.question_text || 'صورة السؤال')
                        }
                        activeOpacity={0.88}
                      >
                        <Image
                          source={{ uri: question.image_url }}
                          style={styles.questionMediaImage}
                          contentFit="cover"
                        />
                      </TouchableOpacity>
                    )}

                    {(question.youtube_url || question.video_url || question.media_url) && (
                      <TouchableOpacity
                        style={[
                          styles.questionMediaCard,
                          styles.questionVideoCard,
                          { borderColor: colors.border, backgroundColor: colors.background },
                        ]}
                        onPress={() =>
                          handleOpenVideo(
                            question.youtube_url || question.video_url || question.media_url!,
                            question.question_text || 'فيديو السؤال'
                          )
                        }
                        activeOpacity={0.88}
                      >
                        <View style={styles.questionVideoPlaceholder}>
                          <IconSymbol name="play.circle.fill" size={44} color="#10b981" />
                          <ThemedText style={[styles.questionVideoLabel, { color: colors.icon }]}>
                            تشغيل الفيديو المرفق مع السؤال
                          </ThemedText>
                        </View>
                      </TouchableOpacity>
                    )}

                    {/* State A: Pre-lock reveal countdown */}
                    {isPreLocked ? (
                      <View
                        style={[
                          styles.lockedContainer,
                          { backgroundColor: isDark ? '#1e293b' : '#fafaf9' },
                        ]}
                      >
                        <IconSymbol name="lock.fill" size={24} color="#f59e0b" />
                        <ThemedText style={[styles.lockedText, { color: isDark ? '#94a3b8' : '#78716c' }]}>
                          يتم الكشف عن السؤال وخيارات الإجابة بعد:
                        </ThemedText>
                        <ThemedText
                          style={[
                            styles.lockedTimer,
                            {
                              backgroundColor: isDark ? '#78350f40' : '#fef3c7',
                              color: isDark ? '#f59e0b' : '#d97706',
                            },
                          ]}
                        >
                          {revealTimeLeft[question.id] || 'ثوانٍ معدودة...'}
                        </ThemedText>
                      </View>
                    ) : question.answer_type === 'walking' ? (
                      question.walking_data?.competition_scope === 'groups' ? (
                        <WalkingGroupBattleCard
                          question={question}
                          colors={colors}
                          onRefresh={() => void loadData(true)}
                        />
                      ) : (
                        <WalkingChallengeCard
                          question={question}
                          colors={colors}
                          onRefresh={() => void loadData(true)}
                        />
                      )
                    ) : question.answer_type === 'walking_prediction' || question.is_walking_prediction ? (
                      <WalkingPredictionCard
                        question={question}
                        colors={colors}
                        onRefresh={() => void loadData(true)}
                      />
                    ) : hasAnswered && !editingVotes[question.id] && !needsVoteCompletion ? (
                      /* State B: Already Answered / Voted */
                      <View style={styles.answeredContainer}>
                        {question.answer_type === 'vote' ? (
                          <View style={{ width: '100%', gap: 12 }}>
                            {currentResults && (
                              <VoteNomineesLeaderboard
                                title={question.question_text?.trim() || undefined}
                                results={currentResults.results}
                                totalVotes={currentResults.total_votes}
                                selectedIds={[
                                  ...(question.user_vote?.selected_choices || []),
                                  ...(question.user_vote?.selected_person_ids || []),
                                ]}
                                hasVoted={true}
                                colors={colors}
                                isDark={isDark}
                                choices={question.choices}
                                isOpenTree={isOpenTree}
                              />
                            )}

                            {/* 24-Hour Vote Edit Option Card */}
                            <View
                              style={[
                                styles.voteEditBannerCard,
                                {
                                  backgroundColor: isDark ? '#1e293b' : '#f8fafc',
                                  borderColor: isDark ? '#334155' : '#e2e8f0',
                                },
                              ]}
                            >
                              {!question.user_vote ||
                              (question.user_vote.can_edit &&
                                voteEditTimeLeft[question.id] !== 'منتهية') ? (
                                <View style={styles.voteEditBannerContent}>
                                  <View style={styles.voteEditHeaderRow}>
                                    <View style={styles.voteEditClockBadge}>
                                      <IconSymbol name="clock.arrow.circlepath" size={16} color="#10b981" />
                                      <ThemedText
                                        style={[
                                          styles.voteEditBadgeText,
                                          { color: isDark ? '#34d399' : '#059669' },
                                        ]}
                                      >
                                        إمكانية تعديل التصويت متاحة
                                      </ThemedText>
                                    </View>
                                    <View
                                      style={[
                                        styles.voteEditTimerBadge,
                                        {
                                          backgroundColor: isDark ? '#78350f30' : '#fef3c7',
                                          borderColor: isDark ? '#b45309' : '#fde68a',
                                        },
                                      ]}
                                    >
                                      <IconSymbol name="hourglass" size={12} color={isDark ? '#fbbf24' : '#d97706'} />
                                      <ThemedText
                                        style={[
                                          styles.voteEditCountdownText,
                                          { color: isDark ? '#fbbf24' : '#92400e' },
                                        ]}
                                      >
                                        {voteEditTimeLeft[question.id] === 'مفتوح' ||
                                        (question.vote_edit_duration_hours === -1 &&
                                          !question.user_vote?.edit_until)
                                          ? 'متاح في أي وقت'
                                          : voteEditTimeLeft[question.id] &&
                                            voteEditTimeLeft[question.id] !== 'منتهية'
                                          ? `متبقي: ${voteEditTimeLeft[question.id]}`
                                          : `متبقي: ${toEnglishDigits(
                                              String(question.vote_edit_duration_hours ?? 24).padStart(2, '0')
                                            )}:00:00`}
                                      </ThemedText>
                                    </View>
                                  </View>
                                  <ThemedText
                                    style={[
                                      styles.voteEditRuleNote,
                                      { color: isDark ? '#94a3b8' : '#64748b' },
                                    ]}
                                  >
                                    {question.vote_edit_duration_hours === -1 ||
                                    (!question.user_vote?.edit_until &&
                                      question.vote_edit_duration_hours == null)
                                      ? 'يمكنك تعديل اختياراتك بحرية في أي وقت طوال فترة التصويت.'
                                      : `يمكنك تعديل اختياراتك بحرية خلال ${
                                          question.vote_edit_duration_hours ?? 24
                                        } ساعة من تاريخ تصويتك الأول.`}
                                  </ThemedText>
                                  <TouchableOpacity
                                    style={styles.voteEditActionBtn}
                                    onPress={() => startEditingVote(question)}
                                    activeOpacity={0.85}
                                  >
                                    <IconSymbol name="pencil" size={16} color="#ffffff" />
                                    <ThemedText style={styles.voteEditActionBtnText}>
                                      تعديل خيارات التصويت
                                    </ThemedText>
                                  </TouchableOpacity>
                                </View>
                              ) : (
                                <View style={styles.voteEditLockedRow}>
                                  <IconSymbol name="lock.fill" size={14} color={isDark ? '#94a3b8' : '#64748b'} />
                                  <ThemedText
                                    style={[
                                      styles.voteEditLockedText,
                                      { color: isDark ? '#94a3b8' : '#64748b' },
                                    ]}
                                  >
                                    {question.vote_edit_duration_hours === 0
                                      ? 'تم تثبيت تصويتك نهائياً (غير متاح للتعديل بعد الإرسال).'
                                      : `تم تثبيت تصويتك نهائياً (انتهت مهلة الـ ${
                                          question.vote_edit_duration_hours ?? 24
                                        } ساعة من أول تصويت).`}
                                  </ThemedText>
                                </View>
                              )}
                            </View>
                          </View>
                        ) : (
                          <View style={styles.answeredSuccess}>
                            <IconSymbol name="checkmark.seal.fill" size={44} color="#10b981" />
                            <ThemedText
                              style={[
                                styles.answeredSuccessTitle,
                                { color: isDark ? '#34d399' : '#064e3b' },
                              ]}
                            >
                              تم تسجيل مشاركتك مسبقاً
                            </ThemedText>
                            <ThemedText
                              style={[
                                styles.answeredSuccessDesc,
                                { color: isDark ? '#94a3b8' : colors.icon },
                              ]}
                            >
                              لا يمكنك الإجابة مجدداً إلا بعد انتهاء فترة التهدئة (ساعتين).
                            </ThemedText>
                          </View>
                        )}
                      </View>
                    ) : isVoterIneligible ? (
                      /* State C: Ineligible voter */
                      <View style={styles.ineligibleContainer}>
                        <View
                          style={[
                            styles.ineligibleBox,
                            {
                              backgroundColor: isDark ? '#450a0a50' : '#fef2f2',
                              borderColor: isDark ? '#7f1d1d' : '#fecaca',
                            },
                          ]}
                        >
                          <View style={styles.ineligibleHeaderRow}>
                            <IconSymbol name="exclamationmark.triangle.fill" size={20} color="#ef4444" />
                            <ThemedText style={styles.ineligibleTitleText}>
                              غير مؤهل للمشاركة في هذا التصويت
                            </ThemedText>
                          </View>
                          <ThemedText
                            style={[
                              styles.ineligibleDescText,
                              { color: isDark ? '#fca5a5' : '#991b1b' },
                            ]}
                          >
                            {question.viewer_eligibility!.message}
                          </ThemedText>
                          {question.viewer_eligibility!.code === 'AUTH_REQUIRED' && (
                            <TouchableOpacity
                              style={styles.identityLoginBtn}
                              onPress={() => lock()}
                              activeOpacity={0.8}
                            >
                              <ThemedText style={styles.identityLoginBtnText}>
                                تسجيل الدخول بالواتساب للتحقق من الأهلية
                              </ThemedText>
                            </TouchableOpacity>
                          )}
                        </View>

                        {currentResults && (
                          <VoteNomineesLeaderboard
                            title={question.question_text?.trim() || undefined}
                            results={currentResults.results}
                            totalVotes={currentResults.total_votes}
                            colors={colors}
                            isDark={isDark}
                            choices={question.choices}
                            isOpenTree={isOpenTree}
                          />
                        )}
                      </View>
                    ) : (
                      /* State D: Needs Answer / Active Voting Form */
                      <View style={styles.formContainer}>
                        {/* Participant Inputs */}
                        {hasIdentity && user ? null : isSavedUser ? (
                          <View
                            style={[
                              styles.savedUserBadge,
                              {
                                backgroundColor: isDark ? '#1e293b' : '#f1f5f9',
                                borderColor: isDark ? '#334155' : '#e2e8f0',
                              },
                            ]}
                          >
                            <ThemedText
                              style={[
                                styles.savedUserText,
                                { color: isDark ? '#94a3b8' : colors.icon },
                              ]}
                            >
                              المشارك: {name || toEnglishDigits(phone)}
                            </ThemedText>
                            <TouchableOpacity
                              onPress={() => {
                                appStorage.removeItem('quiz_user_phone');
                                setIsSavedUser(false);
                              }}
                              hitSlop={8}
                            >
                              <ThemedText
                                style={{
                                  color: colors.primary,
                                  fontSize: 11,
                                  fontFamily: 'Tajawal-Bold',
                                }}
                              >
                                (تغيير)
                              </ThemedText>
                            </TouchableOpacity>
                          </View>
                        ) : (
                          <View style={styles.userInputsContainer}>
                            <ThemedText
                              style={[
                                styles.inputGroupTitle,
                                { color: isDark ? '#94a3b8' : colors.icon },
                              ]}
                            >
                              بيانات المشارك:
                            </ThemedText>

                            {!question.require_prior_registration && (
                              <TextInput
                                style={[
                                  styles.input,
                                  {
                                    borderColor: isDark ? '#334155' : colors.border,
                                    color: isDark ? '#f8fafc' : colors.text,
                                    backgroundColor: isDark ? '#1e293b' : '#ffffff',
                                  },
                                ]}
                                placeholder="الاسم الكامل"
                                placeholderTextColor={colors.icon}
                                value={name}
                                onChangeText={setName}
                              />
                            )}

                            <TextInput
                              style={[
                                styles.input,
                                {
                                  borderColor: isDark ? '#334155' : colors.border,
                                  color: isDark ? '#f8fafc' : colors.text,
                                  backgroundColor: isDark ? '#1e293b' : '#ffffff',
                                },
                              ]}
                              placeholder="رقم الهاتف (05xxxxxxxx)"
                              placeholderTextColor={colors.icon}
                              keyboardType="phone-pad"
                              maxLength={10}
                              value={phone}
                              onChangeText={setPhone}
                            />

                            {!question.require_prior_registration && (
                              <TextInput
                                style={[
                                  styles.input,
                                  {
                                    borderColor: isDark ? '#334155' : colors.border,
                                    color: isDark ? '#f8fafc' : colors.text,
                                    backgroundColor: isDark ? '#1e293b' : '#ffffff',
                                  },
                                ]}
                                placeholder="اسم الأم (للمشاركين من الأنساب)"
                                placeholderTextColor={colors.icon}
                                value={motherName}
                                onChangeText={setMotherName}
                              />
                            )}
                          </View>
                        )}

                        {/* Informational Banners for Edit / Complete Voting */}
                        {needsVoteCompletion && (
                          <View
                            style={[
                              styles.activeEditModeBanner,
                              {
                                backgroundColor: isDark ? '#1e3a8a35' : '#eff6ff',
                                borderColor: '#3b82f6',
                              },
                            ]}
                          >
                            <View style={styles.activeEditHeader}>
                              <IconSymbol name="lock.fill" size={16} color="#2563eb" />
                              <ThemedText
                                style={[
                                  styles.activeEditTitle,
                                  { color: isDark ? '#93c5fd' : '#1d4ed8' },
                                ]}
                              >
                                استكمال توقعات الفوز
                              </ThemedText>
                            </View>
                            <ThemedText
                              style={[
                                styles.activeEditDesc,
                                { color: isDark ? '#bfdbfe' : '#1e40af' },
                              ]}
                            >
                              تم زيادة عدد المتوقعين للفوز إلى{' '}
                              {toEnglishDigits(question.vote_max_selections || 1)} — اختياراتك السابقة
                              (1 إلى {toEnglishDigits(lockedVoteCount)}) محفوظة ومثبتة 🔒، يرجى استكمال
                              المتوقعين للفوز من {toEnglishDigits(lockedVoteCount + 1)} إلى{' '}
                              {toEnglishDigits(question.vote_max_selections || 1)}.
                            </ThemedText>
                          </View>
                        )}

                        {editingVotes[question.id] && (
                          <View
                            style={[
                              styles.activeEditModeBanner,
                              {
                                backgroundColor: isDark ? '#064e3b25' : '#ecfdf5',
                                borderColor: '#10b981',
                              },
                            ]}
                          >
                            <View style={styles.activeEditHeader}>
                              <IconSymbol name="pencil.circle.fill" size={18} color="#10b981" />
                              <ThemedText
                                style={[
                                  styles.activeEditTitle,
                                  { color: isDark ? '#34d399' : '#065f46' },
                                ]}
                              >
                                وضع تعديل التصويت
                              </ThemedText>
                            </View>
                            <ThemedText
                              style={[
                                styles.activeEditDesc,
                                { color: isDark ? '#a7f3d0' : '#047857' },
                              ]}
                            >
                              {lockedVoteCount > 0
                                ? `اختياراتك السابقة (1 إلى ${toEnglishDigits(
                                    lockedVoteCount
                                  )}) مثبتة 🔒 — يمكنك تعديل الاختيارات من ${toEnglishDigits(
                                    lockedVoteCount + 1
                                  )} إلى ${toEnglishDigits(
                                    question.vote_max_selections || 1
                                  )} فقط (المهلة المتبقية: ${voteEditTimeLeft[question.id] || '...'}).`
                                : `قم بتغيير اختياراتك، ثم اضغط على حفظ التعديل أدناه (المهلة المتبقية: ${
                                    voteEditTimeLeft[question.id] || '...'
                                  }).`}
                            </ThemedText>
                          </View>
                        )}

                        {/* Question Inputs */}
                        <View style={styles.inputWrapper}>
                          {question.answer_type === 'vote' && (
                            <>
                              {isOpenTree ? (
                                <OpenTreeCandidateSelector
                                  questionId={question.id}
                                  candidateRules={question.candidate_rules}
                                  selectedIds={
                                    Array.isArray(answers[question.id])
                                      ? answers[question.id]
                                      : answers[question.id]
                                      ? [answers[question.id]]
                                      : []
                                  }
                                  lockedIds={resolveOpenTreeVotePersonIds(
                                    question,
                                    'locked',
                                    voteResults[question.id]?.results
                                  )}
                                  maxSelections={question.vote_max_selections || 1}
                                  onSelect={(ids) =>
                                    setAnswers((prev) => ({ ...prev, [question.id]: ids }))
                                  }
                                  colors={colors}
                                  isDark={isDark}
                                />
                              ) : (
                                <VoteOptionSelector
                                  choices={question.choices}
                                  selectedIds={answers[question.id] || []}
                                  lockedIds={question.user_vote?.locked_choices || []}
                                  isMultiple={question.vote_max_selections > 1}
                                  maxSelections={question.vote_max_selections || 1}
                                  onSelect={(ids) =>
                                    setAnswers((prev) => ({ ...prev, [question.id]: ids }))
                                  }
                                  onVideoPress={handleOpenVideo}
                                  colors={colors}
                                  isDark={isDark}
                                />
                              )}
                            </>
                          )}

                          {question.answer_type === 'multiple_choice' && (
                            <VoteOptionSelector
                              choices={question.choices}
                              selectedIds={answers[question.id] ? [answers[question.id]] : []}
                              isMultiple={false}
                              onSelect={(ids) =>
                                setAnswers((prev) => ({ ...prev, [question.id]: ids[0] }))
                              }
                              onVideoPress={handleOpenVideo}
                              colors={colors}
                            />
                          )}

                          {question.answer_type === 'ordering' && (
                            <OrderingGrid
                              choices={question.choices}
                              orderedIds={answers[question.id] || []}
                              onChange={(ids) =>
                                setAnswers((prev) => ({ ...prev, [question.id]: ids }))
                              }
                              colors={colors}
                            />
                          )}

                          {question.answer_type === 'true_false' && (
                            <TrueFalseList
                              choices={question.choices}
                              value={answers[question.id] || {}}
                              onChange={(val) =>
                                setAnswers((prev) => ({ ...prev, [question.id]: val }))
                              }
                              colors={colors}
                            />
                          )}

                          {question.answer_type === 'fill_blank' && (
                            <FillInBlankSentence
                              questionText={question.question_text}
                              choices={question.choices}
                              selectedId={answers[question.id] || null}
                              onChange={(id) =>
                                setAnswers((prev) => ({ ...prev, [question.id]: id }))
                              }
                              colors={colors}
                            />
                          )}

                          {question.answer_type === 'survey' && (
                            <View style={{ gap: 14 }}>
                              {question.survey_items.map((si) => (
                                <View
                                  key={si.id}
                                  style={[
                                    styles.surveyItemBox,
                                    { borderColor: colors.border, backgroundColor: colors.background },
                                  ]}
                                >
                                  {si.body_text && (
                                    <ThemedText style={[styles.surveyItemBody, { color: colors.text }]}>
                                      {si.body_text}
                                    </ThemedText>
                                  )}

                                  {si.media_url && si.block_type === 'image' && (
                                    <TouchableOpacity
                                      style={styles.surveyMediaCard}
                                      onPress={() =>
                                        handleOpenImage(si.media_url!, si.body_text || 'صورة الاستبيان')
                                      }
                                      activeOpacity={0.88}
                                    >
                                      <Image
                                        source={{ uri: si.media_url }}
                                        style={styles.surveyMediaImage}
                                        contentFit="cover"
                                      />
                                    </TouchableOpacity>
                                  )}

                                  {(si.youtube_url || (si.media_url && si.block_type === 'video')) && (
                                    <TouchableOpacity
                                      style={[
                                        styles.surveyMediaCard,
                                        styles.surveyVideoCard,
                                        {
                                          borderColor: colors.border,
                                          backgroundColor: colors.background,
                                        },
                                      ]}
                                      onPress={() =>
                                        handleOpenVideo(
                                          si.youtube_url || si.media_url!,
                                          si.body_text || 'فيديو الاستبيان'
                                        )
                                      }
                                      activeOpacity={0.88}
                                    >
                                      <View style={styles.surveyVideoPlaceholder}>
                                        <IconSymbol name="play.circle.fill" size={44} color="#10b981" />
                                        <ThemedText
                                          style={[styles.surveyVideoLabel, { color: colors.icon }]}>
                                          تشغيل الفيديو التوضيحي
                                        </ThemedText>
                                      </View>
                                    </TouchableOpacity>
                                  )}

                                  {si.response_kind === 'rating' && (
                                    <StarRatingInput
                                      value={surveyItems[question.id]?.[si.id] || 0}
                                      maxRating={si.rating_max || 5}
                                      onChange={(val) =>
                                        setSurveyItems((prev) => ({
                                          ...prev,
                                          [question.id]: {
                                            ...(prev[question.id] || {}),
                                            [si.id]: val,
                                          },
                                        }))
                                      }
                                      colors={colors}
                                    />
                                  )}

                                  {si.response_kind === 'number' && (
                                    <TextInput
                                      style={[
                                        styles.input,
                                        {
                                          borderColor: colors.border,
                                          color: colors.text,
                                          backgroundColor: colors.background,
                                        },
                                      ]}
                                      placeholder={`أدخل رقم من ${toEnglishDigits(
                                        si.number_min
                                      )} إلى ${toEnglishDigits(si.number_max)}`}
                                      placeholderTextColor={colors.icon}
                                      keyboardType="number-pad"
                                      value={String(surveyItems[question.id]?.[si.id] || '')}
                                      onChangeText={(val) =>
                                        setSurveyItems((prev) => ({
                                          ...prev,
                                          [question.id]: {
                                            ...(prev[question.id] || {}),
                                            [si.id]: parseInt(val, 10) || 0,
                                          },
                                        }))
                                      }
                                    />
                                  )}

                                  {si.response_kind === 'text' && (
                                    <TextInput
                                      style={[
                                        styles.textarea,
                                        {
                                          borderColor: colors.border,
                                          color: colors.text,
                                          backgroundColor: colors.background,
                                        },
                                      ]}
                                      placeholder="اكتب إجابتك هنا..."
                                      placeholderTextColor={colors.icon}
                                      multiline
                                      numberOfLines={3}
                                      value={surveyItems[question.id]?.[si.id] || ''}
                                      onChangeText={(val) =>
                                        setSurveyItems((prev) => ({
                                          ...prev,
                                          [question.id]: {
                                            ...(prev[question.id] || {}),
                                            [si.id]: val,
                                          },
                                        }))
                                      }
                                    />
                                  )}
                                </View>
                              ))}
                            </View>
                          )}

                          {question.answer_type === 'custom' && (
                            <TextInput
                              style={[
                                styles.textarea,
                                {
                                  borderColor: colors.border,
                                  color: colors.text,
                                  backgroundColor: colors.background,
                                },
                              ]}
                              placeholder="اكتب إجابتك هنا..."
                              placeholderTextColor={colors.icon}
                              multiline
                              numberOfLines={4}
                              value={answers[question.id] || ''}
                              onChangeText={(val) =>
                                setAnswers((prev) => ({ ...prev, [question.id]: val }))
                              }
                            />
                          )}
                        </View>

                        {/* Submit Button */}
                        <TouchableOpacity
                          style={[styles.submitBtn, { backgroundColor: '#10b981' }]}
                          onPress={() => handleFormSubmit(comp.id, question)}
                          disabled={submitting}
                        >
                          {submitting ? (
                            <ActivityIndicator size="small" color="#fff" />
                          ) : (
                            <>
                              <ThemedText style={styles.submitBtnText}>
                                {needsVoteCompletion
                                  ? 'تأكيد واستكمال التصويت'
                                  : editingVotes[question.id]
                                  ? 'حفظ وتأكيد التعديل'
                                  : question.answer_type === 'vote'
                                  ? 'إرسال التصويت'
                                  : 'إرسال الإجابة'}
                              </ThemedText>
                              <IconSymbol
                                name="paperplane.fill"
                                size={14}
                                color="#fff"
                                style={{ marginLeft: 6, transform: [{ scaleX: -1 }] }}
                              />
                            </>
                          )}
                        </TouchableOpacity>

                        {editingVotes[question.id] && (
                          <TouchableOpacity
                            style={[
                              styles.cancelEditBtn,
                              {
                                borderColor: isDark ? '#475569' : '#cbd5e1',
                                backgroundColor: isDark ? '#1e293b' : '#f1f5f9',
                              },
                            ]}
                            onPress={() =>
                              setEditingVotes((prev) => ({ ...prev, [question.id]: false }))
                            }
                            disabled={submitting}
                          >
                            <ThemedText
                              style={[
                                styles.cancelEditBtnText,
                                { color: isDark ? '#94a3b8' : '#64748b' },
                              ]}
                            >
                              إلغاء التعديل
                            </ThemedText>
                          </TouchableOpacity>
                        )}

                        {/* Collapsible Live Standings Drawer below submit button */}
                        {question.answer_type === 'vote' && currentResults && (
                          <View style={styles.standingsDrawerContainer}>
                            <TouchableOpacity
                              style={[
                                styles.standingsDrawerBtn,
                                {
                                  backgroundColor: isDark ? '#1e293b' : '#f8fafc',
                                  borderColor: isDark ? '#334155' : '#e2e8f0',
                                },
                              ]}
                              onPress={() =>
                                setExpandedResults((prev) => ({
                                  ...prev,
                                  [question.id]: !prev[question.id],
                                }))
                              }
                              activeOpacity={0.8}
                            >
                              <View style={styles.standingsDrawerBtnContent}>
                                <Ionicons
                                  name={isStandingsOpen ? 'chevron-up-circle' : 'bar-chart-outline'}
                                  size={18}
                                  color={isDark ? '#34d399' : '#059669'}
                                />
                                <ThemedText
                                  style={[
                                    styles.standingsDrawerBtnText,
                                    { color: isDark ? '#cbd5e1' : '#334155' },
                                  ]}
                                >
                                  {isStandingsOpen
                                    ? 'إخفاء الترتيب والنتائج الحالية'
                                    : 'عرض الترتيب والنتائج الحالية المباشرة'}
                                </ThemedText>
                              </View>
                              <Ionicons
                                name={isStandingsOpen ? 'chevron-up' : 'chevron-down'}
                                size={16}
                                color={isDark ? '#94a3b8' : '#64748b'}
                              />
                            </TouchableOpacity>

                            {isStandingsOpen && (
                              <View style={styles.expandedStandingsBox}>
                                <VoteNomineesLeaderboard
                                  title={question.question_text?.trim() || undefined}
                                  results={currentResults.results}
                                  totalVotes={currentResults.total_votes}
                                  selectedIds={
                                    Array.isArray(answers[question.id])
                                      ? answers[question.id]
                                      : answers[question.id]
                                      ? [answers[question.id]]
                                      : []
                                  }
                                  hasVoted={false}
                                  colors={colors}
                                  isDark={isDark}
                                  choices={question.choices}
                                  isOpenTree={isOpenTree}
                                />
                              </View>
                            )}
                          </View>
                        )}
                      </View>
                    )}

                    {/* Question Live Discussion / Polymorphic Chat Section */}
                    {question.has_chat && (
                      <QuestionChatSection
                        questionId={question.id}
                        questionTitle={question.question_text}
                        initialCount={question.chat_room?.messages_count}
                        answerType={question.answer_type}
                      />
                    )}
                  </View>
                );
              })}
            </View>
          </View>
        );
      })}

      {/* Global Media Lightbox */}
      <MediaLightbox
        visible={lightboxVisible}
        mediaUrl={lightboxUrl}
        isVideo={lightboxIsVideo}
        title={lightboxTitle}
        onClose={() => setLightboxVisible(false)}
      />
    </View>
  );
});

export default QuizCompetitionWidget;

