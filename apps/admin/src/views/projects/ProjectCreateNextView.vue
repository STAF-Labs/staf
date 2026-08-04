<script setup lang="ts">
import { ArrowLeft, ArrowRight, Bold, Heading2, Italic, List, ListOrdered } from '@lucide/vue'
import StarterKit from '@tiptap/starter-kit'
import { EditorContent, useEditor } from '@tiptap/vue-3'
import { computed, nextTick, onBeforeUnmount, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppShell from '@/components/layout/AppShell.vue'
import ProjectCompletionCard from '@/components/ProjectCompletionCard.vue'
import StepIndicator from '@/components/ui/StepIndicator.vue'
import {
  calculateProjectPercentageComplete,
  hydrateProjectCreateDraft,
  projectCreateDraft,
  projectCreateSteps,
} from '@/shared/projects/project-create'
import { updateProjectDraft } from '@/shared/projects/projects'

const route = useRoute()
const router = useRouter()
const descriptionError = ref('')
const message = ref('')
const isSaving = ref(false)
const gameId = String(route.params.gameId ?? '')
const routeProjectId = computed(() => {
  const projectId = Number(route.query.projectId)

  return Number.isInteger(projectId) && projectId > 0 ? projectId : null
})

const descriptionEditor = useEditor({
  extensions: [StarterKit],
  content: '',
  onUpdate: ({ editor }) => {
    projectCreateDraft.description = editor.getJSON()
    projectCreateDraft.descriptionFilled = !editor.isEmpty
    descriptionError.value = ''
  },
})

async function loadDraft(): Promise<void> {
  try {
    if (routeProjectId.value !== null) {
      await hydrateProjectCreateDraft(routeProjectId.value)
    }
  } catch {
    message.value = 'Не удалось загрузить сохранённый черновик.'

    return
  }

  await nextTick()

  if (projectCreateDraft.description && descriptionEditor.value) {
    descriptionEditor.value.commands.setContent(projectCreateDraft.description)
    projectCreateDraft.descriptionFilled = !descriptionEditor.value.isEmpty
  }
}

async function continueToNextStep(): Promise<void> {
  const editor = descriptionEditor.value
  descriptionError.value = ''

  if (!editor || editor.isEmpty) {
    descriptionError.value = 'Добавьте описание проекта.'

    return
  }

  projectCreateDraft.description = editor.getJSON()
  projectCreateDraft.descriptionFilled = true

  if (!projectCreateDraft.projectId) {
    message.value = 'Сначала сохраните основные данные проекта.'

    return
  }

  isSaving.value = true
  message.value = ''

  try {
    await updateProjectDraft(projectCreateDraft.projectId, {
      description: projectCreateDraft.description,
      percentageComplete: calculateProjectPercentageComplete(),
    })

    await router.push({
      name: 'projects.create.licence',
      params: { gameId },
      query: { projectId: String(projectCreateDraft.projectId) },
    })
  } catch (error) {
    message.value = error instanceof Error ? error.message : 'Не удалось сохранить описание.'
  } finally {
    isSaving.value = false
  }
}

onBeforeUnmount(() => {
  descriptionEditor.value?.destroy()
})

void loadDraft()
</script>

<template>
  <AppShell>
    <section class="data-page">
      <header class="data-page__header project-create-description__header">
        <RouterLink
          class="data-page__back-link project-create-description__back"
          :to="{
            name: 'projects.create.details',
            params: { gameId },
            query: routeProjectId ? { projectId: String(routeProjectId) } : undefined,
          }"
          aria-label="Вернуться к основным данным"
        >
          <ArrowLeft :size="18" :stroke-width="1.9" aria-hidden="true" />
          <span>Основные данные</span>
        </RouterLink>

        <div>
          <h2 class="data-page__title">Описание проекта</h2>
          <p class="data-page__subtitle">Добавьте подробное описание проекта.</p>
        </div>
      </header>

      <StepIndicator
        :steps="projectCreateSteps"
        :current-step="3"
        aria-label="Этапы создания проекта"
      />

      <p v-if="message" class="data-page__message">{{ message }}</p>

      <div class="project-create-layout">
        <form
          class="project-form-panel form project-create-description__form"
          novalidate
          @submit.prevent="continueToNextStep"
        >
        <div class="form-field project-create-description__field">
          <span class="form-label">Описание</span>

          <div
            class="game-editor"
            :class="{ 'project-create-description__editor--invalid': descriptionError }"
          >
            <div v-if="descriptionEditor" class="game-editor__toolbar">
              <button
                class="game-editor__button"
                :class="{ 'is-active': descriptionEditor.isActive('heading', { level: 2 }) }"
                type="button"
                title="Заголовок"
                @click="descriptionEditor.chain().focus().toggleHeading({ level: 2 }).run()"
              >
                <Heading2 :size="17" :stroke-width="1.9" aria-hidden="true" />
              </button>
              <button
                class="game-editor__button"
                :class="{ 'is-active': descriptionEditor.isActive('bold') }"
                type="button"
                title="Жирный"
                @click="descriptionEditor.chain().focus().toggleBold().run()"
              >
                <Bold :size="17" :stroke-width="2.2" aria-hidden="true" />
              </button>
              <button
                class="game-editor__button"
                :class="{ 'is-active': descriptionEditor.isActive('italic') }"
                type="button"
                title="Курсив"
                @click="descriptionEditor.chain().focus().toggleItalic().run()"
              >
                <Italic :size="17" :stroke-width="2.2" aria-hidden="true" />
              </button>
              <button
                class="game-editor__button"
                :class="{ 'is-active': descriptionEditor.isActive('bulletList') }"
                type="button"
                title="Маркированный список"
                @click="descriptionEditor.chain().focus().toggleBulletList().run()"
              >
                <List :size="17" :stroke-width="1.9" aria-hidden="true" />
              </button>
              <button
                class="game-editor__button"
                :class="{ 'is-active': descriptionEditor.isActive('orderedList') }"
                type="button"
                title="Нумерованный список"
                @click="descriptionEditor.chain().focus().toggleOrderedList().run()"
              >
                <ListOrdered :size="17" :stroke-width="1.9" aria-hidden="true" />
              </button>
            </div>
            <EditorContent class="game-editor__body" :editor="descriptionEditor" />
          </div>

          <span v-if="descriptionError" class="field-error">{{ descriptionError }}</span>
        </div>

        <div class="project-create-description__actions">
          <RouterLink
            class="button"
            :to="{
              name: 'projects.create.details',
              params: { gameId },
              query: routeProjectId ? { projectId: String(routeProjectId) } : undefined,
            }"
          >
            <ArrowLeft :size="18" :stroke-width="1.9" aria-hidden="true" />
            <span>Назад</span>
          </RouterLink>

          <button class="button button-primary" type="submit" :disabled="isSaving">
            <span>{{ isSaving ? 'Сохранение…' : 'Далее' }}</span>
            <ArrowRight :size="18" :stroke-width="1.9" aria-hidden="true" />
          </button>
        </div>
        </form>

        <ProjectCompletionCard />
      </div>
    </section>
  </AppShell>
</template>

<style scoped>
.project-create-description__header {
  gap: 12px;
}

.project-create-description__back {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.project-create-description__form {
  width: 100%;
  max-width: none;
  gap: 0;
}

.project-create-description__field {
  padding-bottom: 24px;
}

.project-create-description__field > .game-editor {
  width: 100%;
}

.project-create-description__editor--invalid {
  border-color: var(--color-danger);
}

.project-create-description__actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding-top: 24px;
  border-top: 1px solid var(--color-border-soft);
}

.project-create-description__actions .button {
  flex: 0 0 auto;
  width: auto;
}

@media (max-width: 520px) {
  .project-create-description__actions {
    display: grid;
    grid-template-columns: 1fr;
  }

  .project-create-description__actions .button {
    width: 100%;
  }
}
</style>
