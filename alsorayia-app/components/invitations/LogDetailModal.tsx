import { PersonAvatar } from '@/components/invitations/PersonAvatar';
import { invitationLogFontName } from '@/components/invitations/composer/typography';
import { ThemedText } from '@/components/themed-text';
import { IconSymbol } from '@/components/ui/icon-symbol';
import { type ThemeColors } from '@/constants/theme';
import {
  effectiveLogStatus,
  isLogCompleted,
  recipientDisplayName,
  recipientPhotoUrl,
  type InvitationChannel,
  type InvitationLog,
} from '@/lib/api/invitations';
import { Ionicons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import React from 'react';
import {
  ActivityIndicator,
  Modal,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';

const WHATSAPP_GREEN = '#25D366';
const PUSH_BLUE = '#3B82F6';

interface LogDetailModalProps {
  visible: boolean;
  onClose: () => void;
  log: InvitationLog | null;
  loading: boolean;
  error: string | null;
  incoming: boolean;
  colors: ThemeColors;
  isDark: boolean;
}

export function statusMeta(status: string): { label: string; color: string; hint?: string } {
  const s = (status || '').toLowerCase().trim();
  switch (s) {
    case 'pending_approval':
    case 'pending':
      return { label: 'قيد المراجعة والاعتماد', color: '#F59E0B', hint: 'الدعوة بانتظار موافقة الإدارة ولم يبدأ الإرسال بعد.' };
    case 'approved':
      return { label: 'تم الاعتماد — بانتظار بدء الإرسال', color: '#0284C7', hint: 'وافقت الإدارة، ومهام الإرسال في الطابور ولم تكتمل بعد.' };
    case 'queued':
    case 'queue':
    case 'processing':
    case 'sending':
    case 'sending_whatsapp':
    case 'sending_push':
      return { label: 'جاري الإرسال…', color: '#D97706', hint: 'الإرسال بدأ فعلاً لكن المهام لم تكتمل بعد. اسحب السجل للتحديث.' };
    case 'partial':
    case 'partially_sent':
      return { label: 'إرسال جزئي', color: '#D97706', hint: 'وصل لجزء من المستلمين وتعثر الباقي. افتح التفاصيل وراجع.' };
    case 'sent':
    case 'completed':
    case 'delivered':
    case 'done':
      return { label: 'تم الإرسال', color: '#10B981' };
    case 'rejected':
      return { label: 'مرفوضة من الإدارة', color: '#EF4444', hint: 'رفضت الإدارة الدعوة ولن تُرسل.' };
    case 'failed':
    case 'fail':
    case 'error':
      return { label: 'فشل الإرسال', color: '#EF4444', hint: 'تعثرت مهام الإرسال. أعد المحاولة أو تواصل مع الإدارة.' };
    case 'cancelled':
    case 'canceled':
      return { label: 'ملغاة', color: '#64748B' };
    case 'delivery_disabled':
      return { label: 'الإرسال معطّل', color: '#64748B', hint: 'حُفظت الدعوة في السجل دون إرسالها من هذه البيئة.' };
    default:
      return { label: status || 'غير معروف', color: '#64748B' };
  }
}

/** خط زمني مبسط: إنشاء → اعتماد → إرسال → اكتمال */
export function statusStepIndex(status: string): number {
  const s = (status || '').toLowerCase().trim();
  if (['sent', 'completed', 'delivered', 'done'].includes(s)) return 3;
  if (['sending', 'sending_whatsapp', 'sending_push', 'queued', 'queue', 'processing', 'partial', 'partially_sent'].includes(s)) return 2;
  if (['approved'].includes(s)) return 1;
  return 0;
}


export function formatLogDate(iso?: string | null): string {
  if (!iso) return '';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return iso;
  return d.toLocaleString('ar-EG', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

export function normalizeChannels(
  channels?: InvitationLog['channels'] | null
): InvitationChannel[] {
  if (!Array.isArray(channels)) return [];
  const next: InvitationChannel[] = [];
  for (const c of channels) {
    if (c === 'whatsapp' || c === 'push') next.push(c);
  }
  return next;
}

export function LogDetailModal({
  visible,
  onClose,
  log,
  loading,
  error,
  incoming,
  colors,
  isDark,
}: LogDetailModalProps) {
  const muted = colors.icon;
  const cardBg = isDark ? colors.card : '#FFFFFF';
  const border = isDark ? '#334155' : colors.border;
  // الحالة الفعالة: sent_at يعني اكتمال فعلي حتى لو نص الحالة متأخر من الخادم
  const effectiveStatus = effectiveLogStatus(log);
  const meta = statusMeta(effectiveStatus || log?.status || '');
  const completed = isLogCompleted(log);
  const requiresApproval = log?.targeting_filters?.requires_approval !== false;
  const steps = requiresApproval
    ? ['إنشاء', 'اعتماد', 'إرسال', 'اكتمال']
    : ['إنشاء', 'إرسال', 'اكتمال'];
  const currentStep = Math.max(0, statusStepIndex(effectiveStatus) - (requiresApproval ? 0 : 1));
  const channels = normalizeChannels(log?.channels);
  const totalRecipients =
    log?.recipients_count ?? log?.recipients?.length ?? 0;

  return (
    <Modal visible={visible} transparent animationType="fade" onRequestClose={onClose}>
      <View style={styles.overlay}>
        <View style={[styles.card, { backgroundColor: isDark ? '#0F172A' : '#F8FAFC' }]}>
          <View style={styles.header}>
            <ThemedText style={styles.title}>{incoming ? 'تفاصيل الدعوة الواردة' : 'تفاصيل الإرسال'}</ThemedText>
            <Pressable onPress={onClose} hitSlop={12}>
              <IconSymbol name="xmark.circle.fill" size={22} color={muted} />
            </Pressable>
          </View>

          {error ? (
            <ThemedText style={[styles.date, { color: muted }]}>{error}</ThemedText>
          ) : loading || !log ? (
            <ActivityIndicator color={colors.primary} style={{ marginVertical: 24 }} />
          ) : (
            <ScrollView showsVerticalScrollIndicator={false}>
              {!incoming ? (
                <>
                  <View style={[styles.badge, { backgroundColor: meta.color + '20' }]}>
                    <ThemedText style={[styles.badgeText, { color: meta.color }]}>
                      {meta.label}
                    </ThemedText>
                  </View>
                  {meta.hint ? (
                    <View style={[styles.hintBox, { backgroundColor: meta.color + '12', borderColor: meta.color + '40' }]}>
                      <Ionicons name="information-circle-outline" size={15} color={meta.color} />
                      <ThemedText style={[styles.hintText, { color: meta.color }]}>
                        {meta.hint}
                      </ThemedText>
                    </View>
                  ) : null}
                  {completed && log?.status && log.status.toLowerCase() !== 'completed' && log.status.toLowerCase() !== 'sent' ? (
                    <View style={[styles.hintBox, { backgroundColor: '#10B98112', borderColor: '#10B98140' }]}>
                      <Ionicons name="checkmark-circle" size={15} color="#10B981" />
                      <ThemedText style={[styles.hintText, { color: '#059669' }]}>
                        وصلت الرسالة فعلاً واكتملت بتاريخ {formatLogDate(log.sent_at)} — نص الحالة في الخادم متأخر فقط.
                      </ThemedText>
                    </View>
                  ) : null}
                  <View style={styles.timeline}>
                    {steps.map((stepLabel, i) => {
                      const done = i <= currentStep;
                      const active = i === currentStep;
                      const connectorColor =
                        i < currentStep ? meta.color : isDark ? '#334155' : '#E2E8F0';
                      return (
                        <React.Fragment key={stepLabel}>
                          <View style={styles.timelineStep}>
                            <View
                              style={[
                                styles.timelineDot,
                                {
                                  backgroundColor: done ? meta.color : isDark ? '#334155' : '#E2E8F0',
                                  borderColor: active ? meta.color : 'transparent',
                                  borderWidth: active ? 2 : 0,
                                },
                              ]}>
                              {done ? <Ionicons name="checkmark" size={11} color="#FFFFFF" /> : null}
                            </View>
                            <ThemedText style={[styles.timelineLabel, { color: done ? meta.color : muted }]}>
                              {stepLabel}
                            </ThemedText>
                          </View>
                          {i < steps.length - 1 ? (
                            <View style={[styles.timelineConnector, { backgroundColor: connectorColor }]} />
                          ) : null}
                        </React.Fragment>
                      );
                    })}
                  </View>
                </>
              ) : null}
              {log.title ? (
                <ThemedText style={[styles.logTitle, { fontFamily: invitationLogFontName(log, 'bold') }]}>{log.title}</ThemedText>
              ) : null}
              {incoming ? (
                <ThemedText style={[styles.date, { color: muted }]}>
                  من {log.sender_name || 'مرسل غير معروف'}
                </ThemedText>
              ) : null}
              <ThemedText style={[styles.date, { color: muted }]}>
                {incoming
                  ? formatLogDate(log.sent_at ?? log.created_at)
                  : `أُنشئت: ${formatLogDate(log.created_at)}${log.sent_at ? `  •  اكتملت: ${formatLogDate(log.sent_at)}` : ''}`}
              </ThemedText>
              <View style={styles.channelRow}>
                {channels.includes('whatsapp') ? (
                  <View style={[styles.ch, { backgroundColor: WHATSAPP_GREEN + '18' }]}>
                    <Ionicons name="logo-whatsapp" size={14} color={WHATSAPP_GREEN} />
                    <ThemedText style={[styles.chText, { color: WHATSAPP_GREEN }]}>واتساب</ThemedText>
                  </View>
                ) : null}
                {channels.includes('push') ? (
                  <View style={[styles.ch, { backgroundColor: PUSH_BLUE + '18' }]}>
                    <IconSymbol name="bell.fill" size={14} color={PUSH_BLUE} />
                    <ThemedText style={[styles.chText, { color: PUSH_BLUE }]}>إشعارات</ThemedText>
                  </View>
                ) : null}
              </View>
              <View style={[styles.bodyBox, { backgroundColor: cardBg, borderColor: border }]}>
                {log.media_type === 'image' && log.media_url ? (
                  <Image source={{ uri: log.media_url }} contentFit="contain"
                    accessibilityLabel="صورة الدعوة" style={{ width: '100%', height: 320, marginBottom: 12 }} />
                ) : null}
                <ThemedText style={[styles.body, { fontFamily: invitationLogFontName(log, 'regular') }]}>{log.body}</ThemedText>
              </View>
              {!incoming ? (
                <>
                  <ThemedText style={styles.section}>
                    المستلمون{totalRecipients > 0 ? ` (${totalRecipients})` : ''}
                  </ThemedText>
                  {(log.recipients ?? []).length === 0 ? (
                    <ThemedText style={[styles.date, { color: muted }]}>لا يوجد مستلمون مسجّلون</ThemedText>
                  ) : (
                    (log.recipients ?? []).map((r) => {
                      const photo = recipientPhotoUrl(r);
                      const displayName = recipientDisplayName(r);
                      return (
                        <View key={r.id} style={[styles.recipient, { borderColor: border }]}>
                          <PersonAvatar
                            uri={photo}
                            size={36}
                            mutedColor={muted}
                            name={displayName}
                          />
                          <View style={{ flex: 1 }}>
                            <ThemedText style={styles.recipientName}>
                              {displayName}
                            </ThemedText>
                            {r.recipient_phone && r.person_id == null ? (
                              <ThemedText style={[styles.recipientSub, { color: muted }]}>
                                {r.recipient_phone}
                              </ThemedText>
                            ) : null}
                          </View>
                          {(r.delivered_at || r.sent_at) ? (
                            <Ionicons name="checkmark-done" size={16} color="#10B981" />
                          ) : null}
                        </View>
                      );
                    })
                  )}
                </>
              ) : null}
            </ScrollView>
          )}

          <TouchableOpacity
            activeOpacity={0.85}
            onPress={onClose}
            style={[styles.closeBtn, { backgroundColor: colors.primary }]}>
            <Text style={styles.closeTextNative}>إغلاق</Text>
          </TouchableOpacity>
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  overlay: {
    flex: 1,
    backgroundColor: 'rgba(15, 23, 42, 0.55)',
    justifyContent: 'center',
    padding: 20,
  },
  card: {
    borderRadius: 20,
    padding: 16,
    maxHeight: '85%',
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 12,
  },
  title: {
    fontSize: 18,
    fontFamily: 'Tajawal-Bold',
  },
  badge: {
    alignSelf: 'flex-start',
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 10,
    marginBottom: 8,
  },
  badgeText: {
    fontSize: 12,
    fontFamily: 'Tajawal-Bold',
  },
  hintBox: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    borderWidth: 1,
    borderRadius: 12,
    paddingHorizontal: 10,
    paddingVertical: 8,
    marginBottom: 10,
  },
  hintText: {
    flex: 1,
    fontSize: 12,
    fontFamily: 'Tajawal-Medium',
    lineHeight: 18,
  },
  timeline: {
    flexDirection: 'row-reverse',
    alignItems: 'flex-start',
    marginBottom: 12,
    marginTop: 2,
  },
  timelineStep: {
    width: 52,
    alignItems: 'center',
  },
  timelineDot: {
    width: 22,
    height: 22,
    borderRadius: 11,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 4,
  },
  timelineLabel: {
    fontSize: 11,
    fontFamily: 'Tajawal-Bold',
    textAlign: 'center',
  },
  timelineConnector: {
    flex: 1,
    height: 2,
    borderRadius: 1,
    marginTop: 10,
    marginHorizontal: 2,
  },
  logTitle: {
    fontSize: 16,
    marginBottom: 4,
  },
  date: {
    fontSize: 12,
    fontFamily: 'Tajawal-Regular',
    marginBottom: 10,
  },
  channelRow: {
    flexDirection: 'row',
    gap: 8,
    marginBottom: 12,
  },
  ch: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 10,
  },
  chText: {
    fontSize: 12,
    fontFamily: 'Tajawal-Medium',
  },
  bodyBox: {
    borderWidth: 1,
    borderRadius: 14,
    padding: 12,
    marginBottom: 14,
  },
  body: {
    fontSize: 14,
    lineHeight: 24,
  },
  section: {
    fontSize: 14,
    fontFamily: 'Tajawal-Bold',
    marginBottom: 8,
  },
  recipient: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    paddingVertical: 8,
    borderBottomWidth: StyleSheet.hairlineWidth,
  },
  recipientName: {
    fontSize: 14,
    fontFamily: 'Tajawal-Medium',
  },
  recipientSub: {
    fontSize: 11,
    fontFamily: 'Tajawal-Regular',
    marginTop: 1,
  },
  closeBtn: {
    marginTop: 12,
    alignItems: 'center',
    paddingVertical: 12,
    borderRadius: 14,
  },
  closeText: {
    color: '#FFFFFF',
    fontFamily: 'Tajawal-Bold',
    fontSize: 15,
  },
  closeTextNative: {
    color: '#FFFFFF',
    fontFamily: 'Tajawal-Bold',
    fontSize: 16,
    lineHeight: 22,
    textAlign: 'center',
  },
});
