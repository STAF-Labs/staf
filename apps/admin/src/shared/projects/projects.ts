import { http } from '@/shared/api/http'
import type { GameDimension } from '@/shared/games/games'
import type {
  CreateProjectPayload,
  HttpsUrl,
  ListResponse,
  ProjectContentTypeOption,
  ProjectDetail,
  ProjectListItem,
  ProjectOwnerOption,
  ProjectPublicationStatus,
  ProjectStatus,
} from '@staf/contracts'

export type {
  CreateProjectPayload,
  HttpsUrl,
  ProjectContentTypeOption,
  ProjectDetail,
  ProjectListItem,
  ProjectOwnerOption,
  ProjectPublicationStatus,
  ProjectStatus,
} from '@staf/contracts'

export async function fetchProjects(): Promise<ListResponse<ProjectListItem>> {
  const response = await http.get<ListResponse<ProjectListItem>>('/api/projects')

  return response.data
}

export async function fetchProject(id: number | string): Promise<ProjectDetail> {
  const response = await http.get<ProjectDetail>(`/api/projects/${id}`)

  return response.data
}

export async function fetchProjectReleaseFilters(
  id: number | string,
): Promise<ListResponse<GameDimension>> {
  const response = await http.get<ListResponse<GameDimension>>(`/api/projects/${id}/release-filters`)

  return response.data
}

export async function fetchProjectContentTypes(): Promise<ListResponse<ProjectContentTypeOption>> {
  const response = await http.get<ListResponse<ProjectContentTypeOption>>('/api/game-content-types')

  return response.data
}

export async function fetchProjectOwnerOptions(): Promise<{ data: ProjectOwnerOption[] }> {
  const response = await http.get<{ data: ProjectOwnerOption[] }>('/api/project-owner-options')

  return response.data
}

export async function createProject(payload: CreateProjectPayload): Promise<ProjectDetail> {
  const response = await http.post<ProjectDetail>('/api/projects', makeProjectPayload(payload))

  return response.data
}

export type SaveProjectDetailsPayload = {
  ownerableType?: string
  ownerableId?: number
  gameContentTypeId: number
  title: string
  summary: unknown
  tags: string[]
  websiteUrls: string[]
  logo: File | null
  dimensionValueIds: number[]
  percentageComplete: number
}

export async function saveProjectDetails(
  projectId: number | null,
  payload: SaveProjectDetailsPayload,
): Promise<ProjectDetail> {
  const formData = new FormData()

  if (projectId === null && payload.ownerableType && payload.ownerableId) {
    formData.append('ownerable_type', payload.ownerableType)
    formData.append('ownerable_id', String(payload.ownerableId))
  }

  formData.append('game_content_type_id', String(payload.gameContentTypeId))
  formData.append('title', payload.title)
  formData.append('summary', JSON.stringify(payload.summary))
  formData.append('percentage_complete', String(payload.percentageComplete))
  if (payload.logo) {
    formData.append('logo', payload.logo)
  }

  formData.append('tags', JSON.stringify(payload.tags))
  formData.append('website_urls', JSON.stringify(payload.websiteUrls))
  formData.append('dimension_value_ids', JSON.stringify(payload.dimensionValueIds))

  if (projectId !== null) {
    formData.append('_method', 'PATCH')
  }

  const response = await http.post<ProjectDetail>(
    projectId === null ? '/api/projects' : `/api/projects/${projectId}`,
    formData,
  )

  return response.data
}

export type UpdateProjectDraftPayload = {
  description?: unknown
  licenceName?: string | null
  percentageComplete: number
  publicationStatus?: ProjectPublicationStatus
  status?: ProjectStatus
}

export async function updateProjectDraft(
  id: number | string,
  payload: UpdateProjectDraftPayload,
): Promise<ProjectDetail> {
  const response = await http.patch<ProjectDetail>(`/api/projects/${id}`, {
    ...(payload.description !== undefined ? { description: payload.description } : {}),
    ...(payload.licenceName !== undefined ? { licence_name: payload.licenceName } : {}),
    percentage_complete: payload.percentageComplete,
    ...(payload.publicationStatus !== undefined
      ? { publication_status: payload.publicationStatus }
      : {}),
    ...(payload.status !== undefined ? { status: payload.status } : {}),
  })

  return response.data
}

export async function updateProject(
  id: number | string,
  payload: CreateProjectPayload,
): Promise<ProjectDetail> {
  const requestPayload = makeProjectPayload(payload)

  delete requestPayload.ownerable_type
  delete requestPayload.ownerable_id

  const response = await http.patch<ProjectDetail>(`/api/projects/${id}`, requestPayload)

  return response.data
}

export async function deleteProject(id: number | string): Promise<void> {
  await http.delete(`/api/projects/${id}`)
}

function makeProjectPayload(payload: CreateProjectPayload): Record<string, unknown> {
  return {
    ownerable_type: payload.ownerableType,
    ownerable_id: payload.ownerableId,
    game_content_type_id: payload.gameContentTypeId,
    title: payload.title,
    slug: payload.slug || null,
    summary: payload.summary,
    description: payload.description,
    tags: payload.tags,
    website_urls: payload.websiteUrls,
    ...(payload.licenceName !== undefined ? { licence_name: payload.licenceName } : {}),
    ...(payload.percentageComplete !== undefined
      ? { percentage_complete: payload.percentageComplete }
      : {}),
    ...(payload.publicationStatus !== undefined
      ? { publication_status: payload.publicationStatus }
      : {}),
    status: payload.status,
  }
}
