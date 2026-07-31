import { http } from '@/shared/api/http'
import type {
  CreateProjectPayload,
  HttpsUrl,
  ListResponse,
  ProjectContentTypeOption,
  ProjectDetail,
  ProjectListItem,
  ProjectOwnerOption,
} from '@staf/contracts'

export type {
  CreateProjectPayload,
  HttpsUrl,
  ProjectContentTypeOption,
  ProjectDetail,
  ProjectListItem,
  ProjectOwnerOption,
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

export async function updateProject(id: number | string, payload: CreateProjectPayload): Promise<ProjectDetail> {
  const response = await http.patch<ProjectDetail>(`/api/projects/${id}`, makeProjectPayload(payload))

  return response.data
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
    status: payload.status,
  }
}
