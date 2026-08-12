export type ProjectStatus = 'draft' | 'on_moderation' | 'published' | 'rejected'

export type ProjectPublicationStatus = 'public' | 'private' | 'url_only' | 'archived'

export type ProjectReleaseType = 'alpha' | 'beta' | 'release'

export type ProjectReleaseStatus = 'published' | 'on_moderation' | 'archived'

export type ProjectMemberRole = 'owner' | 'maintainer' | 'member'

export type ProjectMemberStatus = 'active' | 'invited' | 'suspended'

export type ProjectMemberAccessSource = 'project' | 'organization'

export type ContentTypeListItem = {
  id: number
  name: string
  slug: string
  games: Array<{
    id: number
    name: string
  }>
  game_ids: number[]
  game_names: string[]
  is_public: boolean
  created_at: string | null
}

export type CreateContentTypePayload = {
  name: string
  isPublic: boolean
}

export type ContentTypeImportValidationResponse = {
  valid: boolean
  message: string
  rows: ContentTypeImportRow[]
}

export type ContentTypeImportRow = {
  row: number
  name: string
  is_public: boolean
}

export type ContentTypeImportResponse = {
  message: string
  total: number
  imported_count: number
  skipped_count: number
}

export type ProjectListItem = {
  id: number
  ownerable_type: string
  ownerable_id: number
  owner_name: string | null
  can_delete: boolean
  game_content_type_id: number
  title: string
  slug: string
  summary: unknown
  description: unknown
  tags: unknown
  website_urls: unknown
  logo_url: string | null
  screenshots: ProjectScreenshot[]
  screenshot_urls: string[]
  licence_name: string | null
  dimension_value_ids?: number[]
  percentage_complete: number
  publication_status: ProjectPublicationStatus
  publication_status_label: string | null
  publication_status_color: string | null
  status: ProjectStatus | null
  status_label: string | null
  status_color: string | null
  game_id: number | null
  game_name: string | null
  content_type_name: string | null
  released_at: string | null
  releases_count: number
  created_at: string | null
  updated_at: string | null
}

export type ProjectDetail = ProjectListItem

export type ProjectScreenshot = {
  id: number
  url: string
  order: number
}

export type ProjectRelease = {
  id: number
  project_id: number
  title: string
  slug: string
  type: ProjectReleaseType
  type_label: string | null
  type_color: string | null
  changelog: unknown
  released_at: string | null
  status: ProjectReleaseStatus
  status_label: string | null
  status_color: string | null
  file_url: string | null
  file_name: string | null
  dimension_value_ids?: number[]
  created_at: string | null
  updated_at: string | null
}

export type ProjectMember = {
  id: number | string
  project_id: number
  user_id: number
  username: string | null
  display_name: string | null
  avatar_url: string | null
  role: ProjectMemberRole
  role_label: string | null
  role_color: string | null
  status: ProjectMemberStatus
  status_label: string | null
  status_color: string | null
  created_at: string | null
  updated_at: string | null
  access_source: ProjectMemberAccessSource
}

export type ProjectMemberCandidate = {
  id: number
  username: string
  display_name: string | null
  avatar_url: string | null
}

export type ProjectContentTypeOption = {
  id: number
  game_id: number
  game_name: string | null
  content_type_id: number
  content_type_name: string | null
}

export type GameContentTypeListItem = {
  id: number
  game_id: number
  content_type_id: number
  content_type_name: string | null
  content_type_slug: string | null
  is_public: boolean | null
  projects_count: number
  filters_count: number
  created_at: string | null
}

export type ProjectOwnerOption = {
  kind: 'user' | 'organization'
  type: string
  id: number
  label: string
}

export type HttpsUrl = `https://${string}`

export type CreateProjectPayload = {
  ownerableType: string
  ownerableId: number
  gameContentTypeId: number
  title: string
  slug: string
  summary: unknown
  description: unknown
  tags: unknown
  websiteUrls: HttpsUrl[] | null
  licenceName?: string | null
  percentageComplete?: number
  publicationStatus?: ProjectPublicationStatus
  status: ProjectStatus
}
