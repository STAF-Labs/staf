import { http } from '@/shared/api/http'
import type { CreateGamePayload, GameDetail, GameListItem, ListResponse } from '@staf/contracts'

export type { CreateGamePayload, GameDetail, GameListItem, GameStatus } from '@staf/contracts'

export async function fetchGames(): Promise<ListResponse<GameListItem>> {
  const response = await http.get<ListResponse<GameListItem>>('/api/games')

  return response.data
}

export async function fetchGame(id: number | string): Promise<GameDetail> {
  const response = await http.get<GameDetail>(`/api/games/${id}`)

  return response.data
}

export async function createGame(payload: CreateGamePayload): Promise<GameDetail> {
  const formData = makeGameFormData(payload)
  const response = await http.post<GameDetail>('/api/games', formData)

  return response.data
}

export async function updateGame(id: number | string, payload: CreateGamePayload): Promise<GameDetail> {
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
