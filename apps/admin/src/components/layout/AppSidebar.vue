<script setup lang="ts">
import { ChevronDown, PanelLeftClose, PanelLeftOpen } from '@lucide/vue'
import { computed, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import ThemeToggler from '@/components/ui/ThemeToggler.vue'
import { sidebarItems, type SidebarGroupItem, type SidebarItem } from '@/shared/nav/sidebar'
import logoUrl from '@staf/assets/images/logo.svg'

const props = withDefaults(defineProps<{
  collapsed: boolean
  items?: SidebarItem[]
  navLabel?: string
  openedGroupsStorageKey?: string
}>(), {
  items: () => sidebarItems,
  navLabel: 'Основная навигация',
  openedGroupsStorageKey: 'app-sidebar.opened-groups',
})

const emit = defineEmits<{
  toggle: []
}>()

const route = useRoute()

const openedGroups = ref<Record<string, boolean>>(
  JSON.parse(localStorage.getItem(props.openedGroupsStorageKey) ?? '{}'),
)

const activeRouteName = computed(() => String(route.name ?? ''))

function isGroupOpen(key: string): boolean {
  return openedGroups.value[key] ?? true
}

function toggleGroup(key: string): void {
  openedGroups.value = {
    ...openedGroups.value,
    [key]: !isGroupOpen(key),
  }
}

function isGroupActive(group: SidebarGroupItem): boolean {
  return group.children.some((child) => child.routeName === activeRouteName.value)
}

watch(
  openedGroups,
  (value) => {
    localStorage.setItem(props.openedGroupsStorageKey, JSON.stringify(value))
  },
  { deep: true },
)
</script>

<template>
  <aside class="app-sidebar" :class="{ 'is-collapsed': props.collapsed }">
    <div class="app-sidebar__logo" aria-label="Место для логотипа">
      <img :src="logoUrl" alt="STAF gaming" class="app-sidebar__logo-image">
      <ThemeToggler class="app-sidebar__theme-toggle" />
    </div>

    <nav class="app-sidebar__nav" :aria-label="props.navLabel">
      <template v-for="item in props.items" :key="item.type === 'group' ? item.key : item.routeName">
        <RouterLink
          v-if="item.type === 'link'"
          class="app-sidebar__link"
          :to="{ name: item.routeName }"
        >
          <component :is="item.icon" class="app-sidebar__link-icon" :size="20" :stroke-width="1.9" />
          <span class="app-sidebar__link-label">{{ item.label }}</span>
        </RouterLink>

        <div
          v-else
          class="app-sidebar__group"
          :class="{
            'is-open': isGroupOpen(item.key),
            'is-active': isGroupActive(item),
          }"
        >
          <button
            type="button"
            class="app-sidebar__link app-sidebar__group-trigger"
            :aria-expanded="isGroupOpen(item.key)"
            @click="toggleGroup(item.key)"
          >
            <component :is="item.icon" class="app-sidebar__link-icon" :size="20" :stroke-width="1.9" />
            <span class="app-sidebar__link-label">{{ item.label }}</span>
            <ChevronDown class="app-sidebar__group-chevron" :size="16" :stroke-width="2" />
          </button>

          <div v-if="isGroupOpen(item.key)" class="app-sidebar__subnav">
            <RouterLink
              v-for="child in item.children"
              :key="child.routeName"
              class="app-sidebar__sublink"
              :to="{ name: child.routeName }"
            >
              <component :is="child.icon" class="app-sidebar__sublink-icon" :size="16" :stroke-width="1.9" />
              <span class="app-sidebar__sublink-label">{{ child.label }}</span>
            </RouterLink>
          </div>
        </div>
      </template>
    </nav>

    <div class="app-sidebar__footer">
      <button
        type="button"
        class="app-sidebar__collapse"
        :aria-label="props.collapsed ? 'Раскрыть меню' : 'Свернуть меню'"
        @click="emit('toggle')"
      >
        <PanelLeftOpen v-if="props.collapsed" :size="20" :stroke-width="1.9" aria-hidden="true" />
        <PanelLeftClose v-else :size="20" :stroke-width="1.9" aria-hidden="true" />
      </button>
    </div>
  </aside>
</template>

<style scoped>
.app-sidebar {
  position: sticky;
  top: 0;
  display: flex;
  flex-direction: column;
  width: 248px;
  height: 100vh;
  padding: 10px 14px 18px;
  background: var(--color-surface);
  border-right: 1px solid var(--color-border-soft);
  transition:
    width 220ms ease,
    padding 220ms ease;
}

.app-sidebar.is-collapsed {
  width: 76px;
  padding-inline: 12px;
}

.app-sidebar__logo {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 12px;
  min-height: 74px;
  margin-top: 24px;
  padding: 0 0 14px;
  border-bottom: 1px solid var(--color-border-soft);
  overflow: hidden;
}

.app-sidebar__logo-image {
  width: 65px;
  height: auto;
  object-fit: contain;
  transition:
    width 220ms ease,
    border-radius 220ms ease;
}

.app-sidebar.is-collapsed .app-sidebar__logo {
  align-items: center;
  justify-content: center;
  padding-inline: 0;
}

.app-sidebar.is-collapsed .app-sidebar__logo-image {
  width: 44px;
}

.app-sidebar.is-collapsed .app-sidebar__theme-toggle {
  display: none;
}

.app-sidebar__nav {
  display: grid;
  gap: 6px;
  margin-top: 14px;
}

.app-sidebar__link {
  display: flex;
  align-items: center;
  gap: 12px;
  min-height: 42px;
  padding: 0 10px;
  color: var(--color-text-muted);
  font-size: 14px;
  font-weight: 500;
  border-radius: var(--radius-md);
  overflow: hidden;
  transition:
    color 160ms ease,
    background-color 160ms ease;
}

.app-sidebar__link:hover {
  color: var(--color-text);
  background: var(--color-surface-hover);
}

.app-sidebar__link.router-link-active {
  color: var(--color-primary-text);
  background: var(--color-primary);
}

.app-sidebar__group {
  display: grid;
  gap: 4px;
}

.app-sidebar__group-trigger {
  width: 100%;
  text-align: left;
  background: transparent;
  border: 0;
}

.app-sidebar__group.is-active > .app-sidebar__group-trigger {
  color: var(--color-text);
  background: var(--color-surface-hover);
}

.app-sidebar__group-chevron {
  flex: 0 0 auto;
  margin-left: auto;
  color: var(--color-text-soft);
  transition: transform 160ms ease;
}

.app-sidebar__group.is-open .app-sidebar__group-chevron {
  transform: rotate(180deg);
}

.app-sidebar__subnav {
  display: grid;
  gap: 4px;
  padding-left: 32px;
}

.app-sidebar__sublink {
  display: flex;
  align-items: center;
  gap: 8px;
  min-height: 34px;
  padding: 0 10px;
  color: var(--color-text-muted);
  font-size: 13px;
  font-weight: 500;
  border-radius: var(--radius-sm);
  transition:
    color 160ms ease,
    background-color 160ms ease;
}

.app-sidebar__sublink:hover {
  color: var(--color-text);
  background: var(--color-surface-hover);
}

.app-sidebar__sublink.router-link-active {
  color: var(--color-primary-text);
  background: var(--color-primary);
}

.app-sidebar__sublink-icon {
  flex: 0 0 auto;
}

.app-sidebar__sublink-label {
  white-space: nowrap;
}

.app-sidebar__link-icon {
  flex: 0 0 auto;
}

.app-sidebar__link-label {
  white-space: nowrap;
  transition:
    opacity 160ms ease,
    transform 160ms ease;
}

.app-sidebar.is-collapsed .app-sidebar__link {
  justify-content: center;
  width: 100%;
  padding-inline: 0;
  gap: 0;
}

.app-sidebar.is-collapsed .app-sidebar__group-trigger {
  text-align: center;
}

.app-sidebar.is-collapsed .app-sidebar__group-chevron,
.app-sidebar.is-collapsed .app-sidebar__link-label {
  position: absolute;
  opacity: 0;
  transform: translateX(-8px);
  pointer-events: none;
}

.app-sidebar.is-collapsed .app-sidebar__subnav {
  justify-items: center;
  padding-left: 0;
}

.app-sidebar.is-collapsed .app-sidebar__sublink {
  justify-content: center;
  width: 32px;
  min-height: 32px;
  padding: 0;
  gap: 0;
  border-radius: var(--radius-sm);
}

.app-sidebar.is-collapsed .app-sidebar__sublink-icon {
  width: 15px;
  height: 15px;
}

.app-sidebar.is-collapsed .app-sidebar__sublink-label {
  position: absolute;
  opacity: 0;
  transform: translateX(-8px);
  pointer-events: none;
}

.app-sidebar__footer {
  display: flex;
  justify-content: flex-end;
  margin-top: auto;
}

.app-sidebar.is-collapsed .app-sidebar__footer {
  justify-content: center;
}

.app-sidebar__collapse {
  display: grid;
  place-items: center;
  width: 40px;
  height: 40px;
  color: var(--color-text-muted);
  background: var(--color-bg-muted);
  border: 1px solid var(--color-border-soft);
  border-radius: var(--radius-md);
  transition:
    color 160ms ease,
    background-color 160ms ease,
    transform 180ms ease;
}

.app-sidebar__collapse:hover {
  color: var(--color-text);
  background: var(--color-surface-hover);
}

.app-sidebar__collapse:active {
  transform: scale(0.96);
}
</style>
