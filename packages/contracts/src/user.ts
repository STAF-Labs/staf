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
