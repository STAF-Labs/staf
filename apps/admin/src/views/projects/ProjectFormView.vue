<script setup lang="ts">
import {
  ArrowLeft,
  Bold,
  Building2,
  Clock,
  Columns3,
  Heading2,
  Image as ImageIcon,
  Info,
  Italic,
  List,
  ListOrdered,
  Pencil,
  Plus,
  RotateCcw,
  Rocket,
  Save,
  SlidersHorizontal,
  Trash2,
  UserPlus,
} from '@lucide/vue'
import StarterKit from '@tiptap/starter-kit'
import { EditorContent, useEditor } from '@tiptap/vue-3'
import Sortable from 'sortablejs'
import type { ComponentPublicInstance } from 'vue'
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppShell from '@/components/layout/AppShell.vue'
import DeleteModal from '@/components/ui/DeleteModal.vue'
import SearchField from '@/components/ui/SearchField.vue'
import ProjectReleasesTable from '@/views/projects/components/ProjectReleasesTable.vue'
import type { DataColumn } from '@/shared/data/table'
import {
  fetchGameContentTypes,
  fetchGameDimensions,
  type GameContentTypeListItem,
  type GameDimension,
} from '@/shared/games/games'
import { fetchLicences, type LicenceOption } from '@/shared/licences/licences'
import {
  addProjectMember,
  addProjectScreenshots,
  deleteProjectRelease,
  deleteProjectScreenshot,
  fetchProject,
  fetchProjectMembers,
  fetchProjectReleaseFilters,
  fetchProjectReleases,
  reorderProjectScreenshots,
  saveProjectDetails,
  searchProjectMemberCandidates,
  updateProjectDraft,
  type ProjectDetail,
  type ProjectMember,
  type ProjectMemberCandidate,
  type ProjectOwnerOption,
  type ProjectRelease,
  type ProjectScreenshot,
} from '@/shared/projects/projects'
import '@/assets/styles/game-form.css'

type ProjectMainForm = {
  ownerableType: string
  ownerableId: number | null
  ownerName: string
  gameContentTypeId: number | null
  title: string
  logo: File | null
  logoUrl: string | null
  summary: unknown
  summaryFilled: boolean
  description: unknown
  descriptionFilled: boolean
  licenceName: string
  screenshots: ProjectScreenshot[]
  dimensionValueIds: Record<number, number[]>
  persistedDimensionValueIds: number[]
  requiredProjectDimensionIds: number[]
  tagInput: string
  tags: string[]
  websiteUrls: string[]
}

type ReleaseFilterState = {
  search: string
  type: '' | 'alpha' | 'beta' | 'release'
  status: '' | 'published' | 'on_moderation' | 'archived'
  dimensionValueIds: Record<number, string>
}

type MemberFilterState = {
  search: string
  role: '' | 'owner' | 'maintainer' | 'member'
  status: '' | 'active' | 'invited' | 'suspended'
}

type ProjectMemberCard = {
  id: string
  name: string
  role: 'owner' | 'maintainer' | 'member'
  roleLabel: string
  status: 'active' | 'invited' | 'suspended'
  avatarUrl: string | null
  createdAt: string | null
  accessSource: 'project' | 'organization'
}

type ReleaseSortOption = 'title_asc' | 'title_desc' | 'released_at'

const imageMimeTypes = ['image/jpeg', 'image/png', 'image/webp']
const imageAccept = imageMimeTypes.join(',')
const logoMaxBytes = 2 * 1024 * 1024
const logoMaxWidth = 1024
const logoMaxHeight = 1024
const screenshotMaxBytes = 5 * 1024 * 1024
const screenshotMaxWidth = 3840
const screenshotMaxHeight = 2160
const screenshotsMaxCount = 20
const releaseTypeOptions = [
  { value: 'alpha', label: 'Альфа' },
  { value: 'beta', label: 'Бета' },
  { value: 'release', label: 'Релиз' },
] as const
const releaseStatusOptions = [
  { value: 'on_moderation', label: 'На модерации' },
  { value: 'published', label: 'Опубликован' },
  { value: 'archived', label: 'В архиве' },
] as const
const releaseSortOptions: Array<{ value: ReleaseSortOption; label: string }> = [
  { value: 'title_asc', label: 'А-Я' },
  { value: 'title_desc', label: 'Я-А' },
  { value: 'released_at', label: 'Дата релиза' },
]
const releasePageSizeOptions = [10, 20, 30, 40, 50]
const memberRoleOptions = [
  { value: 'owner', label: 'Владелец' },
  { value: 'maintainer', label: 'Менеджер' },
  { value: 'member', label: 'Участник' },
] as const
const memberStatusOptions = [
  { value: 'active', label: 'Активен' },
  { value: 'invited', label: 'Приглашён' },
  { value: 'suspended', label: 'Приостановлен' },
] as const

const route = useRoute()
const router = useRouter()
const project = ref<ProjectDetail | null>(null)
const isLoading = ref(true)
const isSaving = ref(false)
const isFiltersLoading = ref(false)
const filtersLoadFailed = ref(false)
const activeTab = ref<'main' | 'description' | 'licence' | 'screenshots' | 'releases' | 'members'>(
  'main',
)
const message = ref('')
const messageKind = ref<'success' | 'error'>('error')
const contentTypeOptions = ref<GameContentTypeListItem[]>([])
const projectDimensions = ref<GameDimension[]>([])
const authorOptions = ref<ProjectOwnerOption[]>([])
const licenceOptions = ref<LicenceOption[]>([])
const licencesLoading = ref(false)
const licencesError = ref('')
const dimensionPaths = reactive<Record<number, number[]>>({})
const releaseFilters = ref<GameDimension[]>([])
const releaseColumns = ref<DataColumn[]>([
  { key: 'type', label: 'Тип', visible: true },
  { key: 'title', label: 'Название', visible: true },
  { key: 'status', label: 'Статус', visible: true },
  { key: 'released_at', label: 'Дата релиза', visible: false },
])
const releaseFiltersLoaded = ref(false)
const releaseFiltersLoading = ref(false)
const releaseFiltersError = ref('')
const projectReleases = ref<ProjectRelease[]>([])
const projectReleasesLoading = ref(false)
const projectReleasesError = ref('')
const pendingDeleteRelease = ref<ProjectRelease | null>(null)
const deletingReleaseId = ref<number | null>(null)
const projectMembers = ref<ProjectMember[]>([])
const projectMembersLoading = ref(false)
const projectMembersError = ref('')
const isMemberModalOpen = ref(false)
const memberModalSearch = ref('')
const memberCandidates = ref<ProjectMemberCandidate[]>([])
const memberCandidatesLoading = ref(false)
const memberCandidatesError = ref('')
const selectedMemberCandidate = ref<ProjectMemberCandidate | null>(null)
const addingProjectMember = ref(false)
const projectMemberModalError = ref('')
const releaseAdvancedFiltersOpen = ref(false)
const releaseSort = ref<ReleaseSortOption>('released_at')
const releasePageSize = ref(20)
const releaseFilterState = reactive<ReleaseFilterState>({
  search: '',
  type: '',
  status: '',
  dimensionValueIds: {},
})
const memberFilterState = reactive<MemberFilterState>({
  search: '',
  role: '',
  status: '',
})
const titleInput = ref<HTMLInputElement | null>(null)
const logoInput = ref<HTMLInputElement | null>(null)
const screenshotsInput = ref<HTMLInputElement | null>(null)
const screenshotsGrid = ref<HTMLElement | null>(null)
const deletingScreenshotId = ref<number | null>(null)
const titleError = ref('')
const logoError = ref('')
const summaryError = ref('')
const descriptionError = ref('')
const screenshotsError = ref('')
const filterSettingsError = ref('')
const showFilterErrors = ref(false)
const logoPreviewUrl = ref<string | null>(null)
const selectedScreenshotFiles = ref<File[]>([])
const selectedScreenshotPreviewUrls = ref<string[]>([])
let screenshotsSortable: Sortable | null = null
const websiteUrlInputs = ref<HTMLInputElement[]>([])
let memberCandidateSearchTimer: ReturnType<typeof window.setTimeout> | null = null
const projectId = computed(() => Number(route.params.id))
let ignoreNextMemberSearchChange = false
const form = reactive<ProjectMainForm>({
  ownerableType: '',
  ownerableId: null,
  ownerName: '',
  gameContentTypeId: null,
  title: '',
  logo: null,
  logoUrl: null,
  summary: null,
  summaryFilled: false,
  description: null,
  descriptionFilled: false,
  licenceName: '',
  screenshots: [],
  dimensionValueIds: {},
  persistedDimensionValueIds: [],
  requiredProjectDimensionIds: [],
  tagInput: '',
  tags: [],
  websiteUrls: [],
})
const authorKey = computed(() =>
  form.ownerableType && form.ownerableId ? `${form.ownerableType}:${form.ownerableId}` : '',
)
const hasActiveReleaseFilters = computed(() =>
  Boolean(
    releaseFilterState.search.trim() ||
      releaseFilterState.type ||
      releaseFilterState.status ||
      Object.values(releaseFilterState.dimensionValueIds).some(Boolean),
  ),
)
const filteredProjectReleases = computed(() =>
  projectReleases.value.filter(
    (release) =>
      matchesReleaseSearch(release) &&
      matchesReleaseType(release) &&
      matchesReleaseStatus(release) &&
      matchesReleaseDimensions(release),
  ),
)
const sortedProjectReleases = computed(() =>
  [...filteredProjectReleases.value].sort((firstRelease, secondRelease) => {
    if (releaseSort.value === 'title_asc') {
      return firstRelease.title.localeCompare(secondRelease.title, 'ru-RU')
    }

    if (releaseSort.value === 'title_desc') {
      return secondRelease.title.localeCompare(firstRelease.title, 'ru-RU')
    }

    return releaseTimestamp(secondRelease) - releaseTimestamp(firstRelease)
  }),
)
const visibleProjectReleases = computed(() =>
  sortedProjectReleases.value.slice(0, releasePageSize.value),
)
const visibleReleaseColumns = computed(() => releaseColumns.value.filter((column) => column.visible))
const projectAuthorMember = computed(() => {
  if (!project.value || project.value.ownerable_type !== 'App\\Models\\User\\User') {
    return null
  }

  return {
    id: `author:${project.value.ownerable_type}:${project.value.ownerable_id}`,
    name: project.value.owner_name ?? 'Автор проекта',
    role: 'owner',
    roleLabel: 'Владелец',
    status: 'active',
    avatarUrl: null,
    createdAt: project.value.created_at,
    accessSource: 'project',
  } satisfies ProjectMemberCard
})
const projectMemberCards = computed<ProjectMemberCard[]>(() => [
  ...(projectAuthorMember.value ? [projectAuthorMember.value] : []),
  ...projectMembers.value.map((member) => ({
    id: `member:${member.id}`,
    name: member.display_name || member.username || 'Участник проекта',
    role: member.role,
    roleLabel: member.role_label ?? memberRoleLabel(member.role),
    status: member.status,
    avatarUrl: member.avatar_url,
    createdAt: member.created_at,
    accessSource: member.access_source,
  })),
])
const visibleProjectMemberCards = computed(() => {
  const searchQuery = memberFilterState.search.trim().toLocaleLowerCase('ru-RU')

  return projectMemberCards.value.filter((member) => {
    const matchesSearch =
      !searchQuery || member.name.toLocaleLowerCase('ru-RU').includes(searchQuery)
    const matchesRole = !memberFilterState.role || member.role === memberFilterState.role
    const matchesStatus = !memberFilterState.status || member.status === memberFilterState.status

    return matchesSearch && matchesRole && matchesStatus
  })
})
const releaseDeleteModalDescription = computed(() => {
  const title = pendingDeleteRelease.value?.title ?? 'релиз'

  return `Релиз «${title}» и связанный файл будут удалены без возможности восстановления.`
})

const summaryEditor = useEditor({
  extensions: [StarterKit],
  content: '',
  onUpdate: ({ editor }) => {
    form.summary = editor.getJSON()
    form.summaryFilled = !editor.isEmpty
    summaryError.value = ''
  },
})

const descriptionEditor = useEditor({
  extensions: [StarterKit],
  content: '',
  onUpdate: ({ editor }) => {
    form.description = editor.getJSON()
    form.descriptionFilled = !editor.isEmpty
    descriptionError.value = ''
  },
})

function optionKey(option: ProjectOwnerOption): string {
  return `${option.type}:${option.id}`
}

async function loadProject(): Promise<void> {
  isLoading.value = true
  message.value = ''

  try {
    const loadedProject = await fetchProject(projectId.value)

    if (loadedProject.status === 'draft') {
      await router.replace({ name: 'projects.continue', params: { id: String(loadedProject.id) } })

      return
    }

    project.value = loadedProject
    applyProject(loadedProject)

    if (loadedProject.game_id === null) {
      throw new Error('Project game is missing.')
    }

    const contentTypesResponse = await fetchGameContentTypes(loadedProject.game_id)

    contentTypeOptions.value = contentTypesResponse.data

    if (form.gameContentTypeId) {
      await loadProjectDimensions(form.gameContentTypeId)
    }

    await loadLicences()
    await nextTick()

    if (form.summary && summaryEditor.value) {
      summaryEditor.value.commands.setContent(form.summary)
      form.summaryFilled = !summaryEditor.value.isEmpty
    }

    if (form.description && descriptionEditor.value) {
      descriptionEditor.value.commands.setContent(form.description)
      form.descriptionFilled = !descriptionEditor.value.isEmpty
    }
  } catch {
    messageKind.value = 'error'
    message.value = 'Не удалось загрузить проект.'
  } finally {
    isLoading.value = false
  }
}

function applyProject(loadedProject: ProjectDetail): void {
  Object.assign(form, {
    ownerableType: loadedProject.ownerable_type,
    ownerableId: loadedProject.ownerable_id,
    ownerName: loadedProject.owner_name ?? '',
    gameContentTypeId: loadedProject.game_content_type_id,
    title: loadedProject.title,
    logo: null,
    logoUrl: loadedProject.logo_url,
    summary: loadedProject.summary,
    summaryFilled: richTextIsFilled(loadedProject.summary),
    description: loadedProject.description,
    descriptionFilled: richTextIsFilled(loadedProject.description),
    licenceName: loadedProject.licence_name ?? '',
    screenshots: loadedProject.screenshots,
    dimensionValueIds: {},
    persistedDimensionValueIds: loadedProject.dimension_value_ids ?? [],
    requiredProjectDimensionIds: [],
    tagInput: '',
    tags: normalizeStringArray(loadedProject.tags),
    websiteUrls: normalizeStringArray(loadedProject.website_urls),
  })
  authorOptions.value = [
    {
      kind: loadedProject.ownerable_type.includes('Organization') ? 'organization' : 'user',
      type: loadedProject.ownerable_type,
      id: loadedProject.ownerable_id,
      label: loadedProject.owner_name ?? 'Автор проекта',
    },
  ]
  setLogoPreview(form.logoUrl)
}

async function chooseScreenshots(event: Event): Promise<void> {
  const input = event.target as HTMLInputElement
  const files = Array.from(input.files ?? [])

  screenshotsError.value = ''

  if (files.length === 0) {
    return
  }

  if (files.length > screenshotsMaxCount) {
    screenshotsError.value = `За один раз можно добавить не больше ${screenshotsMaxCount} файлов.`
    input.value = ''

    return
  }

  for (const file of files) {
    const error = await validateScreenshot(file)

    if (error) {
      screenshotsError.value = error
      input.value = ''

      return
    }
  }

  selectedScreenshotFiles.value.push(...files)
  selectedScreenshotPreviewUrls.value.push(...files.map((file) => URL.createObjectURL(file)))
  input.value = ''
}

async function validateScreenshot(file: File): Promise<string> {
  if (!imageMimeTypes.includes(file.type)) {
    return 'Доступные типы файлов: JPG, PNG, WEBP.'
  }

  if (file.size > screenshotMaxBytes) {
    return 'Файл должен быть не больше 5 МБ.'
  }

  try {
    const dimensions = await getImageDimensions(file)

    if (dimensions.width > screenshotMaxWidth || dimensions.height > screenshotMaxHeight) {
      return `Изображение должно быть не больше ${screenshotMaxWidth}x${screenshotMaxHeight}px.`
    }
  } catch {
    return 'Не удалось прочитать изображение.'
  }

  return ''
}

function openScreenshotsPicker(): void {
  screenshotsInput.value?.click()
}

function removeSelectedScreenshot(index: number): void {
  const [previewUrl] = selectedScreenshotPreviewUrls.value.splice(index, 1)

  if (previewUrl) {
    URL.revokeObjectURL(previewUrl)
  }

  selectedScreenshotFiles.value.splice(index, 1)
}

async function saveScreenshotsTab(): Promise<void> {
  if (!project.value) {
    return
  }

  if (selectedScreenshotFiles.value.length === 0) {
    screenshotsError.value = 'Выберите скриншоты для добавления.'

    return
  }

  isSaving.value = true
  message.value = ''
  screenshotsError.value = ''

  try {
    const updatedProject = await addProjectScreenshots(project.value.id, selectedScreenshotFiles.value)

    project.value = updatedProject
    form.screenshots = updatedProject.screenshots
    clearSelectedScreenshots()
    messageKind.value = 'success'
    message.value = 'Скриншоты сохранены.'
    await nextTick()
    initializeScreenshotsSortable()
  } catch {
    messageKind.value = 'error'
    message.value = 'Не удалось сохранить скриншоты.'
  } finally {
    isSaving.value = false
  }
}

function initializeScreenshotsSortable(): void {
  screenshotsSortable?.destroy()
  screenshotsSortable = null

  if (!screenshotsGrid.value) {
    return
  }

  screenshotsSortable = Sortable.create(screenshotsGrid.value, {
    animation: 160,
    draggable: '.project-screenshot-card--persisted',
    dataIdAttr: 'data-screenshot-id',
    ghostClass: 'project-screenshot-card--ghost',
    chosenClass: 'project-screenshot-card--chosen',
    dragClass: 'project-screenshot-card--dragging',
    onEnd: () => {
      void saveScreenshotOrderFromDom()
    },
  })
}

async function saveScreenshotOrderFromDom(): Promise<void> {
  if (!project.value || !screenshotsGrid.value) {
    return
  }

  const mediaIds = Array.from(
    screenshotsGrid.value.querySelectorAll<HTMLElement>('[data-screenshot-id]'),
  ).map((element) => Number(element.dataset.screenshotId))

  if (
    mediaIds.length !== form.screenshots.length ||
    mediaIds.some((mediaId) => !Number.isInteger(mediaId))
  ) {
    await nextTick()
    initializeScreenshotsSortable()

    return
  }

  const currentMediaIds = form.screenshots.map((screenshot) => screenshot.id)

  if (mediaIds.every((mediaId, index) => mediaId === currentMediaIds[index])) {
    return
  }

  const originalScreenshots = [...form.screenshots]
  const screenshotsById = new Map(form.screenshots.map((screenshot) => [screenshot.id, screenshot]))

  form.screenshots = mediaIds
    .map((mediaId) => screenshotsById.get(mediaId))
    .filter((screenshot): screenshot is ProjectScreenshot => screenshot !== undefined)
  message.value = ''

  try {
    const updatedProject = await reorderProjectScreenshots(project.value.id, mediaIds)

    project.value = updatedProject
    form.screenshots = updatedProject.screenshots
  } catch {
    form.screenshots = originalScreenshots
    messageKind.value = 'error'
    message.value = 'Не удалось изменить порядок скриншотов.'
  } finally {
    await nextTick()
    initializeScreenshotsSortable()
  }
}

async function removePersistedScreenshot(screenshot: ProjectScreenshot): Promise<void> {
  if (!project.value || deletingScreenshotId.value !== null) {
    return
  }

  deletingScreenshotId.value = screenshot.id
  message.value = ''
  screenshotsError.value = ''

  try {
    const updatedProject = await deleteProjectScreenshot(project.value.id, screenshot.id)

    project.value = updatedProject
    form.screenshots = updatedProject.screenshots
    await nextTick()
    initializeScreenshotsSortable()
  } catch {
    messageKind.value = 'error'
    message.value = 'Не удалось удалить скриншот.'
  } finally {
    deletingScreenshotId.value = null
  }
}

function clearSelectedScreenshots(): void {
  for (const previewUrl of selectedScreenshotPreviewUrls.value) {
    URL.revokeObjectURL(previewUrl)
  }

  selectedScreenshotFiles.value = []
  selectedScreenshotPreviewUrls.value = []
}

async function loadLicences(): Promise<void> {
  licencesLoading.value = true
  licencesError.value = ''

  try {
    const response = await fetchLicences()

    licenceOptions.value = response.data
  } catch {
    licencesError.value = 'Не удалось загрузить список лицензий.'
  } finally {
    licencesLoading.value = false
  }
}

async function saveDescriptionTab(): Promise<void> {
  const editor = descriptionEditor.value
  descriptionError.value = ''

  if (!editor || editor.isEmpty) {
    descriptionError.value = 'Добавьте описание проекта.'

    return
  }

  if (!project.value) {
    return
  }

  form.description = editor.getJSON()
  form.descriptionFilled = true
  isSaving.value = true
  message.value = ''

  try {
    const updatedProject = await updateProjectDraft(project.value.id, {
      description: form.description,
      percentageComplete: project.value.percentage_complete,
    })

    project.value = updatedProject
    messageKind.value = 'success'
    message.value = 'Описание сохранено.'
  } catch {
    messageKind.value = 'error'
    message.value = 'Не удалось сохранить описание.'
  } finally {
    isSaving.value = false
  }
}

async function saveLicenceTab(): Promise<void> {
  if (!project.value) {
    return
  }

  isSaving.value = true
  message.value = ''

  try {
    const updatedProject = await updateProjectDraft(project.value.id, {
      licenceName: form.licenceName || null,
      percentageComplete: project.value.percentage_complete,
    })

    project.value = updatedProject
    form.licenceName = updatedProject.licence_name ?? ''
    messageKind.value = 'success'
    message.value = 'Лицензия сохранена.'
  } catch {
    messageKind.value = 'error'
    message.value = 'Не удалось сохранить лицензию.'
  } finally {
    isSaving.value = false
  }
}

async function loadProjectDimensions(gameContentTypeId: number): Promise<void> {
  if (!project.value?.game_id) {
    return
  }

  isFiltersLoading.value = true
  filtersLoadFailed.value = false
  filterSettingsError.value = ''

  try {
    const response = await fetchGameDimensions(project.value.game_id, gameContentTypeId)

    projectDimensions.value = response.data
      .filter((dimension) => dimension.is_active && dimension.applies_to === 'project')
      .map((dimension) => ({
        ...dimension,
        values: dimension.values.filter((value) => value.is_active),
      }))
    form.requiredProjectDimensionIds = projectDimensions.value
      .filter((dimension) => dimension.is_required)
      .map((dimension) => dimension.id)

    if (form.persistedDimensionValueIds.length > 0) {
      for (const dimension of projectDimensions.value) {
        const availableValueIds = new Set(dimension.values.map((value) => value.id))
        const selectedValueIds = form.persistedDimensionValueIds.filter((valueId) =>
          availableValueIds.has(valueId),
        )

        if (selectedValueIds.length > 0) {
          form.dimensionValueIds[dimension.id] = selectedValueIds
        }
      }

      form.persistedDimensionValueIds = []
    }

    initializeDimensionPaths()
  } catch {
    filtersLoadFailed.value = true
    projectDimensions.value = []
    form.requiredProjectDimensionIds = []
    filterSettingsError.value = 'Не удалось загрузить настройки проекта.'
  } finally {
    isFiltersLoading.value = false
  }
}

async function loadReleaseFilters(force = false): Promise<void> {
  if (!project.value || (releaseFiltersLoaded.value && !force)) {
    return
  }

  releaseFiltersLoading.value = true
  releaseFiltersError.value = ''

  try {
    const response = await fetchProjectReleaseFilters(project.value.id)

    releaseFilters.value = response.data.map((dimension) => ({
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
    releaseFiltersLoaded.value = true
  } catch {
    releaseFilters.value = []
    releaseFilterState.dimensionValueIds = {}
    releaseFiltersError.value = 'Не удалось загрузить фильтры релизов.'
  } finally {
    releaseFiltersLoading.value = false
  }
}

function syncReleaseColumns(): void {
  const existingColumnsByKey = new Map(releaseColumns.value.map((column) => [column.key, column]))
  const baseColumns: DataColumn[] = [
    existingColumnsByKey.get('type') ?? { key: 'type', label: 'Тип', visible: true },
    existingColumnsByKey.get('title') ?? { key: 'title', label: 'Название', visible: true },
    existingColumnsByKey.get('status') ?? { key: 'status', label: 'Статус', visible: true },
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

async function loadProjectMembers(): Promise<void> {
  if (!project.value) {
    return
  }

  projectMembersLoading.value = true
  projectMembersError.value = ''

  try {
    const response = await fetchProjectMembers(project.value.id)

    projectMembers.value = response.data
  } catch {
    projectMembers.value = []
    projectMembersError.value = 'Не удалось загрузить участников проекта.'
  } finally {
    projectMembersLoading.value = false
  }
}

function resetReleaseFilters(): void {
  releaseFilterState.search = ''
  releaseFilterState.type = ''
  releaseFilterState.status = ''
  releaseFilterState.dimensionValueIds = Object.fromEntries(
    releaseFilters.value.map((filter) => [filter.id, '']),
  )
}

function toggleReleaseAdvancedFilters(): void {
  releaseAdvancedFiltersOpen.value = !releaseAdvancedFiltersOpen.value
}

function toggleReleaseColumn(key: string): void {
  releaseColumns.value = releaseColumns.value.map((column) =>
    column.key === key ? { ...column, visible: !column.visible } : column,
  )
}

function openReleaseDeleteModal(release: ProjectRelease): void {
  pendingDeleteRelease.value = release
}

async function openReleaseEditPage(release: ProjectRelease): Promise<void> {
  if (!project.value) {
    return
  }

  await router.push({
    name: 'projects.releases.edit',
    params: {
      id: String(project.value.id),
      releaseId: String(release.id),
    },
  })
}

function closeReleaseDeleteModal(): void {
  if (deletingReleaseId.value !== null) {
    return
  }

  pendingDeleteRelease.value = null
}

async function confirmReleaseDelete(): Promise<void> {
  if (!project.value || !pendingDeleteRelease.value) {
    return
  }

  const release = pendingDeleteRelease.value

  deletingReleaseId.value = release.id
  message.value = ''

  try {
    await deleteProjectRelease(project.value.id, release.id)
    projectReleases.value = projectReleases.value.filter((item) => item.id !== release.id)
    pendingDeleteRelease.value = null
  } catch {
    messageKind.value = 'error'
    message.value = 'Не удалось удалить релиз.'
  } finally {
    deletingReleaseId.value = null
  }
}

function openProjectMemberModal(): void {
  isMemberModalOpen.value = true
  memberModalSearch.value = ''
  selectedMemberCandidate.value = null
  memberCandidates.value = []
  memberCandidatesError.value = ''
  projectMemberModalError.value = ''
}

function closeProjectMemberModal(): void {
  if (addingProjectMember.value) {
    return
  }

  isMemberModalOpen.value = false
  selectedMemberCandidate.value = null
  memberCandidates.value = []
  memberCandidatesError.value = ''
  projectMemberModalError.value = ''
}

function selectMemberCandidate(candidate: ProjectMemberCandidate): void {
  if (memberCandidateSearchTimer !== null) {
    window.clearTimeout(memberCandidateSearchTimer)
    memberCandidateSearchTimer = null
  }

  ignoreNextMemberSearchChange = true
  memberModalSearch.value = candidate.display_name || candidate.username
  selectedMemberCandidate.value = candidate
  memberCandidates.value = []
  memberCandidatesError.value = ''
  projectMemberModalError.value = ''
}

async function searchMemberCandidates(): Promise<void> {
  if (!project.value) {
    return
  }

  const search = memberModalSearch.value.trim()

  if (search.length < 2) {
    memberCandidates.value = []
    memberCandidatesError.value = ''

    return
  }

  memberCandidatesLoading.value = true
  memberCandidatesError.value = ''

  try {
    const response = await searchProjectMemberCandidates(project.value.id, search)

    memberCandidates.value = response.data
  } catch {
    memberCandidates.value = []
    memberCandidatesError.value = 'Не удалось найти пользователей.'
  } finally {
    memberCandidatesLoading.value = false
  }
}

async function submitProjectMember(keepOpen: boolean): Promise<void> {
  if (!project.value || !selectedMemberCandidate.value) {
    projectMemberModalError.value = 'Выберите пользователя из списка.'

    return
  }

  addingProjectMember.value = true
  projectMemberModalError.value = ''

  try {
    const member = await addProjectMember(project.value.id, selectedMemberCandidate.value.id)

    projectMembers.value = [member, ...projectMembers.value]
    selectedMemberCandidate.value = null
    memberModalSearch.value = ''
    memberCandidates.value = []

    if (!keepOpen) {
      isMemberModalOpen.value = false
    }
  } catch {
    projectMemberModalError.value = 'Не удалось добавить участника.'
  } finally {
    addingProjectMember.value = false
  }
}

function memberRoleLabel(role: ProjectMemberCard['role']): string {
  return memberRoleOptions.find((option) => option.value === role)?.label ?? role
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

function matchesReleaseStatus(release: ProjectRelease): boolean {
  return releaseFilterState.status === '' || release.status === releaseFilterState.status
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

function releaseTimestamp(release: ProjectRelease): number {
  if (!release.released_at) {
    return 0
  }

  return new Date(release.released_at).getTime()
}

function formatProjectMemberDate(value: string | null): string {
  if (!value) {
    return 'Дата создания не указана'
  }

  return `Создан ${new Intl.DateTimeFormat('ru-RU', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  }).format(new Date(value))}`
}

function projectMemberInitials(name: string): string {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toLocaleUpperCase('ru-RU') ?? '')
    .join('')
}

async function changeContentType(): Promise<void> {
  form.dimensionValueIds = {}
  form.requiredProjectDimensionIds = []
  projectDimensions.value = []
  clearDimensionPaths()
  filtersLoadFailed.value = false
  showFilterErrors.value = false
  filterSettingsError.value = ''

  if (form.gameContentTypeId) {
    await loadProjectDimensions(form.gameContentTypeId)
  }
}

function selectedDimensionValues(dimensionId: number): number[] {
  return form.dimensionValueIds[dimensionId] ?? []
}

function dimensionSelectLevels(dimension: GameDimension): GameDimension['values'][] {
  const levels: GameDimension['values'][] = [
    dimension.values.filter((value) => value.parent_id === null),
  ]
  const path = dimensionPaths[dimension.id] ?? []

  for (const selectedValueId of path) {
    const children = dimension.values.filter((value) => value.parent_id === selectedValueId)

    if (children.length === 0) {
      break
    }

    levels.push(children)
  }

  return levels
}

function selectDimensionPathValue(dimension: GameDimension, level: number, event: Event): void {
  const valueId = Number((event.target as HTMLSelectElement).value)
  const path = [...(dimensionPaths[dimension.id] ?? [])].slice(0, level)

  if (!valueId) {
    dimensionPaths[dimension.id] = path

    if (dimension.selection_mode === 'single') {
      form.dimensionValueIds[dimension.id] = []
    }

    return
  }

  path[level] = valueId
  dimensionPaths[dimension.id] = path

  const hasChildren = dimension.values.some((value) => value.parent_id === valueId)

  if (hasChildren) {
    if (dimension.selection_mode === 'single') {
      form.dimensionValueIds[dimension.id] = []
    }

    return
  }

  if (dimension.selection_mode === 'multiple') {
    form.dimensionValueIds[dimension.id] = [
      ...new Set([...selectedDimensionValues(dimension.id), valueId]),
    ]
  } else {
    form.dimensionValueIds[dimension.id] = [valueId]
  }

  clearFilterSettingsError()
}

function removeDimensionValue(dimensionId: number, valueId: number): void {
  form.dimensionValueIds[dimensionId] = selectedDimensionValues(dimensionId).filter(
    (selectedValueId) => selectedValueId !== valueId,
  )
  dimensionPaths[dimensionId] = []
}

function dimensionValueName(dimension: GameDimension, valueId: number): string {
  return dimension.values.find((value) => value.id === valueId)?.name ?? `#${valueId}`
}

function initializeDimensionPaths(): void {
  clearDimensionPaths()

  for (const dimension of projectDimensions.value) {
    if (dimension.selection_mode !== 'single') {
      continue
    }

    const [selectedValueId] = selectedDimensionValues(dimension.id)

    if (!selectedValueId) {
      continue
    }

    const path: number[] = []
    let currentValue = dimension.values.find((value) => value.id === selectedValueId)

    while (currentValue) {
      path.unshift(currentValue.id)
      currentValue = currentValue.parent_id
        ? dimension.values.find((value) => value.id === currentValue?.parent_id)
        : undefined
    }

    dimensionPaths[dimension.id] = path
  }
}

function clearDimensionPaths(): void {
  for (const dimensionId of Object.keys(dimensionPaths)) {
    delete dimensionPaths[Number(dimensionId)]
  }
}

function dimensionHasError(dimension: GameDimension): boolean {
  return Boolean(
    showFilterErrors.value &&
      dimension.is_required &&
      selectedDimensionValues(dimension.id).length === 0,
  )
}

function clearFilterSettingsError(): void {
  const hasMissingRequiredValue = projectDimensions.value.some(
    (dimension) => dimension.is_required && selectedDimensionValues(dimension.id).length === 0,
  )

  if (!hasMissingRequiredValue) {
    filterSettingsError.value = ''
  }
}

async function chooseLogo(event: Event): Promise<void> {
  const input = event.target as HTMLInputElement
  const [file] = Array.from(input.files ?? [])

  logoError.value = ''

  if (!file) {
    clearLogo()

    return
  }

  const error = await validateLogo(file)

  if (error) {
    clearLogo()
    logoError.value = error

    return
  }

  form.logo = file
  setLogoPreview(URL.createObjectURL(file))
}

async function validateLogo(file: File): Promise<string> {
  if (!imageMimeTypes.includes(file.type)) {
    return 'Доступные типы файлов: JPG, PNG, WEBP.'
  }

  if (file.size > logoMaxBytes) {
    return 'Файл должен быть не больше 2 МБ.'
  }

  try {
    const dimensions = await getImageDimensions(file)

    if (dimensions.width > logoMaxWidth || dimensions.height > logoMaxHeight) {
      return `Изображение должно быть не больше ${logoMaxWidth}x${logoMaxHeight}px.`
    }
  } catch {
    return 'Не удалось прочитать изображение.'
  }

  return ''
}

function getImageDimensions(file: File): Promise<{ width: number; height: number }> {
  return new Promise((resolve, reject) => {
    const url = URL.createObjectURL(file)
    const image = new window.Image()

    image.onload = () => {
      URL.revokeObjectURL(url)
      resolve({ width: image.naturalWidth, height: image.naturalHeight })
    }

    image.onerror = () => {
      URL.revokeObjectURL(url)
      reject(new Error('Invalid image'))
    }

    image.src = url
  })
}

function openLogoPicker(): void {
  logoInput.value?.click()
}

function clearLogo(): void {
  form.logo = null
  form.logoUrl = null
  logoError.value = ''
  setLogoPreview(null)

  if (logoInput.value) {
    logoInput.value.value = ''
  }
}

function setLogoPreview(url: string | null): void {
  if (logoPreviewUrl.value?.startsWith('blob:')) {
    URL.revokeObjectURL(logoPreviewUrl.value)
  }

  logoPreviewUrl.value = url
}

async function addWebsiteUrl(): Promise<void> {
  form.websiteUrls.push('')

  await nextTick()
  websiteUrlInputs.value.at(-1)?.focus()
}

function removeWebsiteUrl(index: number): void {
  form.websiteUrls.splice(index, 1)
}

function setWebsiteUrlInput(
  element: Element | ComponentPublicInstance | null,
  index: number,
): void {
  if (element instanceof HTMLInputElement) {
    websiteUrlInputs.value[index] = element
  }
}

function addTag(): void {
  const tag = form.tagInput.trim()

  if (tag.length < 3 || form.tags.includes(tag)) {
    return
  }

  form.tags.push(tag)
  form.tagInput = ''
}

function removeTag(tag: string): void {
  form.tags = form.tags.filter((item) => item !== tag)
}

async function saveMainTab(): Promise<void> {
  const editor = summaryEditor.value

  titleError.value = ''
  logoError.value = ''
  summaryError.value = ''
  filterSettingsError.value = ''
  showFilterErrors.value = true

  if (!form.title.trim()) {
    titleError.value = 'Введите название проекта.'
  }

  if (!form.logo && !form.logoUrl) {
    logoError.value = 'Логотип обязателен.'
  }

  if (!editor || editor.isEmpty) {
    summaryError.value = 'Добавьте краткое описание.'
  }

  if (!form.gameContentTypeId) {
    filterSettingsError.value = 'Выберите тип контента.'
  } else if (filtersLoadFailed.value) {
    filterSettingsError.value = 'Не удалось загрузить настройки проекта.'
  } else if (
    projectDimensions.value.some(
      (dimension) => dimension.is_required && selectedDimensionValues(dimension.id).length === 0,
    )
  ) {
    filterSettingsError.value = 'Заполните обязательные настройки проекта.'
  }

  if (titleError.value || logoError.value || summaryError.value || filterSettingsError.value) {
    if (titleError.value) {
      titleInput.value?.focus()
    }

    return
  }

  if (!project.value || !editor || !form.gameContentTypeId) {
    return
  }

  form.summary = editor.getJSON()
  form.summaryFilled = true

  isSaving.value = true
  message.value = ''

  try {
    const updatedProject = await saveProjectDetails(project.value.id, {
      gameContentTypeId: form.gameContentTypeId,
      title: form.title,
      summary: form.summary,
      tags: form.tags,
      websiteUrls: form.websiteUrls.filter(Boolean),
      logo: form.logo,
      dimensionValueIds: [...new Set(Object.values(form.dimensionValueIds).flat())],
      percentageComplete: project.value.percentage_complete,
    })

    project.value = updatedProject
    form.logo = null
    form.logoUrl = updatedProject.logo_url
    setLogoPreview(updatedProject.logo_url)
    messageKind.value = 'success'
    message.value = 'Основное сохранено.'

    if (releaseFiltersLoaded.value) {
      releaseFiltersLoaded.value = false
      await loadReleaseFilters(true)
    }
  } catch {
    messageKind.value = 'error'
    message.value = 'Не удалось сохранить основное.'
  } finally {
    isSaving.value = false
  }
}

function richTextIsFilled(value: unknown): boolean {
  if (!value || typeof value !== 'object' || !('content' in value)) {
    return false
  }

  return Array.isArray(value.content) && value.content.length > 0
}

function normalizeStringArray(value: unknown): string[] {
  return Array.isArray(value)
    ? value.filter((item): item is string => typeof item === 'string')
    : []
}

onMounted(() => {
  void loadProject()
})

watch(activeTab, async (tab) => {
  if (tab === 'releases') {
    await Promise.all([loadReleaseFilters(), loadProjectReleases()])
  }

  if (tab === 'members') {
    await loadProjectMembers()
  }

  if (tab !== 'screenshots') {
    screenshotsSortable?.destroy()
    screenshotsSortable = null

    return
  }

  await nextTick()
  initializeScreenshotsSortable()
})

watch(memberModalSearch, () => {
  if (ignoreNextMemberSearchChange) {
    ignoreNextMemberSearchChange = false

    return
  }

  selectedMemberCandidate.value = null

  if (!isMemberModalOpen.value) {
    return
  }

  if (memberCandidateSearchTimer !== null) {
    window.clearTimeout(memberCandidateSearchTimer)
  }

  memberCandidateSearchTimer = window.setTimeout(() => {
    void searchMemberCandidates()
  }, 250)
})

onBeforeUnmount(() => {
  if (memberCandidateSearchTimer !== null) {
    window.clearTimeout(memberCandidateSearchTimer)
  }

  if (logoPreviewUrl.value?.startsWith('blob:')) {
    URL.revokeObjectURL(logoPreviewUrl.value)
  }

  clearSelectedScreenshots()
  screenshotsSortable?.destroy()
  summaryEditor.value?.destroy()
  descriptionEditor.value?.destroy()
})
</script>

<template>
  <AppShell>
    <section class="data-page project-edit-page">
      <p v-if="isLoading" class="project-edit-page__state">Загрузка...</p>

      <template v-else-if="project">
        <header class="project-edit-header">
          <RouterLink
            class="project-edit-header__back"
            :to="{ name: 'projects.show', params: { id: project.id } }"
          >
            <ArrowLeft :size="18" aria-hidden="true" />
            <span>Проект</span>
          </RouterLink>

          <div>
            <h1>{{ project.title }}</h1>
            <div class="project-edit-header__meta">
              <span v-if="project.game_name">{{ project.game_name }}</span>
              <span v-if="project.content_type_name">{{ project.content_type_name }}</span>
              <span v-if="project.status_label" class="project-edit-header__status">
                {{ project.status_label }}
              </span>
            </div>
          </div>
        </header>

        <p
          v-if="message"
          class="form-message"
          :class="{
            'project-form-message--success': messageKind === 'success',
            'project-form-message--error': messageKind === 'error',
          }"
        >
          {{ message }}
        </p>

        <div class="project-tabs" role="tablist" aria-label="Разделы редактирования проекта">
          <button
            class="project-tab"
            :class="{ 'project-tab--active': activeTab === 'main' }"
            type="button"
            role="tab"
            :aria-selected="activeTab === 'main'"
            @click="activeTab = 'main'"
          >
            Основное
          </button>
          <button
            class="project-tab"
            :class="{ 'project-tab--active': activeTab === 'description' }"
            type="button"
            role="tab"
            :aria-selected="activeTab === 'description'"
            @click="activeTab = 'description'"
          >
            Описание
          </button>
          <button
            class="project-tab"
            :class="{ 'project-tab--active': activeTab === 'licence' }"
            type="button"
            role="tab"
            :aria-selected="activeTab === 'licence'"
            @click="activeTab = 'licence'"
          >
            Лицензия
          </button>
          <button
            class="project-tab"
            :class="{ 'project-tab--active': activeTab === 'screenshots' }"
            type="button"
            role="tab"
            :aria-selected="activeTab === 'screenshots'"
            @click="activeTab = 'screenshots'"
          >
            Скриншоты
          </button>
          <button
            class="project-tab"
            :class="{ 'project-tab--active': activeTab === 'releases' }"
            type="button"
            role="tab"
            :aria-selected="activeTab === 'releases'"
            @click="activeTab = 'releases'"
          >
            Релизы
          </button>
          <button
            class="project-tab"
            :class="{ 'project-tab--active': activeTab === 'members' }"
            type="button"
            role="tab"
            :aria-selected="activeTab === 'members'"
            @click="activeTab = 'members'"
          >
            Участники
          </button>
        </div>

        <form
          v-if="activeTab === 'main'"
          class="project-form-panel form project-create-details__form"
          novalidate
          @submit.prevent="saveMainTab"
        >
          <div
            class="project-create-details__group project-create-details__group--first project-create-details__identity"
          >
            <label class="form-field">
              <span class="form-label">Автор</span>
              <select
                :value="authorKey"
                class="form-control"
                required
                disabled
              >
                <option
                  v-for="option in authorOptions"
                  :key="optionKey(option)"
                  :value="optionKey(option)"
                >
                  {{ option.label }}
                </option>
              </select>
              <span class="game-form-help">
                Автор зафиксирован после первого сохранения проекта.
              </span>
            </label>

            <label class="form-field">
              <span class="form-label">Название</span>
              <input
                ref="titleInput"
                v-model.trim="form.title"
                class="form-control"
                type="text"
                maxlength="128"
                required
                placeholder="Название проекта"
                :aria-invalid="titleError ? 'true' : undefined"
                @input="titleError = ''"
              />
              <span v-if="titleError" class="field-error">{{ titleError }}</span>
            </label>
          </div>

          <div class="form-field project-create-details__group">
            <span class="form-label">Логотип</span>
            <div
              class="game-upload game-upload--logo project-create-details__logo"
              :class="{
                'game-upload--invalid': logoError,
                'game-upload--empty': !logoPreviewUrl,
              }"
              @click="!logoPreviewUrl ? openLogoPicker() : undefined"
            >
              <input
                ref="logoInput"
                type="file"
                :accept="imageAccept"
                required
                @change="chooseLogo"
              />

              <img
                v-if="logoPreviewUrl"
                class="game-upload__preview"
                :src="logoPreviewUrl"
                alt="Предпросмотр логотипа проекта"
              />

              <span v-if="!logoPreviewUrl" class="game-upload__icon">
                <ImageIcon :size="24" :stroke-width="1.9" aria-hidden="true" />
              </span>
              <span v-if="!logoPreviewUrl" class="game-upload__content">
                <span class="game-upload__title">Выберите изображение</span>
                <span class="game-upload__file">JPG, PNG или WEBP</span>
              </span>

              <div v-if="logoPreviewUrl" class="game-upload__actions">
                <button
                  class="game-upload__action icon-action"
                  type="button"
                  aria-label="Заменить логотип"
                  title="Заменить логотип"
                  @click.stop="openLogoPicker"
                >
                  <Pencil :size="17" :stroke-width="2" aria-hidden="true" />
                </button>
                <button
                  class="game-upload__action icon-action icon-action--danger"
                  type="button"
                  aria-label="Удалить логотип"
                  title="Удалить логотип"
                  @click.stop="clearLogo"
                >
                  <Trash2 :size="17" :stroke-width="2" aria-hidden="true" />
                </button>
              </div>
            </div>
            <p class="game-form-help" :class="{ 'game-form-help--error': logoError }">
              {{ logoError || 'Обязательное изображение до 2 МБ и 1024x1024 пикселей.' }}
            </p>
          </div>

          <div class="form-field project-create-details__group">
            <span class="form-label">Краткое описание</span>
            <div
              class="game-editor"
              :class="{ 'project-create-details__editor--invalid': summaryError }"
            >
              <div v-if="summaryEditor" class="game-editor__toolbar">
                <button
                  class="game-editor__button"
                  :class="{ 'is-active': summaryEditor.isActive('heading', { level: 2 }) }"
                  type="button"
                  title="Заголовок"
                  @click="summaryEditor.chain().focus().toggleHeading({ level: 2 }).run()"
                >
                  <Heading2 :size="17" :stroke-width="1.9" aria-hidden="true" />
                </button>
                <button
                  class="game-editor__button"
                  :class="{ 'is-active': summaryEditor.isActive('bold') }"
                  type="button"
                  title="Жирный"
                  @click="summaryEditor.chain().focus().toggleBold().run()"
                >
                  <Bold :size="17" :stroke-width="2.2" aria-hidden="true" />
                </button>
                <button
                  class="game-editor__button"
                  :class="{ 'is-active': summaryEditor.isActive('italic') }"
                  type="button"
                  title="Курсив"
                  @click="summaryEditor.chain().focus().toggleItalic().run()"
                >
                  <Italic :size="17" :stroke-width="2.2" aria-hidden="true" />
                </button>
              </div>
              <EditorContent
                class="game-editor__body game-editor__body--compact"
                :editor="summaryEditor"
              />
            </div>
            <span v-if="summaryError" class="field-error">{{ summaryError }}</span>
          </div>

          <div class="form-field project-create-details__group">
            <div class="project-filter-settings__heading">
              <span class="form-label">Настройки проекта</span>
              <span>Показываются только фильтры, относящиеся к проекту.</span>
            </div>

            <label class="form-field project-filter-settings__content-type">
              <span class="form-label">Тип контента</span>
              <select
                v-model.number="form.gameContentTypeId"
                class="form-control"
                :aria-invalid="!form.gameContentTypeId && showFilterErrors ? 'true' : undefined"
                @change="changeContentType"
              >
                <option :value="null" disabled>Выберите тип контента</option>
                <option
                  v-for="contentType in contentTypeOptions"
                  :key="contentType.id"
                  :value="contentType.id"
                >
                  {{ contentType.content_type_name ?? 'Тип контента' }}
                </option>
              </select>
            </label>

            <p v-if="isFiltersLoading" class="project-filter-settings__message">
              Загрузка настроек...
            </p>

            <div v-else-if="projectDimensions.length > 0" class="project-filter-settings__grid">
              <div
                v-for="dimension in projectDimensions"
                :key="dimension.id"
                class="project-filter-settings__field"
              >
                <div class="project-filter-settings__field-heading">
                  <span class="form-label">
                    {{ dimension.name }}
                    <span v-if="dimension.is_required" class="project-filter-settings__required">
                      обязательно
                    </span>
                  </span>
                  <span v-if="dimension.selection_mode === 'multiple'"
                    >Можно выбрать несколько</span
                  >
                </div>

                <div class="project-filter-settings__controls">
                  <select
                    v-for="(values, level) in dimensionSelectLevels(dimension)"
                    :key="level"
                    class="form-control project-filter-settings__select"
                    :value="dimensionPaths[dimension.id]?.[level] ?? ''"
                    :aria-label="`${dimension.name}, уровень ${level + 1}`"
                    :aria-invalid="dimensionHasError(dimension) ? 'true' : undefined"
                    @change="selectDimensionPathValue(dimension, level, $event)"
                  >
                    <option value="">
                      {{ level === 0 ? 'Выберите значение' : 'Выберите дочернее значение' }}
                    </option>
                    <option v-for="value in values" :key="value.id" :value="value.id">
                      {{ value.name }}
                    </option>
                  </select>

                  <div
                    v-if="
                      dimension.selection_mode === 'multiple' &&
                      selectedDimensionValues(dimension.id).length > 0
                    "
                    class="project-filter-settings__selected"
                    aria-label="Выбранные значения"
                  >
                    <button
                      v-for="valueId in selectedDimensionValues(dimension.id)"
                      :key="valueId"
                      class="project-filter-settings__selected-value"
                      type="button"
                      :title="`Удалить ${dimensionValueName(dimension, valueId)}`"
                      @click="removeDimensionValue(dimension.id, valueId)"
                    >
                      {{ dimensionValueName(dimension, valueId) }}
                      <span aria-hidden="true">×</span>
                    </button>
                  </div>

                  <span v-if="dimensionHasError(dimension)" class="field-error">
                    Выберите значение.
                  </span>
                </div>
              </div>
            </div>

            <p
              v-else-if="form.gameContentTypeId && !filtersLoadFailed"
              class="project-filter-settings__message"
            >
              Для этого типа контента нет настроек уровня проекта.
            </p>

            <span v-if="filterSettingsError" class="field-error">{{ filterSettingsError }}</span>
          </div>

          <div class="form-field project-create-details__group">
            <span class="form-label">Ссылки</span>

            <div v-if="form.websiteUrls.length > 0" class="project-link-list">
              <div v-for="(_, index) in form.websiteUrls" :key="index" class="project-link-field">
                <input
                  :ref="(element) => setWebsiteUrlInput(element, index)"
                  v-model="form.websiteUrls[index]"
                  class="project-link-field__input"
                  type="url"
                  pattern="https://.*"
                  placeholder="https://example.com"
                />
                <button
                  class="project-link-field__remove"
                  type="button"
                  aria-label="Удалить ссылку"
                  title="Удалить ссылку"
                  @click="removeWebsiteUrl(index)"
                >
                  <Trash2 :size="17" :stroke-width="1.9" aria-hidden="true" />
                </button>
              </div>
            </div>

            <button class="project-link-add" type="button" @click="addWebsiteUrl">
              <Plus :size="17" :stroke-width="1.9" aria-hidden="true" />
              <span>Добавить ссылку</span>
            </button>
          </div>

          <label
            class="form-field project-create-details__group project-create-details__group--last"
          >
            <span class="form-label">Теги</span>
            <input
              v-model="form.tagInput"
              class="form-control"
              type="text"
              placeholder="Введите тег и нажмите Enter"
              @keydown.enter.prevent="addTag"
            />
            <span v-if="form.tags.length > 0" class="project-tag-list">
              <button
                v-for="tag in form.tags"
                :key="tag"
                class="project-tag"
                type="button"
                :title="`Удалить ${tag}`"
                @click="removeTag(tag)"
              >
                {{ tag }}
              </button>
            </span>
          </label>

          <div class="project-create-details__actions">
            <RouterLink
              class="button"
              :to="{ name: 'projects.show', params: { id: project.id } }"
            >
              <ArrowLeft :size="18" :stroke-width="1.9" aria-hidden="true" />
              <span>Назад</span>
            </RouterLink>

            <button class="button button-primary" type="submit" :disabled="isSaving">
              <Save :size="18" :stroke-width="1.9" aria-hidden="true" />
              <span>{{ isSaving ? 'Сохранение...' : 'Сохранить' }}</span>
            </button>
          </div>
        </form>

        <form
          v-else-if="activeTab === 'description'"
          class="project-form-panel form project-create-description__form"
          novalidate
          @submit.prevent="saveDescriptionTab"
        >
          <div class="form-field project-create-description__field">
            <span class="form-label">Описание</span>

            <div
              class="game-editor"
              :class="{ 'project-create-description__editor--invalid': descriptionError }"
            >
              <div v-if="descriptionEditor" class="game-editor__toolbar">
                <button
                  class="game-editor__button"
                  :class="{ 'is-active': descriptionEditor.isActive('heading', { level: 2 }) }"
                  type="button"
                  title="Заголовок"
                  @click="descriptionEditor.chain().focus().toggleHeading({ level: 2 }).run()"
                >
                  <Heading2 :size="17" :stroke-width="1.9" aria-hidden="true" />
                </button>
                <button
                  class="game-editor__button"
                  :class="{ 'is-active': descriptionEditor.isActive('bold') }"
                  type="button"
                  title="Жирный"
                  @click="descriptionEditor.chain().focus().toggleBold().run()"
                >
                  <Bold :size="17" :stroke-width="2.2" aria-hidden="true" />
                </button>
                <button
                  class="game-editor__button"
                  :class="{ 'is-active': descriptionEditor.isActive('italic') }"
                  type="button"
                  title="Курсив"
                  @click="descriptionEditor.chain().focus().toggleItalic().run()"
                >
                  <Italic :size="17" :stroke-width="2.2" aria-hidden="true" />
                </button>
                <button
                  class="game-editor__button"
                  :class="{ 'is-active': descriptionEditor.isActive('bulletList') }"
                  type="button"
                  title="Маркированный список"
                  @click="descriptionEditor.chain().focus().toggleBulletList().run()"
                >
                  <List :size="17" :stroke-width="1.9" aria-hidden="true" />
                </button>
                <button
                  class="game-editor__button"
                  :class="{ 'is-active': descriptionEditor.isActive('orderedList') }"
                  type="button"
                  title="Нумерованный список"
                  @click="descriptionEditor.chain().focus().toggleOrderedList().run()"
                >
                  <ListOrdered :size="17" :stroke-width="1.9" aria-hidden="true" />
                </button>
              </div>
              <EditorContent class="game-editor__body" :editor="descriptionEditor" />
            </div>

            <span v-if="descriptionError" class="field-error">{{ descriptionError }}</span>
          </div>

          <div class="project-create-description__actions">
            <RouterLink
              class="button"
              :to="{ name: 'projects.show', params: { id: project.id } }"
            >
              <ArrowLeft :size="18" :stroke-width="1.9" aria-hidden="true" />
              <span>Назад</span>
            </RouterLink>

            <button class="button button-primary" type="submit" :disabled="isSaving">
              <Save :size="18" :stroke-width="1.9" aria-hidden="true" />
              <span>{{ isSaving ? 'Сохранение...' : 'Сохранить' }}</span>
            </button>
          </div>
        </form>

        <form
          v-else-if="activeTab === 'licence'"
          class="project-form-panel form project-create-licence__form"
          novalidate
          @submit.prevent="saveLicenceTab"
        >
          <div class="project-create-licence__content">
            <div class="project-create-licence__fields">
              <label class="form-field project-create-licence__field">
                <span class="form-label">Название лицензии</span>
                <select
                  v-model="form.licenceName"
                  class="form-control"
                  :disabled="licencesLoading || licencesError !== ''"
                >
                  <option value="">
                    {{ licencesLoading ? 'Загрузка...' : licencesError || 'Не выбрана' }}
                  </option>
                  <option
                    v-for="licence in licenceOptions"
                    :key="licence.id"
                    :value="licence.id"
                  >
                    {{ licence.name }} ({{ licence.id }})
                  </option>
                </select>
                <span
                  class="project-create-licence__help"
                  :class="{ 'field-error': licencesError }"
                >
                  {{ licencesError || 'Выберите точное название из списка, чтобы избежать ошибок.' }}
                </span>
              </label>
            </div>

            <aside class="project-create-licence__note" aria-label="Информация о лицензиях">
              <span class="project-create-licence__note-icon" aria-hidden="true">
                <Info :size="21" :stroke-width="2" />
              </span>
              <div>
                <strong>О лицензии</strong>
                <p>
                  Лицензия определяет, как другие пользователи могут использовать, изменять и
                  распространять проект. Если подходящего варианта нет, выберите собственную
                  лицензию и опишите дополнительные условия на странице проекта.
                </p>
              </div>
            </aside>
          </div>

          <div class="project-create-licence__actions">
            <RouterLink
              class="button"
              :to="{ name: 'projects.show', params: { id: project.id } }"
            >
              <ArrowLeft :size="18" :stroke-width="1.9" aria-hidden="true" />
              <span>Назад</span>
            </RouterLink>

            <button class="button button-primary" type="submit" :disabled="isSaving">
              <Save :size="18" :stroke-width="1.9" aria-hidden="true" />
              <span>{{ isSaving ? 'Сохранение...' : 'Сохранить' }}</span>
            </button>
          </div>
        </form>

        <section
          v-else-if="activeTab === 'screenshots'"
          class="project-form-panel project-edit-empty-tab"
          aria-label="Скриншоты"
        >
          <input
            ref="screenshotsInput"
            class="project-screenshots-input"
            type="file"
            :accept="imageAccept"
            multiple
            @change="chooseScreenshots"
          />

          <div ref="screenshotsGrid" class="project-screenshots-grid">
            <button
              class="project-card project-card--add project-screenshots-add"
              type="button"
              aria-label="Добавить скриншоты"
              title="Добавить скриншоты"
              @click="openScreenshotsPicker"
            >
              <span class="project-card__add-media" aria-hidden="true"></span>
              <Plus
                class="project-card__add-icon"
                :size="58"
                :stroke-width="2.1"
                aria-hidden="true"
              />
            </button>

            <article
              v-for="(screenshot, index) in form.screenshots"
              :key="screenshot.id"
              class="project-screenshot-card project-screenshot-card--persisted"
              :data-screenshot-id="screenshot.id"
            >
              <img :src="screenshot.url" :alt="`Скриншот проекта ${index + 1}`" />
              <button
                class="icon-action icon-action--danger project-screenshot-card__remove"
                type="button"
                :disabled="deletingScreenshotId !== null"
                aria-label="Удалить скриншот"
                title="Удалить скриншот"
                @click.stop="removePersistedScreenshot(screenshot)"
              >
                <Trash2 :size="16" :stroke-width="2" aria-hidden="true" />
              </button>
            </article>

            <article
              v-for="(previewUrl, index) in selectedScreenshotPreviewUrls"
              :key="`selected-${previewUrl}`"
              class="project-screenshot-card project-screenshot-card--pending"
            >
              <img :src="previewUrl" :alt="`Новый скриншот проекта ${index + 1}`" />
              <button
                class="icon-action icon-action--danger project-screenshot-card__remove"
                type="button"
                aria-label="Убрать скриншот"
                title="Убрать скриншот"
                @click="removeSelectedScreenshot(index)"
              >
                <Trash2 :size="16" :stroke-width="2" aria-hidden="true" />
              </button>
            </article>
          </div>

          <p v-if="screenshotsError" class="field-error">{{ screenshotsError }}</p>

          <div class="project-create-details__actions project-screenshots-actions">
            <RouterLink
              class="button"
              :to="{ name: 'projects.show', params: { id: project.id } }"
            >
              <ArrowLeft :size="18" :stroke-width="1.9" aria-hidden="true" />
              <span>Назад</span>
            </RouterLink>

            <button
              class="button button-primary"
              type="button"
              :disabled="isSaving || selectedScreenshotFiles.length === 0"
              @click="saveScreenshotsTab"
            >
              <Save :size="18" :stroke-width="1.9" aria-hidden="true" />
              <span>{{ isSaving ? 'Сохранение...' : 'Сохранить' }}</span>
            </button>
          </div>
        </section>

        <template v-else-if="activeTab === 'releases'">
          <section class="game-filters project-releases-filters" aria-label="Фильтры релизов">
            <div class="game-filter-top">
              <SearchField
                v-model="releaseFilterState.search"
                placeholder="Поиск по релизам"
              />

              <button
                class="game-filter-advanced"
                :class="{ 'game-filter-advanced--active': releaseAdvancedFiltersOpen }"
                type="button"
                title="Расширенные настройки"
                :aria-pressed="releaseAdvancedFiltersOpen"
                @click="toggleReleaseAdvancedFilters"
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

              <label class="game-filter-field">
                <span class="game-filter-field__label">Статус</span>
                <select v-model="releaseFilterState.status" class="game-filter-field__control">
                  <option value="">Все статусы</option>
                  <option
                    v-for="option in releaseStatusOptions"
                    :key="option.value"
                    :value="option.value"
                  >
                    {{ option.label }}
                  </option>
                </select>
              </label>

              <template v-if="releaseFiltersLoading">
                <label class="game-filter-field">
                  <span class="game-filter-field__label">Фильтры</span>
                  <select class="game-filter-field__control" disabled>
                    <option>Загрузка...</option>
                  </select>
                </label>
              </template>

              <template v-else>
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
              </template>

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

            <p v-if="releaseFiltersError" class="field-error">{{ releaseFiltersError }}</p>
          </section>

          <div
            class="game-results-layout project-releases-layout"
            :class="{ 'game-results-layout--with-panel': releaseAdvancedFiltersOpen }"
          >
            <section
              class="project-form-panel project-releases-content"
              aria-label="Список релизов"
            >
              <p v-if="projectReleasesLoading" class="project-releases-state">Загрузка релизов...</p>

              <p v-else-if="projectReleasesError" class="field-error">
                {{ projectReleasesError }}
              </p>

              <ProjectReleasesTable
                v-else-if="visibleProjectReleases.length > 0"
                :releases="visibleProjectReleases"
                :release-filters="releaseFilters"
                :columns="visibleReleaseColumns"
                @edit-release="openReleaseEditPage"
                @delete-release="openReleaseDeleteModal"
              />

              <div v-else-if="projectReleases.length === 0" class="project-releases-empty">
                <span class="project-releases-empty__icon" aria-hidden="true">
                  <Rocket :size="34" :stroke-width="1.8" />
                </span>
                <div class="project-releases-empty__copy">
                  <strong>Добавьте первый релиз</strong>
                  <p>Релизы помогут пользователям отслеживать версии и изменения проекта.</p>
                </div>
                <RouterLink
                  class="button button-primary"
                  :to="{ name: 'projects.releases.create', params: { id: project.id } }"
                >
                  <Plus :size="18" :stroke-width="1.9" aria-hidden="true" />
                  <span>Добавить релиз</span>
                </RouterLink>
              </div>

              <p v-else class="project-releases-state">Релизы не найдены по текущим фильтрам.</p>
            </section>

            <aside
              class="game-advanced-panel"
              :class="{ 'game-advanced-panel--open': releaseAdvancedFiltersOpen }"
              :aria-hidden="!releaseAdvancedFiltersOpen"
              aria-label="Расширенные настройки релизов"
            >
              <label class="game-filter-field">
                <span class="game-filter-field__label">Сортировка</span>
                <select v-model="releaseSort" class="game-filter-field__control">
                  <option
                    v-for="option in releaseSortOptions"
                    :key="option.value"
                    :value="option.value"
                  >
                    {{ option.label }}
                  </option>
                </select>
              </label>

              <label class="game-filter-field">
                <span class="game-filter-field__label">Вид</span>
                <select v-model="releasePageSize" class="game-filter-field__control">
                  <option v-for="option in releasePageSizeOptions" :key="option" :value="option">
                    {{ option }}
                  </option>
                </select>
              </label>
            </aside>
          </div>
        </template>

        <template v-else-if="activeTab === 'members'">
          <section class="game-filters project-members-filters" aria-label="Фильтры участников">
            <div class="game-filter-top project-members-filters__top">
              <SearchField
                v-model="memberFilterState.search"
                placeholder="Поиск по участникам"
              />

              <button class="button button-primary" type="button" @click="openProjectMemberModal">
                <Plus :size="18" :stroke-width="1.9" aria-hidden="true" />
                <span>Добавить участника</span>
              </button>
            </div>

            <div class="game-filter-row project-members-filters__row">
              <label class="game-filter-field">
                <span class="game-filter-field__label">Роль</span>
                <select v-model="memberFilterState.role" class="game-filter-field__control">
                  <option value="">Все роли</option>
                  <option
                    v-for="option in memberRoleOptions"
                    :key="option.value"
                    :value="option.value"
                  >
                    {{ option.label }}
                  </option>
                </select>
              </label>

              <label class="game-filter-field">
                <span class="game-filter-field__label">Статус</span>
                <select v-model="memberFilterState.status" class="game-filter-field__control">
                  <option value="">Все статусы</option>
                  <option
                    v-for="option in memberStatusOptions"
                    :key="option.value"
                    :value="option.value"
                  >
                    {{ option.label }}
                  </option>
                </select>
              </label>
            </div>
          </section>

          <section
            class="project-form-panel project-members-content"
            aria-label="Участники проекта"
          >
            <p v-if="projectMembersLoading" class="project-releases-state">Загрузка участников...</p>

            <p v-else-if="projectMembersError" class="field-error">
              {{ projectMembersError }}
            </p>

            <div v-else-if="visibleProjectMemberCards.length > 0" class="project-members-grid">
              <article
                v-for="member in visibleProjectMemberCards"
                :key="member.id"
                class="project-member-card"
              >
                <header class="project-member-card__header">
                  <span class="project-member-card__avatar" aria-hidden="true">
                    <img v-if="member.avatarUrl" :src="member.avatarUrl" :alt="member.name" />
                    <span v-else>{{ projectMemberInitials(member.name) }}</span>
                  </span>

                  <div class="project-member-card__identity">
                    <strong>{{ member.name }}</strong>
                    <span>{{ formatProjectMemberDate(member.createdAt) }}</span>
                  </div>

                  <span class="project-member-card__meta">
                    <span class="project-member-card__role">{{ member.roleLabel }}</span>
                    <Clock
                      v-if="member.status === 'invited'"
                      class="project-member-card__invited"
                      :size="18"
                      :stroke-width="2"
                      aria-hidden="true"
                    />
                    <Building2
                      v-else-if="member.accessSource === 'organization'"
                      class="project-member-card__organization"
                      :size="18"
                      :stroke-width="2"
                      aria-hidden="true"
                    />
                  </span>
                </header>

                <div class="project-member-card__permissions">
                  <span>Заготовка под «Права доступа»</span>
                  <button
                    class="project-member-card__edit"
                    type="button"
                    :disabled="member.status !== 'active'"
                    aria-label="Редактировать участника"
                    title="Редактировать"
                  >
                    <Pencil :size="17" :stroke-width="1.9" aria-hidden="true" />
                  </button>
                </div>
              </article>
            </div>

            <p v-else class="project-releases-state">Участники не найдены по текущим фильтрам.</p>
          </section>
        </template>
      </template>

      <p v-else-if="message" class="project-edit-page__state">{{ message }}</p>
    </section>

    <DeleteModal
      :open="pendingDeleteRelease !== null"
      title="Удалить релиз"
      :description="releaseDeleteModalDescription"
      :loading="deletingReleaseId !== null"
      @cancel="closeReleaseDeleteModal"
      @confirm="confirmReleaseDelete"
    />

    <Transition name="modal">
      <div v-if="isMemberModalOpen" class="modal project-member-modal" role="presentation">
        <div class="modal__backdrop" @click="closeProjectMemberModal" />

        <section
          class="modal__dialog project-member-modal__dialog"
          role="dialog"
          aria-modal="true"
          aria-labelledby="project-member-modal-title"
        >
          <div class="modal__content project-member-modal__content">
            <div class="modal__icon project-member-modal__icon" aria-hidden="true">
              <UserPlus :size="34" :stroke-width="1.9" />
            </div>

            <div class="modal__copy">
              <h3 id="project-member-modal-title">Добавить участника</h3>
              <p>Найдите активного пользователя по никнейму или имени профиля.</p>
            </div>
          </div>

          <div class="project-member-modal__body">
            <label class="project-member-modal__field">
              <span>Пользователь</span>
              <input
                v-model="memberModalSearch"
                class="project-member-modal__input"
                type="search"
                placeholder="Введите никнейм или имя"
                autocomplete="off"
              />

              <div
                v-if="memberCandidates.length > 0"
                class="project-member-modal__list"
                role="listbox"
              >
                <button
                  v-for="candidate in memberCandidates"
                  :key="candidate.id"
                  class="project-member-modal__option"
                  type="button"
                  @click="selectMemberCandidate(candidate)"
                >
                  <span class="project-member-modal__avatar" aria-hidden="true">
                    <img
                      v-if="candidate.avatar_url"
                      :src="candidate.avatar_url"
                      :alt="candidate.display_name || candidate.username"
                    />
                    <span v-else>{{
                      projectMemberInitials(candidate.display_name || candidate.username)
                    }}</span>
                  </span>
                  <span class="project-member-modal__option-copy">
                    <strong>{{ candidate.display_name || candidate.username }}</strong>
                    <span>@{{ candidate.username }}</span>
                  </span>
                </button>
              </div>
            </label>

            <p
              v-if="
                memberModalSearch.trim().length >= 2
                  && !memberCandidatesLoading
                  && selectedMemberCandidate === null
              "
              class="project-member-modal__empty"
            >
              Пользователи не найдены.
            </p>

            <p v-if="memberCandidatesLoading" class="project-member-modal__hint">Поиск...</p>
            <p v-if="memberCandidatesError" class="project-member-modal__error">
              {{ memberCandidatesError }}
            </p>
            <p v-if="projectMemberModalError" class="project-member-modal__error">
              {{ projectMemberModalError }}
            </p>
          </div>

          <footer class="modal__actions">
            <button
              class="modal__button modal__button--secondary"
              type="button"
              :disabled="addingProjectMember"
              @click="closeProjectMemberModal"
            >
              Отмена
            </button>

            <button
              class="modal__button modal__button--secondary"
              type="button"
              :disabled="addingProjectMember || selectedMemberCandidate === null"
              @click="submitProjectMember(true)"
            >
              Добавить еще
            </button>

            <button
              class="modal__button project-member-modal__button--primary"
              type="button"
              :disabled="addingProjectMember || selectedMemberCandidate === null"
              @click="submitProjectMember(false)"
            >
              Добавить
            </button>
          </footer>
        </section>
      </div>
    </Transition>
  </AppShell>
</template>

<style scoped>
.project-edit-page,
.project-edit-header {
  display: grid;
  gap: 18px;
}

.project-edit-header__back {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  width: fit-content;
  color: var(--color-primary);
  text-decoration: none;
}

.project-edit-header h1 {
  margin: 0;
  font-size: clamp(28px, 4vw, 40px);
  letter-spacing: -0.03em;
}

.project-edit-header__meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px 14px;
  margin-top: 8px;
  color: var(--color-text-muted);
  font-size: 14px;
}

.project-edit-header__status {
  padding: 4px 9px;
  color: var(--color-success);
  border: 1px solid currentColor;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
}

.project-edit-page__state {
  margin: 0;
  color: var(--color-text-muted);
}

.project-edit-page :deep(.project-form-panel.project-create-details__form),
.project-edit-page :deep(.project-form-panel.project-create-description__form),
.project-edit-page :deep(.project-form-panel.project-create-licence__form),
.project-edit-page :deep(.project-form-panel.project-edit-empty-tab),
.project-edit-page :deep(.project-form-panel.project-releases-content),
.project-edit-page :deep(.project-form-panel.project-members-content) {
  max-width: none;
}

.project-edit-empty-tab {
  min-height: 220px;
}

.project-screenshots-input {
  position: absolute;
  width: 1px;
  height: 1px;
  opacity: 0;
  pointer-events: none;
}

.project-screenshots-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 320px));
  gap: 24px;
  align-items: stretch;
  justify-content: start;
}

.project-screenshots-add {
  grid-template-rows: auto;
  place-items: center;
  width: 100%;
  min-height: 0;
  aspect-ratio: 16 / 9;
  padding: 0;
}

.project-screenshots-add .project-card__add-media {
  position: absolute;
  inset: 0;
  width: 100%;
  aspect-ratio: auto;
}

.project-screenshots-add .project-card__add-icon {
  position: relative;
  z-index: 1;
  top: auto;
  left: auto;
  transform: none;
}

.project-screenshot-card {
  position: relative;
  width: 100%;
  min-height: 0;
  overflow: hidden;
  aspect-ratio: 16 / 9;
  background: var(--color-bg-soft);
  border: 1px solid var(--color-border-soft);
  border-radius: 8px;
  box-shadow: var(--shadow-sm);
}

.project-screenshot-card img {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.project-screenshot-card--pending {
  border-color: color-mix(in srgb, var(--color-primary) 38%, var(--color-border-soft));
}

.project-screenshot-card--persisted {
  cursor: grab;
}

.project-screenshot-card--chosen {
  border-color: var(--color-primary);
  box-shadow: var(--shadow-md);
}

.project-screenshot-card--ghost {
  opacity: 0.45;
}

.project-screenshot-card--dragging {
  cursor: grabbing;
}

.project-screenshot-card__remove {
  position: absolute;
  top: 10px;
  right: 10px;
}

.project-screenshots-actions {
  margin-top: 24px;
}

.project-releases-filters {
  margin-bottom: 0;
}

.project-releases-filters .game-filter-top {
  display: grid;
  grid-template-columns: minmax(260px, 1fr) auto auto;
  gap: 12px;
  align-items: center;
}

.project-releases-filters .game-filter-columns {
  position: relative;
  flex: 0 0 auto;
}

.project-releases-filters .game-filter-columns > summary {
  list-style: none;
}

.project-releases-filters .game-filter-columns > summary::-webkit-details-marker {
  display: none;
}

.project-releases-filters .data-toolbar__columns-menu {
  position: absolute;
  top: calc(100% + 8px);
  right: 0;
  z-index: 20;
  display: grid;
  gap: 6px;
  min-width: 220px;
  padding: 10px;
  background: var(--color-surface);
  border: 1px solid var(--color-border-soft);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-md);
}

.project-releases-filters .data-toolbar__column-option {
  display: flex;
  align-items: center;
  gap: 9px;
  min-height: 34px;
  padding: 0 8px;
  color: var(--color-text);
  border-radius: var(--radius-sm);
  cursor: pointer;
}

.project-releases-filters .data-toolbar__column-option:hover {
  background: var(--color-bg-soft);
}

.project-releases-filters__row {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  align-items: end;
}

.project-releases-filters__row .game-filter-field {
  flex: 1 1 180px;
}

.project-releases-filters__row .game-filter-reset {
  margin-left: auto;
}

.project-releases-layout {
  align-items: stretch;
}

.project-releases-content {
  display: grid;
  align-items: start;
  justify-items: stretch;
  width: 100%;
  min-height: 540px;
  color: var(--color-text-muted);
}

.project-releases-state {
  place-self: center;
  margin: 0;
}

.project-releases-empty {
  display: grid;
  place-self: center;
  justify-items: center;
  gap: 16px;
  width: min(100%, 420px);
  text-align: center;
}

.project-releases-empty__icon {
  display: grid;
  place-items: center;
  width: 64px;
  height: 64px;
  color: var(--color-primary);
  background: color-mix(in srgb, var(--color-primary) 10%, transparent);
  border: 1px solid color-mix(in srgb, var(--color-primary) 24%, var(--color-border-soft));
  border-radius: 50%;
}

.project-releases-empty__copy {
  display: grid;
  gap: 6px;
}

.project-releases-empty__copy strong {
  color: var(--color-text);
  font-size: 18px;
}

.project-releases-empty__copy p {
  margin: 0;
  font-size: 14px;
  line-height: 1.55;
}

.project-members-filters {
  margin-bottom: 0;
}

.project-members-filters__top {
  display: grid;
  grid-template-columns: minmax(260px, 1fr) auto;
  gap: 12px;
  align-items: center;
}

.project-members-filters__row {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  align-items: end;
}

.project-members-filters__row .game-filter-field {
  flex: 1 1 180px;
  max-width: 320px;
}

.project-members-content {
  width: 100%;
  min-height: 380px;
}

.project-members-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 18px;
  width: 100%;
}

.project-member-card {
  display: grid;
  gap: 16px;
  min-height: 178px;
  padding: 22px;
  background: var(--color-surface);
  border: 1px solid var(--color-border-soft);
  border-radius: 8px;
}

.project-member-card__header {
  display: grid;
  grid-template-columns: 44px minmax(0, 1fr) auto;
  gap: 12px;
  align-items: center;
}

.project-member-card__avatar {
  overflow: hidden;
  display: grid;
  place-items: center;
  width: 44px;
  height: 44px;
  color: var(--color-primary);
  background: color-mix(in srgb, var(--color-primary) 10%, transparent);
  border: 1px solid color-mix(in srgb, var(--color-primary) 24%, var(--color-border-soft));
  border-radius: 50%;
  font-size: 14px;
  font-weight: 750;
}

.project-member-card__avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.project-member-card__identity {
  display: grid;
  gap: 3px;
  min-width: 0;
}

.project-member-card__identity strong {
  overflow: hidden;
  color: var(--color-text);
  font-size: 15px;
  font-weight: 650;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.project-member-card__identity span {
  color: var(--color-text-muted);
  font-size: 13px;
}

.project-member-card__meta {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.project-member-card__role {
  display: inline-flex;
  align-items: center;
  min-height: 28px;
  padding: 0 9px;
  color: var(--color-primary);
  background: color-mix(in srgb, var(--color-primary) 10%, transparent);
  border: 1px solid color-mix(in srgb, var(--color-primary) 24%, var(--color-border-soft));
  border-radius: 999px;
  font-size: 12px;
  font-weight: 750;
  white-space: nowrap;
}

.project-member-card__invited {
  flex: 0 0 auto;
  color: var(--color-warning);
}

.project-member-card__organization {
  flex: 0 0 auto;
  color: var(--color-primary);
}

.project-member-card__permissions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding-top: 14px;
  color: var(--color-text-muted);
  border-top: 1px solid var(--color-border-soft);
  font-size: 13px;
}

.project-member-card__edit {
  display: inline-grid;
  place-items: center;
  flex: 0 0 auto;
  width: 30px;
  height: 30px;
  color: var(--color-text-muted);
  background: var(--color-bg-soft);
  border: 1px solid var(--color-border-soft);
  border-radius: var(--radius-sm);
  cursor: pointer;
}

.project-member-card__edit:hover:not(:disabled) {
  color: var(--color-text);
  background: var(--color-surface-hover);
  border-color: var(--color-border);
}

.project-member-card__edit:disabled {
  cursor: not-allowed;
  opacity: 0.48;
}

.project-member-modal__dialog {
  width: min(100%, 560px);
}

.project-member-modal__content {
  align-items: start;
}

.project-member-modal__icon {
  color: var(--color-primary);
  background: color-mix(in srgb, var(--color-primary) 10%, transparent);
  border: 1px solid color-mix(in srgb, var(--color-primary) 24%, transparent);
}

.project-member-modal__body {
  display: grid;
  gap: 12px;
  padding: 0 22px 22px;
}

.project-member-modal__field {
  position: relative;
  display: grid;
  gap: 8px;
  color: var(--color-text);
  font-size: 13px;
  font-weight: 650;
}

.project-member-modal__input {
  width: 100%;
  min-height: 42px;
  padding: 0 12px;
  color: var(--color-text);
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  font: inherit;
  font-size: 14px;
  font-weight: 400;
}

.project-member-modal__input:focus {
  border-color: var(--color-primary);
  outline: 2px solid color-mix(in srgb, var(--color-primary) 18%, transparent);
}

.project-member-modal__list {
  position: absolute;
  top: calc(100% + 6px);
  right: 0;
  left: 0;
  z-index: 30;
  display: grid;
  max-height: 168px;
  overflow: auto;
  background: var(--color-surface);
  border: 1px solid var(--color-border-soft);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-md);
}

.project-member-modal__option {
  display: grid;
  grid-template-columns: 40px minmax(0, 1fr);
  gap: 10px;
  align-items: center;
  min-width: 0;
  min-height: 56px;
  padding: 8px 10px;
  color: var(--color-text);
  background: var(--color-surface);
  border: 0;
  border-bottom: 1px solid var(--color-border-soft);
  cursor: pointer;
  text-align: left;
}

.project-member-modal__option:last-child {
  border-bottom: 0;
}

.project-member-modal__option:hover {
  background: var(--color-bg-soft);
}

.project-member-modal__avatar {
  overflow: hidden;
  display: grid;
  place-items: center;
  width: 40px;
  height: 40px;
  color: var(--color-primary);
  background: color-mix(in srgb, var(--color-primary) 10%, transparent);
  border: 1px solid color-mix(in srgb, var(--color-primary) 24%, var(--color-border-soft));
  border-radius: 50%;
  font-size: 13px;
  font-weight: 750;
}

.project-member-modal__avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.project-member-modal__option-copy {
  display: grid;
  gap: 3px;
  min-width: 0;
}

.project-member-modal__option-copy strong,
.project-member-modal__option-copy span {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.project-member-modal__option-copy strong {
  font-size: 14px;
  font-weight: 650;
}

.project-member-modal__option-copy span,
.project-member-modal__hint,
.project-member-modal__empty {
  color: var(--color-text-muted);
  font-size: 13px;
}

.project-member-modal__hint,
.project-member-modal__empty,
.project-member-modal__error {
  margin: 0;
}

.project-member-modal__error {
  color: var(--color-danger);
  font-size: 13px;
}

.project-member-modal__button--primary {
  color: var(--color-primary-text);
  background: var(--color-primary);
}

.project-create-details__form {
  width: 100%;
  max-width: none;
  gap: 0;
}

.project-create-details__group {
  padding: 24px 0;
  border-top: 1px solid var(--color-border-soft);
}

.project-create-details__group--first {
  padding-top: 0;
  border-top: 0;
}

.project-create-details__group--last {
  padding-bottom: 24px;
}

.project-create-details__identity {
  display: grid;
  grid-template-columns: minmax(220px, 0.8fr) minmax(280px, 1.2fr);
  gap: 16px;
  align-items: start;
  width: 100%;
  max-width: 720px;
}

.project-create-details__identity > .form-field {
  min-width: 0;
}

.project-create-details__identity .form-control {
  width: 100%;
  max-width: none;
}

.project-create-details__group > .form-control,
.project-create-details__group > .game-editor,
.project-create-details__group > .project-link-list,
.project-create-details__group > .project-tag-list {
  width: 100%;
  max-width: 720px;
}

.project-filter-settings__heading {
  display: grid;
  gap: 4px;
}

.project-filter-settings__heading > span:last-child,
.project-filter-settings__message {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 13px;
  line-height: 1.45;
}

.project-filter-settings__content-type {
  width: 100%;
  max-width: 720px;
}

.project-filter-settings__grid {
  display: grid;
  width: 100%;
  border-top: 1px solid var(--color-border-soft);
}

.project-filter-settings__field {
  display: grid;
  grid-template-columns: minmax(180px, 240px) minmax(0, 1fr);
  gap: 18px;
  align-items: start;
  min-width: 0;
  padding: 14px 0;
  border-bottom: 1px solid var(--color-border-soft);
}

.project-filter-settings__field-heading {
  display: grid;
  gap: 4px;
  min-width: 0;
}

.project-filter-settings__field-heading > span:last-child {
  color: var(--color-text-muted);
  font-size: 11px;
  font-weight: 650;
}

.project-filter-settings__required {
  margin-left: 5px;
  color: var(--color-danger);
  font-size: 11px;
  font-weight: 700;
}

.project-filter-settings__controls {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  min-width: 0;
}

.project-filter-settings__select {
  flex: 1 1 240px;
  width: auto;
  max-width: 360px;
}

.project-filter-settings__selected {
  display: flex;
  flex: 1 0 100%;
  flex-wrap: wrap;
  gap: 7px;
}

.project-filter-settings__selected-value {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-height: 28px;
  padding: 0 9px;
  color: var(--color-primary);
  background: color-mix(in srgb, var(--color-primary) 10%, transparent);
  border: 1px solid color-mix(in srgb, var(--color-primary) 28%, var(--color-border-soft));
  border-radius: 999px;
  cursor: pointer;
  font: inherit;
  font-size: 12px;
  font-weight: 750;
}

.project-filter-settings__selected-value:hover {
  color: var(--color-primary-text);
  background: var(--color-primary);
  border-color: var(--color-primary);
}

.project-create-details__logo {
  width: min(100%, 280px);
  min-height: 158px;
}

.project-create-details__editor--invalid {
  border-color: var(--color-danger);
}

.project-create-details__actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding-top: 24px;
  border-top: 1px solid var(--color-border-soft);
}

.project-create-details__actions .button {
  flex: 0 0 auto;
  width: auto;
}

.project-create-description__form {
  width: 100%;
  max-width: none;
  gap: 0;
}

.project-create-description__field {
  padding-bottom: 24px;
}

.project-create-description__field > .game-editor {
  width: 100%;
}

.project-create-description__editor--invalid {
  border-color: var(--color-danger);
}

.project-create-description__actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding-top: 24px;
  border-top: 1px solid var(--color-border-soft);
}

.project-create-description__actions .button {
  flex: 0 0 auto;
  width: auto;
}

.project-create-licence__form {
  width: 100%;
  max-width: none;
  gap: 0;
}

.project-create-licence__field {
  padding-bottom: 24px;
}

.project-create-licence__content {
  display: grid;
  grid-template-columns: minmax(0, 720px) minmax(260px, 1fr);
  gap: 28px;
  align-items: start;
}

.project-create-licence__fields {
  min-width: 0;
}

.project-create-licence__help {
  color: var(--color-text-muted);
  font-size: 13px;
}

.project-create-licence__note {
  display: flex;
  gap: 12px;
  padding: 18px;
  color: var(--color-text);
  background: color-mix(in srgb, var(--color-info) 8%, var(--color-surface));
  border: 1px solid color-mix(in srgb, var(--color-info) 24%, var(--color-border));
  border-radius: var(--radius-md);
}

.project-create-licence__note-icon {
  display: grid;
  place-items: center;
  flex: 0 0 auto;
  width: 34px;
  height: 34px;
  color: var(--color-info);
  background: color-mix(in srgb, var(--color-info) 12%, transparent);
  border-radius: 50%;
}

.project-create-licence__note strong {
  display: block;
  margin-bottom: 6px;
}

.project-create-licence__note p {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 14px;
  line-height: 1.55;
}

.project-create-licence__actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding-top: 24px;
  border-top: 1px solid var(--color-border-soft);
}

.project-create-licence__actions .button {
  flex: 0 0 auto;
  width: auto;
}

@media (max-width: 760px) {
  .project-create-details__identity {
    grid-template-columns: 1fr;
    gap: 12px;
  }

  .project-releases-filters {
    margin-bottom: 0;
  }

  .project-releases-filters .game-filter-top {
    grid-template-columns: 1fr;
  }

  .project-members-filters__top {
    grid-template-columns: 1fr;
  }

  .project-releases-filters .data-toolbar__columns-menu {
    right: auto;
    left: 0;
  }

  .project-releases-filters__row .game-filter-field,
  .project-releases-filters__row .game-filter-reset,
  .project-members-filters__row .game-filter-field {
    flex-basis: 100%;
    width: 100%;
    max-width: none;
    margin-left: 0;
  }

  .project-filter-settings__field {
    grid-template-columns: 1fr;
    gap: 8px;
  }

  .project-filter-settings__select {
    max-width: none;
  }

  .project-member-card__header {
    grid-template-columns: 44px minmax(0, 1fr);
  }

  .project-member-card__role {
    grid-column: 2;
    justify-self: start;
  }
}

@media (max-width: 900px) {
  .project-create-licence__content {
    grid-template-columns: 1fr;
  }

  .project-members-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 520px) {
  .project-create-details__actions,
  .project-create-description__actions,
  .project-create-licence__actions {
    display: grid;
    grid-template-columns: 1fr;
  }

  .project-create-details__actions .button,
  .project-create-description__actions .button,
  .project-create-licence__actions .button {
    width: 100%;
  }

  .project-members-grid {
    grid-template-columns: 1fr;
  }
}
</style>
