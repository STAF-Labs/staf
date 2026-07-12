<script setup lang="ts">
import { Search, Settings2 } from '@lucide/vue'
import type { DataColumn } from '@/shared/data/table'

defineProps<{
  search: string
  columns: DataColumn[]
}>()

const emit = defineEmits<{
  'update:search': [value: string]
  'toggle-column': [key: string]
}>()
</script>

<template>
  <div class="data-toolbar">
    <label class="data-toolbar__search">
      <Search class="data-toolbar__search-icon" :size="18" :stroke-width="1.9" aria-hidden="true" />
      <input
        class="data-toolbar__search-input"
        type="search"
        :value="search"
        placeholder="Поиск"
        @input="emit('update:search', ($event.target as HTMLInputElement).value)"
      >
    </label>

    <div class="data-toolbar__actions">
      <details class="data-toolbar__columns">
        <summary class="data-toolbar__button">
          <Settings2 :size="18" :stroke-width="1.9" aria-hidden="true" />
          <span>Колонки</span>
        </summary>

        <div class="data-toolbar__columns-menu">
          <label v-for="column in columns" :key="column.key" class="data-toolbar__column-option">
            <input
              class="checkbox-control"
              type="checkbox"
              :checked="column.visible"
              @change="emit('toggle-column', column.key)"
            >
            <span>{{ column.label }}</span>
          </label>
        </div>
      </details>
    </div>
  </div>
</template>
