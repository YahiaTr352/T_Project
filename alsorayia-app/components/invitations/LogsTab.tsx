import {
  formatLogDate,
  LogDetailModal,
  normalizeChannels,
  statusMeta,
} from '@/components/invitations/LogDetailModal';
import { invitationLogFontName } from '@/components/invitations/composer/typography';
import {
  cardShadowStyle,
  getInvitationPalette,
  inferThumbnailFromLog,
  type InvitationPalette,
} from '@/components/invitations/invitationTheme';
import { ThemedText } from '@/components/themed-text';
import { IconSymbol } from '@/components/ui/icon-symbol';
import { type ThemeColors } from '@/constants/theme';
import {
  effectiveLogStatus,
  extractInvitationError,
  type InvitationLog,
  type InvitationResponse,
  respondToInvitation,
} from '@/lib/api/invitations';
import { Ionicons } from '@expo/vector-icons';
import { Image } from 'expo-image';
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

function resolveLogBadge(
  incoming: boolean,
  item: InvitationLog,
  palette: InvitationPalette
): { label: string; bg: string; text: string } {
  if (incoming) {
    if (item.invitation_response === 'accepted') {
      return { label: 'سأحضر', bg: palette.successBg, text: palette.successText };
    }
    if (item.invitation_response === 'declined') {
      return { label: 'معتذر', bg: palette.dangerBg, text: palette.dangerText };
    }
    return { label: 'بانتظار الرد', bg: palette.warningBg, text: palette.warningText };
  }

  const effective = (effectiveLogStatus(item) || item.status || '').toLowerCase().trim();
  const meta = statusMeta(effective);
  if (['sent', 'completed', 'delivered', 'done'].includes(effective)) {
    return { label: meta.label, bg: palette.successBg, text: palette.successText };
  }
  if (['rejected', 'failed', 'fail', 'error'].includes(effective)) {
    return { label: meta.label, bg: palette.dangerBg, text: palette.dangerText };
  }
  if (['approved'].includes(effective)) {
    return { label: meta.label, bg: palette.infoBg, text: palette.infoText };
  }
  if (['cancelled', 'canceled', 'delivery_disabled'].includes(effective)) {
    return { label: meta.label, bg: palette.neutralBg, text: palette.neutralText };
  }
  return { label: meta.label, bg: palette.warningBg, text: palette.warningText };
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
  const palette = getInvitationPalette(isDark);
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
        <ActivityIndicator color={palette.primary} />
        <ThemedText style={[styles.muted, { color: palette.muted }]}>جاري تحميل السجل...</ThemedText>
      </View>
    );
  }

  return (
    <View style={styles.flex}>
      {error ? (
        <View style={[styles.errorBanner, { backgroundColor: palette.dangerBg, borderColor: palette.dangerBorder }]}>
          <ThemedText style={[styles.error, { color: palette.dangerText }]}>{error}</ThemedText>
        </View>
      ) : null}
      <FlatList
        data={items}
        keyExtractor={(item) => String(item.id)}
        showsVerticalScrollIndicator={false}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={palette.primary} />
        }
        onEndReached={() => {
          if (hasMore) onLoadMore();
        }}
        onEndReachedThreshold={0.4}
        contentContainerStyle={items.length === 0 ? styles.emptyWrap : styles.list}
        ListEmptyComponent={
          <View style={[styles.emptyCard, cardShadowStyle(palette), { backgroundColor: palette.surface, borderColor: palette.border }]}>
            <View style={[styles.emptyIconWrap, { backgroundColor: palette.surfaceSoft }]}>
              <IconSymbol name="clock.fill" size={30} color={palette.primary} />
            </View>
            <ThemedText style={[styles.emptyTitle, { color: palette.text }]}>
              {incoming ? 'لا توجد دعوات واردة بعد' : 'لا توجد دعوات مرسلة بعد'}
            </ThemedText>
            <ThemedText style={[styles.muted, { color: palette.muted }]}>
              {incoming ? 'ستظهر هنا أي دعوة تصلك من أفراد العائلة.' : 'أرسل أول دعوة من تبويب «دعوة جديدة».'}
            </ThemedText>
          </View>
        }
        ListFooterComponent={
          loadingMore ? (
            <ActivityIndicator color={palette.primary} style={{ marginVertical: 16 }} />
          ) : null
        }
        renderItem={({ item }) => {
          const badge = resolveLogBadge(incoming, item, palette);
          const channels = normalizeChannels(item.channels);
          const recipientsCount = item.recipients_count ?? item.recipients?.length ?? 0;
          const isTransient = !incoming && ['pending_approval', 'pending', 'approved', 'queued', 'processing', 'sending'].includes((item.status || '').toLowerCase());
          const title = item.title || 'بدون عنوان';
          const awaitingResponse = incoming && item.invitation_response === null;
          const detailAction = awaitingResponse ? 'عرض الدعوة والرد' : 'عرض التفاصيل';
          const thumbSource = item.media_type === 'image' && item.media_url
            ? { uri: item.media_url }
            : inferThumbnailFromLog(item.title, item.body);
          const dateText = formatLogDate(incoming ? item.sent_at ?? item.created_at : item.created_at);

          return (
            <Pressable
              accessibilityRole="button"
              accessibilityLabel={`${detailAction}، ${title}`}
              onPress={() => void openDetail(item)}
              style={({ pressed }) => [
                styles.card,
                cardShadowStyle(palette),
                {
                  backgroundColor: palette.surface,
                  borderColor: palette.border,
                },
                pressed && styles.cardPressed,
              ]}>
              <View style={styles.cardTop}>
                <View style={[styles.thumbWrap, { borderColor: palette.border, backgroundColor: palette.surfaceAlt }]}>
                  <Image source={thumbSource} style={styles.thumbImg} contentFit="cover" />
                </View>

                <View style={styles.cardContent}>
                  <View style={styles.titleBadgeRow}>
                    <ThemedText
                      style={[
                        styles.cardTitle,
                        { color: palette.text, fontFamily: invitationLogFontName(item, 'bold') },
                      ]}
                      numberOfLines={1}>
                      {title}
                    </ThemedText>
                    <View style={[styles.badge, { backgroundColor: badge.bg }]}>
                      <Text style={[styles.badgeTextNative, { color: badge.text }]}>
                        {badge.label}
                      </Text>
                    </View>
                  </View>

                  <View style={styles.metaRow}>
                    {channels.includes('whatsapp') ? (
                      <Ionicons name="logo-whatsapp" size={13} color={WHATSAPP_GREEN} />
                    ) : null}
                    {channels.includes('push') ? (
                      <IconSymbol name="bell.fill" size={13} color={PUSH_BLUE} />
                    ) : null}
                    <ThemedText style={[styles.metaText, { color: palette.muted }]} numberOfLines={1}>
                      {incoming
                        ? `من: ${item.sender_name || 'أحد أفراد العائلة'} · ${dateText}`
                        : `${recipientsCount > 0 ? `${recipientsCount} مستلم · ` : ''}${dateText}`}
                    </ThemedText>
                  </View>
                </View>
              </View>

              {isTransient ? (
                <Text style={[styles.transientHint, { color: palette.warningText }]}>
                  {item.status === 'pending_approval' || item.status === 'pending'
                    ? 'بانتظار موافقة الإدارة — ستتحدث الحالة تلقائياً'
                    : 'المهام قيد التنفيذ في الخادم — اسحب للأسفل للتحديث'}
                </Text>
              ) : null}

              <View style={[styles.footerDivider, { borderTopColor: palette.border }]}>
                <ThemedText style={[styles.footerPrompt, { color: palette.muted }]} numberOfLines={1}>
                  {awaitingResponse ? 'اضغط لعرض بطاقة الدعوة والرد' : 'اضغط لفتح تفاصيل الدعوة'}
                </ThemedText>
                <View style={styles.footerActionWrap}>
                  <ThemedText style={[styles.detailActionText, { color: palette.primary }]}>
                    {detailAction}
                  </ThemedText>
                  <Ionicons name="chevron-back" size={15} color={palette.primary} />
                </View>
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
    paddingVertical: 32,
  },
  centered: {
    alignItems: 'center',
    justifyContent: 'center',
    gap: 10,
    padding: 24,
  },
  emptyCard: {
    borderWidth: 1,
    borderRadius: 22,
    padding: 28,
    alignItems: 'center',
    gap: 8,
  },
  emptyIconWrap: {
    width: 60,
    height: 60,
    borderRadius: 30,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 4,
  },
  emptyTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 16,
  },
  muted: {
    fontSize: 13,
    fontFamily: 'Tajawal-Regular',
    textAlign: 'center',
  },
  errorBanner: {
    borderWidth: 1,
    borderRadius: 14,
    padding: 10,
    marginBottom: 10,
  },
  error: {
    fontFamily: 'Tajawal-Medium',
    fontSize: 13,
    textAlign: 'center',
  },
  card: {
    borderWidth: 1,
    borderRadius: 20,
    padding: 14,
    marginBottom: 12,
  },
  cardPressed: {
    opacity: 0.82,
  },
  cardTop: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  thumbWrap: {
    width: 56,
    height: 56,
    borderRadius: 14,
    borderWidth: 1,
    overflow: 'hidden',
    flexShrink: 0,
  },
  thumbImg: {
    width: '100%',
    height: '100%',
  },
  cardContent: {
    flex: 1,
    minWidth: 0,
    gap: 5,
  },
  titleBadgeRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 8,
  },
  cardTitle: {
    flex: 1,
    fontSize: 15,
    textAlign: 'right',
    writingDirection: 'rtl',
  },
  badge: {
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 12,
    flexShrink: 0,
  },
  badgeTextNative: {
    fontSize: 11.5,
    fontFamily: 'Tajawal-Bold',
    textAlign: 'center',
    lineHeight: 16,
  },
  metaRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 5,
  },
  metaText: {
    flex: 1,
    fontSize: 12,
    fontFamily: 'Tajawal-Medium',
    textAlign: 'right',
    writingDirection: 'rtl',
  },
  transientHint: {
    fontSize: 11.5,
    fontFamily: 'Tajawal-Medium',
    marginTop: 8,
    lineHeight: 17,
    textAlign: 'right',
  },
  footerDivider: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    borderTopWidth: StyleSheet.hairlineWidth,
    marginTop: 12,
    paddingTop: 10,
    gap: 8,
  },
  footerPrompt: {
    flex: 1,
    fontSize: 12,
    fontFamily: 'Tajawal-Regular',
    textAlign: 'right',
  },
  footerActionWrap: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 3,
    flexShrink: 0,
  },
  detailActionText: {
    fontSize: 13,
    fontFamily: 'Tajawal-Bold',
  },
});
