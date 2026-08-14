<script setup lang="ts">
import {
  ArrowLeft,
  ArrowRight,
  Bold,
  Heading2,
  Image as ImageIcon,
  Italic,
  Pencil,
  Plus,
  Trash2,
} from '@lucide/vue'
import axios from 'axios'
import StarterKit from '@tiptap/starter-kit'
import { EditorContent, useEditor } from '@tiptap/vue-3'
import type { ComponentPublicInstance } from 'vue'
import { computed, nextTick, onBeforeUnmount, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppShell from '@/components/layout/AppShell.vue'
import ProjectCompletionCard from '@/components/ProjectCompletionCard.vue'
import StepIndicator from '@/components/ui/StepIndicator.vue'
import {
  fetchGame,
  fetchGameContentTypes,
  fetchGameDimensions,
  type GameContentTypeListItem,
  type GameDimension,
} from '@/shared/games/games'
import {
  calculateProjectPercentageComplete,
  hydrateProjectCreateDraft,
  projectCreateDraft,
  projectCreateSteps,
  resetProjectCreateDraft,
  selectProjectGame,
} from '@/shared/projects/project-create'
import {
  fetchProjectOwnerOptions,
  saveProjectDetails,
  type ProjectOwnerOption,
} from '@/shared/projects/projects'
import { uploadTotalSizeError } from '@/shared/uploads/upload-limits'
import '@/assets/styles/game-form.css'

const imageMimeTypes = ['image/jpeg', 'image/png', 'image/webp']
const imageAccept = imageMimeTypes.join(',')
const logoMaxBytes = 2 * 1024 * 1024
const logoMaxWidth = 1024
const logoMaxHeight = 1024

const route = useRoute()
const router = useRouter()
const gameName = ref('')
const message = ref('')
const isLoading = ref(false)
const isSaving = ref(false)
const isFiltersLoading = ref(false)
const filtersLoadFailed = ref(false)
const contentTypeOptions = ref<GameContentTypeListItem[]>([])
const projectDimensions = ref<GameDimension[]>([])
const authorOptions = ref<ProjectOwnerOption[]>([])
const dimensionPaths = reactive<Record<number, number[]>>({})
const authorInput = ref<HTMLSelectElement | null>(null)
const titleInput = ref<HTMLInputElement | null>(null)
const logoInput = ref<HTMLInputElement | null>(null)
const titleError = ref('')
const authorError = ref('')
const logoError = ref('')
const summaryError = ref('')
const filterSettingsError = ref('')
const showFilterErrors = ref(false)
const websiteUrlInputs = ref<HTMLInputElement[]>([])

const gameId = computed(() => String(route.params.gameId ?? ''))
const routeProjectId = computed(() => {
  const projectId = Number(route.query.projectId)

  return Number.isInteger(projectId) && projectId > 0 ? projectId : null
})

if (routeProjectId.value === null && projectCreateDraft.projectId !== null) {
  resetProjectCreateDraft()
}

selectProjectGame(gameId.value)

const form = projectCreateDraft
const logoPreviewUrl = ref<string | null>(form.logo ? URL.createObjectURL(form.logo) : null)
const subtitle = computed(() => {
  return gameName.value ? `Игра: ${gameName.value}` : 'Основные сведения о проекте.'
})
const selectedAuthor = computed(() =>
  authorOptions.value.find(
    (option) => option.type === form.ownerableType && option.id === form.ownerableId,
  ),
)
const authorKey = computed({
  get: () =>
    form.ownerableType && form.ownerableId ? `${form.ownerableType}:${form.ownerableId}` : '',
  set: (value: string) => {
    const author = authorOptions.value.find((option) => optionKey(option) === value)

    form.ownerableType = author?.type ?? ''
    form.ownerableId = author?.id ?? null
    form.ownerName = author?.label ?? ''
    authorError.value = ''
  },
})

const summaryEditor = useEditor({
  extensions: [StarterKit],
  content: '',
  onUpdate: ({ editor }) => {
    form.summary = editor.getJSON()
    form.summaryFilled = !editor.isEmpty
    summaryError.value = ''
  },
})

function optionKey(option: ProjectOwnerOption): string {
  return `${option.type}:${option.id}`
}

function authorOptionLabel(option: ProjectOwnerOption): string {
  return option.label
}

async function loadGame(): Promise<void> {
  isLoading.value = true
  message.value = ''

  try {
    const [game, contentTypesResponse] = await Promise.all([
      fetchGame(gameId.value),
      fetchGameContentTypes(gameId.value),
    ])

    gameName.value = game.name
    contentTypeOptions.value = contentTypesResponse.data
    route.meta.breadcrumbLabel = game.name

    const selectedContentTypeStillExists = contentTypeOptions.value.some(
      (contentType) => contentType.id === form.gameContentTypeId,
    )

    if (!selectedContentTypeStillExists) {
      form.gameContentTypeId =
        contentTypeOptions.value.length === 1 ? (contentTypeOptions.value[0]?.id ?? null) : null
      form.dimensionValueIds = {}
    }

    if (form.gameContentTypeId) {
      await loadProjectDimensions(form.gameContentTypeId)
    }
    if (form.projectId === null) {
      try {
        const authorOptionsResponse = await fetchProjectOwnerOptions()

        authorOptions.value = authorOptionsResponse.data

        if (!form.ownerableType || !form.ownerableId) {
          const [defaultAuthor] = authorOptions.value

          form.ownerableType = defaultAuthor?.type ?? ''
          form.ownerableId = defaultAuthor?.id ?? null
          form.ownerName = defaultAuthor?.label ?? ''
        }
      } catch (error) {
        authorOptions.value = []
        message.value =
          axios.isAxiosError(error) && error.response?.status === 403
            ? 'Недостаточно прав для выбора автора проекта.'
            : 'Не удалось загрузить список доступных авторов.'
      }
    }
  } catch {
    message.value = 'Не удалось загрузить выбранную игру.'
  } finally {
    isLoading.value = false
  }
}

async function loadProjectDimensions(gameContentTypeId: number): Promise<void> {
  isFiltersLoading.value = true
  filtersLoadFailed.value = false
  filterSettingsError.value = ''

  try {
    const response = await fetchGameDimensions(Number(gameId.value), gameContentTypeId)

    projectDimensions.value = response.data
      .filter((dimension) => dimension.is_active && dimension.applies_to === 'project')
      .map((dimension) => ({
        ...dimension,
        values: dimension.values.filter((value) => value.is_active),
      }))
    form.requiredProjectDimensionIds = projectDimensions.value
      .filter((dimension) => dimension.is_required)
      .map((dimension) => dimension.id)

    if (form.persistedDimensionValueIds.length > 0) {
      for (const dimension of projectDimensions.value) {
        const availableValueIds = new Set(dimension.values.map((value) => value.id))
        const selectedValueIds = form.persistedDimensionValueIds.filter((valueId) =>
          availableValueIds.has(valueId),
        )

        if (selectedValueIds.length > 0) {
          form.dimensionValueIds[dimension.id] = selectedValueIds
        }
      }

      form.persistedDimensionValueIds = []
    }

    initializeDimensionPaths()
  } catch {
    filtersLoadFailed.value = true
    projectDimensions.value = []
    form.requiredProjectDimensionIds = []
    filterSettingsError.value = 'Не удалось загрузить настройки проекта.'
  } finally {
    isFiltersLoading.value = false
  }
}

async function changeContentType(): Promise<void> {
  form.dimensionValueIds = {}
  form.requiredProjectDimensionIds = []
  projectDimensions.value = []
  clearDimensionPaths()
  filtersLoadFailed.value = false
  showFilterErrors.value = false
  filterSettingsError.value = ''

  if (form.gameContentTypeId) {
    await loadProjectDimensions(form.gameContentTypeId)
  }
}

function selectedDimensionValues(dimensionId: number): number[] {
  return form.dimensionValueIds[dimensionId] ?? []
}

function dimensionSelectLevels(dimension: GameDimension): GameDimension['values'][] {
  const levels: GameDimension['values'][] = [
    dimension.values.filter((value) => value.parent_id === null),
  ]
  const path = dimensionPaths[dimension.id] ?? []

  for (const selectedValueId of path) {
    const children = dimension.values.filter((value) => value.parent_id === selectedValueId)

    if (children.length === 0) {
      break
    }

    levels.push(children)
  }

  return levels
}

function selectDimensionPathValue(dimension: GameDimension, level: number, event: Event): void {
  const valueId = Number((event.target as HTMLSelectElement).value)
  const path = [...(dimensionPaths[dimension.id] ?? [])].slice(0, level)

  if (!valueId) {
    dimensionPaths[dimension.id] = path

    if (dimension.selection_mode === 'single') {
      form.dimensionValueIds[dimension.id] = []
    }

    return
  }

  path[level] = valueId
  dimensionPaths[dimension.id] = path

  const hasChildren = dimension.values.some((value) => value.parent_id === valueId)

  if (hasChildren) {
    if (dimension.selection_mode === 'single') {
      form.dimensionValueIds[dimension.id] = []
    }

    return
  }

  if (dimension.selection_mode === 'multiple') {
    form.dimensionValueIds[dimension.id] = [
      ...new Set([...selectedDimensionValues(dimension.id), valueId]),
    ]
  } else {
    form.dimensionValueIds[dimension.id] = [valueId]
  }

  clearFilterSettingsError()
}

function removeDimensionValue(dimensionId: number, valueId: number): void {
  form.dimensionValueIds[dimensionId] = selectedDimensionValues(dimensionId).filter(
    (selectedValueId) => selectedValueId !== valueId,
  )
  dimensionPaths[dimensionId] = []
}

function dimensionValueName(dimension: GameDimension, valueId: number): string {
  return dimension.values.find((value) => value.id === valueId)?.name ?? `#${valueId}`
}

function initializeDimensionPaths(): void {
  clearDimensionPaths()

  for (const dimension of projectDimensions.value) {
    if (dimension.selection_mode !== 'single') {
      continue
    }

    const [selectedValueId] = selectedDimensionValues(dimension.id)

    if (!selectedValueId) {
      continue
    }

    const path: number[] = []
    let currentValue = dimension.values.find((value) => value.id === selectedValueId)

    while (currentValue) {
      path.unshift(currentValue.id)
      currentValue = currentValue.parent_id
        ? dimension.values.find((value) => value.id === currentValue?.parent_id)
        : undefined
    }

    dimensionPaths[dimension.id] = path
  }
}

function clearDimensionPaths(): void {
  for (const dimensionId of Object.keys(dimensionPaths)) {
    delete dimensionPaths[Number(dimensionId)]
  }
}

function dimensionHasError(dimension: GameDimension): boolean {
  return Boolean(
    showFilterErrors.value &&
    dimension.is_required &&
    selectedDimensionValues(dimension.id).length === 0,
  )
}

function clearFilterSettingsError(): void {
  const hasMissingRequiredValue = projectDimensions.value.some(
    (dimension) => dimension.is_required && selectedDimensionValues(dimension.id).length === 0,
  )

  if (!hasMissingRequiredValue) {
    filterSettingsError.value = ''
  }
}

async function chooseLogo(event: Event): Promise<void> {
  const input = event.target as HTMLInputElement
  const [file] = Array.from(input.files ?? [])

  logoError.value = ''

  if (!file) {
    clearLogo()

    return
  }

  const error = await validateLogo(file)

  if (error) {
    clearLogo()
    logoError.value = error

    return
  }

  form.logo = file
  setLogoPreview(URL.createObjectURL(file))
}

async function validateLogo(file: File): Promise<string> {
  if (!imageMimeTypes.includes(file.type)) {
    return 'Доступные типы файлов: JPG, PNG, WEBP.'
  }

  if (file.size > logoMaxBytes) {
    return 'Файл должен быть не больше 2 МБ.'
  }

  try {
    const dimensions = await getImageDimensions(file)

    if (dimensions.width > logoMaxWidth || dimensions.height > logoMaxHeight) {
      return `Изображение должно быть не больше ${logoMaxWidth}x${logoMaxHeight}px.`
    }
  } catch {
    return 'Не удалось прочитать изображение.'
  }

  return ''
}

function getImageDimensions(file: File): Promise<{ width: number; height: number }> {
  return new Promise((resolve, reject) => {
    const url = URL.createObjectURL(file)
    const image = new window.Image()

    image.onload = () => {
      URL.revokeObjectURL(url)
      resolve({ width: image.naturalWidth, height: image.naturalHeight })
    }

    image.onerror = () => {
      URL.revokeObjectURL(url)
      reject(new Error('Invalid image'))
    }

    image.src = url
  })
}

function openLogoPicker(): void {
  logoInput.value?.click()
}

function clearLogo(): void {
  form.logo = null
  form.logoUrl = null
  logoError.value = ''
  setLogoPreview(null)

  if (logoInput.value) {
    logoInput.value.value = ''
  }
}

function setLogoPreview(url: string | null): void {
  if (logoPreviewUrl.value?.startsWith('blob:')) {
    URL.revokeObjectURL(logoPreviewUrl.value)
  }

  logoPreviewUrl.value = url
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

function setWebsiteUrlInput(
  element: Element | ComponentPublicInstance | null,
  index: number,
): void {
  if (element instanceof HTMLInputElement) {
    websiteUrlInputs.value[index] = element
  }
}

async function continueToNextStep(): Promise<void> {
  const editor = summaryEditor.value

  titleError.value = ''
  authorError.value = ''
  logoError.value = ''
  summaryError.value = ''
  filterSettingsError.value = ''
  showFilterErrors.value = true

  if (!selectedAuthor.value) {
    authorError.value = 'Выберите автора проекта.'
  }

  if (!form.title.trim()) {
    titleError.value = 'Введите название проекта.'
  }

  if (!form.logo && !form.logoUrl) {
    logoError.value = 'Логотип обязателен.'
  }

  const uploadError = uploadTotalSizeError([form.logo])

  if (uploadError) {
    logoError.value = uploadError
  }

  if (!editor || editor.isEmpty) {
    summaryError.value = 'Добавьте краткое описание.'
  }

  if (!form.gameContentTypeId) {
    filterSettingsError.value = 'Выберите тип контента.'
  } else if (filtersLoadFailed.value) {
    filterSettingsError.value = 'Не удалось загрузить настройки проекта.'
  } else if (
    projectDimensions.value.some(
      (dimension) => dimension.is_required && selectedDimensionValues(dimension.id).length === 0,
    )
  ) {
    filterSettingsError.value = 'Заполните обязательные настройки проекта.'
  }

  if (
    authorError.value ||
    titleError.value ||
    logoError.value ||
    summaryError.value ||
    filterSettingsError.value
  ) {
    if (authorError.value) {
      authorInput.value?.focus()
    } else if (titleError.value) {
      titleInput.value?.focus()
    }

    return
  }

  if (!editor) {
    return
  }

  form.summary = editor.getJSON()
  form.summaryFilled = true

  isSaving.value = true
  message.value = ''

  try {
    if ((form.projectId === null && !selectedAuthor.value) || !form.gameContentTypeId) {
      throw new Error('Не удалось определить владельца проекта.')
    }

    const project = await saveProjectDetails(form.projectId, {
      ownerableType: selectedAuthor.value?.type,
      ownerableId: selectedAuthor.value?.id,
      gameContentTypeId: form.gameContentTypeId,
      title: form.title,
      summary: form.summary,
      tags: form.tags,
      websiteUrls: form.websiteUrls.filter(Boolean),
      logo: form.logo,
      dimensionValueIds: [...new Set(Object.values(form.dimensionValueIds).flat())],
      percentageComplete: calculateProjectPercentageComplete(),
    })

    form.projectId = project.id
    form.logoUrl = project.logo_url

    await router.push({
      name: 'projects.create.description',
      params: { gameId: gameId.value },
      query: { projectId: String(project.id) },
    })
  } catch (error) {
    message.value = error instanceof Error ? error.message : 'Не удалось сохранить черновик.'
  } finally {
    isSaving.value = false
  }
}

async function loadPage(): Promise<void> {
  try {
    if (routeProjectId.value !== null) {
      await hydrateProjectCreateDraft(routeProjectId.value)
    }
  } catch {
    message.value = 'Не удалось загрузить сохранённый черновик.'

    return
  }

  setLogoPreview(form.logo ? URL.createObjectURL(form.logo) : form.logoUrl)

  if (form.summary && summaryEditor.value) {
    summaryEditor.value.commands.setContent(form.summary)
    form.summaryFilled = !summaryEditor.value.isEmpty
  }

  await loadGame()
}

onBeforeUnmount(() => {
  if (logoPreviewUrl.value?.startsWith('blob:')) {
    URL.revokeObjectURL(logoPreviewUrl.value)
  }

  summaryEditor.value?.destroy()
})

void loadPage()
</script>

<template>
  <AppShell>
    <section class="data-page">
      <header class="data-page__header project-create-details__header">
        <RouterLink
          class="data-page__back-link project-create-details__back"
          :to="{ name: 'projects.create' }"
          aria-label="Вернуться к выбору игры"
        >
          <ArrowLeft :size="18" :stroke-width="1.9" aria-hidden="true" />
          <span>Выбор игры</span>
        </RouterLink>

        <div>
          <h2 class="data-page__title">Основные данные проекта</h2>
          <p class="data-page__subtitle">{{ subtitle }}</p>
        </div>
      </header>

      <StepIndicator
        :steps="projectCreateSteps"
        :current-step="2"
        aria-label="Этапы создания проекта"
      />

      <p v-if="message" class="data-page__message">{{ message }}</p>

      <div class="project-create-layout">
        <form
          class="project-form-panel form project-create-details__form"
          novalidate
          @submit.prevent="continueToNextStep"
        >
          <p v-if="isLoading" class="project-form-loading">Загрузка...</p>

          <div
            class="project-create-details__group project-create-details__group--first project-create-details__identity"
          >
            <label class="form-field">
              <span class="form-label">Автор</span>
              <select
                ref="authorInput"
                v-model="authorKey"
                class="form-control"
                required
                :disabled="form.projectId !== null"
                :aria-invalid="authorError ? 'true' : undefined"
              >
                <option value="" disabled>Выберите автора</option>
                <option
                  v-for="option in authorOptions"
                  :key="optionKey(option)"
                  :value="optionKey(option)"
                >
                  {{ authorOptionLabel(option) }}
                </option>
              </select>
              <span v-if="authorError" class="field-error">{{ authorError }}</span>
              <span v-else-if="form.projectId !== null" class="game-form-help">
                Автор зафиксирован после первого сохранения проекта.
              </span>
            </label>

            <label class="form-field">
              <span class="form-label">Название</span>
              <input
                ref="titleInput"
                v-model.trim="form.title"
                class="form-control"
                type="text"
                maxlength="128"
                required
                placeholder="Название проекта"
                :aria-invalid="titleError ? 'true' : undefined"
                @input="titleError = ''"
              />
              <span v-if="titleError" class="field-error">{{ titleError }}</span>
            </label>
          </div>

          <div class="form-field project-create-details__group">
            <span class="form-label">Логотип</span>
            <div
              class="game-upload game-upload--logo project-create-details__logo"
              :class="{
                'game-upload--invalid': logoError,
                'game-upload--empty': !logoPreviewUrl,
              }"
              @click="!logoPreviewUrl ? openLogoPicker() : undefined"
            >
              <input
                ref="logoInput"
                type="file"
                :accept="imageAccept"
                required
                @change="chooseLogo"
              />

              <img
                v-if="logoPreviewUrl"
                class="game-upload__preview"
                :src="logoPreviewUrl"
                alt="Предпросмотр логотипа проекта"
              />

              <span v-if="!logoPreviewUrl" class="game-upload__icon">
                <ImageIcon :size="24" :stroke-width="1.9" aria-hidden="true" />
              </span>
              <span v-if="!logoPreviewUrl" class="game-upload__content">
                <span class="game-upload__title">Выберите изображение</span>
                <span class="game-upload__file">JPG, PNG или WEBP</span>
              </span>

              <div v-if="logoPreviewUrl" class="game-upload__actions">
                <button
                  class="game-upload__action icon-action"
                  type="button"
                  aria-label="Заменить логотип"
                  title="Заменить логотип"
                  @click.stop="openLogoPicker"
                >
                  <Pencil :size="17" :stroke-width="2" aria-hidden="true" />
                </button>
                <button
                  class="game-upload__action icon-action icon-action--danger"
                  type="button"
                  aria-label="Удалить логотип"
                  title="Удалить логотип"
                  @click.stop="clearLogo"
                >
                  <Trash2 :size="17" :stroke-width="2" aria-hidden="true" />
                </button>
              </div>
            </div>
            <p class="game-form-help" :class="{ 'game-form-help--error': logoError }">
              {{ logoError || 'Обязательное изображение до 2 МБ и 1024x1024 пикселей.' }}
            </p>
          </div>

          <div class="form-field project-create-details__group">
            <span class="form-label">Краткое описание</span>
            <div
              class="game-editor"
              :class="{ 'project-create-details__editor--invalid': summaryError }"
            >
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
              <EditorContent
                class="game-editor__body game-editor__body--compact"
                :editor="summaryEditor"
              />
            </div>
            <span v-if="summaryError" class="field-error">{{ summaryError }}</span>
          </div>

          <div class="form-field project-create-details__group">
            <div class="project-filter-settings__heading">
              <span class="form-label">Настройки проекта</span>
              <span>Показываются только фильтры, относящиеся к проекту.</span>
            </div>

            <label class="form-field project-filter-settings__content-type">
              <span class="form-label">Тип контента</span>
              <select
                v-model.number="form.gameContentTypeId"
                class="form-control"
                :aria-invalid="!form.gameContentTypeId && showFilterErrors ? 'true' : undefined"
                @change="changeContentType"
              >
                <option :value="null" disabled>Выберите тип контента</option>
                <option
                  v-for="contentType in contentTypeOptions"
                  :key="contentType.id"
                  :value="contentType.id"
                >
                  {{ contentType.content_type_name ?? 'Тип контента' }}
                </option>
              </select>
            </label>

            <p v-if="isFiltersLoading" class="project-filter-settings__message">
              Загрузка настроек…
            </p>

            <div v-else-if="projectDimensions.length > 0" class="project-filter-settings__grid">
              <div
                v-for="dimension in projectDimensions"
                :key="dimension.id"
                class="project-filter-settings__field"
              >
                <div class="project-filter-settings__field-heading">
                  <span class="form-label">
                    {{ dimension.name }}
                    <span v-if="dimension.is_required" class="project-filter-settings__required">
                      обязательно
                    </span>
                  </span>
                  <span v-if="dimension.selection_mode === 'multiple'"
                    >Можно выбрать несколько</span
                  >
                </div>

                <div class="project-filter-settings__controls">
                  <select
                    v-for="(values, level) in dimensionSelectLevels(dimension)"
                    :key="level"
                    class="form-control project-filter-settings__select"
                    :value="dimensionPaths[dimension.id]?.[level] ?? ''"
                    :aria-label="`${dimension.name}, уровень ${level + 1}`"
                    :aria-invalid="dimensionHasError(dimension) ? 'true' : undefined"
                    @change="selectDimensionPathValue(dimension, level, $event)"
                  >
                    <option value="">
                      {{ level === 0 ? 'Выберите значение' : 'Выберите дочернее значение' }}
                    </option>
                    <option v-for="value in values" :key="value.id" :value="value.id">
                      {{ value.name }}
                    </option>
                  </select>

                  <div
                    v-if="
                      dimension.selection_mode === 'multiple' &&
                      selectedDimensionValues(dimension.id).length > 0
                    "
                    class="project-filter-settings__selected"
                    aria-label="Выбранные значения"
                  >
                    <button
                      v-for="valueId in selectedDimensionValues(dimension.id)"
                      :key="valueId"
                      class="project-filter-settings__selected-value"
                      type="button"
                      :title="`Удалить ${dimensionValueName(dimension, valueId)}`"
                      @click="removeDimensionValue(dimension.id, valueId)"
                    >
                      {{ dimensionValueName(dimension, valueId) }}
                      <span aria-hidden="true">×</span>
                    </button>
                  </div>

                  <span v-if="dimensionHasError(dimension)" class="field-error">
                    Выберите значение.
                  </span>
                </div>
              </div>
            </div>

            <p
              v-else-if="form.gameContentTypeId && !filtersLoadFailed"
              class="project-filter-settings__message"
            >
              Для этого типа контента нет настроек уровня проекта.
            </p>

            <span v-if="filterSettingsError" class="field-error">{{ filterSettingsError }}</span>
          </div>

          <div class="form-field project-create-details__group">
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
                />
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
          </div>

          <label
            class="form-field project-create-details__group project-create-details__group--last"
          >
            <span class="form-label">Теги</span>
            <input
              v-model="form.tagInput"
              class="form-control"
              type="text"
              placeholder="Введите тег и нажмите Enter"
              @keydown.enter.prevent="addTag"
            />
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

          <div class="project-create-details__actions">
            <RouterLink class="button" :to="{ name: 'projects.create' }">
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
.project-create-details__header {
  gap: 12px;
}

.project-create-details__back {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.project-create-details__form {
  width: 100%;
  max-width: none;
  gap: 0;
}

.project-create-details__group {
  padding: 24px 0;
  border-top: 1px solid var(--color-border-soft);
}

.project-create-details__group--first {
  padding-top: 0;
  border-top: 0;
}

.project-create-details__group--last {
  padding-bottom: 24px;
}

.project-create-details__identity {
  display: grid;
  grid-template-columns: minmax(220px, 0.8fr) minmax(280px, 1.2fr);
  gap: 16px;
  align-items: start;
  width: 100%;
  max-width: 720px;
}

.project-create-details__identity > .form-field {
  min-width: 0;
}

.project-create-details__identity .form-control {
  width: 100%;
  max-width: none;
}

.project-create-details__group > .form-control,
.project-create-details__group > .game-editor,
.project-create-details__group > .project-link-list,
.project-create-details__group > .project-tag-list {
  width: 100%;
  max-width: 720px;
}

.project-filter-settings__heading {
  display: grid;
  gap: 4px;
}

.project-filter-settings__heading > span:last-child,
.project-filter-settings__message {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 13px;
  line-height: 1.45;
}

.project-filter-settings__content-type {
  width: 100%;
  max-width: 720px;
}

.project-filter-settings__grid {
  display: grid;
  width: 100%;
  border-top: 1px solid var(--color-border-soft);
}

.project-filter-settings__field {
  display: grid;
  grid-template-columns: minmax(180px, 240px) minmax(0, 1fr);
  gap: 18px;
  align-items: start;
  min-width: 0;
  padding: 14px 0;
  border-bottom: 1px solid var(--color-border-soft);
}

.project-filter-settings__field-heading {
  display: grid;
  gap: 4px;
  min-width: 0;
}

.project-filter-settings__field-heading > span:last-child {
  color: var(--color-text-muted);
  font-size: 11px;
  font-weight: 650;
}

.project-filter-settings__required {
  margin-left: 5px;
  color: var(--color-danger);
  font-size: 11px;
  font-weight: 700;
}

.project-filter-settings__controls {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  min-width: 0;
}

.project-filter-settings__select {
  flex: 1 1 240px;
  width: auto;
  max-width: 360px;
}

.project-filter-settings__selected {
  display: flex;
  flex: 1 0 100%;
  flex-wrap: wrap;
  gap: 7px;
}

.project-filter-settings__selected-value {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-height: 28px;
  padding: 0 9px;
  color: var(--color-primary);
  background: color-mix(in srgb, var(--color-primary) 10%, transparent);
  border: 1px solid color-mix(in srgb, var(--color-primary) 28%, var(--color-border-soft));
  border-radius: 999px;
  cursor: pointer;
  font: inherit;
  font-size: 12px;
  font-weight: 750;
}

.project-filter-settings__selected-value:hover {
  color: var(--color-primary-text);
  background: var(--color-primary);
  border-color: var(--color-primary);
}

.project-create-details__logo {
  width: min(100%, 280px);
  min-height: 158px;
}

.project-create-details__editor--invalid {
  border-color: var(--color-danger);
}

.project-create-details__actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding-top: 24px;
  border-top: 1px solid var(--color-border-soft);
}

.project-create-details__actions .button {
  flex: 0 0 auto;
  width: auto;
}

@media (max-width: 760px) {
  .project-create-details__identity {
    grid-template-columns: 1fr;
    gap: 12px;
  }

  .project-filter-settings__field {
    grid-template-columns: 1fr;
    gap: 8px;
  }

  .project-filter-settings__select {
    max-width: none;
  }
}

@media (max-width: 520px) {
  .project-create-details__actions {
    display: grid;
    grid-template-columns: 1fr;
  }

  .project-create-details__actions .button {
    width: 100%;
  }
}
</style>
