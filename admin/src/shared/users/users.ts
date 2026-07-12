import { http } from '@/shared/api/http'

export type ListResponse<T> = {
  data: T[]
  total: number
  filtered_total: number
}

export type UserListItem = {
  id: number
  username: string
  email: string
  status: string
  status_label: string
  display_name: string | null
  email_verified_at: string | null
  last_seen_at: string | null
  created_at: string | null
}

export type UserProfileListItem = {
  id: number
  user_id: number
  username: string | null
  email: string | null
  display_name: string | null
  birthday: string | null
  is_public: boolean
  show_online_status: boolean
  show_last_seen_at: boolean
  created_at: string | null
}

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
