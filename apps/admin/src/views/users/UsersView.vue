<script setup lang="ts">
import { Ban, Eye, RotateCcw, Snowflake, Trash2 } from '@lucide/vue'
import { computed, onMounted, ref, watch } from 'vue'
import DataTable from '@/components/data/DataTable.vue'
import DataToolbar from '@/components/data/DataToolbar.vue'
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
const rows = computed<Record<string, unknown>[]>(() => users.value.map((user) => ({
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

function filterChoiceClass(currentValue: string, optionValue: string): Record<string, boolean> {
  return {
    'filter-choice': true,
    'filter-choice--active': currentValue === optionValue,
  }
}

function toggleColumn(key: string): void {
  columns.value = columns.value.map((column) => (
    column.key === key ? { ...column, visible: !column.visible } : column
  ))
}

function resetFilters(): void {
  status.value = ''
  createdFrom.value = ''
  createdTo.value = ''
  isPublic.value = ''
  showOnlineStatus.value = ''
  showLastSeenAt.value = ''
  deleted.value = 'without'
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

      <div class="data-toolbar-panel">
        <DataToolbar
          v-model:search="search"
          :columns="columns"
          @toggle-column="toggleColumn"
        >
          <template #filters>
            <div class="user-filters">
              <div class="user-filters__choice-row">
                <div class="filter-choice-group">
                  <span class="filter-choice-group__label">Статус</span>
                  <div class="filter-choice-group__options">
                    <button
                      v-for="option in statusOptions"
                      :key="option.value"
                      type="button"
                      :class="filterChoiceClass(status, option.value)"
                      @click="status = option.value"
                    >
                      {{ option.label }}
                    </button>
                  </div>
                </div>

                <div class="filter-choice-group">
                  <span class="filter-choice-group__label">Публичный профиль</span>
                  <div class="filter-choice-group__options">
                    <button
                      v-for="option in booleanOptions"
                      :key="option.value"
                      type="button"
                      :class="filterChoiceClass(isPublic, option.value)"
                      @click="isPublic = option.value"
                    >
                      {{ option.label }}
                    </button>
                  </div>
                </div>

                <div class="filter-choice-group">
                  <span class="filter-choice-group__label">Показывать онлайн</span>
                  <div class="filter-choice-group__options">
                    <button
                      v-for="option in booleanOptions"
                      :key="option.value"
                      type="button"
                      :class="filterChoiceClass(showOnlineStatus, option.value)"
                      @click="showOnlineStatus = option.value"
                    >
                      {{ option.label }}
                    </button>
                  </div>
                </div>

                <div class="filter-choice-group">
                  <span class="filter-choice-group__label">Показывать был онлайн</span>
                  <div class="filter-choice-group__options">
                    <button
                      v-for="option in booleanOptions"
                      :key="option.value"
                      type="button"
                      :class="filterChoiceClass(showLastSeenAt, option.value)"
                      @click="showLastSeenAt = option.value"
                    >
                      {{ option.label }}
                    </button>
                  </div>
                </div>
              </div>

              <div class="user-filters__date-row">
                <label class="filter-field">
                  <span>Создан с</span>
                  <input
                    v-model="createdFrom"
                    class="filter-field__control"
                    type="date"
                    :max="createdTo || undefined"
                  >
                </label>

                <label class="filter-field">
                  <span>Создан по</span>
                  <input
                    v-model="createdTo"
                    class="filter-field__control"
                    type="date"
                    :min="createdFrom || undefined"
                  >
                </label>

              </div>

              <div class="user-filters__deleted-row">
                <div class="filter-choice-group filter-choice-group--compact">
                  <span class="filter-choice-group__label">Удаление</span>
                  <div class="filter-choice-group__options">
                    <button
                      v-for="option in deletedOptions"
                      :key="option.value"
                      type="button"
                      :class="filterChoiceClass(deleted, option.value)"
                      @click="deleted = option.value"
                    >
                      {{ option.label }}
                    </button>
                  </div>
                </div>
              </div>

              <div class="user-filters__footer">
                <button
                  class="data-toolbar__button filter-field__button"
                  type="button"
                  title="Сбросить фильтр"
                  :disabled="!hasActiveFilters"
                  @click="resetFilters"
                >
                  <Trash2 aria-hidden="true" style="color: var(--color-danger)" />
                  <span>Сбросить фильтр</span>
                </button>
              </div>
            </div>
          </template>
        </DataToolbar>
      </div>

      <div class="data-table-panel">
        <p v-if="message" class="data-page__message">{{ message }}</p>

        <DataTable
          :columns="tableColumns"
          :rows="rows"
          :loading="isLoading"
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

.user-filters {
  display: grid;
  gap: 14px;
  width: 100%;
}

.user-filters__choice-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(230px, 320px));
  gap: 10px;
}

.user-filters__date-row {
  display: flex;
  align-items: end;
  flex-wrap: wrap;
  gap: 10px;
}

.user-filters__deleted-row {
  display: flex;
}

.user-filters__footer {
  display: flex;
  justify-content: flex-start;

  padding-top: 12px;
  border-top: 1px solid var(--color-border-soft);
}

.filter-choice-group {
  display: grid;
  gap: 8px;
  min-width: 0;
  padding: 10px;

  background: var(--color-bg-soft);
  border: 1px solid var(--color-border-soft);
  border-radius: var(--radius-md);
}

.filter-choice-group__label {
  color: var(--color-text-muted);
  font-size: 12px;
  font-weight: 800;
}

.filter-choice-group__options {
  display: flex;
  flex-wrap: nowrap;
  gap: 6px;
  max-width: 100%;
  overflow-x: auto;
  overflow-y: hidden;
  padding-bottom: 2px;
  scrollbar-width: thin;
}

.filter-choice-group--compact {
  width: fit-content;
  min-width: min(100%, 260px);
}

.filter-choice {
  min-height: 28px;
  padding: 0 10px;

  color: var(--color-text-muted);
  background: var(--color-surface);
  border: 1px solid var(--color-border-soft);
  border-radius: 999px;
  cursor: pointer;
  white-space: nowrap;

  font-size: 12px;
  font-weight: 800;
}

.filter-choice:hover {
  color: var(--color-text);
  background: var(--color-surface-hover);
}

.filter-choice--active {
  color: var(--color-primary);
  background: color-mix(in srgb, var(--color-primary) 12%, transparent);
  border-color: color-mix(in srgb, var(--color-primary) 32%, transparent);
}

@media (max-width: 680px) {
  .user-filters__choice-row {
    grid-template-columns: 1fr;
  }

  .user-filters__date-row {
    display: grid;
    grid-template-columns: 1fr;
  }

  .user-filters__deleted-row,
  .filter-choice-group--compact,
  .user-filters__footer,
  .filter-field,
  .filter-field__button {
    width: 100%;
  }

  .user-filters__footer {
    justify-content: flex-start;
  }
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
