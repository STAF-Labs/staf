<script setup lang="ts">
import { ArrowLeft, Bold, FileArchive, Heading2, Info, Italic, List, ListOrdered, Pencil, Save, Trash2 } from '@lucide/vue'
import StarterKit from '@tiptap/starter-kit'
import { EditorContent, useEditor } from '@tiptap/vue-3'
import { computed, nextTick, onBeforeUnmount, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppShell from '@/components/layout/AppShell.vue'
import type { GameDimension } from '@/shared/games/games'
import {
  createProjectRelease,
  fetchProject,
  fetchProjectRelease,
  fetchProjectReleaseFilters,
  type ProjectDetail,
  type ProjectRelease,
  updateProjectRelease,
} from '@/shared/projects/projects'
import { pushProjectActionNotification } from '@/shared/projects/notifications'
import { uploadTotalSizeError } from '@/shared/uploads/upload-limits'
import '@/assets/styles/game-form.css'

type ReleaseForm = {
  file: File | null
  title: string
  type: 'alpha' | 'beta' | 'release'
  changelog: unknown
  dimensionValueIds: Record<number, string>
}

const releaseTypeOptions = [
  { value: 'alpha', label: 'Альфа' },
  { value: 'beta', label: 'Бета' },
  { value: 'release', label: 'Релиз' },
] as const

const route = useRoute()
const router = useRouter()
const project = ref<ProjectDetail | null>(null)
const release = ref<ProjectRelease | null>(null)
const releaseFilters = ref<GameDimension[]>([])
const isLoading = ref(true)
const isSaving = ref(false)
const message = ref('')
const fileError = ref('')
const titleError = ref('')
const changelogError = ref('')
const releaseFiltersError = ref('')
const releaseFileInput = ref<HTMLInputElement | null>(null)
const projectId = computed(() => Number(route.params.id))
const releaseId = computed(() => Number(route.params.releaseId))
const isEditMode = computed(() => route.name === 'projects.releases.edit')
const pageTitle = computed(() => (isEditMode.value ? 'Редактирование релиза' : 'Новый релиз'))
const submitLabel = computed(() => {
  if (isSaving.value) {
    return 'Сохранение...'
  }

  return isEditMode.value ? 'Сохранить релиз' : 'Создать релиз'
})
const form = reactive<ReleaseForm>({
  file: null,
  title: '',
  type: 'release',
  changelog: null,
  dimensionValueIds: {},
})

const changelogEditor = useEditor({
  extensions: [StarterKit],
  content: '',
  onUpdate: ({ editor }) => {
    form.changelog = editor.getJSON()
    changelogError.value = ''
  },
})

async function loadProject(): Promise<void> {
  isLoading.value = true
  message.value = ''

  try {
    const [projectResponse, filtersResponse, releaseResponse] = await Promise.all([
      fetchProject(projectId.value),
      fetchProjectReleaseFilters(projectId.value),
      isEditMode.value ? fetchProjectRelease(projectId.value, releaseId.value) : Promise.resolve(null),
    ])

    project.value = projectResponse
    release.value = releaseResponse
    releaseFilters.value = filtersResponse.data.map((dimension) => ({
      ...dimension,
      values: dimension.values.filter((value) => value.is_active),
    }))
    form.dimensionValueIds = Object.fromEntries(
      releaseFilters.value.map((filter) => [
        filter.id,
        String(release.value?.dimension_value_ids?.find((valueId) =>
          filter.values.some((value) => value.id === valueId),
        ) ?? ''),
      ]),
    )

    if (release.value) {
      form.title = release.value.title
      form.type = release.value.type
      form.changelog = release.value.changelog
    }

    await nextTick()
    changelogEditor.value?.commands.setContent(release.value?.changelog || '')
  } catch {
    message.value = isEditMode.value ? 'Не удалось загрузить релиз.' : 'Не удалось загрузить проект.'
  } finally {
    isLoading.value = false
  }
}

function chooseReleaseFile(event: Event): void {
  const input = event.target as HTMLInputElement
  const [file] = Array.from(input.files ?? [])

  fileError.value = ''
  form.file = null

  if (!file) {
    return
  }

  const uploadError = uploadTotalSizeError([file])

  if (uploadError) {
    fileError.value = uploadError
    input.value = ''

    return
  }

  form.file = file
}

function openReleaseFilePicker(): void {
  releaseFileInput.value?.click()
}

function clearReleaseFile(): void {
  form.file = null
  fileError.value = ''

  if (releaseFileInput.value) {
    releaseFileInput.value.value = ''
  }
}

async function submitRelease(): Promise<void> {
  const editor = changelogEditor.value

  fileError.value = ''
  titleError.value = ''
  changelogError.value = ''
  releaseFiltersError.value = ''

  if (!form.file && !isEditMode.value) {
    fileError.value = 'Добавьте файл релиза.'
  }

  const uploadError = uploadTotalSizeError([form.file])

  if (uploadError) {
    fileError.value = uploadError
  }

  if (!form.title.trim()) {
    titleError.value = 'Введите название релиза.'
  }

  if (!editor || editor.isEmpty) {
    changelogError.value = 'Добавьте changelog релиза.'
  }

  const selectedDimensionValueIds = Object.values(form.dimensionValueIds)
    .filter(Boolean)
    .map((valueId) => Number(valueId))

  for (const filter of releaseFilters.value) {
    if (filter.is_required && !form.dimensionValueIds[filter.id]) {
      releaseFiltersError.value = 'Заполните обязательные фильтры релиза.'
    }
  }

  if (fileError.value || titleError.value || changelogError.value || releaseFiltersError.value || !editor) {
    return
  }

  form.changelog = editor.getJSON()
  isSaving.value = true
  message.value = ''

  try {
    const payload = {
      file: form.file,
      title: form.title,
      type: form.type,
      changelog: form.changelog,
      dimensionValueIds: selectedDimensionValueIds,
    }

    if (isEditMode.value) {
      await updateProjectRelease(projectId.value, releaseId.value, payload)
      pushProjectActionNotification('releaseUpdated')
    } else {
      await createProjectRelease(projectId.value, {
        ...payload,
        file: form.file as File,
      })
      pushProjectActionNotification('releaseCreated')
    }

    await router.push({ name: 'projects.edit', params: { id: String(projectId.value) } })
  } catch {
    message.value = isEditMode.value ? 'Не удалось сохранить релиз.' : 'Не удалось создать релиз.'
  } finally {
    isSaving.value = false
  }
}

function releaseFilterValueOptions(dimension: GameDimension): GameDimension['values'] {
  const valuesByParentId = new Map<number | null, GameDimension['values']>()

  for (const value of dimension.values) {
    const values = valuesByParentId.get(value.parent_id) ?? []

    values.push(value)
    valuesByParentId.set(value.parent_id, values)
  }

  const orderedValues: GameDimension['values'] = []
  const appendValues = (parentId: number | null): void => {
    for (const value of valuesByParentId.get(parentId) ?? []) {
      if (!valuesByParentId.has(value.id)) {
        orderedValues.push(value)
      }

      appendValues(value.id)
    }
  }

  appendValues(null)

  return orderedValues
}

onBeforeUnmount(() => {
  changelogEditor.value?.destroy()
})

void loadProject()
</script>

<template>
  <AppShell>
    <section class="data-page project-release-create">
      <p v-if="isLoading" class="project-release-create__state">Загрузка...</p>

      <template v-else-if="project">
        <header class="project-release-create__header">
          <RouterLink
            class="project-release-create__back"
            :to="{ name: 'projects.edit', params: { id: project.id } }"
          >
            <ArrowLeft :size="18" :stroke-width="1.9" aria-hidden="true" />
            <span>Проект</span>
          </RouterLink>

          <div>
            <h1>{{ pageTitle }}</h1>
            <div class="project-release-create__meta">
              <span>{{ project.title }}</span>
              <span v-if="project.game_name">{{ project.game_name }}</span>
              <span v-if="project.content_type_name">{{ project.content_type_name }}</span>
            </div>
          </div>
        </header>

        <p v-if="message" class="form-message">{{ message }}</p>

        <form
          class="project-form-panel form project-release-create__form"
          novalidate
          @submit.prevent="submitRelease"
        >
          <label class="form-field project-release-create__file">
            <span class="form-label">Файл релиза</span>
            <span
              class="game-upload project-release-create__file-upload"
              :class="{
                'game-upload--invalid': fileError,
                'game-upload--empty': !form.file && !release?.file_name,
              }"
              @click="!form.file ? openReleaseFilePicker() : undefined"
            >
              <input
                ref="releaseFileInput"
                class="form-control"
                type="file"
                :aria-invalid="fileError ? 'true' : undefined"
                @change="chooseReleaseFile"
              />

              <span class="game-upload__icon">
                <FileArchive :size="24" :stroke-width="1.9" aria-hidden="true" />
              </span>
              <span class="game-upload__content">
                <span class="game-upload__title">
                  {{
                    form.file
                      ? form.file.name
                      : release?.file_name || 'Выберите файл релиза'
                  }}
                </span>
                <span class="game-upload__file">
                  {{
                    isEditMode && !form.file && release?.file_name
                      ? 'Текущий файл будет сохранён'
                      : 'Формат зависит от игры и типа проекта'
                  }}
                </span>
              </span>

              <div v-if="form.file" class="game-upload__actions">
                <button
                  class="game-upload__action icon-action"
                  type="button"
                  aria-label="Заменить файл релиза"
                  title="Заменить файл релиза"
                  @click.stop="openReleaseFilePicker"
                >
                  <Pencil :size="17" :stroke-width="2" aria-hidden="true" />
                </button>
                <button
                  class="game-upload__action icon-action icon-action--danger"
                  type="button"
                  aria-label="Удалить файл релиза"
                  title="Удалить файл релиза"
                  @click.stop="clearReleaseFile"
                >
                  <Trash2 :size="17" :stroke-width="2" aria-hidden="true" />
                </button>
              </div>
            </span>
            <span class="game-form-help" :class="{ 'game-form-help--error': fileError }">
              {{
                fileError ||
                (isEditMode
                  ? 'Оставьте пустым, если файл релиза менять не нужно.'
                  : 'Добавьте файл, который относится к этому релизу.')
              }}
            </span>
          </label>

          <label class="form-field project-release-create__title">
            <span class="project-release-create__label-row">
              <span class="form-label">Название</span>
              <span class="project-release-create__note" aria-label="Подсказка к названию релиза">
                <Info :size="18" :stroke-width="1.9" aria-hidden="true" />
                <span>
                  Это отображаемое имя релиза. Пользователи увидят его в списке версий проекта.
                </span>
              </span>
            </span>
            <input
              v-model.trim="form.title"
              class="form-control"
              type="text"
              maxlength="64"
              placeholder="Например, 1.0.0"
              :aria-invalid="titleError ? 'true' : undefined"
              @input="titleError = ''"
            />
            <span v-if="titleError" class="field-error">{{ titleError }}</span>
          </label>

          <div v-if="releaseFilters.length > 0" class="project-release-create__filters">
            <label
              v-for="filter in releaseFilters"
              :key="filter.id"
              class="form-field project-release-create__filter"
            >
              <span class="form-label">
                {{ filter.name }}
                <span v-if="filter.is_required" class="project-release-create__required">
                  обязательно
                </span>
              </span>
              <select v-model="form.dimensionValueIds[filter.id]" class="form-control">
                <option value="">Все значения</option>
                <option
                  v-for="value in releaseFilterValueOptions(filter)"
                  :key="value.id"
                  :value="String(value.id)"
                >
                  {{ value.name }}
                </option>
              </select>
            </label>
            <span v-if="releaseFiltersError" class="field-error">
              {{ releaseFiltersError }}
            </span>
          </div>

          <div v-else class="project-release-create__filters-empty">
            Для этого проекта нет фильтров уровня релиза.
          </div>

          <div class="project-release-create__type-row">
            <label class="form-field">
              <span class="form-label">Тип</span>
              <select v-model="form.type" class="form-control">
                <option
                  v-for="option in releaseTypeOptions"
                  :key="option.value"
                  :value="option.value"
                >
                  {{ option.label }}
                </option>
              </select>
            </label>
          </div>

          <div class="form-field project-release-create__changelog">
            <span class="form-label">Changelog</span>
            <div
              class="game-editor"
              :class="{ 'project-release-create__editor--invalid': changelogError }"
            >
              <div v-if="changelogEditor" class="game-editor__toolbar">
                <button
                  class="game-editor__button"
                  :class="{ 'is-active': changelogEditor.isActive('heading', { level: 2 }) }"
                  type="button"
                  title="Заголовок"
                  @click="changelogEditor.chain().focus().toggleHeading({ level: 2 }).run()"
                >
                  <Heading2 :size="17" :stroke-width="1.9" aria-hidden="true" />
                </button>
                <button
                  class="game-editor__button"
                  :class="{ 'is-active': changelogEditor.isActive('bold') }"
                  type="button"
                  title="Жирный"
                  @click="changelogEditor.chain().focus().toggleBold().run()"
                >
                  <Bold :size="17" :stroke-width="2.2" aria-hidden="true" />
                </button>
                <button
                  class="game-editor__button"
                  :class="{ 'is-active': changelogEditor.isActive('italic') }"
                  type="button"
                  title="Курсив"
                  @click="changelogEditor.chain().focus().toggleItalic().run()"
                >
                  <Italic :size="17" :stroke-width="2.2" aria-hidden="true" />
                </button>
                <button
                  class="game-editor__button"
                  :class="{ 'is-active': changelogEditor.isActive('bulletList') }"
                  type="button"
                  title="Маркированный список"
                  @click="changelogEditor.chain().focus().toggleBulletList().run()"
                >
                  <List :size="17" :stroke-width="1.9" aria-hidden="true" />
                </button>
                <button
                  class="game-editor__button"
                  :class="{ 'is-active': changelogEditor.isActive('orderedList') }"
                  type="button"
                  title="Нумерованный список"
                  @click="changelogEditor.chain().focus().toggleOrderedList().run()"
                >
                  <ListOrdered :size="17" :stroke-width="1.9" aria-hidden="true" />
                </button>
              </div>
              <EditorContent class="game-editor__body" :editor="changelogEditor" />
            </div>
            <span v-if="changelogError" class="field-error">{{ changelogError }}</span>
          </div>

          <div class="project-release-create__actions">
            <RouterLink
              class="button"
              :to="{ name: 'projects.edit', params: { id: project.id } }"
            >
              <ArrowLeft :size="18" :stroke-width="1.9" aria-hidden="true" />
              <span>Назад</span>
            </RouterLink>

            <button class="button button-primary" type="submit" :disabled="isSaving">
              <Save :size="18" :stroke-width="1.9" aria-hidden="true" />
              <span>{{ submitLabel }}</span>
            </button>
          </div>
        </form>
      </template>

      <p v-else-if="message" class="project-release-create__state">{{ message }}</p>
    </section>
  </AppShell>
</template>

<style scoped>
.project-release-create,
.project-release-create__header {
  display: grid;
  gap: 18px;
}

.project-release-create__back {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  width: fit-content;
  color: var(--color-primary);
  text-decoration: none;
}

.project-release-create__header h1 {
  margin: 0;
  font-size: clamp(28px, 4vw, 40px);
  letter-spacing: -0.03em;
}

.project-release-create__meta {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 14px;
  margin-top: 8px;
  color: var(--color-text-muted);
  font-size: 14px;
}

.project-release-create__state {
  margin: 0;
  color: var(--color-text-muted);
}

.project-release-create :deep(.project-form-panel.project-release-create__form) {
  max-width: none;
}

.project-release-create__form {
  width: 100%;
  gap: 24px;
}

.project-release-create__file,
.project-release-create__type-row {
  width: min(100%, 720px);
}

.project-release-create__file-upload {
  justify-content: flex-start;
  width: min(100%, 420px);
  min-height: 116px;
}

.project-release-create__title {
  width: min(100%, 720px);
  min-width: 0;
}

.project-release-create__label-row {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 12px;
  align-items: center;
}

.project-release-create__note {
  display: flex;
  align-items: center;
  gap: 7px;
  min-height: 28px;
  padding: 5px 9px;
  color: var(--color-text-muted);
  background: color-mix(in srgb, var(--color-info) 7%, var(--color-surface));
  border: 1px solid color-mix(in srgb, var(--color-info) 20%, var(--color-border-soft));
  border-radius: var(--radius-md);
  font-size: 12px;
  font-weight: 650;
  line-height: 1.35;
}

.project-release-create__note svg {
  flex: 0 0 auto;
  color: var(--color-info);
}

.project-release-create__filters-empty {
  margin: 0;
  font-size: 13px;
  line-height: 1.45;
}

.project-release-create__filters {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
  gap: 14px;
}

.project-release-create__filter {
  min-width: 0;
}

.project-release-create__required {
  margin-left: 5px;
  color: var(--color-danger);
  font-size: 11px;
  font-weight: 700;
}

.project-release-create__filters-empty {
  color: var(--color-text-muted);
}

.project-release-create__changelog {
  width: 100%;
}

.project-release-create__changelog .game-editor {
  width: 100%;
}

.project-release-create__editor--invalid {
  border-color: var(--color-danger);
}

.project-release-create__actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding-top: 24px;
  border-top: 1px solid var(--color-border-soft);
}

.project-release-create__actions .button {
  flex: 0 0 auto;
  width: auto;
}

@media (max-width: 1020px) {
  .project-release-create__label-row {
    align-items: start;
  }
}

@media (max-width: 620px) {
  .project-release-create__filters,
  .project-release-create__actions {
    grid-template-columns: 1fr;
  }

  .project-release-create__actions {
    display: grid;
  }

  .project-release-create__actions .button {
    width: 100%;
  }
}
</style>
