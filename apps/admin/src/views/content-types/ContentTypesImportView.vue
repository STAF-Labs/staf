<script setup lang="ts">
import { ArrowLeft, FileSpreadsheet, Pencil, Trash2 } from '@lucide/vue'
import { AxiosError } from 'axios'
import { computed, ref } from 'vue'
import DataTable from '@/components/data/DataTable.vue'
import AppShell from '@/components/layout/AppShell.vue'
import type { DataColumn } from '@/shared/data/table'
import {
  importContentTypeFile,
  validateContentTypeImportFile,
  type ContentTypeImportRow,
} from '@/shared/content-types/content-types'

const acceptedImportExtensions = ['.csv', '.xlsx']
const acceptedImportTypes = [
  'text/csv',
  'application/csv',
  'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
]
const importAccept = [
  ...acceptedImportExtensions,
  ...acceptedImportTypes,
].join(',')

const importInput = ref<HTMLInputElement | null>(null)
const selectedFile = ref<File | null>(null)
const previewRows = ref<ContentTypeImportRow[]>([])
const fileError = ref('')
const message = ref('')
const isValidatingFile = ref(false)
const isImporting = ref(false)

const previewColumns: DataColumn[] = [
  { key: 'row', label: 'Строка', visible: true },
  { key: 'name', label: 'Название', visible: true },
  { key: 'is_public', label: 'Публичный', visible: true },
]
const previewTableRows = computed<Record<string, unknown>[]>(() => previewRows.value.map((row) => ({
  ...row,
  is_public: row.is_public ? 'Да' : 'Нет',
})))
const previewTitle = computed(() => `Значений в файле: ${previewRows.value.length}.`)

function openFilePicker(): void {
  importInput.value?.click()
}

async function chooseFile(event: Event): Promise<void> {
  const input = event.target as HTMLInputElement
  const [file] = Array.from(input.files ?? [])

  fileError.value = ''
  message.value = ''

  if (!file) {
    selectedFile.value = null
    previewRows.value = []

    return
  }

  const extension = file.name.slice(file.name.lastIndexOf('.')).toLocaleLowerCase('ru-RU')

  if (!acceptedImportExtensions.includes(extension) && !acceptedImportTypes.includes(file.type)) {
    fileError.value = 'Выберите CSV в UTF-8 или Excel-файл .xlsx.'
    selectedFile.value = null
    previewRows.value = []
    input.value = ''

    return
  }

  isValidatingFile.value = true

  try {
    const response = await validateContentTypeImportFile(file)

    selectedFile.value = file
    previewRows.value = response.rows
  } catch (error) {
    fileError.value = fileValidationMessage(error)
    selectedFile.value = null
    previewRows.value = []
    input.value = ''
  } finally {
    isValidatingFile.value = false
  }
}

function removeFile(clearMessage = true): void {
  selectedFile.value = null
  previewRows.value = []
  fileError.value = ''

  if (clearMessage) {
    message.value = ''
  }

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
    const response = await importContentTypeFile(selectedFile.value)

    message.value = `Импорт завершен. Создано: ${response.imported_count}. Пропущено: ${response.skipped_count}.`
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
    const firstError = errors && typeof errors === 'object'
      ? Object.values(errors).flat().find((value) => typeof value === 'string')
      : null

    return typeof firstError === 'string' ? firstError : 'Проверьте структуру файла.'
  }

  return 'Не удалось проверить файл. Попробуйте выбрать его еще раз.'
}
</script>

<template>
  <AppShell>
    <section class="data-page">
      <header class="data-page__header content-type-import-header">
        <RouterLink class="data-page__back-link content-type-import-header__back" :to="{ name: 'content-types.index' }">
          <ArrowLeft :size="17" :stroke-width="1.9" aria-hidden="true" />
          <span>Типы контента</span>
        </RouterLink>

        <div>
          <h2 class="data-page__title">Импорт типов контента</h2>
          <p class="data-page__subtitle">Файл должен содержать только базовые типы контента.</p>
        </div>
      </header>

      <div class="data-table-panel content-type-import-panel">
        <div class="content-type-import-panel__icon" aria-hidden="true">
          <FileSpreadsheet :size="42" :stroke-width="1.9" />
        </div>

        <section class="content-type-import-format" aria-label="Описание формата файла">
          <h3>Формат файла</h3>
          <p>
            Подготовьте обычную таблицу: первая строка — заголовки колонок, ниже — типы контента,
            которые нужно добавить в справочник. Можно загрузить CSV в UTF-8 или Excel-файл `.xlsx`.
            Обязательная колонка только одна — `name`. В ней пишется название типа контента:
            например `Мод`, `Карта`, `Текстура` или `Плагин`.
          </p>
          <p>
            Если нужно заранее указать видимость, добавьте колонку `is_public`. Для публичных типов
            можно писать `true`, `1`, `yes` или `да`; для скрытых — `false`, `0`, `no` или `нет`.
            Если ячейка пустая, тип будет считаться публичным. Игру в этом файле указывать не нужно:
            привязка типа контента к конкретной игре настраивается отдельно в форме игры.
          </p>

          <div class="content-type-import-example">
            <span class="content-type-import-example__title">Пример CSV</span>
            <pre><code>name,is_public
Мод,да
Карта,true
Текстура,1
Плагин,нет</code></pre>
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
          >

          <span class="content-type-import-upload__icon">
            <FileSpreadsheet :size="30" :stroke-width="1.9" aria-hidden="true" />
          </span>

          <span class="content-type-import-upload__content">
            <span class="content-type-import-upload__title">Файл импорта</span>
            <span class="content-type-import-upload__file">
              {{ isValidatingFile ? 'Проверяем файл...' : selectedFile?.name ?? 'Выберите CSV или Excel-файл' }}
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
          {{ isValidatingFile ? 'Проверяем заголовки таблицы на сервере.' : 'Поддерживаются CSV в UTF-8 и Excel .xlsx.' }}
        </p>
      </div>

      <p v-if="message" class="data-page__message">{{ message }}</p>

      <div v-if="previewRows.length > 0" class="data-table-panel content-type-import-preview">
        <p class="content-type-import-preview__count">{{ previewTitle }}</p>

        <DataTable
          :columns="previewColumns"
          :rows="previewTableRows"
          :loading="isImporting"
          empty-text="В файле нет значений"
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
            Импортировать
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

.content-type-import-panel__copy {
  display: grid;
  gap: 8px;
}

.content-type-import-panel__copy h3 {
  margin: 0;
  color: var(--color-text);
  font-size: 18px;
}

.content-type-import-panel__copy p {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 14px;
}

.content-type-import-format {
  display: grid;
  gap: 14px;
  width: 100%;
}

.content-type-import-example {
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

.content-type-import-help {
  margin: -12px 0 0;
  color: var(--color-text-muted);
  font-size: 13px;
  line-height: 1.5;
}

.content-type-import-help--error {
  color: var(--color-danger);
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
}
</style>
