/**
 * Production-safe centralized logger.
 *
 * - `debug`, `info`, `warn` are suppressed in production (`!__DEV__`) to keep the JS thread
 *   free of serialization overhead and avoid leaking internal diagnostics.
 * - `error` redacts bearer / access tokens before logging.
 */

const isDevEnvironment = (): boolean =>
  typeof __DEV__ !== 'undefined' && Boolean(__DEV__);

const BEARER_REGEX = /Bearer\s+[A-Za-z0-9\-._~+/]+=*/gi;
const ACCESS_TOKEN_HEADER_REGEX = /(X-Site-Access-Token:\s*)([^\s\n]+)/gi;

function sanitizeValue(arg: unknown): unknown {
  if (typeof arg === 'string') {
    return arg
      .replace(BEARER_REGEX, 'Bearer [REDACTED]')
      .replace(ACCESS_TOKEN_HEADER_REGEX, '$1[REDACTED]');
  }
  if (arg instanceof Error) {
    return arg.message
      .replace(BEARER_REGEX, 'Bearer [REDACTED]')
      .replace(ACCESS_TOKEN_HEADER_REGEX, '$1[REDACTED]');
  }
  return arg;
}

function sanitizeArgs(args: unknown[]): unknown[] {
  return args.map(sanitizeValue);
}

export const logger = {
  debug(...args: unknown[]): void {
    if (isDevEnvironment()) {
      console.log(...args);
    }
  },

  info(...args: unknown[]): void {
    if (isDevEnvironment()) {
      console.info(...args);
    }
  },

  warn(...args: unknown[]): void {
    if (isDevEnvironment()) {
      console.warn(...args);
    }
  },

  error(...args: unknown[]): void {
    console.error(...sanitizeArgs(args));
  },
};
