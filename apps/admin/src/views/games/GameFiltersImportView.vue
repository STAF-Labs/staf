<script setup lang="ts">
import { ArrowLeft, FileSpreadsheet, Pencil, Trash2 } from '@lucide/vue'
import { AxiosError } from 'axios'
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import DataTable from '@/components/data/DataTable.vue'
import AppShell from '@/components/layout/AppShell.vue'
import type { DataColumn } from '@/shared/data/table'
import {
  fetchGame,
  fetchGameContentTypes,
  importGameDimensionFile,
  validateGameDimensionImportFile,
  type GameContentTypeListItem,
  type GameDetail,
  type GameDimensionImportFilterRow,
  type GameDimensionImportValueRow,
} from '@/shared/games/games'

const maxFileSize = 10 * 1024 * 1024
const importAccept = [
  '.xlsx',
  'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
].join(',')

const route = useRoute()
const gameId = Number(route.params.id)
const gameContentTypeId = Number(route.params.gameContentTypeId)
const game = ref<GameDetail | null>(null)
const gameContentType = ref<GameContentTypeListItem | null>(null)
const importInput = ref<HTMLInputElement | null>(null)
const selectedFile = ref<File | null>(null)
const previewFilters = ref<GameDimensionImportFilterRow[]>([])
const previewValues = ref<GameDimensionImportValueRow[]>([])
const fileError = ref('')
const pageError = ref('')
const message = ref('')
const isValidatingFile = ref(false)
const isImporting = ref(false)

const previewColumns: DataColumn[] = [
  { key: 'sheet', label: 'Лист', visible: true },
  { key: 'row', label: 'Строка', visible: true },
  { key: 'key', label: 'Ключ', visible: true },
  { key: 'name', label: 'Название', visible: true },
  { key: 'context', label: 'Настройки / связь', visible: true },
]

const backRoute = computed(() => ({
  name: 'games.show',
  params: { id: route.params.id },
}))
const contextTitle = computed(() => {
  if (!game.value || !gameContentType.value) {
    return 'Загрузка данных игры и типа контента…'
  }

  return `${game.value.name} · ${gameContentType.value.content_type_name ?? 'Тип контента'}`
})
const previewRows = computed<Record<string, unknown>[]>(() => [
  ...previewFilters.value.map((filter) => ({
    sheet: 'filters',
    row: filter.row,
    key: filter.filter_key,
    name: filter.name,
    context: `${filter.selection_mode} · Уровень: ${filter.applies_to === 'project' ? 'Проект' : 'Релиз'} · В каталоге: ${filter.is_filterable ? 'Да' : 'Нет'} · Обязательный: ${filter.is_required ? 'Да' : 'Нет'}`,
  })),
  ...previewValues.value.map((value) => ({
    sheet: 'values',
    row: value.row,
    key: value.value_key,
    name: value.name,
    context: `${value.filter_key} · Родитель: ${value.parent_key ?? '—'} · Порядок: ${value.sort_order}`,
  })),
])
const previewTitle = computed(
  () => `Фильтров: ${previewFilters.value.length}. Значений: ${previewValues.value.length}.`,
)

function openFilePicker(): void {
  importInput.value?.click()
}

async function chooseFile(event: Event): Promise<void> {
  const input = event.target as HTMLInputElement
  const [file] = Array.from(input.files ?? [])

  await selectFile(file)
}

async function selectFile(file?: File): Promise<void> {
  fileError.value = ''
  message.value = ''

  if (!file) {
    removeFile()

    return
  }

  if (!file.name.toLocaleLowerCase('ru-RU').endsWith('.xlsx')) {
    fileError.value = 'Выберите Excel-файл в формате .xlsx.'
    selectedFile.value = null
    removeInputValue()

    return
  }

  if (file.size > maxFileSize) {
    fileError.value = 'Размер файла не должен превышать 10 МБ.'
    selectedFile.value = null
    removeInputValue()

    return
  }

  isValidatingFile.value = true

  try {
    const response = await validateGameDimensionImportFile(gameId, gameContentTypeId, file)

    selectedFile.value = file
    previewFilters.value = response.filters
    previewValues.value = response.values
  } catch (error) {
    fileError.value = fileValidationMessage(error)
    selectedFile.value = null
    previewFilters.value = []
    previewValues.value = []
    removeInputValue()
  } finally {
    isValidatingFile.value = false
  }
}

function removeFile(clearMessage = true): void {
  selectedFile.value = null
  previewFilters.value = []
  previewValues.value = []
  fileError.value = ''

  if (clearMessage) {
    message.value = ''
  }

  removeInputValue()
}

function removeInputValue(): void {
  if (importInput.value) {
    importInput.value.value = ''
  }
}

async function importFile(): Promise<void> {
  if (!selectedFile.value || isImporting.value) {
    return
  }

  isImporting.value = true
  fileError.value = ''
  message.value = ''

  try {
    const response = await importGameDimensionFile(gameId, gameContentTypeId, selectedFile.value)

    message.value = `Импорт завершён. Создано фильтров: ${response.created_filters}, значений: ${response.created_values}. Пропущено существующих фильтров: ${response.reused_filters}, значений: ${response.skipped_values}.`
    removeFile(false)
  } catch (error) {
    fileError.value = fileValidationMessage(error)
  } finally {
    isImporting.value = false
  }
}

function fileValidationMessage(error: unknown): string {
  if (error instanceof AxiosError && error.response?.status === 422) {
    const errors = error.response.data?.errors
    const firstError =
      errors && typeof errors === 'object'
        ? Object.values(errors)
            .flat()
            .find((value) => typeof value === 'string')
        : null

    return typeof firstError === 'string' ? firstError : 'Проверьте структуру Excel-файла.'
  }

  return 'Не удалось проверить файл. Попробуйте выбрать его ещё раз.'
}

async function loadContext(): Promise<void> {
  if (!Number.isInteger(gameId) || !Number.isInteger(gameContentTypeId)) {
    pageError.value = 'Не удалось определить игру или тип контента.'

    return
  }

  pageError.value = ''

  try {
    const [gameResponse, contentTypesResponse] = await Promise.all([
      fetchGame(gameId),
      fetchGameContentTypes(gameId),
    ])
    const currentGameContentType = contentTypesResponse.data.find(
      (item) => item.id === gameContentTypeId,
    )

    if (!currentGameContentType) {
      pageError.value = 'Этот тип контента не подключён к выбранной игре.'

      return
    }

    game.value = gameResponse
    gameContentType.value = currentGameContentType
  } catch {
    pageError.value = 'Не удалось загрузить данные игры и типа контента.'
  }
}

onMounted(() => {
  void loadContext()
})
</script>

<template>
  <AppShell>
    <section class="data-page">
      <header class="data-page__header content-type-import-header">
        <RouterLink class="data-page__back-link content-type-import-header__back" :to="backRoute">
          <ArrowLeft :size="17" :stroke-width="1.9" aria-hidden="true" />
          <span>Вернуться к игре</span>
        </RouterLink>

        <div>
          <h2 class="data-page__title">Импорт фильтров и значений</h2>
          <p class="data-page__subtitle">{{ contextTitle }}</p>
        </div>
      </header>

      <p v-if="pageError" class="data-page__message content-type-import-help--error">
        {{ pageError }}
      </p>

      <div class="data-table-panel content-type-import-panel">
        <div class="content-type-import-panel__icon" aria-hidden="true">
          <FileSpreadsheet :size="42" :stroke-width="1.9" />
        </div>

        <section class="content-type-import-format" aria-label="Описание формата файла">
          <h3>Формат файла</h3>
          <p>
            Подготовьте книгу Excel `.xlsx` с двумя листами. На листе `filters` указываются фильтры,
            которые нужно добавить выбранному типу контента. На листе `values` перечисляются их
            значения. Игру и тип контента в файле указывать не нужно — импорт выполняется для
            связки, указанной в заголовке страницы.
          </p>
          <p>
            Колонка `filter_key` связывает строки двух листов. Она используется только внутри файла.
            `value_key` идентифицирует значение, а необязательный `parent_key` позволяет построить
            иерархию. Режим выбора задаётся значением `single` или `multiple`.
          </p>

          <div class="content-type-import-example">
            <span class="content-type-import-example__title">Лист `filters`</span>
            <pre><code>filter_key,name,selection_mode,applies_to,is_filterable,is_required,is_active
category,Категория,multiple,project,true,false,true
game_version,Версия игры,single,release,true,true,true</code></pre>
          </div>

          <div class="content-type-import-example">
            <span class="content-type-import-example__title">Лист `values`</span>
            <pre><code>filter_key,value_key,name,parent_key,sort_order,is_active
category,mods,Модификации,,10,true
category,plugins,Плагины,,20,true
game_version,v1_21,1.21,,10,true</code></pre>
          </div>
        </section>

        <p v-if="fileError" class="content-type-import-help content-type-import-help--error">
          {{ fileError }}
        </p>

        <div
          class="content-type-import-upload"
          :class="{
            'content-type-import-upload--empty': !selectedFile,
            'content-type-import-upload--invalid': fileError,
          }"
          @click="!selectedFile && !isValidatingFile ? openFilePicker() : undefined"
        >
          <input
            ref="importInput"
            type="file"
            :accept="importAccept"
            :disabled="isValidatingFile"
            @change="chooseFile"
          />

          <span class="content-type-import-upload__icon">
            <FileSpreadsheet :size="30" :stroke-width="1.9" aria-hidden="true" />
          </span>

          <span class="content-type-import-upload__content">
            <span class="content-type-import-upload__title">Файл импорта</span>
            <span class="content-type-import-upload__file">
              {{
                isValidatingFile ? 'Проверяем файл…' : (selectedFile?.name ?? 'Выберите Excel-файл')
              }}
            </span>
          </span>

          <div v-if="selectedFile" class="content-type-import-upload__actions">
            <button
              class="content-type-import-upload__action icon-action"
              type="button"
              aria-label="Заменить файл"
              title="Заменить файл"
              @click.stop="openFilePicker"
            >
              <Pencil :size="17" :stroke-width="2" aria-hidden="true" />
            </button>

            <button
              class="content-type-import-upload__action icon-action icon-action--danger"
              type="button"
              aria-label="Удалить файл"
              title="Удалить файл"
              @click.stop="() => removeFile()"
            >
              <Trash2 :size="17" :stroke-width="2" aria-hidden="true" />
            </button>
          </div>
        </div>

        <p class="content-type-import-help">
          {{
            isValidatingFile
              ? 'Проверяем листы, заголовки, значения и иерархию.'
              : 'Поддерживается Excel `.xlsx` размером до 10 МБ.'
          }}
        </p>
      </div>

      <p v-if="message" class="data-page__message">{{ message }}</p>

      <div v-if="previewRows.length > 0" class="data-table-panel content-type-import-preview">
        <p class="content-type-import-preview__count">{{ previewTitle }}</p>

        <DataTable
          :columns="previewColumns"
          :rows="previewRows"
          :loading="isImporting"
          empty-text="В файле нет данных"
        />

        <div class="content-type-import-preview__actions">
          <button
            class="content-type-import-preview__button content-type-import-preview__button--secondary"
            type="button"
            :disabled="isImporting"
            @click="() => removeFile()"
          >
            Очистить
          </button>

          <button
            class="content-type-import-preview__button content-type-import-preview__button--primary"
            type="button"
            :disabled="isImporting || !selectedFile"
            @click="importFile"
          >
            {{ isImporting ? 'Импортируем…' : 'Импортировать' }}
          </button>
        </div>
      </div>
    </section>
  </AppShell>
</template>

<style scoped>
.content-type-import-header {
  gap: 12px;
}

.content-type-import-header__back {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  text-decoration: none;
}

.content-type-import-panel {
  gap: 22px;
}

.content-type-import-panel__icon {
  display: grid;
  place-items: center;
  justify-self: center;
  width: 82px;
  height: 82px;
  color: var(--color-success);
  background: color-mix(in srgb, var(--color-success) 10%, transparent);
  border: 1px solid color-mix(in srgb, var(--color-success) 28%, transparent);
  border-radius: var(--radius-lg);
}

.content-type-import-format {
  display: grid;
  gap: 14px;
  width: 100%;
}

.content-type-import-format h3,
.content-type-import-example__title {
  margin: 0;
  color: var(--color-text);
  font-size: 18px;
  font-weight: 850;
}

.content-type-import-format p {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 14px;
  line-height: 1.55;
}

.content-type-import-example {
  display: grid;
  gap: 10px;
  width: 100%;
  padding-top: 4px;
}

.content-type-import-example pre {
  overflow-x: auto;
  margin: 0;
  padding: 12px;
  color: var(--color-text);
  background: var(--color-bg);
  border: 1px solid var(--color-border-soft);
  border-radius: var(--radius-md);
}

.content-type-import-example code {
  font-family: var(--font-mono, monospace);
  font-size: 13px;
  line-height: 1.55;
}

.content-type-import-upload {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 14px;
  min-height: 170px;
  padding: 16px;
  color: var(--color-text);
  background: var(--color-bg-soft);
  border: 1px dashed var(--color-border);
  border-radius: var(--radius-md);
  overflow: hidden;
}

.content-type-import-upload:hover {
  background: var(--color-surface-hover);
  border-color: var(--color-primary);
}

.content-type-import-upload--empty {
  cursor: pointer;
}

.content-type-import-upload--invalid {
  border-color: var(--color-danger);
}

.content-type-import-upload input {
  position: absolute;
  width: 1px;
  height: 1px;
  opacity: 0;
  pointer-events: none;
}

.content-type-import-upload__icon {
  display: grid;
  place-items: center;
  width: 48px;
  height: 48px;
  color: var(--color-primary);
  background: color-mix(in srgb, var(--color-primary) 10%, transparent);
  border-radius: var(--radius-md);
}

.content-type-import-upload__content {
  display: grid;
  gap: 4px;
  min-width: 0;
  text-align: center;
}

.content-type-import-upload__title {
  font-size: 14px;
  font-weight: 700;
}

.content-type-import-upload__file {
  color: var(--color-text-muted);
  font-size: 13px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.content-type-import-upload__actions {
  position: absolute;
  right: 10px;
  bottom: 10px;
  z-index: 2;
  display: flex;
  gap: 8px;
}

.content-type-import-help--error {
  color: var(--color-danger);
}

.content-type-import-help {
  margin: -12px 0 0;
  color: var(--color-text-muted);
  font-size: 13px;
  line-height: 1.5;
}

.content-type-import-preview {
  gap: 14px;
}

.content-type-import-preview__count {
  margin: 0;
  color: var(--color-text);
  font-size: 14px;
  font-weight: 800;
}

.content-type-import-preview__actions {
  display: flex;
  justify-content: flex-end;
  flex-wrap: wrap;
  gap: 10px;
}

.content-type-import-preview__button {
  min-height: 40px;
  padding: 0 14px;
  border: 1px solid transparent;
  border-radius: var(--radius-md);
  cursor: pointer;
  font: inherit;
  font-size: 13px;
  font-weight: 800;
}

.content-type-import-preview__button:disabled {
  cursor: not-allowed;
  opacity: 0.6;
}

.content-type-import-preview__button--secondary {
  color: var(--color-text);
  background: var(--color-bg-muted);
  border-color: var(--color-border-soft);
}

.content-type-import-preview__button--secondary:hover:not(:disabled) {
  background: var(--color-surface-hover);
}

.content-type-import-preview__button--primary {
  color: var(--color-primary-text);
  background: var(--color-primary);
  border-color: var(--color-primary);
}

.content-type-import-preview__button--primary:hover:not(:disabled) {
  background: var(--color-primary-hover);
  border-color: var(--color-primary-hover);
}
</style>
