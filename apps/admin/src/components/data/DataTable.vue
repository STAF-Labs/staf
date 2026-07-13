<script setup lang="ts">
import type { DataColumn } from '@/shared/data/table'

defineProps<{
  columns: DataColumn[]
  rows: Record<string, unknown>[]
  loading?: boolean
  emptyText?: string
}>()

function valueFor(row: Record<string, unknown>, key: string): string {
  const value = row[key]

  if (typeof value === 'boolean') {
    return value ? 'Да' : 'Нет'
  }

  if (value === null || value === undefined || value === '') {
    return '—'
  }

  return String(value)
}
</script>

<template>
  <div class="data-table">
    <table>
      <thead>
        <tr>
          <th v-for="column in columns" :key="column.key" scope="col">
            {{ column.label }}
          </th>
        </tr>
      </thead>

      <tbody>
        <tr v-if="loading">
          <td :colspan="Math.max(columns.length, 1)">Загрузка...</td>
        </tr>

        <tr v-else-if="rows.length === 0">
          <td :colspan="Math.max(columns.length, 1)">{{ emptyText ?? 'Нет данных' }}</td>
        </tr>

        <template v-else>
          <tr v-for="row in rows" :key="String(row.id)">
            <td v-for="column in columns" :key="column.key">
              <slot
                :name="`cell-${column.key}`"
                :row="row"
                :value="row[column.key]"
                :column="column"
              >
                {{ valueFor(row, column.key) }}
              </slot>
            </td>
          </tr>
        </template>
      </tbody>
    </table>
  </div>
</template>
