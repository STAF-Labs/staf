<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import DebugShell from '@/components/debug/DebugShell.vue'
import { debugSidebarItems } from '@/shared/nav/debug-sidebar'

const navigationItems = computed(() =>
  debugSidebarItems.flatMap((item) => (item.type === 'group' ? item.children : [item])),
)
</script>

<template>
  <DebugShell>
    <section class="debug-dashboard-panel">
      <div class="debug-dashboard-panel__header">
        <p class="debug-dashboard-panel__eyebrow">Навигация</p>
        <h2>Разделы debug</h2>
      </div>

      <div class="debug-navigation-list">
        <RouterLink
          v-for="item in navigationItems"
          :key="item.routeName"
          class="debug-navigation-list__item"
          :to="{ name: item.routeName }"
        >
          <span class="debug-navigation-list__icon">
            <component :is="item.icon" :size="20" :stroke-width="1.9" />
          </span>
          <span class="debug-navigation-list__content">
            <span class="debug-navigation-list__label">{{ item.label }}</span>
          </span>
        </RouterLink>
      </div>
    </section>
  </DebugShell>
</template>

<style scoped>
.debug-dashboard-panel {
  max-width: 720px;
  padding: 24px;
  background: var(--color-surface);
  border: 1px solid var(--color-border-soft);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
}

.debug-dashboard-panel--intro {
  padding-bottom: 22px;
}

.debug-dashboard-panel__header {
  margin-bottom: 18px;
}

.debug-dashboard-panel__eyebrow {
  margin-bottom: 8px;
  color: var(--color-primary);
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.debug-dashboard-panel h2 {
  margin-bottom: 0;
  font-size: 24px;
}

.debug-navigation-list {
  display: grid;
  gap: 10px;
}

.debug-navigation-list__item {
  display: grid;
  grid-template-columns: 42px minmax(0, 1fr);
  align-items: center;
  gap: 12px;
  min-height: 58px;
  padding: 8px 12px 8px 8px;
  color: var(--color-text);
  background: var(--color-bg-soft);
  border: 1px solid var(--color-border-soft);
  border-radius: var(--radius-md);
  transition:
    border-color 160ms ease,
    background-color 160ms ease,
    transform 160ms ease;
}

.debug-navigation-list__item:hover {
  color: var(--color-text);
  background: var(--color-surface-hover);
  border-color: var(--color-border);
  transform: translateY(-1px);
}

.debug-navigation-list__icon {
  display: grid;
  place-items: center;
  width: 42px;
  height: 42px;
  color: var(--color-primary);
  background: color-mix(in srgb, var(--color-primary) 12%, transparent);
  border-radius: var(--radius-sm);
}

.debug-navigation-list__content {
  min-width: 0;
}

.debug-navigation-list__label {
  display: block;
  font-size: 15px;
  font-weight: 700;
}
</style>
