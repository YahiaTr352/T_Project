const packageJson = require('./package.json');
const GOOGLE_MAPS_API_KEY = process.env.EXPO_PUBLIC_GOOGLE_MAPS_API_KEY;

if (!GOOGLE_MAPS_API_KEY && process.env.NODE_ENV !== 'test') {
  console.warn(
    '[app.config.js] EXPO_PUBLIC_GOOGLE_MAPS_API_KEY is not set. Configure it in .env.local for local builds and in EAS environment variables for cloud builds.'
  );
}

module.exports = {
  expo: {
    name: "عائلة السريّع",
    slug: "alsorayia-app",
    version: packageJson.version,
    orientation: "portrait",
    icon: "./assets/images/icon.png",
    scheme: "alsorayiaapp",
    userInterfaceStyle: "automatic",
    newArchEnabled: true,
    ios: {
      supportsTablet: true,
      infoPlist: {
        CFBundleDevelopmentRegion: "ar",
        CFBundleAllowMixedLocalizations: true,
        CFBundleDisplayName: "عائلة السريّع",
        CFBundleName: "عائلة السريّع",
        ITSAppUsesNonExemptEncryption: false,
        NSMotionUsageDescription: "يستخدم التطبيق بيانات الحركة لتسجيل خطواتك في برنامج المشي.",
        NSMicrophoneUsageDescription: "يستخدم التطبيق الميكروفون لتسجيل الرسائل الصوتية والتحدث مع المساعد الذكي."
      },
      bundleIdentifier: "com.alsorayia.app",
      config: {
        googleMapsApiKey: GOOGLE_MAPS_API_KEY
      }
    },
    android: {
      permissions: [
        "android.permission.ACTIVITY_RECOGNITION",
        "android.permission.health.READ_STEPS",
        "androidx.health.permission.Steps.READ",
        "android.permission.health.READ_HEALTH_DATA_IN_BACKGROUND",
        "android.permission.POST_NOTIFICATIONS",
        "android.permission.RECORD_AUDIO"
      ],
      blockedPermissions: [
        "android.permission.READ_MEDIA_IMAGES",
        "android.permission.READ_MEDIA_VIDEO",
        "android.permission.READ_MEDIA_AUDIO",
        "android.permission.READ_MEDIA_VISUAL_USER_SELECTED",
        "android.permission.ACCESS_MEDIA_LOCATION"
      ],
      googleServicesFile: "./google-services.json",
      adaptiveIcon: {
        backgroundColor: "#E6F4FE",
        foregroundImage: "./assets/images/android-icon-foreground.png",
        backgroundImage: "./assets/images/android-icon-background.png",
        monochromeImage: "./assets/images/android-icon-monochrome.png"
      },
      edgeToEdgeEnabled: true,
      predictiveBackGestureEnabled: false,
      softwareKeyboardLayoutMode: "resize",
      package: "com.alsorayia.app",
      config: {
        googleMaps: {
          apiKey: GOOGLE_MAPS_API_KEY
        }
      }
    },
    web: {
      output: "static",
      favicon: "./assets/images/favicon.png",
      dir: "rtl"
    },
    plugins: [
      "expo-router",
      "expo-asset",
      [
        "@kingstinct/react-native-healthkit",
        {
          NSHealthShareUsageDescription:
            "يستخدم التطبيق بيانات الخطوات من تطبيق صحتي لمزامنة نشاطك اليومي في برنامج المشي والتحديات.",
          NSHealthUpdateUsageDescription:
            "لا يضيف التطبيق بيانات صحية إلى صحتي؛ يُستخدم هذا الوصف فقط عند الحاجة لتهيئة صلاحية HealthKit."
        }
      ],
      "react-native-health-connect",
      [
        "expo-image-picker",
        {
          "photosPermission": "يسمح لك التطبيق باختيار الصور من المعرض للبحث عن أفراد العائلة ومطابقتهم في شجرة العائلة بالوجه.",
          "cameraPermission": "يسمح لك التطبيق بالتقاط صور بالكاميرا للبحث عن أفراد العائلة ومطابقتهم في شجرة العائلة بالوجه."
        }
      ],
      [
        "expo-camera",
        {
          "cameraPermission": "يسمح لك التطبيق بالوصول إلى الكاميرا الأمامية للتحقق البيومتري المباشر وتسجيل الدخول الآمن."
        }
      ],
      [
        "expo-splash-screen",
        {
          image: "./assets/images/splash-icon.png",
          imageWidth: 200,
          resizeMode: "contain",
          backgroundColor: "#ffffff",
          dark: {
            backgroundColor: "#000000"
          }
        }
      ],
      [
        "expo-secure-store",
        {
          configureAndroidBackup: true,
          faceIDPermission: "Allow $(PRODUCT_NAME) to access your Face ID biometric data."
        }
      ],
      [
        "expo-media-library",
        {
          "photosPermission": "يسمح لك التطبيق باختيار وحفظ الصور في المعرض.",
          "savePhotosPermission": "يسمح لك التطبيق بحفظ الصور في المعرض عند الضغط على زر التنزيل.",
          "isAccessMediaLocationEnabled": false,
          "granularPermissions": []
        }
      ],
      "expo-font",
      "expo-image",
      "expo-notifications",
      "expo-sharing",
      "expo-status-bar",
      "expo-web-browser",
      [
        "react-native-maps",
        {
          iosGoogleMapsApiKey: GOOGLE_MAPS_API_KEY,
          androidGoogleMapsApiKey: GOOGLE_MAPS_API_KEY
        }
      ],
      [
        "expo-build-properties",
        {
          android: {
            minSdkVersion: 26,
            enableProguardInReleaseBuilds: true,
            enableShrinkResourcesInReleaseBuilds: true,
            extraProguardRules: [
              "-keep class com.facebook.react.** { *; }",
              "-keep class com.facebook.hermes.** { *; }",
              "-keep class com.swmansion.reanimated.** { *; }",
              "-keep class com.swmansion.rnscreens.** { *; }",
              "-keep class com.google.android.gms.maps.** { *; }",
              "-keep class com.airbnb.android.react.maps.** { *; }",
              "-keep class fr.greweb.reactnativeviewshot.** { *; }",
              "-keep class com.horcrux.svg.** { *; }"
            ].join("\n")
          }
        }
      ]
    ],
    experiments: {
      typedRoutes: true,
      reactCompiler: true
    },
    extra: {
      supportsRTL: true,
      googleMapsApiKey: GOOGLE_MAPS_API_KEY,
      router: {},
      eas: {
        projectId: "9d634994-42a4-4d9c-9ccb-75801f565f99"
      }
    },
    runtimeVersion: {
      policy: "appVersion"
    },
    updates: {
      url: "https://u.expo.dev/9d634994-42a4-4d9c-9ccb-75801f565f99"
    }
  }
};
