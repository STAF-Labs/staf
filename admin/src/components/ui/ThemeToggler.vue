<script setup lang="ts">
import { Moon, Sun } from '@lucide/vue'
import { useTheme } from '@/shared/theme/theme'

const { theme, toggleTheme } = useTheme()
</script>

<template>
  <button
    type="button"
    class="toggler-theme"
    :class="{ 'is-dark': theme === 'dark' }"
    :aria-label="theme === 'dark' ? 'Включить светлую тему' : 'Включить темную тему'"
    @click="toggleTheme"
  >
    <span class="toggler-theme__thumb" aria-hidden="true" />

    <span class="toggler-theme__icon toggler-theme__icon--light" aria-hidden="true">
      <Sun :size="16" :stroke-width="1.8" />
    </span>

    <span class="toggler-theme__icon toggler-theme__icon--dark" aria-hidden="true">
      <Moon :size="16" :stroke-width="1.8" />
    </span>
  </button>
</template>
