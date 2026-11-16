import { Colors, silaTheme } from '@/constants/theme';
import { confirmFaceAuthOtp, resendFaceAuthOtp } from '@/lib/api/authApi';
import MaterialIcons from '@expo/vector-icons/MaterialIcons';
import * as Haptics from 'expo-haptics';
import React, { useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Animated,
  Keyboard,
  KeyboardAvoidingView,
  Modal,
  Platform,
  ScrollView,
  StyleSheet,
  TextInput,
  TouchableOpacity,
  View,
  useColorScheme,
  useWindowDimensions,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { ThemedText } from '../themed-text';

const OTP_LENGTH = 6;

/** تحويل الأرقام العربية/الفارسية إلى 0-9 وحذف أي رموز أخرى */
function normalizeDigits(raw: string): string {
  const western = raw
    .replace(/[\u0660-\u0669]/g, (d) => String(d.charCodeAt(0) - 0x0660))
    .replace(/[\u06F0-\u06F9]/g, (d) => String(d.charCodeAt(0) - 0x06f0));
  return western.replace(/\D/g, '');
}

export interface DeviceTrustOtpModalProps {
  visible: boolean;
  challengeToken: string;
  maskedPhone: string;
  personName?: string;
  expiresIn?: number;
  isChild?: boolean;
  guardianRelation?: string;
  onSuccess: (authData: any) => void;
  onCancel: () => void;
}

/**
 * شاشة مخصصة (Full Screen Modal) مستقلة تماماً لتوثيق الجهاز الجديد عبر كود الواتساب
 * مصممة بتوافق كامل مع الوضعين الليلي والنهاري (Dark/Light Mode) مع مراعاة أعلى معايير التباين والوضوح لكبار السن
 */
export function DeviceTrustOtpModal({
  visible,
  challengeToken,
  maskedPhone,
  personName,
  expiresIn = 60,
  isChild = false,
  guardianRelation,
  onSuccess,
  onCancel,
}: DeviceTrustOtpModalProps) {
  const insets = useSafeAreaInsets();
  const colorScheme = useColorScheme();
  const isDark = colorScheme === 'dark';
  const { width: windowWidth } = useWindowDimensions();

  const [otpDigits, setOtpDigits] = useState<string[]>(Array(OTP_LENGTH).fill(''));
  const [isVerifying, setIsVerifying] = useState<boolean>(false);
  const [isResending, setIsResending] = useState<boolean>(false);
  const [resendTimer, setResendTimer] = useState<number>(expiresIn);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const inputRefs = useRef<(TextInput | null)[]>([]);
  const scrollViewRef = useRef<ScrollView | null>(null);
  const [shakeAnim] = useState(() => new Animated.Value(0));
  const isMountedRef = useRef<boolean>(true);

  // حساب أبعاد حقول الـ OTP لتناسب كافة الشاشات مع الحفاظ على سهولة اللمس
  const availableWidth = Math.min(windowWidth - 48, 400);
  const cellWidth = Math.max(Math.floor((availableWidth - (OTP_LENGTH - 1) * 8) / OTP_LENGTH), 44);
  const cellHeight = Math.floor(cellWidth * 1.18);

  useEffect(() => {
    isMountedRef.current = true;
    return () => {
      isMountedRef.current = false;
    };
  }, []);

  // رفع حقول الـ OTP للأعلى فور ظهور الكيبورد لتظل مرئية بوضوح تام
  useEffect(() => {
    if (!visible) return;
    const showSub = Keyboard.addListener(
      Platform.OS === 'ios' ? 'keyboardWillShow' : 'keyboardDidShow',
      () => {
        setTimeout(() => {
          scrollViewRef.current?.scrollTo({ y: 50, animated: true });
        }, 60);
      }
    );
    return () => showSub.remove();
  }, [visible]);

  // إعادة ضبط الحالة عند فتح المودال
  useEffect(() => {
    if (visible) {
      setOtpDigits(Array(OTP_LENGTH).fill(''));
      setErrorMessage(null);
      setIsVerifying(false);
      setIsResending(false);
      setResendTimer(expiresIn > 0 ? expiresIn : 60);

      // تركيز فوري سلس على الحقل الأول بعد انقضاء حركة الفتح
      const timer = setTimeout(() => {
        if (isMountedRef.current) {
          inputRefs.current[0]?.focus();
        }
      }, 350);

      return () => clearTimeout(timer);
    }
  }, [visible, expiresIn, challengeToken]);

  // عداد إعادة الإرسال
  useEffect(() => {
    if (!visible || resendTimer <= 0) return;
    const interval = setInterval(() => {
      setResendTimer((prev) => (prev > 0 ? prev - 1 : 0));
    }, 1000);
    return () => clearInterval(interval);
  }, [visible, resendTimer]);

  const triggerShake = () => {
    shakeAnim.setValue(0);
    Animated.sequence([
      Animated.timing(shakeAnim, { toValue: 10, duration: 60, useNativeDriver: true }),
      Animated.timing(shakeAnim, { toValue: -10, duration: 60, useNativeDriver: true }),
      Animated.timing(shakeAnim, { toValue: 8, duration: 60, useNativeDriver: true }),
      Animated.timing(shakeAnim, { toValue: -8, duration: 60, useNativeDriver: true }),
      Animated.timing(shakeAnim, { toValue: 0, duration: 60, useNativeDriver: true }),
    ]).start();
  };

  const handleDigitChange = (rawText: string, index: number) => {
    const cleaned = normalizeDigits(rawText);

    // حالة اللصق المباشر لكود كامل (6 أرقام)
    if (cleaned.length >= OTP_LENGTH) {
      const fullDigits = cleaned.slice(0, OTP_LENGTH).split('');
      setOtpDigits(fullDigits);
      setErrorMessage(null);
      Keyboard.dismiss();
      void submitCode(cleaned.slice(0, OTP_LENGTH));
      return;
    }

    const val = cleaned.slice(-1);
    const updated = [...otpDigits];
    updated[index] = val;
    setOtpDigits(updated);
    setErrorMessage(null);

    // الانتقال التلقائي للخانة التالية
    if (val && index < OTP_LENGTH - 1) {
      inputRefs.current[index + 1]?.focus();
    }

    // إرسال الكود تلقائياً عند اكتمال الـ 6 أرقام
    if (val && updated.every((d) => d !== '') && updated.join('').length === OTP_LENGTH) {
      Keyboard.dismiss();
      void submitCode(updated.join(''));
    }
  };

  const handleKeyPress = (e: any, index: number) => {
    if (e.nativeEvent.key === 'Backspace') {
      if (!otpDigits[index] && index > 0) {
        const updated = [...otpDigits];
        updated[index - 1] = '';
        setOtpDigits(updated);
        inputRefs.current[index - 1]?.focus();
      }
    }
  };

  const submitCode = async (codeToSubmit: string) => {
    if (!challengeToken || isVerifying) return;
    setIsVerifying(true);
    setErrorMessage(null);

    try {
      const res = await confirmFaceAuthOtp(challengeToken, codeToSubmit);
      if (!isMountedRef.current) return;
      setIsVerifying(false);

      if (res.success && res.data) {
        if (Platform.OS !== 'web') {
          void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
        }
        onSuccess(res.data);
      } else {
        if (Platform.OS !== 'web') {
          void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error);
        }
        triggerShake();
        setErrorMessage(res.message || 'رمز التحقق غير صحيح أو انتهت صلاحيته. يرجى إعادة المحاولة.');
        setOtpDigits(Array(OTP_LENGTH).fill(''));
        setTimeout(() => {
          if (isMountedRef.current) {
            inputRefs.current[0]?.focus();
          }
        }, 200);
      }
    } catch {
      if (!isMountedRef.current) return;
      setIsVerifying(false);
      triggerShake();
      setErrorMessage('حدث خطأ أثناء الاتصال بالخادم. يرجى التحقق من الشبكة.');
    }
  };

  const handleResend = async () => {
    if (!challengeToken || isResending || resendTimer > 0) return;
    setIsResending(true);
    setErrorMessage(null);

    try {
      const res = await resendFaceAuthOtp(challengeToken);
      if (!isMountedRef.current) return;
      setIsResending(false);

      if (res.success) {
        if (Platform.OS !== 'web') {
          void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
        }
        setResendTimer(res.data?.expires_in ? Math.min(res.data.expires_in, 60) : 60);
      } else {
        setErrorMessage(res.message || 'تعذر إعادة إرسال الكود في الوقت الحالي.');
      }
    } catch {
      if (!isMountedRef.current) return;
      setIsResending(false);
      setErrorMessage('تعذر الاتصال بالخادم لإعادة الإرسال.');
    }
  };

  const isCodeComplete = otpDigits.every((d) => d !== '') && otpDigits.join('').length === OTP_LENGTH;

  return (
    <Modal
      visible={visible}
      animationType="slide"
      transparent={false}
      statusBarTranslucent
      onRequestClose={onCancel}
    >
      <View
        style={[
          styles.container,
          {
            backgroundColor: isDark ? '#0F172A' : '#F8FAFC',
            paddingTop: insets.top,
            paddingBottom: insets.bottom,
          },
        ]}
      >
        {/* شريط الإغلاق والتنقل العلوي المدمج */}
        <View style={styles.topBar}>
          <TouchableOpacity
            style={[styles.closeBtn, { backgroundColor: isDark ? '#1E293B' : '#E2E8F0' }]}
            onPress={onCancel}
            activeOpacity={0.7}
            accessibilityLabel="إلغاء والعودة"
          >
            <MaterialIcons name="close" size={22} color={isDark ? '#F1F5F9' : '#334155'} />
          </TouchableOpacity>

          <View style={styles.headerTitleWrap}>
            <ThemedText style={[styles.headerTitle, { color: isDark ? '#F1F5F9' : '#0F172A' }]}>
              توثيق الدخول لأول مرة
            </ThemedText>
          </View>

          <View style={{ width: 36 }} />
        </View>

        {/* حاوية التكيف مع الكيبورد */}
        <KeyboardAvoidingView
          behavior={Platform.OS === 'ios' ? 'padding' : undefined}
          keyboardVerticalOffset={Platform.OS === 'ios' ? insets.top : 0}
          style={styles.keyboardAvoid}
        >
          <ScrollView
            ref={scrollViewRef}
            style={styles.scrollView}
            contentContainerStyle={styles.scrollContent}
            keyboardShouldPersistTaps="handled"
            showsVerticalScrollIndicator={false}
          >
            {/* بطاقة مدمجة تجمع التحقق البيومتري وإشعار الواتساب بدون هدر للمساحة */}
            <View
              style={[
                styles.compactHeroCard,
                {
                  backgroundColor: isDark ? '#1E293B' : '#FFFFFF',
                  borderColor: isDark ? '#334155' : '#E2E8F0',
                },
              ]}
            >
              {/* السطر الأول: الترحيب والتحقق البيومتري + وسام الواتساب */}
              <View style={styles.heroTopRow}>
                <View
                  style={[
                    styles.faceSuccessPill,
                    { backgroundColor: isDark ? 'rgba(16, 185, 129, 0.2)' : '#ECFDF5' },
                  ]}
                >
                  <MaterialIcons name="check-circle" size={17} color="#10B981" />
                  <ThemedText
                    style={[
                      styles.faceSuccessText,
                      { color: isDark ? '#34D399' : '#065F46' },
                    ]}
                  >
                    {personName ? `أهلاً بك يا ${personName}` : 'تمت مطابقة الوجه بنجاح ✓'}
                  </ThemedText>
                </View>

                <View
                  style={[
                    styles.whatsappPill,
                    { backgroundColor: isDark ? 'rgba(37, 211, 102, 0.2)' : '#DCFCE7' },
                  ]}
                >
                  <MaterialIcons name="chat" size={15} color="#25D366" />
                  <ThemedText
                    style={[
                      styles.whatsappPillText,
                      { color: isDark ? '#4ADE80' : '#166534' },
                    ]}
                  >
                    كود الواتساب
                  </ThemedText>
                </View>
              </View>

              {/* السطر الثاني: رقم الجوال المعتمد بخط عريض وواضح في الوضعين */}
              <View style={styles.phoneDisplayRow}>
                <ThemedText style={[styles.phoneIntroText, { color: isDark ? '#94A3B8' : '#64748B' }]}>
                  {isChild
                    ? `تم إرسال كود التحقق إلى واتساب ${guardianRelation || 'ولي الأمر'}:`
                    : 'تم إرسال كود التحقق إلى الرقم:'}
                </ThemedText>
                <ThemedText
                  style={[
                    styles.phoneHighlightText,
                    { color: isDark ? '#4ADE80' : '#15803D' },
                  ]}
                >
                  {maskedPhone || 'رقم جوالك المعتمد'}
                </ThemedText>
              </View>

              {/* السطر الثالث: طمأنة المستخدم بأنها خطوة لمرة واحدة */}
              <View
                style={[
                  styles.reassuranceRow,
                  { backgroundColor: isDark ? 'rgba(217, 119, 6, 0.2)' : '#FEF3C7' },
                ]}
              >
                <MaterialIcons name="verified-user" size={15} color={isDark ? '#FCD34D' : '#D97706'} />
                <ThemedText
                  style={[
                    styles.reassuranceText,
                    { color: isDark ? '#FCD34D' : '#92400E' },
                  ]}
                >
                  {isChild
                    ? 'توثيق لمرة واحدة للابن أو الابنة على هذا الجهاز — لن يُطلب منه الرمز مجدداً.'
                    : 'توثيق لمرة واحدة فقط — لن يُطلب منك هذا الرمز مجدداً.'}
                </ThemedText>
              </View>
            </View>

            {/* قسم إدخال رمز التحقق المرفوع للأعلى بوضوح */}
            <View style={styles.inputSection}>
              <ThemedText style={[styles.inputLabel, { color: isDark ? '#F1F5F9' : '#1E293B' }]}>
                أدخل رمز التحقق (6 أرقام):
              </ThemedText>

              {/* حقول الـ OTP مع تباين ألوان مثالي في الوضع الليلي والنهاري */}
              <Animated.View
                style={[
                  styles.otpRow,
                  {
                    transform: [{ translateX: shakeAnim }],
                  },
                ]}
              >
                {otpDigits.map((digit, idx) => {
                  const isFocused = !digit && (idx === 0 || otpDigits[idx - 1] !== '');

                  // لون خلفية الخلية: يراعي التباين التام في الوضعين
                  const boxBg = isDark
                    ? digit
                      ? 'rgba(55, 160, 92, 0.25)'
                      : '#1E293B'
                    : digit
                    ? '#F0FDF4'
                    : '#FFFFFF';

                  // لون حدود الخلية
                  const boxBorder = errorMessage
                    ? '#EF4444'
                    : digit || isFocused
                    ? silaTheme.primary
                    : isDark
                    ? '#334155'
                    : '#CBD5E1';

                  // لون الخط داخل الخلية: أبيض صريح في الليلي، وأسود/داكن صريح في النهاري
                  const digitColor = isDark ? '#FFFFFF' : '#0F172A';

                  return (
                    <TextInput
                      key={idx}
                      ref={(el) => {
                        inputRefs.current[idx] = el;
                      }}
                      style={[
                        styles.otpBox,
                        {
                          width: cellWidth,
                          height: cellHeight,
                          backgroundColor: boxBg,
                          borderColor: boxBorder,
                          color: digitColor,
                        },
                      ]}
                      value={digit}
                      selectionColor={silaTheme.primary}
                      onFocus={() => {
                        setTimeout(() => {
                          scrollViewRef.current?.scrollTo({ y: 50, animated: true });
                        }, 50);
                      }}
                      onChangeText={(txt) => handleDigitChange(txt, idx)}
                      onKeyPress={(e) => handleKeyPress(e, idx)}
                      keyboardType="number-pad"
                      textContentType="oneTimeCode"
                      autoComplete="sms-otp"
                      importantForAutofill="yes"
                      maxLength={idx === 0 ? OTP_LENGTH : 1}
                      selectTextOnFocus
                      editable={!isVerifying}
                    />
                  );
                })}
              </Animated.View>

              {/* رسالة الخطأ الواضحة */}
              {errorMessage && (
                <View
                  style={[
                    styles.errorBanner,
                    {
                      backgroundColor: isDark ? 'rgba(239, 68, 68, 0.2)' : '#FEE2E2',
                      borderColor: isDark ? '#7F1D1D' : '#FECACA',
                    },
                  ]}
                >
                  <MaterialIcons name="error-outline" size={18} color={isDark ? '#F87171' : '#DC2626'} />
                  <ThemedText
                    style={[
                      styles.errorText,
                      { color: isDark ? '#FCA5A5' : '#991B1B' },
                    ]}
                  >
                    {errorMessage}
                  </ThemedText>
                </View>
              )}

              {/* زر التأكيد الرئيسي البارز */}
              <TouchableOpacity
                style={[
                  styles.submitBtn,
                  {
                    backgroundColor: isCodeComplete && !isVerifying ? silaTheme.primary : isDark ? '#334155' : '#94A3B8',
                  },
                ]}
                onPress={() => submitCode(otpDigits.join(''))}
                disabled={!isCodeComplete || isVerifying}
                activeOpacity={0.85}
              >
                {isVerifying ? (
                  <View style={styles.btnLoadingRow}>
                    <ActivityIndicator color="#FFFFFF" size="small" />
                    <ThemedText style={styles.submitBtnText}>جاري توثيق الجهاز والدخول...</ThemedText>
                  </View>
                ) : (
                  <View style={styles.btnRow}>
                    <MaterialIcons name="lock-open" size={20} color="#FFFFFF" />
                    <ThemedText style={styles.submitBtnText}>تأكيد الرمز وتوثيق الجهاز</ThemedText>
                  </View>
                )}
              </TouchableOpacity>

              {/* خيار إعادة إرسال الكود */}
              <View style={styles.resendContainer}>
                {resendTimer > 0 ? (
                  <ThemedText style={[styles.timerText, { color: isDark ? '#94A3B8' : '#64748B' }]}>
                    يمكنك طلب رمز جديد بعد:{' '}
                    <ThemedText style={{ color: silaTheme.primary, fontWeight: '700' }}>
                      {resendTimer} ثانية
                    </ThemedText>
                  </ThemedText>
                ) : (
                  <TouchableOpacity
                    onPress={() => void handleResend()}
                    disabled={isResending}
                    activeOpacity={0.7}
                    style={styles.resendBtn}
                  >
                    <MaterialIcons name="refresh" size={18} color={silaTheme.primary} />
                    <ThemedText style={styles.resendBtnText}>
                      {isResending ? 'جاري إعادة الإرسال...' : 'إعادة إرسال كود الواتساب الآن'}
                    </ThemedText>
                  </TouchableOpacity>
                )}
              </View>

              {/* زر إلغاء والعودة */}
              <TouchableOpacity
                onPress={onCancel}
                activeOpacity={0.7}
                style={styles.cancelLink}
              >
                <ThemedText style={[styles.cancelLinkText, { color: isDark ? '#94A3B8' : '#64748B' }]}>
                  إلغاء والعودة لشاشة الدخول الرئيسية
                </ThemedText>
              </TouchableOpacity>
            </View>
          </ScrollView>
        </KeyboardAvoidingView>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
  },
  topBar: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 16,
    paddingVertical: 10,
  },
  closeBtn: {
    width: 36,
    height: 36,
    borderRadius: 18,
    alignItems: 'center',
    justifyContent: 'center',
  },
  headerTitleWrap: {
    flex: 1,
    alignItems: 'center',
  },
  headerTitle: {
    fontSize: 16,
    fontWeight: '700',
  },
  keyboardAvoid: {
    flex: 1,
  },
  scrollView: {
    flex: 1,
  },
  scrollContent: {
    flexGrow: 1,
    paddingHorizontal: 18,
    paddingTop: 4,
    paddingBottom: 140,
  },
  compactHeroCard: {
    borderRadius: 18,
    paddingVertical: 12,
    paddingHorizontal: 16,
    marginBottom: 14,
    borderWidth: 1,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.04,
    shadowRadius: 6,
    elevation: 2,
  },
  heroTopRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 8,
  },
  faceSuccessPill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingVertical: 4,
    paddingHorizontal: 10,
    borderRadius: 10,
  },
  faceSuccessText: {
    fontSize: 13,
    fontWeight: '700',
  },
  whatsappPill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingVertical: 4,
    paddingHorizontal: 8,
    borderRadius: 10,
  },
  whatsappPillText: {
    fontSize: 12,
    fontWeight: '700',
  },
  phoneDisplayRow: {
    flexDirection: 'row-reverse',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    marginVertical: 4,
  },
  phoneIntroText: {
    fontSize: 13,
    fontWeight: '600',
  },
  phoneHighlightText: {
    fontSize: 16,
    fontWeight: '800',
    writingDirection: 'ltr',
    letterSpacing: 1,
  },
  reassuranceRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
    paddingVertical: 5,
    paddingHorizontal: 10,
    borderRadius: 8,
    marginTop: 6,
  },
  reassuranceText: {
    fontSize: 12,
    fontWeight: '600',
  },
  inputSection: {
    alignItems: 'center',
    marginTop: 4,
  },
  inputLabel: {
    fontSize: 15,
    fontWeight: '700',
    marginBottom: 12,
    alignSelf: 'center',
  },
  otpRow: {
    flexDirection: 'row-reverse',
    justifyContent: 'center',
    alignItems: 'center',
    gap: 8,
    marginBottom: 14,
    width: '100%',
  },
  otpBox: {
    borderWidth: 2,
    borderRadius: 12,
    fontSize: 22,
    fontWeight: '800',
    textAlign: 'center',
    writingDirection: 'ltr',
    elevation: 2,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 4,
  },
  errorBanner: {
    flexDirection: 'row',
    alignItems: 'center',
    borderWidth: 1,
    borderRadius: 10,
    paddingVertical: 8,
    paddingHorizontal: 12,
    marginBottom: 12,
    width: '100%',
  },
  errorText: {
    flex: 1,
    fontSize: 12.5,
    fontWeight: '600',
    marginRight: 6,
    lineHeight: 18,
  },
  submitBtn: {
    width: '100%',
    height: 52,
    borderRadius: 14,
    alignItems: 'center',
    justifyContent: 'center',
    shadowColor: silaTheme.primary,
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.2,
    shadowRadius: 6,
    elevation: 3,
    marginTop: 4,
    marginBottom: 12,
  },
  btnRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
  },
  btnLoadingRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
  },
  submitBtnText: {
    color: '#FFFFFF',
    fontSize: 15,
    fontWeight: '800',
  },
  resendContainer: {
    alignItems: 'center',
    marginVertical: 6,
  },
  timerText: {
    fontSize: 13.5,
    fontWeight: '600',
  },
  resendBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingVertical: 4,
    paddingHorizontal: 12,
  },
  resendBtnText: {
    fontSize: 13.5,
    fontWeight: '700',
    color: silaTheme.primary,
  },
  cancelLink: {
    marginTop: 8,
    paddingVertical: 6,
  },
  cancelLinkText: {
    fontSize: 13.5,
    fontWeight: '600',
    textDecorationLine: 'underline',
  },
});
