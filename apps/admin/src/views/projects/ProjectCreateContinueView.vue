<script setup lang="ts">
import { ArrowLeft, Save } from '@lucide/vue'
import StarterKit from '@tiptap/starter-kit'
import { EditorContent, useEditor } from '@tiptap/vue-3'
import { computed, nextTick, onBeforeUnmount, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppShell from '@/components/layout/AppShell.vue'
import StepIndicator from '@/components/ui/StepIndicator.vue'
import {
  fetchGame,
  fetchGameContentTypes,
  fetchGameDimensions,
  type GameDimension,
} from '@/shared/games/games'
import { createProjectFromWizard, fetchProjectOwnerOptions } from '@/shared/projects/projects'
import {
  projectCreateDraft,
  projectCreateSteps,
  resetProjectCreateDraft,
} from '@/shared/projects/project-create'

const route = useRoute()
const router = useRouter()
const gameId = String(route.params.gameId ?? '')
const gameName = ref('')
const contentTypeName = ref('')
const dimensions = ref<GameDimension[]>([])
const message = ref('')
const isLoading = ref(false)
const isSaving = ref(false)
const logoPreviewUrl = URL.createObjectURL(projectCreateDraft.logo as File)

const selectedDimensions = computed(() =>
  dimensions.value
    .map((dimension) => ({
      id: dimension.id,
      name: dimension.name,
      values: (projectCreateDraft.dimensionValueIds[dimension.id] ?? []).map((valueId) =>
        dimensionValuePath(dimension, valueId),
      ),
    }))
    .filter((dimension) => dimension.values.length > 0),
)

const summaryEditor = useEditor({
  extensions: [StarterKit],
  content: '',
  editable: false,
})
const descriptionEditor = useEditor({
  extensions: [StarterKit],
  content: '',
  editable: false,
})

void nextTick(() => {
  if (summaryEditor.value && projectCreateDraft.summary) {
    summaryEditor.value.commands.setContent(projectCreateDraft.summary)
  }

  if (descriptionEditor.value && projectCreateDraft.description) {
    descriptionEditor.value.commands.setContent(projectCreateDraft.description)
  }
})

async function loadPreviewContext(): Promise<void> {
  isLoading.value = true

  try {
    const [game, contentTypesResponse, dimensionsResponse] = await Promise.all([
      fetchGame(gameId),
      fetchGameContentTypes(gameId),
      fetchGameDimensions(Number(gameId), projectCreateDraft.gameContentTypeId as number),
    ])

    gameName.value = game.name
    contentTypeName.value =
      contentTypesResponse.data.find(
        (contentType) => contentType.id === projectCreateDraft.gameContentTypeId,
      )?.content_type_name ?? 'Тип контента'
    dimensions.value = dimensionsResponse.data.filter(
      (dimension) => dimension.is_active && dimension.applies_to === 'project',
    )
  } catch {
    message.value = 'Не удалось загрузить данные для подтверждения.'
  } finally {
    isLoading.value = false
  }
}

function dimensionValuePath(dimension: GameDimension, valueId: number): string {
  const names: string[] = []
  let value = dimension.values.find((item) => item.id === valueId)

  while (value) {
    names.unshift(value.name)
    value = value.parent_id
      ? dimension.values.find((item) => item.id === value?.parent_id)
      : undefined
  }

  return names.join(' → ')
}

async function saveProject(): Promise<void> {
  if (!projectCreateDraft.gameContentTypeId || !projectCreateDraft.logo) {
    message.value = 'Черновик проекта заполнен не полностью.'

    return
  }

  isSaving.value = true
  message.value = ''

  try {
    const ownerOptions = await fetchProjectOwnerOptions()
    const owner = ownerOptions.data[0]

    if (!owner) {
      throw new Error('Не удалось определить владельца проекта.')
    }

    await createProjectFromWizard({
      ownerableType: owner.type,
      ownerableId: owner.id,
      gameContentTypeId: projectCreateDraft.gameContentTypeId,
      title: projectCreateDraft.title,
      summary: projectCreateDraft.summary,
      description: projectCreateDraft.description,
      tags: projectCreateDraft.tags,
      websiteUrls: projectCreateDraft.websiteUrls.filter(Boolean),
      logo: projectCreateDraft.logo,
      licenceName: projectCreateDraft.licenceName || null,
      licence: projectCreateDraft.licence,
      dimensionValueIds: [...new Set(Object.values(projectCreateDraft.dimensionValueIds).flat())],
    })

    resetProjectCreateDraft()
    await router.push({ name: 'projects.index' })
  } catch (error) {
    message.value = error instanceof Error ? error.message : 'Не удалось создать проект.'
  } finally {
    isSaving.value = false
  }
}

onBeforeUnmount(() => {
  URL.revokeObjectURL(logoPreviewUrl)
  summaryEditor.value?.destroy()
  descriptionEditor.value?.destroy()
})

void loadPreviewContext()
</script>

<template>
  <AppShell>
    <section class="data-page">
      <header class="data-page__header project-confirmation__header">
        <RouterLink
          class="data-page__back-link project-confirmation__back"
          :to="{
            name: 'projects.create.licence',
            params: { gameId },
          }"
          aria-label="Вернуться к лицензии"
        >
          <ArrowLeft :size="18" :stroke-width="1.9" aria-hidden="true" />
          <span>Лицензия</span>
        </RouterLink>

        <div>
          <h2 class="data-page__title">Подтверждение создания</h2>
          <p class="data-page__subtitle">Проверьте данные проекта перед сохранением.</p>
        </div>
      </header>

      <StepIndicator
        :steps="projectCreateSteps"
        :current-step="5"
        aria-label="Этапы создания проекта"
      />

      <p v-if="message" class="data-page__message">{{ message }}</p>

      <div class="project-form-panel project-confirmation">
        <p v-if="isLoading" class="project-form-loading">Загрузка...</p>

        <div class="project-confirmation__group project-confirmation__identity">
          <img class="project-confirmation__logo" :src="logoPreviewUrl" alt="Логотип проекта" />
          <div>
            <span>{{ gameName }} · {{ contentTypeName }}</span>
            <h3>{{ projectCreateDraft.title }}</h3>
          </div>
        </div>

        <div class="project-confirmation__group">
          <span class="form-label">Краткое описание</span>
          <EditorContent class="project-confirmation__editor" :editor="summaryEditor" />
        </div>

        <div v-if="selectedDimensions.length > 0" class="project-confirmation__group">
          <span class="form-label">Настройки проекта</span>
          <dl class="project-confirmation__dimensions">
            <div v-for="dimension in selectedDimensions" :key="dimension.id">
              <dt>{{ dimension.name }}</dt>
              <dd>{{ dimension.values.join(', ') }}</dd>
            </div>
          </dl>
        </div>

        <div v-if="projectCreateDraft.websiteUrls.length > 0" class="project-confirmation__group">
          <span class="form-label">Ссылки</span>
          <ul class="project-confirmation__list">
            <li v-for="url in projectCreateDraft.websiteUrls.filter(Boolean)" :key="url">
              {{ url }}
            </li>
          </ul>
        </div>

        <div v-if="projectCreateDraft.tags.length > 0" class="project-confirmation__group">
          <span class="form-label">Теги</span>
          <div class="project-tag-list">
            <span v-for="tag in projectCreateDraft.tags" :key="tag" class="project-tag">
              {{ tag }}
            </span>
          </div>
        </div>

        <div class="project-confirmation__group">
          <span class="form-label">Описание</span>
          <EditorContent class="project-confirmation__editor" :editor="descriptionEditor" />
        </div>

        <div class="project-confirmation__group">
          <span class="form-label">Лицензия</span>
          <dl class="project-confirmation__licence">
            <div>
              <dt>Название</dt>
              <dd>{{ projectCreateDraft.licenceName || 'Не выбрана' }}</dd>
            </div>
            <div>
              <dt>Файл</dt>
              <dd>{{ projectCreateDraft.licence?.name ?? 'Не приложен' }}</dd>
            </div>
          </dl>
        </div>

        <div class="project-confirmation__actions">
          <RouterLink
            class="button"
            :to="{
              name: 'projects.create.licence',
              params: { gameId },
            }"
          >
            <ArrowLeft :size="18" :stroke-width="1.9" aria-hidden="true" />
            <span>Назад</span>
          </RouterLink>

          <button
            class="button button-primary"
            type="button"
            :disabled="isSaving"
            @click="saveProject"
          >
            <Save :size="18" :stroke-width="1.9" aria-hidden="true" />
            <span>{{ isSaving ? 'Сохранение…' : 'Сохранить' }}</span>
          </button>
        </div>
      </div>
    </section>
  </AppShell>
</template>

<style scoped>
.project-confirmation__header {
  gap: 12px;
}

.project-confirmation__back {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.project-confirmation {
  width: 100%;
  max-width: none;
  padding: 22px;
}

.project-confirmation__group {
  display: grid;
  gap: 8px;
  padding: 24px 0;
  border-bottom: 1px solid var(--color-border-soft);
}

.project-confirmation__group:first-of-type {
  padding-top: 0;
}

.project-confirmation__identity {
  grid-template-columns: 112px minmax(0, 1fr);
  align-items: center;
}

.project-confirmation__identity h3,
.project-confirmation__identity span,
.project-confirmation__group p {
  margin: 0;
}

.project-confirmation__identity h3 {
  margin-top: 6px;
  font-size: 24px;
}

.project-confirmation__identity span,
.project-confirmation__group p {
  color: var(--color-text-muted);
  font-size: 13px;
}

.project-confirmation__logo {
  width: 112px;
  height: 112px;
  object-fit: cover;
  border-radius: var(--radius-md);
}

.project-confirmation__editor {
  width: 100%;
  min-height: 72px;
  padding: 14px;
  background: var(--color-bg-soft);
  border: 1px solid var(--color-border-soft);
  border-radius: var(--radius-md);
}

.project-confirmation__dimensions {
  display: grid;
  gap: 0;
  margin: 0;
}

.project-confirmation__dimensions > div {
  display: grid;
  grid-template-columns: minmax(180px, 240px) minmax(0, 1fr);
  gap: 16px;
  padding: 10px 0;
  border-bottom: 1px solid var(--color-border-soft);
}

.project-confirmation__dimensions > div:last-child {
  border-bottom: 0;
}

.project-confirmation__dimensions dt {
  font-weight: 700;
}

.project-confirmation__dimensions dd {
  margin: 0;
  color: var(--color-text-muted);
}

.project-confirmation__licence {
  display: grid;
  gap: 8px;
  margin: 0;
}

.project-confirmation__licence div {
  display: grid;
  grid-template-columns: 96px minmax(0, 1fr);
  gap: 12px;
}

.project-confirmation__licence dt {
  color: var(--color-text-muted);
}

.project-confirmation__licence dd {
  margin: 0;
}

.project-confirmation__list {
  display: grid;
  gap: 6px;
  margin: 0;
  padding-left: 20px;
  color: var(--color-text-muted);
}

.project-confirmation :deep(.project-tag) {
  cursor: default;
}

.project-confirmation__actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding-top: 24px;
}

.project-confirmation__actions .button {
  flex: 0 0 auto;
  width: auto;
}

@media (max-width: 600px) {
  .project-confirmation__identity,
  .project-confirmation__dimensions > div,
  .project-confirmation__licence div {
    grid-template-columns: 1fr;
  }

  .project-confirmation__actions {
    display: grid;
    grid-template-columns: 1fr;
  }

  .project-confirmation__actions .button {
    width: 100%;
  }
}
</style>
