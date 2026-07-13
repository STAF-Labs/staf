import { http } from '@/shared/api/http'
import type { ListResponse, UserDetail, UserListItem } from '@staf/contracts'

export type { ListResponse, UserDetail, UserListItem } from '@staf/contracts'

export type UserFilters = {
  search: string
  status: string
  createdFrom: string
  createdTo: string
  isPublic: string
  showOnlineStatus: string
  showLastSeenAt: string
  deleted: string
}

export async function fetchUsers(filters: UserFilters): Promise<ListResponse<UserListItem>> {
  const response = await http.get<ListResponse<UserListItem>>('/api/users', {
    params: {
      search: filters.search || undefined,
      status: filters.status || undefined,
      created_from: filters.createdFrom || undefined,
      created_to: filters.createdTo || undefined,
      is_public: filters.isPublic || undefined,
      show_online_status: filters.showOnlineStatus || undefined,
      show_last_seen_at: filters.showLastSeenAt || undefined,
      deleted: filters.deleted !== 'without' ? filters.deleted : undefined,
    },
  })

  return response.data
}

export async function fetchUser(id: number | string): Promise<UserDetail> {
  const response = await http.get<UserDetail>(`/api/users/${id}`)

  return response.data
}

export async function blockUser(id: number | string): Promise<UserDetail> {
  const response = await http.patch<UserDetail>(`/api/users/${id}/block`)

  return response.data
}

export async function unblockUser(id: number | string): Promise<UserDetail> {
  const response = await http.patch<UserDetail>(`/api/users/${id}/unblock`)

  return response.data
}

export async function softDeleteUser(id: number | string): Promise<{ message: string }> {
  const response = await http.delete<{ message: string }>(`/api/users/${id}`)

  return response.data
}
