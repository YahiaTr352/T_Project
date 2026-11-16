# Architecture & Engineering Handover Guide

This document explains the structural boundaries, state invariants, and runtime pipelines of `alsorayia-app` so an incoming engineer can modify features safely without introducing auth race conditions, storage quota failures, or timezone step-count corruption.

---

## 1. Directory & Layer Map

```text
app/                        # Expo Router file-based screens & layouts
  _layout.tsx               # App bootstrap (global RTL, fonts, theme, OTA reload, Auth/Lock gates)
  (tabs)/                   # Primary bottom tabs (Home, Family Tree, Walking, AI, etc.)
  (app)/                    # Authenticated stack routes (Notifications, Search, Person Profile, Reports, Chat)
components/
  walking/
    GroupDetailsModal.tsx   # Walking challenge modal (consumes modular helpers below)
    group-details/          # Extracted pure scoring engine, styles, and action sheet
  quiz/
    QuizCompetitionWidget.tsx
    competition/            # Extracted countdown/validation helpers and StyleSheet
  lock-screen.tsx           # Biometric / site-access lock UI
contexts/
  auth-context.tsx          # Auth state machine, identity refresh, lock/logout lifecycle
hooks/
  use-walking.ts            # Unified foreground pedometer + health sync + leaderboard hook
  use-global-walking-sync.ts# App-start & AppState foreground silent step sync trigger
lib/
  api/
    alsorayiaClient.ts      # Axios HTTP layer, dual auth headers, single-flight token refresh, XHR upload
    walkingApi.ts           # Walking endpoints & KSA date-context DTOs
  storage/
    appStorage.ts           # File-backed unbounded KV store + in-memory cache + SecureStore auto-migration
  walking/
    ledger.ts               # Local daily/hourly ledger, KSA timezone date keys, monotonic & rollover math
    syncService.ts          # Background authoritative-health vs fallback-pedometer sync orchestrator
    health.ts               # Platform abstraction over Apple HealthKit (iOS) & Health Connect (Android)
    demoData.ts             # Synthetic 365-day guest ledger & demo leaderboard generator
    achievementMetrics.ts   # Pure calculators for bestDay, activeDaysCount, avgDaily30d, streak10k
  utils/
    logger.ts               # Production-safe logger (__DEV__ gated info/debug/warn + redacted error logs)
    patchGlobalText.ts      # Global Tajawal font mapping & RTL typography normalization
```

---

## 2. Boot Sequence & Runtime Invariants (`app/_layout.tsx`)

On cold start, [`app/_layout.tsx`](./app/_layout.tsx) executes an ordered initialization sequence before rendering navigation trees:

1. `patchGlobalText()` installs global default typography (`Tajawal-*`).
2. `I18nManager.forceRTL(true)` enforces RTL layout; if a non-dev binary boots in LTR on first launch, it triggers a reload to lock native RTL mirrors cleanly.
3. `hydrateThemeFromStorage()` restores user theme preference before screen mount.
4. Once fonts finish loading, it wraps the router tree inside `SafeAreaView`, `AlertProvider`, and `AuthProvider`, activating `usePushNotifications`, `useGlobalWalkingSync`, `useAppVersionGate`, maintenance/update modals, and the `LockScreen` access gate.

---

## 3. Networking & Dual-Header Auth Lifecycle (`lib/api/alsorayiaClient.ts`)

All API calls travel through [`lib/api/alsorayiaClient.ts`](./lib/api/alsorayiaClient.ts):

- **Base URL:** Configured via `EXPO_PUBLIC_API_BASE_URL` with a built-in fallback to `'https://alsorayia.com/api'` so pre-configured EAS builds never fail if env vars are absent.
- **Dual Headers:** Authenticated requests carry both `Authorization: Bearer <token>` and `X-Site-Access-Token` (family site access token).
- **Single-Flight Token Refresh (`refreshPromise`):** Concurrent `401` responses share a single active `executeTokenRefresh` promise instead of issuing overlapping refresh calls that would revoke each other's refresh tokens.
- **Session Displacement & Site Lock:** Responses signaling `SESSION_DISPLACED` or `TOKEN_REVOKED` clear session state cleanly, while `SITE_PASSWORD_REQUIRED` transitions the UI back to the `LockScreen` gate.
- **Observability:** Diagnostics flow through [`lib/utils/logger.ts`](./lib/utils/logger.ts), which strips/redacts `Bearer` and `X-Site-Access-Token` values and suppresses verbose debug/info/warn logs in production builds (`!__DEV__`).

---

## 4. Storage Boundary: `SecureStore` vs. `appStorage`

**Rule:** Never store unbounded JSON blobs, caches, or arrays in `expo-secure-store`.

iOS Keychain / Android Keystore impose a hard ~2048-byte limit per `SecureStore` entry. This codebase enforces a strict boundary:

| Storage Layer | Purpose | Backing Mechanism |
| :--- | :--- | :--- |
| `expo-secure-store` | Access tokens, refresh tokens, PIN / site-access secrets **only** | OS Keychain / Android Keystore |
| [`appStorage`](./lib/storage/appStorage.ts) | Walking ledger (`walking_ledger_v2`), AI chat history, read/deleted notification IDs, search history, quiz offline queues & cooldowns | `expo-file-system/legacy` (`app_storage/store_*.dat`) on native + in-memory `Map` cache + `localStorage` on web |

`appStorage.getItem(key)` automatically checks for any legacy value left in `SecureStore`, migrates it to file storage, and deletes the old `SecureStore` entry so existing users upgrade seamlessly without losing local state.

---

## 5. Walking & Step-Sync Engine Invariants

The step-tracking domain spans [`lib/walking/ledger.ts`](./lib/walking/ledger.ts), [`lib/walking/syncService.ts`](./lib/walking/syncService.ts), [`hooks/use-walking.ts`](./hooks/use-walking.ts), and `components/walking/group-details/`:

1. **Authoritative Health vs. Fallback Pedometer:**
   - Apple Health (`HealthKit`) and Android `Health Connect` reads are flagged `is_authoritative: true`.
   - If health permissions are explicitly **denied** or a read returns **null**, `syncService` intentionally refuses to fall back to raw `Pedometer` counts to prevent double/divergent step inflation.
   - `Pedometer` fallback activates only when health providers report `status: 'unavailable'`. On iOS, `Pedometer.getStepCountAsync` is treated as authoritative (`Platform.OS === 'ios'`).
2. **KSA Midnight Rollover (`Asia/Riyadh`):**
   - `ksaDateKey()` and `ksaStartOfDay()` anchor "today" to Saudi Arabia (`Asia/Riyadh`) rather than shifting server-vs-device timezones cleanly at midnight, preventing yesterday's trailing steps from leaking into today's leaderboard race.
   - On the first authoritative sync of a new day (`lastSyncedDateKey !== todayKey`), `setAuthoritativeDaySteps(..., { forceOverwrite: true })` overwrites any stale cached step number cleanly; within the same day, `Math.max` preserves monotonic non-decreasing counts against transient sensor glitches unless overridden by a server `reset_id`.
3. **Challenge Points & Ranking (`groupLeaderboardEngine.ts`):**
   - `GroupDetailsModal` delegates member sorting, step leaderboard merging, intra-day tie-break scoring (`expected_points = max(1, memberCount - rank + 1)`), and points projection to pure functions inside [`groupLeaderboardEngine.ts`](./components/walking/group-details/groupLeaderboardEngine.ts) so scoring math can be tested independently of React component state.

---

## 6. Modular Component Conventions

Large interactive modules are split cleanly into **UI Shell + Pure Logic Engine + StyleSheet**:

- **Walking Challenge Modal:**
  - Shell: [`components/walking/GroupDetailsModal.tsx`](./components/walking/GroupDetailsModal.tsx)
  - Pure Scoring Engine: [`components/walking/group-details/groupLeaderboardEngine.ts`](./components/walking/group-details/groupLeaderboardEngine.ts)
  - Extracted Action Sheet: [`components/walking/group-details/GroupActionsSheet.tsx`](./components/walking/group-details/GroupActionsSheet.tsx)
  - Styles: [`components/walking/group-details/groupDetailsStyles.ts`](./components/walking/group-details/groupDetailsStyles.ts)
- **Quiz Competitions:**
  - Shell: [`components/quiz/QuizCompetitionWidget.tsx`](./components/quiz/QuizCompetitionWidget.tsx)
  - Helpers (HTML check, countdowns, phone validation): [`components/quiz/competition/quizCompetitionHelpers.ts`](./components/quiz/competition/quizCompetitionHelpers.ts)
  - Styles: [`components/quiz/competition/quizCompetitionStyles.ts`](./components/quiz/competition/quizCompetitionStyles.ts)
