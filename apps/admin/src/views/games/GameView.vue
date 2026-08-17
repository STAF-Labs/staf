<script setup lang="ts">
import {
  ArrowLeft,
  Calendar,
  Check,
  ChevronDown,
  ChevronRight,
  Copy,
  GripVertical,
  Image,
  Info,
  MoreHorizontal,
  Pencil,
  PlayCircle,
  Plus,
  RotateCcw,
  Settings2,
  SlidersHorizontal,
  Tags,
  Trash2,
  X,
} from '@lucide/vue'
import { AxiosError } from 'axios'
import Sortable, { type SortableEvent } from 'sortablejs'
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { useRoute, useRouter, type RouteLocationRaw } from 'vue-router'
import AppShell from '@/components/layout/AppShell.vue'
import DeleteModal from '@/components/ui/DeleteModal.vue'
import RichTextRenderer from '@/components/ui/RichTextRenderer.vue'
import SearchField from '@/components/ui/SearchField.vue'
import StepIndicator from '@/components/ui/StepIndicator.vue'
import { fetchContentTypes, type ContentTypeListItem } from '@/shared/content-types/content-types'
import {
  attachGameContentType,
  copyGameDimensions,
  createGameDimension,
  createGameDimensionValue,
  deleteGame as removeGame,
  deleteGameDimension,
  deleteGameDimensionValue,
  detachGameContentType,
  fetchGame,
  fetchGameContentTypes,
  fetchGameDimensions,
  updateGameDimension,
  updateGameDimensionValue,
  type GameContentTypeListItem,
  type GameDetail,
  type GameDimension,
  type GameDimensionValue,
} from '@/shared/games/games'
import { pushGameActionNotification } from '@/shared/games/notifications'
import {
  deleteProject as removeProject,
  fetchProjectReleases,
  fetchProjects,
  type ProjectListItem,
  type ProjectStatus,
} from '@/shared/projects/projects'
import { pushProjectActionNotification } from '@/shared/projects/notifications'
import '@/assets/styles/game-view.css'

type ProjectStatusFilter = ProjectStatus | 'all'
type ProjectSortOption = 'title_asc' | 'title_desc' | 'released_at'
type ProjectDisplayMode = 'cards' | 'list'

const route = useRoute()
const router = useRouter()
const copyFilterSteps = ['Тип контента', 'Фильтры'] as const
const projectStatusFilterOptions: Array<{ value: ProjectStatusFilter; label: string }> = [
  { value: 'all', label: 'Все статусы' },
  { value: 'draft', label: 'Черновик' },
  { value: 'on_moderation', label: 'На модерации' },
  { value: 'published', label: 'Опубликован' },
  { value: 'rejected', label: 'Отклонён' },
]
const projectSortOptions: Array<{ value: ProjectSortOption; label: string }> = [
  { value: 'title_asc', label: 'А-Я' },
  { value: 'title_desc', label: 'Я-А' },
  { value: 'released_at', label: 'Дата релиза' },
]
const projectPageSizeOptions = [10, 20, 30, 40, 50]
const game = ref<GameDetail | null>(null)
const projects = ref<ProjectListItem[]>([])
const contentTypes = ref<ContentTypeListItem[]>([])
const gameContentTypes = ref<GameContentTypeListItem[]>([])
const connectedContentTypeSearch = ref('')
const contentTypeModalSearch = ref('')
const selectedContentTypeIds = ref<number[]>([])
const isLoading = ref(false)
const isAttachingContentType = ref(false)
const isContentTypeModalOpen = ref(false)
const actionGameContentTypeId = ref<number | null>(null)
const activeFiltersGameContentTypeId = ref<number | null>(null)
const isFilterDraftOpen = ref(false)
const filterDraftName = ref('')
const filterDraftMode = ref<'single' | 'multiple'>('single')
const filterDraftIsFilterable = ref(true)
const activeLocalFilterId = ref<number | null>(null)
const filterValueDraftName = ref('')
const editingFilterValueId = ref<number | null>(null)
const editingFilterValueName = ref('')
const editingFilterId = ref<number | null>(null)
const editingFilterName = ref('')
const editingFilterMode = ref<'single' | 'multiple'>('single')
const editingFilterIsFilterable = ref(true)
const filters = ref<GameDimension[]>([])
const loadedFilterContextIds = ref<number[]>([])
const isFiltersLoading = ref(false)
const isFilterSaving = ref(false)
const pendingFilterId = ref<number | null>(null)
const isFilterValueSaving = ref(false)
const pendingFilterValueId = ref<number | null>(null)
const filterValueList = ref<HTMLElement | null>(null)
let filterValueSortable: Sortable | null = null
const isCopyFiltersModalOpen = ref(false)
const copyFiltersStep = ref<1 | 2>(1)
const copySourceGameContentTypeId = ref<number | null>(null)
const copySourceFilters = ref<GameDimension[]>([])
const selectedCopyFilterIds = ref<number[]>([])
const isCopyFiltersLoading = ref(false)
const isCopyingFilters = ref(false)
const message = ref('')
const activeTab = ref<'main' | 'filters' | 'projects'>('main')
const isMoreOpen = ref(false)
const isDeleteModalOpen = ref(false)
const isDeletingGame = ref(false)
const deleteGameError = ref('')
const projectSearch = ref('')
const projectReleaseDateFrom = ref('')
const projectReleaseDateTo = ref('')
const projectStatusFilter = ref<ProjectStatusFilter>('all')
const projectContentTypeFilter = ref('all')
const selectedProjectFilterValueIds = ref<Record<number, number[]>>({})
const selectedReleaseFilterValueIds = ref<Record<number, number[]>>({})
const projectReleaseDimensionValueIds = ref<Record<number, number[]>>({})
const projectSort = ref<ProjectSortOption>('title_asc')
const projectPageSize = ref(20)
const projectDisplayMode = ref<ProjectDisplayMode>('cards')
const deletingProjectId = ref<number | null>(null)
const pendingDeleteProject = ref<ProjectListItem | null>(null)
const tabs = [
  { value: 'main', label: 'Основной' },
  { value: 'filters', label: 'Фильтры' },
  { value: 'projects', label: 'Проекты' },
] as const

const title = computed(() => game.value?.name ?? 'Игра')
const statusClass = computed(() => `game-view-status--${game.value?.status_color ?? 'gray'}`)
const deleteModalDescription = computed(() => {
  const name = game.value?.name ?? 'игра'

  return `Игра ${name} будет удалена. Это действие скроет ее из списка.`
})
const gameProjects = computed(() => {
  if (!game.value) {
    return []
  }

  return projects.value.filter((project) => project.game_id === game.value?.id)
})
const projectContentTypeOptions = computed(() =>
  gameContentTypes.value
    .map((gameContentType) => ({
      value: String(gameContentType.id),
      label: gameContentType.content_type_name ?? 'Тип контента',
    }))
    .sort((firstOption, secondOption) =>
      firstOption.label.localeCompare(secondOption.label, 'ru-RU'),
    ),
)
const projectFilterDimensions = computed(() =>
  filters.value.filter(
    (filter) =>
      filter.is_active &&
      filter.is_filterable &&
      filter.applies_to === 'project' &&
      (projectContentTypeFilter.value === 'all' ||
        String(filter.game_content_type_id) === projectContentTypeFilter.value),
  ),
)
const releaseFilterDimensions = computed(() =>
  filters.value.filter(
    (filter) =>
      filter.is_active &&
      filter.is_filterable &&
      filter.applies_to === 'release' &&
      (projectContentTypeFilter.value === 'all' ||
        String(filter.game_content_type_id) === projectContentTypeFilter.value),
  ),
)
const hasActiveProjectFilters = computed(() =>
  Boolean(
    projectSearch.value.trim() ||
    projectContentTypeFilter.value !== 'all' ||
    projectReleaseDateFrom.value ||
    projectReleaseDateTo.value ||
    projectStatusFilter.value !== 'all' ||
    hasSelectedFilterValues(selectedProjectFilterValueIds.value) ||
    hasSelectedFilterValues(selectedReleaseFilterValueIds.value),
  ),
)
const filteredProjects = computed(() =>
  gameProjects.value.filter(
    (project) =>
      matchesProjectSearch(project) &&
      matchesProjectContentTypeFilter(project) &&
      matchesProjectReleaseDateFilter(project) &&
      matchesProjectStatusFilter(project) &&
      matchesProjectDimensionFilters(project) &&
      matchesReleaseDimensionFilters(project),
  ),
)
const sortedProjects = computed(() =>
  [...filteredProjects.value].sort((firstProject, secondProject) => {
    if (projectSort.value === 'title_desc') {
      return secondProject.title.localeCompare(firstProject.title, 'ru-RU')
    }

    if (projectSort.value === 'released_at') {
      return projectReleaseTimestamp(secondProject) - projectReleaseTimestamp(firstProject)
    }

    return firstProject.title.localeCompare(secondProject.title, 'ru-RU')
  }),
)
const visibleProjects = computed(() => sortedProjects.value.slice(0, projectPageSize.value))
const projectsSubtitle = computed(() => {
  if (
    hasActiveProjectFilters.value &&
    filteredProjects.value.length !== gameProjects.value.length
  ) {
    return `Всего проектов: ${gameProjects.value.length}. Найдено: ${filteredProjects.value.length}.`
  }

  return `Всего проектов: ${gameProjects.value.length}.`
})
const projectDeleteModalDescription = computed(() => {
  const title = pendingDeleteProject.value?.title ?? 'проект'

  return `Проект «${title}», его релизы и файлы будут удалены без возможности восстановления.`
})
const availableContentTypes = computed(() => {
  const attachedIds = new Set(gameContentTypes.value.map((item) => item.content_type_id))

  return contentTypes.value.filter((contentType) => !attachedIds.has(contentType.id))
})
const filteredGameContentTypes = computed(() => {
  const query = connectedContentTypeSearch.value.trim().toLocaleLowerCase('ru-RU')

  if (!query) {
    return gameContentTypes.value
  }

  return gameContentTypes.value.filter((gameContentType) =>
    (gameContentType.content_type_name ?? '').toLocaleLowerCase('ru-RU').includes(query),
  )
})
const filteredAvailableContentTypes = computed(() => {
  const query = contentTypeModalSearch.value.trim().toLocaleLowerCase('ru-RU')

  if (!query) {
    return availableContentTypes.value
  }

  return availableContentTypes.value.filter((contentType) => {
    return (
      contentType.name.toLocaleLowerCase('ru-RU').includes(query) ||
      contentType.slug.toLocaleLowerCase('ru-RU').includes(query)
    )
  })
})
const selectedContentTypes = computed(() => {
  const selectedIds = new Set(selectedContentTypeIds.value)

  return contentTypes.value.filter((contentType) => selectedIds.has(contentType.id))
})
const contentTypesSummary = computed(() => {
  const count = gameContentTypes.value.length

  if (count === 0) {
    return 'Типы контента ещё не подключены'
  }

  return `Подключено: ${count}`
})
const activeFiltersGameContentType = computed(() => {
  if (activeFiltersGameContentTypeId.value === null) {
    return null
  }

  return (
    gameContentTypes.value.find((item) => item.id === activeFiltersGameContentTypeId.value) ?? null
  )
})
const activeLocalFilters = computed(() => {
  if (activeFiltersGameContentTypeId.value === null) {
    return []
  }

  return filters.value.filter(
    (filter) => filter.game_content_type_id === activeFiltersGameContentTypeId.value,
  )
})
const activeLocalFilter = computed(() => {
  if (activeLocalFilterId.value === null) {
    return null
  }

  return activeLocalFilters.value.find((filter) => filter.id === activeLocalFilterId.value) ?? null
})
const activeLocalFilterValues = computed(() =>
  activeLocalFilter.value ? orderDimensionValuesHierarchically(activeLocalFilter.value.values) : [],
)
const canAddFilterValue = computed(() => {
  if (!activeLocalFilter.value) {
    return false
  }

  const name = filterValueDraftName.value.trim().toLocaleLowerCase('ru-RU')

  return (
    name !== '' &&
    !activeLocalFilter.value.values.some((value) => value.name.toLocaleLowerCase('ru-RU') === name)
  )
})
const copySourceOptions = computed(() =>
  gameContentTypes.value.filter((item) => item.id !== activeFiltersGameContentTypeId.value),
)
const copySourceGameContentType = computed(
  () =>
    copySourceOptions.value.find((item) => item.id === copySourceGameContentTypeId.value) ?? null,
)

watch([activeLocalFilterId, () => activeLocalFilter.value?.values.length ?? 0], async () => {
  await nextTick()
  initializeFilterValueSortable()
})

watch(pendingFilterValueId, (valueId) => {
  filterValueSortable?.option('disabled', valueId !== null)
})

watch(projectContentTypeFilter, () => {
  selectedProjectFilterValueIds.value = {}
  selectedReleaseFilterValueIds.value = {}
})

function formatDate(value: string | null): string {
  if (!value) {
    return 'Не указана'
  }

  return new Intl.DateTimeFormat('ru-RU', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  }).format(new Date(value))
}

function toggleMoreMenu(): void {
  isMoreOpen.value = !isMoreOpen.value
}

async function copyGameLink(): Promise<void> {
  await navigator.clipboard?.writeText(window.location.href)
  isMoreOpen.value = false
  message.value = 'Ссылка на игру скопирована.'
}

function openGameDeleteModal(): void {
  deleteGameError.value = ''
  isDeleteModalOpen.value = true
  isMoreOpen.value = false
}

function closeGameDeleteModal(): void {
  if (isDeletingGame.value) {
    return
  }

  isDeleteModalOpen.value = false
}

async function confirmDeleteGame(): Promise<void> {
  if (!game.value) {
    return
  }

  isDeletingGame.value = true
  deleteGameError.value = ''

  try {
    await removeGame(game.value.id)
    pushGameActionNotification('deleted')
    await router.push({ name: 'games.index' })
  } catch {
    deleteGameError.value = 'Не удалось удалить игру.'
  } finally {
    isDeletingGame.value = false
    isDeleteModalOpen.value = false
  }
}

function matchesProjectSearch(project: ProjectListItem): boolean {
  const searchQuery = projectSearch.value.trim().toLocaleLowerCase('ru-RU')

  if (!searchQuery) {
    return true
  }

  return project.title.toLocaleLowerCase('ru-RU').includes(searchQuery)
}

function matchesProjectContentTypeFilter(project: ProjectListItem): boolean {
  return (
    projectContentTypeFilter.value === 'all' ||
    String(project.game_content_type_id) === projectContentTypeFilter.value
  )
}

function matchesProjectReleaseDateFilter(project: ProjectListItem): boolean {
  if (!projectReleaseDateFrom.value && !projectReleaseDateTo.value) {
    return true
  }

  if (!project.released_at) {
    return false
  }

  const releaseDate = new Date(project.released_at)
  releaseDate.setHours(0, 0, 0, 0)

  if (projectReleaseDateFrom.value) {
    const dateFrom = new Date(projectReleaseDateFrom.value)
    dateFrom.setHours(0, 0, 0, 0)

    if (releaseDate < dateFrom) {
      return false
    }
  }

  if (projectReleaseDateTo.value) {
    const dateTo = new Date(projectReleaseDateTo.value)
    dateTo.setHours(0, 0, 0, 0)

    if (releaseDate > dateTo) {
      return false
    }
  }

  return true
}

function matchesProjectStatusFilter(project: ProjectListItem): boolean {
  return projectStatusFilter.value === 'all' || project.status === projectStatusFilter.value
}

function matchesProjectDimensionFilters(project: ProjectListItem): boolean {
  const projectValueIds = new Set(project.dimension_value_ids ?? [])

  return Object.entries(selectedProjectFilterValueIds.value).every(([, valueIds]) => {
    if (valueIds.length === 0) {
      return true
    }

    return valueIds.some((valueId) => projectValueIds.has(valueId))
  })
}

function matchesReleaseDimensionFilters(project: ProjectListItem): boolean {
  const releaseValueIds = new Set(projectReleaseDimensionValueIds.value[project.id] ?? [])

  return Object.entries(selectedReleaseFilterValueIds.value).every(([, valueIds]) => {
    if (valueIds.length === 0) {
      return true
    }

    return valueIds.some((valueId) => releaseValueIds.has(valueId))
  })
}

function hasSelectedFilterValues(filtersState: Record<number, number[]>): boolean {
  return Object.values(filtersState).some((valueIds) => valueIds.length > 0)
}

function projectSidebarFilterSelectedValueCount(
  filterId: number,
  target: 'project' | 'release',
): number {
  const filtersState =
    target === 'project' ? selectedProjectFilterValueIds.value : selectedReleaseFilterValueIds.value

  return filtersState[filterId]?.length ?? 0
}

function toggleProjectSidebarFilterValue(
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

function projectSidebarFilterValueIsSelected(
  filterId: number,
  valueId: number,
  target: 'project' | 'release',
): boolean {
  const filtersState =
    target === 'project' ? selectedProjectFilterValueIds.value : selectedReleaseFilterValueIds.value

  return filtersState[filterId]?.includes(valueId) ?? false
}

function projectSidebarRootValues(filter: GameDimension): GameDimensionValue[] {
  return filter.values
    .filter((value) => value.is_active && value.parent_id === null)
    .sort(compareDimensionValueOrder)
}

function projectSidebarChildValues(
  filter: GameDimension,
  parentValue: GameDimensionValue,
): GameDimensionValue[] {
  return filter.values
    .filter((value) => value.is_active && value.parent_id === parentValue.id)
    .sort(compareDimensionValueOrder)
}

function projectSidebarValueHasChildren(filter: GameDimension, value: GameDimensionValue): boolean {
  return filter.values.some(
    (childValue) => childValue.is_active && childValue.parent_id === value.id,
  )
}

function projectReleaseTimestamp(project: ProjectListItem): number {
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

function resetProjectFilters(): void {
  projectSearch.value = ''
  projectContentTypeFilter.value = 'all'
  projectReleaseDateFrom.value = ''
  projectReleaseDateTo.value = ''
  projectStatusFilter.value = 'all'
  selectedProjectFilterValueIds.value = {}
  selectedReleaseFilterValueIds.value = {}
}

function toggleProjectDisplayMode(): void {
  projectDisplayMode.value = projectDisplayMode.value === 'cards' ? 'list' : 'cards'
}

function openProjectDeleteModal(project: ProjectListItem): void {
  pendingDeleteProject.value = project
}

function closeProjectDeleteModal(): void {
  if (deletingProjectId.value !== null) {
    return
  }

  pendingDeleteProject.value = null
}

async function confirmDeleteProject(): Promise<void> {
  if (!pendingDeleteProject.value) {
    return
  }

  const project = pendingDeleteProject.value

  deletingProjectId.value = project.id
  message.value = ''

  try {
    await removeProject(project.id)
    projects.value = projects.value.filter(({ id }) => id !== project.id)
    pushProjectActionNotification('deleted')
  } catch {
    message.value = 'Не удалось удалить проект.'
  } finally {
    deletingProjectId.value = null
    pendingDeleteProject.value = null
  }
}

async function loadGame(): Promise<void> {
  isLoading.value = true
  message.value = ''

  try {
    const gameId = String(route.params.id)
    const [gameResponse, gameContentTypesResponse, contentTypesResponse, projectsResponse] =
      await Promise.all([
        fetchGame(gameId),
        fetchGameContentTypes(gameId),
        fetchContentTypes(),
        fetchProjects(),
      ])

    game.value = gameResponse
    gameContentTypes.value = gameContentTypesResponse.data
    contentTypes.value = contentTypesResponse.data
    projects.value = projectsResponse.data
    const currentGameProjects = projectsResponse.data.filter(
      (project) => project.game_id === gameResponse.id,
    )
    const [dimensionResponses, releaseResponses] = await Promise.all([
      Promise.all(
        gameContentTypesResponse.data.map((gameContentType) =>
          fetchGameDimensions(gameResponse.id, gameContentType.id),
        ),
      ),
      Promise.all(currentGameProjects.map((project) => fetchProjectReleases(project.id))),
    ])

    filters.value = dimensionResponses.flatMap((response) => response.data)
    loadedFilterContextIds.value = gameContentTypesResponse.data.map(
      (gameContentType) => gameContentType.id,
    )
    projectReleaseDimensionValueIds.value = Object.fromEntries(
      currentGameProjects.map((project, index) => {
        const valueIds = new Set<number>()

        for (const release of releaseResponses[index]?.data ?? []) {
          for (const valueId of release.dimension_value_ids ?? []) {
            valueIds.add(valueId)
          }
        }

        return [project.id, [...valueIds]]
      }),
    )
    route.meta.breadcrumbLabel = game.value.name
  } catch {
    message.value = 'Не удалось загрузить игру.'
  } finally {
    isLoading.value = false
  }
}

function openContentTypeModal(): void {
  selectedContentTypeIds.value = []
  contentTypeModalSearch.value = ''
  isContentTypeModalOpen.value = true
}

function closeContentTypeModal(): void {
  if (isAttachingContentType.value) {
    return
  }

  isContentTypeModalOpen.value = false
}

function toggleContentTypeSelection(contentTypeId: number): void {
  if (selectedContentTypeIds.value.includes(contentTypeId)) {
    selectedContentTypeIds.value = selectedContentTypeIds.value.filter((id) => id !== contentTypeId)

    return
  }

  selectedContentTypeIds.value = [...selectedContentTypeIds.value, contentTypeId]
}

function removeSelectedContentType(contentTypeId: number): void {
  selectedContentTypeIds.value = selectedContentTypeIds.value.filter((id) => id !== contentTypeId)
}

async function openFiltersPanel(gameContentType: GameContentTypeListItem): Promise<void> {
  activeFiltersGameContentTypeId.value = gameContentType.id
  activeLocalFilterId.value = null
  closeFilterDraft()
  cancelEditingFilter()
  resetFilterValueEditor()

  if (!game.value) {
    return
  }

  isFiltersLoading.value = true

  try {
    const response = await fetchGameDimensions(game.value.id, gameContentType.id)
    filters.value = [
      ...filters.value.filter((filter) => filter.game_content_type_id !== gameContentType.id),
      ...response.data,
    ]
    loadedFilterContextIds.value = [
      ...new Set([...loadedFilterContextIds.value, gameContentType.id]),
    ]
    if (activeFiltersGameContentTypeId.value === gameContentType.id) {
      activeLocalFilterId.value = response.data[0]?.id ?? null
    }
  } catch (error) {
    message.value = apiErrorMessage(error, 'Не удалось загрузить фильтры.')
  } finally {
    if (activeFiltersGameContentTypeId.value === gameContentType.id) {
      isFiltersLoading.value = false
    }
  }
}

function closeFiltersPanel(): void {
  activeFiltersGameContentTypeId.value = null
  activeLocalFilterId.value = null
  closeFilterDraft()
  cancelEditingFilter()
  resetFilterValueEditor()
}

function openFilterDraft(): void {
  filterDraftName.value = ''
  filterDraftMode.value = 'single'
  filterDraftIsFilterable.value = true
  isFilterDraftOpen.value = true
}

function closeFilterDraft(): void {
  isFilterDraftOpen.value = false
  filterDraftName.value = ''
}

async function saveFilterDraft(): Promise<void> {
  if (
    !game.value ||
    activeFiltersGameContentTypeId.value === null ||
    filterDraftName.value.trim() === '' ||
    isFilterSaving.value
  ) {
    return
  }

  isFilterSaving.value = true

  try {
    const filter = await createGameDimension(game.value.id, activeFiltersGameContentTypeId.value, {
      name: filterDraftName.value.trim(),
      selection_mode: filterDraftMode.value,
      is_filterable: filterDraftIsFilterable.value,
    })
    filters.value = [...filters.value, filter]
    activeLocalFilterId.value = filter.id
    closeFilterDraft()
  } catch (error) {
    message.value = apiErrorMessage(error, 'Не удалось создать фильтр.')
  } finally {
    isFilterSaving.value = false
  }
}

function selectLocalFilter(filterId: number): void {
  activeLocalFilterId.value = filterId
  cancelEditingFilter()
  resetFilterValueEditor()
}

function startEditingFilter(filter: GameDimension): void {
  activeLocalFilterId.value = filter.id
  editingFilterId.value = filter.id
  editingFilterName.value = filter.name
  editingFilterMode.value = filter.selection_mode
  editingFilterIsFilterable.value = filter.is_filterable
}

function cancelEditingFilter(): void {
  editingFilterId.value = null
  editingFilterName.value = ''
}

async function saveFilter(): Promise<void> {
  if (
    !game.value ||
    !activeFiltersGameContentType.value ||
    editingFilterId.value === null ||
    editingFilterName.value.trim() === '' ||
    isFilterSaving.value
  ) {
    return
  }

  isFilterSaving.value = true

  try {
    const filter = await updateGameDimension(
      game.value.id,
      activeFiltersGameContentType.value.id,
      editingFilterId.value,
      {
        name: editingFilterName.value.trim(),
        selection_mode: editingFilterMode.value,
        is_filterable: editingFilterIsFilterable.value,
      },
    )
    replaceFilter(filter)
    cancelEditingFilter()
  } catch (error) {
    message.value = apiErrorMessage(error, 'Не удалось обновить фильтр.')
  } finally {
    isFilterSaving.value = false
  }
}

async function removeFilter(filter: GameDimension): Promise<void> {
  if (
    !game.value ||
    !activeFiltersGameContentType.value ||
    pendingFilterId.value !== null ||
    !window.confirm(`Удалить фильтр «${filter.name}» вместе со всеми значениями?`)
  ) {
    return
  }

  pendingFilterId.value = filter.id

  try {
    await deleteGameDimension(game.value.id, activeFiltersGameContentType.value.id, filter.id)
    filters.value = filters.value.filter((item) => item.id !== filter.id)
    activeLocalFilterId.value = activeLocalFilters.value[0]?.id ?? null
    cancelEditingFilter()
    resetFilterValueEditor()
  } catch (error) {
    message.value = apiErrorMessage(error, 'Не удалось удалить фильтр.')
  } finally {
    pendingFilterId.value = null
  }
}

async function addFilterValue(): Promise<void> {
  if (
    !game.value ||
    !activeFiltersGameContentType.value ||
    !activeLocalFilter.value ||
    !canAddFilterValue.value ||
    isFilterValueSaving.value
  ) {
    return
  }

  isFilterValueSaving.value = true

  try {
    const value = await createGameDimensionValue(
      game.value.id,
      activeFiltersGameContentType.value.id,
      activeLocalFilter.value.id,
      filterValueDraftName.value.trim(),
    )
    updateActiveLocalFilter((filter) => ({ ...filter, values: [...filter.values, value] }))
    filterValueDraftName.value = ''
  } catch (error) {
    message.value = apiErrorMessage(error, 'Не удалось добавить значение.')
  } finally {
    isFilterValueSaving.value = false
  }
}

function startEditingFilterValue(value: GameDimensionValue): void {
  editingFilterValueId.value = value.id
  editingFilterValueName.value = value.name
}

async function saveFilterValueName(): Promise<void> {
  const name = editingFilterValueName.value.trim()

  if (!activeLocalFilter.value || editingFilterValueId.value === null || name === '') {
    return
  }

  const normalizedName = name.toLocaleLowerCase('ru-RU')
  const duplicate = activeLocalFilter.value.values.some(
    (value) =>
      value.id !== editingFilterValueId.value &&
      value.name.toLocaleLowerCase('ru-RU') === normalizedName,
  )

  if (duplicate) {
    return
  }

  if (!game.value || !activeFiltersGameContentType.value || isFilterValueSaving.value) {
    return
  }

  isFilterValueSaving.value = true

  try {
    const value = await updateGameDimensionValue(
      game.value.id,
      activeFiltersGameContentType.value.id,
      activeLocalFilter.value.id,
      editingFilterValueId.value,
      { name },
    )
    replaceActiveFilterValue(value)
    cancelEditingFilterValue()
  } catch (error) {
    message.value = apiErrorMessage(error, 'Не удалось переименовать значение.')
  } finally {
    isFilterValueSaving.value = false
  }
}

function cancelEditingFilterValue(): void {
  editingFilterValueId.value = null
  editingFilterValueName.value = ''
}

async function toggleFilterValue(value: GameDimensionValue): Promise<void> {
  if (!game.value || !activeFiltersGameContentType.value || !activeLocalFilter.value) {
    return
  }

  pendingFilterValueId.value = value.id

  try {
    const updatedValue = await updateGameDimensionValue(
      game.value.id,
      activeFiltersGameContentType.value.id,
      activeLocalFilter.value.id,
      value.id,
      { is_active: !value.is_active },
    )
    replaceActiveFilterValue(updatedValue)
  } catch (error) {
    message.value = apiErrorMessage(error, 'Не удалось изменить активность значения.')
  } finally {
    pendingFilterValueId.value = null
  }
}

async function removeFilterValue(value: GameDimensionValue): Promise<void> {
  if (
    !game.value ||
    !activeFiltersGameContentType.value ||
    !activeLocalFilter.value ||
    !window.confirm(`Удалить значение «${value.name}»?`)
  ) {
    return
  }

  pendingFilterValueId.value = value.id

  try {
    await deleteGameDimensionValue(
      game.value.id,
      activeFiltersGameContentType.value.id,
      activeLocalFilter.value.id,
      value.id,
    )
    updateActiveLocalFilter((filter) => ({
      ...filter,
      values: filter.values.filter((item) => item.id !== value.id),
    }))
  } catch (error) {
    message.value = apiErrorMessage(error, 'Не удалось удалить значение.')
  } finally {
    pendingFilterValueId.value = null
  }

  if (editingFilterValueId.value === value.id) {
    cancelEditingFilterValue()
  }
}

function initializeFilterValueSortable(): void {
  filterValueSortable?.destroy()
  filterValueSortable = null

  if (!filterValueList.value) {
    return
  }

  filterValueSortable = Sortable.create(filterValueList.value, {
    animation: 160,
    handle: '.game-content-types-panel__value-handle',
    draggable: '.game-content-types-panel__value',
    ghostClass: 'game-content-types-panel__value--ghost',
    chosenClass: 'game-content-types-panel__value--chosen',
    dragClass: 'game-content-types-panel__value--dragging',
    onMove: (event) =>
      canMoveFilterValue(event.dragged, event.related, event.willInsertAfter ?? false),
    onEnd: (event: SortableEvent) => {
      if (event.oldIndex === undefined || event.newIndex === undefined) {
        return
      }

      void reorderFilterValues(event.oldIndex, event.newIndex)
    },
  })
}

async function reorderFilterValues(oldIndex: number, newIndex: number): Promise<void> {
  if (!game.value || !activeFiltersGameContentType.value || !activeLocalFilter.value) {
    return
  }

  if (oldIndex === newIndex) {
    return
  }

  const originalValues = [...activeLocalFilter.value.values]
  const visibleValues = orderDimensionValuesHierarchically(originalValues)
  const values = [...visibleValues]
  const [value] = values.splice(oldIndex, 1)

  if (!value || newIndex < 0 || newIndex > values.length) {
    return
  }

  values.splice(newIndex, 0, value)
  const reorderedSiblingIds = values
    .filter((item) => item.parent_id === value.parent_id)
    .map((item) => item.id)
  const reorderedValues = orderDimensionValuesHierarchically(
    originalValues.map((item) => {
      if (item.parent_id !== value.parent_id) {
        return item
      }

      return { ...item, sort_order: reorderedSiblingIds.indexOf(item.id) }
    }),
  )
  const changedValues = reorderedValues.filter((item) => {
    const originalValue = originalValues.find((originalItem) => originalItem.id === item.id)

    return originalValue && originalValue.sort_order !== item.sort_order
  })

  pendingFilterValueId.value = value.id
  updateActiveLocalFilter((filter) => ({ ...filter, values: reorderedValues }))

  try {
    await Promise.all(
      changedValues.map((item) =>
        updateGameDimensionValue(
          game.value!.id,
          activeFiltersGameContentType.value!.id,
          activeLocalFilter.value!.id,
          item.id,
          { sort_order: item.sort_order },
        ),
      ),
    )
  } catch (error) {
    updateActiveLocalFilter((filter) => ({ ...filter, values: originalValues }))
    message.value = apiErrorMessage(error, 'Не удалось изменить порядок значений.')
  } finally {
    pendingFilterValueId.value = null
  }
}

function orderDimensionValuesHierarchically(values: GameDimensionValue[]): GameDimensionValue[] {
  const valuesByParentId = new Map<number | null, GameDimensionValue[]>()

  for (const value of values) {
    const siblings = valuesByParentId.get(value.parent_id) ?? []

    siblings.push(value)
    valuesByParentId.set(value.parent_id, siblings)
  }

  for (const siblings of valuesByParentId.values()) {
    siblings.sort(compareDimensionValueOrder)
  }

  const orderedValues: GameDimensionValue[] = []
  const appendValues = (parentId: number | null): void => {
    for (const value of valuesByParentId.get(parentId) ?? []) {
      orderedValues.push(value)
      appendValues(value.id)
    }
  }

  appendValues(null)

  return orderedValues
}

function compareDimensionValueOrder(
  firstValue: GameDimensionValue,
  secondValue: GameDimensionValue,
): number {
  if (firstValue.sort_order !== secondValue.sort_order) {
    return firstValue.sort_order - secondValue.sort_order
  }

  return firstValue.id - secondValue.id
}

function filterValueDepth(value: GameDimensionValue): number {
  if (!activeLocalFilter.value) {
    return 0
  }

  const valuesById = new Map(activeLocalFilter.value.values.map((item) => [item.id, item]))
  let depth = 0
  let parentId = value.parent_id

  while (parentId !== null) {
    const parent = valuesById.get(parentId)

    if (!parent) {
      break
    }

    depth += 1
    parentId = parent.parent_id
  }

  return depth
}

function canMoveFilterValue(
  draggedElement: HTMLElement,
  relatedElement: HTMLElement | null,
  willInsertAfter: boolean,
): boolean {
  if (!activeLocalFilter.value || !relatedElement) {
    return false
  }

  const draggedValue = filterValueByElement(draggedElement)
  const relatedValue = filterValueByElement(relatedElement)

  if (!draggedValue || !relatedValue) {
    return false
  }

  if (draggedValue.parent_id === relatedValue.parent_id) {
    return true
  }

  const siblingElement = willInsertAfter
    ? relatedElement.nextElementSibling
    : relatedElement.previousElementSibling
  const siblingValue =
    siblingElement instanceof HTMLElement ? filterValueByElement(siblingElement) : null

  return Boolean(siblingValue && siblingValue.parent_id === draggedValue.parent_id)
}

function filterValueByElement(element: HTMLElement): GameDimensionValue | null {
  const valueId = Number(element.dataset.valueId)

  if (!Number.isFinite(valueId) || !activeLocalFilter.value) {
    return null
  }

  return activeLocalFilter.value.values.find((value) => value.id === valueId) ?? null
}

function updateActiveLocalFilter(update: (filter: GameDimension) => GameDimension): void {
  if (activeLocalFilterId.value === null) {
    return
  }

  const filterId = activeLocalFilterId.value
  filters.value = filters.value.map((filter) => (filter.id === filterId ? update(filter) : filter))
}

function replaceFilter(updatedFilter: GameDimension): void {
  filters.value = filters.value.map((filter) =>
    filter.id === updatedFilter.id ? updatedFilter : filter,
  )
}

function replaceActiveFilterValue(updatedValue: GameDimensionValue): void {
  updateActiveLocalFilter((filter) => ({
    ...filter,
    values: filter.values.map((value) => (value.id === updatedValue.id ? updatedValue : value)),
  }))
}

function resetFilterValueEditor(): void {
  filterValueDraftName.value = ''
  cancelEditingFilterValue()
}

function filtersCount(gameContentType: GameContentTypeListItem): number {
  if (loadedFilterContextIds.value.includes(gameContentType.id)) {
    return filters.value.filter((filter) => filter.game_content_type_id === gameContentType.id)
      .length
  }

  return gameContentType.filters_count
}

function openCopyFiltersModal(): void {
  copyFiltersStep.value = 1
  copySourceGameContentTypeId.value = null
  copySourceFilters.value = []
  selectedCopyFilterIds.value = []
  isCopyFiltersModalOpen.value = true
}

function closeCopyFiltersModal(): void {
  if (isCopyFiltersLoading.value || isCopyingFilters.value) {
    return
  }

  isCopyFiltersModalOpen.value = false
}

async function continueCopyFilters(): Promise<void> {
  if (!game.value || copySourceGameContentTypeId.value === null || isCopyFiltersLoading.value) {
    return
  }

  isCopyFiltersLoading.value = true

  try {
    const response = await fetchGameDimensions(game.value.id, copySourceGameContentTypeId.value)
    copySourceFilters.value = response.data
    selectedCopyFilterIds.value = []
    copyFiltersStep.value = 2
  } catch (error) {
    message.value = apiErrorMessage(error, 'Не удалось загрузить фильтры источника.')
  } finally {
    isCopyFiltersLoading.value = false
  }
}

function returnToCopySource(): void {
  copyFiltersStep.value = 1
  selectedCopyFilterIds.value = []
}

function toggleCopyFilter(filterId: number): void {
  if (selectedCopyFilterIds.value.includes(filterId)) {
    selectedCopyFilterIds.value = selectedCopyFilterIds.value.filter((id) => id !== filterId)

    return
  }

  selectedCopyFilterIds.value = [...selectedCopyFilterIds.value, filterId]
}

async function submitCopyFilters(): Promise<void> {
  if (
    !game.value ||
    !activeFiltersGameContentType.value ||
    copySourceGameContentTypeId.value === null ||
    selectedCopyFilterIds.value.length === 0 ||
    isCopyingFilters.value
  ) {
    return
  }

  isCopyingFilters.value = true
  const targetGameContentTypeId = activeFiltersGameContentType.value.id

  try {
    const response = await copyGameDimensions(
      game.value.id,
      targetGameContentTypeId,
      copySourceGameContentTypeId.value,
      selectedCopyFilterIds.value,
    )
    filters.value = [
      ...filters.value.filter((filter) => filter.game_content_type_id !== targetGameContentTypeId),
      ...response.data,
    ]
    loadedFilterContextIds.value = [
      ...new Set([...loadedFilterContextIds.value, targetGameContentTypeId]),
    ]

    if (!response.data.some((filter) => filter.id === activeLocalFilterId.value)) {
      activeLocalFilterId.value = response.data[0]?.id ?? null
    }

    isCopyFiltersModalOpen.value = false
  } catch (error) {
    message.value = apiErrorMessage(error, 'Не удалось скопировать фильтры.')
  } finally {
    isCopyingFilters.value = false
  }
}

async function attachSelectedContentTypes(): Promise<void> {
  if (!game.value || selectedContentTypeIds.value.length === 0 || isAttachingContentType.value) {
    return
  }

  isAttachingContentType.value = true
  message.value = ''

  try {
    const attachedContentTypes: GameContentTypeListItem[] = []

    for (const contentTypeId of selectedContentTypeIds.value) {
      attachedContentTypes.push(await attachGameContentType(game.value.id, contentTypeId))
    }

    gameContentTypes.value = [...attachedContentTypes, ...gameContentTypes.value]
    selectedContentTypeIds.value = []
    contentTypeModalSearch.value = ''
    isContentTypeModalOpen.value = false
    message.value =
      attachedContentTypes.length === 1
        ? 'Тип контента подключен к игре.'
        : `Типы контента подключены: ${attachedContentTypes.length}.`
  } catch (error) {
    message.value = apiErrorMessage(error, 'Не удалось подключить типы контента.')
  } finally {
    isAttachingContentType.value = false
  }
}

async function detachContentType(gameContentType: GameContentTypeListItem): Promise<void> {
  if (!game.value || actionGameContentTypeId.value !== null) {
    return
  }

  actionGameContentTypeId.value = gameContentType.id
  message.value = ''

  try {
    await detachGameContentType(game.value.id, gameContentType.id)
    gameContentTypes.value = gameContentTypes.value.filter((item) => item.id !== gameContentType.id)
    if (activeFiltersGameContentTypeId.value === gameContentType.id) {
      closeFiltersPanel()
    }
    message.value = 'Тип контента отключен от игры.'
  } catch (error) {
    message.value = apiErrorMessage(error, 'Не удалось отключить тип контента.')
  } finally {
    actionGameContentTypeId.value = null
  }
}

function apiErrorMessage(error: unknown, fallback: string): string {
  if (error instanceof AxiosError && typeof error.response?.data?.message === 'string') {
    return error.response.data.message
  }

  return fallback
}

onBeforeUnmount(() => {
  filterValueSortable?.destroy()
})

void loadGame()
</script>

<template>
  <AppShell>
    <section class="game-view">
      <p v-if="message" class="data-page__message">{{ message }}</p>
      <div v-if="isLoading" class="game-view__loading">Загрузка...</div>

      <template v-else-if="game">
        <header class="game-view__header">
          <div class="game-view__breadcrumbs">
            <RouterLink :to="{ name: 'games.index' }" aria-label="Назад к играм">
              <ArrowLeft :size="16" aria-hidden="true" />
              <span>Игры</span>
            </RouterLink>
            <span aria-hidden="true">/</span>
            <span>{{ game.name }}</span>
          </div>

          <div class="game-view__heading-row">
            <div>
              <h1>{{ title }}</h1>
              <div class="game-view__meta">
                <span>/{{ game.slug }}</span>
                <span class="game-view-status" :class="statusClass">
                  {{ game.status_label ?? 'Не указан' }}
                </span>
              </div>
            </div>

            <div class="game-view__actions">
              <RouterLink
                class="button button--secondary game-view__edit"
                :to="{ name: 'games.edit', params: { id: String(game.id) } }"
                aria-label="Редактировать игру"
                title="Редактировать игру"
              >
                <Pencil :size="16" aria-hidden="true" />
                Редактировать
              </RouterLink>
              <button
                class="button button--secondary game-view__more"
                type="button"
                :aria-expanded="isMoreOpen"
                aria-haspopup="menu"
                @click="toggleMoreMenu"
              >
                <MoreHorizontal :size="18" aria-hidden="true" />
                <span>Ещё</span>
                <ChevronDown
                  class="game-view__more-chevron"
                  :class="{ 'game-view__more-chevron--open': isMoreOpen }"
                  :size="16"
                  aria-hidden="true"
                />
              </button>
              <div v-if="isMoreOpen" class="game-view__more-menu" role="menu">
                <button type="button" role="menuitem" @click="copyGameLink">
                  <Copy :size="15" aria-hidden="true" />
                  Скопировать ссылку
                </button>
                <button
                  class="game-view__more-menu-danger"
                  type="button"
                  role="menuitem"
                  :disabled="isDeletingGame"
                  @click="openGameDeleteModal"
                >
                  <Trash2 :size="15" aria-hidden="true" />
                  Удалить
                </button>
              </div>
            </div>
          </div>
        </header>
        <p v-if="deleteGameError" class="game-view__action-error">{{ deleteGameError }}</p>

        <div class="project-tabs" role="tablist" aria-label="Разделы игры">
          <button
            v-for="tab in tabs"
            :key="tab.value"
            class="project-tab"
            :class="{ 'project-tab--active': activeTab === tab.value }"
            type="button"
            role="tab"
            :aria-selected="activeTab === tab.value"
            @click="activeTab = tab.value"
          >
            {{ tab.label }}
          </button>
        </div>

        <div class="game-view__banner">
          <img v-if="game.banner_url" :src="game.banner_url" :alt="game.name" />
        </div>

        <article v-if="activeTab === 'main'" class="game-view__body">
          <section class="game-view__summary" aria-label="Основная информация">
            <div class="game-view__logo">
              <img v-if="game.logo_url" :src="game.logo_url" :alt="game.name" />
              <span v-else>{{ game.name.slice(0, 1).toUpperCase() }}</span>
            </div>

            <div class="game-view__facts">
              <div class="game-view__field">
                <span class="game-view__label">Slug</span>
                <span class="game-view__value">/{{ game.slug }}</span>
              </div>

              <div class="game-view__field">
                <span class="game-view__label">Дата релиза</span>
                <span class="game-view__value">{{ formatDate(game.released_at) }}</span>
              </div>

              <div class="game-view__field">
                <span class="game-view__label">Статус</span>
                <span class="game-view-status" :class="statusClass">
                  {{ game.status_label ?? 'Не указан' }}
                </span>
              </div>
            </div>
          </section>

          <section class="game-view__section">
            <h3 class="game-view__section-title">Описание</h3>
            <RichTextRenderer
              class="game-view-description"
              :value="game.description"
              empty-text="Описание игры пока не заполнено."
            />
          </section>
        </article>

        <article v-else-if="activeTab === 'filters'" class="game-view__body">
          <section class="game-view__section game-content-types" aria-label="Типы контента игры">
            <header class="game-content-types__header">
              <div class="game-content-types__heading">
                <span class="game-view__section-title">Типы контента</span>
                <p class="game-content-types__summary">{{ contentTypesSummary }}</p>
              </div>

              <div class="game-content-types__controls">
                <button
                  class="game-content-types__add"
                  type="button"
                  :disabled="availableContentTypes.length === 0 || isAttachingContentType"
                  @click="openContentTypeModal"
                >
                  <Plus :size="17" :stroke-width="2" aria-hidden="true" />
                  <span>Добавить тип</span>
                </button>
              </div>
            </header>

            <SearchField
              v-model="connectedContentTypeSearch"
              class="game-content-types__search"
              placeholder="Поиск по подключённым типам"
              :disabled="gameContentTypes.length === 0"
            />

            <div
              class="game-results-layout game-content-types__settings-layout"
              :class="{
                'game-results-layout--with-panel': activeFiltersGameContentType !== null,
              }"
            >
              <div
                class="game-content-types__list"
                role="table"
                aria-label="Подключённые типы контента"
              >
                <div class="game-content-types__list-head" role="row">
                  <span role="columnheader">Тип контента</span>
                  <span role="columnheader">Проекты</span>
                  <span role="columnheader">Фильтры</span>
                  <span role="columnheader">Действия</span>
                </div>

                <div
                  v-if="gameContentTypes.length === 0"
                  class="game-content-types__empty-row"
                  role="row"
                >
                  <span>
                    Подключите глобальные типы контента к игре, чтобы затем настроить фильтры и
                    разрешить создание проектов в этих категориях.
                  </span>
                </div>

                <div
                  v-else-if="filteredGameContentTypes.length === 0"
                  class="game-content-types__empty-row"
                  role="row"
                >
                  <span>По подключённым типам ничего не найдено.</span>
                </div>

                <template v-else>
                  <div
                    v-for="gameContentType in filteredGameContentTypes"
                    :key="gameContentType.id"
                    class="game-content-types__list-row"
                    :class="{
                      'game-content-types__list-row--active':
                        activeFiltersGameContentTypeId === gameContentType.id,
                    }"
                    role="row"
                  >
                    <div class="game-content-types__type-cell" role="cell">
                      <span class="game-content-types__type-name">
                        {{ gameContentType.content_type_name ?? 'Тип контента' }}
                      </span>
                      <span class="game-content-types__type-slug">
                        {{ gameContentType.content_type_slug ?? 'slug не указан' }}
                      </span>
                    </div>

                    <span role="cell">{{ gameContentType.projects_count }}</span>
                    <span role="cell">{{ filtersCount(gameContentType) }}</span>

                    <div class="game-content-types__actions" role="cell">
                      <button
                        class="game-content-types__action"
                        :class="{
                          'game-content-types__action--active':
                            activeFiltersGameContentTypeId === gameContentType.id,
                        }"
                        type="button"
                        aria-label="Настроить фильтры"
                        title="Настроить фильтры"
                        :aria-pressed="activeFiltersGameContentTypeId === gameContentType.id"
                        @click="openFiltersPanel(gameContentType)"
                      >
                        <Settings2 :size="16" :stroke-width="1.9" aria-hidden="true" />
                      </button>

                      <button
                        class="game-content-types__action game-content-types__action--danger"
                        type="button"
                        :disabled="actionGameContentTypeId === gameContentType.id"
                        aria-label="Отключить тип контента"
                        title="Отключить тип контента"
                        @click="detachContentType(gameContentType)"
                      >
                        <Trash2 :size="16" :stroke-width="1.9" aria-hidden="true" />
                      </button>
                    </div>
                  </div>
                </template>
              </div>

              <aside
                class="game-advanced-panel game-content-types-panel"
                :class="{ 'game-advanced-panel--open': activeFiltersGameContentType !== null }"
                :aria-hidden="activeFiltersGameContentType === null"
                aria-label="Настройки фильтров типа контента"
              >
                <template v-if="activeFiltersGameContentType">
                  <header class="game-content-types-panel__header">
                    <div class="game-content-types-panel__title-group">
                      <span class="game-content-types-panel__eyebrow">Фильтры типа</span>
                      <h3 class="game-content-types-panel__title">
                        {{ activeFiltersGameContentType.content_type_name ?? 'Тип контента' }}
                      </h3>
                      <p class="game-content-types-panel__subtitle">
                        {{ game.name }} ·
                        {{ activeFiltersGameContentType.content_type_slug ?? 'slug не указан' }}
                      </p>
                    </div>

                    <button
                      class="game-content-types-panel__close"
                      type="button"
                      aria-label="Закрыть настройки фильтров"
                      title="Закрыть"
                      @click="closeFiltersPanel"
                    >
                      <X :size="16" :stroke-width="2" aria-hidden="true" />
                    </button>
                  </header>

                  <div class="game-content-types-panel__toolbar">
                    <RouterLink
                      class="game-filter-advanced game-content-types-panel__advanced"
                      :to="{
                        name: 'games.content-types.filters.import',
                        params: {
                          id: game.id,
                          gameContentTypeId: activeFiltersGameContentType.id,
                        },
                      }"
                      title="Расширенные настройки"
                    >
                      <SlidersHorizontal :size="16" :stroke-width="2" aria-hidden="true" />
                      <span>Расширенные настройки</span>
                    </RouterLink>
                    <button
                      class="game-content-types-panel__copy"
                      type="button"
                      :disabled="copySourceOptions.length === 0 || isFiltersLoading"
                      @click="openCopyFiltersModal"
                    >
                      <Copy :size="16" :stroke-width="2" aria-hidden="true" />
                      <span>Копировать из</span>
                    </button>
                    <button
                      class="game-content-types-panel__create"
                      type="button"
                      :disabled="isFiltersLoading || isFilterSaving"
                      @click="openFilterDraft"
                    >
                      <Plus :size="16" :stroke-width="2" aria-hidden="true" />
                      <span>Добавить фильтр</span>
                    </button>
                  </div>

                  <div v-if="isFiltersLoading" class="game-content-types-panel__empty">
                    <SlidersHorizontal :size="26" :stroke-width="1.8" aria-hidden="true" />
                    <div><p>Загружаем фильтры…</p></div>
                  </div>

                  <div
                    v-else-if="activeLocalFilters.length > 0"
                    class="game-content-types-panel__list"
                  >
                    <div
                      v-for="filter in activeLocalFilters"
                      :key="filter.id"
                      class="game-content-types-panel__filter"
                      :class="{
                        'game-content-types-panel__filter--active':
                          activeLocalFilterId === filter.id,
                      }"
                    >
                      <button
                        class="game-content-types-panel__filter-select"
                        type="button"
                        :aria-pressed="activeLocalFilterId === filter.id"
                        @click="selectLocalFilter(filter.id)"
                      >
                        <span class="game-content-types-panel__filter-copy">
                          <span class="game-content-types-panel__filter-name">{{
                            filter.name
                          }}</span>
                          <span class="game-content-types-panel__filter-meta">
                            {{
                              filter.selection_mode === 'single'
                                ? 'Один выбор'
                                : 'Несколько значений'
                            }}
                            · Значений: {{ filter.values.length }}
                          </span>
                        </span>
                        <ChevronRight :size="16" :stroke-width="2" aria-hidden="true" />
                      </button>
                      <div class="game-content-types-panel__filter-actions">
                        <button
                          class="game-content-types-panel__filter-action"
                          type="button"
                          aria-label="Редактировать фильтр"
                          title="Редактировать"
                          @click="startEditingFilter(filter)"
                        >
                          <Pencil :size="15" :stroke-width="2" aria-hidden="true" />
                        </button>
                        <button
                          class="game-content-types-panel__filter-action game-content-types-panel__filter-action--danger"
                          type="button"
                          :disabled="pendingFilterId === filter.id"
                          aria-label="Удалить фильтр"
                          title="Удалить"
                          @click="removeFilter(filter)"
                        >
                          <Trash2 :size="15" :stroke-width="2" aria-hidden="true" />
                        </button>
                      </div>
                    </div>
                  </div>

                  <div v-else-if="!isFilterDraftOpen" class="game-content-types-panel__empty">
                    <SlidersHorizontal :size="26" :stroke-width="1.8" aria-hidden="true" />
                    <div>
                      <p>Фильтры ещё не настроены.</p>
                      <span>
                        Здесь появятся dimensions и значения для выбранной связки игры и типа
                        контента.
                      </span>
                    </div>
                  </div>

                  <form
                    v-if="isFilterDraftOpen"
                    class="game-content-types-panel__preview"
                    aria-label="Новый фильтр"
                    @submit.prevent="saveFilterDraft"
                  >
                    <label class="game-filter-field">
                      <span class="game-filter-field__label">Название фильтра</span>
                      <input
                        v-model="filterDraftName"
                        class="game-filter-field__control"
                        type="text"
                        placeholder="Версия"
                        autofocus
                      />
                    </label>

                    <label class="game-filter-field">
                      <span class="game-filter-field__label">Режим выбора</span>
                      <select v-model="filterDraftMode" class="game-filter-field__control">
                        <option value="single">Один выбор</option>
                        <option value="multiple">Несколько значений</option>
                      </select>
                    </label>

                    <label class="game-content-types-panel__toggle">
                      <input v-model="filterDraftIsFilterable" type="checkbox" />
                      <span>Показывать в каталоге</span>
                    </label>

                    <div class="game-content-types-panel__actions">
                      <button
                        class="game-content-types-panel__secondary"
                        type="button"
                        @click="closeFilterDraft"
                      >
                        Отмена
                      </button>
                      <button
                        class="game-content-types-panel__save"
                        type="submit"
                        :disabled="filterDraftName.trim() === '' || isFilterSaving"
                      >
                        {{ isFilterSaving ? 'Сохраняем…' : 'Сохранить' }}
                      </button>
                    </div>
                  </form>

                  <form
                    v-if="editingFilterId !== null"
                    class="game-content-types-panel__preview game-content-types-panel__filter-edit"
                    aria-label="Редактирование фильтра"
                    @submit.prevent="saveFilter"
                  >
                    <label class="game-filter-field">
                      <span class="game-filter-field__label">Название фильтра</span>
                      <input
                        v-model="editingFilterName"
                        class="game-filter-field__control"
                        type="text"
                        autofocus
                      />
                    </label>
                    <label class="game-filter-field">
                      <span class="game-filter-field__label">Режим выбора</span>
                      <select v-model="editingFilterMode" class="game-filter-field__control">
                        <option value="single">Один выбор</option>
                        <option value="multiple">Несколько значений</option>
                      </select>
                    </label>
                    <label class="game-content-types-panel__toggle">
                      <input v-model="editingFilterIsFilterable" type="checkbox" />
                      <span>Показывать в каталоге</span>
                    </label>
                    <div class="game-content-types-panel__actions">
                      <button
                        class="game-content-types-panel__secondary"
                        type="button"
                        @click="cancelEditingFilter"
                      >
                        Отмена
                      </button>
                      <button
                        class="game-content-types-panel__save"
                        type="submit"
                        :disabled="editingFilterName.trim() === '' || isFilterSaving"
                      >
                        Сохранить
                      </button>
                    </div>
                  </form>

                  <section
                    v-if="activeLocalFilter"
                    class="game-content-types-panel__values"
                    :aria-labelledby="`filter-values-title-${activeLocalFilter.id}`"
                  >
                    <header class="game-content-types-panel__values-header">
                      <div>
                        <span class="game-content-types-panel__values-eyebrow"
                          >Значения фильтра</span
                        >
                        <h4
                          :id="`filter-values-title-${activeLocalFilter.id}`"
                          class="game-content-types-panel__values-title"
                        >
                          {{ activeLocalFilter.name }}
                        </h4>
                      </div>
                      <span class="game-content-types-panel__values-count">
                        {{ activeLocalFilter.values.length }}
                      </span>
                    </header>

                    <form
                      class="game-content-types-panel__value-create"
                      @submit.prevent="addFilterValue"
                    >
                      <label class="game-filter-field game-content-types-panel__value-field">
                        <span class="game-filter-field__label">Новое значение</span>
                        <span class="game-content-types-panel__value-input-row">
                          <input
                            v-model="filterValueDraftName"
                            class="game-filter-field__control"
                            type="text"
                            placeholder="Например, Windows"
                          />
                          <button
                            class="game-content-types-panel__value-add"
                            type="submit"
                            :disabled="!canAddFilterValue || isFilterValueSaving"
                            aria-label="Добавить значение"
                            title="Добавить значение"
                          >
                            <Plus :size="17" :stroke-width="2" aria-hidden="true" />
                          </button>
                        </span>
                      </label>
                    </form>

                    <div
                      v-if="activeLocalFilter.values.length === 0"
                      class="game-content-types-panel__values-empty"
                    >
                      Добавьте первое значение фильтра.
                    </div>

                    <div v-else ref="filterValueList" class="game-content-types-panel__value-list">
                      <div
                        v-for="value in activeLocalFilterValues"
                        :key="value.id"
                        class="game-content-types-panel__value"
                        :class="{ 'game-content-types-panel__value--inactive': !value.is_active }"
                        :data-value-id="value.id"
                        :style="{ '--value-depth': String(filterValueDepth(value)) }"
                      >
                        <div class="game-content-types-panel__value-main">
                          <button
                            class="game-content-types-panel__value-handle"
                            type="button"
                            :disabled="pendingFilterValueId !== null"
                            aria-label="Изменить порядок значения"
                            title="Перетащить"
                          >
                            <GripVertical :size="17" :stroke-width="2" aria-hidden="true" />
                          </button>

                          <label
                            class="game-content-types-panel__value-toggle"
                            :title="value.is_active ? 'Отключить значение' : 'Включить значение'"
                          >
                            <input
                              type="checkbox"
                              :checked="value.is_active"
                              :disabled="pendingFilterValueId === value.id"
                              :aria-label="
                                value.is_active
                                  ? `Отключить значение ${value.name}`
                                  : `Включить значение ${value.name}`
                              "
                              @change="toggleFilterValue(value)"
                            />
                          </label>

                          <form
                            v-if="editingFilterValueId === value.id"
                            class="game-content-types-panel__value-edit"
                            @submit.prevent="saveFilterValueName"
                          >
                            <input
                              v-model="editingFilterValueName"
                              class="game-filter-field__control"
                              type="text"
                              aria-label="Название значения"
                              autofocus
                            />
                            <button
                              class="game-content-types-panel__value-action"
                              type="submit"
                              :disabled="isFilterValueSaving"
                              aria-label="Сохранить название"
                              title="Сохранить"
                            >
                              <Check :size="15" :stroke-width="2" aria-hidden="true" />
                            </button>
                            <button
                              class="game-content-types-panel__value-action"
                              type="button"
                              :disabled="pendingFilterValueId !== null"
                              aria-label="Отменить редактирование"
                              title="Отмена"
                              @click="cancelEditingFilterValue"
                            >
                              <X :size="15" :stroke-width="2" aria-hidden="true" />
                            </button>
                          </form>

                          <span v-else class="game-content-types-panel__value-name">
                            {{ value.name }}
                          </span>

                          <div
                            v-if="editingFilterValueId !== value.id"
                            class="game-content-types-panel__value-actions"
                          >
                            <button
                              class="game-content-types-panel__value-action"
                              type="button"
                              :disabled="pendingFilterValueId !== null"
                              aria-label="Переименовать значение"
                              title="Переименовать"
                              @click="startEditingFilterValue(value)"
                            >
                              <Pencil :size="15" :stroke-width="2" aria-hidden="true" />
                            </button>
                            <button
                              class="game-content-types-panel__value-action game-content-types-panel__value-action--danger"
                              type="button"
                              :disabled="pendingFilterValueId !== null"
                              aria-label="Удалить значение"
                              title="Удалить"
                              @click="removeFilterValue(value)"
                            >
                              <Trash2 :size="15" :stroke-width="2" aria-hidden="true" />
                            </button>
                          </div>
                        </div>
                      </div>
                    </div>
                  </section>
                </template>
              </aside>
            </div>
          </section>
        </article>

        <article v-else class="game-view__body">
          <section class="game-view__section game-view-projects" aria-label="Проекты игры">
            <header class="game-content-types__header">
              <div class="game-content-types__heading">
                <span class="game-view__section-title">Проекты</span>
                <p class="game-content-types__summary">{{ projectsSubtitle }}</p>
              </div>
            </header>

            <div class="game-view-projects__layout">
              <aside class="game-project-filter-sidebar" aria-label="Фильтры проектов">
                <details class="game-project-filter-block" open>
                  <summary class="game-project-filter-block__summary">
                    <span>Тип контента</span>
                    <span v-if="projectContentTypeFilter !== 'all'">1</span>
                  </summary>

                  <div class="game-project-filter-block__list">
                    <label class="game-project-filter-option">
                      <input
                        v-model="projectContentTypeFilter"
                        type="radio"
                        name="project-content-type-filter"
                        value="all"
                      />
                      <span>Все типы</span>
                    </label>
                    <label
                      v-for="option in projectContentTypeOptions"
                      :key="option.value"
                      class="game-project-filter-option"
                    >
                      <input
                        v-model="projectContentTypeFilter"
                        type="radio"
                        name="project-content-type-filter"
                        :value="option.value"
                      />
                      <span>{{ option.label }}</span>
                    </label>
                  </div>
                </details>

                <aside
                  v-if="projectContentTypeFilter === 'all'"
                  class="game-project-filter-note"
                  aria-label="Информация о фильтрах проектов"
                >
                  <span class="game-project-filter-note__icon" aria-hidden="true">
                    <Info :size="21" :stroke-width="2" />
                  </span>
                  <div>
                    <strong>Фильтры проектов</strong>
                    <p>Выберите тип контента, чтобы увидеть проектные и релизные фильтры.</p>
                  </div>
                </aside>

                <template v-else>
                  <details
                    v-for="filter in projectFilterDimensions"
                    :key="`project-filter-${filter.id}`"
                    class="game-project-filter-block"
                    open
                  >
                    <summary class="game-project-filter-block__summary">
                      <span>{{ filter.name }}</span>
                      <span v-if="projectSidebarFilterSelectedValueCount(filter.id, 'project')">
                        {{ projectSidebarFilterSelectedValueCount(filter.id, 'project') }}
                      </span>
                    </summary>

                    <div class="game-project-filter-block__list">
                      <template v-for="value in projectSidebarRootValues(filter)" :key="value.id">
                        <details
                          v-if="projectSidebarValueHasChildren(filter, value)"
                          class="game-project-filter-group"
                        >
                          <summary class="game-project-filter-group__summary">
                            {{ value.name }}
                          </summary>

                          <label
                            v-for="childValue in projectSidebarChildValues(filter, value)"
                            :key="childValue.id"
                            class="game-project-filter-option game-project-filter-option--child"
                          >
                            <input
                              type="checkbox"
                              :checked="
                                projectSidebarFilterValueIsSelected(
                                  filter.id,
                                  childValue.id,
                                  'project',
                                )
                              "
                              @change="
                                toggleProjectSidebarFilterValue(filter.id, childValue.id, 'project')
                              "
                            />
                            <span>{{ childValue.name }}</span>
                          </label>
                        </details>

                        <label v-else class="game-project-filter-option">
                          <input
                            type="checkbox"
                            :checked="
                              projectSidebarFilterValueIsSelected(filter.id, value.id, 'project')
                            "
                            @change="
                              toggleProjectSidebarFilterValue(filter.id, value.id, 'project')
                            "
                          />
                          <span>{{ value.name }}</span>
                        </label>
                      </template>
                    </div>
                  </details>

                  <details
                    v-for="filter in releaseFilterDimensions"
                    :key="`release-filter-${filter.id}`"
                    class="game-project-filter-block"
                    open
                  >
                    <summary class="game-project-filter-block__summary">
                      <span>{{ filter.name }}</span>
                      <span v-if="projectSidebarFilterSelectedValueCount(filter.id, 'release')">
                        {{ projectSidebarFilterSelectedValueCount(filter.id, 'release') }}
                      </span>
                    </summary>

                    <div class="game-project-filter-block__list">
                      <template v-for="value in projectSidebarRootValues(filter)" :key="value.id">
                        <details
                          v-if="projectSidebarValueHasChildren(filter, value)"
                          class="game-project-filter-group"
                        >
                          <summary class="game-project-filter-group__summary">
                            {{ value.name }}
                          </summary>

                          <label
                            v-for="childValue in projectSidebarChildValues(filter, value)"
                            :key="childValue.id"
                            class="game-project-filter-option game-project-filter-option--child"
                          >
                            <input
                              type="checkbox"
                              :checked="
                                projectSidebarFilterValueIsSelected(
                                  filter.id,
                                  childValue.id,
                                  'release',
                                )
                              "
                              @change="
                                toggleProjectSidebarFilterValue(filter.id, childValue.id, 'release')
                              "
                            />
                            <span>{{ childValue.name }}</span>
                          </label>
                        </details>

                        <label v-else class="game-project-filter-option">
                          <input
                            type="checkbox"
                            :checked="
                              projectSidebarFilterValueIsSelected(filter.id, value.id, 'release')
                            "
                            @change="
                              toggleProjectSidebarFilterValue(filter.id, value.id, 'release')
                            "
                          />
                          <span>{{ value.name }}</span>
                        </label>
                      </template>
                    </div>
                  </details>
                </template>

                <details class="game-project-filter-block">
                  <summary class="game-project-filter-block__summary">
                    <span>Параметры</span>
                  </summary>

                  <div class="game-project-filter-block__fields">
                    <label class="game-filter-field">
                      <span class="game-filter-field__label">Сортировка</span>
                      <select v-model="projectSort" class="game-filter-field__control">
                        <option
                          v-for="option in projectSortOptions"
                          :key="option.value"
                          :value="option.value"
                        >
                          {{ option.label }}
                        </option>
                      </select>
                    </label>

                    <label class="game-filter-field">
                      <span class="game-filter-field__label">Вид</span>
                      <select v-model="projectPageSize" class="game-filter-field__control">
                        <option
                          v-for="option in projectPageSizeOptions"
                          :key="option"
                          :value="option"
                        >
                          {{ option }}
                        </option>
                      </select>
                    </label>
                  </div>
                </details>
              </aside>

              <div class="game-view-projects__content">
                <section class="game-filters" aria-label="Фильтры проектов">
                  <div class="game-filter-top">
                    <SearchField v-model="projectSearch" placeholder="Поиск по названию" />
                  </div>

                  <div class="game-filter-row game-filter-row--projects">
                    <label class="game-filter-field">
                      <span class="game-filter-field__label">Дата релиза</span>
                      <span class="game-filter-date-range">
                        <input
                          v-model="projectReleaseDateFrom"
                          class="game-filter-date-range__input"
                          type="date"
                          :max="projectReleaseDateTo || undefined"
                          aria-label="Дата релиза от"
                        />
                        <span class="game-filter-date-range__separator">-</span>
                        <input
                          v-model="projectReleaseDateTo"
                          class="game-filter-date-range__input"
                          type="date"
                          :min="projectReleaseDateFrom || undefined"
                          aria-label="Дата релиза до"
                        />
                      </span>
                    </label>

                    <label class="game-filter-field">
                      <span class="game-filter-field__label">Статус</span>
                      <select v-model="projectStatusFilter" class="game-filter-field__control">
                        <option
                          v-for="option in projectStatusFilterOptions"
                          :key="option.value"
                          :value="option.value"
                        >
                          {{ option.label }}
                        </option>
                      </select>
                    </label>

                    <button
                      class="project-display-mode"
                      type="button"
                      :aria-pressed="projectDisplayMode === 'list'"
                      :title="
                        projectDisplayMode === 'cards' ? 'Показать списком' : 'Показать карточками'
                      "
                      :aria-label="
                        projectDisplayMode === 'cards' ? 'Показать списком' : 'Показать карточками'
                      "
                      @click="toggleProjectDisplayMode"
                    >
                      <Image :size="18" :stroke-width="1.9" aria-hidden="true" />
                    </button>

                    <button
                      class="game-filter-reset"
                      type="button"
                      :disabled="!hasActiveProjectFilters"
                      title="Сбросить фильтры"
                      @click="resetProjectFilters"
                    >
                      <RotateCcw :size="18" :stroke-width="1.9" aria-hidden="true" />
                      <span>Сбросить</span>
                    </button>
                  </div>
                </section>

                <div
                  class="project-card-grid"
                  :class="{ 'project-card-grid--list': projectDisplayMode === 'list' }"
                  aria-label="Список проектов"
                >
                  <article
                    v-for="project in visibleProjects"
                    :key="project.id"
                    class="project-card"
                  >
                    <RouterLink
                      class="project-card__link"
                      :to="{ name: 'projects.show', params: { id: String(project.id) } }"
                      :aria-label="`Открыть превью проекта ${project.title}`"
                    >
                      <span class="project-card__media">
                        <img
                          v-if="project.logo_url"
                          class="project-card__image"
                          :src="project.logo_url"
                          :alt="`Изображение проекта ${project.title}`"
                        />
                        <span v-else class="project-card__placeholder">
                          {{ project.title.slice(0, 1).toUpperCase() }}
                        </span>
                      </span>

                      <span class="project-card__body">
                        <span class="project-card__heading">
                          <strong class="project-card__title">{{ project.title }}</strong>
                          <span v-if="project.owner_name" class="project-card__author">
                            by {{ project.owner_name }}
                          </span>
                        </span>
                        <span class="project-card__summary">{{ projectSummary(project) }}</span>

                        <span class="project-card__badges" aria-label="Теги проекта">
                          <span
                            v-for="tag in projectTags(project)"
                            :key="tag"
                            class="project-card__badge"
                          >
                            {{ tag }}
                          </span>
                        </span>

                        <span class="project-card__footer">
                          <span class="project-card__updated-at">{{
                            projectUpdatedAt(project)
                          }}</span>
                        </span>
                      </span>
                    </RouterLink>

                    <div class="project-card__actions" aria-label="Действия проекта">
                      <span class="project-card__status" :class="projectStatusClass(project)">
                        {{ project.status_label ?? project.status ?? 'Статус не указан' }}
                      </span>
                      <span class="project-card__updated-at project-card__updated-at--actions">
                        <span>{{ projectUpdatedAt(project) }}</span>
                        <Calendar :size="14" :stroke-width="1.9" aria-hidden="true" />
                      </span>

                      <RouterLink
                        class="icon-action"
                        :to="projectActionRoute(project)"
                        :aria-label="projectActionLabel(project)"
                        :title="projectActionLabel(project)"
                        @click.stop
                      >
                        <PlayCircle
                          v-if="projectIsDraft(project)"
                          :size="16"
                          :stroke-width="2"
                          aria-hidden="true"
                        />
                        <Pencil v-else :size="16" :stroke-width="2" aria-hidden="true" />
                      </RouterLink>

                      <button
                        v-if="project.can_delete"
                        class="icon-action icon-action--danger"
                        type="button"
                        :disabled="deletingProjectId !== null"
                        aria-label="Удалить проект"
                        title="Удалить проект"
                        @click.stop.prevent="openProjectDeleteModal(project)"
                      >
                        <Trash2 :size="16" :stroke-width="2" aria-hidden="true" />
                      </button>
                    </div>
                  </article>

                  <div v-if="visibleProjects.length === 0" class="game-card-empty">
                    Проекты не найдены
                  </div>
                </div>
              </div>
            </div>
          </section>
        </article>

        <Transition name="modal">
          <div
            v-if="isContentTypeModalOpen"
            class="modal game-content-type-modal"
            role="presentation"
          >
            <div class="modal__backdrop" @click="closeContentTypeModal" />

            <section
              class="modal__dialog game-content-type-modal__dialog"
              role="dialog"
              aria-modal="true"
              aria-labelledby="game-content-type-modal-title"
            >
              <div class="modal__content game-content-type-modal__content">
                <div class="modal__icon game-content-type-modal__icon" aria-hidden="true">
                  <Tags :size="34" :stroke-width="1.9" />
                </div>

                <div class="modal__copy">
                  <h3 id="game-content-type-modal-title">Добавить типы контента</h3>
                  <p>
                    Выберите один или несколько типов контента. Выбрано:
                    {{ selectedContentTypes.length }}
                  </p>
                </div>

                <SearchField
                  v-model="contentTypeModalSearch"
                  class="game-content-type-modal__search"
                  placeholder="Поиск по названию или slug"
                />

                <div
                  v-if="selectedContentTypes.length > 0"
                  class="game-content-type-modal__selected"
                >
                  <button
                    v-for="contentType in selectedContentTypes"
                    :key="contentType.id"
                    class="game-content-type-modal__chip"
                    type="button"
                    :disabled="isAttachingContentType"
                    @click="removeSelectedContentType(contentType.id)"
                  >
                    <span>{{ contentType.name }}</span>
                    <X :size="14" :stroke-width="2" aria-hidden="true" />
                  </button>
                </div>

                <div class="game-content-type-modal__list">
                  <label
                    v-for="contentType in filteredAvailableContentTypes"
                    :key="contentType.id"
                    class="game-content-type-modal__option"
                  >
                    <input
                      type="checkbox"
                      :checked="selectedContentTypeIds.includes(contentType.id)"
                      :disabled="isAttachingContentType"
                      @change="toggleContentTypeSelection(contentType.id)"
                    />
                    <span class="game-content-type-modal__option-copy">
                      <span class="game-content-type-modal__option-name">{{
                        contentType.name
                      }}</span>
                      <span class="game-content-type-modal__option-slug">{{
                        contentType.slug
                      }}</span>
                    </span>
                  </label>

                  <p
                    v-if="filteredAvailableContentTypes.length === 0"
                    class="game-content-type-modal__empty"
                  >
                    Доступные типы контента не найдены.
                  </p>
                </div>
              </div>

              <footer class="modal__actions">
                <button
                  class="modal__button modal__button--secondary"
                  type="button"
                  :disabled="isAttachingContentType"
                  @click="closeContentTypeModal"
                >
                  Отмена
                </button>

                <button
                  class="modal__button game-content-type-modal__button--primary"
                  type="button"
                  :disabled="selectedContentTypeIds.length === 0 || isAttachingContentType"
                  @click="attachSelectedContentTypes"
                >
                  Добавить
                </button>
              </footer>
            </section>
          </div>
        </Transition>

        <Transition name="modal">
          <div
            v-if="isCopyFiltersModalOpen"
            class="modal game-filter-copy-modal"
            role="presentation"
          >
            <div class="modal__backdrop" @click="closeCopyFiltersModal" />

            <section
              class="modal__dialog game-filter-copy-modal__dialog"
              role="dialog"
              aria-modal="true"
              aria-labelledby="game-filter-copy-modal-title"
            >
              <header class="game-filter-copy-modal__header">
                <div class="modal__icon game-filter-copy-modal__icon" aria-hidden="true">
                  <Copy :size="28" :stroke-width="1.9" />
                </div>
                <div class="modal__copy game-filter-copy-modal__heading">
                  <h3 id="game-filter-copy-modal-title">Копировать фильтры</h3>
                  <p>{{ activeFiltersGameContentType?.content_type_name ?? 'Тип контента' }}</p>
                </div>
                <button
                  class="game-content-types-panel__close"
                  type="button"
                  :disabled="isCopyFiltersLoading || isCopyingFilters"
                  aria-label="Закрыть копирование фильтров"
                  title="Закрыть"
                  @click="closeCopyFiltersModal"
                >
                  <X :size="16" :stroke-width="2" aria-hidden="true" />
                </button>
              </header>

              <StepIndicator
                :steps="copyFilterSteps"
                :current-step="copyFiltersStep"
                aria-label="Шаги копирования"
                variant="embedded"
              />

              <div class="game-filter-copy-modal__viewport">
                <div
                  class="game-filter-copy-modal__track"
                  :class="{ 'game-filter-copy-modal__track--second': copyFiltersStep === 2 }"
                >
                  <section
                    class="game-filter-copy-modal__stage"
                    :aria-hidden="copyFiltersStep !== 1"
                    :inert="copyFiltersStep !== 1"
                  >
                    <div class="game-filter-copy-modal__stage-heading">
                      <h4>Откуда копировать</h4>
                      <span>Выберите тип контента этой игры</span>
                    </div>

                    <div class="game-filter-copy-modal__source-list">
                      <label
                        v-for="source in copySourceOptions"
                        :key="source.id"
                        class="game-filter-copy-modal__source"
                        :class="{
                          'game-filter-copy-modal__source--selected':
                            copySourceGameContentTypeId === source.id,
                        }"
                      >
                        <input
                          v-model="copySourceGameContentTypeId"
                          type="radio"
                          name="copy-source-content-type"
                          :value="source.id"
                        />
                        <span class="game-filter-copy-modal__source-copy">
                          <strong>{{ source.content_type_name ?? 'Тип контента' }}</strong>
                          <span>{{ source.content_type_slug ?? 'slug не указан' }}</span>
                        </span>
                        <span class="game-filter-copy-modal__source-count">
                          {{ filtersCount(source) }}
                        </span>
                      </label>
                    </div>
                  </section>

                  <section
                    class="game-filter-copy-modal__stage"
                    :aria-hidden="copyFiltersStep !== 2"
                    :inert="copyFiltersStep !== 2"
                  >
                    <div class="game-filter-copy-modal__stage-heading">
                      <h4>Какие фильтры копировать</h4>
                      <span>{{ copySourceGameContentType?.content_type_name }}</span>
                    </div>

                    <div class="game-filter-copy-modal__filter-list">
                      <label
                        v-for="filter in copySourceFilters"
                        :key="filter.id"
                        class="game-filter-copy-modal__filter"
                        :class="{
                          'game-filter-copy-modal__filter--selected':
                            selectedCopyFilterIds.includes(filter.id),
                        }"
                      >
                        <input
                          type="checkbox"
                          :checked="selectedCopyFilterIds.includes(filter.id)"
                          @change="toggleCopyFilter(filter.id)"
                        />
                        <span class="game-filter-copy-modal__filter-copy">
                          <strong>{{ filter.name }}</strong>
                          <span>
                            {{
                              filter.selection_mode === 'single'
                                ? 'Один выбор'
                                : 'Несколько значений'
                            }}
                            · Значений: {{ filter.values.length }}
                          </span>
                        </span>
                      </label>

                      <p
                        v-if="copySourceFilters.length === 0"
                        class="game-filter-copy-modal__empty"
                      >
                        У выбранного типа контента нет фильтров.
                      </p>
                    </div>
                  </section>
                </div>
              </div>

              <footer class="modal__actions game-filter-copy-modal__actions">
                <button
                  v-if="copyFiltersStep === 1"
                  class="modal__button modal__button--secondary"
                  type="button"
                  :disabled="isCopyFiltersLoading"
                  @click="closeCopyFiltersModal"
                >
                  Отмена
                </button>
                <button
                  v-else
                  class="modal__button modal__button--secondary"
                  type="button"
                  :disabled="isCopyingFilters"
                  @click="returnToCopySource"
                >
                  Назад
                </button>

                <button
                  v-if="copyFiltersStep === 1"
                  class="modal__button game-content-type-modal__button--primary"
                  type="button"
                  :disabled="copySourceGameContentTypeId === null || isCopyFiltersLoading"
                  @click="continueCopyFilters"
                >
                  {{ isCopyFiltersLoading ? 'Загружаем…' : 'Далее' }}
                </button>
                <button
                  v-else
                  class="modal__button game-content-type-modal__button--primary"
                  type="button"
                  :disabled="selectedCopyFilterIds.length === 0 || isCopyingFilters"
                  @click="submitCopyFilters"
                >
                  {{
                    isCopyingFilters ? 'Копируем…' : `Копировать (${selectedCopyFilterIds.length})`
                  }}
                </button>
              </footer>
            </section>
          </div>
        </Transition>

        <DeleteModal
          :open="isDeleteModalOpen"
          title="Удалить игру?"
          :description="deleteModalDescription"
          :loading="isDeletingGame"
          @cancel="closeGameDeleteModal"
          @confirm="confirmDeleteGame"
        />

        <DeleteModal
          :open="pendingDeleteProject !== null"
          title="Удалить проект"
          :description="projectDeleteModalDescription"
          :loading="deletingProjectId !== null"
          @cancel="closeProjectDeleteModal"
          @confirm="confirmDeleteProject"
        />
      </template>
    </section>
  </AppShell>
</template>
