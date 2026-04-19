import api from './api'

export interface TwoFactorStatus {
  enabled: boolean
  setup_in_progress: boolean
  confirmed_at: string | null
}

export interface TwoFactorEnableResponse {
  secret: string
  otpauth_url: string
  qr_code_svg: string
}

export async function fetchStatus(): Promise<TwoFactorStatus> {
  const response = await api.get('/profile/2fa/status')
  return response.data
}

export async function enable(): Promise<TwoFactorEnableResponse> {
  const response = await api.post('/profile/2fa/enable')
  return response.data.data
}

export async function confirm(code: string): Promise<string[]> {
  const response = await api.post('/profile/2fa/confirm', { code })
  return response.data.data.recovery_codes
}

export async function disable(password: string): Promise<void> {
  await api.post('/profile/2fa/disable', { password })
}

export async function fetchRecoveryCodes(): Promise<string[]> {
  const response = await api.get('/profile/2fa/recovery-codes')
  return response.data.data
}

export async function regenerateRecoveryCodes(): Promise<string[]> {
  const response = await api.post('/profile/2fa/recovery-codes/regenerate')
  return response.data.data
}

/**
 * Login utáni TOTP / recovery kód ellenőrzés. Header: Authorization: Bearer {challenge_token}
 * Visszaad egy teljes login választ (data.user + data.token vagy requires_organization_selection).
 */
export async function challenge(params: {
  challengeToken: string
  code?: string
  recoveryCode?: string
}): Promise<unknown> {
  const response = await api.post(
    '/auth/2fa/challenge',
    {
      code: params.code,
      recovery_code: params.recoveryCode,
    },
    {
      headers: { Authorization: `Bearer ${params.challengeToken}` },
    }
  )
  return response.data
}
