<script setup lang="ts">
import {
  Activity,
  ArrowLeft,
  Box,
  CalendarDays,
  Check,
  ChevronDown,
  ExternalLink,
  FileText,
  Gamepad2,
  Link2,
  MoreHorizontal,
  Pencil,
  Tag,
  UserRound,
} from '@lucide/vue'
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppShell from '@/components/layout/AppShell.vue'
import type { GameDimension } from '@/shared/games/games'
import {
  fetchProject,
  fetchProjectReleaseFilters,
  updateProjectDraft,
  type ProjectDetail,
  type ProjectPublicationStatus,
} from '@/shared/projects/projects'

const route = useRoute()
const router = useRouter()
const project = ref<ProjectDetail | null>(null)
const releaseFilters = ref<GameDimension[]>([])
const selectedReleaseFilters = ref<Record<number, string>>({})
const isLoading = ref(true)
const errorMessage = ref('')

const projectId = computed(() => String(route.params.id))
const summaryText = computed(() => richTextToPlainText(project.value?.summary))
const descriptionText = computed(() => richTextToPlainText(project.value?.description))
const projectTags = computed(() => toStringArray(project.value?.tags))
const projectLinks = computed(() => toStringArray(project.value?.website_urls))
const hasDetails = computed(() => Boolean(project.value?.game_name || project.value?.content_type_name))
const isDescriptionExpanded = ref(false)
const descriptionIsLong = computed(() => descriptionText.value.length > 520)
const activeTab = ref<'overview' | 'releases' | 'screenshots'>('overview')
const isMoreOpen = ref(false)
const isPublicationStatusSaving = ref(false)
const publicationStatusError = ref('')
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
const tabs = [
  { value: 'overview', label: 'Обзор' },
  { value: 'releases', label: 'Релизы' },
  { value: 'screenshots', label: 'Скриншоты' },
] as const

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

async function loadProject(): Promise<void> {
  isLoading.value = true
  errorMessage.value = ''

  try {
    const [projectResponse, filtersResponse] = await Promise.all([
      fetchProject(projectId.value),
      fetchProjectReleaseFilters(projectId.value),
    ])
    project.value = projectResponse
    releaseFilters.value = filtersResponse.data
    selectedReleaseFilters.value = Object.fromEntries(
      filtersResponse.data.map((filter) => [filter.id, '']),
    )
  } catch {
    errorMessage.value = 'Не удалось загрузить проект.'
  } finally {
    isLoading.value = false
  }
}

function goBack(): void {
  void router.push({ name: 'projects.index' })
}

function toggleDescription(): void {
  isDescriptionExpanded.value = !isDescriptionExpanded.value
}

function toggleMoreMenu(): void {
  isMoreOpen.value = !isMoreOpen.value
}

async function copyProjectLink(): Promise<void> {
  await navigator.clipboard?.writeText(window.location.href)
  isMoreOpen.value = false
}

const publicationStatusDescription = computed(() => {
  return publicationStatusOptions.find((option) => option.value === project.value?.publication_status)?.description ?? ''
})

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
                  Скопировать ссылку
                </button>
              </div>
            </div>
          </div>
        </header>

        <section class="project-preview__hero">
          <div class="project-preview__hero-media">
            <img v-if="project.logo_url" :src="project.logo_url" :alt="project.title" />
            <span v-else>{{ project.title.slice(0, 1).toUpperCase() }}</span>
          </div>

          <div class="project-preview__hero-content">
            <div class="project-preview__hero-copy">
              <p v-if="summaryText" class="project-preview__summary">{{ summaryText }}</p>
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

        <section v-if="activeTab === 'releases'" class="project-preview__releases">
          <div v-if="releaseFilters.length" class="project-preview__release-filters">
            <label v-for="filter in releaseFilters" :key="filter.id" class="project-preview__release-filter">
              <span>{{ filter.name }}</span>
              <select v-model="selectedReleaseFilters[filter.id]">
                <option value="" disabled hidden>{{ filter.name }}</option>
                <option v-for="value in filter.values" :key="value.id" :value="String(value.id)">
                  {{ value.name }}
                </option>
              </select>
            </label>
          </div>
          <div v-else class="project-preview__release-filters">
            <label class="project-preview__release-filter">
              <span>Фильтры релизов</span>
              <select disabled aria-label="Фильтры релизов">
                <option>Фильтры для этой игры пока не настроены</option>
              </select>
            </label>
          </div>
          <section class="project-preview__tab-placeholder">
            <h2>Релизы проекта</h2>
            <p>Релизы будут отображаться здесь после добавления данных.</p>
          </section>
        </section>

        <section
          v-if="activeTab === 'screenshots'"
          class="project-preview__empty-tab"
          aria-label="Скриншоты"
        ></section>

        <div v-if="activeTab === 'overview'" class="project-preview__layout">
          <main class="project-preview__main">
            <section class="project-preview__card">
              <h2><FileText :size="18" aria-hidden="true" />Описание</h2>
              <div
                v-if="descriptionText"
                class="project-preview__description"
                :class="{ 'project-preview__description--expanded': isDescriptionExpanded }"
              >
                <p>{{ descriptionText }}</p>
              </div>
              <p v-else class="project-preview__muted">Подробное описание проекта пока не добавлено.</p>
              <button
                v-if="descriptionIsLong"
                class="project-preview__description-toggle"
                type="button"
                @click="toggleDescription"
              >
                {{ isDescriptionExpanded ? 'Свернуть' : 'Читать полностью' }}
              </button>
            </section>

            <section v-if="hasDetails" class="project-preview__card">
              <h2><Gamepad2 :size="18" aria-hidden="true" />Характеристики проекта</h2>
              <dl class="project-preview__details">
                <div v-if="project.game_name"><dt>Игра</dt><dd>{{ project.game_name }}</dd></div>
                <div v-if="project.content_type_name"><dt>Тип контента</dt><dd>{{ project.content_type_name }}</dd></div>
                <div><dt>Статус публикации</dt><dd>{{ project.publication_status_label ?? '—' }}</dd></div>
                <div><dt>Завершённость</dt><dd>{{ project.percentage_complete }}%</dd></div>
                <div v-if="project.licence_name"><dt>Лицензия</dt><dd>{{ project.licence_name }}</dd></div>
              </dl>
            </section>

            <section v-if="project.screenshot_urls.length" class="project-preview__card">
              <h2><FileText :size="18" aria-hidden="true" />Изображения</h2>
              <div class="project-preview__screenshots">
                <img v-for="(url, index) in project.screenshot_urls" :key="url" :src="url" :alt="`${project.title} — изображение ${index + 1}`" />
              </div>
            </section>
          </main>

          <aside class="project-preview__aside">
            <section class="project-preview__card">
              <h2><Tag :size="18" aria-hidden="true" />Быстрая информация</h2>
              <dl class="project-preview__details project-preview__details--aside">
                <div><dt>Автор</dt><dd><a href="#">{{ project.owner_name ?? '—' }}</a></dd></div>
                <div><dt>Создан</dt><dd>{{ formatDate(project.created_at) }}</dd></div>
                <div><dt>Обновлён</dt><dd>{{ formatDate(project.updated_at) }}</dd></div>
              </dl>
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
          </aside>
        </div>

      </template>
    </section>
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

.project-preview__hero { display: grid; grid-template-columns: 180px minmax(0, 1fr) minmax(280px, .65fr); gap: 22px; padding: 18px; }
.project-preview__hero-media { overflow: hidden; aspect-ratio: 1; background: var(--color-bg-soft); border-radius: var(--radius-md); }
.project-preview__hero-media img { width: 100%; height: 100%; object-fit: cover; }
.project-preview__hero-media span { display: grid; width: 100%; height: 100%; place-items: center; color: var(--color-primary); font-size: 56px; font-weight: 800; }
.project-preview__hero-content { display: flex; flex-direction: column; justify-content: space-between; gap: 18px; min-width: 0; }
.project-preview__summary { max-width: 720px; margin: 0; color: var(--color-text-muted); font-size: 16px; line-height: 1.5; }
.project-preview__muted { color: var(--color-text-muted); }
.project-preview__tags { display: flex; flex-wrap: wrap; gap: 7px; margin-top: 14px; }
.project-preview__tags span { padding: 5px 10px; color: var(--color-primary); background: color-mix(in srgb, var(--color-primary) 12%, transparent); border: 1px solid color-mix(in srgb, var(--color-primary) 28%, transparent); border-radius: 999px; font-size: 12px; font-weight: 700; }
.project-preview__hero-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 14px; margin-top: 14px; }
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
.project-preview__more-menu button { display: block; width: 100%; padding: 9px 10px; color: var(--color-text); background: transparent; border: 0; border-radius: var(--radius-sm); cursor: pointer; text-align: left; font: inherit; font-size: 13px; }
.project-preview__more-menu button:hover { background: var(--color-surface-hover); }
.project-preview__tab-placeholder { min-height: 180px; padding: 24px; background: var(--color-surface); border: 1px solid var(--color-border-soft); border-radius: var(--radius-lg); }
.project-preview__tab-placeholder h2 { margin: 0 0 8px; }
.project-preview__tab-placeholder p { margin: 0; color: var(--color-text-muted); }
.project-preview__empty-tab { min-height: 160px; }
.project-preview__releases { display: grid; gap: 16px; }
.project-preview__release-filters { display: flex; flex-wrap: wrap; gap: 12px; padding: 14px 16px; background: var(--color-surface); border: 1px solid var(--color-border-soft); border-radius: var(--radius-lg); }
.project-preview__release-filter { display: grid; gap: 6px; min-width: 180px; color: var(--color-text-muted); font-size: 12px; font-weight: 700; }
.project-preview__release-filter select { min-height: 36px; padding: 0 10px; color: var(--color-text); background: var(--color-bg-soft); border: 1px solid var(--color-border); border-radius: var(--radius-md); font: inherit; font-size: 13px; }
.project-preview__release-filter select:disabled { color: var(--color-text-muted); cursor: not-allowed; opacity: 0.8; }
.project-preview__layout { display: grid; grid-template-columns: minmax(0, 1.65fr) minmax(280px, .75fr); gap: 16px; align-items: start; }
.project-preview__main, .project-preview__aside { display: grid; gap: 16px; min-width: 0; }
.project-preview__card { padding: 18px; }
.project-preview__card h2 { gap: 8px; margin: 0 0 16px; font-size: 16px; }
.project-preview__card > p { margin: 0; color: var(--color-text-muted); line-height: 1.6; white-space: pre-line; }
.project-preview__description { max-height: 250px; overflow: hidden; }
.project-preview__description--expanded { max-height: none; }
.project-preview__description p { margin: 0; color: var(--color-text-muted); line-height: 1.6; white-space: pre-line; }
.project-preview__description-toggle { margin-top: 12px; padding: 0; color: var(--color-primary); background: none; border: 0; cursor: pointer; font: inherit; font-size: 13px; font-weight: 700; }
.project-preview__details { display: grid; gap: 12px; margin: 0; }
.project-preview__details div { display: grid; grid-template-columns: minmax(120px, .7fr) minmax(0, 1.3fr); gap: 14px; align-items: baseline; }
.project-preview__details dd { min-width: 0; overflow-wrap: anywhere; }
.project-preview__details--aside div { grid-template-columns: minmax(70px, .7fr) minmax(0, 1.3fr); }
.project-preview__details--aside dd { text-align: right; }
.project-preview__screenshots { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px; }
.project-preview__screenshots img { width: 100%; aspect-ratio: 16 / 10; object-fit: cover; border-radius: var(--radius-md); }
.project-preview__links { display: grid; gap: 10px; }
.project-preview__links a { display: flex; align-items: center; justify-content: space-between; gap: 8px; color: var(--color-primary); font-size: 13px; text-decoration: none; }
.project-preview__links a span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.project-preview__card--status { display: grid; gap: 12px; }
.project-preview__card--status h2 { margin-bottom: 0; }
.project-preview__status-select select { width: 220px; max-width: 100%; min-height: 38px; padding: 0 10px; color: var(--color-text); background: var(--color-bg-soft); border: 1px solid var(--color-border); border-radius: var(--radius-md); font: inherit; }
.project-preview__status-select select:focus { border-color: var(--color-primary); outline: 2px solid color-mix(in srgb, var(--color-focus) 25%, transparent); outline-offset: 1px; }
.project-preview__status-description, .project-preview__status-error { margin: 0; color: var(--color-text-muted); font-size: 13px; line-height: 1.45; }
.project-preview__status-error { color: var(--color-danger); }

@media (max-width: 900px) {
  .project-preview__hero { grid-template-columns: 150px minmax(0, 1fr); }
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
