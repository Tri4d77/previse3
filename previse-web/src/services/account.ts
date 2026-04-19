import api from './api'

export interface DeleteAccountResponse {
  scheduled_deletion_at: string
  message: string
}

export async function deleteAccount(password: string): Promise<DeleteAccountResponse> {
  const response = await api.delete('/profile', { data: { password } })
  return response.data
}

export async function cancelAccountDeletion(): Promise<void> {
  await api.post('/profile/delete/cancel')
}

export async function leaveOrganization(membershipId: number): Promise<void> {
  await api.post(`/profile/memberships/${membershipId}/leave`)
}
