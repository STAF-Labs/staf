<script setup lang="ts">
import { ArrowLeft, Save } from '@lucide/vue'
import { computed, onBeforeUnmount, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppShell from '@/components/layout/AppShell.vue'
import ProjectCompletionCard from '@/components/ProjectCompletionCard.vue'
import RichTextRenderer from '@/components/ui/RichTextRenderer.vue'
import StepIndicator from '@/components/ui/StepIndicator.vue'
import {
  fetchGame,
  fetchGameContentTypes,
  fetchGameDimensions,
  type GameDimension,
} from '@/shared/games/games'
import { updateProjectDraft } from '@/shared/projects/projects'
import {
  calculateProjectPercentageComplete,
  hydrateProjectCreateDraft,
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
const logoPreviewUrl = ref<string | null>(null)
const percentageComplete = computed(() => calculateProjectPercentageComplete())
const routeProjectId = computed(() => {
  const projectId = Number(route.query.projectId)

  return Number.isInteger(projectId) && projectId > 0 ? projectId : null
})

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

async function loadPreviewContext(): Promise<void> {
  isLoading.value = true

  try {
    if (routeProjectId.value !== null) {
      await hydrateProjectCreateDraft(routeProjectId.value)
    }

    logoPreviewUrl.value = projectCreateDraft.logo
      ? URL.createObjectURL(projectCreateDraft.logo)
      : projectCreateDraft.logoUrl

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
    projectCreateDraft.requiredProjectDimensionIds = dimensions.value
      .filter((dimension) => dimension.is_required)
      .map((dimension) => dimension.id)

    if (projectCreateDraft.persistedDimensionValueIds.length > 0) {
      for (const dimension of dimensions.value) {
        const availableValueIds = new Set(dimension.values.map((value) => value.id))
        const selectedValueIds = projectCreateDraft.persistedDimensionValueIds.filter((valueId) =>
          availableValueIds.has(valueId),
        )

        if (selectedValueIds.length > 0) {
          projectCreateDraft.dimensionValueIds[dimension.id] = selectedValueIds
        }
      }

      projectCreateDraft.persistedDimensionValueIds = []
    }
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
  if (!projectCreateDraft.projectId || percentageComplete.value !== 100) {
    message.value = 'Черновик проекта заполнен не полностью.'

    return
  }

  isSaving.value = true
  message.value = ''

  try {
    await updateProjectDraft(projectCreateDraft.projectId, {
      percentageComplete: percentageComplete.value,
      publicationStatus: 'public',
      status: 'published',
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
  if (projectCreateDraft.logo && logoPreviewUrl.value) {
    URL.revokeObjectURL(logoPreviewUrl.value)
  }
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
            query: routeProjectId ? { projectId: String(routeProjectId) } : undefined,
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

      <div class="project-create-layout">
        <div class="project-form-panel project-confirmation">
        <p v-if="isLoading" class="project-form-loading">Загрузка...</p>

        <div class="project-confirmation__group project-confirmation__identity">
          <img
            class="project-confirmation__logo"
            :src="logoPreviewUrl ?? undefined"
            alt="Логотип проекта"
          />
          <div>
            <span>{{ gameName }} · {{ contentTypeName }}</span>
            <h3>{{ projectCreateDraft.title }}</h3>
          </div>
        </div>

        <div class="project-confirmation__group">
          <span class="form-label">Краткое описание</span>
          <RichTextRenderer
            class="project-confirmation__editor"
            :value="projectCreateDraft.summary"
            empty-text="Краткое описание не заполнено."
          />
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
          <RichTextRenderer
            class="project-confirmation__editor"
            :value="projectCreateDraft.description"
            empty-text="Описание не заполнено."
          />
        </div>

        <div class="project-confirmation__group">
          <span class="form-label">Лицензия</span>
          <p>{{ projectCreateDraft.licenceName || 'Не выбрана' }}</p>
        </div>

        <div class="project-confirmation__actions">
          <RouterLink
            class="button"
            :to="{
              name: 'projects.create.licence',
              params: { gameId },
              query: routeProjectId ? { projectId: String(routeProjectId) } : undefined,
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
            <span>{{ isSaving ? 'Публикация…' : 'Опубликовать' }}</span>
          </button>
        </div>
        </div>

        <ProjectCompletionCard />
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
  .project-confirmation__dimensions > div {
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
