export type ProjectStatus = 'draft' | 'on_moderation' | 'published' | 'rejected' | 'archived'

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
  game_content_type_id: number
  title: string
  slug: string
  summary: unknown
  description: unknown
  tags: unknown
  website_urls: unknown
  status: ProjectStatus | null
  status_label: string | null
  status_color: string | null
  game_id: number | null
  game_name: string | null
  content_type_name: string | null
  released_at: string | null
  created_at: string | null
}

export type ProjectDetail = ProjectListItem

export type ProjectContentTypeOption = {
  id: number
  game_id: number
  game_name: string | null
  content_type_id: number
  content_type_name: string | null
}

export type ProjectOwnerOption = {
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
  status: ProjectStatus
}
