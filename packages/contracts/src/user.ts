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
  status_color: string | null
  display_name: string | null
  avatar_url: string | null
  birthday: string | null
  is_public: boolean | null
  show_online_status: boolean | null
  show_last_seen_at: boolean | null
  email_verified_at: string | null
  last_seen_at: string | null
  created_at: string | null
}

export type UserDetail = UserListItem & {
  profile: {
    id: number
    display_name: string | null
    avatar_url: string | null
    bio: unknown
    website_urls: unknown
    birthday: string | null
    is_public: boolean
    show_online_status: boolean
    show_last_seen_at: boolean
    created_at: string | null
  } | null
  organizations: {
    id: number
    name: string
    status: string | null
    status_label: string | null
    status_color: string | null
    is_visible: boolean
    verified_at: string | null
  }[]
}
