<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import type { RouteLocationRaw } from 'vue-router'
import { useRoute, useRouter } from 'vue-router'
import AppShell from '@/components/layout/AppShell.vue'
import DataTable from '@/components/data/DataTable.vue'
import BlockModal from '@/components/ui/BlockModal.vue'
import DeleteModal from '@/components/ui/DeleteModal.vue'
import RichTextRenderer from '@/components/ui/RichTextRenderer.vue'
import SearchField from '@/components/ui/SearchField.vue'
import ProjectIndexPanel from '@/views/projects/components/ProjectIndexPanel.vue'
import {
  ArrowLeft,
  Ban,
  Calendar,
  Check,
  ChevronDown,
  Circle,
  Clock3,
  ExternalLink,
  Globe2,
  Image,
  Info,
  Link2,
  LogOut,
  MoreHorizontal,
  Pencil,
  PlayCircle,
  RotateCcw,
  ShieldCheck,
  Snowflake,
  Trash2,
  UserRound,
} from '@lucide/vue'
import type { DataColumn } from '@/shared/data/table'
import {
  fetchProjectContentTypes,
  fetchProjectReleases,
  type ProjectContentTypeOption,
  type ProjectListItem,
  type ProjectStatus,
} from '@/shared/projects/projects'
import {
  fetchGameDimensions,
  type GameDimension,
  type GameDimensionValue,
} from '@/shared/games/games'
import { currentUser, logout } from '@/shared/auth/session'
import {
  blockUser,
  fetchUser,
  freezeUser,
  softDeleteUser,
  unblockUser,
  unfreezeUser,
  type UserDetail,
} from '@/shared/users/users'
import { pushUserActionNotification } from '@/shared/users/notifications'

type UserActionNotification = Parameters<typeof pushUserActionNotification>[0]

type ProjectStatusFilter = ProjectStatus | 'all'
type ProjectSortOption = 'title_asc' | 'title_desc' | 'released_at'
type ProjectDisplayMode = 'cards' | 'list'

const props = withDefaults(
  defineProps<{
    profileMode?: boolean
  }>(),
  {
    profileMode: false,
  },
)

const route = useRoute()
const router = useRouter()
const user = ref<UserDetail | null>(null)
const isLoading = ref(false)
const isActionLoading = ref(false)
const isBlockModalOpen = ref(false)
const isFreezeModalOpen = ref(false)
const isDeleteModalOpen = ref(false)
const isMoreOpen = ref(false)
const isLoggingOut = ref(false)
const activeTab = ref<'overview' | 'organizations' | 'activity'>('overview')
const projectSearch = ref('')
const projectGameFilter = ref('all')
const projectContentTypeFilter = ref('all')
const projectStatusFilter = ref<ProjectStatusFilter>('all')
const projectSort = ref<ProjectSortOption>('title_asc')
const projectPageSize = ref(20)
const projectDisplayMode = ref<ProjectDisplayMode>('cards')
const projectContentTypeOptions = ref<ProjectContentTypeOption[]>([])
const projectDimensions = ref<GameDimension[]>([])
const projectReleaseDimensionValueIds = ref<Record<number, number[]>>({})
const selectedProjectFilterValueIds = ref<Record<number, number[]>>({})
const selectedReleaseFilterValueIds = ref<Record<number, number[]>>({})
const message = ref('')
const tabs = [
  { value: 'overview', label: 'Обзор' },
  { value: 'organizations', label: 'Организации' },
  { value: 'activity', label: 'Активность' },
] as const
const organizationColumns: DataColumn[] = [
  { key: 'name', label: 'Название', visible: true },
  { key: 'status_label', label: 'Статус', visible: true },
  { key: 'is_visible', label: 'Видимость', visible: true },
  { key: 'verified_at', label: 'Верификация', visible: true },
  { key: 'actions', label: '', visible: true },
]
const projectStatusFilterOptions: Array<{ value: ProjectStatusFilter; label: string }> = [
  { value: 'all', label: 'Все статусы' },
  { value: 'draft', label: 'Черновик' },
  { value: 'on_moderation', label: 'На модерации' },
  { value: 'published', label: 'Опубликован' },
  { value: 'rejected', label: 'Отклонён' },
]
const projectSortOptions: Array<{ value: ProjectSortOption; label: string }> = [
  { value: 'title_asc', label: 'А-Я' },
  { value: 'title_desc', label: 'Я-А' },
  { value: 'released_at', label: 'Дата релиза' },
]
const projectPageSizeOptions = [10, 20, 30, 40, 50]
const isProfileMode = computed(() => props.profileMode)

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
const backTarget = computed<RouteLocationRaw>(() =>
  isProfileMode.value ? { name: 'dashboard' } : { name: 'users.index' },
)
const backLabel = computed(() =>
  isProfileMode.value ? 'Назад к главной' : 'Назад к пользователям',
)

const accountStatusTitle = computed(() => {
  if (user.value?.status === 'blocked') {
    return 'Аккаунт заблокирован'
  }

  if (user.value?.status === 'suspended') {
    return 'Аккаунт заморожен'
  }

  return 'Аккаунт активен'
})
const accountStatusText = computed(() => {
  if (user.value?.status === 'blocked') {
    return 'Доступ пользователя ограничен.'
  }

  if (user.value?.status === 'suspended') {
    return 'Активные возможности аккаунта временно приостановлены.'
  }

  return 'Нарушений не зафиксировано. Доступ ко всем функциям платформы.'
})
const accountStatusVariant = computed(() => {
  if (user.value?.status === 'blocked') {
    return 'danger'
  }

  if (user.value?.status === 'suspended') {
    return 'info'
  }

  return 'success'
})
const organizationRows = computed<Record<string, unknown>[]>(() =>
  (user.value?.organizations ?? []).map((organization) => ({
    ...organization,
    verified_at: formatDate(organization.verified_at),
  })),
)
const userProjects = computed<ProjectListItem[]>(() => user.value?.projects ?? [])
const userProjectContentTypeIds = computed(
  () => new Set(userProjects.value.map((project) => project.game_content_type_id)),
)
const projectGameFilterOptions = computed(() => {
  const games = new Map<number, string>()

  for (const project of userProjects.value) {
    if (project.game_id !== null && project.game_name) {
      games.set(project.game_id, project.game_name)
    }
  }

  return [
    { value: 'all', label: 'Все игры' },
    ...[...games.entries()]
      .map(([id, name]) => ({ value: String(id), label: name }))
      .sort((firstOption, secondOption) =>
        firstOption.label.localeCompare(secondOption.label, 'ru-RU'),
      ),
  ]
})
const projectContentTypeFilterOptions = computed(() =>
  projectContentTypeOptions.value
    .filter((option) => userProjectContentTypeIds.value.has(option.id))
    .filter(
      (option) =>
        projectGameFilter.value === 'all' || String(option.game_id) === projectGameFilter.value,
    )
    .map((option) => ({
      value: String(option.id),
      label:
        projectGameFilter.value === 'all'
          ? `${option.content_type_name ?? 'Тип контента'} · ${option.game_name ?? 'Игра'}`
          : (option.content_type_name ?? 'Тип контента'),
    }))
    .sort((firstOption, secondOption) =>
      firstOption.label.localeCompare(secondOption.label, 'ru-RU'),
    ),
)
const projectFilterDimensions = computed(() =>
  projectDimensions.value.filter(
    (dimension) =>
      dimension.is_active &&
      dimension.is_filterable &&
      dimension.applies_to === 'project' &&
      projectContentTypeFilter.value !== 'all' &&
      String(dimension.game_content_type_id) === projectContentTypeFilter.value,
  ),
)
const releaseFilterDimensions = computed(() =>
  projectDimensions.value.filter(
    (dimension) =>
      dimension.is_active &&
      dimension.is_filterable &&
      dimension.applies_to === 'release' &&
      projectContentTypeFilter.value !== 'all' &&
      String(dimension.game_content_type_id) === projectContentTypeFilter.value,
  ),
)
const hasActiveProjectFilters = computed(() =>
  Boolean(
    projectSearch.value.trim() ||
    projectGameFilter.value !== 'all' ||
    projectContentTypeFilter.value !== 'all' ||
    projectStatusFilter.value !== 'all' ||
    hasSelectedFilterValues(selectedProjectFilterValueIds.value) ||
    hasSelectedFilterValues(selectedReleaseFilterValueIds.value),
  ),
)
const filteredUserProjects = computed(() =>
  userProjects.value.filter(
    (project) =>
      matchesProjectSearch(project) &&
      matchesProjectGameFilter(project) &&
      matchesProjectContentTypeFilter(project) &&
      matchesProjectStatusFilter(project) &&
      matchesProjectDimensionFilters(project) &&
      matchesReleaseDimensionFilters(project),
  ),
)
const sortedUserProjects = computed(() =>
  [...filteredUserProjects.value].sort((firstProject, secondProject) => {
    if (projectSort.value === 'title_desc') {
      return secondProject.title.localeCompare(firstProject.title, 'ru-RU')
    }

    if (projectSort.value === 'released_at') {
      return projectReleaseTimestamp(secondProject) - projectReleaseTimestamp(firstProject)
    }

    return firstProject.title.localeCompare(secondProject.title, 'ru-RU')
  }),
)
const visibleUserProjects = computed(() => sortedUserProjects.value.slice(0, projectPageSize.value))
const bannerUrl = computed(() => user.value?.banner_url || user.value?.profile?.banner_url || null)
const heroClasses = computed(() => ({
  'profile-hero': true,
  'profile-hero--with-banner': bannerUrl.value !== null,
}))
const heroStyle = computed(() =>
  bannerUrl.value === null ? undefined : { backgroundImage: `url("${bannerUrl.value}")` },
)
const deleteModalDescription = computed(
  () => `Пользователь ${title.value} будет удален. Это действие скроет его из рабочего списка.`,
)
const blockModalDescription = computed(
  () =>
    `Пользователь ${title.value} будет заблокирован и потеряет доступ к активным возможностям аккаунта.`,
)
const freezeModalDescription = computed(
  () =>
    `Пользователь ${title.value} будет заморожен и потеряет доступ к активным возможностям аккаунта.`,
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

function organizationStatusClass(color: string | null): string {
  return `organization-status--${color ?? 'gray'}`
}

function organizationInitial(name: string): string {
  return name.slice(0, 1).toUpperCase()
}

function matchesProjectSearch(project: ProjectListItem): boolean {
  const searchQuery = projectSearch.value.trim().toLocaleLowerCase('ru-RU')

  if (!searchQuery) {
    return true
  }

  return project.title.toLocaleLowerCase('ru-RU').includes(searchQuery)
}

function matchesProjectGameFilter(project: ProjectListItem): boolean {
  return projectGameFilter.value === 'all' || String(project.game_id) === projectGameFilter.value
}

function matchesProjectContentTypeFilter(project: ProjectListItem): boolean {
  return (
    projectContentTypeFilter.value === 'all' ||
    String(project.game_content_type_id) === projectContentTypeFilter.value
  )
}

function matchesProjectStatusFilter(project: ProjectListItem): boolean {
  return projectStatusFilter.value === 'all' || project.status === projectStatusFilter.value
}

function matchesProjectDimensionFilters(project: ProjectListItem): boolean {
  const projectValueIds = new Set(project.dimension_value_ids ?? [])

  return Object.values(selectedProjectFilterValueIds.value).every((valueIds) => {
    if (valueIds.length === 0) {
      return true
    }

    return valueIds.some((valueId) => projectValueIds.has(valueId))
  })
}

function matchesReleaseDimensionFilters(project: ProjectListItem): boolean {
  const releaseValueIds = new Set(projectReleaseDimensionValueIds.value[project.id] ?? [])

  return Object.values(selectedReleaseFilterValueIds.value).every((valueIds) => {
    if (valueIds.length === 0) {
      return true
    }

    return valueIds.some((valueId) => releaseValueIds.has(valueId))
  })
}

function hasSelectedFilterValues(filtersState: Record<number, number[]>): boolean {
  return Object.values(filtersState).some((valueIds) => valueIds.length > 0)
}

function sidebarFilterSelectedValueCount(filterId: number, target: 'project' | 'release'): number {
  const filtersState =
    target === 'project' ? selectedProjectFilterValueIds.value : selectedReleaseFilterValueIds.value

  return filtersState[filterId]?.length ?? 0
}

function toggleSidebarFilterValue(
  filterId: number,
  valueId: number,
  target: 'project' | 'release',
): void {
  const filtersState =
    target === 'project' ? selectedProjectFilterValueIds.value : selectedReleaseFilterValueIds.value
  const selectedValueIds = filtersState[filterId] ?? []

  filtersState[filterId] = selectedValueIds.includes(valueId)
    ? selectedValueIds.filter((selectedValueId) => selectedValueId !== valueId)
    : [...selectedValueIds, valueId]
}

function sidebarFilterValueIsSelected(
  filterId: number,
  valueId: number,
  target: 'project' | 'release',
): boolean {
  const filtersState =
    target === 'project' ? selectedProjectFilterValueIds.value : selectedReleaseFilterValueIds.value

  return filtersState[filterId]?.includes(valueId) ?? false
}

function sidebarRootValues(filter: GameDimension): GameDimensionValue[] {
  return filter.values
    .filter((value) => value.is_active && value.parent_id === null)
    .sort(compareDimensionValueOrder)
}

function sidebarChildValues(
  filter: GameDimension,
  parentValue: GameDimensionValue,
): GameDimensionValue[] {
  return filter.values
    .filter((value) => value.is_active && value.parent_id === parentValue.id)
    .sort(compareDimensionValueOrder)
}

function sidebarValueHasChildren(filter: GameDimension, value: GameDimensionValue): boolean {
  return filter.values.some(
    (childValue) => childValue.is_active && childValue.parent_id === value.id,
  )
}

function compareDimensionValueOrder(
  firstValue: GameDimensionValue,
  secondValue: GameDimensionValue,
): number {
  if (firstValue.sort_order !== secondValue.sort_order) {
    return firstValue.sort_order - secondValue.sort_order
  }

  return firstValue.name.localeCompare(secondValue.name, 'ru-RU')
}

function projectReleaseTimestamp(project: ProjectListItem): number {
  if (!project.released_at) {
    return 0
  }

  return new Date(project.released_at).getTime()
}

function projectSummary(project: ProjectListItem): string {
  return richTextToPlainText(project.summary) || 'Краткое описание пока не добавлено.'
}

function projectTags(project: ProjectListItem): string[] {
  if (!Array.isArray(project.tags)) {
    return []
  }

  return project.tags.filter((tag): tag is string => typeof tag === 'string' && tag.trim() !== '')
}

function projectUpdatedAt(project: ProjectListItem): string {
  if (!project.updated_at) {
    return 'Дата не указана'
  }

  return new Intl.DateTimeFormat('ru-RU', { dateStyle: 'short' }).format(
    new Date(project.updated_at),
  )
}

function projectStatusClass(project: ProjectListItem): string {
  const color = project.status_color ?? 'gray'

  return ['gray', 'warning', 'success', 'danger'].includes(color)
    ? `project-card__status--${color}`
    : 'project-card__status--gray'
}

function projectIsDraft(project: ProjectListItem): boolean {
  return project.status === 'draft'
}

function projectActionRoute(project: ProjectListItem): RouteLocationRaw {
  if (projectIsDraft(project)) {
    return { name: 'projects.continue', params: { id: String(project.id) } }
  }

  return { name: 'projects.edit', params: { id: String(project.id) } }
}

function projectActionLabel(project: ProjectListItem): string {
  return projectIsDraft(project) ? 'Продолжить создание' : 'Редактировать проект'
}

function richTextToPlainText(value: unknown): string {
  if (Array.isArray(value)) {
    return value.map(richTextToPlainText).filter(Boolean).join(' ')
  }

  if (!value || typeof value !== 'object') {
    return ''
  }

  if ('text' in value && typeof value.text === 'string') {
    return value.text
  }

  return 'content' in value ? richTextToPlainText(value.content) : ''
}

function resetProjectFilters(): void {
  projectSearch.value = ''
  projectGameFilter.value = 'all'
  projectContentTypeFilter.value = 'all'
  projectStatusFilter.value = 'all'
  selectedProjectFilterValueIds.value = {}
  selectedReleaseFilterValueIds.value = {}
}

function toggleProjectDisplayMode(): void {
  projectDisplayMode.value = projectDisplayMode.value === 'cards' ? 'list' : 'cards'
}

function toggleMoreMenu(): void {
  isMoreOpen.value = !isMoreOpen.value
}

function editProfileStub(): void {
  isMoreOpen.value = false
  message.value = 'Редактирование профиля пока не подключено.'
}

async function submitProfileLogout(): Promise<void> {
  isLoggingOut.value = true

  try {
    await logout()
    await router.push({ name: 'login' })
  } finally {
    isLoggingOut.value = false
    isMoreOpen.value = false
  }
}

async function loadUser(): Promise<void> {
  isLoading.value = true
  message.value = ''

  try {
    const userId = isProfileMode.value ? currentUser.value?.id : route.params.id

    if (!userId) {
      await router.replace({ name: 'login' })

      return
    }

    user.value = await fetchUser(String(userId))
    route.meta.breadcrumbLabel = title.value
    await loadProjectActivityFilters()
  } catch {
    message.value = 'Не удалось загрузить пользователя.'
  } finally {
    isLoading.value = false
  }
}

async function loadProjectActivityFilters(): Promise<void> {
  const projects = user.value?.projects ?? []

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
  const relevantContentTypes = [...userProjectContentTypeIds.value]
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

async function runUserAction(
  action: () => Promise<UserDetail | { message: string }>,
  notification?: UserActionNotification,
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

    if (notification) {
      pushUserActionNotification(notification)
    }
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

  void runUserAction(() => blockUser(userId), 'blocked')

  isBlockModalOpen.value = false
}

function unblock(): void {
  if (!user.value) {
    return
  }

  const userId = user.value.id

  void runUserAction(() => unblockUser(userId), 'unblocked')
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

  void runUserAction(() => freezeUser(userId), 'frozen')

  isFreezeModalOpen.value = false
}

function unfreeze(): void {
  if (!user.value) {
    return
  }

  const userId = user.value.id

  void runUserAction(() => unfreezeUser(userId), 'unfrozen')
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
    pushUserActionNotification('deleted')
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

watch(projectGameFilter, () => {
  projectContentTypeFilter.value = 'all'
})

watch(projectContentTypeFilter, () => {
  selectedProjectFilterValueIds.value = {}
  selectedReleaseFilterValueIds.value = {}
})
</script>

<template>
  <AppShell>
    <section class="profile-page">
      <p v-if="message" class="profile-page__message">
        {{ message }}
      </p>

      <div v-if="isLoading" class="profile-card profile-card--loading">Загрузка...</div>

      <template v-else-if="user">
        <header :class="heroClasses" :style="heroStyle">
          <div class="profile-hero__avatar" aria-hidden="true">
            <img v-if="user.avatar_url" :src="user.avatar_url" alt="" />
            <span v-else>{{ title.slice(0, 1).toUpperCase() }}</span>
          </div>

          <div class="profile-hero__content">
            <div class="profile-hero__title-row">
              <h2 class="profile-hero__title">
                {{ title }}
              </h2>
            </div>

            <p class="profile-hero__username">
              @{{ user.username }}
              <span aria-hidden="true">•</span>
              {{ user.email }}
            </p>

            <div class="profile-hero__meta">
              <span
                ><Clock3 :size="14" :stroke-width="1.9" aria-hidden="true" />Создан:
                {{ formatDate(user.created_at) }}</span
              >
              <span
                ><Clock3 :size="14" :stroke-width="1.9" aria-hidden="true" />Был онлайн:
                {{ formatDate(user.last_seen_at) }}</span
              >
              <span v-if="user.email_verified_at"
                ><Check :size="14" :stroke-width="2" aria-hidden="true" />Почта подтверждена</span
              >
            </div>
          </div>

          <div class="profile-hero__actions">
            <RouterLink
              class="button button--secondary profile-hero__action"
              :to="backTarget"
              :aria-label="backLabel"
            >
              <ArrowLeft :size="16" :stroke-width="1.9" aria-hidden="true" />
              Назад
            </RouterLink>

            <button
              class="button button--secondary profile-preview__more"
              type="button"
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
              <template v-if="isProfileMode">
                <button type="button" role="menuitem" @click="editProfileStub">
                  <Pencil :size="16" :stroke-width="1.9" aria-hidden="true" />
                  <span>Редактировать</span>
                </button>

                <button
                  class="profile-preview__more-menu-danger"
                  type="button"
                  role="menuitem"
                  :disabled="isLoggingOut"
                  @click="submitProfileLogout"
                >
                  <LogOut :size="16" :stroke-width="1.9" aria-hidden="true" />
                  <span>{{ isLoggingOut ? 'Выходим...' : 'Выйти из аккаунта' }}</span>
                </button>
              </template>

              <template v-else>
                <button
                  v-if="user.status === 'active'"
                  type="button"
                  role="menuitem"
                  :disabled="isActionLoading"
                  @click="freeze"
                >
                  <Snowflake :size="16" :stroke-width="1.9" aria-hidden="true" />
                  <span>Заморозить</span>
                </button>

                <button
                  v-if="user.status === 'suspended'"
                  type="button"
                  role="menuitem"
                  :disabled="isActionLoading"
                  @click="unfreeze"
                >
                  <RotateCcw :size="16" :stroke-width="1.9" aria-hidden="true" />
                  <span>Разморозить</span>
                </button>

                <button
                  v-if="user.status !== 'blocked'"
                  type="button"
                  role="menuitem"
                  :disabled="isActionLoading"
                  @click="block"
                >
                  <Ban :size="16" :stroke-width="1.9" aria-hidden="true" />
                  <span>Заблокировать</span>
                </button>

                <button
                  v-if="user.status === 'blocked'"
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
              </template>
            </div>
          </div>
        </header>

        <div class="profile-tabs" role="tablist" aria-label="Разделы пользователя">
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
                    <UserRound :size="18" :stroke-width="1.9" aria-hidden="true" />
                    Профиль
                  </h3>
                </header>

                <template v-if="user.profile">
                  <div class="profile-overview">
                    <div class="profile-field">
                      <span class="profile-field__label">Отображаемое имя</span>
                      <strong>{{ user.profile.display_name || user.username }}</strong>
                    </div>

                    <div class="profile-field profile-field--wide">
                      <span class="profile-field__label">О себе</span>
                      <RichTextRenderer
                        class="profile-field__text"
                        :value="user.profile.bio"
                        empty-text="Описание профиля пока не заполнено."
                      />
                    </div>
                  </div>

                  <div class="profile-info-grid">
                    <div class="profile-info">
                      <Calendar :size="19" :stroke-width="1.9" aria-hidden="true" />
                      <span class="profile-info__label">Дата рождения</span>
                      <strong class="profile-info__value">
                        {{ formatDate(user.profile.birthday, false) }}
                      </strong>
                    </div>

                    <div class="profile-info">
                      <Globe2
                        class="profile-info__icon"
                        :size="19"
                        :stroke-width="1.9"
                        aria-hidden="true"
                      />
                      <span class="profile-info__label">Публичный профиль</span>
                      <strong class="profile-info__value">
                        {{ formatBoolean(user.profile.is_public) }}
                      </strong>
                    </div>

                    <div class="profile-info">
                      <Circle
                        class="profile-info__icon profile-info__icon--success"
                        :size="19"
                        :stroke-width="1.9"
                        aria-hidden="true"
                      />
                      <span class="profile-info__label">Показывать онлайн</span>
                      <strong class="profile-info__value">
                        {{ formatBoolean(user.profile.show_online_status) }}
                      </strong>
                    </div>

                    <div class="profile-info">
                      <Clock3 :size="19" :stroke-width="1.9" aria-hidden="true" />
                      <span class="profile-info__label">Показывать был онлайн</span>
                      <strong class="profile-info__value">
                        {{ formatBoolean(user.profile.show_last_seen_at) }}
                      </strong>
                    </div>

                    <div class="profile-info">
                      <Link2
                        class="profile-info__icon"
                        :size="19"
                        :stroke-width="1.9"
                        aria-hidden="true"
                      />
                      <span class="profile-info__label">Сайты</span>
                      <strong class="profile-info__value">
                        {{ formatJsonValue(user.profile.website_urls) }}
                      </strong>
                    </div>
                  </div>
                </template>

                <p v-else class="profile-card__empty">Профиль не заполнен.</p>
              </section>

              <section class="profile-card profile-card--main">
                <header class="profile-card__header">
                  <h3 class="profile-card__title">
                    <ShieldCheck :size="18" :stroke-width="1.9" aria-hidden="true" />
                    Роли и доступ
                  </h3>
                </header>

                <p class="profile-card__empty">Роли и права доступа пока не подключены.</p>
              </section>
            </template>

            <template v-else-if="activeTab === 'organizations'">
              <div class="data-table-panel profile-organizations-table">
                <DataTable
                  :columns="organizationColumns"
                  :rows="organizationRows"
                  empty-text="Не состоит в организациях"
                >
                  <template #cell-name="{ row, value }">
                    <div class="organization-name">
                      <span class="organization-avatar" aria-hidden="true">
                        {{ organizationInitial(String(value)) }}
                      </span>
                      <strong>{{ value }}</strong>
                    </div>
                  </template>

                  <template #cell-status_label="{ row, value }">
                    <span
                      class="organization-status"
                      :class="organizationStatusClass(String(row.status_color || 'gray'))"
                    >
                      {{ value || '—' }}
                    </span>
                  </template>

                  <template #cell-actions="{ row }">
                    <RouterLink
                      class="organization-link"
                      :to="{ name: 'org.show', params: { id: String(row.id) } }"
                      aria-label="Открыть организацию"
                      title="Открыть организацию"
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
                :projects="userProjects"
                :content-type-options="projectContentTypeOptions"
                :dimensions="projectDimensions"
                :release-dimension-value-ids="projectReleaseDimensionValueIds"
                restrict-content-types-to-projects
                empty-text="Проекты не найдены."
              />
            </template>
          </div>

          <aside class="profile-side-column">
            <section class="profile-card profile-card--side">
              <header class="profile-card__header">
                <h3 class="profile-card__title">
                  <UserRound :size="18" :stroke-width="1.9" aria-hidden="true" />
                  Аккаунт
                </h3>
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
                  <span>Почта подтверждена</span>
                  <strong>{{ formatDate(user.email_verified_at) }}</strong>
                </div>

                <div class="account-list__item">
                  <span>Создан</span>
                  <strong>{{ formatDate(user.created_at) }}</strong>
                </div>

                <div class="account-list__item">
                  <span>Был онлайн</span>
                  <strong>{{ formatDate(user.last_seen_at) }}</strong>
                </div>
              </div>
            </section>

            <section
              class="profile-card profile-card--side profile-account-status"
              :class="`profile-account-status--${accountStatusVariant}`"
            >
              <Ban
                v-if="user.status === 'blocked'"
                :size="54"
                :stroke-width="1.7"
                aria-hidden="true"
              />
              <Snowflake
                v-else-if="user.status === 'suspended'"
                :size="54"
                :stroke-width="1.7"
                aria-hidden="true"
              />
              <ShieldCheck v-else :size="54" :stroke-width="1.7" aria-hidden="true" />
              <strong>{{ accountStatusTitle }}</strong>
              <p>{{ accountStatusText }}</p>
            </section>
          </aside>
        </div>
      </template>
    </section>

    <BlockModal
      :open="isBlockModalOpen"
      title="Заблокировать пользователя?"
      :description="blockModalDescription"
      tone="danger"
      :loading="isActionLoading"
      @cancel="closeBlockModal"
      @confirm="confirmBlock"
    />

    <BlockModal
      :open="isFreezeModalOpen"
      title="Заморозить пользователя?"
      :description="freezeModalDescription"
      icon="snowflake"
      tone="info"
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

.profile-main-column {
  display: grid;
  gap: 20px;
  min-width: 0;
}

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
  letter-spacing: -0.02em;
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
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 10px;
}

.profile-info {
  display: flex;
  flex-direction: column;
  gap: 8px;

  padding: 14px;

  background: var(--color-bg-muted);
  border: 1px solid var(--color-border-soft);
  border-radius: var(--radius-md);
}

.profile-info svg {
  color: var(--color-primary);
}

.profile-info .profile-info__icon--success {
  color: var(--color-success);
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
  width: 40px;
  height: 40px;
  color: var(--color-primary);
  background: color-mix(in srgb, var(--color-primary) 14%, var(--color-surface));
  border: 1px solid color-mix(in srgb, var(--color-primary) 26%, transparent);
  border-radius: var(--radius-sm);
  font-size: 15px;
  font-weight: 850;
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

  .profile-overview {
    grid-template-columns: 1fr;
  }

  .profile-info-grid {
    grid-template-columns: 1fr;
  }

  .profile-tabs {
    overflow-x: auto;
  }
}
</style>
