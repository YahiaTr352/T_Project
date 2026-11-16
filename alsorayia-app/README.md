# Alsorayia Family App (`عائلة السريّع`)

Production React Native mobile application for the Alsorayia family platform — featuring the interactive family tree (`سِلة`), social feed, AI assistant, family quizzes & voting competitions, and a full native step-tracking & walking challenge engine.

> **Architecture Deep Dive:** See [`ARCHITECTURE.md`](./ARCHITECTURE.md) for the data flow, dual-token auth lifecycle, storage boundaries, KSA midnight-reset invariants, and modularized domain structure.

---

## Tech Stack

| Layer | Technology |
| :--- | :--- |
| **Framework** | Expo SDK 57 (`~57.0.31`) / React Native `0.86.3` / React `19.2.3` |
| **Language** | TypeScript `~6.0.3` (`strict: true`) |
| **Navigation** | Expo Router (typed routes enabled) |
| **Runtime** | React Native New Architecture (`newArchEnabled: true`), React Compiler (`reactCompiler: true`), RTL enforced (`supportsRTL: true`) |
| **Native Health & Sensors** | `@kingstinct/react-native-healthkit`, `react-native-health-connect`, `react-native-nitro-modules`, `expo-sensors` (`Pedometer`) |
| **Maps & UI** | `react-native-maps`, `react-native-reanimated`, `expo-image`, `Tajawal` typography |
| **Storage** | `expo-secure-store` (credentials/tokens only) + [`lib/storage/appStorage.ts`](./lib/storage/appStorage.ts) (`expo-file-system/legacy` + in-memory cache + transparent migration) |
| **Testing** | Jest (`jest-expo`) |

---

## Why Expo Go Is Not Supported

Do **not** try to run this project in **Expo Go**. It compiles custom native modules that are absent from the generic Expo Go client:

- `@kingstinct/react-native-healthkit` + `react-native-nitro-modules` (iOS HealthKit)
- `react-native-health-connect` (Android Health Connect)
- `react-native-maps` (Google Maps / Apple Maps native SDKs)

You **must** use an **Expo Development Build** (`development` EAS profile or local native prebuild).

---

## Getting Started

### 1. Install Dependencies

```bash
npm install
```

### 2. Configure Environment Variables

Copy `.env.example` to `.env.local` (never commit `.env.local` or secret JSON keys):

```bash
cp .env.example .env.local
```

Variables in [`.env.example`](./.env.example):

- `EXPO_PUBLIC_API_BASE_URL` — Backend API base URL without trailing slash (defaults to `https://alsorayia.com/api` if omitted so CI/EAS builds remain self-contained).
- `EXPO_PUBLIC_GOOGLE_MAPS_API_KEY` — Required Google Maps SDK key consumed by `app.config.js` for iOS/Android native builds (no hardcoded fallback is stored in source control; set it in `.env.local` locally and in EAS Environment Variables for cloud builds).

> **Security Hygiene:** Firebase Admin SDK service accounts (`*firebase-adminsdk*.json`) and keystore credentials are strictly excluded via [`.gitignore`](./.gitignore) and must remain outside the repository.

### 3. Run Locally (Development Build)

```bash
# Start Metro for a Development Client
npx expo start --dev-client

# Or build & install a local native development binary
npx expo run:android
npx expo run:ios
```

---

## Verification & Quality Gates

Always run these checks before pushing or opening a PR:

```bash
# 1. Strict TypeScript check
npm run typecheck

# 2. Automated Jest suite (appStorage + walking sync engine + unit tests)
npx jest --watchAll=false

# 3. ESLint check
npm run lint
# Or targeted lint on edited files:
npx eslint path/to/file.ts
```

---

## EAS Build Profiles

Configured in [`eas.json`](./eas.json):

- `development` — Internal dev-client binary for native HealthKit / Health Connect testing.
- `preview` — Internal QA distribution build.
- `production` — Store release binary.

```bash
npx eas build --profile development --platform all
npx eas build --profile preview --platform android
npx eas build --profile production --platform all
```
