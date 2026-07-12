<script setup lang="ts">
import AppShell from '@/components/layout/AppShell.vue'
import { currentUser } from '../../shared/auth/session.ts'
</script>

<template>
  <AppShell>
    <section class="dashboard-panel">
      <p class="dashboard-panel__eyebrow">Главная</p>
      <h2>Добро пожаловать</h2>
      <p v-if="currentUser">{{ currentUser.email }}</p>
    </section>
  </AppShell>
</template>

<style scoped>
.dashboard-panel {
  max-width: 720px;
  padding: 24px;
  background: var(--color-surface);
  border: 1px solid var(--color-border-soft);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
}

.dashboard-panel__eyebrow {
  margin-bottom: 8px;
  color: var(--color-primary);
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.dashboard-panel h2 {
  margin-bottom: 8px;
  font-size: 24px;
}

.dashboard-panel p:last-child {
  margin-bottom: 0;
}
</style>
