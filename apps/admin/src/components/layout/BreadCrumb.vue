<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink, useRoute } from 'vue-router'

type Crumb = {
  label: string
  routeName: string
}

const route = useRoute()

const crumbs = computed<Crumb[]>(() => {
  const items: Crumb[] = [
    {
      label: 'Главная',
      routeName: 'dashboard',
    },
  ]

  if (route.name !== 'dashboard') {
    items.push({
      label: typeof route.meta.title === 'string' ? route.meta.title : 'Страница',
      routeName: String(route.name),
    })
  }

  return items
})
</script>

<template>
  <nav class="breadcrumb" aria-label="Навигационная цепочка">
    <ol class="breadcrumb__list">
      <li v-for="(crumb, index) in crumbs" :key="crumb.routeName" class="breadcrumb__item">
        <RouterLink class="breadcrumb__link" :to="{ name: crumb.routeName }">
          {{ crumb.label }}
        </RouterLink>

        <span v-if="index < crumbs.length - 1" class="breadcrumb__separator" aria-hidden="true">&gt;</span>
      </li>
    </ol>
  </nav>
</template>

<style scoped>
.breadcrumb {
  color: var(--color-text-muted);
  font-size: 14px;
}

.breadcrumb__list {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.breadcrumb__item {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.breadcrumb__link {
  color: var(--color-primary);
  font-weight: 500;
}

.breadcrumb__link:hover {
  color: var(--color-primary-hover);
}

.breadcrumb__separator {
  color: var(--color-text-soft);
}
</style>
