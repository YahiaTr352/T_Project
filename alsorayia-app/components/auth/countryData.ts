export interface CountryCodeItem {
  code: string; // ISO 3166-1 alpha-2
  nameAr: string;
  nameEn: string;
  dialCode: string;
  flag: string;
  placeholder: string;
  isFrequent?: boolean;
}

/** تحويل الأرقام العربية والفارسية إلى أرقام غربية 0-9 */
export function toWesternDigits(str: string): string {
  if (!str) return '';
  return str
    .replace(/[\u0660-\u0669]/g, (d) => String(d.charCodeAt(0) - 0x0660))
    .replace(/[\u06F0-\u06F9]/g, (d) => String(d.charCodeAt(0) - 0x06f0));
}

/** قائمة الدول العربية والدول الأكثر استخداماً */
export const COUNTRIES: CountryCodeItem[] = [
  // الدول الخليجية والعربية الأكثر طلباً واستخداماً
  {
    code: 'SA',
    nameAr: 'المملكة العربية السعودية',
    nameEn: 'Saudi Arabia',
    dialCode: '+966',
    flag: '🇸🇦',
    placeholder: '5X XXX XXXX',
    isFrequent: true,
  },
  {
    code: 'EG',
    nameAr: 'مصر',
    nameEn: 'Egypt',
    dialCode: '+20',
    flag: '🇪🇬',
    placeholder: '01X XXXX XXXX',
    isFrequent: true,
  },
  {
    code: 'AE',
    nameAr: 'الإمارات العربية المتحدة',
    nameEn: 'United Arab Emirates',
    dialCode: '+971',
    flag: '🇦🇪',
    placeholder: '5X XXX XXXX',
    isFrequent: true,
  },
  {
    code: 'KW',
    nameAr: 'الكويت',
    nameEn: 'Kuwait',
    dialCode: '+965',
    flag: '🇰🇼',
    placeholder: '9X XXX XXX',
    isFrequent: true,
  },
  {
    code: 'QA',
    nameAr: 'قطر',
    nameEn: 'Qatar',
    dialCode: '+974',
    flag: '🇶🇦',
    placeholder: '3X XXX XXX',
    isFrequent: true,
  },
  {
    code: 'BH',
    nameAr: 'مملكة البحرين',
    nameEn: 'Bahrain',
    dialCode: '+973',
    flag: '🇧🇭',
    placeholder: '3X XXX XXX',
    isFrequent: true,
  },
  {
    code: 'OM',
    nameAr: 'سلطنة عُمان',
    nameEn: 'Oman',
    dialCode: '+968',
    flag: '🇴🇲',
    placeholder: '9X XXX XXX',
    isFrequent: true,
  },
  {
    code: 'JO',
    nameAr: 'المملكة الأردنية الهاشمية',
    nameEn: 'Jordan',
    dialCode: '+962',
    flag: '🇯🇴',
    placeholder: '7X XXX XXXX',
    isFrequent: true,
  },

  // باقي الدول العربية
  {
    code: 'IQ',
    nameAr: 'العراق',
    nameEn: 'Iraq',
    dialCode: '+964',
    flag: '🇮🇶',
    placeholder: '7X XXXX XXXX',
  },
  {
    code: 'YE',
    nameAr: 'اليمن',
    nameEn: 'Yemen',
    dialCode: '+967',
    flag: '🇾🇪',
    placeholder: '7X XXX XXXX',
  },
  {
    code: 'SY',
    nameAr: 'سوريا',
    nameEn: 'Syria',
    dialCode: '+963',
    flag: '🇸🇾',
    placeholder: '9X XXX XXXX',
  },
  {
    code: 'LB',
    nameAr: 'لبنان',
    nameEn: 'Lebanon',
    dialCode: '+961',
    flag: '🇱🇧',
    placeholder: '7X XXX XXX',
  },
  {
    code: 'PS',
    nameAr: 'فلسطين',
    nameEn: 'Palestine',
    dialCode: '+970',
    flag: '🇵🇸',
    placeholder: '59 XXX XXXX',
  },
  {
    code: 'SD',
    nameAr: 'السودان',
    nameEn: 'Sudan',
    dialCode: '+249',
    flag: '🇸🇩',
    placeholder: '9X XXX XXXX',
  },
  {
    code: 'LY',
    nameAr: 'ليبيا',
    nameEn: 'Libya',
    dialCode: '+218',
    flag: '🇱🇾',
    placeholder: '9X XXX XXXX',
  },
  {
    code: 'TN',
    nameAr: 'تونس',
    nameEn: 'Tunisia',
    dialCode: '+216',
    flag: '🇹🇳',
    placeholder: '9X XXX XXX',
  },
  {
    code: 'DZ',
    nameAr: 'الجزائر',
    nameEn: 'Algeria',
    dialCode: '+213',
    flag: '🇩🇿',
    placeholder: '6X XXX XXXX',
  },
  {
    code: 'MA',
    nameAr: 'المغرب',
    nameEn: 'Morocco',
    dialCode: '+212',
    flag: '🇲🇦',
    placeholder: '6X XXX XXXX',
  },

  // دول عالمية شائعة لمغتربي العائلة والطلاب
  {
    code: 'TR',
    nameAr: 'تركيا',
    nameEn: 'Turkey',
    dialCode: '+90',
    flag: '🇹🇷',
    placeholder: '5XX XXX XXXX',
  },
  {
    code: 'GB',
    nameAr: 'المملكة المتحدة',
    nameEn: 'United Kingdom',
    dialCode: '+44',
    flag: '🇬🇧',
    placeholder: '7XXX XXXXXX',
  },
  {
    code: 'US',
    nameAr: 'الولايات المتحدة الأمريكية',
    nameEn: 'United States',
    dialCode: '+1',
    flag: '🇺🇸',
    placeholder: 'XXX XXX XXXX',
  },
  {
    code: 'CA',
    nameAr: 'كندا',
    nameEn: 'Canada',
    dialCode: '+1',
    flag: '🇨🇦',
    placeholder: 'XXX XXX XXXX',
  },
  {
    code: 'DE',
    nameAr: 'ألمانيا',
    nameEn: 'Germany',
    dialCode: '+49',
    flag: '🇩🇪',
    placeholder: '15X XXXXXXXX',
  },
  {
    code: 'FR',
    nameAr: 'فرنسا',
    nameEn: 'France',
    dialCode: '+33',
    flag: '🇫🇷',
    placeholder: '6 XX XX XX XX',
  },
  {
    code: 'MY',
    nameAr: 'ماليزيا',
    nameEn: 'Malaysia',
    dialCode: '+60',
    flag: '🇲🇾',
    placeholder: '1X XXX XXXX',
  },
];

export const DEFAULT_COUNTRY: CountryCodeItem = COUNTRIES[0]; // السعودية 🇸🇦

/** الدول المفضلة في أعلى القائمة لاختيار سريع */
export const FREQUENT_COUNTRIES = COUNTRIES.filter((c) => c.isFrequent);

/**
 * معالجة وتوحيد رقم الجوال وإرجاع الرقم بالصيغة الدولية E.164
 */
export function normalizePhoneNumber(
  rawInput: string,
  dialCode: string
): {
  fullPhone: string;
  localDigits: string;
  isValid: boolean;
} {
  const western = toWesternDigits(rawInput).trim();
  // استخراج الأرقام فقط (مع تجاهل علامة + والمسافات والشرطات)
  let cleanDigits = western.replace(/\D/g, '');

  const numericDialCode = dialCode.replace(/\D/g, '');

  // إذا أدخل المستخدم الرقم كاملاً يبدأ بـ 00 أو كود الدولة
  if (cleanDigits.startsWith('00' + numericDialCode)) {
    cleanDigits = cleanDigits.slice(2 + numericDialCode.length);
  } else if (cleanDigits.startsWith(numericDialCode) && cleanDigits.length > numericDialCode.length + 5) {
    cleanDigits = cleanDigits.slice(numericDialCode.length);
  }

  // حذف الصفر الزائد في بداية الرقم المحلي (مثل 055... أو 010...)
  if (cleanDigits.startsWith('0')) {
    cleanDigits = cleanDigits.replace(/^0+/, '');
  }

  const fullPhone = cleanDigits.length > 0 ? `${dialCode}${cleanDigits}` : '';
  const isValid = cleanDigits.length >= 7 && cleanDigits.length <= 14;

  return {
    fullPhone,
    localDigits: cleanDigits,
    isValid,
  };
}

/**
 * تنسيق الرقم للعرض البصري الجذاب للمستخدم
 */
export function formatPhoneDisplay(dialCode: string, localDigits: string): string {
  const clean = toWesternDigits(localDigits).replace(/\D/g, '');
  if (!clean) return dialCode;

  // تنسيق مجزأ للأرقام
  if (clean.length <= 4) {
    return `${dialCode} ${clean}`;
  } else if (clean.length <= 7) {
    return `${dialCode} ${clean.slice(0, 3)} ${clean.slice(3)}`;
  } else {
    return `${dialCode} ${clean.slice(0, 3)} ${clean.slice(3, 6)} ${clean.slice(6)}`;
  }
}
