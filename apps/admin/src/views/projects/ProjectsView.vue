<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Pencil, Plus, RotateCcw, SlidersHorizontal, Trash2 } from '@lucide/vue'
import AppShell from '@/components/layout/AppShell.vue'
import DeleteModal from '@/components/ui/DeleteModal.vue'
import SearchField from '@/components/ui/SearchField.vue'
import { fetchGames, type GameListItem } from '@/shared/games/games'
import {
  deleteProject as removeProject,
  fetchProjects,
  type ProjectListItem,
  type ProjectStatus,
} from '@/shared/projects/projects'

type StatusFilter = ProjectStatus | 'all'
type SortOption = 'title_asc' | 'title_desc' | 'released_at'

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
const search = ref('')
const gameFilter = ref('all')
const releaseDateFrom = ref('')
const releaseDateTo = ref('')
const statusFilter = ref<StatusFilter>('all')
const sort = ref<SortOption>('title_asc')
const pageSize = ref(20)
const advancedFiltersOpen = ref(false)
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
const hasActiveFilters = computed(() =>
  Boolean(
    search.value.trim() ||
    gameFilter.value !== 'all' ||
    releaseDateFrom.value ||
    releaseDateTo.value ||
    statusFilter.value !== 'all',
  ),
)
const filteredProjects = computed(() =>
  projects.value.filter(
    (project) =>
      matchesSearch(project) &&
      matchesGameFilter(project) &&
      matchesReleaseDateFilter(project) &&
      matchesStatusFilter(project),
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
  releaseDateFrom.value = ''
  releaseDateTo.value = ''
  statusFilter.value = 'all'
}

function toggleAdvancedFilters(): void {
  advancedFiltersOpen.value = !advancedFiltersOpen.value
}

async function loadProjects(): Promise<void> {
  isLoading.value = true
  message.value = ''

  try {
    const [projectsResponse, gamesResponse] = await Promise.all([fetchProjects(), fetchGames()])

    projects.value = projectsResponse.data
    games.value = gamesResponse.data
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
</script>

<template>
  <AppShell>
    <section class="data-page">
      <header class="data-page__header">
        <h2 class="data-page__title">Проекты</h2>
        <p class="data-page__subtitle">{{ subtitle }}</p>
      </header>

      <p v-if="message" class="data-page__message">{{ message }}</p>

      <section class="game-filters" aria-label="Фильтры проектов">
        <div class="game-filter-top">
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
        </div>

        <div class="game-filter-row game-filter-row--projects">
          <label class="game-filter-field">
            <span class="game-filter-field__label">Игра</span>
            <select v-model="gameFilter" class="game-filter-field__control">
              <option v-for="option in gameFilterOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </label>

          <label class="game-filter-field">
            <span class="game-filter-field__label">Дата релиза</span>
            <span class="game-filter-date-range">
              <input
                v-model="releaseDateFrom"
                class="game-filter-date-range__input"
                type="date"
                :max="releaseDateTo || undefined"
                aria-label="Дата релиза от"
              />
              <span class="game-filter-date-range__separator">-</span>
              <input
                v-model="releaseDateTo"
                class="game-filter-date-range__input"
                type="date"
                :min="releaseDateFrom || undefined"
                aria-label="Дата релиза до"
              />
            </span>
          </label>

          <label class="game-filter-field">
            <span class="game-filter-field__label">Статус</span>
            <select v-model="statusFilter" class="game-filter-field__control">
              <option
                v-for="option in statusFilterOptions"
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
        <div class="project-card-grid" aria-label="Список проектов">
          <RouterLink
            class="project-card project-card--add"
            :to="{ name: 'projects.create' }"
            aria-label="Добавить проект"
          >
            <span class="project-card__add-media" aria-hidden="true"></span>
            <Plus
              class="project-card__add-icon"
              :size="58"
              :stroke-width="2.1"
              aria-hidden="true"
            />
          </RouterLink>

          <div v-if="isLoading" class="project-card project-card--loading">Загрузка...</div>

          <article v-for="project in visibleProjects" :key="project.id" class="project-card">
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
                  <span class="project-card__updated-at">{{ projectUpdatedAt(project) }}</span>
                </span>
              </span>
            </RouterLink>

            <div class="project-card__actions" aria-label="Действия проекта">
              <span class="project-card__status" :class="projectStatusClass(project)">
                {{ project.status_label ?? project.status ?? 'Статус не указан' }}
              </span>

              <RouterLink
                class="icon-action"
                :to="`/projects/${project.id}/edit`"
                aria-label="Редактировать проект"
                title="Редактировать проект"
                @click.stop
              >
                <Pencil :size="16" :stroke-width="2" aria-hidden="true" />
              </RouterLink>

              <button
                v-if="project.can_delete"
                class="icon-action icon-action--danger"
                type="button"
                :disabled="deletingProjectId !== null"
                aria-label="Удалить проект"
                title="Удалить проект"
                @click.stop.prevent="openDeleteModal(project)"
              >
                <Trash2 :size="16" :stroke-width="2" aria-hidden="true" />
              </button>
            </div>
          </article>
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

<style scoped>
.project-card:not(.project-card--add, .project-card--loading) {
  min-height: 0;
  aspect-ratio: auto;
  background: var(--color-surface);
}

.project-card:not(.project-card--add, .project-card--loading):hover,
.project-card:not(.project-card--add, .project-card--loading):focus-within {
  transform: translateY(-2px);
}

.project-card--add {
  grid-template-rows: auto 176px;
  place-items: stretch;
  min-height: 0;
  padding: 0;
  aspect-ratio: auto;
}

.project-card__add-media {
  width: 100%;
  aspect-ratio: 16 / 8;
}

.project-card--add .project-card__add-icon {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
}

.project-card__link {
  display: grid;
  grid-template-rows: auto 176px;
}

.project-card__actions {
  position: absolute;
  top: 10px;
  right: 10px;
  z-index: 2;
  display: flex;
  gap: 7px;
  align-items: center;
}

.project-card__status {
  min-height: 34px;
  color: var(--color-primary-text);
  background: color-mix(in srgb, var(--color-surface) 72%, transparent);
  border: 1px solid color-mix(in srgb, var(--color-border) 72%, transparent);
  backdrop-filter: blur(8px);
}

.project-card__status {
  display: inline-flex;
  align-items: center;
  padding: 0 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 800;
  line-height: 1;
}

.project-card__status--gray {
  color: var(--color-text-muted);
}

.project-card__status--warning {
  color: var(--color-warning);
  border-color: color-mix(in srgb, var(--color-warning) 42%, transparent);
}

.project-card__status--success {
  color: var(--color-success);
  border-color: color-mix(in srgb, var(--color-success) 42%, transparent);
}

.project-card__status--danger {
  color: var(--color-danger);
  border-color: color-mix(in srgb, var(--color-danger) 42%, transparent);
}

.project-card__media {
  aspect-ratio: 16 / 8;
  height: auto;
  min-height: 0;
  background:
    linear-gradient(145deg, transparent 42%, color-mix(in srgb, var(--color-text) 5%, transparent) 43% 57%, transparent 58%),
    linear-gradient(35deg, var(--color-bg-soft), color-mix(in srgb, var(--color-text) 7%, var(--color-bg-soft)));
  border-bottom: 1px solid var(--color-border-soft);
  border-radius: 0;
}

.project-card__media::after {
  display: none;
}

.project-card__image {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.project-card__placeholder {
  display: grid;
  place-items: center;
  width: 58px;
  height: 58px;
  color: var(--color-primary);
  background: color-mix(in srgb, var(--color-surface) 78%, transparent);
  border: 1px solid var(--color-border-soft);
  border-radius: var(--radius-md);
  font-size: 26px;
}

.project-card__body {
  display: flex;
  flex-direction: column;
  gap: 8px;
  overflow: hidden;
  padding: 16px;
}

.project-card__heading {
  display: flex;
  flex-wrap: wrap;
  gap: 4px 8px;
  align-items: baseline;
  min-width: 0;
}

.project-card__title {
  color: var(--color-text);
  font-size: 16px;
  line-height: 1.25;
  overflow-wrap: anywhere;
}

.project-card__author {
  min-width: 0;
  overflow: hidden;
  color: var(--color-text-muted);
  font-size: 13px;
  line-height: 1.25;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.project-card__summary {
  display: -webkit-box;
  overflow: hidden;
  color: var(--color-text-muted);
  font-size: 16px;
  line-height: 1.35;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
}

.project-card__badges {
  display: flex;
  flex-wrap: nowrap;
  gap: 6px;
  min-height: 26px;
  overflow: hidden;
  padding-top: 3px;
}

.project-card__badge {
  display: inline-flex;
  align-items: center;
  min-height: 26px;
  flex: 0 0 auto;
  padding: 3px 9px;
  color: var(--color-primary-text);
  background: var(--color-primary);
  border: 1px solid var(--color-primary);
  border-radius: 999px;
  font-size: 12px;
  line-height: 1;
}

.project-card__footer {
  display: flex;
  align-items: end;
  justify-content: flex-end;
  min-height: 20px;
  margin-top: auto;
  padding-top: 4px;
}

.project-card__updated-at {
  color: var(--color-text-muted);
  font-size: 12px;
  line-height: 1.25;
  white-space: nowrap;
}
</style>
