<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowLeft, Ban, RotateCcw, Snowflake, Trash2 } from '@lucide/vue'
import AppShell from '@/components/layout/AppShell.vue'
import BlockModal from '@/components/ui/BlockModal.vue'
import DeleteModal from '@/components/ui/DeleteModal.vue'
import RichTextRenderer from '@/components/ui/RichTextRenderer.vue'
import {
  blockOrganization,
  fetchOrganization,
  freezeOrganization,
  softDeleteOrganization,
  unblockOrganization,
  unfreezeOrganization,
  type OrganizationDetail,
} from '@/shared/orgs/organizations'

const route = useRoute()
const router = useRouter()
const organization = ref<OrganizationDetail | null>(null)
const isLoading = ref(false)
const isActionLoading = ref(false)
const isBlockModalOpen = ref(false)
const isFreezeModalOpen = ref(false)
const isDeleteModalOpen = ref(false)
const message = ref('')

const title = computed(() => organization.value?.name ?? 'Организация')
const statusColorClass = computed(() => `profile-status--${organization.value?.status_color ?? 'gray'}`)
const bannerUrl = computed(() => organization.value?.banner_url ?? null)
const heroClasses = computed(() => ({
  'profile-hero': true,
  'profile-hero--with-banner': bannerUrl.value !== null,
}))
const heroStyle = computed(() => (
  bannerUrl.value === null
    ? undefined
    : { backgroundImage: `url("${bannerUrl.value}")` }
))
const blockModalDescription = computed(() => (
  `Организация ${title.value} будет заблокирована и потеряет доступ к активным возможностям.`
))
const freezeModalDescription = computed(() => (
  `Организация ${title.value} будет заморожена и потеряет доступ к активным возможностям.`
))
const deleteModalDescription = computed(() => (
  `Организация ${title.value} будет удалена. Это действие скроет ее из рабочего списка.`
))

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

function memberStatusClass(color: string | null): string {
  return `member-badge--${color ?? 'gray'}`
}

function memberRoleClass(color: string | null): string {
  return `member-badge--${color ?? 'gray'}`
}

async function loadOrganization(): Promise<void> {
  isLoading.value = true
  message.value = ''

  try {
    organization.value = await fetchOrganization(String(route.params.id))
    route.meta.breadcrumbLabel = organization.value.name
  } catch {
    message.value = 'Не удалось загрузить организацию.'
  } finally {
    isLoading.value = false
  }
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

  void runOrganizationAction(
    () => blockOrganization(organizationId),
    'Организация заблокирована.',
  )

  isBlockModalOpen.value = false
}

function unblock(): void {
  if (!organization.value) {
    return
  }

  const organizationId = organization.value.id

  void runOrganizationAction(
    () => unblockOrganization(organizationId),
    'Организация разблокирована.',
  )
}

function freeze(): void {
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

  void runOrganizationAction(
    () => freezeOrganization(organizationId),
    'Организация заморожена.',
  )

  isFreezeModalOpen.value = false
}

function unfreeze(): void {
  if (!organization.value) {
    return
  }

  const organizationId = organization.value.id

  void runOrganizationAction(
    () => unfreezeOrganization(organizationId),
    'Организация разморожена.',
  )
}

function softDelete(): void {
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

      <div v-if="isLoading" class="profile-card profile-card--loading">
        Загрузка...
      </div>

      <template v-else-if="organization">
        <header :class="heroClasses" :style="heroStyle">
          <RouterLink
            class="profile-hero__back"
            :to="{ name: 'orgs.index' }"
            aria-label="Назад к организациям"
          >
            <ArrowLeft :size="20" :stroke-width="1.9" />
          </RouterLink>

          <div class="profile-hero__avatar" aria-hidden="true">
            <img
              v-if="organization.avatar_url"
              :src="organization.avatar_url"
              alt=""
            >
            <span v-else>{{ organizationInitial() }}</span>
          </div>

          <div class="profile-hero__content">
            <div class="profile-hero__title-row">
              <h2 class="profile-hero__title">
                {{ organization.name }}
              </h2>

              <span class="profile-status" :class="statusColorClass">
                {{ organization.status_label }}
              </span>
            </div>

            <p class="profile-hero__username">
              {{ organization.slug }}
            </p>

            <div class="profile-hero__meta">
              <span>{{ organization.contact_email || 'Почта не указана' }}</span>
              <span>Создана {{ formatDate(organization.created_at) }}</span>
              <span>Верификация {{ formatDate(organization.verified_at) }}</span>
            </div>

            <div class="profile-hero__actions">
              <button
                v-if="organization.status === 'blocked'"
                class="profile-action"
                type="button"
                :disabled="isActionLoading"
                @click="unblock"
              >
                <RotateCcw :size="17" :stroke-width="1.9" aria-hidden="true" />
                <span>Разблокировать</span>
              </button>

              <button
                v-if="organization.status !== 'blocked'"
                class="profile-action"
                type="button"
                :disabled="isActionLoading"
                @click="block"
              >
                <Ban :size="17" :stroke-width="1.9" aria-hidden="true" />
                <span>Заблокировать</span>
              </button>

              <button
                v-if="organization.status === 'active'"
                class="profile-action"
                type="button"
                :disabled="isActionLoading"
                @click="freeze"
              >
                <Snowflake :size="17" :stroke-width="1.9" aria-hidden="true" />
                <span>Заморозить</span>
              </button>

              <button
                v-if="organization.status === 'suspended'"
                class="profile-action"
                type="button"
                :disabled="isActionLoading"
                @click="unfreeze"
              >
                <RotateCcw :size="17" :stroke-width="1.9" aria-hidden="true" />
                <span>Разморозить</span>
              </button>

              <button
                class="profile-action profile-action--danger"
                type="button"
                :disabled="isActionLoading"
                @click="softDelete"
              >
                <Trash2 :size="17" :stroke-width="1.9" aria-hidden="true" />
                <span>Удалить</span>
              </button>
            </div>
          </div>
        </header>

        <div class="profile-layout">
          <div class="profile-main-column">
            <section class="profile-card profile-card--main">
              <header class="profile-card__header">
                <h3 class="profile-card__title">Основная информация</h3>
              </header>

              <div class="profile-about">
                <h4 class="profile-about__name">
                  {{ organization.summary || organization.name }}
                </h4>

                <RichTextRenderer
                  class="profile-about__text"
                  :value="organization.description"
                  empty-text="Описание организации пока не заполнено."
                />
              </div>

              <div class="profile-info-grid">
                <div class="profile-info">
                  <span class="profile-info__label">Slug</span>
                  <strong class="profile-info__value">{{ organization.slug }}</strong>
                </div>

                <div class="profile-info">
                  <span class="profile-info__label">Почта</span>
                  <strong class="profile-info__value">{{ organization.contact_email || '—' }}</strong>
                </div>

                <div class="profile-info">
                  <span class="profile-info__label">Видимость</span>
                  <strong class="profile-info__value">{{ formatBoolean(organization.is_visible) }}</strong>
                </div>

                <div class="profile-info">
                  <span class="profile-info__label">Верификация</span>
                  <strong class="profile-info__value">{{ formatDate(organization.verified_at) }}</strong>
                </div>
              </div>

              <div class="profile-section">
                <h4 class="profile-section__title">Сайты</h4>
                <p class="profile-section__text">
                  {{ formatJsonValue(organization.website_urls) }}
                </p>
              </div>
            </section>

            <section class="profile-card profile-card--main">
              <header class="profile-card__header">
                <h3 class="profile-card__title">Участники</h3>
              </header>

              <div v-if="organization.members.length > 0" class="members-table">
                <table>
                  <thead>
                    <tr>
                      <th scope="col">Имя пользователя</th>
                      <th scope="col">status</th>
                      <th scope="col">public_title</th>
                      <th scope="col">role</th>
                    </tr>
                  </thead>

                  <tbody>
                    <tr v-for="member in organization.members" :key="member.id">
                      <td>{{ member.username || '—' }}</td>
                      <td>
                        <span class="member-badge" :class="memberStatusClass(member.status_color)">
                          {{ member.status_label || member.status || '—' }}
                        </span>
                      </td>
                      <td>{{ member.public_title || '—' }}</td>
                      <td>
                        <span class="member-badge" :class="memberRoleClass(member.role_color)">
                          {{ member.role_label || member.role || '—' }}
                        </span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <div v-else class="members-empty">
                Нет участников
              </div>
            </section>
          </div>

          <aside class="profile-card profile-card--side">
            <header class="profile-card__header">
              <h3 class="profile-card__title">Организация</h3>
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
                <span>Статус</span>
                <strong>{{ organization.status_label }}</strong>
              </div>

              <div class="account-list__item">
                <span>Создана</span>
                <strong>{{ formatDate(organization.created_at) }}</strong>
              </div>
            </div>
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
  grid-template-columns: auto auto 1fr;
  align-items: center;
  gap: 16px;
  padding: 22px;
  background: linear-gradient(135deg, color-mix(in srgb, var(--color-primary) 18%, transparent), transparent 42%), var(--color-surface);
  border: 1px solid var(--color-border-soft);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
}

.profile-hero::before {
  content: "";
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
    linear-gradient(90deg, color-mix(in srgb, var(--color-surface) 94%, transparent), color-mix(in srgb, var(--color-surface) 70%, transparent) 52%, color-mix(in srgb, var(--color-surface) 34%, transparent)),
    linear-gradient(0deg, color-mix(in srgb, var(--color-surface) 42%, transparent), color-mix(in srgb, var(--color-surface) 42%, transparent));
}

.profile-hero__back,
.profile-action {
  color: var(--color-text);
  background: var(--color-bg-muted);
  border: 1px solid var(--color-border-soft);
  border-radius: var(--radius-md);
}

.profile-hero__back {
  display: grid;
  place-items: center;
  width: 40px;
  height: 40px;
  color: var(--color-text-muted);
}

.profile-hero__avatar {
  display: grid;
  place-items: center;
  overflow: hidden;
  width: 72px;
  height: 72px;
  color: var(--color-primary-text);
  background: var(--color-primary);
  border-radius: 22px;
  font-size: 30px;
  font-weight: 800;
}

.profile-hero__avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.profile-hero__content {
  min-width: 0;
}

.profile-hero__title-row,
.profile-hero__meta,
.profile-hero__actions {
  display: flex;
  flex-wrap: wrap;
}

.profile-hero__title-row {
  align-items: center;
  gap: 10px;
}

.profile-hero__title {
  margin: 0;
  color: var(--color-text);
  font-size: 28px;
  line-height: 1.1;
}

.profile-hero__username {
  margin: 6px 0 0;
  color: var(--color-text-muted);
  font-size: 15px;
}

.profile-hero__meta {
  gap: 8px 14px;
  margin-top: 14px;
  color: var(--color-text-soft);
  font-size: 13px;
}

.profile-hero__actions {
  gap: 8px;
  margin-top: 16px;
}

.profile-action {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-height: 36px;
  padding: 0 12px;
  cursor: pointer;
  font-size: 13px;
  font-weight: 700;
}

.profile-action:disabled {
  cursor: not-allowed;
  opacity: 0.55;
}

.profile-action--danger:hover {
  color: var(--color-danger);
}

.profile-layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 340px;
  gap: 20px;
  align-items: start;
}

.profile-main-column {
  display: grid;
  gap: 20px;
  min-width: 0;
}

.profile-card {
  padding: 18px;
  background: var(--color-surface);
  border: 1px solid var(--color-border-soft);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
}

.profile-card--loading,
.profile-card__empty {
  color: var(--color-text-muted);
}

.profile-card__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
}

.profile-card__title {
  margin: 0;
  color: var(--color-text);
  font-size: 16px;
  font-weight: 800;
}

.profile-about,
.profile-info {
  background: var(--color-bg-muted);
  border: 1px solid var(--color-border-soft);
  border-radius: var(--radius-md);
}

.profile-about {
  padding: 18px;
}

.profile-about__name {
  margin: 0 0 8px;
  color: var(--color-text);
  font-size: 18px;
  line-height: 1.25;
}

.profile-about__text,
.profile-section__text {
  margin: 0;
  color: var(--color-text-muted);
  line-height: 1.6;
}

.profile-info-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
  margin-top: 16px;
}

.profile-info {
  display: grid;
  gap: 6px;
  padding: 14px;
}

.profile-info__label,
.account-list__item span {
  color: var(--color-text-soft);
  font-size: 12px;
}

.profile-info__value,
.account-list__item strong {
  overflow: hidden;
  color: var(--color-text);
  font-size: 14px;
  font-weight: 700;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.profile-section {
  margin-top: 16px;
  padding-top: 16px;
  border-top: 1px solid var(--color-border-soft);
}

.profile-section__title {
  margin: 0 0 8px;
  color: var(--color-text);
  font-size: 14px;
  font-weight: 800;
}

.profile-status {
  display: inline-flex;
  align-items: center;
  min-height: 26px;
  padding: 0 10px;
  border: 1px solid transparent;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 800;
}

.profile-status--success {
  color: var(--color-success);
  background: color-mix(in srgb, var(--color-success) 12%, transparent);
  border-color: color-mix(in srgb, var(--color-success) 28%, transparent);
}

.profile-status--gray {
  color: var(--color-text-muted);
  background: var(--color-bg-muted);
  border-color: var(--color-border-soft);
}

.profile-status--danger {
  color: var(--color-danger);
  background: color-mix(in srgb, var(--color-danger) 10%, transparent);
  border-color: color-mix(in srgb, var(--color-danger) 28%, transparent);
}

.account-list {
  display: grid;
  gap: 10px;
}

.account-list__item {
  display: grid;
  gap: 4px;
  padding-bottom: 10px;
  border-bottom: 1px solid var(--color-border-soft);
}

.account-list__item:last-child {
  padding-bottom: 0;
  border-bottom: 0;
}

@media (max-width: 1040px) {
  .profile-layout {
    grid-template-columns: 1fr;
  }

  .profile-card--side {
    order: -1;
  }
}

@media (max-width: 720px) {
  .profile-hero {
    grid-template-columns: auto 1fr;
  }

  .profile-hero__avatar {
    width: 56px;
    height: 56px;
    font-size: 24px;
    border-radius: 18px;
  }

  .profile-hero__content {
    grid-column: 1 / -1;
  }

  .profile-info-grid {
    grid-template-columns: 1fr;
  }
}
</style>
