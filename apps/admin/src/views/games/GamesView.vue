<script setup lang="ts">
import { Pencil, Plus, RotateCcw, SlidersHorizontal, Trash2 } from '@lucide/vue'
import { computed, onMounted, ref } from 'vue'
import AppShell from '@/components/layout/AppShell.vue'
import DeleteModal from '@/components/ui/DeleteModal.vue'
import SearchField from '@/components/ui/SearchField.vue'
import { deleteGame, fetchGames, type GameListItem, type GameStatus } from '@/shared/games/games'
import { pushGameActionNotification } from '@/shared/games/notifications'

type StatusFilter = GameStatus | 'all'
type SortOption = 'name_asc' | 'name_desc' | 'released_at'

const statusFilterOptions: Array<{ value: StatusFilter; label: string }> = [
  { value: 'all', label: 'Все статусы' },
  { value: 'active', label: 'Активна' },
  { value: 'suspended', label: 'Приостановлена' },
  { value: 'blocked', label: 'Заблокирована' },
]
const sortOptions: Array<{ value: SortOption; label: string }> = [
  { value: 'name_asc', label: 'А-Я' },
  { value: 'name_desc', label: 'Я-А' },
  { value: 'released_at', label: 'Дата релиза' },
]
const pageSizeOptions = [10, 20, 30, 40, 50]

const games = ref<GameListItem[]>([])
const search = ref('')
const releaseDateFrom = ref('')
const releaseDateTo = ref('')
const statusFilter = ref<StatusFilter>('all')
const sort = ref<SortOption>('name_asc')
const pageSize = ref(20)
const advancedFiltersOpen = ref(false)
const message = ref('')
const isLoading = ref(false)
const actionGameId = ref<number | null>(null)
const pendingDeleteGame = ref<GameListItem | null>(null)

const hasActiveFilters = computed(() =>
  Boolean(
    search.value.trim() ||
    releaseDateFrom.value ||
    releaseDateTo.value ||
    statusFilter.value !== 'all',
  ),
)
const filteredGames = computed(() =>
  games.value.filter(
    (game) => matchesSearch(game) && matchesReleaseDateFilter(game) && matchesStatusFilter(game),
  ),
)
const sortedGames = computed(() =>
  [...filteredGames.value].sort((firstGame, secondGame) => {
    if (sort.value === 'name_desc') {
      return secondGame.name.localeCompare(firstGame.name, 'ru-RU')
    }

    if (sort.value === 'released_at') {
      return releaseTimestamp(secondGame) - releaseTimestamp(firstGame)
    }

    return firstGame.name.localeCompare(secondGame.name, 'ru-RU')
  }),
)
const visibleGames = computed(() => sortedGames.value.slice(0, pageSize.value))
const subtitle = computed(() => {
  if (hasActiveFilters.value && filteredGames.value.length !== games.value.length) {
    return `Всего игр: ${games.value.length}. Найдено: ${filteredGames.value.length}.`
  }

  return `Всего игр: ${games.value.length}.`
})
const deleteModalDescription = computed(() => {
  const name = pendingDeleteGame.value?.name ?? 'игра'

  return `Игра ${name} будет удалена. Это действие скроет ее из списка.`
})

function matchesSearch(game: GameListItem): boolean {
  const searchQuery = search.value.trim().toLocaleLowerCase('ru-RU')

  if (!searchQuery) {
    return true
  }

  return game.name.toLocaleLowerCase('ru-RU').includes(searchQuery)
}

function matchesReleaseDateFilter(game: GameListItem): boolean {
  if (!releaseDateFrom.value && !releaseDateTo.value) {
    return true
  }

  if (!game.released_at) {
    return false
  }

  const releaseDate = new Date(game.released_at)
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

function matchesStatusFilter(game: GameListItem): boolean {
  return statusFilter.value === 'all' || game.status === statusFilter.value
}

function releaseTimestamp(game: GameListItem): number {
  if (!game.released_at) {
    return 0
  }

  return new Date(game.released_at).getTime()
}

function resetFilters(): void {
  search.value = ''
  releaseDateFrom.value = ''
  releaseDateTo.value = ''
  statusFilter.value = 'all'
}

function toggleAdvancedFilters(): void {
  advancedFiltersOpen.value = !advancedFiltersOpen.value
}

async function loadGames(): Promise<void> {
  isLoading.value = true
  message.value = ''

  try {
    const response = await fetchGames()

    games.value = response.data
  } catch {
    message.value = 'Не удалось загрузить игры.'
  } finally {
    isLoading.value = false
  }
}

function softDelete(game: GameListItem): void {
  pendingDeleteGame.value = game
}

function closeDeleteModal(): void {
  if (actionGameId.value !== null) {
    return
  }

  pendingDeleteGame.value = null
}

async function confirmDelete(): Promise<void> {
  if (!pendingDeleteGame.value) {
    return
  }

  const gameId = pendingDeleteGame.value.id

  actionGameId.value = gameId
  message.value = ''

  try {
    await deleteGame(gameId)
    await loadGames()
    pushGameActionNotification('deleted')
  } catch {
    message.value = 'Не удалось удалить игру.'
  } finally {
    actionGameId.value = null
    pendingDeleteGame.value = null
  }
}

onMounted(() => {
  void loadGames()
})
</script>

<template>
  <AppShell>
    <section class="data-page">
      <header class="data-page__header">
        <h2 class="data-page__title">Игры</h2>
        <p class="data-page__subtitle">{{ subtitle }}</p>
      </header>

      <p v-if="message" class="data-page__message">{{ message }}</p>

      <section class="game-filters" aria-label="Фильтры игр">
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

        <div class="game-filter-row">
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
        <div class="game-card-grid" aria-label="Список игр">
          <RouterLink
            class="game-card game-card--add"
            :to="{ name: 'games.create' }"
            aria-label="Добавить игру"
          >
            <Plus class="game-card__add-icon" :size="58" :stroke-width="2.1" aria-hidden="true" />
          </RouterLink>

          <div v-if="isLoading" class="game-card game-card--loading">Загрузка...</div>

          <article v-for="game in visibleGames" :key="game.id" class="game-card">
            <RouterLink
              class="game-card__link"
              :to="{ name: 'games.show', params: { id: String(game.id) } }"
              :aria-label="`Просмотреть игру ${game.name}`"
            >
              <span class="game-card__media">
                <img
                  v-if="game.logo_url"
                  class="game-card__logo"
                  :src="game.logo_url"
                  :alt="game.name"
                />
                <span v-else class="game-card__placeholder">{{
                  game.name.slice(0, 1).toUpperCase()
                }}</span>
                <span class="game-card__name">{{ game.name }}</span>
              </span>
            </RouterLink>

            <div class="game-card__actions" aria-label="Действия игры">
              <RouterLink
                class="icon-action"
                :to="{ name: 'games.edit', params: { id: String(game.id) } }"
                aria-label="Редактировать игру"
                title="Редактировать игру"
              >
                <Pencil :size="16" :stroke-width="2" aria-hidden="true" />
              </RouterLink>

              <button
                class="icon-action icon-action--danger"
                type="button"
                :disabled="actionGameId === game.id"
                aria-label="Удалить игру"
                title="Удалить игру"
                @click="softDelete(game)"
              >
                <Trash2 :size="16" :stroke-width="2" aria-hidden="true" />
              </button>
            </div>
          </article>

          <div v-if="!isLoading && visibleGames.length === 0" class="game-card-empty">
            Игры не найдены
          </div>
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
      :open="pendingDeleteGame !== null"
      title="Удалить игру"
      :description="deleteModalDescription"
      :loading="actionGameId !== null"
      @cancel="closeDeleteModal"
      @confirm="confirmDelete"
    />
  </AppShell>
</template>
