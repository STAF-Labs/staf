<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { currentUser, logout } from '../lib/auth'

const router = useRouter()
const isLoggingOut = ref(false)

const displayName = computed(() => currentUser.value?.username ?? currentUser.value?.email ?? '')

async function submitLogout(): Promise<void> {
  isLoggingOut.value = true

  try {
    await logout()
    await router.push({ name: 'login' })
  } finally {
    isLoggingOut.value = false
  }
}
</script>

<template>
  <main class="dashboard-shell">
    <section class="dashboard-header" aria-labelledby="dashboard-title">
      <div>
        <p class="eyebrow">Аккаунт</p>
        <h1 id="dashboard-title">{{ displayName }}</h1>
        <p v-if="currentUser" class="muted">{{ currentUser.email }}</p>
      </div>

      <button type="button" :disabled="isLoggingOut" @click="submitLogout">
        {{ isLoggingOut ? 'Выходим...' : 'Выйти' }}
      </button>
    </section>
  </main>
</template>
