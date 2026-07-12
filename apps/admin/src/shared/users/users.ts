import { http } from '@/shared/api/http'
import type { ListResponse, UserListItem, UserProfileListItem } from '@staf/contracts'

export type { ListResponse, UserListItem, UserProfileListItem } from '@staf/contracts'

export async function fetchUsers(search: string): Promise<ListResponse<UserListItem>> {
  const response = await http.get<ListResponse<UserListItem>>('/api/users', {
    params: { search: search || undefined },
  })

  return response.data
}

export async function fetchUserProfiles(search: string): Promise<ListResponse<UserProfileListItem>> {
  const response = await http.get<ListResponse<UserProfileListItem>>('/api/user-profiles', {
    params: { search: search || undefined },
  })

  return response.data
}
