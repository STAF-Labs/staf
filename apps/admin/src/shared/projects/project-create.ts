import { reactive } from 'vue'

export const projectCreateSteps = [
  'Игра',
  'Основные данные',
  'Описание',
  'Лицензия',
  'Подтверждение',
] as const

export type ProjectCreateDraft = {
  gameId: string
  gameContentTypeId: number | null
  title: string
  logo: File | null
  summary: unknown
  summaryFilled: boolean
  description: unknown
  descriptionFilled: boolean
  licence: File | null
  dimensionValueIds: Record<number, number[]>
  requiredProjectDimensionIds: number[]
  tagInput: string
  tags: string[]
  websiteUrls: string[]
}

export const projectCreateDraft = reactive<ProjectCreateDraft>({
  gameId: '',
  gameContentTypeId: null,
  title: '',
  logo: null,
  summary: null,
  summaryFilled: false,
  description: null,
  descriptionFilled: false,
  licence: null,
  dimensionValueIds: {},
  requiredProjectDimensionIds: [],
  tagInput: '',
  tags: [],
  websiteUrls: [],
})

export function selectProjectGame(gameId: string): void {
  if (projectCreateDraft.gameId === gameId) {
    return
  }

  Object.assign(projectCreateDraft, {
    gameId,
    gameContentTypeId: null,
    title: '',
    logo: null,
    summary: null,
    summaryFilled: false,
    description: null,
    descriptionFilled: false,
    licence: null,
    dimensionValueIds: {},
    requiredProjectDimensionIds: [],
    tagInput: '',
    tags: [],
    websiteUrls: [],
  })
}

export function resetProjectCreateDraft(): void {
  Object.assign(projectCreateDraft, {
    gameId: '',
    gameContentTypeId: null,
    title: '',
    logo: null,
    summary: null,
    summaryFilled: false,
    description: null,
    descriptionFilled: false,
    licence: null,
    dimensionValueIds: {},
    requiredProjectDimensionIds: [],
    tagInput: '',
    tags: [],
    websiteUrls: [],
  })
}

export function projectDescriptionIsComplete(gameId: string): boolean {
  return projectBasicsAreComplete(gameId) && projectCreateDraft.descriptionFilled
}

export function projectBasicsAreComplete(gameId: string): boolean {
  return Boolean(
    projectCreateDraft.gameId === gameId &&
    projectCreateDraft.gameContentTypeId &&
    projectCreateDraft.title.trim() &&
    projectCreateDraft.logo &&
    projectCreateDraft.summaryFilled &&
    projectCreateDraft.requiredProjectDimensionIds.every(
      (dimensionId) => (projectCreateDraft.dimensionValueIds[dimensionId]?.length ?? 0) > 0,
    ),
  )
}
