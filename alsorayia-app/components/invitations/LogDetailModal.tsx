import { PersonAvatar } from '@/components/invitations/PersonAvatar';
import { invitationLogFontName } from '@/components/invitations/composer/typography';
import { ThemedText } from '@/components/themed-text';
import { IconSymbol } from '@/components/ui/icon-symbol';
import { silaTheme, type ThemeColors } from '@/constants/theme';
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
  Text,
  TouchableOpacity,
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
  onRespond,
  pendingResponse,
  responseError,
  colors,
  isDark,
}: LogDetailModalProps) {
  const [confirmationResponse, setConfirmationResponse] = React.useState<InvitationResponse | null>(null);
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
  const savedResponse = log?.invitation_response;
  const isResponding = pendingResponse !== null;
  const acceptColor = isDark ? silaTheme.primaryLight : silaTheme.primaryDark;
  const declineColor = isDark ? '#FCA5A5' : '#B91C1C';
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
    <Modal visible={visible} transparent animationType="fade"
      onRequestClose={confirmation ? () => setConfirmationResponse(null) : onClose}>
      <View style={styles.overlay}>
        <View style={[styles.card, { backgroundColor: isDark ? '#0F172A' : '#F8FAFC' }]}
          accessibilityElementsHidden={Boolean(confirmation)}
          importantForAccessibility={confirmation ? 'no-hide-descendants' : 'auto'}>
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
              {incoming && savedResponse !== undefined ? (
                <View style={[styles.bodyBox, { backgroundColor: cardBg, borderColor: border }]}>
                  <ThemedText style={styles.section}>الرد على الدعوة</ThemedText>
                  <ThemedText style={[styles.responseStatus, {
                    color: savedResponse === 'accepted' ? acceptColor : savedResponse === 'declined' ? declineColor : muted,
                  }]} accessibilityLiveRegion="polite">
                    {isResponding ? 'جارٍ حفظ ردك…'
                      : responseOptions.find((option) => option.value === savedResponse)?.status ?? 'بانتظار ردك'}
                  </ThemedText>
                  {savedResponse === null ? <View style={styles.responseActions}>
                    {responseOptions.map((option) => {
                      const pending = pendingResponse === option.value;
                      const primary = option.value === 'accepted';
                      const accent = option.value === 'accepted' ? acceptColor : declineColor;
                      const buttonColor = primary ? (isDark ? '#102418' : '#FFFFFF') : accent;
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
                              borderColor: primary ? accent : border,
                              backgroundColor: primary ? accent : cardBg,
                              opacity: pressed || (isResponding && !pending) ? 0.7 : 1,
                            },
                          ]}>
                          {pending ? <ActivityIndicator color={buttonColor} size="small" />
                            : <Ionicons name={option.icon} size={18} color={buttonColor} />}
                          <ThemedText align="center" style={[styles.responseButtonText, { color: buttonColor }]}>
                            {option.button}
                          </ThemedText>
                        </Pressable>
                      );
                    })}
                  </View> : null}
                  {responseError ? (
                    <ThemedText style={[styles.recipientSub, { color: isDark ? '#FCA5A5' : '#B91C1C' }]} accessibilityLiveRegion="polite">
                      {responseError}
                    </ThemedText>
                  ) : null}
                </View>
              ) : null}
              {!incoming ? (
                <>
                  <ThemedText style={styles.section}>
                    المستلمون{totalRecipients > 0 ? ` (${totalRecipients})` : ''}
                  </ThemedText>
                  {appRecipients.length > 0 ? (
                    <View style={styles.responseRow}>
                      {['موافق', 'معتذر', 'بانتظار الرد'].map((label, index) => (
                        <View key={label} accessible accessibilityLabel={`${responseCounts[index]} ${label}`} style={[styles.responseCount, { borderColor: border }]}>
                          <ThemedText align="center" style={styles.responseButtonText}>{responseCounts[index]}</ThemedText>
                          <ThemedText align="center" style={[styles.recipientSub, { color: muted }]}>{label}</ThemedText>
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
                            <ThemedText style={styles.recipientName}>
                              {displayName}
                            </ThemedText>
                            {r.recipient_phone && r.person_id == null ? (
                              <ThemedText style={[styles.recipientSub, { color: muted }]}>
                                {r.recipient_phone}
                              </ThemedText>
                            ) : null}
                            {showResponses ? (
                              <ThemedText style={[styles.recipientSub, { color: muted }]}>
                                {r.person_id == null ? 'خارج التطبيق'
                                  : responseOptions.find((option) => option.value === r.invitation_response)?.label ?? 'بانتظار الرد'}
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
        {confirmation ? (
          <View style={[StyleSheet.absoluteFill, styles.overlay]} accessibilityViewIsModal>
            <View style={[styles.card, styles.confirmationCard, { backgroundColor: cardBg, borderColor: border }]}>
              <View style={[styles.confirmationIcon, { backgroundColor: confirmationColor + '18' }]}>
                <Ionicons name={confirmation.icon} color={confirmationColor} size={24} />
              </View>
              <ThemedText accessibilityRole="header" align="center" style={styles.title}>
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
                  <ThemedText align="center" style={[styles.responseButtonText, { color: isDark ? '#0F172A' : '#FFFFFF' }]}>
                    {confirmation.confirm}
                  </ThemedText>
                </Pressable>
                <Pressable
                  accessibilityRole="button"
                  accessibilityLabel="إلغاء"
                  onPress={() => setConfirmationResponse(null)}
                  style={({ pressed }) => [styles.responseButton, { borderColor: border, opacity: pressed ? 0.7 : 1 }]}>
                  <ThemedText align="center" style={styles.responseButtonText}>إلغاء</ThemedText>
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
    backgroundColor: 'rgba(15, 23, 42, 0.55)',
    justifyContent: 'center',
    padding: 20,
  },
  card: {
    borderRadius: 20,
    padding: 16,
    maxHeight: '85%',
  },
  confirmationCard: {
    alignSelf: 'center',
    width: '100%',
    maxWidth: 380,
    padding: 20,
    borderWidth: StyleSheet.hairlineWidth,
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
  responseRow: {
    flexDirection: 'row',
    gap: 8,
    marginTop: 6,
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
    fontFamily: 'Tajawal-Medium',
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
    borderRadius: 12,
    overflow: 'hidden',
  },
  responseButtonText: {
    fontSize: 14,
    fontFamily: 'Tajawal-Bold',
  },
  responseCount: {
    flex: 1,
    padding: 6,
    borderWidth: 1,
    borderRadius: 10,
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
