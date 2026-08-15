<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import type { RouteLocationRaw } from 'vue-router'
import { Calendar, Image, Info, Pencil, PlayCircle, Plus, RotateCcw, Trash2 } from '@lucide/vue'
import AppShell from '@/components/layout/AppShell.vue'
import DeleteModal from '@/components/ui/DeleteModal.vue'
import SearchField from '@/components/ui/SearchField.vue'
import ProjectIndexPanel from '@/views/projects/components/ProjectIndexPanel.vue'
import {
  fetchGameDimensions,
  fetchGames,
  type GameDimension,
  type GameDimensionValue,
  type GameListItem,
} from '@/shared/games/games'
import {
  deleteProject as removeProject,
  fetchProjectContentTypes,
  fetchProjectReleases,
  fetchProjects,
  type ProjectContentTypeOption,
  type ProjectListItem,
  type ProjectStatus,
} from '@/shared/projects/projects'

type StatusFilter = ProjectStatus | 'all'
type SortOption = 'title_asc' | 'title_desc' | 'released_at'
type DisplayMode = 'cards' | 'list'

const statusFilterOptions: Array<{ value: StatusFilter; label: string }> = [
  { value: 'all', label: 'Все статусы' },
  { value: 'draft', label: 'Черновик' },
  { value: 'on_moderation', label: 'На модерации' },
  { value: 'published', label: 'Опубликован' },
  { value: 'rejected', label: 'Отклонён' },
]
const sortOptions: Array<{ value: SortOption; label: string }> = [
  { value: 'title_asc', label: 'А-Я' },
  { value: 'title_desc', label: 'Я-А' },
  { value: 'released_at', label: 'Дата релиза' },
]
const pageSizeOptions = [10, 20, 30, 40, 50]

const projects = ref<ProjectListItem[]>([])
const games = ref<GameListItem[]>([])
const contentTypeOptions = ref<ProjectContentTypeOption[]>([])
const dimensions = ref<GameDimension[]>([])
const projectReleaseDimensionValueIds = ref<Record<number, number[]>>({})
const search = ref('')
const gameFilter = ref('all')
const contentTypeFilter = ref('all')
const selectedProjectFilterValueIds = ref<Record<number, number[]>>({})
const selectedReleaseFilterValueIds = ref<Record<number, number[]>>({})
const releaseDateFrom = ref('')
const releaseDateTo = ref('')
const statusFilter = ref<StatusFilter>('all')
const sort = ref<SortOption>('title_asc')
const pageSize = ref(20)
const displayMode = ref<DisplayMode>('cards')
const message = ref('')
const isLoading = ref(false)
const deletingProjectId = ref<number | null>(null)
const pendingDeleteProject = ref<ProjectListItem | null>(null)

const gameFilterOptions = computed(() => {
  return [
    { value: 'all', label: 'Все игры' },
    ...games.value
      .map((game) => ({ value: String(game.id), label: game.name }))
      .sort((firstOption, secondOption) =>
        firstOption.label.localeCompare(secondOption.label, 'ru-RU'),
      ),
  ]
})
const contentTypeFilterOptions = computed(() =>
  contentTypeOptions.value
    .filter((option) => gameFilter.value === 'all' || String(option.game_id) === gameFilter.value)
    .map((option) => ({
      value: String(option.id),
      label:
        gameFilter.value === 'all'
          ? `${option.content_type_name ?? 'Тип контента'} · ${option.game_name ?? 'Игра'}`
          : (option.content_type_name ?? 'Тип контента'),
    }))
    .sort((firstOption, secondOption) =>
      firstOption.label.localeCompare(secondOption.label, 'ru-RU'),
    ),
)
const projectFilterDimensions = computed(() =>
  dimensions.value.filter(
    (dimension) =>
      dimension.is_active &&
      dimension.is_filterable &&
      dimension.applies_to === 'project' &&
      contentTypeFilter.value !== 'all' &&
      String(dimension.game_content_type_id) === contentTypeFilter.value,
  ),
)
const releaseFilterDimensions = computed(() =>
  dimensions.value.filter(
    (dimension) =>
      dimension.is_active &&
      dimension.is_filterable &&
      dimension.applies_to === 'release' &&
      contentTypeFilter.value !== 'all' &&
      String(dimension.game_content_type_id) === contentTypeFilter.value,
  ),
)
const hasActiveFilters = computed(() =>
  Boolean(
    search.value.trim() ||
    gameFilter.value !== 'all' ||
    contentTypeFilter.value !== 'all' ||
    releaseDateFrom.value ||
    releaseDateTo.value ||
    statusFilter.value !== 'all' ||
    hasSelectedFilterValues(selectedProjectFilterValueIds.value) ||
    hasSelectedFilterValues(selectedReleaseFilterValueIds.value),
  ),
)
const filteredProjects = computed(() =>
  projects.value.filter(
    (project) =>
      matchesSearch(project) &&
      matchesGameFilter(project) &&
      matchesContentTypeFilter(project) &&
      matchesReleaseDateFilter(project) &&
      matchesStatusFilter(project) &&
      matchesProjectDimensionFilters(project) &&
      matchesReleaseDimensionFilters(project),
  ),
)
const sortedProjects = computed(() =>
  [...filteredProjects.value].sort((firstProject, secondProject) => {
    if (sort.value === 'title_desc') {
      return secondProject.title.localeCompare(firstProject.title, 'ru-RU')
    }

    if (sort.value === 'released_at') {
      return releaseTimestamp(secondProject) - releaseTimestamp(firstProject)
    }

    return firstProject.title.localeCompare(secondProject.title, 'ru-RU')
  }),
)
const visibleProjects = computed(() => sortedProjects.value.slice(0, pageSize.value))
const subtitle = computed(() => {
  if (hasActiveFilters.value && filteredProjects.value.length !== projects.value.length) {
    return `Всего проектов: ${projects.value.length}. Найдено: ${filteredProjects.value.length}.`
  }

  return `Всего проектов: ${projects.value.length}.`
})
const deleteModalDescription = computed(() => {
  const title = pendingDeleteProject.value?.title ?? 'проект'

  return `Проект «${title}», его релизы и файлы будут удалены без возможности восстановления.`
})

function matchesSearch(project: ProjectListItem): boolean {
  const searchQuery = search.value.trim().toLocaleLowerCase('ru-RU')

  if (!searchQuery) {
    return true
  }

  return project.title.toLocaleLowerCase('ru-RU').includes(searchQuery)
}

function matchesGameFilter(project: ProjectListItem): boolean {
  return gameFilter.value === 'all' || String(project.game_id) === gameFilter.value
}

function matchesContentTypeFilter(project: ProjectListItem): boolean {
  return (
    contentTypeFilter.value === 'all' ||
    String(project.game_content_type_id) === contentTypeFilter.value
  )
}

function matchesReleaseDateFilter(project: ProjectListItem): boolean {
  if (!releaseDateFrom.value && !releaseDateTo.value) {
    return true
  }

  if (!project.released_at) {
    return false
  }

  const releaseDate = new Date(project.released_at)
  releaseDate.setHours(0, 0, 0, 0)

  if (releaseDateFrom.value) {
    const dateFrom = new Date(releaseDateFrom.value)
    dateFrom.setHours(0, 0, 0, 0)

    if (releaseDate < dateFrom) {
      return false
    }
  }

  if (releaseDateTo.value) {
    const dateTo = new Date(releaseDateTo.value)
    dateTo.setHours(0, 0, 0, 0)

    if (releaseDate > dateTo) {
      return false
    }
  }

  return true
}

function matchesStatusFilter(project: ProjectListItem): boolean {
  return statusFilter.value === 'all' || project.status === statusFilter.value
}

function matchesProjectDimensionFilters(project: ProjectListItem): boolean {
  const projectValueIds = new Set(project.dimension_value_ids ?? [])

  return Object.values(selectedProjectFilterValueIds.value).every((valueIds) => {
    if (valueIds.length === 0) {
      return true
    }

    return valueIds.some((valueId) => projectValueIds.has(valueId))
  })
}

function matchesReleaseDimensionFilters(project: ProjectListItem): boolean {
  const releaseValueIds = new Set(projectReleaseDimensionValueIds.value[project.id] ?? [])

  return Object.values(selectedReleaseFilterValueIds.value).every((valueIds) => {
    if (valueIds.length === 0) {
      return true
    }

    return valueIds.some((valueId) => releaseValueIds.has(valueId))
  })
}

function hasSelectedFilterValues(filtersState: Record<number, number[]>): boolean {
  return Object.values(filtersState).some((valueIds) => valueIds.length > 0)
}

function sidebarFilterSelectedValueCount(filterId: number, target: 'project' | 'release'): number {
  const filtersState =
    target === 'project' ? selectedProjectFilterValueIds.value : selectedReleaseFilterValueIds.value

  return filtersState[filterId]?.length ?? 0
}

function toggleSidebarFilterValue(
  filterId: number,
  valueId: number,
  target: 'project' | 'release',
): void {
  const filtersState =
    target === 'project' ? selectedProjectFilterValueIds.value : selectedReleaseFilterValueIds.value
  const selectedValueIds = filtersState[filterId] ?? []

  filtersState[filterId] = selectedValueIds.includes(valueId)
    ? selectedValueIds.filter((selectedValueId) => selectedValueId !== valueId)
    : [...selectedValueIds, valueId]
}

function sidebarFilterValueIsSelected(
  filterId: number,
  valueId: number,
  target: 'project' | 'release',
): boolean {
  const filtersState =
    target === 'project' ? selectedProjectFilterValueIds.value : selectedReleaseFilterValueIds.value

  return filtersState[filterId]?.includes(valueId) ?? false
}

function sidebarRootValues(filter: GameDimension): GameDimensionValue[] {
  return filter.values
    .filter((value) => value.is_active && value.parent_id === null)
    .sort(compareDimensionValueOrder)
}

function sidebarChildValues(
  filter: GameDimension,
  parentValue: GameDimensionValue,
): GameDimensionValue[] {
  return filter.values
    .filter((value) => value.is_active && value.parent_id === parentValue.id)
    .sort(compareDimensionValueOrder)
}

function sidebarValueHasChildren(filter: GameDimension, value: GameDimensionValue): boolean {
  return filter.values.some(
    (childValue) => childValue.is_active && childValue.parent_id === value.id,
  )
}

function compareDimensionValueOrder(
  firstValue: GameDimensionValue,
  secondValue: GameDimensionValue,
): number {
  if (firstValue.sort_order !== secondValue.sort_order) {
    return firstValue.sort_order - secondValue.sort_order
  }

  return firstValue.name.localeCompare(secondValue.name, 'ru-RU')
}

function releaseTimestamp(project: ProjectListItem): number {
  if (!project.released_at) {
    return 0
  }

  return new Date(project.released_at).getTime()
}

function projectSummary(project: ProjectListItem): string {
  return richTextToPlainText(project.summary) || 'Краткое описание пока не добавлено.'
}

function projectTags(project: ProjectListItem): string[] {
  if (!Array.isArray(project.tags)) {
    return []
  }

  return project.tags.filter((tag): tag is string => typeof tag === 'string' && tag.trim() !== '')
}

function projectUpdatedAt(project: ProjectListItem): string {
  if (!project.updated_at) {
    return 'Дата не указана'
  }

  return new Intl.DateTimeFormat('ru-RU', { dateStyle: 'short' }).format(
    new Date(project.updated_at),
  )
}

function projectStatusClass(project: ProjectListItem): string {
  const color = project.status_color ?? 'gray'

  return ['gray', 'warning', 'success', 'danger'].includes(color)
    ? `project-card__status--${color}`
    : 'project-card__status--gray'
}

function projectIsDraft(project: ProjectListItem): boolean {
  return project.status === 'draft'
}

function projectActionRoute(project: ProjectListItem): RouteLocationRaw {
  if (projectIsDraft(project)) {
    return { name: 'projects.continue', params: { id: String(project.id) } }
  }

  return { name: 'projects.edit', params: { id: String(project.id) } }
}

function projectActionLabel(project: ProjectListItem): string {
  return projectIsDraft(project) ? 'Продолжить создание' : 'Редактировать проект'
}

function richTextToPlainText(value: unknown): string {
  if (Array.isArray(value)) {
    return value.map(richTextToPlainText).filter(Boolean).join(' ')
  }

  if (!value || typeof value !== 'object') {
    return ''
  }

  if ('text' in value && typeof value.text === 'string') {
    return value.text
  }

  return 'content' in value ? richTextToPlainText(value.content) : ''
}

function resetFilters(): void {
  search.value = ''
  gameFilter.value = 'all'
  contentTypeFilter.value = 'all'
  selectedProjectFilterValueIds.value = {}
  selectedReleaseFilterValueIds.value = {}
  releaseDateFrom.value = ''
  releaseDateTo.value = ''
  statusFilter.value = 'all'
}

function toggleDisplayMode(): void {
  displayMode.value = displayMode.value === 'cards' ? 'list' : 'cards'
}

async function loadProjects(): Promise<void> {
  isLoading.value = true
  message.value = ''

  try {
    const [projectsResponse, gamesResponse, contentTypesResponse] = await Promise.all([
      fetchProjects(),
      fetchGames(),
      fetchProjectContentTypes(),
    ])

    projects.value = projectsResponse.data
    games.value = gamesResponse.data
    contentTypeOptions.value = contentTypesResponse.data
    const [dimensionResponses, releaseResponses] = await Promise.all([
      Promise.all(
        contentTypesResponse.data.map((contentType) =>
          fetchGameDimensions(contentType.game_id, contentType.id),
        ),
      ),
      Promise.all(projectsResponse.data.map((project) => fetchProjectReleases(project.id))),
    ])

    dimensions.value = dimensionResponses.flatMap((response) => response.data)
    projectReleaseDimensionValueIds.value = Object.fromEntries(
      projectsResponse.data.map((project, index) => {
        const valueIds = new Set<number>()

        for (const release of releaseResponses[index]?.data ?? []) {
          for (const valueId of release.dimension_value_ids ?? []) {
            valueIds.add(valueId)
          }
        }

        return [project.id, [...valueIds]]
      }),
    )
  } catch {
    message.value = 'Не удалось загрузить проекты.'
  } finally {
    isLoading.value = false
  }
}

function openDeleteModal(project: ProjectListItem): void {
  pendingDeleteProject.value = project
}

function closeDeleteModal(): void {
  if (deletingProjectId.value !== null) {
    return
  }

  pendingDeleteProject.value = null
}

async function confirmDelete(): Promise<void> {
  if (!pendingDeleteProject.value) {
    return
  }

  const project = pendingDeleteProject.value

  deletingProjectId.value = project.id
  message.value = ''

  try {
    await removeProject(project.id)
    projects.value = projects.value.filter(({ id }) => id !== project.id)
  } catch {
    message.value = 'Не удалось удалить проект.'
  } finally {
    deletingProjectId.value = null
    pendingDeleteProject.value = null
  }
}

onMounted(() => {
  void loadProjects()
})

watch(gameFilter, () => {
  contentTypeFilter.value = 'all'
})

watch(contentTypeFilter, () => {
  selectedProjectFilterValueIds.value = {}
  selectedReleaseFilterValueIds.value = {}
})
</script>

<template>
  <AppShell>
    <section class="data-page">
      <header class="data-page__header">
        <h2 class="data-page__title">Проекты</h2>
        <p class="data-page__subtitle">{{ subtitle }}</p>
      </header>

      <p v-if="message" class="data-page__message">{{ message }}</p>

      <ProjectIndexPanel
        :projects="projects"
        :games="games"
        :content-type-options="contentTypeOptions"
        :dimensions="dimensions"
        :release-dimension-value-ids="projectReleaseDimensionValueIds"
        :loading="isLoading"
        :deleting-project-id="deletingProjectId"
        show-add-card
        enable-delete
        @delete="openDeleteModal"
      />
    </section>

    <DeleteModal
      :open="pendingDeleteProject !== null"
      title="Удалить проект"
      :description="deleteModalDescription"
      :loading="deletingProjectId !== null"
      @cancel="closeDeleteModal"
      @confirm="confirmDelete"
    />
  </AppShell>
</template>
