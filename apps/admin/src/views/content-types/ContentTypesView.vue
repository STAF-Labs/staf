<script setup lang="ts">
import {
  BookKey,
  Columns3,
  FileSpreadsheet,
  Pencil,
  Plus,
  RotateCcw,
  SlidersHorizontal,
  Trash2,
} from '@lucide/vue'
import { AxiosError } from 'axios'
import { computed, onMounted, ref } from 'vue'
import ContentTypeModal from '@/components/content-types/ContentTypeModal.vue'
import DataTable from '@/components/data/DataTable.vue'
import AppShell from '@/components/layout/AppShell.vue'
import DeleteModal from '@/components/ui/DeleteModal.vue'
import SearchField from '@/components/ui/SearchField.vue'
import type { DataColumn } from '@/shared/data/table'
import {
  createContentType as createContentTypeRequest,
  deleteContentType as deleteContentTypeRequest,
  fetchContentTypes,
  toggleContentTypePublic,
  updateContentType,
  type ContentTypeListItem,
  type CreateContentTypePayload,
} from '@/shared/content-types/content-types'
import { fetchGames, type GameListItem } from '@/shared/games/games'

type PublicFilter = 'all' | 'public' | 'private'
type SortOption = 'name_asc' | 'name_desc' | 'created_at'

const publicFilterOptions: Array<{ value: PublicFilter; label: string }> = [
  { value: 'all', label: 'Все' },
  { value: 'public', label: 'Да' },
  { value: 'private', label: 'Нет' },
]
const sortOptions: Array<{ value: SortOption; label: string }> = [
  { value: 'name_asc', label: 'А-Я' },
  { value: 'name_desc', label: 'Я-А' },
  { value: 'created_at', label: 'Дата создания' },
]
const pageSizeOptions = [10, 20, 30, 40, 50]

const contentTypes = ref<ContentTypeListItem[]>([])
const games = ref<GameListItem[]>([])
const search = ref('')
const gameFilter = ref('all')
const createdFrom = ref('')
const createdTo = ref('')
const publicFilter = ref<PublicFilter>('all')
const sort = ref<SortOption>('name_asc')
const pageSize = ref(20)
const advancedFiltersOpen = ref(false)
const isCreateModalOpen = ref(false)
const isCreating = ref(false)
const editingContentType = ref<ContentTypeListItem | null>(null)
const createError = ref('')
const message = ref('')
const isLoading = ref(false)
const actionContentTypeId = ref<number | null>(null)
const pendingDeleteContentType = ref<ContentTypeListItem | null>(null)

const columns = ref<DataColumn[]>([
  { key: 'id', label: 'ID', visible: false },
  { key: 'name', label: 'Название', visible: true },
  { key: 'game_name', label: 'Игра', visible: true },
  { key: 'slug', label: 'Slug', visible: false },
  { key: 'is_public', label: 'Публичный', visible: true },
  { key: 'created_at', label: 'Создан', visible: true },
])

const visibleColumns = computed(() => columns.value.filter((column) => column.visible))
const tableColumns = computed<DataColumn[]>(() => [
  ...visibleColumns.value,
  { key: 'actions', label: '', visible: true },
])
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
const hasActiveFilters = computed(() =>
  Boolean(
    search.value.trim() ||
    gameFilter.value !== 'all' ||
    createdFrom.value ||
    createdTo.value ||
    publicFilter.value !== 'all',
  ),
)
const filteredContentTypes = computed(() =>
  contentTypes.value.filter(
    (contentType) =>
      matchesSearch(contentType) &&
      matchesGameFilter(contentType) &&
      matchesCreatedDateFilter(contentType) &&
      matchesPublicFilter(contentType),
  ),
)
const sortedContentTypes = computed(() =>
  [...filteredContentTypes.value].sort((firstContentType, secondContentType) => {
    if (sort.value === 'name_desc') {
      return (secondContentType.name ?? '').localeCompare(firstContentType.name ?? '', 'ru-RU')
    }

    if (sort.value === 'created_at') {
      return createdTimestamp(secondContentType) - createdTimestamp(firstContentType)
    }

    return (firstContentType.name ?? '').localeCompare(secondContentType.name ?? '', 'ru-RU')
  }),
)
const rows = computed<Record<string, unknown>[]>(() =>
  sortedContentTypes.value.map((contentType) => ({
    ...contentType,
    game_name: contentType.game_names.length > 0 ? contentType.game_names.join(', ') : '—',
    created_at: formatDate(contentType.created_at),
  })),
)
const subtitle = computed(() => {
  if (hasActiveFilters.value && filteredContentTypes.value.length !== contentTypes.value.length) {
    return `Всего типов контента: ${contentTypes.value.length}. Найдено: ${filteredContentTypes.value.length}.`
  }

  return `Всего типов контента: ${contentTypes.value.length}.`
})
const deleteModalDescription = computed(() => {
  const name = pendingDeleteContentType.value?.name ?? 'тип контента'

  return `Тип контента ${name} будет удален. Связи с играми также будут удалены.`
})

function matchesSearch(contentType: ContentTypeListItem): boolean {
  const searchQuery = search.value.trim().toLocaleLowerCase('ru-RU')

  if (!searchQuery) {
    return true
  }

  return contentType.name.toLocaleLowerCase('ru-RU').includes(searchQuery)
}

function matchesGameFilter(contentType: ContentTypeListItem): boolean {
  return gameFilter.value === 'all' || contentType.game_ids.includes(Number(gameFilter.value))
}

function matchesCreatedDateFilter(contentType: ContentTypeListItem): boolean {
  if (!createdFrom.value && !createdTo.value) {
    return true
  }

  if (!contentType.created_at) {
    return false
  }

  const createdAt = new Date(contentType.created_at)
  createdAt.setHours(0, 0, 0, 0)

  if (createdFrom.value) {
    const dateFrom = new Date(createdFrom.value)
    dateFrom.setHours(0, 0, 0, 0)

    if (createdAt < dateFrom) {
      return false
    }
  }

  if (createdTo.value) {
    const dateTo = new Date(createdTo.value)
    dateTo.setHours(0, 0, 0, 0)

    if (createdAt > dateTo) {
      return false
    }
  }

  return true
}

function matchesPublicFilter(contentType: ContentTypeListItem): boolean {
  return (
    publicFilter.value === 'all' ||
    (publicFilter.value === 'public' && contentType.is_public) ||
    (publicFilter.value === 'private' && !contentType.is_public)
  )
}

function createdTimestamp(contentType: ContentTypeListItem): number {
  if (!contentType.created_at) {
    return 0
  }

  return new Date(contentType.created_at).getTime()
}

function formatDate(value: string | null): string {
  if (!value) {
    return '—'
  }

  return new Intl.DateTimeFormat('ru-RU', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(value))
}

function toggleColumn(key: string): void {
  columns.value = columns.value.map((column) =>
    column.key === key ? { ...column, visible: !column.visible } : column,
  )
}

function resetFilters(): void {
  search.value = ''
  gameFilter.value = 'all'
  createdFrom.value = ''
  createdTo.value = ''
  publicFilter.value = 'all'
}

function toggleAdvancedFilters(): void {
  advancedFiltersOpen.value = !advancedFiltersOpen.value
}

function openCreateModal(): void {
  createError.value = ''
  editingContentType.value = null
  isCreateModalOpen.value = true
}

function openEditModal(row: Record<string, unknown>): void {
  const contentType = contentTypes.value.find((item) => item.id === Number(row.id))

  if (!contentType) {
    return
  }

  createError.value = ''
  editingContentType.value = contentType
  isCreateModalOpen.value = true
}

function closeCreateModal(): void {
  if (isCreating.value) {
    return
  }

  isCreateModalOpen.value = false
  editingContentType.value = null
  createError.value = ''
}

async function togglePublic(row: Record<string, unknown>): Promise<void> {
  const contentTypeId = Number(row.id)

  actionContentTypeId.value = contentTypeId
  message.value = ''

  try {
    const updatedContentType = await toggleContentTypePublic(contentTypeId)

    contentTypes.value = contentTypes.value.map((contentType) =>
      contentType.id === updatedContentType.id ? updatedContentType : contentType,
    )
    message.value = updatedContentType.is_public
      ? 'Тип контента опубликован.'
      : 'Тип контента скрыт.'
  } catch {
    message.value = 'Не удалось переключить публичность типа контента.'
  } finally {
    actionContentTypeId.value = null
  }
}

function openDeleteModal(row: Record<string, unknown>): void {
  const contentType = contentTypes.value.find((item) => item.id === Number(row.id))

  if (!contentType) {
    return
  }

  pendingDeleteContentType.value = contentType
}

function closeDeleteModal(): void {
  if (actionContentTypeId.value !== null) {
    return
  }

  pendingDeleteContentType.value = null
}

async function confirmDelete(): Promise<void> {
  if (!pendingDeleteContentType.value) {
    return
  }

  const contentTypeId = pendingDeleteContentType.value.id

  actionContentTypeId.value = contentTypeId
  message.value = ''

  try {
    await deleteContentTypeRequest(contentTypeId)
    await loadContentTypes()
    message.value = 'Тип контента удален.'
  } catch (error) {
    message.value = apiErrorMessage(error, 'Не удалось удалить тип контента.')
  } finally {
    actionContentTypeId.value = null
    pendingDeleteContentType.value = null
  }
}

async function createContentType(payload: CreateContentTypePayload): Promise<void> {
  isCreating.value = true
  createError.value = ''
  message.value = ''

  try {
    if (editingContentType.value) {
      const updatedContentType = await updateContentType(editingContentType.value.id, payload)

      contentTypes.value = contentTypes.value.map((contentType) =>
        contentType.id === updatedContentType.id ? updatedContentType : contentType,
      )
      message.value = 'Тип контента обновлен.'
    } else {
      await createContentTypeRequest(payload)
      await loadContentTypes()
      message.value = 'Тип контента создан.'
    }

    isCreateModalOpen.value = false
    editingContentType.value = null
  } catch (error) {
    createError.value = validationMessage(error)
  } finally {
    isCreating.value = false
  }
}

function validationMessage(error: unknown): string {
  if (error instanceof AxiosError && error.response?.status === 422) {
    const errors = error.response.data?.errors
    const firstError =
      errors && typeof errors === 'object'
        ? Object.values(errors)
            .flat()
            .find((value) => typeof value === 'string')
        : null

    return typeof firstError === 'string' ? firstError : 'Проверьте поля формы.'
  }

  return editingContentType.value
    ? 'Не удалось обновить тип контента.'
    : 'Не удалось создать тип контента.'
}

function apiErrorMessage(error: unknown, fallback: string): string {
  if (error instanceof AxiosError && typeof error.response?.data?.message === 'string') {
    return error.response.data.message
  }

  return fallback
}

async function loadContentTypes(): Promise<void> {
  isLoading.value = true
  message.value = ''

  try {
    const [contentTypesResponse, gamesResponse] = await Promise.all([
      fetchContentTypes(),
      fetchGames(),
    ])

    contentTypes.value = contentTypesResponse.data
    games.value = gamesResponse.data
  } catch {
    message.value = 'Не удалось загрузить типы контента.'
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  void loadContentTypes()
})
</script>

<template>
  <AppShell>
    <section class="data-page">
      <header class="data-page__header content-type-header">
        <div class="content-type-header__text">
          <h2 class="data-page__title">Типы контента</h2>
          <p class="data-page__subtitle">{{ subtitle }}</p>
        </div>

        <div class="content-type-header__actions">
          <RouterLink
            class="content-type-header__button content-type-header__button--success"
            :to="{ name: 'content-types.import' }"
          >
            <FileSpreadsheet :size="18" :stroke-width="1.9" aria-hidden="true" />
            <span>Импорт из Excel</span>
          </RouterLink>

          <button
            class="content-type-header__button content-type-header__button--primary"
            type="button"
            @click="openCreateModal"
          >
            <Plus :size="18" :stroke-width="2.1" aria-hidden="true" />
            <span>Создать</span>
          </button>
        </div>
      </header>

      <p v-if="message" class="data-page__message">{{ message }}</p>

      <section class="game-filters" aria-label="Фильтры типов контента">
        <div class="game-filter-top game-filter-top--with-actions">
          <SearchField v-model="search" placeholder="Поиск по названию" />

          <button
            class="game-filter-advanced"
            :class="{ 'game-filter-advanced--active': advancedFiltersOpen }"
            type="button"
            title="Расширенные настройки"
            :aria-pressed="advancedFiltersOpen"
            @click="toggleAdvancedFilters"
          >
            <SlidersHorizontal :size="18" :stroke-width="1.9" aria-hidden="true" />
            <span>Расширенные настройки</span>
          </button>

          <details class="data-toolbar__columns game-filter-columns">
            <summary class="game-filter-advanced">
              <Columns3 :size="18" :stroke-width="1.9" aria-hidden="true" />
              <span>Колонки</span>
            </summary>

            <div class="data-toolbar__columns-menu">
              <label
                v-for="column in columns"
                :key="column.key"
                class="data-toolbar__column-option"
              >
                <input
                  class="checkbox-control"
                  type="checkbox"
                  :checked="column.visible"
                  @change="toggleColumn(column.key)"
                />
                <span>{{ column.label }}</span>
              </label>
            </div>
          </details>
        </div>

        <div class="game-filter-row game-filter-row--content-types">
          <label class="game-filter-field">
            <span class="game-filter-field__label">Игра</span>
            <select v-model="gameFilter" class="game-filter-field__control">
              <option v-for="option in gameFilterOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </label>

          <label class="game-filter-field">
            <span class="game-filter-field__label">Создан</span>
            <span class="game-filter-date-range">
              <input
                v-model="createdFrom"
                class="game-filter-date-range__input"
                type="date"
                :max="createdTo || undefined"
                aria-label="Создан от"
              />
              <span class="game-filter-date-range__separator">-</span>
              <input
                v-model="createdTo"
                class="game-filter-date-range__input"
                type="date"
                :min="createdFrom || undefined"
                aria-label="Создан до"
              />
            </span>
          </label>

          <label class="game-filter-field">
            <span class="game-filter-field__label">Публичный</span>
            <select v-model="publicFilter" class="game-filter-field__control">
              <option
                v-for="option in publicFilterOptions"
                :key="option.value"
                :value="option.value"
              >
                {{ option.label }}
              </option>
            </select>
          </label>

          <button
            class="game-filter-reset"
            type="button"
            :disabled="!hasActiveFilters"
            title="Сбросить фильтры"
            @click="resetFilters"
          >
            <RotateCcw :size="18" :stroke-width="1.9" aria-hidden="true" />
            <span>Сбросить</span>
          </button>
        </div>
      </section>

      <div
        class="game-results-layout"
        :class="{ 'game-results-layout--with-panel': advancedFiltersOpen }"
      >
        <div class="data-table-panel">
          <DataTable
            :columns="tableColumns"
            :rows="rows"
            :loading="isLoading"
            :page-size="pageSize"
            empty-text="Типы контента не найдены"
          >
            <template #cell-is_public="{ value }">
              <span
                class="status-badge"
                :class="value ? 'status-badge--success' : 'status-badge--gray'"
              >
                {{ value ? 'Да' : 'Нет' }}
              </span>
            </template>

            <template #cell-actions="{ row }">
              <div class="content-type-actions">
                <button
                  class="data-table__icon-action"
                  type="button"
                  :disabled="actionContentTypeId === Number(row.id)"
                  aria-label="Переключить публичность"
                  title="Публичный"
                  @click="togglePublic(row)"
                >
                  <BookKey :size="17" :stroke-width="1.9" aria-hidden="true" />
                </button>

                <button
                  class="data-table__icon-action"
                  type="button"
                  :disabled="actionContentTypeId === Number(row.id)"
                  aria-label="Редактировать тип контента"
                  title="Редактировать"
                  @click="openEditModal(row)"
                >
                  <Pencil :size="17" :stroke-width="1.9" aria-hidden="true" />
                </button>

                <button
                  class="data-table__icon-action data-table__icon-action--danger"
                  type="button"
                  :disabled="actionContentTypeId === Number(row.id)"
                  aria-label="Удалить тип контента"
                  title="Удалить"
                  @click="openDeleteModal(row)"
                >
                  <Trash2 :size="17" :stroke-width="1.9" aria-hidden="true" />
                </button>
              </div>
            </template>
          </DataTable>
        </div>

        <aside
          class="game-advanced-panel"
          :class="{ 'game-advanced-panel--open': advancedFiltersOpen }"
          :aria-hidden="!advancedFiltersOpen"
          aria-label="Расширенные настройки"
        >
          <label class="game-filter-field">
            <span class="game-filter-field__label">Сортировка</span>
            <select v-model="sort" class="game-filter-field__control">
              <option v-for="option in sortOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </label>

          <label class="game-filter-field">
            <span class="game-filter-field__label">Вид</span>
            <select v-model="pageSize" class="game-filter-field__control">
              <option v-for="option in pageSizeOptions" :key="option" :value="option">
                {{ option }}
              </option>
            </select>
          </label>
        </aside>
      </div>
    </section>

    <ContentTypeModal
      :open="isCreateModalOpen"
      :mode="editingContentType ? 'edit' : 'create'"
      :initial-name="editingContentType?.name ?? ''"
      :initial-is-public="editingContentType?.is_public ?? true"
      :loading="isCreating"
      :error="createError"
      @cancel="closeCreateModal"
      @submit="createContentType"
    />

    <DeleteModal
      :open="pendingDeleteContentType !== null"
      title="Удалить тип контента"
      :description="deleteModalDescription"
      :loading="actionContentTypeId !== null"
      @cancel="closeDeleteModal"
      @confirm="confirmDelete"
    />
  </AppShell>
</template>

<style scoped>
.content-type-header {
  grid-template-columns: minmax(0, 1fr) auto;
  align-items: center;
}

.content-type-header__text {
  display: grid;
  gap: 6px;
  min-width: 0;
}

.content-type-header__actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  flex-wrap: wrap;
  gap: 10px;
}

.content-type-header__button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 40px;
  padding: 0 14px;
  border: 1px solid transparent;
  border-radius: var(--radius-md);
  cursor: pointer;
  font: inherit;
  font-size: 13px;
  font-weight: 800;
  text-decoration: none;
  white-space: nowrap;
}

.content-type-header__button--success {
  color: var(--color-primary-text);
  background: var(--color-success);
  border-color: var(--color-success);
}

.content-type-header__button--primary {
  color: var(--color-primary-text);
  background: var(--color-primary);
  border-color: var(--color-primary);
}

.content-type-header__button:hover {
  filter: brightness(0.96);
}

.content-type-actions {
  display: flex;
  align-items: center;
  gap: 6px;
}

@media (max-width: 640px) {
  .content-type-header {
    grid-template-columns: 1fr;
  }

  .content-type-header__actions {
    justify-content: flex-start;
  }
}
</style>
