<script setup lang="ts">
import { ArrowLeft, Bold, Heading2, Italic, List, ListOrdered, Plus, Save, Trash2 } from '@lucide/vue'
import StarterKit from '@tiptap/starter-kit'
import { EditorContent, useEditor } from '@tiptap/vue-3'
import type { ComponentPublicInstance } from 'vue'
import { computed, nextTick, onBeforeUnmount, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppShell from '@/components/layout/AppShell.vue'
import {
  createProject,
  fetchProject,
  fetchProjectContentTypes,
  fetchProjectOwnerOptions,
  updateProject,
  type ProjectContentTypeOption,
  type HttpsUrl,
  type ProjectOwnerOption,
  type ProjectStatus,
} from '@/shared/projects/projects'

type ProjectForm = {
  ownerKey: string
  gameContentTypeId: number | null
  title: string
  tagInput: string
  tags: string[]
  websiteUrls: string[]
  status: ProjectStatus
}

const statusOptions: Array<{ value: ProjectStatus, label: string }> = [
  { value: 'draft', label: 'Черновик' },
  { value: 'on_moderation', label: 'На модерации' },
  { value: 'published', label: 'Опубликован' },
  { value: 'rejected', label: 'Отклонён' },
  { value: 'archived', label: 'В архиве' },
]

const route = useRoute()
const router = useRouter()
const isSubmitting = ref(false)
const isLoading = ref(false)
const message = ref('')
const messageType = ref<'success' | 'error'>('error')
const contentTypeOptions = ref<ProjectContentTypeOption[]>([])
const ownerOptions = ref<ProjectOwnerOption[]>([])
const websiteUrlInputs = ref<HTMLInputElement[]>([])

const isEditing = computed(() => route.name === 'projects.edit')
const projectId = computed(() => String(route.params.id ?? ''))
const pageTitle = computed(() => (isEditing.value ? 'Редактирование проекта' : 'Создание проекта'))
const subtitle = computed(() => (
  isEditing.value ? `ID проекта: ${projectId.value}` : 'Новый проект появится в списке после сохранения.'
))
const selectedOwner = computed(() => ownerOptions.value.find((option) => ownerKey(option) === form.ownerKey) ?? null)

route.meta.breadcrumbLabel = pageTitle.value

const form = reactive<ProjectForm>({
  ownerKey: '',
  gameContentTypeId: null,
  title: '',
  tagInput: '',
  tags: [],
  websiteUrls: [],
  status: 'draft',
})

const summaryEditor = useEditor({
  extensions: [StarterKit],
  content: '',
})
const descriptionEditor = useEditor({
  extensions: [StarterKit],
  content: '',
})

function ownerKey(owner: ProjectOwnerOption): string {
  return `${owner.type}:${owner.id}`
}

function setEditorContent(targetEditor: typeof summaryEditor, value: unknown): void {
  if (!targetEditor.value) {
    return
  }

  if (value && typeof value === 'object') {
    targetEditor.value.commands.setContent(value)

    return
  }

  targetEditor.value.commands.clearContent()
}

function normalizeStringArray(value: unknown): string[] {
  if (!Array.isArray(value)) {
    return []
  }

  return value.filter((item): item is string => typeof item === 'string' && item.trim() !== '')
}

function addTag(): void {
  const tag = form.tagInput.trim()

  if (tag.length < 3 || form.tags.includes(tag)) {
    return
  }

  form.tags.push(tag)
  form.tagInput = ''
}

function removeTag(tag: string): void {
  form.tags = form.tags.filter((item) => item !== tag)
}

async function addWebsiteUrl(): Promise<void> {
  form.websiteUrls.push('')

  await nextTick()

  websiteUrlInputs.value.at(-1)?.focus()
}

function removeWebsiteUrl(index: number): void {
  form.websiteUrls.splice(index, 1)
}

function setWebsiteUrlInput(element: Element | ComponentPublicInstance | null, index: number): void {
  if (element instanceof HTMLInputElement) {
    websiteUrlInputs.value[index] = element
  }
}

function isHttpsUrl(value: string): value is HttpsUrl {
  try {
    const url = new URL(value)

    return url.protocol === 'https:' && url.hostname.includes('.')
  } catch {
    return false
  }
}

function validatedWebsiteUrls(): HttpsUrl[] {
  const urls = form.websiteUrls.map((url) => url.trim()).filter(Boolean)
  const invalidUrl = urls.find((url) => !isHttpsUrl(url))

  if (invalidUrl) {
    throw new Error('Ссылки должны быть корректными HTTPS URL.')
  }

  return urls.filter(isHttpsUrl)
}

async function loadProjectForm(): Promise<void> {
  isLoading.value = true
  message.value = ''

  try {
    const [contentTypesResponse, ownerOptionsResponse, project] = await Promise.all([
      fetchProjectContentTypes(),
      fetchProjectOwnerOptions(),
      isEditing.value ? fetchProject(projectId.value) : Promise.resolve(null),
    ])

    contentTypeOptions.value = contentTypesResponse.data
    ownerOptions.value = ownerOptionsResponse.data
    form.ownerKey = ownerOptions.value[0] ? ownerKey(ownerOptions.value[0]) : ''

    if (project) {
      form.ownerKey = `${project.ownerable_type}:${project.ownerable_id}`
      form.gameContentTypeId = project.game_content_type_id
      form.title = project.title
      form.tags = normalizeStringArray(project.tags)
      form.websiteUrls = normalizeStringArray(project.website_urls)
      form.status = project.status ?? 'draft'
      route.meta.breadcrumbLabel = project.title
      setEditorContent(summaryEditor, project.summary)
      setEditorContent(descriptionEditor, project.description)
    }
  } catch {
    messageType.value = 'error'
    message.value = 'Не удалось загрузить форму проекта.'
  } finally {
    isLoading.value = false
  }
}

async function submit(): Promise<void> {
  isSubmitting.value = true
  message.value = ''
  messageType.value = 'error'

  try {
    if (!selectedOwner.value || !form.gameContentTypeId) {
      throw new Error('Заполните владельца и тип контента.')
    }

    const payload = {
      ownerableType: selectedOwner.value.type,
      ownerableId: selectedOwner.value.id,
      gameContentTypeId: form.gameContentTypeId,
      title: form.title,
      slug: '',
      summary: summaryEditor.value?.getJSON() ?? null,
      description: descriptionEditor.value?.getJSON() ?? {},
      tags: form.tags.length > 0 ? form.tags : null,
      websiteUrls: validatedWebsiteUrls().length > 0 ? validatedWebsiteUrls() : null,
      status: form.status,
    }

    if (isEditing.value) {
      const project = await updateProject(projectId.value, payload)

      route.meta.breadcrumbLabel = project.title
      messageType.value = 'success'
      message.value = 'Проект сохранён.'

      return
    }

    await createProject(payload)
    await router.push({ name: 'projects.index' })
  } catch (error) {
    messageType.value = 'error'
    message.value = error instanceof Error ? error.message : 'Не удалось сохранить проект.'
  } finally {
    isSubmitting.value = false
  }
}

onBeforeUnmount(() => {
  summaryEditor.value?.destroy()
  descriptionEditor.value?.destroy()
})

void loadProjectForm()
</script>

<template>
  <AppShell>
    <section class="data-page">
      <header class="data-page__header project-form-header">
        <RouterLink
          class="data-page__back-link project-form-header__back"
          :to="{ name: 'projects.index' }"
          aria-label="Назад к проектам"
        >
          <ArrowLeft :size="18" :stroke-width="1.9" aria-hidden="true" />
          <span>Проекты</span>
        </RouterLink>

        <h2 class="data-page__title">{{ pageTitle }}</h2>
        <p class="data-page__subtitle">{{ subtitle }}</p>
      </header>

      <form class="project-form-panel form" @submit.prevent="submit">
        <p v-if="message" class="form-message project-form-message" :class="`project-form-message--${messageType}`">
          {{ message }}
        </p>
        <p v-if="isLoading" class="project-form-loading">Загрузка...</p>

        <div class="project-form-grid">
          <label class="form-field">
            <span class="form-label">Владелец</span>
            <select v-model="form.ownerKey" class="form-control" required>
              <option v-for="option in ownerOptions" :key="ownerKey(option)" :value="ownerKey(option)">
                {{ option.label }}
              </option>
            </select>
          </label>

          <label class="form-field">
            <span class="form-label">Тип контента игры</span>
            <select v-model.number="form.gameContentTypeId" class="form-control" required>
              <option :value="null" disabled>Выберите тип контента</option>
              <option v-for="option in contentTypeOptions" :key="option.id" :value="option.id">
                {{ option.game_name ?? 'Игра' }} / {{ option.content_type_name ?? 'Тип контента' }}
              </option>
            </select>
          </label>
        </div>

        <div class="project-form-grid">
          <label class="form-field">
            <span class="form-label">Название</span>
            <input v-model.trim="form.title" class="form-control" type="text" maxlength="128" required>
          </label>

          <label class="form-field">
            <span class="form-label">Статус</span>
            <select v-model="form.status" class="form-control" required>
              <option v-for="option in statusOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </label>
        </div>

        <section class="form-field">
          <span class="form-label">Краткое описание</span>
          <div class="game-editor">
            <div v-if="summaryEditor" class="game-editor__toolbar">
              <button
                class="game-editor__button"
                :class="{ 'is-active': summaryEditor.isActive('heading', { level: 2 }) }"
                type="button"
                title="Заголовок"
                @click="summaryEditor.chain().focus().toggleHeading({ level: 2 }).run()"
              >
                <Heading2 :size="17" :stroke-width="1.9" aria-hidden="true" />
              </button>
              <button
                class="game-editor__button"
                :class="{ 'is-active': summaryEditor.isActive('bold') }"
                type="button"
                title="Жирный"
                @click="summaryEditor.chain().focus().toggleBold().run()"
              >
                <Bold :size="17" :stroke-width="2.2" aria-hidden="true" />
              </button>
              <button
                class="game-editor__button"
                :class="{ 'is-active': summaryEditor.isActive('italic') }"
                type="button"
                title="Курсив"
                @click="summaryEditor.chain().focus().toggleItalic().run()"
              >
                <Italic :size="17" :stroke-width="2.2" aria-hidden="true" />
              </button>
            </div>
            <EditorContent class="game-editor__body game-editor__body--compact" :editor="summaryEditor" />
          </div>
        </section>

        <section class="form-field">
          <span class="form-label">Описание</span>
          <div class="game-editor">
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
        </section>

        <label class="form-field">
          <span class="form-label">Теги</span>
          <input
            v-model="form.tagInput"
            class="form-control"
            type="text"
            placeholder="Введите тег и нажмите Enter"
            @keydown.enter.prevent="addTag"
          >
          <span v-if="form.tags.length > 0" class="project-tag-list">
            <button
              v-for="tag in form.tags"
              :key="tag"
              class="project-tag"
              type="button"
              :title="`Удалить ${tag}`"
              @click="removeTag(tag)"
            >
              {{ tag }}
            </button>
          </span>
        </label>

        <section class="form-field">
          <span class="form-label">Ссылки</span>

          <div v-if="form.websiteUrls.length > 0" class="project-link-list">
            <div v-for="(_, index) in form.websiteUrls" :key="index" class="project-link-field">
              <input
                :ref="(element) => setWebsiteUrlInput(element, index)"
                v-model="form.websiteUrls[index]"
                class="project-link-field__input"
                type="url"
                pattern="https://.*"
                placeholder="https://example.com"
              >
              <button
                class="project-link-field__remove"
                type="button"
                aria-label="Удалить ссылку"
                title="Удалить ссылку"
                @click="removeWebsiteUrl(index)"
              >
                <Trash2 :size="17" :stroke-width="1.9" aria-hidden="true" />
              </button>
            </div>
          </div>

          <button class="project-link-add" type="button" @click="addWebsiteUrl">
            <Plus :size="17" :stroke-width="1.9" aria-hidden="true" />
            <span>Добавить ссылку</span>
          </button>
        </section>

        <div class="project-form-actions">
          <RouterLink class="button" :to="{ name: 'projects.index' }">
            Отмена
          </RouterLink>

          <button class="button button-primary" type="submit" :disabled="isSubmitting">
            <Save :size="18" :stroke-width="1.9" aria-hidden="true" />
            <span>{{ isSubmitting ? 'Сохранение...' : 'Сохранить' }}</span>
          </button>
        </div>
      </form>
    </section>
  </AppShell>
</template>
