<script setup lang="ts">
import { ArrowLeft, Pencil } from '@lucide/vue'
import StarterKit from '@tiptap/starter-kit'
import { EditorContent, useEditor } from '@tiptap/vue-3'
import { computed, onBeforeUnmount, ref } from 'vue'
import { useRoute } from 'vue-router'
import AppShell from '@/components/layout/AppShell.vue'
import { fetchGame, type GameDetail } from '@/shared/games/games'
import '@/assets/styles/game-view.css'

const route = useRoute()
const game = ref<GameDetail | null>(null)
const isLoading = ref(false)
const message = ref('')

const editor = useEditor({
  extensions: [StarterKit],
  content: '',
  editable: false,
})

const title = computed(() => game.value?.name ?? 'Игра')
const statusClass = computed(() => `game-view-status--${game.value?.status_color ?? 'gray'}`)

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
    game.value = await fetchGame(String(route.params.id))
    route.meta.breadcrumbLabel = game.value.name
    setDescription(game.value.description)
  } catch {
    message.value = 'Не удалось загрузить игру.'
  } finally {
    isLoading.value = false
  }
}

onBeforeUnmount(() => {
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
          <img v-if="game.banner_url" :src="game.banner_url" :alt="game.name">
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
              <img v-if="game.logo_url" :src="game.logo_url" :alt="game.name">
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
        </article>
      </template>
    </section>
  </AppShell>
</template>
