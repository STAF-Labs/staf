<script setup lang="ts">
import { ref, watch } from 'vue'
import AppHeader from '@/components/layout/AppHeader.vue'
import AppSidebar from '@/components/layout/AppSidebar.vue'
import BreadCrumb from '@/components/layout/BreadCrumb.vue'
import { debugSidebarItems } from '@/shared/nav/debug-sidebar'

defineProps<{
  title?: string
}>()

const sidebarCollapsedStorageKey = 'debug-shell.sidebar-collapsed'
const isSidebarCollapsed = ref(localStorage.getItem(sidebarCollapsedStorageKey) === 'true')

function toggleSidebar(): void {
  isSidebarCollapsed.value = !isSidebarCollapsed.value
}

watch(isSidebarCollapsed, (isCollapsed) => {
  localStorage.setItem(sidebarCollapsedStorageKey, String(isCollapsed))
})
</script>

<template>
  <div class="debug-shell" :class="{ 'is-sidebar-collapsed': isSidebarCollapsed }">
    <AppSidebar
      :collapsed="isSidebarCollapsed"
      :items="debugSidebarItems"
      nav-label="Debug navigation"
      opened-groups-storage-key="debug-sidebar.opened-groups"
      @toggle="toggleSidebar"
    />

    <div class="debug-shell__content">
      <AppHeader :title="title" />

      <main class="debug-shell__main">
        <BreadCrumb />
        <slot />
      </main>
    </div>
  </div>
</template>

<style scoped>
.debug-shell {
  --sidebar-width: 248px;

  min-height: 100vh;
  display: grid;
  grid-template-columns: var(--sidebar-width) minmax(0, 1fr);
  background: var(--color-bg);
  transition: grid-template-columns 220ms ease;
}

.debug-shell.is-sidebar-collapsed {
  --sidebar-width: 76px;
}

.debug-shell__content {
  min-width: 0;
}

.debug-shell__main {
  display: grid;
  gap: 18px;
  padding: 28px;
}
</style>
