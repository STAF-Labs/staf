<script setup lang="ts">
import { ArrowLeft } from '@lucide/vue'
import { computed, onMounted, ref } from 'vue'
import AppShell from '@/components/layout/AppShell.vue'
import SearchField from '@/components/ui/SearchField.vue'
import StepIndicator from '@/components/ui/StepIndicator.vue'
import { fetchGames, type GameListItem } from '@/shared/games/games'
import { projectCreateSteps } from '@/shared/projects/project-create'

const games = ref<GameListItem[]>([])
const search = ref('')
const message = ref('')
const isLoading = ref(false)

const visibleGames = computed(() => {
  const searchQuery = search.value.trim().toLocaleLowerCase('ru-RU')
  const filteredGames = searchQuery
    ? games.value.filter((game) => game.name.toLocaleLowerCase('ru-RU').includes(searchQuery))
    : games.value

  return [...filteredGames].sort((firstGame, secondGame) =>
    firstGame.name.localeCompare(secondGame.name, 'ru-RU'),
  )
})

async function loadGames(): Promise<void> {
  isLoading.value = true
  message.value = ''

  try {
    const response = await fetchGames()
    games.value = response.data
  } catch (error) {
    message.value = error instanceof Error ? error.message : 'Не удалось загрузить список игр.'
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  void loadGames()
})
</script>

<template>
  <AppShell>
    <section class="data-page">
      <header class="data-page__header project-game-select__header">
        <RouterLink
          class="data-page__back-link project-game-select__back"
          :to="{ name: 'projects.index' }"
          aria-label="Назад к проектам"
        >
          <ArrowLeft :size="18" :stroke-width="1.9" aria-hidden="true" />
          <span>Проекты</span>
        </RouterLink>

        <div>
          <h2 class="data-page__title">Выберите игру</h2>
          <p class="data-page__subtitle">Для какой игры создаётся проект?</p>
        </div>
      </header>

      <StepIndicator
        :steps="projectCreateSteps"
        :current-step="1"
        aria-label="Этапы создания проекта"
      />

      <p v-if="message" class="data-page__message">{{ message }}</p>

      <section class="game-filters project-game-select__search" aria-label="Поиск игры">
        <SearchField
          v-model="search"
          placeholder="Поиск по названию игры"
          aria-label="Поиск по названию игры"
          :disabled="isLoading"
        />
      </section>

      <div class="game-card-grid" aria-label="Выбор игры">
        <div v-if="isLoading" class="game-card game-card--loading">Загрузка...</div>

        <article v-for="game in visibleGames" :key="game.id" class="game-card">
          <RouterLink
            class="game-card__link"
            :to="{
              name: 'projects.create.details',
              params: { gameId: String(game.id) },
            }"
            :aria-label="`Выбрать игру ${game.name}`"
          >
            <span class="game-card__media">
              <img
                v-if="game.logo_url"
                class="game-card__logo"
                :src="game.logo_url"
                :alt="game.name"
              />
              <span v-else class="game-card__placeholder">
                {{ game.name.slice(0, 1).toUpperCase() }}
              </span>
              <span class="game-card__name">{{ game.name }}</span>
            </span>
          </RouterLink>
        </article>

        <div v-if="!isLoading && visibleGames.length === 0" class="game-card-empty">
          {{ search.trim() ? 'Игры не найдены' : 'Нет доступных игр' }}
        </div>
      </div>
    </section>
  </AppShell>
</template>

<style scoped>
.project-game-select__header {
  gap: 12px;
}

.project-game-select__back {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.project-game-select__search {
  max-width: 680px;
  margin-bottom: 4px;
}

@media (max-width: 640px) {
  .project-game-select__search {
    max-width: none;
  }
}
</style>
