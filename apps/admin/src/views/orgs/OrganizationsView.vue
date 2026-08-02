<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { Ban, Columns3, Eye, RotateCcw, SlidersHorizontal, Snowflake, Trash2 } from '@lucide/vue'
import DataTable from '@/components/data/DataTable.vue'
import AppShell from '@/components/layout/AppShell.vue'
import BlockModal from '@/components/ui/BlockModal.vue'
import DeleteModal from '@/components/ui/DeleteModal.vue'
import SearchField from '@/components/ui/SearchField.vue'
import type { DataColumn } from '@/shared/data/table'
import {
  blockOrganization,
  fetchOrganizations,
  freezeOrganization,
  softDeleteOrganization,
  unblockOrganization,
  unfreezeOrganization,
  type OrganizationListItem,
} from '@/shared/orgs/organizations'

const search = ref('')
const status = ref('')
const isVisible = ref('')
const verification = ref('')
const createdFrom = ref('')
const createdTo = ref('')
const deleted = ref('without')
const pageSize = ref(20)
const sort = ref<'name_asc' | 'name_desc' | 'created_at'>('name_asc')
const advancedFiltersOpen = ref(false)
const organizations = ref<OrganizationListItem[]>([])
const total = ref(0)
const filteredTotal = ref(0)
const isLoading = ref(false)
const actionOrganizationId = ref<number | null>(null)
const message = ref('')
const pendingBlockOrganization = ref<Record<string, unknown> | null>(null)
const pendingFreezeOrganization = ref<Record<string, unknown> | null>(null)
const pendingDeleteOrganization = ref<Record<string, unknown> | null>(null)

const columns = ref<DataColumn[]>([
  { key: 'id', label: 'ID', visible: false },
  { key: 'name', label: 'Название', visible: true },
  { key: 'slug', label: 'Slug', visible: true },
  { key: 'summary', label: 'Описание', visible: false },
  { key: 'contact_email', label: 'Почта', visible: false },
  { key: 'status_label', label: 'Статус', visible: true },
  { key: 'is_visible', label: 'Видимость', visible: true },
  { key: 'verified_at', label: 'Верификация', visible: true },
  { key: 'created_at', label: 'Создана', visible: true },
])
const statusOptions = [
  { value: '', label: 'Все' },
  { value: 'active', label: 'Активна' },
  { value: 'suspended', label: 'Приостановлена' },
  { value: 'blocked', label: 'Заблокирована' },
]
const booleanOptions = [
  { value: '', label: 'Все' },
  { value: '1', label: 'Да' },
  { value: '0', label: 'Нет' },
]
const verificationOptions = [
  { value: '', label: 'Все' },
  { value: 'verified', label: 'Есть' },
  { value: 'unverified', label: 'Нет' },
]
const deletedOptions = [
  { value: 'all', label: 'Все' },
  { value: 'only', label: 'Удалённые' },
  { value: 'without', label: 'Активные' },
]
const pageSizeOptions = [10, 20, 30, 40, 50]
const sortOptions = [
  { value: 'name_asc', label: 'А-Я' },
  { value: 'name_desc', label: 'Я-А' },
  { value: 'created_at', label: 'Дата создания' },
]

const visibleColumns = computed(() => columns.value.filter((column) => column.visible))
const tableColumns = computed<DataColumn[]>(() => [
  ...visibleColumns.value,
  { key: 'actions', label: '', visible: true },
])
const hasActiveFilters = computed(() =>
  Boolean(
    search.value ||
    status.value ||
    isVisible.value ||
    verification.value ||
    createdFrom.value ||
    createdTo.value ||
    deleted.value !== 'without',
  ),
)
const sortedOrganizations = computed<OrganizationListItem[]>(() =>
  [...organizations.value].sort((left, right) => {
    if (sort.value === 'created_at') {
      return timestamp(right.created_at) - timestamp(left.created_at)
    }

    const direction = sort.value === 'name_desc' ? -1 : 1

    return direction * left.name.localeCompare(right.name, 'ru', { sensitivity: 'base' })
  }),
)
const rows = computed<Record<string, unknown>[]>(() =>
  sortedOrganizations.value.map((organization) => ({
    ...organization,
    verified_at: formatDate(organization.verified_at),
    created_at: formatDate(organization.created_at),
  })),
)
const subtitle = computed(() => {
  if (hasActiveFilters.value && filteredTotal.value !== total.value) {
    return `Всего организаций: ${total.value}. Найдено: ${filteredTotal.value}.`
  }

  return `Всего организаций: ${total.value}.`
})

function timestamp(value: string | null): number {
  return value ? new Date(value).getTime() : 0
}

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

function statusColorClass(row: Record<string, unknown>): string {
  const color = typeof row.status_color === 'string' ? row.status_color : 'gray'

  return `status-badge--${color}`
}

function toggleColumn(key: string): void {
  columns.value = columns.value.map((column) =>
    column.key === key ? { ...column, visible: !column.visible } : column,
  )
}

function resetFilters(): void {
  search.value = ''
  status.value = ''
  isVisible.value = ''
  verification.value = ''
  createdFrom.value = ''
  createdTo.value = ''
  deleted.value = 'without'
}

function toggleAdvancedFilters(): void {
  advancedFiltersOpen.value = !advancedFiltersOpen.value
}

async function loadOrganizations(): Promise<void> {
  isLoading.value = true
  message.value = ''

  try {
    const response = await fetchOrganizations({
      search: search.value,
      status: status.value,
      isVisible: isVisible.value,
      verification: verification.value,
      createdFrom: createdFrom.value,
      createdTo: createdTo.value,
      deleted: deleted.value,
    })

    organizations.value = response.data
    total.value = response.total
    filteredTotal.value = response.filtered_total
  } catch {
    message.value = 'Не удалось загрузить организации.'
  } finally {
    isLoading.value = false
  }
}

async function runOrganizationAction(
  organizationId: number,
  action: () => Promise<unknown>,
  successMessage: string,
): Promise<void> {
  actionOrganizationId.value = organizationId
  message.value = ''

  try {
    await action()
    await loadOrganizations()
    message.value = successMessage
  } catch {
    message.value = 'Не удалось выполнить действие.'
  } finally {
    actionOrganizationId.value = null
  }
}

function block(row: Record<string, unknown>): void {
  pendingBlockOrganization.value = row
}

function unblock(row: Record<string, unknown>): void {
  const organizationId = Number(row.id)

  void runOrganizationAction(
    organizationId,
    () => unblockOrganization(organizationId),
    'Организация разблокирована.',
  )
}

function freeze(row: Record<string, unknown>): void {
  pendingFreezeOrganization.value = row
}

function unfreeze(row: Record<string, unknown>): void {
  const organizationId = Number(row.id)

  void runOrganizationAction(
    organizationId,
    () => unfreezeOrganization(organizationId),
    'Организация разморожена.',
  )
}

function softDelete(row: Record<string, unknown>): void {
  pendingDeleteOrganization.value = row
}

function closeBlockModal(): void {
  if (actionOrganizationId.value !== null) {
    return
  }

  pendingBlockOrganization.value = null
}

function closeFreezeModal(): void {
  if (actionOrganizationId.value !== null) {
    return
  }

  pendingFreezeOrganization.value = null
}

function closeDeleteModal(): void {
  if (actionOrganizationId.value !== null) {
    return
  }

  pendingDeleteOrganization.value = null
}

function organizationName(row: Record<string, unknown> | null): string {
  if (!row) {
    return 'организация'
  }

  const name = typeof row.name === 'string' ? row.name : ''
  const slug = typeof row.slug === 'string' ? row.slug : ''

  return name || slug || 'организация'
}

const deleteModalDescription = computed(
  () =>
    `Организация ${organizationName(pendingDeleteOrganization.value)} будет удалена. Это действие скроет ее из рабочего списка.`,
)
const blockModalDescription = computed(
  () =>
    `Организация ${organizationName(pendingBlockOrganization.value)} будет заблокирована и потеряет доступ к активным возможностям.`,
)
const freezeModalDescription = computed(
  () =>
    `Организация ${organizationName(pendingFreezeOrganization.value)} будет заморожена и потеряет доступ к активным возможностям.`,
)

function confirmBlock(): void {
  if (!pendingBlockOrganization.value) {
    return
  }

  const row = pendingBlockOrganization.value
  const organizationId = Number(row.id)

  void runOrganizationAction(
    organizationId,
    () => blockOrganization(organizationId),
    'Организация заблокирована.',
  )

  pendingBlockOrganization.value = null
}

function confirmFreeze(): void {
  if (!pendingFreezeOrganization.value) {
    return
  }

  const row = pendingFreezeOrganization.value
  const organizationId = Number(row.id)

  void runOrganizationAction(
    organizationId,
    () => freezeOrganization(organizationId),
    'Организация заморожена.',
  )

  pendingFreezeOrganization.value = null
}

function confirmSoftDelete(): void {
  if (!pendingDeleteOrganization.value) {
    return
  }

  const row = pendingDeleteOrganization.value
  const organizationId = Number(row.id)

  void runOrganizationAction(
    organizationId,
    () => softDeleteOrganization(organizationId),
    'Организация удалена.',
  )

  pendingDeleteOrganization.value = null
}

watch([search, status, isVisible, verification, createdFrom, createdTo, deleted], () => {
  void loadOrganizations()
})

onMounted(() => {
  void loadOrganizations()
})
</script>

<template>
  <AppShell>
    <section class="data-page">
      <header class="data-page__header">
        <h2 class="data-page__title">Организации</h2>
        <p class="data-page__subtitle">{{ subtitle }}</p>
      </header>

      <p v-if="message" class="data-page__message">{{ message }}</p>

      <section class="game-filters" aria-label="Фильтры организаций">
        <div class="game-filter-top game-filter-top--with-actions">
          <SearchField v-model="search" />

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
              <label
                v-for="column in columns"
                :key="column.key"
                class="data-toolbar__column-option"
              >
                <input
                  class="checkbox-control"
                  type="checkbox"
                  :checked="column.visible"
                  @change="toggleColumn(column.key)"
                />
                <span>{{ column.label }}</span>
              </label>
            </div>
          </details>
        </div>

        <div class="game-filter-row game-filter-row--orgs">
          <label class="game-filter-field">
            <span class="game-filter-field__label">Статус</span>
            <select v-model="status" class="game-filter-field__control">
              <option v-for="option in statusOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </label>

          <label class="game-filter-field">
            <span class="game-filter-field__label">Видимость</span>
            <select v-model="isVisible" class="game-filter-field__control">
              <option v-for="option in booleanOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </label>

          <label class="game-filter-field">
            <span class="game-filter-field__label">Верификация</span>
            <select v-model="verification" class="game-filter-field__control">
              <option
                v-for="option in verificationOptions"
                :key="option.value"
                :value="option.value"
              >
                {{ option.label }}
              </option>
            </select>
          </label>

          <label class="game-filter-field">
            <span class="game-filter-field__label">Создана</span>
            <span class="game-filter-date-range">
              <input
                v-model="createdFrom"
                class="game-filter-date-range__input"
                type="date"
                :max="createdTo || undefined"
                aria-label="Создана от"
              />
              <span class="game-filter-date-range__separator">-</span>
              <input
                v-model="createdTo"
                class="game-filter-date-range__input"
                type="date"
                :min="createdFrom || undefined"
                aria-label="Создана до"
              />
            </span>
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

      <div
        class="game-results-layout"
        :class="{ 'game-results-layout--with-panel': advancedFiltersOpen }"
      >
        <div class="data-table-panel">
          <DataTable
            :columns="tableColumns"
            :rows="rows"
            :loading="isLoading"
            :page-size="pageSize"
            empty-text="Организации не найдены"
          >
            <template #cell-status_label="{ row, value }">
              <span class="status-badge" :class="statusColorClass(row)">
                {{ value }}
              </span>
            </template>

            <template #cell-actions="{ row }">
              <div class="org-actions">
                <RouterLink
                  class="data-table__icon-action"
                  :to="{ name: 'org.show', params: { id: String(row.id) } }"
                  aria-label="Открыть организацию"
                  title="Открыть организацию"
                >
                  <Eye :size="17" :stroke-width="1.9" aria-hidden="true" />
                </RouterLink>

                <button
                  v-if="row.status === 'blocked'"
                  class="data-table__icon-action"
                  type="button"
                  :disabled="actionOrganizationId === Number(row.id)"
                  aria-label="Разблокировать организацию"
                  title="Разблокировать организацию"
                  @click="unblock(row)"
                >
                  <RotateCcw :size="17" :stroke-width="1.9" aria-hidden="true" />
                </button>

                <button
                  v-if="row.status !== 'blocked'"
                  class="data-table__icon-action"
                  type="button"
                  :disabled="actionOrganizationId === Number(row.id)"
                  aria-label="Заблокировать организацию"
                  title="Заблокировать организацию"
                  @click="block(row)"
                >
                  <Ban :size="17" :stroke-width="1.9" aria-hidden="true" />
                </button>

                <button
                  v-if="row.status === 'active'"
                  class="data-table__icon-action"
                  type="button"
                  :disabled="actionOrganizationId === Number(row.id)"
                  aria-label="Заморозить организацию"
                  title="Заморозить организацию"
                  @click="freeze(row)"
                >
                  <Snowflake :size="17" :stroke-width="1.9" aria-hidden="true" />
                </button>

                <button
                  v-if="row.status === 'suspended'"
                  class="data-table__icon-action"
                  type="button"
                  :disabled="actionOrganizationId === Number(row.id)"
                  aria-label="Разморозить организацию"
                  title="Разморозить организацию"
                  @click="unfreeze(row)"
                >
                  <RotateCcw :size="17" :stroke-width="1.9" aria-hidden="true" />
                </button>

                <button
                  class="data-table__icon-action data-table__icon-action--danger"
                  type="button"
                  :disabled="actionOrganizationId === Number(row.id)"
                  aria-label="Мягко удалить организацию"
                  title="Мягко удалить организацию"
                  @click="softDelete(row)"
                >
                  <Trash2 :size="17" :stroke-width="1.9" aria-hidden="true" />
                </button>
              </div>
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
      :open="pendingBlockOrganization !== null"
      title="Заблокировать организацию?"
      :description="blockModalDescription"
      :loading="actionOrganizationId !== null"
      @cancel="closeBlockModal"
      @confirm="confirmBlock"
    />

    <BlockModal
      :open="pendingFreezeOrganization !== null"
      title="Заморозить организацию?"
      :description="freezeModalDescription"
      icon="snowflake"
      confirm-text="Заморозить"
      :loading="actionOrganizationId !== null"
      @cancel="closeFreezeModal"
      @confirm="confirmFreeze"
    />

    <DeleteModal
      :open="pendingDeleteOrganization !== null"
      title="Удалить организацию?"
      :description="deleteModalDescription"
      :loading="actionOrganizationId !== null"
      @cancel="closeDeleteModal"
      @confirm="confirmSoftDelete"
    />
  </AppShell>
</template>

<style scoped>
.org-actions {
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
