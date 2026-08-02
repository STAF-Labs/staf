<script setup lang="ts">
import {
  ArrowLeft,
  Check,
  ChevronRight,
  Copy,
  GripVertical,
  Pencil,
  Plus,
  Settings2,
  SlidersHorizontal,
  Tags,
  Trash2,
  X,
} from '@lucide/vue'
import { AxiosError } from 'axios'
import Sortable, { type SortableEvent } from 'sortablejs'
import StarterKit from '@tiptap/starter-kit'
import { EditorContent, useEditor } from '@tiptap/vue-3'
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import AppShell from '@/components/layout/AppShell.vue'
import SearchField from '@/components/ui/SearchField.vue'
import { fetchContentTypes, type ContentTypeListItem } from '@/shared/content-types/content-types'
import {
  attachGameContentType,
  copyGameDimensions,
  createGameDimension,
  createGameDimensionValue,
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
import '@/assets/styles/game-view.css'

const route = useRoute()
const game = ref<GameDetail | null>(null)
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

const editor = useEditor({
  extensions: [StarterKit],
  content: '',
  editable: false,
})

const title = computed(() => game.value?.name ?? 'Игра')
const statusClass = computed(() => `game-view-status--${game.value?.status_color ?? 'gray'}`)
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

function setDescription(value: unknown): void {
  if (!editor.value) {
    return
  }

  if (value && typeof value === 'object') {
    editor.value.commands.setContent(value)

    return
  }

  editor.value.commands.clearContent()
}

async function loadGame(): Promise<void> {
  isLoading.value = true
  message.value = ''

  try {
    const gameId = String(route.params.id)
    const [gameResponse, gameContentTypesResponse, contentTypesResponse] = await Promise.all([
      fetchGame(gameId),
      fetchGameContentTypes(gameId),
      fetchContentTypes(),
    ])

    game.value = gameResponse
    gameContentTypes.value = gameContentTypesResponse.data
    contentTypes.value = contentTypesResponse.data
    route.meta.breadcrumbLabel = game.value.name
    setDescription(game.value.description)
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
  const values = [...originalValues]
  const [value] = values.splice(oldIndex, 1)

  if (!value || newIndex < 0 || newIndex > values.length) {
    return
  }

  values.splice(newIndex, 0, value)
  const reorderedValues = values.map((item, sortOrder) => ({ ...item, sort_order: sortOrder }))
  pendingFilterValueId.value = value.id
  updateActiveLocalFilter((filter) => ({ ...filter, values: reorderedValues }))

  try {
    await Promise.all(
      reorderedValues.map((item) =>
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
    message.value = `Фильтры скопированы: новых ${response.created_filters}, переиспользовано ${response.reused_filters}; значений добавлено ${response.created_values}, дублей пропущено ${response.skipped_values}.`
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
  editor.value?.destroy()
})

void loadGame()
</script>

<template>
  <AppShell>
    <section class="game-view">
      <p v-if="message" class="data-page__message">{{ message }}</p>
      <div v-if="isLoading" class="game-view__loading">Загрузка...</div>

      <template v-else-if="game">
        <div class="game-view__banner">
          <img v-if="game.banner_url" :src="game.banner_url" :alt="game.name" />
        </div>

        <article class="game-view__body">
          <div class="game-view__topbar">
            <RouterLink
              class="data-page__back-link game-view__back"
              :to="{ name: 'games.index' }"
              aria-label="Назад к играм"
            >
              <ArrowLeft :size="18" :stroke-width="1.9" aria-hidden="true" />
              <span>Игры</span>
            </RouterLink>

            <RouterLink
              class="game-view__edit"
              :to="{ name: 'games.edit', params: { id: String(game.id) } }"
              aria-label="Редактировать игру"
              title="Редактировать игру"
            >
              <Pencil :size="17" :stroke-width="2" aria-hidden="true" />
            </RouterLink>
          </div>

          <section class="game-view__summary" aria-label="Основная информация">
            <div class="game-view__logo">
              <img v-if="game.logo_url" :src="game.logo_url" :alt="game.name" />
              <span v-else>{{ game.name.slice(0, 1).toUpperCase() }}</span>
            </div>

            <div class="game-view__facts">
              <div class="game-view__field">
                <h2 class="game-view__title">{{ title }}</h2>
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
            <EditorContent class="game-view-description" :editor="editor" />
          </section>

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
                        v-for="value in activeLocalFilter.values"
                        :key="value.id"
                        class="game-content-types-panel__value"
                        :class="{ 'game-content-types-panel__value--inactive': !value.is_active }"
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

              <div class="game-filter-copy-modal__steps" aria-label="Шаги копирования">
                <span
                  class="game-filter-copy-modal__step"
                  :class="{ 'game-filter-copy-modal__step--active': copyFiltersStep === 1 }"
                >
                  <span>1</span>
                  Тип контента
                </span>
                <span class="game-filter-copy-modal__step-line" aria-hidden="true" />
                <span
                  class="game-filter-copy-modal__step"
                  :class="{ 'game-filter-copy-modal__step--active': copyFiltersStep === 2 }"
                >
                  <span>2</span>
                  Фильтры
                </span>
              </div>

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
      </template>
    </section>
  </AppShell>
</template>
