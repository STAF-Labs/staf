<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { Ban, Eye, RotateCcw, Snowflake, Trash2 } from '@lucide/vue'
import DataTable from '@/components/data/DataTable.vue'
import DataToolbar from '@/components/data/DataToolbar.vue'
import AppShell from '@/components/layout/AppShell.vue'
import BlockModal from '@/components/ui/BlockModal.vue'
import DeleteModal from '@/components/ui/DeleteModal.vue'
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

const visibleColumns = computed(() => columns.value.filter((column) => column.visible))
const tableColumns = computed<DataColumn[]>(() => [
  ...visibleColumns.value,
  { key: 'actions', label: '', visible: true },
])
const hasActiveFilters = computed(() => Boolean(
  search.value
  || status.value
  || isVisible.value
  || verification.value
  || createdFrom.value
  || createdTo.value
  || deleted.value !== 'without',
))
const rows = computed<Record<string, unknown>[]>(() => organizations.value.map((organization) => ({
  ...organization,
  verified_at: formatDate(organization.verified_at),
  created_at: formatDate(organization.created_at),
})))
const subtitle = computed(() => {
  if (hasActiveFilters.value && filteredTotal.value !== total.value) {
    return `Всего организаций: ${total.value}. Найдено: ${filteredTotal.value}.`
  }

  return `Всего организаций: ${total.value}.`
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
  isVisible.value = ''
  verification.value = ''
  createdFrom.value = ''
  createdTo.value = ''
  deleted.value = 'without'
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

const deleteModalDescription = computed(() => (
  `Организация ${organizationName(pendingDeleteOrganization.value)} будет удалена. Это действие скроет ее из рабочего списка.`
))
const blockModalDescription = computed(() => (
  `Организация ${organizationName(pendingBlockOrganization.value)} будет заблокирована и потеряет доступ к активным возможностям.`
))
const freezeModalDescription = computed(() => (
  `Организация ${organizationName(pendingFreezeOrganization.value)} будет заморожена и потеряет доступ к активным возможностям.`
))

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

      <div class="data-toolbar-panel">
        <DataToolbar
          v-model:search="search"
          :columns="columns"
          @toggle-column="toggleColumn"
        >
          <template #filters>
            <div class="org-filters">
              <div class="org-filters__choice-row">
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
                  <span class="filter-choice-group__label">Видимость</span>
                  <div class="filter-choice-group__options">
                    <button
                      v-for="option in booleanOptions"
                      :key="option.value"
                      type="button"
                      :class="filterChoiceClass(isVisible, option.value)"
                      @click="isVisible = option.value"
                    >
                      {{ option.label }}
                    </button>
                  </div>
                </div>

                <div class="filter-choice-group">
                  <span class="filter-choice-group__label">Верификация</span>
                  <div class="filter-choice-group__options">
                    <button
                      v-for="option in verificationOptions"
                      :key="option.value"
                      type="button"
                      :class="filterChoiceClass(verification, option.value)"
                      @click="verification = option.value"
                    >
                      {{ option.label }}
                    </button>
                  </div>
                </div>
              </div>

              <div class="org-filters__date-row">
                <label class="filter-field">
                  <span>Создана с</span>
                  <input
                    v-model="createdFrom"
                    class="filter-field__control"
                    type="date"
                    :max="createdTo || undefined"
                  >
                </label>

                <label class="filter-field">
                  <span>Создана по</span>
                  <input
                    v-model="createdTo"
                    class="filter-field__control"
                    type="date"
                    :min="createdFrom || undefined"
                  >
                </label>
              </div>

              <div class="org-filters__deleted-row">
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

              <div class="org-filters__footer">
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
.org-filters {
  display: grid;
  gap: 14px;
  width: 100%;
}

.org-filters__choice-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(230px, 320px));
  gap: 10px;
}

.org-filters__date-row {
  display: flex;
  align-items: end;
  flex-wrap: wrap;
  gap: 10px;
}

.org-filters__deleted-row {
  display: flex;
}

.org-filters__footer {
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

@media (max-width: 680px) {
  .org-filters__choice-row {
    grid-template-columns: 1fr;
  }

  .org-filters__date-row {
    display: grid;
    grid-template-columns: 1fr;
  }

  .org-filters__deleted-row,
  .filter-choice-group--compact,
  .org-filters__footer,
  .filter-field,
  .filter-field__button {
    width: 100%;
  }
}
</style>
