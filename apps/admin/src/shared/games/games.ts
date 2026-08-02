import { http } from '@/shared/api/http'
import type {
  CreateGamePayload,
  GameContentTypeListItem,
  GameDetail,
  GameListItem,
  ListResponse,
} from '@staf/contracts'

export type {
  CreateGamePayload,
  GameContentTypeListItem,
  GameDetail,
  GameListItem,
  GameStatus,
} from '@staf/contracts'

export type GameDimensionValue = {
  id: number
  dimension_id: number
  parent_id: number | null
  name: string
  sort_order: number
  is_active: boolean
}

export type GameDimension = {
  id: number
  game_content_type_id: number
  name: string
  slug: string
  selection_mode: 'single' | 'multiple'
  is_filterable: boolean
  is_required: boolean
  is_active: boolean
  sort_order: number
  values: GameDimensionValue[]
}

export type CreateGameDimensionPayload = {
  name: string
  selection_mode: 'single' | 'multiple'
  is_filterable: boolean
}

type UpdateGameDimensionPayload = Partial<
  CreateGameDimensionPayload & Pick<GameDimension, 'is_active' | 'sort_order'>
>

type UpdateGameDimensionValuePayload = Partial<
  Pick<GameDimensionValue, 'name' | 'is_active' | 'sort_order'>
>

export type CopyGameDimensionsResponse = {
  created_filters: number
  reused_filters: number
  created_values: number
  skipped_values: number
  data: GameDimension[]
}

export async function fetchGames(): Promise<ListResponse<GameListItem>> {
  const response = await http.get<ListResponse<GameListItem>>('/api/games')

  return response.data
}

export async function fetchGame(id: number | string): Promise<GameDetail> {
  const response = await http.get<GameDetail>(`/api/games/${id}`)

  return response.data
}

export async function fetchGameContentTypes(
  id: number | string,
): Promise<ListResponse<GameContentTypeListItem>> {
  const response = await http.get<ListResponse<GameContentTypeListItem>>(
    `/api/games/${id}/content-types`,
  )

  return response.data
}

export async function attachGameContentType(
  gameId: number | string,
  contentTypeId: number | string,
): Promise<GameContentTypeListItem> {
  const response = await http.post<GameContentTypeListItem>(`/api/games/${gameId}/content-types`, {
    content_type_id: contentTypeId,
  })

  return response.data
}

export async function detachGameContentType(
  gameId: number | string,
  gameContentTypeId: number | string,
): Promise<{ message: string }> {
  const response = await http.delete<{ message: string }>(
    `/api/games/${gameId}/content-types/${gameContentTypeId}`,
  )

  return response.data
}

function dimensionBaseUrl(gameId: number, gameContentTypeId: number): string {
  return `/api/games/${gameId}/content-types/${gameContentTypeId}/dimensions`
}

export async function fetchGameDimensions(
  gameId: number,
  gameContentTypeId: number,
): Promise<ListResponse<GameDimension>> {
  const response = await http.get<ListResponse<GameDimension>>(
    dimensionBaseUrl(gameId, gameContentTypeId),
  )

  return response.data
}

export async function createGameDimension(
  gameId: number,
  gameContentTypeId: number,
  payload: CreateGameDimensionPayload,
): Promise<GameDimension> {
  const response = await http.post<GameDimension>(
    dimensionBaseUrl(gameId, gameContentTypeId),
    payload,
  )

  return response.data
}

export async function copyGameDimensions(
  gameId: number,
  targetGameContentTypeId: number,
  sourceGameContentTypeId: number,
  dimensionIds: number[],
): Promise<CopyGameDimensionsResponse> {
  const response = await http.post<CopyGameDimensionsResponse>(
    `${dimensionBaseUrl(gameId, targetGameContentTypeId)}/copy`,
    {
      source_game_content_type_id: sourceGameContentTypeId,
      dimension_ids: dimensionIds,
    },
  )

  return response.data
}

export async function updateGameDimension(
  gameId: number,
  gameContentTypeId: number,
  dimensionId: number,
  payload: UpdateGameDimensionPayload,
): Promise<GameDimension> {
  const response = await http.patch<GameDimension>(
    `${dimensionBaseUrl(gameId, gameContentTypeId)}/${dimensionId}`,
    payload,
  )

  return response.data
}

export async function deleteGameDimension(
  gameId: number,
  gameContentTypeId: number,
  dimensionId: number,
): Promise<{ message: string }> {
  const response = await http.delete<{ message: string }>(
    `${dimensionBaseUrl(gameId, gameContentTypeId)}/${dimensionId}`,
  )

  return response.data
}

export async function createGameDimensionValue(
  gameId: number,
  gameContentTypeId: number,
  dimensionId: number,
  name: string,
): Promise<GameDimensionValue> {
  const response = await http.post<GameDimensionValue>(
    `${dimensionBaseUrl(gameId, gameContentTypeId)}/${dimensionId}/values`,
    { name },
  )

  return response.data
}

export async function updateGameDimensionValue(
  gameId: number,
  gameContentTypeId: number,
  dimensionId: number,
  valueId: number,
  payload: UpdateGameDimensionValuePayload,
): Promise<GameDimensionValue> {
  const response = await http.patch<GameDimensionValue>(
    `${dimensionBaseUrl(gameId, gameContentTypeId)}/${dimensionId}/values/${valueId}`,
    payload,
  )

  return response.data
}

export async function deleteGameDimensionValue(
  gameId: number,
  gameContentTypeId: number,
  dimensionId: number,
  valueId: number,
): Promise<{ message: string }> {
  const response = await http.delete<{ message: string }>(
    `${dimensionBaseUrl(gameId, gameContentTypeId)}/${dimensionId}/values/${valueId}`,
  )

  return response.data
}

export async function createGame(payload: CreateGamePayload): Promise<GameDetail> {
  const formData = makeGameFormData(payload)
  const response = await http.post<GameDetail>('/api/games', formData)

  return response.data
}

export async function updateGame(
  id: number | string,
  payload: CreateGamePayload,
): Promise<GameDetail> {
  const formData = makeGameFormData(payload)
  formData.append('_method', 'PATCH')

  const response = await http.post<GameDetail>(`/api/games/${id}`, formData)

  return response.data
}

function makeGameFormData(payload: CreateGamePayload): FormData {
  const formData = new FormData()

  formData.append('name', payload.name)
  formData.append('status', payload.status)

  if (payload.description !== null) {
    formData.append('description', JSON.stringify(payload.description))
  }

  if (payload.releasedAt) {
    formData.append('released_at', payload.releasedAt)
  }

  if (payload.banner) {
    formData.append('banner', payload.banner)
  }

  if (payload.logo) {
    formData.append('logo', payload.logo)
  }

  return formData
}

export async function deleteGame(id: number | string): Promise<{ message: string }> {
  const response = await http.delete<{ message: string }>(`/api/games/${id}`)

  return response.data
}
