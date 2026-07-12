<script setup lang="ts">
import { ChevronDown } from '@lucide/vue'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { currentUser, logout } from '@/shared/auth/session'

const props = defineProps<{
  title?: string
}>()

const route = useRoute()
const router = useRouter()
const isMenuOpen = ref(false)
const isLoggingOut = ref(false)
const menuRef = ref<HTMLElement | null>(null)

const pageTitle = computed(() => {
  if (props.title) {
    return props.title
  }

  return typeof route.meta.title === 'string' ? route.meta.title : 'Страница'
})

const avatarLabel = computed(() => {
  const name = currentUser.value?.username ?? currentUser.value?.email ?? 'U'

  return name.trim().charAt(0).toUpperCase()
})

function toggleMenu(): void {
  isMenuOpen.value = !isMenuOpen.value
}

function closeMenu(): void {
  isMenuOpen.value = false
}

function handlePointerDown(event: PointerEvent): void {
  if (!menuRef.value?.contains(event.target as Node)) {
    closeMenu()
  }
}

function handleKeyDown(event: KeyboardEvent): void {
  if (event.key === 'Escape') {
    closeMenu()
  }
}

async function submitLogout(): Promise<void> {
  isLoggingOut.value = true

  try {
    await logout()
    await router.push({ name: 'login' })
  } finally {
    isLoggingOut.value = false
    closeMenu()
  }
}

onMounted(() => {
  document.addEventListener('pointerdown', handlePointerDown)
  document.addEventListener('keydown', handleKeyDown)
})

onBeforeUnmount(() => {
  document.removeEventListener('pointerdown', handlePointerDown)
  document.removeEventListener('keydown', handleKeyDown)
})
</script>

<template>
  <header class="app-header">
    <span class="app-header__title">{{ pageTitle }}</span>

    <div ref="menuRef" class="app-header__account">
      <button
        type="button"
        class="account-button"
        :aria-expanded="isMenuOpen"
        aria-haspopup="menu"
        @click="toggleMenu"
      >
        <span class="account-button__avatar">{{ avatarLabel }}</span>
        <ChevronDown class="account-button__icon" :size="16" :stroke-width="2" aria-hidden="true" />
      </button>

      <div v-if="isMenuOpen" class="account-menu" role="menu">
        <a class="account-menu__item" href="#" role="menuitem" @click="closeMenu">Профиль</a>

        <div class="account-menu__section">
          <button
            type="button"
            class="account-menu__item account-menu__item--danger"
            role="menuitem"
            :disabled="isLoggingOut"
            @click="submitLogout"
          >
            {{ isLoggingOut ? 'Выходим...' : 'Выйти' }}
          </button>
        </div>
      </div>
    </div>
  </header>
</template>

<style scoped>
.app-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  min-height: 72px;
  padding: 0 28px;
  border-bottom: 1px solid var(--color-border-soft);
  overflow: visible;
}

.app-header__account {
  position: relative;
}

.app-header__title {
  margin: 0;
  font-size: 22px;
}

.account-button {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 4px 8px 4px 4px;
  color: var(--color-text);
  background: var(--color-bg-muted);
  border: 1px solid var(--color-border-soft);
  border-radius: 999px;
}

.account-button:hover {
  background: var(--color-surface-hover);
}

.account-button__avatar {
  display: grid;
  place-items: center;
  width: 34px;
  height: 34px;
  color: var(--color-primary-text);
  font-size: 14px;
  font-weight: 700;
  background: var(--color-primary);
  border-radius: 50%;
}

.account-button__icon {
  color: var(--color-text-muted);
}

.account-menu {
  position: absolute;
  top: calc(100% + 10px);
  right: 0;
  z-index: 20;
  width: max-content;
  min-width: 180px;
  max-width: min(240px, calc(100vw - 32px));
  padding: 6px;
  background: var(--color-surface);
  border: 1px solid var(--color-border-soft);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-md);
}

.account-menu__item {
  display: flex;
  align-items: center;
  width: 100%;
  min-height: 38px;
  padding: 0 10px;
  color: var(--color-text);
  font-size: 14px;
  font-weight: 600;
  text-align: left;
  white-space: nowrap;
  background: transparent;
  border: 0;
  border-radius: var(--radius-sm);
}

.account-menu__item:hover {
  background: var(--color-surface-hover);
}

.account-menu__section {
  margin-top: 6px;
  padding-top: 6px;
  border-top: 1px solid rgb(255 255 255 / 0.22);
}

.account-menu__item--danger {
  color: var(--color-danger);
}

.account-menu__item--danger:disabled {
  color: var(--color-text-disabled);
}
</style>
