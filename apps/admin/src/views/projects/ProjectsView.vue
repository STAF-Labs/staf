<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Plus, RotateCcw, SlidersHorizontal } from '@lucide/vue'
import AppShell from '@/components/layout/AppShell.vue'
import SearchField from '@/components/ui/SearchField.vue'
import { fetchGames, type GameListItem } from '@/shared/games/games'
import { fetchProjects, type ProjectListItem, type ProjectStatus } from '@/shared/projects/projects'

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
              :to="{ name: 'projects.edit', params: { id: String(project.id) } }"
              :aria-label="`Редактировать проект ${project.title}`"
            >
              <span class="project-card__media">
                <span class="project-card__placeholder">{{
                  project.title.slice(0, 1).toUpperCase()
                }}</span>
                <span class="project-card__name">{{ project.title }}</span>
              </span>
            </RouterLink>
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
  </AppShell>
</template>
