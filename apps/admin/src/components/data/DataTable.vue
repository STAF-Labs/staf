<script setup lang="ts">
import { ChevronLeft, ChevronRight } from '@lucide/vue'
import { computed, ref, watch } from 'vue'
import type { DataColumn } from '@/shared/data/table'

const props = withDefaults(defineProps<{
  columns: DataColumn[]
  rows: Record<string, unknown>[]
  loading?: boolean
  emptyText?: string
  pageSize?: number
  paginated?: boolean
}>(), {
  loading: false,
  emptyText: 'Нет данных',
  pageSize: 20,
  paginated: true,
})

const currentPage = ref(1)
const totalPages = computed(() => Math.max(1, Math.ceil(props.rows.length / props.pageSize)))
const paginationVisible = computed(() => props.paginated && props.rows.length > props.pageSize)
const pageStart = computed(() => (currentPage.value - 1) * props.pageSize)
const visibleRows = computed(() => {
  if (!props.paginated) {
    return props.rows
  }

  return props.rows.slice(pageStart.value, pageStart.value + props.pageSize)
})
const paginationLabel = computed(() => {
  if (props.rows.length === 0) {
    return '0 из 0'
  }

  const from = pageStart.value + 1
  const to = Math.min(pageStart.value + props.pageSize, props.rows.length)

  return `${from}-${to} из ${props.rows.length}`
})
const pageItems = computed(() => {
  const pages: number[] = []
  const firstPage = Math.max(1, currentPage.value - 2)
  const lastPage = Math.min(totalPages.value, firstPage + 4)

  for (let page = Math.max(1, lastPage - 4); page <= lastPage; page++) {
    pages.push(page)
  }

  return pages
})

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

function rowKey(row: Record<string, unknown>, index: number): string {
  return String(row.id ?? row.row ?? index)
}

function setPage(page: number): void {
  currentPage.value = Math.min(Math.max(page, 1), totalPages.value)
}

watch(() => props.rows, () => {
  currentPage.value = 1
})

watch(totalPages, (pages) => {
  if (currentPage.value > pages) {
    currentPage.value = pages
  }
})
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
          <td :colspan="Math.max(columns.length, 1)">{{ emptyText }}</td>
        </tr>

        <template v-else>
          <tr v-for="(row, index) in visibleRows" :key="rowKey(row, index)">
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

    <nav v-if="paginationVisible" class="data-table-pagination" aria-label="Пагинация таблицы">
      <span class="data-table-pagination__summary">{{ paginationLabel }}</span>

      <div class="data-table-pagination__controls">
        <button
          class="data-table-pagination__button"
          type="button"
          :disabled="currentPage === 1"
          aria-label="Предыдущая страница"
          @click="setPage(currentPage - 1)"
        >
          <ChevronLeft :size="17" :stroke-width="1.9" aria-hidden="true" />
        </button>

        <button
          v-for="page in pageItems"
          :key="page"
          class="data-table-pagination__button data-table-pagination__page"
          :class="{ 'data-table-pagination__page--active': page === currentPage }"
          type="button"
          :aria-current="page === currentPage ? 'page' : undefined"
          @click="setPage(page)"
        >
          {{ page }}
        </button>

        <button
          class="data-table-pagination__button"
          type="button"
          :disabled="currentPage === totalPages"
          aria-label="Следующая страница"
          @click="setPage(currentPage + 1)"
        >
          <ChevronRight :size="17" :stroke-width="1.9" aria-hidden="true" />
        </button>
      </div>
    </nav>
  </div>
</template>
