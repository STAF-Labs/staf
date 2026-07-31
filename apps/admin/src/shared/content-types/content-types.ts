import { http } from '@/shared/api/http'
import type {
  ContentTypeImportResponse,
  ContentTypeImportRow,
  ContentTypeImportValidationResponse,
  ContentTypeListItem,
  CreateContentTypePayload,
  ListResponse,
} from '@staf/contracts'

export type {
  ContentTypeImportResponse,
  ContentTypeImportRow,
  ContentTypeImportValidationResponse,
  ContentTypeListItem,
  CreateContentTypePayload,
} from '@staf/contracts'

export async function fetchContentTypes(): Promise<ListResponse<ContentTypeListItem>> {
  const response = await http.get<ListResponse<ContentTypeListItem>>('/api/content-types')

  return response.data
}

export async function createContentType(payload: CreateContentTypePayload): Promise<ContentTypeListItem> {
  const response = await http.post<ContentTypeListItem>('/api/content-types', {
    name: payload.name,
    is_public: payload.isPublic,
  })

  return response.data
}

export async function updateContentType(
  id: number | string,
  payload: CreateContentTypePayload,
): Promise<ContentTypeListItem> {
  const response = await http.patch<ContentTypeListItem>(`/api/content-types/${id}`, {
    name: payload.name,
    is_public: payload.isPublic,
  })

  return response.data
}

export async function toggleContentTypePublic(id: number | string): Promise<ContentTypeListItem> {
  const response = await http.patch<ContentTypeListItem>(`/api/content-types/${id}/toggle-public`)

  return response.data
}

export async function validateContentTypeImportFile(file: File): Promise<ContentTypeImportValidationResponse> {
  const formData = new FormData()
  formData.append('file', file)

  const response = await http.post<ContentTypeImportValidationResponse>('/api/content-types/import/validate', formData)

  return response.data
}

export async function importContentTypeFile(file: File): Promise<ContentTypeImportResponse> {
  const formData = new FormData()
  formData.append('file', file)

  const response = await http.post<ContentTypeImportResponse>('/api/content-types/import', formData)

  return response.data
}
