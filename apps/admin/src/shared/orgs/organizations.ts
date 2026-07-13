import { http } from '@/shared/api/http'
import type { ListResponse, OrganizationDetail, OrganizationListItem } from '@staf/contracts'

export type { ListResponse, OrganizationDetail, OrganizationListItem } from '@staf/contracts'

export type OrganizationFilters = {
  search: string
  status: string
  isVisible: string
  verification: string
  createdFrom: string
  createdTo: string
  deleted: string
}

export async function fetchOrganizations(
  filters: OrganizationFilters,
): Promise<ListResponse<OrganizationListItem>> {
  const response = await http.get<ListResponse<OrganizationListItem>>('/api/organizations', {
    params: {
      search: filters.search || undefined,
      status: filters.status || undefined,
      is_visible: filters.isVisible || undefined,
      verification: filters.verification || undefined,
      created_from: filters.createdFrom || undefined,
      created_to: filters.createdTo || undefined,
      deleted: filters.deleted !== 'without' ? filters.deleted : undefined,
    },
  })

  return response.data
}

export async function fetchOrganization(id: number | string): Promise<OrganizationDetail> {
  const response = await http.get<OrganizationDetail>(`/api/organizations/${id}`)

  return response.data
}

export async function blockOrganization(id: number | string): Promise<OrganizationListItem> {
  const response = await http.patch<OrganizationListItem>(`/api/organizations/${id}/block`)

  return response.data
}

export async function unblockOrganization(id: number | string): Promise<OrganizationListItem> {
  const response = await http.patch<OrganizationListItem>(`/api/organizations/${id}/unblock`)

  return response.data
}

export async function freezeOrganization(id: number | string): Promise<OrganizationListItem> {
  const response = await http.patch<OrganizationListItem>(`/api/organizations/${id}/freeze`)

  return response.data
}

export async function unfreezeOrganization(id: number | string): Promise<OrganizationListItem> {
  const response = await http.patch<OrganizationListItem>(`/api/organizations/${id}/unfreeze`)

  return response.data
}

export async function softDeleteOrganization(id: number | string): Promise<{ message: string }> {
  const response = await http.delete<{ message: string }>(`/api/organizations/${id}`)

  return response.data
}
