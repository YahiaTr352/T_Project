// https://docs.expo.dev/guides/using-eslint/
const { defineConfig } = require('eslint/config');
const expoConfig = require('eslint-config-expo/flat');

module.exports = defineConfig([
  expoConfig,
  {
    ignores: ['.expo/*', 'dist/*', 'dist-test/*'],
  },
  {
    rules: {
      // قاعدة React Compiler الجديدة ترفض الاستدعاء المتزامن لـ setState داخل useEffect.
      // نمط جلب البيانات عند التركيب (fetch-on-mount) شائع في كل شاشات التطبيق،
      // وتحويلها يتطلب ترحيلًا شاملًا إلى مكتبة استعلامات (React Query) — نحتفظ بها كتحذير
      // حتى رحلة الترحيل تلك.
      'react-hooks/set-state-in-effect': 'warn',
    },
  },
]);
