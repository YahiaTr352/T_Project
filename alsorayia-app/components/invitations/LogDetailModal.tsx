import { PersonAvatar } from '@/components/invitations/PersonAvatar';
import { invitationLogFontName } from '@/components/invitations/composer/typography';
import { InvitationGradientButton } from '@/components/invitations/InvitationPrimitives';
import { cardShadowStyle, getInvitationPalette } from '@/components/invitations/invitationTheme';
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
  type InvitationResponse,
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
  View,
} from 'react-native';

const WHATSAPP_GREEN = '#25D366';
const PUSH_BLUE = '#3B82F6';
const responseOptions = [
  { value: 'accepted', button: 'قبول الدعوة', confirm: 'تأكيد القبول', status: 'قبلت الدعوة', label: 'موافق على الحضور', icon: 'checkmark' },
  { value: 'declined', button: 'رفض الدعوة', confirm: 'تأكيد الرفض', status: 'رفضت الدعوة', label: 'معتذر عن الحضور', icon: 'close' },
] as const;

interface LogDetailModalProps {
  visible: boolean;
  onClose: () => void;
  log: InvitationLog | null;
  loading: boolean;
  error: string | null;
  incoming: boolean;
  onRespond: (response: InvitationResponse) => Promise<void>;
  pendingResponse: InvitationResponse | null;
  responseError?: string;
  colors: ThemeColors;
  isDark: boolean;
}

export function statusMeta(status: string): { label: string; color: string; hint?: string } {
  const s = (status || '').toLowerCase().trim();
  switch (s) {
    case 'pending_approval':
    case 'pending':
      return { label: 'قيد المراجعة والاعتماد', color: '#D97706', hint: 'الدعوة بانتظار موافقة الإدارة ولم يبدأ الإرسال بعد.' };
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
      return { label: 'تم الإرسال', color: '#19844A' };
    case 'rejected':
      return { label: 'مرفوضة من الإدارة', color: '#DC2626', hint: 'رفضت الإدارة الدعوة ولن تُرسل.' };
    case 'failed':
    case 'fail':
    case 'error':
      return { label: 'فشل الإرسال', color: '#DC2626', hint: 'تعثرت مهام الإرسال. أعد المحاولة أو تواصل مع الإدارة.' };
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
  onRespond,
  pendingResponse,
  responseError,
  isDark,
}: LogDetailModalProps) {
  const [confirmationResponse, setConfirmationResponse] = React.useState<InvitationResponse | null>(null);
  const palette = getInvitationPalette(isDark);
  const muted = palette.muted;
  const cardBg = palette.surface;
  const border = palette.border;
  const effectiveStatus = effectiveLogStatus(log);
  const meta = statusMeta(effectiveStatus || log?.status || '');
  const completed = isLogCompleted(log);
  const requiresApproval = log?.targeting_filters?.requires_approval !== false;
  const steps = requiresApproval
    ? ['إنشاء', 'اعتماد', 'إرسال', 'اكتمال']
    : ['إنشاء', 'إرسال', 'اكتمال'];
  const currentStep = Math.max(0, statusStepIndex(effectiveStatus) - (requiresApproval ? 0 : 1));
  const channels = normalizeChannels(log?.channels);
  const savedResponse = log?.invitation_response;
  const isResponding = pendingResponse !== null;
  const acceptColor = palette.primary;
  const declineColor = palette.dangerText;
  const confirmation = responseOptions.find((option) => option.value === confirmationResponse);
  const confirmationColor = confirmation?.value === 'declined' ? declineColor : acceptColor;
  const recipients = log?.recipients ?? [];
  const totalRecipients = log?.recipients_count ?? recipients.length;
  const showResponses = !incoming && (log?.status === 'sent' || log?.status === 'partial');
  const appRecipients = showResponses ? recipients.filter((recipient) => recipient.person_id != null) : [];
  const responseCounts = [
    appRecipients.filter((recipient) => recipient.invitation_response === 'accepted').length,
    appRecipients.filter((recipient) => recipient.invitation_response === 'declined').length,
    appRecipients.filter((recipient) => recipient.invitation_response == null).length,
  ];

  return (
    <Modal
      visible={visible}
      transparent
      animationType="fade"
      onRequestClose={confirmation ? () => setConfirmationResponse(null) : onClose}>
      <View style={[styles.overlay, { backgroundColor: palette.overlay }]}>
        <View
          style={[
            styles.card,
            cardShadowStyle(palette),
            { backgroundColor: isDark ? '#0D1E18' : '#F8FCFA', borderColor: border },
          ]}
          accessibilityElementsHidden={Boolean(confirmation)}
          importantForAccessibility={confirmation ? 'no-hide-descendants' : 'auto'}>
          <View style={styles.header}>
            <ThemedText style={[styles.title, { color: palette.text }]}>
              {incoming ? 'تفاصيل الدعوة الواردة' : 'تفاصيل الإرسال'}
            </ThemedText>
            <Pressable
              onPress={onClose}
              hitSlop={12}
              style={[styles.headerCloseBtn, { backgroundColor: palette.surface, borderColor: border }]}>
              <IconSymbol name="xmark" size={16} color={muted} />
            </Pressable>
          </View>

          {error ? (
            <ThemedText style={[styles.date, { color: palette.dangerText }]}>{error}</ThemedText>
          ) : loading || !log ? (
            <ActivityIndicator color={palette.primary} style={{ marginVertical: 24 }} />
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
                    <View style={[styles.hintBox, { backgroundColor: meta.color + '14', borderColor: meta.color + '40' }]}>
                      <Ionicons name="information-circle-outline" size={15} color={meta.color} />
                      <ThemedText style={[styles.hintText, { color: meta.color }]}>
                        {meta.hint}
                      </ThemedText>
                    </View>
                  ) : null}
                  {completed && log?.status && log.status.toLowerCase() !== 'completed' && log.status.toLowerCase() !== 'sent' ? (
                    <View style={[styles.hintBox, { backgroundColor: palette.successBg, borderColor: palette.successBorder }]}>
                      <Ionicons name="checkmark-circle" size={15} color={palette.successText} />
                      <ThemedText style={[styles.hintText, { color: palette.successText }]}>
                        وصلت الرسالة فعلاً واكتملت بتاريخ {formatLogDate(log.sent_at)} — نص الحالة في الخادم متأخر فقط.
                      </ThemedText>
                    </View>
                  ) : null}
                  <View style={styles.timeline}>
                    {steps.map((stepLabel, i) => {
                      const done = i <= currentStep;
                      const active = i === currentStep;
                      const connectorColor =
                        i < currentStep ? meta.color : border;
                      return (
                        <React.Fragment key={stepLabel}>
                          <View style={styles.timelineStep}>
                            <View
                              style={[
                                styles.timelineDot,
                                {
                                  backgroundColor: done ? meta.color : border,
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
                <ThemedText style={[styles.logTitle, { color: palette.text, fontFamily: invitationLogFontName(log, 'bold') }]}>
                  {log.title}
                </ThemedText>
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
                  <Image
                    source={{ uri: log.media_url }}
                    contentFit="contain"
                    accessibilityLabel="صورة الدعوة"
                    style={{ width: '100%', height: 300, marginBottom: 12, borderRadius: 12 }}
                  />
                ) : null}
                <ThemedText style={[styles.body, { color: palette.text, fontFamily: invitationLogFontName(log, 'regular') }]}>
                  {log.body}
                </ThemedText>
              </View>

              {incoming && savedResponse !== undefined ? (
                <View style={[styles.bodyBox, { backgroundColor: cardBg, borderColor: border }]}>
                  <ThemedText style={[styles.section, { color: palette.text }]}>الرد على الدعوة</ThemedText>
                  <ThemedText
                    style={[
                      styles.responseStatus,
                      {
                        color: savedResponse === 'accepted'
                          ? palette.successText
                          : savedResponse === 'declined'
                            ? palette.dangerText
                            : muted,
                      },
                    ]}
                    accessibilityLiveRegion="polite">
                    {isResponding
                      ? 'جارٍ حفظ ردك…'
                      : responseOptions.find((option) => option.value === savedResponse)?.status ?? 'بانتظار ردك'}
                  </ThemedText>
                  {savedResponse === null ? (
                    <View style={styles.responseActions}>
                      {responseOptions.map((option) => {
                        const pending = pendingResponse === option.value;
                        const primary = option.value === 'accepted';
                        const accent = option.value === 'accepted' ? acceptColor : declineColor;
                        const buttonColor = primary ? '#FFFFFF' : accent;
                        return (
                          <Pressable
                            key={option.value}
                            accessibilityRole="button"
                            accessibilityLabel={option.button}
                            accessibilityState={{ disabled: isResponding, busy: pending }}
                            disabled={isResponding}
                            android_ripple={{ color: buttonColor + '24' }}
                            onPress={() => setConfirmationResponse(option.value)}
                            style={({ pressed }) => [
                              styles.responseButton,
                              {
                                borderColor: primary ? accent : palette.dangerBorder,
                                backgroundColor: primary ? accent : palette.dangerBg,
                                opacity: pressed || (isResponding && !pending) ? 0.7 : 1,
                              },
                            ]}>
                            {pending ? (
                              <ActivityIndicator color={buttonColor} size="small" />
                            ) : (
                              <Ionicons name={option.icon} size={18} color={buttonColor} />
                            )}
                            <ThemedText align="center" style={[styles.responseButtonText, { color: buttonColor }]}>
                              {option.button}
                            </ThemedText>
                          </Pressable>
                        );
                      })}
                    </View>
                  ) : null}
                  {responseError ? (
                    <ThemedText style={[styles.recipientSub, { color: palette.dangerText }]} accessibilityLiveRegion="polite">
                      {responseError}
                    </ThemedText>
                  ) : null}
                </View>
              ) : null}

              {!incoming ? (
                <>
                  <ThemedText style={[styles.section, { color: palette.text }]}>
                    المستلمون{totalRecipients > 0 ? ` (${totalRecipients})` : ''}
                  </ThemedText>
                  {appRecipients.length > 0 ? (
                    <View style={styles.responseRow}>
                      {['موافق', 'معتذر', 'بانتظار الرد'].map((label, index) => (
                        <View
                          key={label}
                          accessible
                          accessibilityLabel={`${responseCounts[index]} ${label}`}
                          style={[styles.responseCount, { backgroundColor: cardBg, borderColor: border }]}>
                          <ThemedText align="center" style={[styles.responseButtonText, { color: palette.text }]}>
                            {responseCounts[index]}
                          </ThemedText>
                          <ThemedText align="center" style={[styles.recipientSub, { color: muted }]}>
                            {label}
                          </ThemedText>
                        </View>
                      ))}
                    </View>
                  ) : null}
                  {recipients.length === 0 ? (
                    <ThemedText style={[styles.date, { color: muted }]}>لا يوجد مستلمون مسجّلون</ThemedText>
                  ) : (
                    recipients.map((r) => {
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
                            <ThemedText style={[styles.recipientName, { color: palette.text }]}>
                              {displayName}
                            </ThemedText>
                            {r.recipient_phone && r.person_id == null ? (
                              <ThemedText style={[styles.recipientSub, { color: muted }]}>
                                {r.recipient_phone}
                              </ThemedText>
                            ) : null}
                            {showResponses ? (
                              <ThemedText style={[styles.recipientSub, { color: muted }]}>
                                {r.person_id == null
                                  ? 'خارج التطبيق'
                                  : responseOptions.find((option) => option.value === r.invitation_response)?.label ?? 'بانتظار الرد'}
                              </ThemedText>
                            ) : null}
                          </View>
                          {(r.delivered_at || r.sent_at) ? (
                            <Ionicons name="checkmark-done" size={16} color={palette.primary} />
                          ) : null}
                        </View>
                      );
                    })
                  )}
                </>
              ) : null}
            </ScrollView>
          )}

          <InvitationGradientButton
            label="إغلاق"
            onPress={onClose}
            palette={palette}
            style={{ marginTop: 12 }}
          />
        </View>

        {confirmation ? (
          <View style={[StyleSheet.absoluteFill, styles.overlay, { backgroundColor: palette.overlay }]} accessibilityViewIsModal>
            <View style={[styles.card, styles.confirmationCard, { backgroundColor: cardBg, borderColor: border }]}>
              <View style={[styles.confirmationIcon, { backgroundColor: confirmationColor + '18' }]}>
                <Ionicons name={confirmation.icon} color={confirmationColor} size={24} />
              </View>
              <ThemedText accessibilityRole="header" align="center" style={[styles.title, { color: palette.text }]}>
                {`تأكيد ${confirmation.button}`}
              </ThemedText>
              <ThemedText align="center" style={[styles.confirmationMessage, { color: muted }]}>
                بعد التأكيد، لا يمكنك تغيير ردك.
              </ThemedText>
              <View style={styles.responseActions}>
                <Pressable
                  accessibilityRole="button"
                  accessibilityLabel={confirmation.confirm}
                  onPress={() => {
                    setConfirmationResponse(null);
                    void onRespond(confirmation.value);
                  }}
                  style={({ pressed }) => [
                    styles.responseButton,
                    { backgroundColor: confirmationColor, borderColor: confirmationColor, opacity: pressed ? 0.7 : 1 },
                  ]}>
                  <ThemedText align="center" style={[styles.responseButtonText, { color: '#FFFFFF' }]}>
                    {confirmation.confirm}
                  </ThemedText>
                </Pressable>
                <Pressable
                  accessibilityRole="button"
                  accessibilityLabel="إلغاء"
                  onPress={() => setConfirmationResponse(null)}
                  style={({ pressed }) => [
                    styles.responseButton,
                    { borderColor: border, backgroundColor: palette.surfaceAlt, opacity: pressed ? 0.7 : 1 },
                  ]}>
                  <ThemedText align="center" style={[styles.responseButtonText, { color: palette.text }]}>
                    إلغاء
                  </ThemedText>
                </Pressable>
              </View>
            </View>
          </View>
        ) : null}
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  overlay: {
    flex: 1,
    justifyContent: 'center',
    padding: 18,
  },
  card: {
    borderRadius: 24,
    borderWidth: 1,
    padding: 16,
    maxHeight: '86%',
  },
  confirmationCard: {
    alignSelf: 'center',
    width: '100%',
    maxWidth: 380,
    padding: 20,
  },
  confirmationIcon: {
    width: 48,
    height: 48,
    borderRadius: 24,
    alignSelf: 'center',
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 12,
  },
  confirmationMessage: {
    fontSize: 14,
    lineHeight: 22,
    marginTop: 4,
    marginBottom: 8,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 12,
  },
  headerCloseBtn: {
    width: 34,
    height: 34,
    borderRadius: 17,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
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
    textAlign: 'right',
  },
  date: {
    fontSize: 12,
    fontFamily: 'Tajawal-Regular',
    marginBottom: 10,
    textAlign: 'right',
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
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 10,
  },
  chText: {
    fontSize: 12,
    fontFamily: 'Tajawal-Medium',
  },
  bodyBox: {
    borderWidth: 1,
    borderRadius: 16,
    padding: 14,
    marginBottom: 14,
  },
  body: {
    fontSize: 14,
    lineHeight: 24,
    textAlign: 'right',
  },
  responseRow: {
    flexDirection: 'row',
    gap: 8,
    marginTop: 6,
    marginBottom: 10,
  },
  responseActions: {
    direction: 'rtl',
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
    marginTop: 10,
  },
  responseStatus: {
    fontSize: 13,
    fontFamily: 'Tajawal-Bold',
    textAlign: 'right',
  },
  responseButton: {
    flex: 1,
    flexDirection: 'row',
    minWidth: 112,
    minHeight: 48,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 5,
    padding: 8,
    borderWidth: 1,
    borderRadius: 14,
    overflow: 'hidden',
  },
  responseButtonText: {
    fontSize: 14,
    fontFamily: 'Tajawal-Bold',
  },
  responseCount: {
    flex: 1,
    padding: 8,
    borderWidth: 1,
    borderRadius: 14,
  },
  section: {
    fontSize: 14,
    fontFamily: 'Tajawal-Bold',
    marginBottom: 8,
    textAlign: 'right',
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
    textAlign: 'right',
  },
  recipientSub: {
    fontSize: 11,
    fontFamily: 'Tajawal-Regular',
    marginTop: 1,
    textAlign: 'right',
  },
});
