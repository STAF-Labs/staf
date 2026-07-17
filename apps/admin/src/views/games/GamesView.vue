<script setup lang="ts">
import { Eye, Pencil, Plus, Trash2 } from '@lucide/vue'
import { computed, onMounted, ref } from 'vue'
import AppShell from '@/components/layout/AppShell.vue'
import DeleteModal from '@/components/ui/DeleteModal.vue'
import { deleteGame, fetchGames, type GameListItem } from '@/shared/games/games'
import '@/assets/styles/card.css'

const games = ref<GameListItem[]>([])
const message = ref('')
const isLoading = ref(false)
const actionGameId = ref<number | null>(null)
const pendingDeleteGame = ref<GameListItem | null>(null)

const subtitle = computed(() => `Всего игр: ${games.value.length}.`)
const deleteModalDescription = computed(() => {
  const name = pendingDeleteGame.value?.name ?? 'игра'

  return `Игра ${name} будет удалена. Это действие скроет ее из списка.`
})

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
    message.value = 'Игра удалена.'
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

      <div class="game-card-grid" aria-label="Список игр">
        <RouterLink class="game-card game-card--add" :to="{ name: 'games.create' }" aria-label="Добавить игру">
          <Plus class="game-card__add-icon" :size="58" :stroke-width="2.1" aria-hidden="true" />
        </RouterLink>

        <div v-if="isLoading" class="game-card game-card--loading">
          Загрузка...
        </div>

        <article
          v-for="game in games"
          :key="game.id"
          class="game-card"
        >
          <RouterLink
            class="game-card__link"
            :to="{ name: 'games.edit', params: { id: String(game.id) } }"
            :aria-label="`Редактировать игру ${game.name}`"
          >
            <span class="game-card__media">
              <img
                v-if="game.logo_url"
                class="game-card__logo"
                :src="game.logo_url"
                :alt="game.name"
              >
              <span v-else class="game-card__placeholder">{{ game.name.slice(0, 1).toUpperCase() }}</span>
            </span>

            <span class="game-card__name">{{ game.name }}</span>
          </RouterLink>

          <div class="game-card__actions" aria-label="Действия игры">
            <RouterLink
              class="game-card__action"
              :to="{ name: 'games.show', params: { id: String(game.id) } }"
              aria-label="Просмотр игры"
              title="Просмотр игры"
            >
              <Eye :size="16" :stroke-width="2" aria-hidden="true" />
            </RouterLink>

            <RouterLink
              class="game-card__action"
              :to="{ name: 'games.edit', params: { id: String(game.id) } }"
              aria-label="Редактировать игру"
              title="Редактировать игру"
            >
              <Pencil :size="16" :stroke-width="2" aria-hidden="true" />
            </RouterLink>

            <button
              class="game-card__action game-card__action--danger"
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
