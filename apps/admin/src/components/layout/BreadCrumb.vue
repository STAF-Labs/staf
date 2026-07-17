<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink, useRoute, useRouter, type RouteLocationRaw, type RouteRecordNameGeneric } from 'vue-router'

type Crumb = {
  label: string
  key: string
  to: RouteLocationRaw | null
}

const route = useRoute()
const router = useRouter()

function routeNameKey(name: RouteRecordNameGeneric): string {
  return String(name)
}

function resolveCrumbLabel(routeName: RouteRecordNameGeneric): string {
  const name = routeNameKey(routeName)

  if (name === route.name && typeof route.meta.breadcrumbLabel === 'string') {
    return route.meta.breadcrumbLabel
  }

  const record = router.getRoutes().find((routeRecord) => routeRecord.name === routeName)
  const breadcrumbLabel = record?.meta.breadcrumb?.label

  if (typeof breadcrumbLabel === 'string') {
    return breadcrumbLabel
  }

  if (record?.name === route.name && typeof route.meta.title === 'string') {
    return route.meta.title
  }

  return typeof record?.meta.title === 'string' ? record.meta.title : 'Страница'
}

function findRouteChain(routeName: RouteRecordNameGeneric): RouteRecordNameGeneric[] {
  const routeMap = new Map(
    router
      .getRoutes()
      .filter((routeRecord) => routeRecord.name)
      .map((routeRecord) => [routeNameKey(routeRecord.name), routeRecord]),
  )
  const chain: RouteRecordNameGeneric[] = []
  const visited = new Set<string>()
  let currentName: RouteRecordNameGeneric | undefined = routeName

  while (currentName && !visited.has(routeNameKey(currentName))) {
    visited.add(routeNameKey(currentName))
    chain.unshift(currentName)

    const record = routeMap.get(routeNameKey(currentName))
    const parentName = record?.meta.breadcrumb?.parentName

    currentName = parentName
  }

  return chain
}

const crumbs = computed<Crumb[]>(() => {
  if (!route.name) {
    return []
  }

  return findRouteChain(route.name).map((routeName, index, chain) => {
    const isCurrent = index === chain.length - 1

    return {
      label: resolveCrumbLabel(routeName),
      key: `${routeNameKey(routeName)}:${index}`,
      to: isCurrent ? null : { name: routeName },
    }
  })
})
</script>

<template>
  <nav class="breadcrumb" aria-label="Навигационная цепочка">
    <ol class="breadcrumb__list">
      <li v-for="(crumb, index) in crumbs" :key="crumb.key" class="breadcrumb__item">
        <RouterLink v-if="crumb.to" class="breadcrumb__link" :to="crumb.to">
          {{ crumb.label }}
        </RouterLink>

        <span v-else class="breadcrumb__current" aria-current="page">
          {{ crumb.label }}
        </span>

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

.breadcrumb__current {
  color: var(--color-text-muted);
  font-weight: 600;
}

.breadcrumb__separator {
  color: var(--color-text-soft);
}
</style>
