<script setup lang="ts">
import {
  Activity,
  ArrowLeft,
  Box,
  CalendarDays,
  Check,
  ChevronDown,
  ChevronLeft,
  ChevronRight,
  Columns3,
  Download,
  ExternalLink,
  FileText,
  Gamepad2,
  Link2,
  MoreHorizontal,
  Pencil,
  RotateCcw,
  Tag,
  Copy,
  Trash2,
  UserRound,
} from '@lucide/vue'
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppShell from '@/components/layout/AppShell.vue'
import DeleteModal from '@/components/ui/DeleteModal.vue'
import OverflowBadgeRow from '@/components/ui/OverflowBadgeRow.vue'
import RichTextRenderer from '@/components/ui/RichTextRenderer.vue'
import SearchField from '@/components/ui/SearchField.vue'
import type { DataColumn } from '@/shared/data/table'
import { fetchGameDimensions, type GameDimension } from '@/shared/games/games'
import {
  deleteProject as removeProject,
  fetchProject,
  fetchProjectMembers,
  fetchProjectReleases,
  fetchProjectReleaseFilters,
  updateProjectDraft,
  type ProjectDetail,
  type ProjectMember,
  type ProjectPublicationStatus,
  type ProjectRelease,
} from '@/shared/projects/projects'
import ProjectReleasesTable from '@/views/projects/components/ProjectReleasesTable.vue'

type ReleaseFilterState = {
  search: string
  type: '' | 'alpha' | 'beta' | 'release'
  dimensionValueIds: Record<number, string>
}

type OverviewFilterBlock = {
  id: string
  name: string
  values: string[]
}

type OverviewAuthor = {
  id: string
  name: string
  roleLabel: string
  avatarUrl: string | null
}

const route = useRoute()
const router = useRouter()
const project = ref<ProjectDetail | null>(null)
const projectFilters = ref<GameDimension[]>([])
const releaseFilters = ref<GameDimension[]>([])
const projectReleases = ref<ProjectRelease[]>([])
const projectMembers = ref<ProjectMember[]>([])
const selectedProjectRelease = ref<ProjectRelease | null>(null)
const isLoading = ref(true)
const projectReleasesLoading = ref(false)
const errorMessage = ref('')
const projectReleasesError = ref('')
const isDeleteModalOpen = ref(false)
const isDeletingProject = ref(false)
const deleteProjectError = ref('')

const projectId = computed(() => String(route.params.id))
const summaryText = computed(() => richTextToPlainText(project.value?.summary))
const descriptionText = computed(() => richTextToPlainText(project.value?.description))
const hasSummary = computed(() => summaryText.value !== '')
const hasDescription = computed(() => descriptionText.value !== '')
const projectTags = computed(() => toStringArray(project.value?.tags))
const projectLinks = computed(() => toStringArray(project.value?.website_urls))
const projectScreenshots = computed(() => {
  if (!project.value) {
    return []
  }

  if (project.value.screenshots.length) {
    return project.value.screenshots
  }

  return project.value.screenshot_urls.map((url, index) => ({
    id: index,
    url,
    order: index + 1,
  }))
})
const activeTab = ref<'overview' | 'releases' | 'screenshots'>('overview')
const isMoreOpen = ref(false)
const isPublicationStatusSaving = ref(false)
const publicationStatusError = ref('')
const releaseCurrentPage = ref(1)
const releasePageSize = 10
const releaseFilterState = reactive<ReleaseFilterState>({
  search: '',
  type: '',
  dimensionValueIds: {},
})
const releaseColumns = ref<DataColumn[]>([
  { key: 'type', label: 'Тип', visible: true },
  { key: 'title', label: 'Название', visible: true },
  { key: 'released_at', label: 'Дата релиза', visible: false },
])
const tabs = [
  { value: 'overview', label: 'Обзор' },
  { value: 'releases', label: 'Релизы' },
  { value: 'screenshots', label: 'Скриншоты' },
] as const
const releaseTypeOptions = [
  { value: 'alpha', label: 'Альфа' },
  { value: 'beta', label: 'Бета' },
  { value: 'release', label: 'Релиз' },
] as const
const publicationStatusOptions: Array<{
  value: ProjectPublicationStatus
  label: string
  description: string
}> = [
  { value: 'public', label: 'Публичный', description: 'Проект доступен всем пользователям.' },
  { value: 'private', label: 'Приватный', description: 'Проект виден только вам и участникам команды.' },
  { value: 'url_only', label: 'Только по ссылке', description: 'Проект доступен только пользователям с прямой ссылкой.' },
  { value: 'archived', label: 'В архиве', description: 'Проект скрыт из обычных списков и сохранён в архиве.' },
]
const hasActiveReleaseFilters = computed(() =>
  Boolean(
    releaseFilterState.search.trim() ||
      releaseFilterState.type ||
      Object.values(releaseFilterState.dimensionValueIds).some(Boolean),
  ),
)
const filteredProjectReleases = computed(() =>
  projectReleases.value.filter(
    (release) =>
      release.status === 'published' &&
      matchesReleaseSearch(release) &&
      matchesReleaseType(release) &&
      matchesReleaseDimensions(release),
  ),
)
const sortedProjectReleases = computed(() =>
  [...filteredProjectReleases.value].sort(
    (firstRelease, secondRelease) => releaseTimestamp(secondRelease) - releaseTimestamp(firstRelease),
  ),
)
const releaseTotalPages = computed(() =>
  Math.max(1, Math.ceil(sortedProjectReleases.value.length / releasePageSize)),
)
const releaseEffectivePage = computed(() =>
  Math.min(releaseCurrentPage.value, releaseTotalPages.value),
)
const releasePageStart = computed(() => (releaseEffectivePage.value - 1) * releasePageSize)
const visibleProjectReleases = computed(() =>
  sortedProjectReleases.value.slice(releasePageStart.value, releasePageStart.value + releasePageSize),
)
const visibleReleaseColumns = computed(() => releaseColumns.value.filter((column) => column.visible))
const publishedReleasesCount = computed(
  () => projectReleases.value.filter((release) => release.status === 'published').length,
)
const publishedProjectReleases = computed(() =>
  projectReleases.value.filter((release) => release.status === 'published'),
)
const latestReleaseDate = computed(() => {
  const latestTimestamp = publishedProjectReleases.value.reduce((latest, release) => {
    const timestamp = releaseTimestamp(release)

    return timestamp > latest ? timestamp : latest
  }, 0)

  return latestTimestamp > 0 ? new Date(latestTimestamp).toISOString() : null
})
const overviewFilterBlocks = computed<OverviewFilterBlock[]>(() => [
  ...projectFilters.value.map((filter) => ({
    id: `project:${filter.id}`,
    name: filter.name,
    values: selectedDimensionValueNames(filter, project.value?.dimension_value_ids ?? []),
  })),
  ...releaseFilters.value.map((filter) => ({
    id: `release:${filter.id}`,
    name: filter.name,
    values: selectedDimensionValueNames(filter, publishedReleaseDimensionValueIds.value),
  })),
])
const visibleOverviewFilterBlocks = computed(() =>
  overviewFilterBlocks.value.filter((filter) => filter.values.length > 0),
)
const publishedReleaseDimensionValueIds = computed(() => {
  const selectedIds = new Set<number>()

  for (const release of publishedProjectReleases.value) {
    for (const valueId of release.dimension_value_ids ?? []) {
      selectedIds.add(valueId)
    }
  }

  return [...selectedIds]
})
const isOrganizationOwner = computed(() => project.value?.ownerable_type === 'App\\Models\\Organization\\Organization')
const overviewAuthors = computed<OverviewAuthor[]>(() => {
  if (!project.value) {
    return []
  }

  if (isOrganizationOwner.value) {
    const organizationOwners = projectMembers.value
      .filter((member) => member.access_source === 'organization' && member.role === 'owner')
      .map(toOverviewAuthor)

    return organizationOwners.length > 0 ? organizationOwners : [projectOwnerAuthor(project.value)]
  }

  const members = projectMembers.value
    .filter((member) => member.status === 'active')
    .map(toOverviewAuthor)
  const owner = projectOwnerAuthor(project.value)
  const membersWithoutOwner = members.filter((member) => member.name !== owner.name)

  return [owner, ...membersWithoutOwner]
})
const releasePaginationVisible = computed(() => sortedProjectReleases.value.length > releasePageSize)
const releasePaginationLabel = computed(() => {
  if (sortedProjectReleases.value.length === 0) {
    return 'Нет релизов'
  }

  const from = releasePageStart.value + 1
  const to = Math.min(releasePageStart.value + releasePageSize, sortedProjectReleases.value.length)

  return `${from}-${to} из ${sortedProjectReleases.value.length}`
})
const releaseVisiblePages = computed(() => {
  const firstPage = Math.max(1, releaseEffectivePage.value - 2)
  const lastPage = Math.min(releaseTotalPages.value, firstPage + 4)

  return Array.from({ length: lastPage - firstPage + 1 }, (_, index) => firstPage + index)
})
const publicationStatusDescription = computed(() => {
  return publicationStatusOptions.find((option) => option.value === project.value?.publication_status)?.description ?? ''
})
const deleteModalDescription = computed(() => {
  const title = project.value?.title ?? 'проект'

  return `Проект «${title}», его релизы и файлы будут удалены без возможности восстановления.`
})

function richTextToPlainText(value: unknown): string {
  if (Array.isArray(value)) {
    return value.map(richTextToPlainText).filter(Boolean).join(' ')
  }

  if (!value || typeof value !== 'object') {
    return typeof value === 'string' ? value : ''
  }

  if ('text' in value && typeof value.text === 'string') {
    return value.text
  }

  return 'content' in value ? richTextToPlainText(value.content) : ''
}

function toStringArray(value: unknown): string[] {
  if (!Array.isArray(value)) {
    return []
  }

  return value.filter((item): item is string => typeof item === 'string' && item.trim() !== '')
}

function formatDate(value: string | null | undefined): string {
  if (!value) {
    return '—'
  }

  return new Intl.DateTimeFormat('ru-RU', { dateStyle: 'medium' }).format(new Date(value))
}

function projectStatusClass(): string {
  const color = project.value?.status_color ?? 'gray'

  return ['gray', 'warning', 'success', 'danger'].includes(color)
    ? `project-preview__status--${color}`
    : 'project-preview__status--gray'
}

function selectedDimensionValueNames(dimension: GameDimension, selectedValueIds: number[]): string[] {
  const selectedIds = new Set(selectedValueIds)

  return releaseFilterValueOptions(dimension)
    .filter((value) => selectedIds.has(value.id))
    .map((value) => value.name)
}

function projectOwnerAuthor(projectDetail: ProjectDetail): OverviewAuthor {
  return {
    id: `owner:${projectDetail.ownerable_type}:${projectDetail.ownerable_id}`,
    name: projectDetail.owner_name ?? 'Автор проекта',
    roleLabel: 'Владелец',
    avatarUrl: null,
  }
}

function toOverviewAuthor(member: ProjectMember): OverviewAuthor {
  return {
    id: `member:${member.id}`,
    name: member.display_name || member.username || 'Участник проекта',
    roleLabel: member.role_label ?? 'Участник',
    avatarUrl: member.avatar_url,
  }
}

async function loadProject(): Promise<void> {
  isLoading.value = true
  errorMessage.value = ''

  try {
    const [projectResponse, filtersResponse] = await Promise.all([
      fetchProject(projectId.value),
      fetchProjectReleaseFilters(projectId.value),
    ])
    project.value = projectResponse
    releaseFilters.value = filtersResponse.data.map((dimension) => ({
      ...dimension,
      values: dimension.values.filter((value) => value.is_active),
    }))
    releaseFilterState.dimensionValueIds = Object.fromEntries(
      releaseFilters.value.map((filter) => [
        filter.id,
        releaseFilterState.dimensionValueIds[filter.id] ?? '',
      ]),
    )
    syncReleaseColumns()
    await Promise.all([loadProjectReleases(), loadProjectFilters(), loadProjectMembers()])
  } catch {
    errorMessage.value = 'Не удалось загрузить проект.'
  } finally {
    isLoading.value = false
  }
}

function goBack(): void {
  void router.push({ name: 'projects.index' })
}

function toggleMoreMenu(): void {
  isMoreOpen.value = !isMoreOpen.value
}

function openDeleteModal(): void {
  if (!project.value?.can_delete) {
    return
  }

  isMoreOpen.value = false
  deleteProjectError.value = ''
  isDeleteModalOpen.value = true
}

function closeDeleteModal(): void {
  if (isDeletingProject.value) {
    return
  }

  isDeleteModalOpen.value = false
}

function toggleReleaseColumn(key: string): void {
  releaseColumns.value = releaseColumns.value.map((column) =>
    column.key === key ? { ...column, visible: !column.visible } : column,
  )
}

function resetReleaseFilters(): void {
  releaseFilterState.search = ''
  releaseFilterState.type = ''
  releaseCurrentPage.value = 1
  releaseFilterState.dimensionValueIds = Object.fromEntries(
    releaseFilters.value.map((filter) => [filter.id, '']),
  )
}

function setReleasePage(page: number): void {
  releaseCurrentPage.value = Math.min(Math.max(page, 1), releaseTotalPages.value)
}

function openReleasePage(release: ProjectRelease): void {
  selectedProjectRelease.value = release
}

function closeReleasePage(): void {
  selectedProjectRelease.value = null
}

function syncReleaseColumns(): void {
  const existingColumnsByKey = new Map(releaseColumns.value.map((column) => [column.key, column]))
  const baseColumns: DataColumn[] = [
    existingColumnsByKey.get('type') ?? { key: 'type', label: 'Тип', visible: true },
    existingColumnsByKey.get('title') ?? { key: 'title', label: 'Название', visible: true },
  ]
  const filterColumns = releaseFilters.value.map((filter) => {
    const key = `filter:${filter.id}`

    return existingColumnsByKey.get(key) ?? { key, label: filter.name, visible: true }
  })
  const releasedAtColumn = existingColumnsByKey.get('released_at') ?? {
    key: 'released_at',
    label: 'Дата релиза',
    visible: false,
  }

  releaseColumns.value = [...baseColumns, ...filterColumns, releasedAtColumn]
}

async function loadProjectReleases(): Promise<void> {
  if (!project.value) {
    return
  }

  projectReleasesLoading.value = true
  projectReleasesError.value = ''

  try {
    const response = await fetchProjectReleases(project.value.id)

    projectReleases.value = response.data
  } catch {
    projectReleases.value = []
    projectReleasesError.value = 'Не удалось загрузить релизы проекта.'
  } finally {
    projectReleasesLoading.value = false
  }
}

async function loadProjectFilters(): Promise<void> {
  if (!project.value?.game_id) {
    projectFilters.value = []

    return
  }

  const response = await fetchGameDimensions(project.value.game_id, project.value.game_content_type_id)

  projectFilters.value = response.data
    .filter((dimension) => dimension.applies_to === 'project' && dimension.is_active && dimension.is_filterable)
    .map((dimension) => ({
      ...dimension,
      values: dimension.values.filter((value) => value.is_active),
    }))
}

async function loadProjectMembers(): Promise<void> {
  if (!project.value) {
    return
  }

  try {
    const response = await fetchProjectMembers(project.value.id)

    projectMembers.value = response.data
  } catch {
    projectMembers.value = []
  }
}

function releaseFilterValueOptions(dimension: GameDimension): GameDimension['values'] {
  const valuesByParentId = new Map<number | null, GameDimension['values']>()

  for (const value of dimension.values) {
    const values = valuesByParentId.get(value.parent_id) ?? []

    values.push(value)
    valuesByParentId.set(value.parent_id, values)
  }

  const orderedValues: GameDimension['values'] = []
  const appendValues = (parentId: number | null): void => {
    for (const value of valuesByParentId.get(parentId) ?? []) {
      orderedValues.push(value)
      appendValues(value.id)
    }
  }

  appendValues(null)

  return orderedValues
}

function matchesReleaseSearch(release: ProjectRelease): boolean {
  const searchQuery = releaseFilterState.search.trim().toLocaleLowerCase('ru-RU')

  if (!searchQuery) {
    return true
  }

  return release.title.toLocaleLowerCase('ru-RU').includes(searchQuery)
}

function matchesReleaseType(release: ProjectRelease): boolean {
  return releaseFilterState.type === '' || release.type === releaseFilterState.type
}

function matchesReleaseDimensions(release: ProjectRelease): boolean {
  const selectedEntries = Object.entries(releaseFilterState.dimensionValueIds).filter(
    ([, valueId]) => valueId !== '',
  )

  if (selectedEntries.length === 0) {
    return true
  }

  const releaseDimensionValueIds = new Set(release.dimension_value_ids ?? [])

  return selectedEntries.every(([, valueId]) => releaseDimensionValueIds.has(Number(valueId)))
}

function releaseFilterValueLabel(release: ProjectRelease, filterId: number): string {
  const filter = releaseFilters.value.find((item) => item.id === filterId)

  if (!filter) {
    return '—'
  }

  const selectedIds = new Set(release.dimension_value_ids ?? [])
  const selectedValues = filter.values.filter((value) => selectedIds.has(value.id))

  if (selectedValues.length === 0) {
    return '—'
  }

  return selectedValues.map((value) => value.name).join(', ')
}

function releaseFilterSummary(release: ProjectRelease): string {
  const filterLabels = releaseFilters.value
    .map((filter) => releaseFilterValueLabel(release, filter.id))
    .filter((label) => label !== '—')

  if (filterLabels.length === 0) {
    return 'Фильтры не указаны'
  }

  return filterLabels.join(' · ')
}

function releaseChangelogText(release: ProjectRelease): string {
  return richTextToPlainText(release.changelog).trim()
}

function formatFileSize(value: number | null): string {
  if (!value) {
    return 'Размер неизвестен'
  }

  const units = ['B', 'KB', 'MB', 'GB']
  let size = value
  let unitIndex = 0

  while (size >= 1024 && unitIndex < units.length - 1) {
    size /= 1024
    unitIndex += 1
  }

  return `${size >= 10 || unitIndex === 0 ? Math.round(size) : size.toFixed(1)} ${units[unitIndex]}`
}

function releaseTimestamp(release: ProjectRelease): number {
  return release.released_at ? new Date(release.released_at).getTime() : 0
}

async function copyProjectLink(): Promise<void> {
  await navigator.clipboard?.writeText(window.location.href)
  isMoreOpen.value = false
}

async function changePublicationStatus(event: Event): Promise<void> {
  if (!project.value) {
    return
  }

  const select = event.target as HTMLSelectElement
  const previousStatus = project.value.publication_status
  const nextStatus = select.value as ProjectPublicationStatus

  isPublicationStatusSaving.value = true
  publicationStatusError.value = ''

  try {
    const updatedProject = await updateProjectDraft(project.value.id, {
      percentageComplete: project.value.percentage_complete,
      publicationStatus: nextStatus,
    })

    project.value = updatedProject
  } catch {
    select.value = previousStatus
    publicationStatusError.value = 'Не удалось изменить статус.'
  } finally {
    isPublicationStatusSaving.value = false
  }
}

async function confirmDeleteProject(): Promise<void> {
  if (!project.value) {
    return
  }

  isDeletingProject.value = true
  deleteProjectError.value = ''

  try {
    await removeProject(project.value.id)
    await router.push({ name: 'projects.index' })
  } catch {
    deleteProjectError.value = 'Не удалось удалить проект.'
  } finally {
    isDeletingProject.value = false
    isDeleteModalOpen.value = false
  }
}

onMounted(() => {
  void loadProject()
})
</script>

<template>
  <AppShell>
    <section class="project-preview" aria-label="Просмотр проекта">
      <div v-if="isLoading" class="project-preview__state">Загрузка проекта...</div>

      <div v-else-if="errorMessage" class="project-preview__state project-preview__state--error">
        <p>{{ errorMessage }}</p>
        <button class="button button--secondary" type="button" @click="loadProject">
          Повторить
        </button>
      </div>

      <template v-else-if="project">
        <header class="project-preview__header">
          <div class="project-preview__breadcrumbs">
            <button type="button" @click="goBack">
              <ArrowLeft :size="16" aria-hidden="true" />
              <span>Проекты</span>
            </button>
            <span aria-hidden="true">/</span>
            <span>{{ project.title }}</span>
          </div>

          <div class="project-preview__heading-row">
            <div>
              <h1>{{ project.title }}</h1>
              <div class="project-preview__meta">
                <span v-if="project.game_name"><Gamepad2 :size="15" aria-hidden="true" />{{ project.game_name }}</span>
                <span v-if="project.content_type_name">{{ project.content_type_name }}</span>
                <span v-if="project.status_label" class="project-preview__status" :class="projectStatusClass()">
                  <Check :size="14" aria-hidden="true" />{{ project.status_label }}
                </span>
              </div>
            </div>

            <div class="project-preview__actions">
              <RouterLink
                v-if="project.can_delete"
                class="button button--secondary project-preview__edit"
                :to="{
                  name: 'projects.edit',
                  params: { id: String(project.id) },
                }"
              >
                <Pencil :size="16" aria-hidden="true" />
                Редактировать
              </RouterLink>
              <button
                class="button button--secondary project-preview__more"
                type="button"
                :aria-expanded="isMoreOpen"
                aria-haspopup="menu"
                @click="toggleMoreMenu"
              >
                <MoreHorizontal :size="18" aria-hidden="true" />
                <span>Ещё</span>
                <ChevronDown
                  class="project-preview__more-chevron"
                  :class="{ 'project-preview__more-chevron--open': isMoreOpen }"
                  :size="16"
                  aria-hidden="true"
                />
              </button>
              <div v-if="isMoreOpen" class="project-preview__more-menu" role="menu">
                <button type="button" role="menuitem" @click="copyProjectLink">
                  <Copy :size="15" aria-hidden="true"/>
                  Скопировать ссылку
                </button>
                <button
                  v-if="project.can_delete"
                  class="project-preview__more-menu-danger"
                  type="button"
                  role="menuitem"
                  :disabled="isDeletingProject"
                  @click="openDeleteModal"
                >
                  <Trash2 :size="15" aria-hidden="true" />
                  <span>Удалить</span>
                </button>
              </div>
            </div>
          </div>
          <p v-if="deleteProjectError" class="project-preview__action-error">{{ deleteProjectError }}</p>
        </header>

        <section class="project-preview__hero">
          <div class="project-preview__hero-media">
            <img v-if="project.logo_url" :src="project.logo_url" :alt="project.title" />
            <span v-else>{{ project.title.slice(0, 1).toUpperCase() }}</span>
          </div>

          <div class="project-preview__hero-content">
            <div class="project-preview__hero-copy">
              <p v-if="hasSummary" class="project-preview__summary">{{ summaryText }}</p>
              <p v-else class="project-preview__muted">Описание проекта пока не добавлено.</p>
            </div>
            <div v-if="projectTags.length" class="project-preview__tags">
              <span v-for="tag in projectTags" :key="tag">{{ tag }}</span>
            </div>
            <div class="project-preview__hero-meta">
              <span v-if="project.owner_name" class="project-preview__hero-author">
                <UserRound :size="15" aria-hidden="true" />{{ project.owner_name }}
              </span>
              <span class="project-preview__hero-slug">/{{ project.slug }}</span>
              <span v-if="project.publication_status_label" class="project-preview__publication-status">
                {{ project.publication_status_label }}
              </span>
            </div>
          </div>

          <dl class="project-preview__hero-facts">
            <div>
              <Box :size="22" aria-hidden="true" />
              <dd>{{ project.releases_count }}</dd>
              <dt>Релизы</dt>
            </div>
            <div>
              <CalendarDays :size="22" aria-hidden="true" />
              <dd>{{ formatDate(project.created_at) }}</dd>
              <dt>Создан</dt>
            </div>
          </dl>
        </section>

        <div class="project-tabs" role="tablist" aria-label="Разделы проекта">
          <button
            v-for="tab in tabs"
            :key="tab.value"
            class="project-tab"
            :class="{ 'project-tab--active': activeTab === tab.value }"
            type="button"
            role="tab"
            :aria-selected="activeTab === tab.value"
            @click="activeTab = tab.value"
          >
            {{ tab.label }}
          </button>
        </div>

        <div class="project-preview__layout">
          <main class="project-preview__main">
            <section v-if="activeTab === 'releases'" class="project-preview__releases">
              <section
                v-if="!selectedProjectRelease"
                class="game-filters project-releases-filters"
                aria-label="Фильтры релизов"
              >
                <div class="game-filter-top project-releases-filters__top">
                  <SearchField
                    v-model="releaseFilterState.search"
                    placeholder="Поиск по релизам"
                  />

                  <details class="data-toolbar__columns game-filter-columns">
                    <summary class="game-filter-advanced">
                      <Columns3 :size="18" :stroke-width="1.9" aria-hidden="true" />
                      <span>Колонки</span>
                    </summary>

                    <div class="data-toolbar__columns-menu">
                      <label
                        v-for="column in releaseColumns"
                        :key="column.key"
                        class="data-toolbar__column-option"
                      >
                        <input
                          class="checkbox-control"
                          type="checkbox"
                          :checked="column.visible"
                          @change="toggleReleaseColumn(column.key)"
                        />
                        <span>{{ column.label }}</span>
                      </label>
                    </div>
                  </details>
                </div>

                <div class="game-filter-row project-releases-filters__row">
                  <label class="game-filter-field">
                    <span class="game-filter-field__label">Тип</span>
                    <select v-model="releaseFilterState.type" class="game-filter-field__control">
                      <option value="">Все типы</option>
                      <option
                        v-for="option in releaseTypeOptions"
                        :key="option.value"
                        :value="option.value"
                      >
                        {{ option.label }}
                      </option>
                    </select>
                  </label>

                  <label
                    v-for="filter in releaseFilters"
                    :key="filter.id"
                    class="game-filter-field"
                  >
                    <span class="game-filter-field__label">{{ filter.name }}</span>
                    <select
                      v-model="releaseFilterState.dimensionValueIds[filter.id]"
                      class="game-filter-field__control"
                    >
                      <option value="">Все значения</option>
                      <option
                        v-for="value in releaseFilterValueOptions(filter)"
                        :key="value.id"
                        :value="String(value.id)"
                      >
                        {{ value.name }}
                      </option>
                    </select>
                  </label>

                  <button
                    class="game-filter-reset"
                    type="button"
                    :disabled="!hasActiveReleaseFilters"
                    title="Сбросить фильтры"
                    @click="resetReleaseFilters"
                  >
                    <RotateCcw :size="18" :stroke-width="1.9" aria-hidden="true" />
                    <span>Сбросить</span>
                  </button>
                </div>
              </section>

              <div class="project-releases-layout">
                <section
                  v-if="selectedProjectRelease"
                  class="project-preview__card project-release-page"
                  aria-label="Релиз проекта"
                >
                  <button class="project-release-page__back" type="button" @click="closeReleasePage">
                    <ChevronLeft :size="17" :stroke-width="1.9" aria-hidden="true" />
                    <span>К релизам</span>
                  </button>

                  <section class="project-release-page__block">
                    <header class="project-release-page__header">
                      <h2>{{ selectedProjectRelease.title }}</h2>

                      <a
                        v-if="selectedProjectRelease.file_url"
                        class="button button-primary project-release-page__download"
                        :href="selectedProjectRelease.file_url"
                        download
                      >
                        <Download :size="18" :stroke-width="1.9" aria-hidden="true" />
                        <span>Скачать</span>
                      </a>
                      <button
                        v-else
                        class="button button-primary project-release-page__download"
                        type="button"
                        disabled
                      >
                        <Download :size="18" :stroke-width="1.9" aria-hidden="true" />
                        <span>Скачать</span>
                      </button>
                    </header>

                    <p class="project-release-page__file-name">
                      {{ selectedProjectRelease.file_name ?? 'Файл релиза не указан' }}
                    </p>

                    <div class="project-release-page__meta">
                      <span class="project-release-page__author">
                        <span class="project-release-page__avatar" aria-hidden="true">
                          {{ (project.owner_name ?? 'А').slice(0, 1).toUpperCase() }}
                        </span>
                        <span>{{ project.owner_name ?? 'Автор не указан' }}</span>
                      </span>
                      <span>{{ formatDate(selectedProjectRelease.released_at) }}</span>
                      <span>{{ formatFileSize(selectedProjectRelease.file_size) }}</span>
                      <span>{{ selectedProjectRelease.type_label ?? selectedProjectRelease.type }}</span>
                      <span>{{ releaseFilterSummary(selectedProjectRelease) }}</span>
                    </div>
                  </section>

                  <section class="project-release-page__block">
                    <h3>Changelog</h3>
                    <RichTextRenderer
                      class="project-release-page__changelog"
                      :value="selectedProjectRelease.changelog"
                      empty-text="Changelog пока не добавлен."
                    />
                  </section>
                </section>

                <section v-else class="project-preview__card project-releases-content" aria-label="Список релизов">
                  <p v-if="projectReleasesLoading" class="project-releases-state">Загрузка релизов...</p>

                  <p v-else-if="projectReleasesError" class="field-error">
                    {{ projectReleasesError }}
                  </p>

                  <template v-else-if="visibleProjectReleases.length > 0">
                    <ProjectReleasesTable
                      :releases="visibleProjectReleases"
                      :release-filters="releaseFilters"
                      :columns="visibleReleaseColumns"
                      :project-owner-name="project.owner_name"
                      readonly
                      @open-release="openReleasePage"
                    />

                    <nav
                      v-if="releasePaginationVisible"
                      class="data-table-pagination project-releases-pagination"
                      aria-label="Пагинация релизов"
                    >
                      <span class="data-table-pagination__summary">{{ releasePaginationLabel }}</span>

                      <div class="data-table-pagination__controls">
                        <button
                          class="data-table-pagination__button"
                          type="button"
                          :disabled="releaseEffectivePage === 1"
                          aria-label="Предыдущая страница"
                          @click="setReleasePage(releaseEffectivePage - 1)"
                        >
                          <ChevronLeft :size="16" aria-hidden="true" />
                        </button>

                        <button
                          v-for="page in releaseVisiblePages"
                          :key="page"
                          class="data-table-pagination__button data-table-pagination__page"
                          :class="{ 'data-table-pagination__page--active': page === releaseEffectivePage }"
                          type="button"
                          :aria-current="page === releaseEffectivePage ? 'page' : undefined"
                          @click="setReleasePage(page)"
                        >
                          {{ page }}
                        </button>

                        <button
                          class="data-table-pagination__button"
                          type="button"
                          :disabled="releaseEffectivePage === releaseTotalPages"
                          aria-label="Следующая страница"
                          @click="setReleasePage(releaseEffectivePage + 1)"
                        >
                          <ChevronRight :size="16" aria-hidden="true" />
                        </button>
                      </div>
                    </nav>
                  </template>

                  <p v-else-if="publishedReleasesCount === 0" class="project-releases-state">
                    Опубликованные релизы пока не добавлены.
                  </p>

                  <p v-else class="project-releases-state">Релизы не найдены по текущим фильтрам.</p>
                </section>
              </div>
            </section>

            <section
              v-if="activeTab === 'screenshots'"
              class="project-preview__screenshots-tab"
              aria-label="Скриншоты"
            >
              <section v-if="projectScreenshots.length" class="project-preview__screenshots-gallery">
                <article
                  v-for="(screenshot, index) in projectScreenshots"
                  :key="screenshot.id"
                  class="project-preview__screenshot-card"
                >
                  <img
                    :src="screenshot.url"
                    :alt="`${project.title} — скриншот ${index + 1}`"
                  />
                </article>
              </section>

              <section v-else class="project-preview__tab-placeholder">
                <h2>Скриншоты</h2>
                <p>Скриншоты проекта пока не добавлены.</p>
              </section>
            </section>

            <section v-if="activeTab === 'overview'" class="project-preview__card">
              <h2><FileText :size="18" aria-hidden="true" />Описание</h2>
              <div
                v-if="hasDescription"
                class="project-preview__description"
              >
                <RichTextRenderer
                  :value="project.description"
                  empty-text="Подробное описание проекта пока не добавлено."
                />
              </div>
              <p v-else class="project-preview__muted">Подробное описание проекта пока не добавлено.</p>
            </section>
          </main>

          <aside class="project-preview__aside">
            <section class="project-preview__card project-preview__card--status">
              <h2><Activity :size="18" aria-hidden="true" />Статус проекта</h2>
              <label class="project-preview__status-select">
                <select
                  :value="project.publication_status"
                  aria-label="Статус публикации"
                  :disabled="isPublicationStatusSaving"
                  @change="changePublicationStatus"
                >
                  <option v-for="option in publicationStatusOptions" :key="option.value" :value="option.value">
                    {{ option.label }}
                  </option>
                </select>
              </label>
              <p class="project-preview__status-description">{{ publicationStatusDescription }}</p>
              <p v-if="publicationStatusError" class="project-preview__status-error">{{ publicationStatusError }}</p>
            </section>

            <section class="project-preview__card">
              <h2><Tag :size="18" aria-hidden="true" />Подробности</h2>
              <dl class="project-preview__details project-preview__details--aside">
                <div><dt>Создан</dt><dd>{{ formatDate(project.created_at) }}</dd></div>
                <div><dt>Обновлён</dt><dd>{{ formatDate(latestReleaseDate) }}</dd></div>
                <div><dt>Лицензия</dt><dd>{{ project.licence_name ?? '—' }}</dd></div>
              </dl>
            </section>

            <section
              v-for="filter in visibleOverviewFilterBlocks"
              :key="filter.id"
              class="project-preview__card project-preview__filter-card"
            >
              <h2>{{ filter.name }}</h2>
              <OverflowBadgeRow :values="filter.values" :overflow-label="filter.name" />
            </section>

            <section class="project-preview__card">
              <h2><Link2 :size="18" aria-hidden="true" />Ссылки</h2>
              <div v-if="projectLinks.length" class="project-preview__links">
                <a v-for="link in projectLinks" :key="link" :href="link" target="_blank" rel="noreferrer">
                  <span>{{ link }}</span><ExternalLink :size="15" aria-hidden="true" />
                </a>
              </div>
              <p v-else class="project-preview__muted">Ссылки не добавлены.</p>
            </section>

            <section class="project-preview__card">
              <h2><UserRound :size="18" aria-hidden="true" />Авторы</h2>
              <div class="project-preview__authors">
                <article
                  v-for="author in overviewAuthors"
                  :key="author.id"
                  class="project-preview__author"
                >
                  <img
                    v-if="author.avatarUrl"
                    :src="author.avatarUrl"
                    :alt="author.name"
                    class="project-preview__author-avatar"
                  />
                  <span v-else class="project-preview__author-avatar" aria-hidden="true">
                    {{ author.name.slice(0, 1).toUpperCase() }}
                  </span>
                  <span>
                    <strong>{{ author.name }}</strong>
                    <small>{{ author.roleLabel }}</small>
                  </span>
                </article>
              </div>
            </section>
          </aside>
        </div>

      </template>
    </section>

    <DeleteModal
      :open="isDeleteModalOpen"
      title="Удалить проект"
      :description="deleteModalDescription"
      :loading="isDeletingProject"
      @cancel="closeDeleteModal"
      @confirm="confirmDeleteProject"
    />
  </AppShell>
</template>

<style scoped>
.project-preview {
  display: grid;
  gap: 18px;
}

.project-preview__state {
  display: grid;
  min-height: 320px;
  place-items: center;
  color: var(--color-text-muted);
}

.project-preview__state--error {
  gap: 12px;
  align-content: center;
  justify-items: center;
}

.project-preview__state p { margin: 0; }

.project-preview__header { display: grid; gap: 14px; }

.project-preview__breadcrumbs,
.project-preview__meta,
.project-preview__meta span,
.project-preview__heading-row,
.project-preview__edit,
.project-preview__status,
.project-preview__card h2 {
  display: flex;
  align-items: center;
}

.project-preview__breadcrumbs { gap: 8px; color: var(--color-text-muted); font-size: 13px; }
.project-preview__breadcrumbs button { display: inline-flex; align-items: center; gap: 6px; padding: 0; color: var(--color-primary); background: none; border: 0; cursor: pointer; font: inherit; }
.project-preview__heading-row { justify-content: space-between; gap: 16px; }
.project-preview__heading-row h1 { margin: 0; font-size: clamp(28px, 4vw, 40px); letter-spacing: -0.03em; }
.project-preview__meta { flex-wrap: wrap; gap: 8px 14px; margin-top: 8px; color: var(--color-text-muted); font-size: 14px; }
.project-preview__meta span { gap: 5px; }
.project-preview__actions { position: relative; display: flex; align-items: center; gap: 8px; flex: 0 0 auto; }
.project-preview__edit { gap: 8px; }

.project-preview__hero,
.project-preview__card {
  background: var(--color-surface);
  border: 1px solid var(--color-border-soft);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
}

.project-preview__hero { display: grid; grid-template-columns: 136px minmax(0, 1fr) minmax(280px, .65fr); gap: 22px; align-items: stretch; max-height: 166px; overflow: hidden; padding: 14px 16px; }
.project-preview__hero-media { overflow: hidden; aspect-ratio: 1; background: var(--color-bg-soft); border-radius: var(--radius-md); }
.project-preview__hero-media img { width: 100%; height: 100%; object-fit: cover; }
.project-preview__hero-media span { display: grid; width: 100%; height: 100%; place-items: center; color: var(--color-primary); font-size: 56px; font-weight: 800; }
.project-preview__hero-content { display: flex; flex-direction: column; justify-content: space-between; gap: 10px; min-width: 0; }
.project-preview__summary { display: -webkit-box; max-width: 720px; margin: 0; overflow: hidden; color: var(--color-text-muted); font-size: 15px; line-height: 1.45; -webkit-box-orient: vertical; -webkit-line-clamp: 2; }
.project-preview__muted { color: var(--color-text-muted); }
.project-preview__tags { display: flex; flex-wrap: wrap; gap: 7px; margin-top: 8px; }
.project-preview__tags span { padding: 5px 10px; color: var(--color-primary); background: color-mix(in srgb, var(--color-primary) 12%, transparent); border: 1px solid color-mix(in srgb, var(--color-primary) 28%, transparent); border-radius: 999px; font-size: 12px; font-weight: 700; }
.project-preview__hero-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 14px; margin-top: 8px; }
.project-preview__hero-author { display: inline-flex; align-items: center; gap: 5px; color: var(--color-primary); font-size: 13px; }
.project-preview__hero-slug { color: var(--color-text-muted); font-size: 13px; }
.project-preview__publication-status { padding: 4px 9px; color: var(--color-text-muted); background: var(--color-bg-soft); border: 1px solid var(--color-border); border-radius: 999px; font-size: 12px; }
.project-preview__hero-facts { display: grid; grid-template-columns: repeat(2, minmax(150px, 1fr)); gap: 0; margin: 0; padding-left: 20px; border-left: 1px solid var(--color-border-soft); }
.project-preview__hero-facts div { display: grid; gap: 5px; align-content: center; justify-items: center; text-align: center; }
.project-preview__hero-facts div + div { border-left: 1px solid var(--color-border-soft); }
.project-preview__hero-facts svg { color: var(--color-text-muted); }
.project-preview__hero-facts dd { margin: 0; font-size: 18px; font-weight: 800; }
.project-preview__hero-facts dt, .project-preview__details dt { color: var(--color-text-muted); font-size: 12px; }
.project-preview__details dd { margin: 0; }

.project-preview__status { gap: 5px; width: fit-content; padding: 5px 9px; border: 1px solid currentColor; border-radius: 999px; font-size: 12px; font-weight: 700; }
.project-preview__status--gray { color: var(--color-text-muted); }
.project-preview__status--warning { color: var(--color-warning); }
.project-preview__status--success { color: var(--color-success); }
.project-preview__status--danger { color: var(--color-danger); }
.project-preview__tabs { display: flex; }
.project-preview__more { gap: 7px; }
.project-preview__more-chevron { transition: transform 160ms ease; }
.project-preview__more-chevron--open { transform: rotate(180deg); }
.project-preview__more-menu { position: absolute; top: calc(100% + 8px); right: 0; z-index: 5; min-width: 190px; padding: 5px; background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-md); box-shadow: var(--shadow-md); }
.project-preview__more-menu button { display: flex; width: 100%; gap: 8px; align-items: center; padding: 9px 10px; color: var(--color-text); background: transparent; border: 0; border-radius: var(--radius-sm); cursor: pointer; text-align: left; font: inherit; font-size: 13px; }
.project-preview__more-menu button:hover { background: var(--color-surface-hover); }
.project-preview__more-menu button:disabled { cursor: wait; opacity: .6; }
.project-preview__more-menu-danger { color: var(--color-danger) !important; }
.project-preview__action-error { margin: 8px 0 0; color: var(--color-danger); font-size: 13px; }
.project-preview__tab-placeholder { min-height: 180px; padding: 24px; background: var(--color-surface); border: 1px solid var(--color-border-soft); border-radius: var(--radius-lg); }
.project-preview__tab-placeholder h2 { margin: 0 0 8px; }
.project-preview__tab-placeholder p { margin: 0; color: var(--color-text-muted); }
.project-preview__releases { display: grid; gap: 16px; }
.project-releases-filters__top { grid-template-columns: minmax(0, 1fr) auto; }
.project-releases-filters__row { display: flex; flex-wrap: wrap; gap: 10px; align-items: end; }
.project-releases-filters__row .game-filter-field { flex: 0 1 240px; }
.project-releases-filters__row .game-filter-reset { margin-left: auto; }
.project-releases-layout { align-items: start; }
.project-releases-content { min-height: 220px; padding: 0; overflow: hidden; }
.project-releases-state { margin: 0; padding: 22px; color: var(--color-text-muted); }
.project-releases-pagination { border-top: 1px solid var(--color-border-soft); }
.project-release-page { display: grid; gap: 18px; min-height: 220px; align-content: start; background: transparent; border: 0; box-shadow: none; padding: 0; }
.project-release-page__back { display: inline-flex; align-items: center; gap: 7px; width: fit-content; padding: 0; color: var(--color-primary); background: none; border: 0; cursor: pointer; font: inherit; font-size: 13px; font-weight: 800; }
.project-release-page__back:hover { color: var(--color-primary-hover); }
.project-release-page__block { display: grid; gap: 14px; padding: 18px; background: var(--color-surface); border: 1px solid var(--color-border-soft); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); }
.project-release-page__block:first-of-type { gap: 9px; }
.project-release-page__header { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
.project-release-page__header h2 { margin: 0; font-size: 24px; }
.project-release-page__download { flex: 0 0 auto; gap: 8px; width: fit-content; white-space: nowrap; }
.project-release-page__file-name { margin: 0; color: var(--color-primary); font-size: 16px; font-weight: 650; overflow-wrap: anywhere; }
.project-release-page__meta { display: flex; flex-wrap: wrap; align-items: center; gap: 10px 18px; color: var(--color-text-muted); font-size: 15px; }
.project-release-page__author { display: inline-flex; align-items: center; gap: 8px; color: var(--color-text); font-size: 16px; font-weight: 700; }
.project-release-page__avatar { display: inline-grid; place-items: center; width: 30px; height: 30px; color: var(--color-primary); background: color-mix(in srgb, var(--color-primary) 12%, transparent); border: 1px solid color-mix(in srgb, var(--color-primary) 24%, var(--color-border-soft)); border-radius: 50%; font-size: 12px; font-weight: 850; }
.project-release-page__block h3 { margin: 0; font-size: 16px; }
.project-release-page__changelog { font-size: 14px; }
.project-preview__screenshots-tab { display: grid; gap: 16px; }
.project-preview__screenshots-gallery { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 16px; }
.project-preview__screenshot-card { overflow: hidden; margin: 0; background: var(--color-surface); border: 1px solid var(--color-border-soft); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); }
.project-preview__screenshot-card img { display: block; width: 100%; aspect-ratio: 16 / 10; object-fit: cover; }
.project-preview__layout { display: grid; grid-template-columns: minmax(0, 1.65fr) minmax(280px, .75fr); gap: 16px; align-items: start; }
.project-preview__main, .project-preview__aside { display: grid; gap: 16px; min-width: 0; }
.project-preview__card { padding: 18px; }
.project-preview__card h2 { gap: 8px; margin: 0 0 16px; font-size: 16px; }
.project-preview__card > p { margin: 0; color: var(--color-text-muted); line-height: 1.6; white-space: pre-line; }
.project-preview__description { overflow: visible; }
.project-preview__description :deep(.rich-text-renderer) { line-height: 1.6; }
.project-preview__details { display: grid; gap: 12px; margin: 0; }
.project-preview__details div { display: grid; grid-template-columns: minmax(120px, .7fr) minmax(0, 1.3fr); gap: 14px; align-items: baseline; }
.project-preview__details dd { min-width: 0; overflow-wrap: anywhere; }
.project-preview__details--aside div { grid-template-columns: minmax(70px, .7fr) minmax(0, 1.3fr); }
.project-preview__details--aside dd { text-align: right; }
.project-preview__card--status { display: grid; gap: 12px; }
.project-preview__card--status h2 { margin-bottom: 0; }
.project-preview__status-select select { width: 220px; max-width: 100%; min-height: 38px; padding: 0 10px; color: var(--color-text); background: var(--color-bg-soft); border: 1px solid var(--color-border); border-radius: var(--radius-md); font: inherit; }
.project-preview__status-select select:focus { border-color: var(--color-primary); outline: 2px solid color-mix(in srgb, var(--color-focus) 25%, transparent); outline-offset: 1px; }
.project-preview__status-description, .project-preview__status-error { margin: 0; color: var(--color-text-muted); font-size: 13px; line-height: 1.45; }
.project-preview__status-error { color: var(--color-danger); }
.project-preview__filter-card { display: grid; gap: 10px; }
.project-preview__filter-card h2 { margin-bottom: 0; }
.project-preview__links { display: grid; gap: 10px; }
.project-preview__links a { display: flex; align-items: center; justify-content: space-between; gap: 8px; color: var(--color-primary); font-size: 13px; text-decoration: none; }
.project-preview__links a span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.project-preview__authors { display: grid; gap: 10px; }
.project-preview__author { display: flex; align-items: center; gap: 10px; min-width: 0; }
.project-preview__author-avatar { display: grid; flex: 0 0 auto; width: 34px; height: 34px; place-items: center; color: var(--color-primary); background: color-mix(in srgb, var(--color-primary) 11%, transparent); border: 1px solid color-mix(in srgb, var(--color-primary) 24%, var(--color-border-soft)); border-radius: 50%; font-size: 13px; font-weight: 850; object-fit: cover; }
.project-preview__author span:last-child { display: grid; gap: 2px; min-width: 0; }
.project-preview__author strong { overflow: hidden; color: var(--color-text); font-size: 14px; font-weight: 800; text-overflow: ellipsis; white-space: nowrap; }
.project-preview__author small { color: var(--color-text-muted); font-size: 12px; }

@media (max-width: 900px) {
  .project-preview__hero { grid-template-columns: 136px minmax(0, 1fr); max-height: none; overflow: visible; }
  .project-preview__hero-facts { grid-column: 2; grid-row: 2; padding-top: 14px; padding-left: 0; border-top: 1px solid var(--color-border-soft); border-left: 0; }
  .project-preview__layout { grid-template-columns: 1fr; }
  .project-preview__aside { grid-template-columns: repeat(2, minmax(0, 1fr)); align-items: start; }
}

@media (max-width: 640px) {
  .project-preview__heading-row { align-items: flex-start; flex-direction: column; }
  .project-preview__hero { grid-template-columns: 1fr; }
  .project-preview__hero-media { max-width: 180px; }
  .project-preview__hero-facts { grid-column: auto; grid-row: auto; padding-top: 14px; border-top: 1px solid var(--color-border-soft); }
  .project-preview__aside { grid-template-columns: 1fr; }
  .project-preview__details div { grid-template-columns: 1fr; gap: 3px; }
  .project-preview__details--aside dd { text-align: left; }
}
</style>
