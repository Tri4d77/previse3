import api from './api'

export interface RequestEmailChangePayload {
  password: string
  new_email: string
}

export async function requestEmailChange(payload: RequestEmailChangePayload): Promise<string> {
  const response = await api.post('/profile/email/change', payload)
  return response.data.pending_email as string
}

export async function confirmEmailChange(token: string): Promise<string> {
  const response = await api.post('/auth/email/confirm', { token })
  return response.data.email as string
}

export async function cancelEmailChange(): Promise<void> {
  await api.delete('/profile/email/pending')
}
