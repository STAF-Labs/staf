<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppShell from '@/components/layout/AppShell.vue'
import BlockModal from '@/components/ui/BlockModal.vue'
import DeleteModal from '@/components/ui/DeleteModal.vue'
import {
  blockUser,
  fetchUser,
  freezeUser,
  softDeleteUser,
  unblockUser,
  unfreezeUser,
  type UserDetail,
} from '@/shared/users/users'
import { ArrowLeft, Ban, RotateCcw, Snowflake, Trash2 } from '@lucide/vue'

const route = useRoute()
const router = useRouter()
const user = ref<UserDetail | null>(null)
const isLoading = ref(false)
const isActionLoading = ref(false)
const isBlockModalOpen = ref(false)
const isFreezeModalOpen = ref(false)
const isDeleteModalOpen = ref(false)
const message = ref('')

const title = computed(() => {
  if (!user.value) {
    return 'Пользователь'
  }

  return user.value.profile?.display_name || user.value.username
})

const subtitle = computed(() => {
  if (!user.value) {
    return 'Просмотр учетной записи и профиля.'
  }

  return `ID ${user.value.id} · ${user.value.email}`
})

const statusColorClass = computed(() => {
  const color = user.value?.status_color ?? 'gray'

  return `profile-status--${color}`
})
const bannerUrl = computed(() => user.value?.banner_url || user.value?.profile?.banner_url || null)
const heroClasses = computed(() => ({
  'profile-hero': true,
  'profile-hero--with-banner': bannerUrl.value !== null,
}))
const heroStyle = computed(() => (
  bannerUrl.value === null
    ? undefined
    : { backgroundImage: `url("${bannerUrl.value}")` }
))
const deleteModalDescription = computed(() => (
  `Пользователь ${title.value} будет удален. Это действие скроет его из рабочего списка.`
))
const blockModalDescription = computed(() => (
  `Пользователь ${title.value} будет заблокирован и потеряет доступ к активным возможностям аккаунта.`
))
const freezeModalDescription = computed(() => (
  `Пользователь ${title.value} будет заморожен и потеряет доступ к активным возможностям аккаунта.`
))

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

function organizationStatusClass(color: string | null): string {
  return `organization-status--${color ?? 'gray'}`
}

async function loadUser(): Promise<void> {
  isLoading.value = true
  message.value = ''

  try {
    user.value = await fetchUser(String(route.params.id))
    route.meta.breadcrumbLabel = title.value
  } catch {
    message.value = 'Не удалось загрузить пользователя.'
  } finally {
    isLoading.value = false
  }
}

async function runUserAction(
  action: () => Promise<UserDetail | { message: string }>,
  successMessage: string,
): Promise<void> {
  if (!user.value) {
    return
  }

  isActionLoading.value = true
  message.value = ''

  try {
    const response = await action()

    if ('username' in response) {
      user.value = response
    }

    message.value = successMessage
  } catch {
    message.value = 'Не удалось выполнить действие.'
  } finally {
    isActionLoading.value = false
  }
}

function block(): void {
  if (!user.value) {
    return
  }

  isBlockModalOpen.value = true
}

function closeBlockModal(): void {
  if (isActionLoading.value) {
    return
  }

  isBlockModalOpen.value = false
}

function confirmBlock(): void {
  if (!user.value) {
    return
  }

  const userId = user.value.id

  void runUserAction(
    () => blockUser(userId),
    'Пользователь заблокирован.',
  )

  isBlockModalOpen.value = false
}

function unblock(): void {
  if (!user.value) {
    return
  }

  const userId = user.value.id

  void runUserAction(
    () => unblockUser(userId),
    'Пользователь разблокирован.',
  )
}

function freeze(): void {
  if (!user.value) {
    return
  }

  isFreezeModalOpen.value = true
}

function closeFreezeModal(): void {
  if (isActionLoading.value) {
    return
  }

  isFreezeModalOpen.value = false
}

function confirmFreeze(): void {
  if (!user.value) {
    return
  }

  const userId = user.value.id

  void runUserAction(
    () => freezeUser(userId),
    'Пользователь заморожен.',
  )

  isFreezeModalOpen.value = false
}

function unfreeze(): void {
  if (!user.value) {
    return
  }

  const userId = user.value.id

  void runUserAction(
    () => unfreezeUser(userId),
    'Пользователь разморожен.',
  )
}

function softDelete(): void {
  if (!user.value) {
    return
  }

  isDeleteModalOpen.value = true
}

function closeDeleteModal(): void {
  if (isActionLoading.value) {
    return
  }

  isDeleteModalOpen.value = false
}

async function confirmSoftDelete(): Promise<void> {
  if (!user.value) {
    return
  }

  const userId = user.value.id

  isActionLoading.value = true
  message.value = ''

  try {
    await softDeleteUser(userId)
    await router.replace({ name: 'users.index' })
  } catch {
    message.value = 'Не удалось выполнить действие.'
  } finally {
    isActionLoading.value = false
    isDeleteModalOpen.value = false
  }
}

onMounted(() => {
  void loadUser()
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

      <template v-else-if="user">
        <header :class="heroClasses" :style="heroStyle">
          <RouterLink
            class="profile-hero__back"
            :to="{ name: 'users.index' }"
            aria-label="Назад к пользователям"
          >
            <ArrowLeft :size="20" :stroke-width="1.9" />
          </RouterLink>

          <div class="profile-hero__avatar" aria-hidden="true">
            <img
              v-if="user.avatar_url"
              :src="user.avatar_url"
              alt=""
            >
            <span v-else>{{ title.slice(0, 1).toUpperCase() }}</span>
          </div>

          <div class="profile-hero__content">
            <div class="profile-hero__title-row">
              <h2 class="profile-hero__title">
                {{ title }}
              </h2>

              <span class="profile-status" :class="statusColorClass">
                {{ user.status_label }}
              </span>
            </div>

            <p class="profile-hero__username">
              {{ user.username }}
            </p>

            <div class="profile-hero__meta">
              <span>{{ user.email }}</span>
              <span>Создан {{ formatDate(user.created_at) }}</span>
              <span>Был онлайн {{ formatDate(user.last_seen_at) }}</span>
            </div>

            <div class="profile-hero__actions">
              <button
                v-if="user.status === 'blocked'"
                class="profile-action"
                type="button"
                :disabled="isActionLoading"
                @click="unblock"
              >
                <RotateCcw :size="17" :stroke-width="1.9" aria-hidden="true" />
                <span>Разблокировать</span>
              </button>

              <button
                v-if="user.status !== 'blocked'"
                class="profile-action"
                type="button"
                :disabled="isActionLoading"
                @click="block"
              >
                <Ban :size="17" :stroke-width="1.9" aria-hidden="true" />
                <span>Заблокировать</span>
              </button>

              <button
                v-if="user.status === 'active'"
                class="profile-action"
                type="button"
                :disabled="isActionLoading"
                @click="freeze"
              >
                <Snowflake :size="17" :stroke-width="1.9" aria-hidden="true" />
                <span>Заморозить</span>
              </button>

              <button
                v-if="user.status === 'suspended'"
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
                <h3 class="profile-card__title">Профиль</h3>
              </header>

              <template v-if="user.profile">
                <div class="profile-about">
                  <h4 class="profile-about__name">
                    {{ user.profile.display_name || user.username }}
                  </h4>

                  <p class="profile-about__text">
                    Тут будет описание профиля из TipTap.
                  </p>
                </div>

                <div class="profile-info-grid">
                  <div class="profile-info">
                    <span class="profile-info__label">Дата рождения</span>
                    <strong class="profile-info__value">
                      {{ formatDate(user.profile.birthday, false) }}
                    </strong>
                  </div>

                  <div class="profile-info">
                    <span class="profile-info__label">Публичный профиль</span>
                    <strong class="profile-info__value">
                      {{ formatBoolean(user.profile.is_public) }}
                    </strong>
                  </div>

                  <div class="profile-info">
                    <span class="profile-info__label">Показывать онлайн</span>
                    <strong class="profile-info__value">
                      {{ formatBoolean(user.profile.show_online_status) }}
                    </strong>
                  </div>

                  <div class="profile-info">
                    <span class="profile-info__label">Показывать был онлайн</span>
                    <strong class="profile-info__value">
                      {{ formatBoolean(user.profile.show_last_seen_at) }}
                    </strong>
                  </div>
                </div>

                <div class="profile-section">
                  <h4 class="profile-section__title">Сайты</h4>
                  <p class="profile-section__text">
                    {{ formatJsonValue(user.profile.website_urls) }}
                  </p>
                </div>
              </template>

              <p v-else class="profile-card__empty">
                Профиль не заполнен.
              </p>
            </section>

            <section class="profile-card profile-card--main">
              <header class="profile-card__header">
                <h3 class="profile-card__title">Организации</h3>
              </header>

              <div v-if="user.organizations.length > 0" class="organization-table">
                <table>
                  <thead>
                    <tr>
                      <th scope="col">Название</th>
                      <th scope="col">Статус</th>
                      <th scope="col">Видимость</th>
                      <th scope="col">Верификация</th>
                    </tr>
                  </thead>

                  <tbody>
                    <tr v-for="organization in user.organizations" :key="organization.id">
                      <td>{{ organization.name }}</td>
                      <td>
                        <span class="organization-status" :class="organizationStatusClass(organization.status_color)">
                          {{ organization.status_label }}
                        </span>
                      </td>
                      <td>{{ formatBoolean(organization.is_visible) }}</td>
                      <td>{{ formatDate(organization.verified_at) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <div v-else class="organizations-empty">
                Не состоит в организациях
              </div>
            </section>
          </div>

          <aside class="profile-card profile-card--side">
            <header class="profile-card__header">
              <h3 class="profile-card__title">Аккаунт</h3>
            </header>

            <div class="account-list">
              <div class="account-list__item">
                <span>Логин</span>
                <strong>{{ user.username }}</strong>
              </div>

              <div class="account-list__item">
                <span>Почта</span>
                <strong>{{ user.email }}</strong>
              </div>

              <div class="account-list__item">
                <span>Статус</span>
                <strong>{{ user.status_label }}</strong>
              </div>

              <div class="account-list__item">
                <span>Почта подтверждена</span>
                <strong>{{ formatDate(user.email_verified_at) }}</strong>
              </div>

              <div class="account-list__item">
                <span>Был онлайн</span>
                <strong>{{ formatDate(user.last_seen_at) }}</strong>
              </div>
            </div>
          </aside>
        </div>
      </template>
    </section>

    <BlockModal
      :open="isBlockModalOpen"
      title="Заблокировать пользователя?"
      :description="blockModalDescription"
      :loading="isActionLoading"
      @cancel="closeBlockModal"
      @confirm="confirmBlock"
    />

    <BlockModal
      :open="isFreezeModalOpen"
      title="Заморозить пользователя?"
      :description="freezeModalDescription"
      icon="snowflake"
      confirm-text="Заморозить"
      :loading="isActionLoading"
      @cancel="closeFreezeModal"
      @confirm="confirmFreeze"
    />

    <DeleteModal
      :open="isDeleteModalOpen"
      title="Удалить пользователя?"
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

  width: 72px;
  height: 72px;

  color: var(--color-primary-text);
  background: var(--color-primary);

  border-radius: 22px;

  font-size: 30px;
  font-weight: 800;
  letter-spacing: -0.04em;
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
  letter-spacing: -0.04em;
}

.profile-status {
  display: inline-flex;
  align-items: center;

  min-height: 26px;
  padding: 0 10px;

  border: 1px solid transparent;
  border-radius: 999px;

  font-size: 13px;
  font-weight: 700;
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

.profile-hero__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;

  margin-top: 16px;
}

.profile-action {
  display: inline-flex;
  align-items: center;
  gap: 8px;

  min-height: 36px;
  padding: 0 12px;

  color: var(--color-text);
  background: var(--color-bg-muted);
  border: 1px solid var(--color-border-soft);
  border-radius: var(--radius-md);
  cursor: pointer;

  font-size: 13px;
  font-weight: 700;
}

.profile-action:hover {
  color: var(--color-primary);
  background: var(--color-surface-hover);
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
  margin: 0;

  color: var(--color-text);
  font-size: 16px;
  font-weight: 800;
  letter-spacing: -0.02em;
}

.profile-card__empty {
  margin: 0;

  color: var(--color-text-muted);
}

.profile-about {
  padding: 18px;

  background: var(--color-bg-muted);
  border: 1px solid var(--color-border-soft);
  border-radius: var(--radius-md);
}

.profile-about__name {
  margin: 0 0 8px;

  color: var(--color-text);
  font-size: 18px;
  line-height: 1.25;
}

.profile-about__text {
  margin: 0;

  color: var(--color-text-muted);
  line-height: 1.65;
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

  background: var(--color-bg-muted);
  border: 1px solid var(--color-border-soft);
  border-radius: var(--radius-md);
}

.profile-info__label {
  color: var(--color-text-soft);
  font-size: 12px;
}

.profile-info__value {
  color: var(--color-text);
  font-size: 14px;
  font-weight: 700;
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

.profile-section__text {
  margin: 0;

  color: var(--color-text-muted);
  line-height: 1.6;
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
