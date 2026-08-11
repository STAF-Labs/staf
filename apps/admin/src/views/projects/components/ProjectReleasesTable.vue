<script setup lang="ts">
import { Pencil, Trash2 } from '@lucide/vue'
import type { GameDimension } from '@/shared/games/games'
import type { ProjectRelease } from '@/shared/projects/projects'
import type { DataColumn } from '@/shared/data/table'

const props = defineProps<{
  releases: ProjectRelease[]
  releaseFilters: GameDimension[]
  columns: DataColumn[]
}>()

const emit = defineEmits<{
  'delete-release': [release: ProjectRelease]
  'edit-release': [release: ProjectRelease]
}>()

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
  <div class="project-releases-table">
    <table>
      <thead>
        <tr>
          <th v-for="column in columns" :key="column.key" scope="col">
            {{ column.label }}
          </th>
          <th class="project-releases-table__actions-heading" scope="col">
            Действия
          </th>
        </tr>
      </thead>

      <tbody>
        <tr v-for="release in releases" :key="release.id">
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
          <td class="project-releases-table__actions-cell">
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
</style>
