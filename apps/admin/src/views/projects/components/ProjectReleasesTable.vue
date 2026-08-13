<script setup lang="ts">
import { ChevronRight, Download, Pencil, Trash2 } from '@lucide/vue'
import { ref } from 'vue'
import RichTextRenderer from '@/components/ui/RichTextRenderer.vue'
import type { GameDimension } from '@/shared/games/games'
import type { ProjectRelease } from '@/shared/projects/projects'
import type { DataColumn } from '@/shared/data/table'

const props = defineProps<{
  releases: ProjectRelease[]
  releaseFilters: GameDimension[]
  columns: DataColumn[]
  readonly?: boolean
  projectOwnerName?: string | null
}>()

const emit = defineEmits<{
  'delete-release': [release: ProjectRelease]
  'edit-release': [release: ProjectRelease]
  'open-release': [release: ProjectRelease]
}>()

const expandedReleaseId = ref<number | null>(null)
const expandedChangelogIds = ref<Set<number>>(new Set())

function releaseFilterValueLabel(release: ProjectRelease, filterId: number): string {
  const filter = props.releaseFilters.find((item) => item.id === filterId)

  if (!filter) {
    return '—'
  }

  const selectedIds = new Set(release.dimension_value_ids ?? [])
  const selectedValues = filter.values.filter((value) => selectedIds.has(value.id))

  if (selectedValues.length === 0) {
    return '—'
  }

  return selectedValues.map((value) => value.name).join(', ')
}

function releaseFilterSummary(release: ProjectRelease): string {
  const filterLabels = props.releaseFilters
    .map((filter) => releaseFilterValueLabel(release, filter.id))
    .filter((label) => label !== '—')

  if (filterLabels.length === 0) {
    return 'Фильтры не указаны'
  }

  return filterLabels.join(' · ')
}

function releaseCellValue(release: ProjectRelease, column: DataColumn): string {
  if (column.key === 'type') {
    return release.type_label ?? release.type
  }

  if (column.key === 'title') {
    return release.title
  }

  if (column.key === 'status') {
    return release.status_label ?? release.status
  }

  if (column.key === 'released_at') {
    return formatDate(release.released_at)
  }

  if (column.key.startsWith('filter:')) {
    return releaseFilterValueLabel(release, Number(column.key.replace('filter:', '')))
  }

  return '—'
}

function toggleReleaseSummary(release: ProjectRelease): void {
  if (!props.readonly) {
    return
  }

  expandedReleaseId.value = expandedReleaseId.value === release.id ? null : release.id
}

function toggleChangelog(releaseId: number): void {
  const nextExpandedIds = new Set(expandedChangelogIds.value)

  if (nextExpandedIds.has(releaseId)) {
    nextExpandedIds.delete(releaseId)
  } else {
    nextExpandedIds.add(releaseId)
  }

  expandedChangelogIds.value = nextExpandedIds
}

function isChangelogExpanded(releaseId: number): boolean {
  return expandedChangelogIds.value.has(releaseId)
}

function richTextToPlainText(value: unknown): string {
  if (Array.isArray(value)) {
    return value.map(richTextToPlainText).filter(Boolean).join('\n')
  }

  if (!value || typeof value !== 'object') {
    return typeof value === 'string' ? value : ''
  }

  if ('text' in value && typeof value.text === 'string') {
    return value.text
  }

  return 'content' in value ? richTextToPlainText(value.content) : ''
}

function releaseChangelogText(release: ProjectRelease): string {
  return richTextToPlainText(release.changelog).trim()
}

function hasChangelog(release: ProjectRelease): boolean {
  return releaseChangelogText(release) !== ''
}

function formatFileSize(value: number | null): string {
  if (!value) {
    return 'Размер неизвестен'
  }

  const units = ['B', 'KB', 'MB', 'GB']
  let size = value
  let unitIndex = 0

  while (size >= 1024 && unitIndex < units.length - 1) {
    size /= 1024
    unitIndex += 1
  }

  return `${size >= 10 || unitIndex === 0 ? Math.round(size) : size.toFixed(1)} ${units[unitIndex]}`
}

function formatDate(value: string | null): string {
  if (!value) {
    return '—'
  }

  return new Intl.DateTimeFormat('ru-RU', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  }).format(new Date(value))
}
</script>

<template>
  <div class="project-releases-table" :class="{ 'project-releases-table--readonly': props.readonly }">
    <table>
      <thead>
        <tr>
          <th
            v-if="props.readonly"
            class="project-releases-table__chevron-heading"
            scope="col"
            aria-label="Открыть"
          ></th>
          <th v-for="column in columns" :key="column.key" scope="col">
            {{ column.label }}
          </th>
          <th
            v-if="props.readonly"
            class="project-releases-table__download-heading"
            scope="col"
            aria-label="Скачать"
          ></th>
          <th v-if="!props.readonly" class="project-releases-table__actions-heading" scope="col">
            Действия
          </th>
        </tr>
      </thead>

      <tbody>
        <template v-for="release in releases" :key="release.id">
          <tr
            :class="{ 'project-releases-table__row--expanded': expandedReleaseId === release.id }"
            @click="toggleReleaseSummary(release)"
          >
            <td v-if="props.readonly" class="project-releases-table__chevron-cell">
              <ChevronRight
                class="project-releases-table__chevron"
                :class="{ 'project-releases-table__chevron--expanded': expandedReleaseId === release.id }"
                :size="18"
                :stroke-width="1.9"
                aria-hidden="true"
              />
            </td>
            <td v-for="column in columns" :key="column.key">
              <span
                v-if="column.key === 'type'"
                class="project-releases-table__badge"
              >
                {{ releaseCellValue(release, column) }}
              </span>

              <span
                v-else-if="column.key === 'status'"
                class="project-releases-table__status"
              >
                {{ releaseCellValue(release, column) }}
              </span>

              <span
                v-else
                :class="{ 'project-releases-table__empty': releaseCellValue(release, column) === '—' }"
              >
                {{ releaseCellValue(release, column) }}
              </span>
            </td>
            <td v-if="props.readonly" class="project-releases-table__download-cell">
              <a
                v-if="release.file_url"
                class="project-releases-table__download-action"
                :href="release.file_url"
                download
                aria-label="Скачать релиз"
                title="Скачать релиз"
                @click.stop
              >
                <Download :size="17" :stroke-width="1.9" aria-hidden="true" />
              </a>
              <button
                v-else
                class="project-releases-table__download-action"
                type="button"
                disabled
                aria-label="Файл релиза недоступен"
                title="Файл релиза недоступен"
                @click.stop
              >
                <Download :size="17" :stroke-width="1.9" aria-hidden="true" />
              </button>
            </td>
            <td v-if="!props.readonly" class="project-releases-table__actions-cell">
              <div class="project-releases-table__actions">
                <button
                  class="project-releases-table__icon-action"
                  type="button"
                  aria-label="Редактировать релиз"
                  title="Редактировать"
                  @click="emit('edit-release', release)"
                >
                  <Pencil :size="17" :stroke-width="1.9" aria-hidden="true" />
                </button>

                <button
                  class="project-releases-table__icon-action project-releases-table__icon-action--danger"
                  type="button"
                  aria-label="Удалить релиз"
                  title="Удалить"
                  @click="emit('delete-release', release)"
                >
                  <Trash2 :size="17" :stroke-width="1.9" aria-hidden="true" />
                </button>
              </div>
            </td>
          </tr>

          <tr
            v-if="props.readonly && expandedReleaseId === release.id"
            class="project-releases-table__summary-row"
          >
            <td :colspan="columns.length + 2">
              <section class="project-release-summary" aria-label="Сводка релиза">
                <h3>{{ release.file_name ?? release.title }}</h3>

                <div class="project-release-summary__meta">
                  <span class="project-release-summary__author">
                    <span class="project-release-summary__avatar" aria-hidden="true">
                      {{ (props.projectOwnerName ?? 'А').slice(0, 1).toUpperCase() }}
                    </span>
                    <span>{{ props.projectOwnerName ?? 'Автор не указан' }}</span>
                  </span>
                  <span>{{ formatDate(release.released_at) }}</span>
                  <span>{{ formatFileSize(release.file_size) }}</span>
                  <span>{{ releaseFilterSummary(release) }}</span>
                </div>

                <section class="project-release-summary__changelog">
                  <h4>Changelog</h4>
                  <div
                    v-if="hasChangelog(release)"
                    :class="{
                      'project-release-summary__changelog-text': true,
                      'project-release-summary__changelog-text--collapsed':
                        !isChangelogExpanded(release.id),
                    }"
                  >
                    <RichTextRenderer class="project-release-summary__rich-text" :value="release.changelog" />
                  </div>
                  <p v-else class="project-release-summary__changelog-empty">
                    Changelog пока не добавлен.
                  </p>
                  <button
                    v-if="hasChangelog(release)"
                    class="project-release-summary__changelog-toggle"
                    type="button"
                    @click.stop="toggleChangelog(release.id)"
                  >
                    {{ isChangelogExpanded(release.id) ? 'Свернуть' : 'Читать полностью' }}
                  </button>
                </section>

                <div class="project-release-summary__actions">
                  <a
                    v-if="release.file_url"
                    class="button button-primary"
                    :href="release.file_url"
                    download
                    @click.stop
                  >
                    <Download :size="18" :stroke-width="1.9" aria-hidden="true" />
                    <span>Скачать</span>
                  </a>
                  <button v-else class="button button-primary" type="button" disabled @click.stop>
                    <Download :size="18" :stroke-width="1.9" aria-hidden="true" />
                    <span>Скачать</span>
                  </button>

                  <button class="button button--secondary" type="button" @click.stop="emit('open-release', release)">
                    Страница релиза
                    <ChevronRight :size="17" :stroke-width="1.9" aria-hidden="true" />
                  </button>
                </div>
              </section>
            </td>
          </tr>
        </template>
      </tbody>
    </table>
  </div>
</template>

<style scoped>
.project-releases-table {
  width: 100%;
  overflow-x: auto;
}

.project-releases-table table {
  width: 100%;
  min-width: 760px;
  border-collapse: collapse;
}

.project-releases-table th,
.project-releases-table td {
  padding: 14px 16px;
  text-align: left;
  border-bottom: 1px solid var(--color-border-soft);
  vertical-align: middle;
}

.project-releases-table th {
  color: var(--color-text-muted);
  font-size: 12px;
  font-weight: 800;
}

.project-releases-table--readonly tbody tr {
  cursor: pointer;
  transition: background 160ms ease;
}

.project-releases-table--readonly tbody tr:hover {
  background: var(--color-surface-hover);
}

.project-releases-table--readonly tbody .project-releases-table__summary-row,
.project-releases-table--readonly tbody .project-releases-table__summary-row:hover {
  cursor: default;
  background: var(--color-surface);
}

.project-releases-table__badge,
.project-releases-table__status {
  display: inline-flex;
  align-items: center;
  min-height: 28px;
  padding: 0 9px;
  background: var(--color-bg-soft);
  border: 1px solid var(--color-border-soft);
  border-radius: 999px;
  font-size: 12px;
  font-weight: 750;
  white-space: nowrap;
}

.project-releases-table__empty {
  color: var(--color-text-muted);
}

.project-releases-table__actions-heading {
  position: sticky;
  right: 0;
  width: 112px;
  background: var(--color-surface);
  box-shadow: -1px 0 0 var(--color-border-soft);
}

.project-releases-table__chevron-heading {
  width: 48px;
}

.project-releases-table__download-heading {
  width: 56px;
}

.project-releases-table__chevron-cell {
  width: 48px;
  color: var(--color-text-soft);
  text-align: center;
}

.project-releases-table__chevron {
  transition: transform 160ms ease;
}

.project-releases-table__chevron--expanded {
  transform: rotate(90deg);
}

.project-releases-table__download-cell {
  width: 56px;
  text-align: right;
}

.project-releases-table__download-action {
  display: inline-grid;
  place-items: center;
  width: 32px;
  height: 32px;
  color: var(--color-text-muted);
  text-decoration: none;
  background: var(--color-bg-soft);
  border: 1px solid var(--color-border-soft);
  border-radius: var(--radius-sm);
  cursor: pointer;
  font: inherit;
}

.project-releases-table__download-action:disabled {
  cursor: not-allowed;
  opacity: 0.55;
}

.project-releases-table--readonly tbody tr:hover .project-releases-table__chevron-cell {
  color: var(--color-primary);
}

.project-releases-table--readonly tbody tr:hover .project-releases-table__download-action:not(:disabled) {
  color: var(--color-primary);
  background: var(--color-surface);
  border-color: color-mix(in srgb, var(--color-primary) 28%, var(--color-border-soft));
}

.project-releases-table__actions-cell {
  position: sticky;
  right: 0;
  background: var(--color-surface);
  box-shadow: -1px 0 0 var(--color-border-soft);
}

.project-releases-table__actions {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.project-releases-table__icon-action {
  display: inline-grid;
  place-items: center;
  width: 32px;
  height: 32px;
  color: var(--color-text-muted);
  background: var(--color-bg-soft);
  border: 1px solid var(--color-border-soft);
  border-radius: var(--radius-sm);
  cursor: pointer;
}

.project-releases-table__icon-action:hover {
  color: var(--color-text);
  background: var(--color-surface-hover);
  border-color: var(--color-border);
}

.project-releases-table__icon-action--danger:hover {
  color: var(--color-danger);
  border-color: color-mix(in srgb, var(--color-danger) 28%, var(--color-border-soft));
}

.project-release-summary {
  display: grid;
  gap: 16px;
  padding: 6px 6px 18px 48px;
  overflow: hidden;
  animation: release-summary-open 180ms ease-out;
}

.project-release-summary h3,
.project-release-summary h4,
.project-release-summary p {
  margin: 0;
}

.project-release-summary h3 {
  color: var(--color-primary);
  font-size: 15px;
  font-weight: 650;
}

.project-release-summary__meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px 18px;
  color: var(--color-text-muted);
  font-size: 14px;
}

.project-release-summary__author {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  color: var(--color-text);
  font-size: 15px;
  font-weight: 700;
}

.project-release-summary__avatar {
  display: inline-grid;
  place-items: center;
  width: 28px;
  height: 28px;
  color: var(--color-primary);
  background: color-mix(in srgb, var(--color-primary) 12%, transparent);
  border: 1px solid color-mix(in srgb, var(--color-primary) 24%, var(--color-border-soft));
  border-radius: 50%;
  font-size: 12px;
  font-weight: 850;
}

.project-release-summary__changelog {
  display: grid;
  gap: 8px;
}

.project-release-summary__changelog h4 {
  color: var(--color-text-muted);
  font-size: 12px;
  font-weight: 850;
  text-transform: uppercase;
}

.project-release-summary__changelog-text,
.project-release-summary__changelog-empty {
  color: var(--color-text-muted);
  font-size: 13px;
  line-height: 1.5;
}

.project-release-summary__changelog-text--collapsed {
  max-height: 120px;
  overflow: hidden;
}

.project-release-summary__rich-text {
  display: grid;
  gap: 8px;
}

.project-release-summary__rich-text :deep(p),
.project-release-summary__rich-text :deep(ul),
.project-release-summary__rich-text :deep(ol),
.project-release-summary__rich-text :deep(blockquote),
.project-release-summary__rich-text :deep(pre) {
  margin: 0;
}

.project-release-summary__rich-text :deep(h2),
.project-release-summary__rich-text :deep(h3),
.project-release-summary__rich-text :deep(h4) {
  margin: 4px 0 0;
  color: var(--color-text);
  font-size: 14px;
  font-weight: 800;
}

.project-release-summary__rich-text :deep(ul),
.project-release-summary__rich-text :deep(ol) {
  display: grid;
  gap: 4px;
  padding-left: 20px;
}

.project-release-summary__rich-text :deep(blockquote) {
  padding-left: 12px;
  border-left: 3px solid var(--color-border);
}

.project-release-summary__rich-text :deep(code) {
  padding: 2px 5px;
  background: var(--color-bg-soft);
  border-radius: var(--radius-sm);
  font-size: 12px;
}

.project-release-summary__rich-text :deep(pre) {
  overflow-x: auto;
  padding: 10px;
  background: var(--color-bg-soft);
  border-radius: var(--radius-md);
}

.project-release-summary__changelog-toggle {
  width: fit-content;
  padding: 0;
  color: var(--color-primary);
  background: none;
  border: 0;
  cursor: pointer;
  font: inherit;
  font-size: 13px;
  font-weight: 800;
}

.project-release-summary__actions {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  padding-top: 14px;
  border-top: 1px solid var(--color-border-soft);
}

.project-release-summary__actions .button {
  flex: 0 0 auto;
  gap: 8px;
  width: fit-content;
  white-space: nowrap;
}

@keyframes release-summary-open {
  from {
    max-height: 0;
    opacity: 0;
    transform: translateY(-6px);
  }

  to {
    max-height: 520px;
    opacity: 1;
    transform: translateY(0);
  }
}
</style>
