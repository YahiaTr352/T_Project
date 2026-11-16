import { Dimensions, PixelRatio } from 'react-native';

/**
 * القياس المرجعي الأساسي لتصميم الشاشات الحديثة (iPhone 13 / 14 القياسي)
 */
const BASE_WIDTH = 390;

/**
 * استخراج أبعاد الشاشة بأمان تام:
 * نعتمد دائماً البعد الأصغر كعرض (screenWidth) حتى لا يتضخم الخط إذا تغير اتجاه الجهاز إلى الأفقي.
 */
export function getDeviceDimensions(): { screenWidth: number; screenHeight: number } {
  const { width, height } = Dimensions.get('window');
  const screenWidth = Math.min(width, height);
  const screenHeight = Math.max(width, height);
  return { screenWidth, screenHeight };
}

/**
 * خوارزمية التحجيم المعتدل للنصوص (Moderate Responsive Font Scaling)
 * 
 * المعادلة:
 * scaled = size + ((screenWidth / BASE_WIDTH) - 1) * size * factor
 * 
 * @param size المقاس الأساسي للخط بالنقاط (Points)
 * @param factor معامل المرونة (الافتراضي 0.45): يعطي انخفاضاً لطيفاً على الشاشات الأصغر وزيادة متزنة على الشاشات الأكبر
 */
export function scaleFont(size: number, factor: number = 0.45): number {
  if (!size || typeof size !== 'number' || isNaN(size)) return size;
  
  const { screenWidth } = getDeviceDimensions();
  const scale = screenWidth / BASE_WIDTH;
  const scaled = size + (scale - 1) * size * factor;

  // حدود أمان صارمة (Guard Clamping):
  // - لا يقل الخط عن 85% من حجمه الأصلي منعاً لضعف القراءة
  // - لا يزيد عن 125% من حجمه الأصلي منعاً للتضخم على الأجهزة اللوحية
  const minAllowed = size * 0.85;
  const maxAllowed = size * 1.25;
  const clamped = Math.max(minAllowed, Math.min(maxAllowed, scaled));

  return Math.round(PixelRatio.roundToNearestPixel(clamped));
}

/**
 * حساب ارتفاع السطر (Line Height) بدقة متوافقة مع خط "تجوال" (Tajawal)
 * الخط العربي يحتاج مساحة رأسية (~1.45x - 1.5x) لمنع قص نقاط الحروف (ث، ش، أ)
 */
export function scaleLineHeight(fontSize: number, explicitLineHeight?: number): number {
  if (explicitLineHeight && typeof explicitLineHeight === 'number' && !isNaN(explicitLineHeight)) {
    return scaleFont(explicitLineHeight);
  }
  return Math.round(fontSize * 1.48);
}

/**
 * اختصارات إضافية مفيدة للمطورين (Aliases)
 */
export const rf = scaleFont;
export const moderateScale = scaleFont;

/**
 * السقف المسموح به لتكبير الخط من إعدادات النظام (iOS Dynamic Type / Android Font Scale)
 * يسمح بزيادة تصل إلى 15% لمساعدة ضعاف البصر مع الحفاظ الكامل على سلامة واجهات وكروت التطبيق.
 */
export const MAX_FONT_SIZE_MULTIPLIER = 1.15;
