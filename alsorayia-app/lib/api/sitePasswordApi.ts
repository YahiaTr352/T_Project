/**
 * مسارات كلمة مرور الموقع (Laravel routes/api.php) — بدون CSRF.
 * @see family-tree-app/docs/mobile-app-api-integration.md
 */
import { api } from '@/lib/api/alsorayiaClient';
import { isAxiosError } from 'axios';

function logServerError(scope: string, detail: Record<string, unknown>) {
  console.error(`[SitePassword API] ${scope}`, detail);
}

export type SitePasswordStatus = {
  success: boolean;
  protection_enabled: boolean;
  password_length: number | null;
  requires_password: boolean;
  session_timeout_minutes: number;
};

export type SitePasswordVerifySuccess = {
  success: true;
  message?: string;
  access_token: string | null;
  token_type: string | null;
  expires_in: number | null;
};

export type SitePasswordVerifyFailure = {
  success: false;
  message?: string;
  errors?: Record<string, string[]>;
};

export async function getSitePasswordStatus(): Promise<SitePasswordStatus> {
  try {
    const data = await api.get<SitePasswordStatus>('/site-password/status', {
      skipGlobalErrorHandler: true,
    });
    return (data as unknown) as SitePasswordStatus;
  } catch (e) {
    if (isAxiosError(e)) {
      logServerError('GET /site-password/status', {
        status: e.response?.status,
        data: e.response?.data,
        message: e.message,
      });
    } else {
      logServerError('GET /site-password/status (non-Axios)', { error: String(e) });
    }
    throw e;
  }
}

export async function verifySitePassword(
  password: string
): Promise<SitePasswordVerifySuccess | SitePasswordVerifyFailure> {
  try {
    const data = await api.post<
      SitePasswordVerifySuccess | SitePasswordVerifyFailure
    >('/site-password/verify', { password }, { skipGlobalErrorHandler: true });
    const body = (data as unknown) as SitePasswordVerifySuccess | SitePasswordVerifyFailure;
    if (body && typeof body === 'object' && 'success' in body && body.success === false) {
      logServerError('POST /site-password/verify (success:false في الجسم)', {
        body,
      });
    }
    return body;
  } catch (e) {
    if (isAxiosError(e)) {
      logServerError('POST /site-password/verify', {
        status: e.response?.status,
        data: e.response,
        message: e.message,
      });
      if (e.response?.data && typeof e.response.data === 'object') {
        return e.response.data as SitePasswordVerifyFailure;
      }
    } else {
      logServerError('POST /site-password/verify (non-Axios)', { error: String(e) });
    }
    throw e;
  }
}

export async function revokeSitePassword(accessToken: string): Promise<void> {
  try {
    await api.post(
      '/site-password/revoke',
      {},
      {
        skipGlobalErrorHandler: true,
        headers: {
          Authorization: `Bearer ${accessToken}`,
          'X-Site-Access-Token': accessToken,
        },
      }
    );
  } catch (e) {
    if (isAxiosError(e)) {
      logServerError('POST /site-password/revoke', {
        status: e.response?.status,
        data: e.response?.data,
        message: e.message,
      });
    }
    throw e;
  }
}
