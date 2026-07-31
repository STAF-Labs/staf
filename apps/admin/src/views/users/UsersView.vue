<script setup lang="ts">
import { Ban, Columns3, Eye, RotateCcw, Search, SlidersHorizontal, Snowflake, Trash2 } from '@lucide/vue'
import { computed, onMounted, ref, watch } from 'vue'
import DataTable from '@/components/data/DataTable.vue'
import AppShell from '@/components/layout/AppShell.vue'
import BlockModal from '@/components/ui/BlockModal.vue'
import DeleteModal from '@/components/ui/DeleteModal.vue'
import type { DataColumn } from '@/shared/data/table'
import {
  blockUser,
  fetchUsers,
  freezeUser,
  softDeleteUser,
  unblockUser,
  unfreezeUser,
  type UserListItem,
} from '@/shared/users/users'

const search = ref('')
const status = ref('')
const createdFrom = ref('')
const createdTo = ref('')
const isPublic = ref('')
const showOnlineStatus = ref('')
const showLastSeenAt = ref('')
const deleted = ref('without')
const pageSize = ref(20)
const sort = ref<'username_asc' | 'username_desc' | 'created_at'>('username_asc')
const advancedFiltersOpen = ref(false)
const users = ref<UserListItem[]>([])
const total = ref(0)
const filteredTotal = ref(0)
const isLoading = ref(false)
const actionUserId = ref<number | null>(null)
const message = ref('')
const pendingBlockUser = ref<Record<string, unknown> | null>(null)
const pendingFreezeUser = ref<Record<string, unknown> | null>(null)
const pendingDeleteUser = ref<Record<string, unknown> | null>(null)

const columns = ref<DataColumn[]>([
  { key: 'avatar', label: 'Аватар', visible: false },
  { key: 'id', label: 'ID', visible: false },
  { key: 'username', label: 'Логин', visible: true },
  { key: 'email', label: 'Почта', visible: true },
  { key: 'display_name', label: 'Имя профиля', visible: true },
  { key: 'birthday', label: 'Дата рождения', visible: false },
  { key: 'is_public', label: 'Публичный', visible: false },
  { key: 'show_online_status', label: 'Онлайн', visible: false },
  { key: 'show_last_seen_at', label: 'Был онлайн в профиле', visible: false },
  { key: 'status_label', label: 'Статус', visible: true },
  { key: 'last_seen_at', label: 'Был онлайн', visible: false },
  { key: 'created_at', label: 'Создан', visible: true },
])
const statusOptions = [
  { value: '', label: 'Все' },
  { value: 'active', label: 'Активен' },
  { value: 'suspended', label: 'Приостановлен' },
  { value: 'blocked', label: 'Заблокирован' },
]
const booleanOptions = [
  { value: '', label: 'Все' },
  { value: '1', label: 'Да' },
  { value: '0', label: 'Нет' },
]
const deletedOptions = [
  { value: 'all', label: 'Все' },
  { value: 'only', label: 'Удалённые' },
  { value: 'without', label: 'Активные' },
]
const pageSizeOptions = [10, 20, 30, 40, 50]
const sortOptions = [
  { value: 'username_asc', label: 'А-Я' },
  { value: 'username_desc', label: 'Я-А' },
  { value: 'created_at', label: 'Дата создания' },
]

const visibleColumns = computed(() => columns.value.filter((column) => column.visible))
const tableColumns = computed<DataColumn[]>(() => [
  ...visibleColumns.value,
  { key: 'actions', label: '', visible: true },
])
const hasActiveFilters = computed(() => Boolean(
  search.value
  || status.value
  || createdFrom.value
  || createdTo.value
  || isPublic.value
  || showOnlineStatus.value
  || showLastSeenAt.value
  || deleted.value !== 'without',
))
const sortedUsers = computed<UserListItem[]>(() => [...users.value].sort((left, right) => {
  if (sort.value === 'created_at') {
    return timestamp(right.created_at) - timestamp(left.created_at)
  }

  const direction = sort.value === 'username_desc' ? -1 : 1

  return direction * left.username.localeCompare(right.username, 'ru', { sensitivity: 'base' })
}))
const rows = computed<Record<string, unknown>[]>(() => sortedUsers.value.map((user) => ({
  ...user,
  avatar: user.avatar_url,
  birthday: formatDate(user.birthday, false),
  last_seen_at: formatDate(user.last_seen_at),
  created_at: formatDate(user.created_at),
})))
const subtitle = computed(() => {
  if (hasActiveFilters.value && filteredTotal.value !== total.value) {
    return `Всего пользователей: ${total.value}. Найдено: ${filteredTotal.value}.`
  }

  return `Всего пользователей: ${total.value}.`
})

function timestamp(value: string | null): number {
  return value ? new Date(value).getTime() : 0
}

function formatDate(value: string | null, withTime = true): string {
  if (!value) {
    return '—'
  }

  return new Intl.DateTimeFormat('ru-RU', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    ...(withTime ? { hour: '2-digit', minute: '2-digit' } : {}),
  }).format(new Date(value))
}

function avatarLabel(row: Record<string, unknown>): string {
  const displayName = typeof row.display_name === 'string' ? row.display_name : ''
  const username = typeof row.username === 'string' ? row.username : ''
  const label = displayName || username || '?'

  return label.slice(0, 1).toUpperCase()
}

function statusColorClass(row: Record<string, unknown>): string {
  const color = typeof row.status_color === 'string' ? row.status_color : 'gray'

  return `status-badge--${color}`
}

function toggleColumn(key: string): void {
  columns.value = columns.value.map((column) => (
    column.key === key ? { ...column, visible: !column.visible } : column
  ))
}

function resetFilters(): void {
  search.value = ''
  status.value = ''
  createdFrom.value = ''
  createdTo.value = ''
  isPublic.value = ''
  showOnlineStatus.value = ''
  showLastSeenAt.value = ''
  deleted.value = 'without'
}

function toggleAdvancedFilters(): void {
  advancedFiltersOpen.value = !advancedFiltersOpen.value
}

async function loadUsers(): Promise<void> {
  isLoading.value = true
  message.value = ''

  try {
    const response = await fetchUsers({
      search: search.value,
      status: status.value,
      createdFrom: createdFrom.value,
      createdTo: createdTo.value,
      isPublic: isPublic.value,
      showOnlineStatus: showOnlineStatus.value,
      showLastSeenAt: showLastSeenAt.value,
      deleted: deleted.value,
    })

    users.value = response.data
    total.value = response.total
    filteredTotal.value = response.filtered_total
  } catch {
    message.value = 'Не удалось загрузить пользователей.'
  } finally {
    isLoading.value = false
  }
}

async function runUserAction(
  userId: number,
  action: () => Promise<unknown>,
  successMessage: string,
): Promise<void> {
  actionUserId.value = userId
  message.value = ''

  try {
    await action()
    await loadUsers()
    message.value = successMessage
  } catch {
    message.value = 'Не удалось выполнить действие.'
  } finally {
    actionUserId.value = null
  }
}

function block(row: Record<string, unknown>): void {
  pendingBlockUser.value = row
}

function unblock(row: Record<string, unknown>): void {
  const userId = Number(row.id)

  void runUserAction(
    userId,
    () => unblockUser(userId),
    'Пользователь разблокирован.',
  )
}

function freeze(row: Record<string, unknown>): void {
  pendingFreezeUser.value = row
}

function unfreeze(row: Record<string, unknown>): void {
  const userId = Number(row.id)

  void runUserAction(
    userId,
    () => unfreezeUser(userId),
    'Пользователь разморожен.',
  )
}

function softDelete(row: Record<string, unknown>): void {
  pendingDeleteUser.value = row
}

function closeBlockModal(): void {
  if (actionUserId.value !== null) {
    return
  }

  pendingBlockUser.value = null
}

function closeFreezeModal(): void {
  if (actionUserId.value !== null) {
    return
  }

  pendingFreezeUser.value = null
}

function closeDeleteModal(): void {
  if (actionUserId.value !== null) {
    return
  }

  pendingDeleteUser.value = null
}

function deleteUserName(row: Record<string, unknown> | null): string {
  if (!row) {
    return 'пользователь'
  }

  const displayName = typeof row.display_name === 'string' ? row.display_name : ''
  const username = typeof row.username === 'string' ? row.username : ''

  return displayName || username || 'пользователь'
}

const deleteModalDescription = computed(() => (
  `Пользователь ${deleteUserName(pendingDeleteUser.value)} будет удален. Это действие скроет его из рабочего списка.`
))
const blockModalDescription = computed(() => (
  `Пользователь ${deleteUserName(pendingBlockUser.value)} будет заблокирован и потеряет доступ к активным возможностям аккаунта.`
))
const freezeModalDescription = computed(() => (
  `Пользователь ${deleteUserName(pendingFreezeUser.value)} будет заморожен и потеряет доступ к активным возможностям аккаунта.`
))

function confirmBlock(): void {
  if (!pendingBlockUser.value) {
    return
  }

  const row = pendingBlockUser.value
  const userId = Number(row.id)

  void runUserAction(
    userId,
    () => blockUser(userId),
    'Пользователь заблокирован.',
  )

  pendingBlockUser.value = null
}

function confirmFreeze(): void {
  if (!pendingFreezeUser.value) {
    return
  }

  const row = pendingFreezeUser.value
  const userId = Number(row.id)

  void runUserAction(
    userId,
    () => freezeUser(userId),
    'Пользователь заморожен.',
  )

  pendingFreezeUser.value = null
}

function confirmSoftDelete(): void {
  if (!pendingDeleteUser.value) {
    return
  }

  const row = pendingDeleteUser.value
  const userId = Number(row.id)

  void runUserAction(
    userId,
    () => softDeleteUser(userId),
    'Пользователь удален.',
  )

  pendingDeleteUser.value = null
}

watch([search, status, createdFrom, createdTo, isPublic, showOnlineStatus, showLastSeenAt, deleted], () => {
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

      <p v-if="message" class="data-page__message">{{ message }}</p>

      <section class="game-filters" aria-label="Фильтры пользователей">
        <div class="game-filter-top game-filter-top--with-actions">
          <label class="game-filter-search">
            <Search class="game-filter-search__icon" :size="18" :stroke-width="1.9" aria-hidden="true" />
            <input
              v-model="search"
              class="game-filter-search__input"
              type="search"
              placeholder="Поиск"
            >
          </label>

          <button
            class="game-filter-advanced"
            :class="{ 'game-filter-advanced--active': advancedFiltersOpen }"
            type="button"
            title="Расширенные настройки"
            :aria-pressed="advancedFiltersOpen"
            @click="toggleAdvancedFilters"
          >
            <SlidersHorizontal :size="18" :stroke-width="1.9" aria-hidden="true" />
            <span>Расширенные настройки</span>
          </button>

          <details class="data-toolbar__columns game-filter-columns">
            <summary class="game-filter-advanced">
              <Columns3 :size="18" :stroke-width="1.9" aria-hidden="true" />
              <span>Колонки</span>
            </summary>

            <div class="data-toolbar__columns-menu">
              <label v-for="column in columns" :key="column.key" class="data-toolbar__column-option">
                <input
                  class="checkbox-control"
                  type="checkbox"
                  :checked="column.visible"
                  @change="toggleColumn(column.key)"
                >
                <span>{{ column.label }}</span>
              </label>
            </div>
          </details>
        </div>

        <div class="game-filter-row game-filter-row--users">
          <label class="game-filter-field">
            <span class="game-filter-field__label">Статус</span>
            <select v-model="status" class="game-filter-field__control">
              <option v-for="option in statusOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </label>

          <label class="game-filter-field">
            <span class="game-filter-field__label">Публичный профиль</span>
            <select v-model="isPublic" class="game-filter-field__control">
              <option v-for="option in booleanOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </label>

          <label class="game-filter-field">
            <span class="game-filter-field__label">Создан</span>
            <span class="game-filter-date-range">
              <input
                v-model="createdFrom"
                class="game-filter-date-range__input"
                type="date"
                :max="createdTo || undefined"
                aria-label="Создан от"
              >
              <span class="game-filter-date-range__separator">-</span>
              <input
                v-model="createdTo"
                class="game-filter-date-range__input"
                type="date"
                :min="createdFrom || undefined"
                aria-label="Создан до"
              >
            </span>
          </label>

          <label class="game-filter-field">
            <span class="game-filter-field__label">Показывать онлайн</span>
            <select v-model="showOnlineStatus" class="game-filter-field__control">
              <option v-for="option in booleanOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </label>

          <label class="game-filter-field">
            <span class="game-filter-field__label">Показывать был онлайн</span>
            <select v-model="showLastSeenAt" class="game-filter-field__control">
              <option v-for="option in booleanOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </label>

          <button
            class="game-filter-reset"
            type="button"
            :disabled="!hasActiveFilters"
            title="Сбросить фильтры"
            @click="resetFilters"
          >
            <RotateCcw :size="18" :stroke-width="1.9" aria-hidden="true" />
            <span>Сбросить</span>
          </button>
        </div>
      </section>

      <div class="game-results-layout" :class="{ 'game-results-layout--with-panel': advancedFiltersOpen }">
        <div class="data-table-panel">
          <DataTable
            :columns="tableColumns"
            :rows="rows"
            :loading="isLoading"
            :page-size="pageSize"
            empty-text="Пользователи не найдены"
          >
          <template #cell-avatar="{ row }">
            <span class="user-avatar" aria-hidden="true">
              <img
                v-if="row.avatar"
                :src="String(row.avatar)"
                alt=""
              >
              <span v-else>{{ avatarLabel(row) }}</span>
            </span>
          </template>

          <template #cell-actions="{ row }">
            <div class="user-actions">
              <RouterLink
                class="data-table__icon-action"
                :to="{ name: 'user.show', params: { id: String(row.id) } }"
                aria-label="Открыть пользователя"
                title="Открыть пользователя"
              >
                <Eye :size="17" :stroke-width="1.9" aria-hidden="true" />
              </RouterLink>

              <button
                v-if="row.status === 'blocked'"
                class="data-table__icon-action"
                type="button"
                :disabled="actionUserId === Number(row.id)"
                aria-label="Разблокировать пользователя"
                title="Разблокировать пользователя"
                @click="unblock(row)"
              >
                <RotateCcw :size="17" :stroke-width="1.9" aria-hidden="true" />
              </button>

              <button
                v-if="row.status !== 'blocked'"
                class="data-table__icon-action"
                type="button"
                :disabled="actionUserId === Number(row.id)"
                aria-label="Заблокировать пользователя"
                title="Заблокировать пользователя"
                @click="block(row)"
              >
                <Ban :size="17" :stroke-width="1.9" aria-hidden="true" />
              </button>

              <button
                v-if="row.status === 'active'"
                class="data-table__icon-action"
                type="button"
                :disabled="actionUserId === Number(row.id)"
                aria-label="Заморозить пользователя"
                title="Заморозить пользователя"
                @click="freeze(row)"
              >
                <Snowflake :size="17" :stroke-width="1.9" aria-hidden="true" />
              </button>

              <button
                v-if="row.status === 'suspended'"
                class="data-table__icon-action"
                type="button"
                :disabled="actionUserId === Number(row.id)"
                aria-label="Разморозить пользователя"
                title="Разморозить пользователя"
                @click="unfreeze(row)"
              >
                <RotateCcw :size="17" :stroke-width="1.9" aria-hidden="true" />
              </button>

              <button
                class="data-table__icon-action data-table__icon-action--danger"
                type="button"
                :disabled="actionUserId === Number(row.id)"
                aria-label="Мягко удалить пользователя"
                title="Мягко удалить пользователя"
                @click="softDelete(row)"
              >
                <Trash2 :size="17" :stroke-width="1.9" aria-hidden="true" />
              </button>
            </div>
          </template>

          <template #cell-status_label="{ row, value }">
            <span class="status-badge" :class="statusColorClass(row)">
              {{ value }}
            </span>
          </template>

          </DataTable>
        </div>

        <aside
          class="game-advanced-panel"
          :class="{ 'game-advanced-panel--open': advancedFiltersOpen }"
          :aria-hidden="!advancedFiltersOpen"
          aria-label="Расширенные настройки"
        >
          <label class="game-filter-field">
            <span class="game-filter-field__label">Сортировка</span>
            <select v-model="sort" class="game-filter-field__control">
              <option v-for="option in sortOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </label>

          <label class="game-filter-field">
            <span class="game-filter-field__label">Вид</span>
            <select v-model="pageSize" class="game-filter-field__control">
              <option v-for="option in pageSizeOptions" :key="option" :value="option">
                {{ option }}
              </option>
            </select>
          </label>

          <label class="game-filter-field">
            <span class="game-filter-field__label">Удаленные</span>
            <select v-model="deleted" class="game-filter-field__control">
              <option v-for="option in deletedOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </label>
        </aside>
      </div>
    </section>

    <BlockModal
      :open="pendingBlockUser !== null"
      title="Заблокировать пользователя?"
      :description="blockModalDescription"
      :loading="actionUserId !== null"
      @cancel="closeBlockModal"
      @confirm="confirmBlock"
    />

    <BlockModal
      :open="pendingFreezeUser !== null"
      title="Заморозить пользователя?"
      :description="freezeModalDescription"
      icon="snowflake"
      confirm-text="Заморозить"
      :loading="actionUserId !== null"
      @cancel="closeFreezeModal"
      @confirm="confirmFreeze"
    />

    <DeleteModal
      :open="pendingDeleteUser !== null"
      title="Удалить пользователя?"
      :description="deleteModalDescription"
      :loading="actionUserId !== null"
      @cancel="closeDeleteModal"
      @confirm="confirmSoftDelete"
    />
  </AppShell>
</template>

<style scoped>
.user-avatar {
  display: grid;
  place-items: center;
  overflow: hidden;

  width: 34px;
  height: 34px;

  color: var(--color-primary-text);
  background: var(--color-primary);
  border-radius: var(--radius-md);

  font-size: 14px;
  font-weight: 800;
}

.user-avatar img {
  width: 100%;
  height: 100%;

  object-fit: cover;
}

.user-actions {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.status-badge {
  display: inline-flex;
  align-items: center;

  min-height: 26px;
  padding: 0 10px;

  border: 1px solid transparent;
  border-radius: 999px;

  font-size: 12px;
  font-weight: 800;
}

.status-badge--success {
  color: var(--color-success);
  background: color-mix(in srgb, var(--color-success) 12%, transparent);
  border-color: color-mix(in srgb, var(--color-success) 28%, transparent);
}

.status-badge--gray {
  color: var(--color-text-muted);
  background: var(--color-bg-muted);
  border-color: var(--color-border-soft);
}

.status-badge--danger {
  color: var(--color-danger);
  background: color-mix(in srgb, var(--color-danger) 10%, transparent);
  border-color: color-mix(in srgb, var(--color-danger) 28%, transparent);
}
</style>
