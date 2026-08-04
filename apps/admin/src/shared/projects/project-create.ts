import { reactive } from 'vue'
import { fetchProject } from '@/shared/projects/projects'

export const projectCreateSteps = [
  'Игра',
  'Основные данные',
  'Описание',
  'Лицензия',
  'Подтверждение',
] as const

export type ProjectCreateDraft = {
  projectId: number | null
  gameId: string
  gameContentTypeId: number | null
  title: string
  logo: File | null
  logoUrl: string | null
  summary: unknown
  summaryFilled: boolean
  description: unknown
  descriptionFilled: boolean
  licenceName: string
  dimensionValueIds: Record<number, number[]>
  requiredProjectDimensionIds: number[]
  persistedDimensionValueIds: number[]
  tagInput: string
  tags: string[]
  websiteUrls: string[]
}

export const projectCreateDraft = reactive<ProjectCreateDraft>({
  projectId: null,
  gameId: '',
  gameContentTypeId: null,
  title: '',
  logo: null,
  logoUrl: null,
  summary: null,
  summaryFilled: false,
  description: null,
  descriptionFilled: false,
  licenceName: '',
  dimensionValueIds: {},
  requiredProjectDimensionIds: [],
  persistedDimensionValueIds: [],
  tagInput: '',
  tags: [],
  websiteUrls: [],
})

export function selectProjectGame(gameId: string): void {
  if (projectCreateDraft.gameId === gameId) {
    return
  }

  Object.assign(projectCreateDraft, {
    projectId: null,
    gameId,
    gameContentTypeId: null,
    title: '',
    logo: null,
    logoUrl: null,
    summary: null,
    summaryFilled: false,
    description: null,
    descriptionFilled: false,
    licenceName: '',
    dimensionValueIds: {},
    requiredProjectDimensionIds: [],
    persistedDimensionValueIds: [],
    tagInput: '',
    tags: [],
    websiteUrls: [],
  })
}

export function resetProjectCreateDraft(): void {
  Object.assign(projectCreateDraft, {
    projectId: null,
    gameId: '',
    gameContentTypeId: null,
    title: '',
    logo: null,
    logoUrl: null,
    summary: null,
    summaryFilled: false,
    description: null,
    descriptionFilled: false,
    licenceName: '',
    dimensionValueIds: {},
    requiredProjectDimensionIds: [],
    persistedDimensionValueIds: [],
    tagInput: '',
    tags: [],
    websiteUrls: [],
  })
}

export function projectDescriptionIsComplete(gameId: string): boolean {
  return projectBasicsAreComplete(gameId) && projectCreateDraft.descriptionFilled
}

export function calculateProjectPercentageComplete(): number {
  const checklist = projectCompletionChecklist()

  return Math.round(
    (checklist.filter((item) => item.complete).length / checklist.length) * 100,
  )
}

export type ProjectCompletionItem = {
  id: string
  label: string
  complete: boolean
}

export function projectCompletionChecklist(): ProjectCompletionItem[] {
  const items: ProjectCompletionItem[] = [
    { id: 'game', label: 'Игра', complete: Boolean(projectCreateDraft.gameId) },
    {
      id: 'content-type',
      label: 'Тип контента',
      complete: Boolean(projectCreateDraft.gameContentTypeId),
    },
    { id: 'title', label: 'Название', complete: Boolean(projectCreateDraft.title.trim()) },
    {
      id: 'logo',
      label: 'Логотип',
      complete: Boolean(projectCreateDraft.logo || projectCreateDraft.logoUrl),
    },
    {
      id: 'summary',
      label: 'Краткое описание',
      complete: projectCreateDraft.summaryFilled,
    },
    {
      id: 'description',
      label: 'Подробное описание',
      complete: projectCreateDraft.descriptionFilled,
    },
  ]

  if (projectCreateDraft.requiredProjectDimensionIds.length > 0) {
    items.push({
      id: 'dimensions',
      label: 'Обязательные настройки',
      complete: projectCreateDraft.requiredProjectDimensionIds.every(
        (dimensionId) => (projectCreateDraft.dimensionValueIds[dimensionId]?.length ?? 0) > 0,
      ),
    })
  }

  return items
}

export async function hydrateProjectCreateDraft(projectId: number): Promise<void> {
  if (projectCreateDraft.projectId === projectId) {
    return
  }

  const project = await fetchProject(projectId)

  Object.assign(projectCreateDraft, {
    projectId: project.id,
    gameId: project.game_id === null ? '' : String(project.game_id),
    gameContentTypeId: project.game_content_type_id,
    title: project.title,
    logo: null,
    logoUrl: project.logo_url,
    summary: project.summary,
    summaryFilled: richTextIsFilled(project.summary),
    description: project.description,
    descriptionFilled: richTextIsFilled(project.description),
    licenceName: project.licence_name ?? '',
    persistedDimensionValueIds: project.dimension_value_ids ?? [],
    tagInput: '',
    tags: normalizeStringArray(project.tags),
    websiteUrls: normalizeStringArray(project.website_urls),
  })
}

function richTextIsFilled(value: unknown): boolean {
  if (!value || typeof value !== 'object' || !('content' in value)) {
    return false
  }

  return Array.isArray(value.content) && value.content.length > 0
}

function normalizeStringArray(value: unknown): string[] {
  return Array.isArray(value)
    ? value.filter((item): item is string => typeof item === 'string')
    : []
}

export function projectBasicsAreComplete(gameId: string): boolean {
  return Boolean(
    projectCreateDraft.gameId === gameId &&
    projectCreateDraft.gameContentTypeId &&
    projectCreateDraft.title.trim() &&
    (projectCreateDraft.logo || projectCreateDraft.logoUrl) &&
    projectCreateDraft.summaryFilled &&
    projectCreateDraft.requiredProjectDimensionIds.every(
      (dimensionId) => (projectCreateDraft.dimensionValueIds[dimensionId]?.length ?? 0) > 0,
    ),
  )
}
