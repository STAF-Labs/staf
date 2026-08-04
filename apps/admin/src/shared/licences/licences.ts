import { http } from '@/shared/api/http'
import type { LicenceOption } from '@staf/contracts'

export type { LicenceOption } from '@staf/contracts'

export async function fetchLicences(): Promise<{ data: LicenceOption[]; total: number }> {
  const response = await http.get<{ data: LicenceOption[]; total: number }>('/api/licences')

  return response.data
}
