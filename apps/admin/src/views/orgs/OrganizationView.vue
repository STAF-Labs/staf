<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import {
  ArrowLeft,
  Ban,
  Building2,
  Calendar,
  Check,
  ChevronDown,
  ExternalLink,
  Globe2,
  Link2,
  Mail,
  MoreHorizontal,
  RotateCcw,
  ShieldCheck,
  Snowflake,
  Trash2,
} from '@lucide/vue'
import DataTable from '@/components/data/DataTable.vue'
import AppShell from '@/components/layout/AppShell.vue'
import BlockModal from '@/components/ui/BlockModal.vue'
import DeleteModal from '@/components/ui/DeleteModal.vue'
import RichTextRenderer from '@/components/ui/RichTextRenderer.vue'
import ProjectIndexPanel from '@/views/projects/components/ProjectIndexPanel.vue'
import { fetchGameDimensions, type GameDimension } from '@/shared/games/games'
import {
  blockOrganization,
  fetchOrganization,
  freezeOrganization,
  softDeleteOrganization,
  unblockOrganization,
  unfreezeOrganization,
  type OrganizationDetail,
} from '@/shared/orgs/organizations'
import {
  fetchProjectContentTypes,
  fetchProjectReleases,
  type ProjectContentTypeOption,
  type ProjectListItem,
} from '@/shared/projects/projects'
import type { DataColumn } from '@/shared/data/table'

const route = useRoute()
const router = useRouter()
const organization = ref<OrganizationDetail | null>(null)
const isLoading = ref(false)
const isActionLoading = ref(false)
const isBlockModalOpen = ref(false)
const isFreezeModalOpen = ref(false)
const isDeleteModalOpen = ref(false)
const isMoreOpen = ref(false)
const activeTab = ref<'overview' | 'members' | 'activity'>('overview')
const projectContentTypeOptions = ref<ProjectContentTypeOption[]>([])
const projectDimensions = ref<GameDimension[]>([])
const projectReleaseDimensionValueIds = ref<Record<number, number[]>>({})
const message = ref('')

const tabs = [
  { value: 'overview', label: 'Обзор' },
  { value: 'members', label: 'Участники' },
  { value: 'activity', label: 'Активность' },
] as const
const memberColumns: DataColumn[] = [
  { key: 'name', label: 'Название', visible: true },
  { key: 'status_label', label: 'Статус', visible: true },
  { key: 'email_verified_at', label: 'Верификация', visible: true },
  { key: 'actions', label: '', visible: true },
]

const title = computed(() => organization.value?.name ?? 'Организация')
const subtitle = computed(() => {
  if (!organization.value) {
    return 'Просмотр организации.'
  }

  return `ID ${organization.value.id} · ${organization.value.slug}`
})
const bannerUrl = computed(() => organization.value?.banner_url ?? null)
const heroClasses = computed(() => ({
  'profile-hero': true,
  'profile-hero--with-banner': bannerUrl.value !== null,
}))
const heroStyle = computed(() =>
  bannerUrl.value === null ? undefined : { backgroundImage: `url("${bannerUrl.value}")` },
)
const organizationStatusTitle = computed(() => {
  if (organization.value?.status === 'blocked') {
    return 'Организация заблокирована'
  }

  if (organization.value?.status === 'suspended') {
    return 'Организация заморожена'
  }

  return 'Организация активна'
})
const organizationStatusText = computed(() => {
  if (organization.value?.status === 'blocked') {
    return 'Доступ организации ограничен.'
  }

  if (organization.value?.status === 'suspended') {
    return 'Активные возможности организации временно приостановлены.'
  }

  return 'Нарушений не зафиксировано. Организация доступна на платформе.'
})
const organizationStatusVariant = computed(() => {
  if (organization.value?.status === 'blocked') {
    return 'danger'
  }

  if (organization.value?.status === 'suspended') {
    return 'info'
  }

  return 'success'
})
const memberRows = computed<Record<string, unknown>[]>(() =>
  (organization.value?.members ?? []).map((member) => ({
    ...member,
    name: member.display_name || member.username || '—',
    email_verified_at: formatDate(member.email_verified_at),
  })),
)
const organizationProjects = computed<ProjectListItem[]>(() => organization.value?.projects ?? [])
const organizationProjectContentTypeIds = computed(
  () => new Set(organizationProjects.value.map((project) => project.game_content_type_id)),
)
const blockModalDescription = computed(
  () => `Организация ${title.value} будет заблокирована и потеряет доступ к активным возможностям.`,
)
const freezeModalDescription = computed(
  () => `Организация ${title.value} будет заморожена и потеряет доступ к активным возможностям.`,
)
const deleteModalDescription = computed(
  () => `Организация ${title.value} будет удалена. Это действие скроет ее из рабочего списка.`,
)

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

function formatBoolean(value: boolean | null | undefined): string {
  if (value === undefined || value === null) {
    return '—'
  }

  return value ? 'Да' : 'Нет'
}

function formatJsonValue(value: unknown): string {
  if (value === null || value === undefined || value === '') {
    return '—'
  }

  if (Array.isArray(value)) {
    return value.length > 0 ? value.join(', ') : '—'
  }

  if (typeof value === 'object') {
    return JSON.stringify(value)
  }

  return String(value)
}

function organizationInitial(): string {
  return title.value.slice(0, 1).toUpperCase()
}

function memberInitial(name: string): string {
  return name.slice(0, 1).toUpperCase()
}

function memberStatusClass(color: string | null): string {
  return `organization-status--${color ?? 'gray'}`
}

function toggleMoreMenu(): void {
  isMoreOpen.value = !isMoreOpen.value
}

async function loadOrganization(): Promise<void> {
  isLoading.value = true
  message.value = ''

  try {
    organization.value = await fetchOrganization(String(route.params.id))
    route.meta.breadcrumbLabel = organization.value.name
    await loadProjectActivityFilters()
  } catch {
    message.value = 'Не удалось загрузить организацию.'
  } finally {
    isLoading.value = false
  }
}

async function loadProjectActivityFilters(): Promise<void> {
  const projects = organizationProjects.value

  if (projects.length === 0) {
    projectContentTypeOptions.value = []
    projectDimensions.value = []
    projectReleaseDimensionValueIds.value = {}

    return
  }

  const contentTypesResponse = await fetchProjectContentTypes()
  const contentTypesById = new Map(
    contentTypesResponse.data.map((contentType) => [contentType.id, contentType]),
  )
  const relevantContentTypes = [...organizationProjectContentTypeIds.value]
    .map((contentTypeId) => contentTypesById.get(contentTypeId))
    .filter((contentType): contentType is ProjectContentTypeOption => contentType !== undefined)

  projectContentTypeOptions.value = contentTypesResponse.data

  const [dimensionResponses, releaseResponses] = await Promise.all([
    Promise.all(
      relevantContentTypes.map((contentType) =>
        fetchGameDimensions(contentType.game_id, contentType.id),
      ),
    ),
    Promise.all(projects.map((project) => fetchProjectReleases(project.id))),
  ])

  projectDimensions.value = dimensionResponses.flatMap((response) => response.data)
  projectReleaseDimensionValueIds.value = Object.fromEntries(
    projects.map((project, index) => {
      const valueIds = new Set<number>()

      for (const release of releaseResponses[index]?.data ?? []) {
        for (const valueId of release.dimension_value_ids ?? []) {
          valueIds.add(valueId)
        }
      }

      return [project.id, [...valueIds]]
    }),
  )
}

async function runOrganizationAction(
  action: () => Promise<unknown>,
  successMessage: string,
): Promise<void> {
  if (!organization.value) {
    return
  }

  isActionLoading.value = true
  message.value = ''

  try {
    await action()
    await loadOrganization()
    message.value = successMessage
  } catch {
    message.value = 'Не удалось выполнить действие.'
  } finally {
    isActionLoading.value = false
  }
}

function block(): void {
  isMoreOpen.value = false
  isBlockModalOpen.value = true
}

function closeBlockModal(): void {
  if (isActionLoading.value) {
    return
  }

  isBlockModalOpen.value = false
}

function confirmBlock(): void {
  if (!organization.value) {
    return
  }

  const organizationId = organization.value.id

  void runOrganizationAction(() => blockOrganization(organizationId), 'Организация заблокирована.')

  isBlockModalOpen.value = false
}

function unblock(): void {
  if (!organization.value) {
    return
  }

  const organizationId = organization.value.id
  isMoreOpen.value = false

  void runOrganizationAction(
    () => unblockOrganization(organizationId),
    'Организация разблокирована.',
  )
}

function freeze(): void {
  isMoreOpen.value = false
  isFreezeModalOpen.value = true
}

function closeFreezeModal(): void {
  if (isActionLoading.value) {
    return
  }

  isFreezeModalOpen.value = false
}

function confirmFreeze(): void {
  if (!organization.value) {
    return
  }

  const organizationId = organization.value.id

  void runOrganizationAction(() => freezeOrganization(organizationId), 'Организация заморожена.')

  isFreezeModalOpen.value = false
}

function unfreeze(): void {
  if (!organization.value) {
    return
  }

  const organizationId = organization.value.id
  isMoreOpen.value = false

  void runOrganizationAction(() => unfreezeOrganization(organizationId), 'Организация разморожена.')
}

function softDelete(): void {
  isMoreOpen.value = false
  isDeleteModalOpen.value = true
}

function closeDeleteModal(): void {
  if (isActionLoading.value) {
    return
  }

  isDeleteModalOpen.value = false
}

async function confirmSoftDelete(): Promise<void> {
  if (!organization.value) {
    return
  }

  const organizationId = organization.value.id

  isActionLoading.value = true
  message.value = ''

  try {
    await softDeleteOrganization(organizationId)
    await router.replace({ name: 'orgs.index' })
  } catch {
    message.value = 'Не удалось выполнить действие.'
  } finally {
    isActionLoading.value = false
    isDeleteModalOpen.value = false
  }
}

onMounted(() => {
  void loadOrganization()
})
</script>

<template>
  <AppShell>
    <section class="profile-page">
      <p v-if="message" class="profile-page__message">
        {{ message }}
      </p>

      <div v-if="isLoading" class="profile-card profile-card--loading">Загрузка...</div>

      <template v-else-if="organization">
        <header :class="heroClasses" :style="heroStyle">
          <div class="profile-hero__avatar" aria-hidden="true">
            <img v-if="organization.avatar_url" :src="organization.avatar_url" alt="" />
            <span v-else>{{ organizationInitial() }}</span>
          </div>

          <div class="profile-hero__content">
            <div class="profile-hero__title-row">
              <h2 class="profile-hero__title">
                {{ organization.name }}
              </h2>
            </div>

            <p class="profile-hero__username">
              {{ subtitle }}
            </p>

            <div class="profile-hero__meta">
              <span>
                <Mail :size="15" :stroke-width="1.9" aria-hidden="true" />
                {{ organization.contact_email || 'Почта не указана' }}
              </span>
              <span>
                <Calendar :size="15" :stroke-width="1.9" aria-hidden="true" />
                Создана {{ formatDate(organization.created_at) }}
              </span>
              <span v-if="organization.verified_at">
                <Check :size="15" :stroke-width="1.9" aria-hidden="true" />
                Верифицирована {{ formatDate(organization.verified_at) }}
              </span>
            </div>
          </div>

          <div class="profile-hero__actions">
            <RouterLink
              class="button button--secondary profile-hero__action"
              :to="{ name: 'orgs.index' }"
              aria-label="Назад к организациям"
            >
              <ArrowLeft :size="16" :stroke-width="1.9" aria-hidden="true" />
              Назад
            </RouterLink>

            <button
              class="button button--secondary profile-preview__more"
              type="button"
              :disabled="isActionLoading"
              :aria-expanded="isMoreOpen"
              aria-haspopup="menu"
              @click="toggleMoreMenu"
            >
              <MoreHorizontal :size="18" aria-hidden="true" />
              <span>Ещё</span>
              <ChevronDown
                class="profile-preview__more-chevron"
                :class="{ 'profile-preview__more-chevron--open': isMoreOpen }"
                :size="16"
                aria-hidden="true"
              />
            </button>

            <div v-if="isMoreOpen" class="profile-preview__more-menu" role="menu">
              <button
                v-if="organization.status === 'active'"
                type="button"
                role="menuitem"
                :disabled="isActionLoading"
                @click="freeze"
              >
                <Snowflake :size="16" :stroke-width="1.9" aria-hidden="true" />
                <span>Заморозить</span>
              </button>

              <button
                v-if="organization.status === 'suspended'"
                type="button"
                role="menuitem"
                :disabled="isActionLoading"
                @click="unfreeze"
              >
                <RotateCcw :size="16" :stroke-width="1.9" aria-hidden="true" />
                <span>Разморозить</span>
              </button>

              <button
                v-if="organization.status !== 'blocked'"
                type="button"
                role="menuitem"
                :disabled="isActionLoading"
                @click="block"
              >
                <Ban :size="16" :stroke-width="1.9" aria-hidden="true" />
                <span>Заблокировать</span>
              </button>

              <button
                v-if="organization.status === 'blocked'"
                type="button"
                role="menuitem"
                :disabled="isActionLoading"
                @click="unblock"
              >
                <RotateCcw :size="16" :stroke-width="1.9" aria-hidden="true" />
                <span>Разблокировать</span>
              </button>

              <button
                class="profile-preview__more-menu-danger"
                type="button"
                role="menuitem"
                :disabled="isActionLoading"
                @click="softDelete"
              >
                <Trash2 :size="16" :stroke-width="1.9" aria-hidden="true" />
                <span>Удалить</span>
              </button>
            </div>
          </div>
        </header>

        <div class="profile-tabs" role="tablist" aria-label="Разделы организации">
          <button
            v-for="tab in tabs"
            :key="tab.value"
            class="profile-tab"
            :class="{ 'profile-tab--active': activeTab === tab.value }"
            type="button"
            role="tab"
            :aria-selected="activeTab === tab.value"
            @click="activeTab = tab.value"
          >
            {{ tab.label }}
          </button>
        </div>

        <div class="profile-layout">
          <div class="profile-main-column">
            <template v-if="activeTab === 'overview'">
              <section class="profile-card profile-card--main">
                <header class="profile-card__header">
                  <h3 class="profile-card__title">
                    <Building2 :size="18" :stroke-width="1.9" aria-hidden="true" />
                    Организация
                  </h3>
                </header>

                <div class="profile-overview">
                  <div class="profile-field">
                    <span class="profile-field__label">Краткое описание</span>
                    <strong>{{ organization.summary || '—' }}</strong>
                  </div>

                  <div class="profile-field profile-field--wide">
                    <span class="profile-field__label">Описание</span>
                    <RichTextRenderer
                      class="profile-field__text"
                      :value="organization.description"
                      empty-text="Описание организации пока не заполнено."
                    />
                  </div>
                </div>

                <div class="profile-info-grid">
                  <div class="profile-info">
                    <Mail :size="19" :stroke-width="1.9" aria-hidden="true" />
                    <span class="profile-info__label">Почта</span>
                    <strong class="profile-info__value">
                      {{ organization.contact_email || '—' }}
                    </strong>
                  </div>

                  <div class="profile-info">
                    <Globe2 :size="19" :stroke-width="1.9" aria-hidden="true" />
                    <span class="profile-info__label">Публичная</span>
                    <strong class="profile-info__value">
                      {{ formatBoolean(organization.is_visible) }}
                    </strong>
                  </div>

                  <div class="profile-info">
                    <Link2 :size="19" :stroke-width="1.9" aria-hidden="true" />
                    <span class="profile-info__label">Сайты</span>
                    <strong class="profile-info__value">
                      {{ formatJsonValue(organization.website_urls) }}
                    </strong>
                  </div>

                  <div class="profile-info">
                    <Check :size="19" :stroke-width="1.9" aria-hidden="true" />
                    <span class="profile-info__label">Верификация</span>
                    <strong class="profile-info__value">
                      {{ formatDate(organization.verified_at) }}
                    </strong>
                  </div>
                </div>
              </section>
            </template>

            <template v-else-if="activeTab === 'members'">
              <div class="data-table-panel profile-members-table">
                <DataTable
                  :columns="memberColumns"
                  :rows="memberRows"
                  empty-text="В организации нет участников"
                >
                  <template #cell-name="{ row, value }">
                    <div class="organization-name">
                      <span class="organization-avatar" aria-hidden="true">
                        <img v-if="row.avatar_url" :src="String(row.avatar_url)" alt="" />
                        <span v-else>{{ memberInitial(String(value)) }}</span>
                      </span>
                      <strong>{{ value }}</strong>
                    </div>
                  </template>

                  <template #cell-status_label="{ row, value }">
                    <span
                      class="organization-status"
                      :class="memberStatusClass(String(row.status_color || 'gray'))"
                    >
                      {{ value || row.status || '—' }}
                    </span>
                  </template>

                  <template #cell-actions="{ row }">
                    <RouterLink
                      v-if="row.user_id"
                      class="organization-link"
                      :to="{ name: 'user.show', params: { id: String(row.user_id) } }"
                      aria-label="Открыть пользователя"
                      title="Открыть пользователя"
                    >
                      Открыть
                      <ExternalLink :size="14" :stroke-width="1.9" aria-hidden="true" />
                    </RouterLink>
                  </template>
                </DataTable>
              </div>
            </template>

            <template v-else>
              <ProjectIndexPanel
                :projects="organizationProjects"
                :content-type-options="projectContentTypeOptions"
                :dimensions="projectDimensions"
                :release-dimension-value-ids="projectReleaseDimensionValueIds"
                restrict-content-types-to-projects
                empty-text="Проекты организации не найдены."
              />
            </template>
          </div>

          <aside class="profile-side-column">
            <section class="profile-card profile-card--side">
              <header class="profile-card__header">
                <h3 class="profile-card__title">
                  <Building2 :size="18" :stroke-width="1.9" aria-hidden="true" />
                  Организация
                </h3>
              </header>

              <div class="account-list">
                <div class="account-list__item">
                  <span>ID</span>
                  <strong>{{ organization.id }}</strong>
                </div>

                <div class="account-list__item">
                  <span>Название</span>
                  <strong>{{ organization.name }}</strong>
                </div>

                <div class="account-list__item">
                  <span>Создана</span>
                  <strong>{{ formatDate(organization.created_at) }}</strong>
                </div>
              </div>
            </section>

            <section
              class="profile-card profile-card--side profile-account-status"
              :class="`profile-account-status--${organizationStatusVariant}`"
            >
              <Ban
                v-if="organization.status === 'blocked'"
                :size="54"
                :stroke-width="1.7"
                aria-hidden="true"
              />
              <Snowflake
                v-else-if="organization.status === 'suspended'"
                :size="54"
                :stroke-width="1.7"
                aria-hidden="true"
              />
              <ShieldCheck v-else :size="54" :stroke-width="1.7" aria-hidden="true" />
              <strong>{{ organizationStatusTitle }}</strong>
              <p>{{ organizationStatusText }}</p>
            </section>
          </aside>
        </div>
      </template>
    </section>

    <BlockModal
      :open="isBlockModalOpen"
      title="Заблокировать организацию?"
      :description="blockModalDescription"
      :loading="isActionLoading"
      @cancel="closeBlockModal"
      @confirm="confirmBlock"
    />

    <BlockModal
      :open="isFreezeModalOpen"
      title="Заморозить организацию?"
      :description="freezeModalDescription"
      icon="snowflake"
      confirm-text="Заморозить"
      :loading="isActionLoading"
      @cancel="closeFreezeModal"
      @confirm="confirmFreeze"
    />

    <DeleteModal
      :open="isDeleteModalOpen"
      title="Удалить организацию?"
      :description="deleteModalDescription"
      :loading="isActionLoading"
      @cancel="closeDeleteModal"
      @confirm="confirmSoftDelete"
    />
  </AppShell>
</template>

<style scoped>
.profile-page {
  display: grid;
  gap: 20px;
}

.profile-page__message {
  margin: 0;
  padding: 12px 14px;

  color: var(--color-danger);
  background: color-mix(in srgb, var(--color-danger) 10%, transparent);

  border: 1px solid color-mix(in srgb, var(--color-danger) 32%, transparent);
  border-radius: var(--radius-md);
}

.profile-hero {
  position: relative;
  isolation: isolate;

  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  align-items: center;
  gap: 22px;

  min-height: 148px;
  padding: 24px;

  background:
    linear-gradient(
      135deg,
      color-mix(in srgb, var(--color-primary) 18%, transparent),
      transparent 42%
    ),
    var(--color-surface);

  border: 1px solid var(--color-border-soft);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
}

.profile-hero::before {
  content: '';
  position: absolute;
  inset: 0;
  z-index: -1;

  background: var(--color-surface);
  border-radius: inherit;
}

.profile-hero--with-banner {
  overflow: hidden;
  background-position: center;
  background-size: cover;
}

.profile-hero--with-banner::before {
  background:
    linear-gradient(
      90deg,
      color-mix(in srgb, var(--color-surface) 94%, transparent),
      color-mix(in srgb, var(--color-surface) 70%, transparent) 52%,
      color-mix(in srgb, var(--color-surface) 34%, transparent)
    ),
    linear-gradient(
      0deg,
      color-mix(in srgb, var(--color-surface) 42%, transparent),
      color-mix(in srgb, var(--color-surface) 42%, transparent)
    );
}

.profile-hero__back {
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
    transform 160ms ease;
}

.profile-hero__back:hover {
  color: var(--color-text);
  background: var(--color-surface-hover);
}

.profile-hero__back:active {
  transform: scale(0.96);
}

.profile-hero__avatar {
  display: grid;
  place-items: center;
  overflow: hidden;

  width: 104px;
  height: 104px;

  color: var(--color-primary-text);
  background: var(--color-primary);

  border-radius: 20px;

  font-size: 46px;
  font-weight: 800;
  letter-spacing: 0;
}

.profile-hero__avatar img {
  width: 100%;
  height: 100%;

  object-fit: cover;
}

.profile-hero__content {
  min-width: 0;
}

.profile-hero__title-row {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
}

.profile-hero__title {
  margin: 0;

  color: var(--color-text);
  font-size: 28px;
  line-height: 1.1;
  letter-spacing: 0;
}

.profile-hero__username {
  margin: 6px 0 0;

  color: var(--color-text-muted);
  font-size: 15px;
}

.profile-hero__meta {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 14px;

  margin-top: 14px;

  color: var(--color-text-soft);
  font-size: 13px;
}

.profile-hero__meta span {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.profile-hero__actions {
  position: relative;
  display: flex;
  align-self: start;
  gap: 8px;
}

.profile-hero__action {
  gap: 8px;
  min-height: 40px;
}

.profile-preview__more {
  gap: 7px;
}

.profile-preview__more-chevron {
  transition: transform 160ms ease;
}

.profile-preview__more-chevron--open {
  transform: rotate(180deg);
}

.profile-preview__more-menu {
  position: absolute;
  top: calc(100% + 8px);
  right: 0;
  z-index: 5;
  min-width: 190px;
  padding: 5px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-md);
}

.profile-preview__more-menu button {
  display: flex;
  width: 100%;
  align-items: center;
  gap: 8px;
  padding: 9px 10px;
  color: var(--color-text);
  background: transparent;
  border: 0;
  border-radius: var(--radius-sm);
  cursor: pointer;
  text-align: left;
  font: inherit;
  font-size: 13px;
}

.profile-preview__more-menu button:hover {
  background: var(--color-surface-hover);
}

.profile-preview__more-menu button:disabled {
  cursor: wait;
  opacity: 0.6;
}

.profile-preview__more-menu-danger {
  color: var(--color-danger) !important;
}

.profile-tabs {
  display: flex;
  gap: 2px;
  border-bottom: 1px solid var(--color-border-soft);
}

.profile-tab {
  min-width: 112px;
  padding: 13px 24px;
  color: var(--color-text-muted);
  background: transparent;
  border: 0;
  border-bottom: 3px solid transparent;
  cursor: pointer;
  font: inherit;
  font-size: 14px;
  font-weight: 750;
}

.profile-tab:hover,
.profile-tab--active {
  color: var(--color-primary);
}

.profile-tab--active {
  border-bottom-color: var(--color-primary);
}

.profile-layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(320px, 420px);
  gap: 16px;
  align-items: start;
}

.profile-main-column,
.profile-side-column {
  display: grid;
  gap: 16px;
  min-width: 0;
}

.profile-card {
  padding: 18px;

  background: var(--color-surface);
  border: 1px solid var(--color-border-soft);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
}

.profile-card--loading {
  color: var(--color-text-muted);
}

.profile-card__header {
  display: flex;
  align-items: center;
  justify-content: space-between;

  margin-bottom: 16px;
}

.profile-card__title {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  margin: 0;

  color: var(--color-text);
  font-size: 16px;
  font-weight: 800;
  letter-spacing: 0;
}

.profile-card__title svg {
  color: var(--color-text-muted);
}

.profile-card__empty {
  margin: 0;
  color: var(--color-text-muted);
}

.profile-overview {
  display: grid;
  grid-template-columns: 1fr;
  gap: 20px;
  margin-bottom: 18px;
}

.profile-field {
  display: grid;
  gap: 8px;
  min-width: 0;
}

.profile-field__label {
  color: var(--color-text-muted);
  font-size: 13px;
}

.profile-field strong {
  color: var(--color-text);
  font-size: 15px;
}

.profile-field__text {
  color: var(--color-text-muted);
  line-height: 1.55;
}

.profile-info-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 10px;
}

.profile-info {
  display: flex;
  flex-direction: column;
  gap: 8px;

  min-width: 0;
  padding: 14px;

  background: var(--color-bg-muted);
  border: 1px solid var(--color-border-soft);
  border-radius: var(--radius-md);
}

.profile-info svg {
  color: var(--color-primary);
}

.profile-info__label {
  color: var(--color-text-soft);
  font-size: 12px;
}

.profile-info__value {
  overflow: hidden;
  color: var(--color-text);
  font-size: 14px;
  font-weight: 700;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.account-list {
  display: grid;
  gap: 10px;
}

.account-list__item {
  display: grid;
  grid-template-columns: minmax(120px, 0.8fr) minmax(0, 1fr);
  gap: 12px;
  align-items: baseline;

  padding-bottom: 10px;

  border-bottom: 1px solid var(--color-border-soft);
}

.account-list__item:last-child {
  padding-bottom: 0;
  border-bottom: 0;
}

.account-list__item span {
  color: var(--color-text-soft);
  font-size: 12px;
}

.account-list__item strong {
  overflow: hidden;

  color: var(--color-text);
  font-size: 14px;
  font-weight: 700;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.organization-name {
  display: inline-flex;
  align-items: center;
  gap: 12px;
  min-width: 0;
}

.organization-name strong {
  color: var(--color-text);
  font-size: 14px;
  font-weight: 750;
}

.organization-avatar {
  display: grid;
  flex: 0 0 auto;
  place-items: center;
  overflow: hidden;
  width: 40px;
  height: 40px;
  color: var(--color-primary);
  background: color-mix(in srgb, var(--color-primary) 14%, var(--color-surface));
  border: 1px solid color-mix(in srgb, var(--color-primary) 26%, transparent);
  border-radius: var(--radius-sm);
  font-size: 15px;
  font-weight: 850;
}

.organization-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.organization-status {
  display: inline-flex;
  align-items: center;
  min-height: 26px;
  padding: 0 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 800;
}

.organization-status--success {
  color: var(--color-success);
  background: color-mix(in srgb, var(--color-success) 12%, transparent);
}

.organization-status--warning {
  color: var(--color-warning);
  background: color-mix(in srgb, var(--color-warning) 12%, transparent);
}

.organization-status--danger {
  color: var(--color-danger);
  background: color-mix(in srgb, var(--color-danger) 10%, transparent);
}

.organization-status--gray {
  color: var(--color-text-muted);
  background: var(--color-bg-muted);
}

.organization-link {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-height: 34px;
  padding: 0 10px;
  color: var(--color-text);
  text-decoration: none;
  background: var(--color-bg-muted);
  border: 1px solid var(--color-border-soft);
  border-radius: var(--radius-md);
  font-size: 13px;
  font-weight: 700;
}

.organization-link:hover {
  color: var(--color-primary);
  background: var(--color-surface-hover);
}

.profile-account-status {
  display: grid;
  min-height: 260px;
  place-items: center;
  align-content: center;
  gap: 12px;
  text-align: center;
}

.profile-account-status--success svg {
  color: var(--color-success);
}

.profile-account-status--info svg {
  color: var(--color-info);
}

.profile-account-status--danger svg {
  color: var(--color-danger);
}

.profile-account-status strong {
  color: var(--color-text);
  font-size: 18px;
}

.profile-account-status p {
  max-width: 280px;
  margin: 0;
  color: var(--color-text-muted);
  line-height: 1.5;
}

@media (max-width: 1040px) {
  .profile-layout {
    grid-template-columns: 1fr;
  }

  .profile-side-column {
    order: -1;
  }

  .profile-info-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 720px) {
  .profile-hero {
    grid-template-columns: 1fr;
  }

  .profile-hero__avatar {
    width: 56px;
    height: 56px;

    font-size: 24px;
    border-radius: 18px;
  }

  .profile-hero__content {
    grid-column: auto;
  }

  .profile-hero__actions {
    width: 100%;
  }

  .profile-hero__action {
    flex: 1 1 0;
  }

  .profile-info-grid {
    grid-template-columns: 1fr;
  }

  .profile-tabs {
    overflow-x: auto;
  }
}
</style>
