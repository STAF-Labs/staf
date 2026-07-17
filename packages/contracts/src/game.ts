export type GameStatus = 'active' | 'suspended' | 'blocked'

export type GameDetail = {
  id: number
  name: string
  slug: string
  description: unknown
  released_at: string | null
  status: GameStatus | null
  status_label: string | null
  status_color: string | null
  logo_url: string | null
  banner_url: string | null
  created_at: string | null
}

export type GameListItem = GameDetail

export type CreateGamePayload = {
  name: string
  description: Record<string, unknown> | null
  releasedAt: string
  status: GameStatus
  logo: File | null
  banner: File | null
}
