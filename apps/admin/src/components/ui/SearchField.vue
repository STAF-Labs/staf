<script setup lang="ts">
import { Search } from '@lucide/vue'

withDefaults(
  defineProps<{
    modelValue: string
    placeholder?: string
    disabled?: boolean
    ariaLabel?: string
  }>(),
  {
    placeholder: 'Поиск',
    disabled: false,
    ariaLabel: 'Поиск',
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

function updateValue(event: Event): void {
  emit('update:modelValue', (event.target as HTMLInputElement).value)
}
</script>

<template>
  <label class="game-filter-search">
    <Search class="game-filter-search__icon" :size="18" :stroke-width="1.9" aria-hidden="true" />
    <input
      class="game-filter-search__input"
      type="search"
      :value="modelValue"
      :placeholder="placeholder"
      :disabled="disabled"
      :aria-label="ariaLabel"
      @input="updateValue"
    />
  </label>
</template>
