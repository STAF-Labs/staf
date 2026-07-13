<script setup lang="ts">
import { computed, ref, useSlots } from 'vue'
import { Search, Settings2, Funnel } from '@lucide/vue'
import type { DataColumn } from '@/shared/data/table'

defineProps<{
  search: string
  columns: DataColumn[]
}>()

const emit = defineEmits<{
  'update:search': [value: string]
  'toggle-column': [key: string]
}>()

const filtersOpen = ref(false)
const slots = useSlots()
const hasFilters = computed(() => Boolean(slots.filters))

function toggleFilters(): void {
  filtersOpen.value = !filtersOpen.value
}
</script>

<template>
  <div class="data-toolbar">
    <div class="data-toolbar__top">
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
        <button
          v-if="hasFilters"
          class="data-toolbar__button"
          type="button"
          :aria-expanded="filtersOpen"
          @click="toggleFilters"
        >
          <Funnel :size="18" :stroke-width="1.9" aria-hidden="true" />
          <span>Фильтры</span>
        </button>

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

    <div v-if="hasFilters && filtersOpen" class="data-toolbar__filters">
      <slot name="filters" />
    </div>
  </div>
</template>
