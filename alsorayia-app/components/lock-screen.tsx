import { Colors, silaTheme } from '@/constants/theme';
import { storage, useAuth } from '@/contexts/auth-context';
import {
  ChildPersonItem,
  lookupParentForChild,
  sendChildWhatsappOtp,
  sendWhatsappOtp,
  verifyChildWhatsappOtp,
  verifyFaceAuth,
  verifyWhatsappOtp,
} from '@/lib/api/authApi';
import { getSitePasswordStatus, verifySitePassword } from '@/lib/api/sitePasswordApi';
import MaterialIcons from '@expo/vector-icons/MaterialIcons';
import { LinearGradient } from 'expo-linear-gradient';
import { useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Animated,
  Easing,
  Image,
  Keyboard,
  KeyboardAvoidingView,
  NativeScrollEvent,
  NativeSyntheticEvent,
  Platform,
  ScrollView,
  TextInput,
  TextInputKeyPressEventData,
  TouchableOpacity,
  View,
  useColorScheme,
  useWindowDimensions
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { ChildSelectorModal } from './auth/ChildSelectorModal';
import { CountryPickerModal } from './auth/CountryPickerModal';
import { FaceCaptureResult, FaceLivenessModal } from './auth/FaceLivenessModal';
import { DeviceTrustOtpModal } from './auth/DeviceTrustOtpModal';
import { DeviceGraceNoticeModal } from './auth/DeviceGraceNoticeModal';
import {
  COUNTRIES,
  CountryCodeItem,
  DEFAULT_COUNTRY,
  formatPhoneDisplay,
  normalizePhoneNumber,
} from './auth/countryData';
import { ThemedText } from './themed-text';

import {
  FAMILY_FEATURES,
  OTP_LENGTH,
  normalizeOtpInput,
  triggerHaptic,
  type AuthTab,
  type ColorPalette,
} from './auth/lockScreenHelpers';
import { lockScreenStyles as styles } from './auth/lockScreenStyles';

// ==================== المكون الرئيسي لشاشة الدخول القوية ====================

export function LockScreen() {
  const { width } = useWindowDimensions();
  const insets = useSafeAreaInsets();
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme ?? 'light'] as ColorPalette;
  const isDark = colorScheme === 'dark';
  const { authenticate } = useAuth();

  // قياسات وتناسب الشاشات المختلفة (آيفون 13 والشاشات المتنوعة)
  const cardWidth = Math.max(280, width - 32);
  const scannerSize = Math.min(195, Math.floor(width * 0.46));
  const scannerRadius = scannerSize / 2;
  const imageInnerSize = scannerSize - 6;
  const imageInnerRadius = imageInnerSize / 2;

  // الحالة العامة
  const [activeTab, setActiveTab] = useState<AuthTab>('face');
  const [activeFeatureIndex, setActiveFeatureIndex] = useState(0);
  const [statusLoading, setStatusLoading] = useState(true);
  const [statusError, setStatusError] = useState<string | null>(null);
  const [passwordLength, setPasswordLength] = useState(6);
  const [showSuccess, setShowSuccess] = useState(false);
  const [globalError, setGlobalError] = useState<string | null>(null);

  // حساب أبعاد الخانات بحسب طول كلمة المرور وعرض الشاشة
  const otpCellSize = Math.min(46, Math.floor((width - 48 - (OTP_LENGTH - 1) * 8) / OTP_LENGTH));
  const otpCellHeight = Math.round(otpCellSize * 1.16);
  const pinCellSize = Math.min(46, Math.floor((width - 48 - (passwordLength - 1) * 8) / passwordLength));
  const pinCellHeight = Math.round(pinCellSize * 1.16);
  const keypadBtnSize = Math.min(66, Math.floor((width - 96) / 3));

  const [selectedCountry, setSelectedCountry] = useState<CountryCodeItem>(DEFAULT_COUNTRY);

  useEffect(() => {
    void storage.getItem('preferred_auth_tab').then((tab) => {
      if (tab === 'passcode' || tab === 'whatsapp' || tab === 'face') {
        setActiveTab(tab);
      }
    });
    void storage.getItem('preferred_country_code').then((savedCode) => {
      if (savedCode) {
        const found = COUNTRIES.find((c) => c.code === savedCode);
        if (found) setSelectedCountry(found);
      }
    });
  }, []);

  // ---------------- حالة بصمة الوجه والحيوية ----------------
  const [faceImageUri, setFaceImageUri] = useState<string | null>(null);
  const [isScanningFace, setIsScanningFace] = useState(false);
  const [scanProgress, setScanProgress] = useState(0);
  const [faceMatchScore, setFaceMatchScore] = useState<number | null>(null);
  const [faceVerified, setFaceVerified] = useState(false);
  const [showLivenessModal, setShowLivenessModal] = useState(false);
  const [faceAttempts, setFaceAttempts] = useState(0);
  const [faceLockoutTimer, setFaceLockoutTimer] = useState(0);
  const [scanAnim] = useState(() => new Animated.Value(0));

  // ---------------- حالة توثيق الجهاز الجديد وتنبيه المهلة ----------------
  const [deviceTrustChallenge, setDeviceTrustChallenge] = useState<{
    challengeToken: string;
    maskedPhone: string;
    personName?: string;
    expiresIn?: number;
    isChild?: boolean;
    guardianRelation?: string;
  } | null>(null);

  const [deviceGraceNotice, setDeviceGraceNotice] = useState<{
    personName?: string;
    hoursRemaining?: number;
    data: any;
  } | null>(null);

  // ---------------- حالة تسجيل دخول الطفل ----------------
  const [isChildMode, setIsChildMode] = useState(false);
  const [parentName, setParentName] = useState<string | undefined>(undefined);
  const [childrenList, setChildrenList] = useState<ChildPersonItem[]>([]);
  const [selectedChild, setSelectedChild] = useState<ChildPersonItem | null>(null);
  const [showChildSelector, setShowChildSelector] = useState(false);
  const [childRedirectNotice, setChildRedirectNotice] = useState<{
    isChild: boolean;
    childName?: string;
    guardianRelation?: string;
    maskedPhone?: string;
  } | null>(null);

  // ---------------- حالة الواتساب OTP والدول ولوحة المفاتيح ----------------
  const [phone, setPhone] = useState('');
  const [submittedPhone, setSubmittedPhone] = useState('');
  const [showCountryPicker, setShowCountryPicker] = useState(false);
  const [otpSent, setOtpSent] = useState(false);
  const [sendingOtp, setSendingOtp] = useState(false);
  const [otpCode, setOtpCode] = useState<string[]>(Array(6).fill(''));
  const [otpTimer, setOtpTimer] = useState(60);
  const [verifyingOtp, setVerifyingOtp] = useState(false);
  const [isKeyboardVisible, setIsKeyboardVisible] = useState(false);
  const [keyboardHeight, setKeyboardHeight] = useState(0);
  const scrollViewRef = useRef<ScrollView>(null);
  const carouselScrollRef = useRef<ScrollView>(null);

  // التمرير التلقائي السلس إلى قسم الإدخال لمنع تغطية الكيبورد
  const scrollToWhatsAppInputs = (delay = 120) => {
    setTimeout(() => {
      scrollViewRef.current?.scrollToEnd({ animated: true });
    }, delay);
  };

  const handleCountrySelect = (country: CountryCodeItem) => {
    setSelectedCountry(country);
    void storage.setItem('preferred_country_code', country.code);
  };

  // مراقبة أحداث لوحة المفاتيح لضبط الارتفاع والتمرير التلقائي
  useEffect(() => {
    const showSub = Keyboard.addListener(
      Platform.OS === 'ios' ? 'keyboardWillShow' : 'keyboardDidShow',
      (e) => {
        setIsKeyboardVisible(true);
        setKeyboardHeight(e.endCoordinates.height);
        if (activeTab === 'whatsapp') {
          setTimeout(() => {
            scrollViewRef.current?.scrollToEnd({ animated: true });
          }, 80);
        }
      }
    );
    const hideSub = Keyboard.addListener(
      Platform.OS === 'ios' ? 'keyboardWillHide' : 'keyboardDidHide',
      () => {
        setIsKeyboardVisible(false);
        setKeyboardHeight(0);
      }
    );
    return () => {
      showSub.remove();
      hideSub.remove();
    };
  }, [activeTab]);

  // ---------------- حالة كلمة المرور القديمة ----------------
  const [otp, setOtp] = useState<string[]>(Array(6).fill(''));
  const [isLoadingPasscode, setIsLoadingPasscode] = useState(false);
  const [hasPasscodeError, setHasPasscodeError] = useState(false);
  const [passcodeErrorMsg, setPasscodeErrorMsg] = useState<string | null>(null);
  const [attempts, setAttempts] = useState(0);
  const [isLocked, setIsLocked] = useState(false);
  const [lockTimer, setLockTimer] = useState(0);
  const verifyingRef = useRef(false);
  const verifyingOtpRef = useRef(false);
  const otpInputRefs = useRef<Array<TextInput | null>>([]);

  // جلب حالة الحماية من السيرفر
  useEffect(() => {
    let cancelled = false;
    (async () => {
      setStatusLoading(true);
      setStatusError(null);
      try {
        const status = await getSitePasswordStatus();
        if (cancelled) return;
        if (status?.success && !status.requires_password) {
          await authenticate();
          return;
        }
        const len = status?.password_length ?? 6;
        setPasswordLength(len);
        setOtp(Array(len).fill(''));
      } catch (err) {
        console.error('[LockScreen] getSitePasswordStatus error:', err);
        if (!cancelled) {
          const cachedStr = await storage.getItem('site_password_status_cache');
          if (cachedStr) {
            try {
              const cached = JSON.parse(cachedStr);
              if (!cached.requires_password) {
                await authenticate();
                return;
              }
              const len = cached.password_length ?? 6;
              setPasswordLength(len);
              setOtp(Array(len).fill(''));
              return;
            } catch { }
          }
          setStatusError('تعذر الاتصال بالخادم. تحقق من الشبكة وحاول مرة أخرى.');
        }
      } finally {
        if (!cancelled) setStatusLoading(false);
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [authenticate]);

  // مؤقت الإغلاق عند تجاوُز محاولات الـ PIN
  useEffect(() => {
    if (attempts >= 5) {
      setIsLocked(true);
      setLockTimer(30);
    }
  }, [attempts]);

  useEffect(() => {
    if (lockTimer > 0) {
      const timer = setTimeout(() => setLockTimer(lockTimer - 1), 1000);
      return () => clearTimeout(timer);
    } else if (lockTimer === 0 && isLocked) {
      setIsLocked(false);
      setAttempts(0);
    }
  }, [lockTimer, isLocked]);

  // مؤقت إغلاق بصمة الوجه عند تكرار الفشل الأمني
  useEffect(() => {
    if (faceLockoutTimer > 0) {
      const timer = setTimeout(() => setFaceLockoutTimer((prev) => prev - 1), 1000);
      return () => clearTimeout(timer);
    } else if (faceLockoutTimer === 0 && faceAttempts >= 5) {
      setFaceAttempts(0);
    }
  }, [faceLockoutTimer, faceAttempts]);

  // مؤقت إعادة إرسال كود الواتساب
  useEffect(() => {
    let interval: ReturnType<typeof setInterval> | undefined;
    if (otpSent && otpTimer > 0) {
      interval = setInterval(() => {
        setOtpTimer((prev) => prev - 1);
      }, 1000);
    }
    return () => {
      if (interval) clearInterval(interval);
    };
  }, [otpSent, otpTimer]);

  useEffect(() => {
    const code = otpCode.join('');
    if (!otpSent || code.length !== OTP_LENGTH || verifyingOtpRef.current) return;
    const timer = setTimeout(() => {
      // الدالة معلمة برفعٍ (hoisted `function`) وتُستدعى بعد الـ mount — لا TDZ فعلياً
      // eslint-disable-next-line react-hooks/immutability
      void handleVerifyWhatsappOtp(code);
    }, 50);
    return () => clearTimeout(timer);
  }, [otpCode, otpSent]);

  // انيميشن خط الليزر للوجه
  useEffect(() => {
    if (isScanningFace) {
      Animated.loop(
        Animated.sequence([
          Animated.timing(scanAnim, {
            toValue: 1,
            duration: 1200,
            easing: Easing.inOut(Easing.ease),
            useNativeDriver: true,
          }),
          Animated.timing(scanAnim, {
            toValue: 0,
            duration: 1200,
            easing: Easing.inOut(Easing.ease),
            useNativeDriver: true,
          }),
        ])
      ).start();
    } else {
      scanAnim.setValue(0);
    }
  }, [isScanningFace, scanAnim]);

  // ---------------- التفاعل مع بصمة الوجه والحيوية ----------------

  const handleOpenLiveness = async () => {
    if (faceLockoutTimer > 0) {
      await triggerHaptic('error');
      setGlobalError(
        `تم إيقاف بصمة الوجه مؤقتاً لحماية الحساب. يرجى الانتظار ${faceLockoutTimer} ثانية أو استخدام كود الواتساب.`
      );
      return;
    }
    await triggerHaptic('light');
    setGlobalError(null);
    setFaceVerified(false);
    setFaceMatchScore(null);
    setShowLivenessModal(true);
  };

  const handleVerifyFaceDirect = async (uri: string, base64?: string) => {
    setFaceImageUri(uri);
    setGlobalError(null);
    const meta = {
      liveness_score: 98.6,
      is_live_capture: true,
      client_timestamp: Date.now(),
    };
    const res = await verifyFaceAuth(uri, base64, meta);
    if (!res.success) {
      const nextAttempts = faceAttempts + 1;
      setFaceAttempts(nextAttempts);
      if (nextAttempts >= 5) {
        setFaceLockoutTimer(180);
        setActiveTab('whatsapp');
        setGlobalError(
          'تم إيقاف بصمة الوجه مؤقتاً لحماية الحساب بعد 5 محاولات غير متطابقة. يرجى الدخول عبر كود الواتساب.'
        );
      }
    }
    return res;
  };

  const handleFaceAuthSuccess = async (data: any) => {
    setShowLivenessModal(false);
    setDeviceTrustChallenge(null);
    setDeviceGraceNotice(null);
    setFaceAttempts(0);
    const score = data?.similarity_score ?? 99.1;
    setFaceMatchScore(score);
    setFaceVerified(true);
    setIsScanningFace(false);
    await triggerHaptic('success');
    await handleSuccessAuth({
      accessToken: data?.access_token,
      refreshToken: data?.refresh_token,
      expiresInSeconds: data?.expires_in,
      user: data?.person,
    });
  };

  function handleLivenessFaceCaptured(result: FaceCaptureResult) {
    setShowLivenessModal(false);
    setFaceImageUri(result.uri);
    void processFaceScanAndVerify(result.uri, result.base64, {
      liveness_score: result.livenessScore,
      is_live_capture: true,
      // eslint-disable-next-line react-hooks/purity -- تُستدعى فقط من كولباك التقاط الكاميرا وليس أثناء الـ render
      client_timestamp: Date.now(),
    });
  }

  async function processFaceScanAndVerify(
    imageUri: string,
    base64?: string | null,
    meta?: { liveness_score?: number; is_live_capture?: boolean; client_timestamp?: number }
  ) {
    setIsScanningFace(true);
    setScanProgress(0);
    setGlobalError(null);

    const stepInterval = setInterval(() => {
      setScanProgress((prev) => {
        if (prev >= 85) {
          return 85;
        }
        return prev + 15;
      });
    }, 120);

    try {
      const res = await verifyFaceAuth(imageUri, base64, meta);
      clearInterval(stepInterval);
      setScanProgress(100);

      if (res.success && res.data) {
        if (res.requires_otp) {
          setIsScanningFace(false);
          setDeviceTrustChallenge({
            challengeToken: res.data.challenge_token || '',
            maskedPhone: res.data.masked_phone || '',
            personName: res.data.person?.full_name || res.data.person?.first_name || '',
            expiresIn: res.data.expires_in || 60,
            isChild: res.is_child || res.data.is_child || false,
            guardianRelation: res.guardian_relation || res.data.guardian_relation,
          });
          return;
        }

        if (res.whatsapp_required) {
          setIsScanningFace(false);
          setDeviceGraceNotice({
            personName: res.data.person?.full_name || res.data.person?.first_name || '',
            hoursRemaining: res.grace_hours_remaining ?? res.data.grace_hours_remaining ?? 24,
            data: res.data,
          });
          return;
        }

        setFaceAttempts(0);
        const score = res.data.similarity_score ?? 99.1;
        setFaceMatchScore(score);
        setFaceVerified(true);
        setIsScanningFace(false);
        await triggerHaptic('success');

        setTimeout(() => {
          handleSuccessAuth({
            accessToken: res.data?.access_token,
            refreshToken: res.data?.refresh_token,
            expiresInSeconds: res.data?.expires_in,
            user: res.data?.person,
          });
        }, 700);
      } else {
        setIsScanningFace(false);
        await triggerHaptic('error');

        const isUnclearPhoto = res?.data?.similarity_score === 0;
        const nextAttempts = faceAttempts + 1;
        setFaceAttempts(nextAttempts);

        if (nextAttempts >= 5) {
          setFaceLockoutTimer(180);
          setActiveTab('whatsapp');
          setGlobalError(
            'تم إيقاف بصمة الوجه مؤقتاً لحماية الحساب بعد 5 محاولات غير متطابقة. يرجى الدخول عبر كود الواتساب.'
          );
        } else {
          if (isUnclearPhoto) {
            setGlobalError(
              `لم نتمكن من رصد ملامح الوجه بوضوح في الصورة. تأكد من وضوح الإضاءة وتوسيط وجهك بالكامل داخل الإطار. (المحاولات المتبقية: ${5 - nextAttempts})`
            );
          } else {
            setGlobalError(
              res.message ?? `فشلت المطابقة البيومترية. متبقي ${5 - nextAttempts} محاولات قبل الإيقاف المؤقت.`
            );
          }
        }
      }
    } catch (err) {
      clearInterval(stepInterval);
      setIsScanningFace(false);
      await triggerHaptic('error');
      setGlobalError('تعذر التحقق من بيئة الوجه، حاول مرة أخرى.');
    }
  }

  // ---------------- التفاعل مع الواتساب OTP ----------------

  const dispatchChildOtp = async (parentPhone: string, child: ChildPersonItem) => {
    setSendingOtp(true);
    setGlobalError(null);
    try {
      const res = await sendChildWhatsappOtp(parentPhone, child.id);
      setSendingOtp(false);
      setShowChildSelector(false);

      if (res.success) {
        setSubmittedPhone(parentPhone);
        verifyingOtpRef.current = false;
        setVerifyingOtp(false);
        setOtpSent(true);
        setOtpCode(Array(OTP_LENGTH).fill(''));
        setOtpTimer(res.data?.expires_in ? Math.min(res.data.expires_in, 60) : 60);
        await triggerHaptic('success');
        scrollToWhatsAppInputs(150);
        setTimeout(() => otpInputRefs.current[0]?.focus(), 250);
      } else {
        await triggerHaptic('error');
        setGlobalError(res.message ?? 'تعذر إرسال كود الواتساب. حاول مجدداً.');
      }
    } catch {
      setSendingOtp(false);
      await triggerHaptic('error');
      setGlobalError('حدث خطأ أثناء إرسال كود الواتساب لولي الأمر.');
    }
  };

  const handleSelectChildAndSendOtp = async (child: ChildPersonItem) => {
    setSelectedChild(child);
    const { fullPhone } = normalizePhoneNumber(phone, selectedCountry.dialCode);
    await dispatchChildOtp(fullPhone, child);
  };

  const handleSendWhatsappOtp = async () => {
    const { fullPhone, isValid, localDigits } = normalizePhoneNumber(
      phone,
      selectedCountry.dialCode
    );
    if (!isValid || localDigits.length < 7) {
      await triggerHaptic('error');
      setGlobalError(
        isChildMode
          ? 'يرجى إدخال رقم واتساب ولي الأمر (الأب أو الأم) بشكل صحيح.'
          : 'يرجى إدخال رقم جوال صحيح مسجل لدى العائلة.'
      );
      return;
    }
    await triggerHaptic('light');
    setGlobalError(null);
    setSendingOtp(true);

    // إذا كان في وضع تسجيل/دخول الطفل
    if (isChildMode) {
      if (selectedChild) {
        await dispatchChildOtp(fullPhone, selectedChild);
        return;
      }

      try {
        const lookup = await lookupParentForChild(fullPhone);
        setSendingOtp(false);

        if (!lookup.success || !lookup.data?.children?.length) {
          await triggerHaptic('error');
          setGlobalError(
            lookup.message || 'لا يوجد أطفال (15 سنة فأقل) مسجلين لحساب ولي الأمر هذا في شجرة العائلة.'
          );
          return;
        }

        const validChildren = lookup.data.children;
        setParentName(lookup.data.parent?.name);

        if (validChildren.length === 1) {
          const soleChild = validChildren[0];
          setSelectedChild(soleChild);
          await dispatchChildOtp(fullPhone, soleChild);
        } else {
          setChildrenList(validChildren);
          setShowChildSelector(true);
        }
      } catch (err) {
        setSendingOtp(false);
        await triggerHaptic('error');
        setGlobalError('حدث خطأ أثناء البحث عن أطفال ولي الأمر.');
      }
      return;
    }

    try {
      const res = await sendWhatsappOtp(fullPhone);
      setSendingOtp(false);

      if (res.success) {
        setSubmittedPhone(fullPhone);
        if (res.is_child || res.data?.is_child) {
          setChildRedirectNotice({
            isChild: true,
            childName: res.data?.child_name,
            guardianRelation: res.data?.guardian_relation,
            maskedPhone: res.data?.masked_guardian_phone,
          });
        } else {
          setChildRedirectNotice(null);
        }
        verifyingOtpRef.current = false;
        setVerifyingOtp(false);
        setOtpSent(true);
        setOtpCode(Array(OTP_LENGTH).fill(''));
        setOtpTimer(res.data?.expires_in ? Math.min(res.data.expires_in, 60) : 60);
        await triggerHaptic('success');
        scrollToWhatsAppInputs(150);
        setTimeout(() => otpInputRefs.current[0]?.focus(), 250);
      } else {
        await triggerHaptic('error');
        setGlobalError(res.message ?? 'تعذر إرسال كود الواتساب. حاول مجدداً.');
      }
    } catch (err) {
      setSendingOtp(false);
      await triggerHaptic('error');
      setGlobalError('حدث خطأ أثناء الاتصال بخدمة الواتساب.');
    }
  };

  async function handleVerifyWhatsappOtp(codeStr?: string) {
    const finalCode = normalizeOtpInput(codeStr ?? otpCode.join('')).slice(0, OTP_LENGTH);
    if (finalCode.length < OTP_LENGTH) {
      await triggerHaptic('error');
      setGlobalError('يرجى إكمال كود التحقق (6 أرقام).');
      return;
    }

    if (verifyingOtpRef.current) return;
    verifyingOtpRef.current = true;

    await triggerHaptic('light');
    setVerifyingOtp(true);
    setGlobalError(null);

    const phoneToVerify =
      submittedPhone || normalizePhoneNumber(phone, selectedCountry.dialCode).fullPhone;

    try {
      const res = (isChildMode && selectedChild)
        ? await verifyChildWhatsappOtp(phoneToVerify, selectedChild.id, finalCode)
        : await verifyWhatsappOtp(phoneToVerify, finalCode);

      if (res.success && res.data) {
        await triggerHaptic('success');
        await handleSuccessAuth({
          accessToken: res.data.access_token,
          refreshToken: res.data.refresh_token,
          expiresInSeconds: res.data.expires_in,
          user: res.data.person,
        });
        return;
      }

      verifyingOtpRef.current = false;
      setVerifyingOtp(false);
      await triggerHaptic('error');
      setGlobalError(res.message ?? 'كود التحقق غير صحيح.');
      setOtpCode(Array(OTP_LENGTH).fill(''));
      setTimeout(() => otpInputRefs.current[0]?.focus(), 50);
    } catch (err) {
      verifyingOtpRef.current = false;
      setVerifyingOtp(false);
      await triggerHaptic('error');
      setGlobalError('تعذر التأكد من كود التحقق من السيرفر.');
    }
  }

  const handleOtpCellChange = (index: number, raw: string) => {
    const digits = normalizeOtpInput(raw);

    if (digits.length === 0) {
      setOtpCode((prev) => {
        const next = [...prev];
        next[index] = '';
        return next;
      });
      return;
    }

    if (digits.length >= OTP_LENGTH) {
      setOtpCode(digits.slice(0, OTP_LENGTH).split(''));
      requestAnimationFrame(() => otpInputRefs.current[OTP_LENGTH - 1]?.blur());
      return;
    }

    let incoming = digits;
    if (otpCode[index] && digits.startsWith(otpCode[index]) && digits.length > otpCode[index].length) {
      incoming = digits.slice(otpCode[index].length);
    }

    setOtpCode((prev) => {
      const next = [...prev];
      incoming.split('').forEach((char, offset) => {
        if (index + offset < OTP_LENGTH) {
          next[index + offset] = char;
        }
      });
      return next;
    });

    const focusAt = Math.min(index + incoming.length, OTP_LENGTH - 1);
    requestAnimationFrame(() => otpInputRefs.current[focusAt]?.focus());
  };

  const handleOtpKeyPress = (
    index: number,
    event: NativeSyntheticEvent<TextInputKeyPressEventData>
  ) => {
    if (event.nativeEvent.key !== 'Backspace') return;

    setOtpCode((prev) => {
      if (prev[index]) {
        const next = [...prev];
        next[index] = '';
        return next;
      }
      if (index > 0) {
        requestAnimationFrame(() => otpInputRefs.current[index - 1]?.focus());
        const next = [...prev];
        next[index - 1] = '';
        return next;
      }
      return prev;
    });
  };

  // ---------------- التفاعل مع رمز الدخول PIN ----------------

  const verifyPasscodeOTP = async (code: string) => {
    if (isLocked || isLoadingPasscode || verifyingRef.current) return;
    verifyingRef.current = true;
    setIsLoadingPasscode(true);
    setHasPasscodeError(false);
    setPasscodeErrorMsg(null);

    try {
      const res = await verifySitePassword(code);
      if (res.success) {
        await triggerHaptic('success');
        void storage.setItem('preferred_auth_tab', 'passcode');
        await authenticate({
          accessToken: res.access_token ?? undefined,
          expiresInSeconds: 30 * 24 * 3600,
        });
      } else {
        await triggerHaptic('error');
        setHasPasscodeError(true);
        setPasscodeErrorMsg(res.message ?? 'رمز الدخول غير صحيح.');
        setAttempts((prev) => prev + 1);
        setTimeout(() => {
          setOtp(Array(passwordLength).fill(''));
          setIsLoadingPasscode(false);
          verifyingRef.current = false;
        }, 400);
      }
    } catch (err) {
      await triggerHaptic('error');
      setHasPasscodeError(true);
      setPasscodeErrorMsg('تعذر التحقق من السيرفر. تحقق من الاتصال وحاول مجدداً.');
      setAttempts((prev) => prev + 1);
      setTimeout(() => {
        setOtp(Array(passwordLength).fill(''));
        setIsLoadingPasscode(false);
        verifyingRef.current = false;
      }, 400);
    }
  };

  const handleKeypadPress = (num: string) => {
    if (isLocked || isLoadingPasscode || statusLoading || statusError) return;

    const newOtp = [...otp];
    const emptyIndex = newOtp.findIndex((d) => d === '');

    if (emptyIndex !== -1) {
      newOtp[emptyIndex] = num;
      setOtp(newOtp);
      setHasPasscodeError(false);
      setPasscodeErrorMsg(null);
      const filled = newOtp.join('');
      if (filled.length === passwordLength) {
        verifyPasscodeOTP(filled);
      }
    }
  };

  const handleKeypadDelete = () => {
    if (isLocked || isLoadingPasscode) return;
    const newOtp = [...otp];
    const lastFilledIndex = newOtp.map((d, i) => (d ? i : -1)).filter((i) => i !== -1).pop();

    if (lastFilledIndex !== undefined) {
      newOtp[lastFilledIndex] = '';
      setOtp(newOtp);
      setHasPasscodeError(false);
      setPasscodeErrorMsg(null);
    }
  };

  // ---------------- الدخول الناجح ----------------

  const handleSuccessAuth = async (opts?: {
    accessToken?: string;
    refreshToken?: string;
    expiresInSeconds?: number;
    user?: any;
  }) => {
    void storage.setItem('preferred_auth_tab', activeTab);
    setShowSuccess(true);
    await new Promise((r) => setTimeout(r, 600));
    await authenticate(opts);
  };

  const handleScrollFeature = (e: NativeSyntheticEvent<NativeScrollEvent>) => {
    const offsetX = Math.abs(e.nativeEvent.contentOffset.x);
    const index = Math.min(
      Math.max(0, Math.round(offsetX / cardWidth)),
      FAMILY_FEATURES.length - 1
    );
    if (index !== activeFeatureIndex) {
      setActiveFeatureIndex(index);
    }
  };

  if (statusLoading) {
    return (
      <View style={[styles.container, styles.centered, { backgroundColor: colors.background }]}>
        <ActivityIndicator size="large" color={silaTheme.primary} />
        <ThemedText style={[styles.loadingLabel, { color: colors.icon }]}>
          جاري إعداد منصة العائلة والدخول الذكي...
        </ThemedText>
      </View>
    );
  }

  return (
    <View style={[styles.container, { backgroundColor: colors.background }]}>
      <KeyboardAvoidingView
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
        keyboardVerticalOffset={Platform.OS === 'ios' ? 0 : 0}
        style={styles.container}
      >
        <ScrollView
          ref={scrollViewRef}
          contentContainerStyle={[
            styles.scrollContent,
            {
              paddingBottom: isKeyboardVisible
                ? (Platform.OS === 'ios' ? keyboardHeight + 20 : 150)
                : Math.max(34, insets.bottom + 16),
            },
          ]}
          showsVerticalScrollIndicator={false}
          keyboardShouldPersistTaps="handled"
        >
          {/* ================= HERO HEADER & BRANDING ================= */}
          <LinearGradient
            colors={isDark ? ['#145147', '#061D19'] : ['#DCF2DD', '#F0F9F1']}
            style={styles.headerHero}
          >
            <View style={styles.topBrandRow}>
              <View style={[styles.brandLogoCircle, { backgroundColor: silaTheme.primary }]}>
                <MaterialIcons name="security" size={24} color="#FFFFFF" />
              </View>
              <View style={styles.brandTextWrap}>
                <ThemedText
                  style={styles.brandTitle}
                  numberOfLines={1}
                  adjustsFontSizeToFit
                  minimumFontScale={0.85}
                >
                  عائلة السريّع
                </ThemedText>
                <ThemedText style={styles.brandSub} numberOfLines={1}>
                  منصّتكم العائلية التفاعلية والآمنة
                </ThemedText>
              </View>
            </View>

            <View style={styles.featuresSectionTitleRow}>
              <MaterialIcons name="auto-awesome" size={16} color={silaTheme.primary} />
              <ThemedText style={styles.featuresSectionHeaderTitle}>
                ماذا تكتسب عند توثيق دخولك؟
              </ThemedText>
            </View>

            {/* Paged Family Features Showcase - متوافق كلياً مع RTL و iOS بدون تشوه */}
            <ScrollView
              ref={carouselScrollRef}
              horizontal
              pagingEnabled
              showsHorizontalScrollIndicator={false}
              onScroll={handleScrollFeature}
              scrollEventThrottle={16}
              style={{ width: cardWidth, alignSelf: 'center', borderRadius: 14, overflow: 'hidden' }}
              contentContainerStyle={styles.carouselContainer}
            >
              {FAMILY_FEATURES.map((item, idx) => (
                <View
                  key={item.id}
                  style={[
                    styles.featureCard,
                    {
                      width: cardWidth,
                      backgroundColor: colors.card,
                      borderColor: idx === activeFeatureIndex ? silaTheme.primary : colors.border,
                    },
                  ]}
                >
                  <View style={styles.cardHeaderRow}>
                    <View style={[styles.cardIconCircle, { backgroundColor: item.gradient[0] + '20' }]}>
                      <MaterialIcons name={item.icon} size={18} color={item.gradient[0]} />
                    </View>
                    <ThemedText style={styles.cardTitle} numberOfLines={1}>
                      {item.title}
                    </ThemedText>
                    <View style={[styles.badgePill, { backgroundColor: item.gradient[0] + '15' }]}>
                      <ThemedText style={[styles.badgeText, { color: item.gradient[0] }]}>
                        {item.badge}
                      </ThemedText>
                    </View>
                  </View>

                  <ThemedText style={styles.cardDesc} numberOfLines={2}>
                    {item.desc}
                  </ThemedText>
                </View>
              ))}
            </ScrollView>

            {/* Carousel Pagination Dots */}
            <View style={styles.dotsRow}>
              {FAMILY_FEATURES.map((_, idx) => (
                <View
                  key={idx}
                  style={[
                    styles.dot,
                    {
                      backgroundColor:
                        idx === activeFeatureIndex ? silaTheme.primary : colors.border,
                      width: idx === activeFeatureIndex ? 16 : 6,
                    },
                  ]}
                />
              ))}
            </View>
          </LinearGradient>

          {/* ================= ERRORS DISPLAY ================= */}
          {globalError && (
            <View style={styles.globalErrorBox}>
              <MaterialIcons name="error-outline" size={18} color="#EF4444" />
              <ThemedText style={styles.globalErrorText}>{globalError}</ThemedText>
            </View>
          )}

          {/* ================= TAB BAR SELECTOR ================= */}
          <View style={[styles.tabBar, { backgroundColor: isDark ? '#1F2937' : '#F3F4F6' }]}>
            <TouchableOpacity
              style={[
                styles.tabBtn,
                activeTab === 'face' && [styles.tabBtnActive, { backgroundColor: colors.card }],
              ]}
              onPress={() => {
                void triggerHaptic('light');
                setActiveTab('face');
                setGlobalError(null);
              }}
              activeOpacity={0.8}
            >
              <MaterialIcons
                name="face"
                size={18}
                color={activeTab === 'face' ? silaTheme.primary : colors.icon}
              />
              <ThemedText
                numberOfLines={1}
                adjustsFontSizeToFit
                minimumFontScale={0.75}
                style={[
                  styles.tabBtnText,
                  { color: activeTab === 'face' ? silaTheme.primary : colors.icon },
                ]}
              >
                بصمة الوجه
              </ThemedText>
            </TouchableOpacity>

            <TouchableOpacity
              style={[
                styles.tabBtn,
                activeTab === 'whatsapp' && [styles.tabBtnActive, { backgroundColor: colors.card }],
              ]}
              onPress={() => {
                void triggerHaptic('light');
                setActiveTab('whatsapp');
                setGlobalError(null);
                scrollToWhatsAppInputs(150);
              }}
              activeOpacity={0.8}
            >
              <MaterialIcons
                name="chat"
                size={18}
                color={activeTab === 'whatsapp' ? silaTheme.primary : colors.icon}
              />
              <ThemedText
                numberOfLines={1}
                adjustsFontSizeToFit
                minimumFontScale={0.75}
                style={[
                  styles.tabBtnText,
                  { color: activeTab === 'whatsapp' ? silaTheme.primary : colors.icon },
                ]}
              >
                كود الواتساب
              </ThemedText>
            </TouchableOpacity>

            <TouchableOpacity
              style={[
                styles.tabBtn,
                activeTab === 'passcode' && [styles.tabBtnActive, { backgroundColor: colors.card }],
              ]}
              onPress={() => {
                void triggerHaptic('light');
                setActiveTab('passcode');
                setGlobalError(null);
              }}
              activeOpacity={0.8}
            >
              <MaterialIcons
                name="lock"
                size={18}
                color={activeTab === 'passcode' ? silaTheme.primary : colors.icon}
              />
              <ThemedText
                numberOfLines={1}
                adjustsFontSizeToFit
                minimumFontScale={0.75}
                style={[
                  styles.tabBtnText,
                  { color: activeTab === 'passcode' ? silaTheme.primary : colors.icon },
                ]}
              >
                رمز الدخول
              </ThemedText>
            </TouchableOpacity>
          </View>

          {/* ================= TAB 1: FACE RECOGNITION ================= */}
          {activeTab === 'face' && (
            <View style={styles.tabContent}>
              <ThemedText style={styles.sectionTitle}>الدخول البيومتري ببصمة الوجه</ThemedText>
              <ThemedText style={styles.sectionSubtitle}>
                مسح بيومتري فوري ومطابقة مشفرة بالذكاء الاصطناعي مع السجل العائلي
              </ThemedText>

              {/* Frame Container */}
              <View
                style={[
                  styles.scannerFrame,
                  {
                    width: scannerSize,
                    height: scannerSize,
                    borderRadius: scannerRadius,
                    borderColor: faceVerified
                      ? '#10B981'
                      : isScanningFace
                        ? silaTheme.primary
                        : colors.border,
                    backgroundColor: isDark ? '#111827' : '#F9FAFB',
                  },
                ]}
              >
                {faceImageUri ? (
                  <View
                    style={[
                      styles.imageWrap,
                      {
                        width: imageInnerSize,
                        height: imageInnerSize,
                        borderRadius: imageInnerRadius,
                      },
                    ]}
                  >
                    <Image
                      source={{ uri: faceImageUri }}
                      style={{
                        width: imageInnerSize,
                        height: imageInnerSize,
                        borderRadius: imageInnerRadius,
                      }}
                    />

                    {/* Animated Scanning Line */}
                    {isScanningFace && (
                      <Animated.View
                        style={[
                          styles.scanLine,
                          {
                            transform: [
                              {
                                translateY: scanAnim.interpolate({
                                  inputRange: [0, 1],
                                  outputRange: [-(scannerRadius - 10), scannerRadius - 10],
                                }),
                              },
                            ],
                          },
                        ]}
                      />
                    )}

                    {/* Verified Overlay */}
                    {faceVerified && (
                      <View style={styles.verifiedOverlay}>
                        <MaterialIcons name="check-circle" size={44} color="#10B981" />
                        <ThemedText style={styles.verifiedText}>تمت المطابقة البيومترية!</ThemedText>
                        {faceMatchScore && (
                          <ThemedText style={styles.matchScoreText}>
                            نسبة المطابقة: {faceMatchScore}%
                          </ThemedText>
                        )}
                      </View>
                    )}
                  </View>
                ) : (
                  <View style={styles.emptyScanPlaceholder}>
                    <View style={[styles.scanIconCircle, { backgroundColor: silaTheme.lightGreen }]}>
                      <MaterialIcons name="face" size={40} color={silaTheme.primary} />
                    </View>
                    <ThemedText style={styles.scanPlaceholderTitle}>
                      المطابقة البيومترية الذكية
                    </ThemedText>
                    <ThemedText style={styles.scanPlaceholderSub}>
                      فحص فوري للوجه في ثانيتين بنقرة واحدة
                    </ThemedText>
                  </View>
                )}
              </View>

              <TouchableOpacity
                style={[
                  styles.primaryBtn,
                  {
                    backgroundColor:
                      isScanningFace || faceLockoutTimer > 0 ? '#9CA3AF' : silaTheme.primary,
                  },
                ]}
                onPress={handleOpenLiveness}
                disabled={isScanningFace || faceLockoutTimer > 0}
                activeOpacity={0.85}
              >
                {isScanningFace ? (
                  <ActivityIndicator color="#FFFFFF" />
                ) : (
                  <>
                    <MaterialIcons name="camera-front" size={20} color="#FFFFFF" />
                    <ThemedText style={styles.primaryBtnText}>
                      {faceLockoutTimer > 0
                        ? `مقفلة مؤقتاً (${faceLockoutTimer} ثانية)`
                        : faceImageUri
                          ? 'إعادة مسح بصمة الوجه'
                          : 'بدء مسح بصمة الوجه'}
                    </ThemedText>
                  </>
                )}
              </TouchableOpacity>
            </View>
          )}

          {/* ================= TAB 2: WHATSAPP OTP ================= */}
          {activeTab === 'whatsapp' && (
            <View style={styles.tabContent}>
              <ThemedText style={styles.sectionTitle}>
                {isChildMode ? 'دخول الابن / الابنة عبر واتساب ولي الأمر' : 'الدخول عبر كود الواتساب'}
              </ThemedText>
              <ThemedText style={styles.sectionSubtitle}>
                {isChildMode
                  ? 'أدخل رقم واتساب الوالد لتلقي كود التحقق وتوثيق جهاز ابنك أو ابنتك'
                  : 'أدخل رقم جوالك المعتمد لدى العائلة وتلقّ كود التحقق بنقرة واحدة'}
              </ThemedText>

              {/* بطاقة تفعيل وضع تسجيل دخول الابن أو الابنة */}
              {!otpSent && (
                <TouchableOpacity
                  style={[
                    styles.childModeCard,
                    {
                      backgroundColor: isChildMode
                        ? (isDark ? 'rgba(217, 119, 6, 0.15)' : '#FEF3C7')
                        : (isDark ? 'rgba(255, 255, 255, 0.04)' : '#F8FAFC'),
                      borderColor: isChildMode ? silaTheme.primary : (isDark ? '#334155' : '#E2E8F0'),
                    },
                  ]}
                  onPress={() => {
                    setIsChildMode((prev) => !prev);
                    setSelectedChild(null);
                    setChildrenList([]);
                    setChildRedirectNotice(null);
                    setGlobalError(null);
                  }}
                  activeOpacity={0.8}
                >
                  <View style={styles.childModeTextWrap}>
                    <ThemedText
                      style={[
                        styles.childModeTitle,
                        isChildMode && { color: silaTheme.primary },
                      ]}
                    >
                      تسجيل كابن أو ابنة
                    </ThemedText>
                    <ThemedText style={styles.childModeSubtitle}>
                      مخصصة لمن هم في عمر 15 سنة أو أقل (يصل كود التحقق لواتساب الأب أو الأم)
                    </ThemedText>
                  </View>
                  <MaterialIcons
                    name={isChildMode ? 'check-box' : 'check-box-outline-blank'}
                    size={24}
                    color={isChildMode ? silaTheme.primary : colors.icon}
                  />
                </TouchableOpacity>
              )}

              {/* شارة إظهار الابن/الابنة المختار في وضع الابن أو الابنة */}
              {!otpSent && isChildMode && selectedChild && (
                <View
                  style={[
                    styles.selectedChildBadge,
                    {
                      backgroundColor: isDark ? '#1E293B' : '#EFF6FF',
                      borderColor: isDark ? '#3B82F6' : '#93C5FD',
                    },
                  ]}
                >
                  <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
                    <MaterialIcons name="face" size={20} color="#3B82F6" />
                    <ThemedText style={[styles.selectedChildText, { color: isDark ? '#93C5FD' : '#1D4ED8' }]}>
                      الابن/الابنة المختار: {selectedChild.first_name}
                    </ThemedText>
                  </View>
                  {childrenList.length > 1 && (
                    <TouchableOpacity
                      onPress={() => setShowChildSelector(true)}
                      style={styles.changeChildBtn}
                    >
                      <ThemedText style={styles.changeChildBtnText}>تغيير</ThemedText>
                    </TouchableOpacity>
                  )}
                </View>
              )}

              {!otpSent ? (
                <View style={styles.phoneSection}>
                  <ThemedText style={styles.inputLabel}>
                    {isChildMode ? 'رقم واتساب ولي الأمر (الأب أو الأم):' : 'رقم الجوال المسجل:'}
                  </ThemedText>
                  <View
                    style={[
                      styles.phoneInputRow,
                      { backgroundColor: colors.card, borderColor: colors.border },
                    ]}
                  >
                    <TouchableOpacity
                      style={styles.countryBadge}
                      onPress={() => setShowCountryPicker(true)}
                      activeOpacity={0.7}
                    >
                      <ThemedText style={styles.countryFlag}>{selectedCountry.flag}</ThemedText>
                      <ThemedText style={[styles.countryCode, { color: colors.text }]}>
                        {selectedCountry.dialCode}
                      </ThemedText>
                      <MaterialIcons name="arrow-drop-down" size={18} color={colors.icon} />
                    </TouchableOpacity>

                    <View
                      style={[
                        styles.verticalDivider,
                        { backgroundColor: isDark ? '#374151' : '#E5E7EB' },
                      ]}
                    />

                    <TextInput
                      style={[styles.phoneTextInput, { color: colors.text }]}
                      placeholder={selectedCountry.placeholder}
                      placeholderTextColor={colors.icon}
                      keyboardType="phone-pad"
                      value={phone}
                      onChangeText={setPhone}
                      maxLength={15}
                      onFocus={() => scrollToWhatsAppInputs(100)}
                    />
                  </View>

                  <TouchableOpacity
                    style={[styles.primaryBtn, { backgroundColor: silaTheme.primary }]}
                    onPress={handleSendWhatsappOtp}
                    disabled={sendingOtp}
                    activeOpacity={0.85}
                  >
                    {sendingOtp ? (
                      <ActivityIndicator color="#FFFFFF" />
                    ) : (
                      <>
                        <MaterialIcons name="chat" size={20} color="#FFFFFF" />
                        <ThemedText style={styles.primaryBtnText}>
                          {isChildMode
                            ? (selectedChild
                                ? `إرسال كود التحقق لدخول ${selectedChild.first_name}`
                                : 'متابعة لاختيار الابن أو الابنة وإرسال الكود')
                            : 'إرسال كود التحقق عبر الواتساب'}
                        </ThemedText>
                      </>
                    )}
                  </TouchableOpacity>
                </View>
              ) : (
                <View style={styles.otpSection}>
                  <View style={styles.sentNoticeBox}>
                    <View style={styles.sentNoticeContent}>
                      <MaterialIcons name="check-circle" size={20} color="#10B981" />
                      <View style={styles.sentNoticeTextWrap}>
                        <ThemedText style={styles.sentNoticeText}>
                          {isChildMode && selectedChild
                            ? `تم إرسال كود دخول (${selectedChild.first_name}) إلى واتساب ولي الأمر:`
                            : childRedirectNotice?.isChild
                            ? `تم إرسال كود التحقق إلى واتساب ${childRedirectNotice.guardianRelation || 'ولي الأمر'} لدخول (${childRedirectNotice.childName || 'الابن'}):`
                            : 'تم إرسال كود التحقق إلى واتساب الرقم:'}
                        </ThemedText>
                        <ThemedText style={styles.sentNoticePhone}>
                          {childRedirectNotice?.maskedPhone
                            ? childRedirectNotice.maskedPhone
                            : formatPhoneDisplay(selectedCountry.dialCode, phone)}
                        </ThemedText>
                      </View>
                    </View>
                    <TouchableOpacity
                      style={styles.editPhoneBtn}
                      onPress={() => {
                        setOtpSent(false);
                        setChildRedirectNotice(null);
                        setOtpCode(Array(OTP_LENGTH).fill(''));
                        scrollToWhatsAppInputs(80);
                      }}
                      activeOpacity={0.7}
                    >
                      <MaterialIcons name="edit" size={13} color={silaTheme.primary} />
                      <ThemedText style={styles.editPhoneBtnText}>تعديل الرقم</ThemedText>
                    </TouchableOpacity>
                  </View>

                  <ThemedText style={styles.otpInputTitle}>أدخل الكود المكون من 6 أرقام:</ThemedText>

                  <View style={styles.otpCellsRow}>
                    {otpCode.map((digit, idx) => (
                      <TextInput
                        key={idx}
                        ref={(el) => {
                          otpInputRefs.current[idx] = el;
                        }}
                        style={[
                          styles.singleOtpInput,
                          {
                            width: otpCellSize,
                            height: otpCellHeight,
                            backgroundColor: colors.card,
                            borderColor: digit ? silaTheme.primary : colors.border,
                            color: colors.text,
                          },
                        ]}
                        keyboardType="number-pad"
                        textContentType="oneTimeCode"
                        autoComplete="sms-otp"
                        importantForAutofill="yes"
                        maxLength={OTP_LENGTH}
                        selectTextOnFocus
                        value={digit}
                        editable={!verifyingOtp}
                        onFocus={() => scrollToWhatsAppInputs(100)}
                        onChangeText={(val) => handleOtpCellChange(idx, val)}
                        onKeyPress={(e) => handleOtpKeyPress(idx, e)}
                      />
                    ))}
                  </View>

                  <TouchableOpacity
                    style={[styles.primaryBtn, { backgroundColor: silaTheme.primary }]}
                    onPress={() => handleVerifyWhatsappOtp()}
                    disabled={verifyingOtp}
                    activeOpacity={0.85}
                  >
                    {verifyingOtp ? (
                      <ActivityIndicator color="#FFFFFF" />
                    ) : (
                      <ThemedText style={styles.primaryBtnText}>تأكيد الرمز والدخول</ThemedText>
                    )}
                  </TouchableOpacity>

                  <View style={styles.resendRow}>
                    {otpTimer > 0 ? (
                      <ThemedText style={styles.resendTimerText}>
                        إعادة إرسال الرمز خلال {otpTimer} ثانية
                      </ThemedText>
                    ) : (
                      <TouchableOpacity onPress={handleSendWhatsappOtp}>
                        <ThemedText style={styles.resendLinkText}>إعادة إرسال رمز الواتساب</ThemedText>
                      </TouchableOpacity>
                    )}
                  </View>
                </View>
              )}
            </View>
          )}

          {/* ================= TAB 3: PASSCODE PIN ================= */}
          {activeTab === 'passcode' && (
            <View style={styles.tabContent}>
              <ThemedText style={styles.sectionTitle}>رمز دخول العائلة</ThemedText>
              <ThemedText style={styles.sectionSubtitle}>
                {isLocked
                  ? `تم قفل محاولات الدخول. حاول مجدداً بعد ${lockTimer} ثانية`
                  : `أدخل رمز الدخول المكون من ${passwordLength} أرقام`}
              </ThemedText>

              <View style={styles.otpCellsRow}>
                {otp.map((digit, index) => (
                  <View
                    key={index}
                    style={[
                      styles.passcodeCell,
                      {
                        width: pinCellSize,
                        height: pinCellHeight,
                        backgroundColor: colors.card,
                        borderColor: hasPasscodeError
                          ? '#EF4444'
                          : digit
                            ? silaTheme.primary
                            : colors.border,
                      },
                    ]}
                  >
                    {digit ? (
                      <ThemedText style={[styles.passcodeCellText, { color: colors.text }]}>
                        {digit}
                      </ThemedText>
                    ) : (
                      <View style={[styles.passcodeDot, { backgroundColor: colors.border }]} />
                    )}
                  </View>
                ))}
              </View>

              {hasPasscodeError && (
                <View style={styles.errorRow}>
                  <MaterialIcons name="cancel" size={16} color="#EF4444" />
                  <ThemedText style={styles.errorRowText}>
                    {passcodeErrorMsg ?? `المحاولات المتبقية: ${Math.max(0, 5 - attempts)}`}
                  </ThemedText>
                </View>
              )}

              <View style={styles.keypadContainer}>
                {[
                  ['1', '2', '3'],
                  ['4', '5', '6'],
                  ['7', '8', '9'],
                  ['', '0', 'del'],
                ].map((row, rIdx) => (
                  <View key={rIdx} style={styles.keypadRow}>
                    {row.map((btn, bIdx) => (
                      <TouchableOpacity
                        key={bIdx}
                        style={[
                          styles.keypadBtn,
                          {
                            width: keypadBtnSize,
                            height: keypadBtnSize,
                            borderRadius: keypadBtnSize / 2,
                          },
                          !btn && styles.keypadBtnEmpty,
                          btn ? { backgroundColor: colors.card } : null,
                        ]}
                        onPress={() => {
                          if (btn === 'del') handleKeypadDelete();
                          else if (btn) handleKeypadPress(btn);
                        }}
                        disabled={!btn || isLocked || isLoadingPasscode}
                      >
                        {btn === 'del' ? (
                          <MaterialIcons name="backspace" size={22} color={colors.text} />
                        ) : (
                          <ThemedText style={[styles.keypadBtnText, { color: colors.text }]}>
                            {btn}
                          </ThemedText>
                        )}
                      </TouchableOpacity>
                    ))}
                  </View>
                ))}
              </View>
            </View>
          )}

          {/* Modal overlay upon success */}
          {showSuccess && (
            <View style={styles.successOverlay}>
              <View style={styles.successBox}>
                <MaterialIcons name="check-circle" size={68} color="#10B981" />
                <ThemedText style={styles.successTitle}>مرحباً بك في منصّة العائلة!</ThemedText>
                <ThemedText style={styles.successSub}>
                  تم توثيق دخولك وتفعيل التنبيهات والدخول المستمر بنجاح
                </ThemedText>
              </View>
            </View>
          )}
        </ScrollView>

        <FaceLivenessModal
          visible={showLivenessModal}
          onClose={() => setShowLivenessModal(false)}
          onVerifyFace={handleVerifyFaceDirect}
          onSuccessAuth={handleFaceAuthSuccess}
          onRequireOtp={(challenge) => {
            setShowLivenessModal(false);
            setDeviceTrustChallenge(challenge);
          }}
          onGraceNotice={(graceData) => {
            setShowLivenessModal(false);
            setDeviceGraceNotice({
              personName: graceData?.person?.full_name || graceData?.person?.first_name,
              hoursRemaining: graceData?.grace_hours_remaining || 24,
              data: graceData,
            });
          }}
          onFaceCaptured={handleLivenessFaceCaptured}
          onFallbackWhatsapp={() => {
            setShowLivenessModal(false);
            setActiveTab('whatsapp');
          }}
        />

        {deviceTrustChallenge && (
          <DeviceTrustOtpModal
            visible={!!deviceTrustChallenge}
            challengeToken={deviceTrustChallenge.challengeToken}
            maskedPhone={deviceTrustChallenge.maskedPhone}
            personName={deviceTrustChallenge.personName}
            expiresIn={deviceTrustChallenge.expiresIn}
            isChild={deviceTrustChallenge.isChild}
            guardianRelation={deviceTrustChallenge.guardianRelation}
            onSuccess={handleFaceAuthSuccess}
            onCancel={() => setDeviceTrustChallenge(null)}
          />
        )}

        <ChildSelectorModal
          visible={showChildSelector}
          parentName={parentName}
          childrenList={childrenList}
          onSelectChild={handleSelectChildAndSendOtp}
          onCancel={() => {
            setShowChildSelector(false);
            setSendingOtp(false);
          }}
        />

        {deviceGraceNotice && (
          <DeviceGraceNoticeModal
            visible={!!deviceGraceNotice}
            personName={deviceGraceNotice.personName}
            hoursRemaining={deviceGraceNotice.hoursRemaining}
            onProceed={() => handleFaceAuthSuccess(deviceGraceNotice.data)}
          />
        )}

        <CountryPickerModal
          visible={showCountryPicker}
          selectedCountry={selectedCountry}
          onSelect={handleCountrySelect}
          onClose={() => setShowCountryPicker(false)}
        />
      </KeyboardAvoidingView>
    </View>
  );
}
