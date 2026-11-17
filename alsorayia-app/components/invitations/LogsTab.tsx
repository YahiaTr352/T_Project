import {
  formatLogDate,
  LogDetailModal,
  normalizeChannels,
  statusMeta,
} from '@/components/invitations/LogDetailModal';
import { invitationLogFontName } from '@/components/invitations/composer/typography';
import { effectiveLogStatus, extractInvitationError, respondToInvitation } from '@/lib/api/invitations';
import { ThemedText } from '@/components/themed-text';
import { IconSymbol } from '@/components/ui/icon-symbol';
import { silaTheme, type ThemeColors } from '@/constants/theme';
import { Ionicons } from '@expo/vector-icons';
import type { InvitationLog, InvitationResponse } from '@/lib/api/invitations';
import React, { useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  FlatList,
  Pressable,
  RefreshControl,
  StyleSheet,
  Text,
  View,
} from 'react-native';

const WHATSAPP_GREEN = '#25D366';
const PUSH_BLUE = '#3B82F6';

interface LogsTabProps {
  incoming: boolean;
  items: InvitationLog[];
  loading: boolean;
  refreshing: boolean;
  loadingMore: boolean;
  error: string | null;
  hasMore: boolean;
  onRefresh: () => void;
  onLoadMore: () => void;
  onOpenDetail: (id: number) => Promise<
    { ok: true; notification: InvitationLog } | { ok: false; message: string }
  >;
  onItemUpdated?: (notification: InvitationLog) => void;
  colors: ThemeColors;
  isDark: boolean;
}

export function LogsTab({
  incoming,
  items,
  loading,
  refreshing,
  loadingMore,
  error,
  hasMore,
  onRefresh,
  onLoadMore,
  onOpenDetail,
  onItemUpdated,
  colors,
  isDark,
}: LogsTabProps) {
  const muted = colors.icon;
  const cardBg = isDark ? colors.card : '#FFFFFF';
  const border = isDark ? '#334155' : colors.border;
  const actionGreen = isDark ? silaTheme.primaryLight : silaTheme.primaryDark;
  const [detail, setDetail] = useState<InvitationLog | null>(null);
  const [detailVisible, setDetailVisible] = useState(false);
  const [detailLoading, setDetailLoading] = useState(false);
  const [detailError, setDetailError] = useState<string | null>(null);
  const [pendingResponse, setPendingResponse] = useState<InvitationResponse | null>(null);
  const [responseError, setResponseError] = useState<{ id: number; message: string } | null>(null);
  const responseInFlight = useRef(false);
  const detailRequest = useRef(0);

  const openDetail = async (item: InvitationLog) => {
    if (responseInFlight.current) return;
    const requestId = ++detailRequest.current;
    setDetail(item);
    setDetailVisible(true);
    setDetailLoading(true);
    setDetailError(null);
    setResponseError(null);
    const res = await onOpenDetail(item.id);
    if (requestId !== detailRequest.current) return;
    setDetailLoading(false);
    if (res.ok) {
      setDetail(res.notification);
      // حدّث عنصر القائمة فوراً حتى لا تبقى الشارة قديمة بعد اكتمال الإرسال
      onItemUpdated?.(res.notification);
    } else {
      setDetailError(res.message);
    }
  };

  const respond = async (response: InvitationResponse) => {
    if (!incoming || !detail || detailLoading || responseInFlight.current) return;
    if (detail.invitation_response !== null) return;

    const invitation = detail;
    responseInFlight.current = true;
    setPendingResponse(response);
    setResponseError(null);
    try {
      const result = await respondToInvitation(invitation.id, response);
      if (!result.success || !result.data) {
        setResponseError({ id: invitation.id, message: extractInvitationError(result) });
        if (result.code === 'INVITATION_RESPONSE_ALREADY_RECORDED') {
          const updated = await onOpenDetail(invitation.id);
          if (updated.ok) {
            setDetail((current) => current?.id === invitation.id ? updated.notification : current);
            onItemUpdated?.(updated.notification);
          }
        }
        return;
      }

      const savedResponse = result.data.invitation_response;
      setDetail((current) => current?.id === invitation.id
        ? { ...current, invitation_response: savedResponse }
        : current);
      onItemUpdated?.({ ...invitation, invitation_response: savedResponse });
    } finally {
      responseInFlight.current = false;
      setPendingResponse(null);
    }
  };

  // تحديث تلقائي صامت كل 20 ثانية طالما يوجد عنصر بانتظار الاعتماد أو جارٍ إرساله
  const hasTransient = !incoming && items.some((it) => {
    const s = (it.status || '').toLowerCase();
    return ['pending_approval', 'pending', 'approved', 'queued', 'processing', 'sending', 'partial'].includes(s);
  });
  useEffect(() => {
    if (!hasTransient) return;
    const t = setInterval(() => onRefresh(), 20000);
    return () => clearInterval(t);
  }, [hasTransient, onRefresh]);

  if (loading && items.length === 0) {
    return (
      <View style={[styles.centered, styles.flex]}>
        <ActivityIndicator color={colors.primary} />
        <ThemedText style={[styles.muted, { color: muted }]}>جاري تحميل السجل...</ThemedText>
      </View>
    );
  }

  return (
    <View style={styles.flex}>
      {error ? <ThemedText style={styles.error}>{error}</ThemedText> : null}
      <FlatList
        data={items}
        keyExtractor={(item) => String(item.id)}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={colors.primary} />
        }
        onEndReached={() => {
          if (hasMore) onLoadMore();
        }}
        onEndReachedThreshold={0.4}
        contentContainerStyle={items.length === 0 ? styles.emptyWrap : styles.list}
        ListEmptyComponent={
          <View style={styles.centered}>
            <IconSymbol name="clock.fill" size={40} color={muted} />
            <ThemedText style={[styles.muted, { color: muted }]}>
              {incoming ? 'لا توجد دعوات واردة بعد.' : 'لا توجد إرسالات بعد. أرسل أول دعوة من تبويب «دعوة جديدة».'}
            </ThemedText>
          </View>
        }
        ListFooterComponent={
          loadingMore ? (
            <ActivityIndicator color={colors.primary} style={{ marginVertical: 16 }} />
          ) : null
        }
        renderItem={({ item }) => {
          const meta = incoming
            ? { label: 'واردة', color: colors.primary }
            : statusMeta(effectiveLogStatus(item) || item.status);
          const channels = normalizeChannels(item.channels);
          const recipientsCount = item.recipients_count ?? item.recipients?.length ?? 0;
          const isTransient = !incoming && ['pending_approval', 'pending', 'approved', 'queued', 'processing', 'sending'].includes((item.status || '').toLowerCase());
          const title = item.title || 'بدون عنوان';
          const awaitingResponse = incoming && item.invitation_response === null;
          const detailAction = awaitingResponse ? 'عرض الدعوة والرد' : 'عرض التفاصيل';
          const actionColor = awaitingResponse ? actionGreen : muted;
          return (
            <Pressable
              accessibilityRole="button"
              accessibilityLabel={`${detailAction}، ${title}`}
              onPress={() => void openDetail(item)}
              style={({ pressed }) => [
                styles.card,
                { backgroundColor: cardBg, borderColor: border },
                pressed && styles.cardPressed,
              ]}>
              <View style={styles.cardTop}>
                <ThemedText style={[styles.cardTitle, { fontFamily: invitationLogFontName(item, 'bold') }]} numberOfLines={1}>
                  {title}
                </ThemedText>
                <View style={[styles.badgeRow]}>
                  <View style={[styles.badge, { backgroundColor: meta.color + '20' }]}>
                    <Text style={[styles.badgeTextNative, { color: meta.color }]}>
                      {meta.label}
                    </Text>
                  </View>
                </View>
              </View>
              {isTransient ? (
                <Text style={[styles.transientHint, { color: muted }]}>
                  {item.status === 'pending_approval' || item.status === 'pending'
                    ? 'بانتظار موافقة الإدارة — ستتحدث الحالة تلقائياً'
                    : 'المهام قيد التنفيذ في الخادم — اسحب للأسفل للتحديث'}
                </Text>
              ) : null}
              <ThemedText style={[styles.bodyPreview, { color: muted, fontFamily: invitationLogFontName(item, 'regular') }]} numberOfLines={2}>
                {item.body}
              </ThemedText>
              <View style={styles.cardBottom}>
                <View style={styles.channels}>
                  {channels.includes('whatsapp') ? (
                    <Ionicons name="logo-whatsapp" size={16} color={WHATSAPP_GREEN} />
                  ) : null}
                  {channels.includes('push') ? (
                    <IconSymbol name="bell.fill" size={16} color={PUSH_BLUE} />
                  ) : null}
                  {incoming ? (
                    <ThemedText style={[styles.metaText, { color: muted }]}>
                      من {item.sender_name || 'مرسل غير معروف'}
                    </ThemedText>
                  ) : recipientsCount > 0 ? (
                    <ThemedText style={[styles.metaText, { color: muted }]}>
                      {recipientsCount} مستلم
                    </ThemedText>
                  ) : null}
                </View>
                <ThemedText style={[styles.metaText, { color: muted }]}>
                  {formatLogDate(incoming ? item.sent_at ?? item.created_at : item.created_at)}
                </ThemedText>
              </View>
              <View style={[
                styles.detailAction,
                {
                  backgroundColor: awaitingResponse ? actionGreen + '14' : cardBg,
                  borderColor: awaitingResponse ? actionGreen + '40' : border,
                },
              ]}>
                <ThemedText style={[styles.detailActionText, { color: actionColor }]}>
                  {detailAction}
                </ThemedText>
                <Ionicons name="chevron-back" size={17} color={actionColor} />
              </View>
            </Pressable>
          );
        }}
      />

      <LogDetailModal
        visible={detailVisible}
        onClose={() => {
          detailRequest.current++;
          setDetailVisible(false);
        }}
        log={detail}
        loading={detailLoading}
        error={detailError}
        incoming={incoming}
        onRespond={respond}
        pendingResponse={pendingResponse}
        responseError={responseError?.id === detail?.id ? responseError?.message : undefined}
        colors={colors}
        isDark={isDark}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  flex: {
    flex: 1,
  },
  list: {
    paddingBottom: 32,
  },
  emptyWrap: {
    flexGrow: 1,
    justifyContent: 'center',
    paddingVertical: 40,
  },
  centered: {
    alignItems: 'center',
    justifyContent: 'center',
    gap: 10,
    padding: 24,
  },
  muted: {
    fontSize: 13,
    fontFamily: 'Tajawal-Regular',
    textAlign: 'center',
  },
  error: {
    color: '#EF4444',
    fontFamily: 'Tajawal-Medium',
    textAlign: 'center',
    marginBottom: 10,
  },
  card: {
    borderWidth: 1,
    borderRadius: 16,
    padding: 14,
    marginBottom: 10,
  },
  cardPressed: {
    opacity: 0.72,
  },
  cardTop: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginBottom: 6,
  },
  cardTitle: {
    flex: 1,
    fontSize: 15,
  },
  badge: {
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 10,
  },
  badgeText: {
    fontSize: 11,
    fontFamily: 'Tajawal-Bold',
  },
  badgeRow: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  badgeTextNative: {
    fontSize: 11,
    fontFamily: 'Tajawal-Bold',
    textAlign: 'center',
    lineHeight: 16,
  },
  transientHint: {
    fontSize: 11,
    fontFamily: 'Tajawal-Regular',
    marginBottom: 6,
    lineHeight: 16,
  },
  bodyPreview: {
    fontSize: 13,
    lineHeight: 20,
    marginBottom: 10,
  },
  cardBottom: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  detailAction: {
    direction: 'rtl',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 8,
    minHeight: 44,
    marginTop: 12,
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderWidth: 1,
    borderRadius: 12,
  },
  detailActionText: {
    flexShrink: 1,
    fontSize: 14,
    fontFamily: 'Tajawal-Bold',
  },
  channels: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  metaText: {
    fontSize: 12,
    fontFamily: 'Tajawal-Medium',
  },
});
