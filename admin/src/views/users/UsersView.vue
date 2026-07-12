<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import DataTable from '@/components/data/DataTable.vue'
import DataToolbar from '@/components/data/DataToolbar.vue'
import AppShell from '@/components/layout/AppShell.vue'
import type { DataColumn } from '@/shared/data/table'
import { fetchUsers, type UserListItem } from '@/shared/users/users'

const search = ref('')
const users = ref<UserListItem[]>([])
const total = ref(0)
const filteredTotal = ref(0)
const isLoading = ref(false)
const message = ref('')

const columns = ref<DataColumn[]>([
  { key: 'id', label: 'ID', visible: true },
  { key: 'username', label: 'Логин', visible: true },
  { key: 'email', label: 'Почта', visible: true },
  { key: 'display_name', label: 'Имя профиля', visible: true },
  { key: 'status_label', label: 'Статус', visible: true },
  { key: 'last_seen_at', label: 'Был онлайн', visible: true },
  { key: 'created_at', label: 'Создан', visible: true },
])

const visibleColumns = computed(() => columns.value.filter((column) => column.visible))
const rows = computed<Record<string, unknown>[]>(() => users.value.map((user) => ({
  ...user,
  last_seen_at: formatDate(user.last_seen_at),
  created_at: formatDate(user.created_at),
})))
const subtitle = computed(() => {
  if (search.value && filteredTotal.value !== total.value) {
    return `Всего пользователей: ${total.value}. Найдено: ${filteredTotal.value}.`
  }

  return `Всего пользователей: ${total.value}.`
})

function formatDate(value: string | null): string {
  if (!value) {
    return '—'
  }

  return new Intl.DateTimeFormat('ru-RU', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(value))
}

function toggleColumn(key: string): void {
  columns.value = columns.value.map((column) => (
    column.key === key ? { ...column, visible: !column.visible } : column
  ))
}

async function loadUsers(): Promise<void> {
  isLoading.value = true
  message.value = ''

  try {
    const response = await fetchUsers(search.value)

    users.value = response.data
    total.value = response.total
    filteredTotal.value = response.filtered_total
  } catch {
    message.value = 'Не удалось загрузить пользователей.'
  } finally {
    isLoading.value = false
  }
}

watch(search, () => {
  void loadUsers()
})

onMounted(() => {
  void loadUsers()
})
</script>

<template>
  <AppShell>
    <section class="data-page">
      <header class="data-page__header">
        <h2 class="data-page__title">Пользователи</h2>
        <p class="data-page__subtitle">{{ subtitle }}</p>
      </header>

      <div class="data-toolbar-panel">
        <DataToolbar
          v-model:search="search"
          :columns="columns"
          @toggle-column="toggleColumn"
        />
      </div>

      <div class="data-table-panel">
        <p v-if="message" class="data-page__message">{{ message }}</p>

        <DataTable
          :columns="visibleColumns"
          :rows="rows"
          :loading="isLoading"
          empty-text="Пользователи не найдены"
        />
      </div>
    </section>
  </AppShell>
</template>
