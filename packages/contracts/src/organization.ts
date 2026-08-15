import type { ProjectListItem } from './project'

export type OrganizationListItem = {
  id: number
  name: string
  slug: string
  summary: string | null
  contact_email: string | null
  avatar_url: string | null
  banner_url: string | null
  status: string | null
  status_label: string | null
  status_color: string | null
  is_visible: boolean
  verified_at: string | null
  created_at: string | null
}

export type OrganizationDetail = OrganizationListItem & {
  description: unknown
  website_urls: unknown
  members: {
    id: number
    user_id: number | null
    username: string | null
    display_name: string | null
    avatar_url: string | null
    email_verified_at: string | null
    status: string | null
    status_label: string | null
    status_color: string | null
    public_title: string | null
    role: string | null
    role_label: string | null
    role_color: string | null
  }[]
  projects: ProjectListItem[]
}
