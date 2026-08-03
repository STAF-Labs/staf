<script setup lang="ts">
import { ArrowLeft, ArrowRight, FileText, Pencil, Trash2 } from '@lucide/vue'
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppShell from '@/components/layout/AppShell.vue'
import StepIndicator from '@/components/ui/StepIndicator.vue'
import { projectCreateDraft, projectCreateSteps } from '@/shared/projects/project-create'

const licenceAccept = '.pdf,.txt,.md,.doc,.docx'
const licenceExtensions = ['pdf', 'txt', 'md', 'doc', 'docx']
const licenceMimeTypes = [
  'application/msword',
  'application/pdf',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  'text/markdown',
  'text/plain',
]
const licenceMaxBytes = 5 * 1024 * 1024

const route = useRoute()
const router = useRouter()
const licenceInput = ref<HTMLInputElement | null>(null)
const licenceError = ref('')
const gameId = String(route.params.gameId ?? '')

function chooseLicence(event: Event): void {
  const input = event.target as HTMLInputElement
  const [file] = Array.from(input.files ?? [])

  licenceError.value = ''

  if (!file) {
    return
  }

  const extension = file.name.split('.').pop()?.toLocaleLowerCase() ?? ''

  if (
    !licenceExtensions.includes(extension) ||
    (file.type && !licenceMimeTypes.includes(file.type))
  ) {
    licenceError.value = 'Доступные форматы: PDF, TXT, MD, DOC и DOCX.'
    input.value = ''

    return
  }

  if (file.size > licenceMaxBytes) {
    licenceError.value = 'Файл должен быть не больше 5 МБ.'
    input.value = ''

    return
  }

  projectCreateDraft.licence = file
}

function openLicencePicker(): void {
  licenceInput.value?.click()
}

function clearLicence(): void {
  projectCreateDraft.licence = null
  licenceError.value = ''

  if (licenceInput.value) {
    licenceInput.value.value = ''
  }
}

function formatFileSize(bytes: number): string {
  if (bytes < 1024 * 1024) {
    return `${Math.max(1, Math.round(bytes / 1024))} КБ`
  }

  return `${(bytes / 1024 / 1024).toFixed(1)} МБ`
}

async function continueToNextStep(): Promise<void> {
  await router.push({
    name: 'projects.create.continue',
    params: { gameId },
  })
}
</script>

<template>
  <AppShell>
    <section class="data-page">
      <header class="data-page__header project-create-licence__header">
        <RouterLink
          class="data-page__back-link project-create-licence__back"
          :to="{
            name: 'projects.create.description',
            params: { gameId },
          }"
          aria-label="Вернуться к описанию"
        >
          <ArrowLeft :size="18" :stroke-width="1.9" aria-hidden="true" />
          <span>Описание</span>
        </RouterLink>

        <div>
          <h2 class="data-page__title">Лицензия проекта</h2>
          <p class="data-page__subtitle">При необходимости приложите файл лицензии.</p>
        </div>
      </header>

      <StepIndicator
        :steps="projectCreateSteps"
        :current-step="4"
        aria-label="Этапы создания проекта"
      />

      <form
        class="project-form-panel form project-create-licence__form"
        novalidate
        @submit.prevent="continueToNextStep"
      >
        <div class="form-field project-create-licence__field">
          <span class="form-label">Файл лицензии</span>

          <div
            class="project-licence-upload"
            :class="{
              'project-licence-upload--empty': !projectCreateDraft.licence,
              'project-licence-upload--invalid': licenceError,
            }"
            @click="!projectCreateDraft.licence ? openLicencePicker() : undefined"
          >
            <input ref="licenceInput" type="file" :accept="licenceAccept" @change="chooseLicence" />

            <span class="project-licence-upload__icon" aria-hidden="true">
              <FileText :size="28" :stroke-width="1.9" />
            </span>

            <span class="project-licence-upload__content">
              <strong>{{ projectCreateDraft.licence?.name ?? 'Выберите файл' }}</strong>
              <span>
                {{
                  projectCreateDraft.licence
                    ? formatFileSize(projectCreateDraft.licence.size)
                    : 'PDF, TXT, MD, DOC или DOCX'
                }}
              </span>
            </span>

            <div v-if="projectCreateDraft.licence" class="project-licence-upload__actions">
              <button
                class="project-licence-upload__action"
                type="button"
                aria-label="Заменить файл лицензии"
                title="Заменить файл"
                @click.stop="openLicencePicker"
              >
                <Pencil :size="17" :stroke-width="2" aria-hidden="true" />
              </button>
              <button
                class="project-licence-upload__action project-licence-upload__action--danger"
                type="button"
                aria-label="Удалить файл лицензии"
                title="Удалить файл"
                @click.stop="clearLicence"
              >
                <Trash2 :size="17" :stroke-width="2" aria-hidden="true" />
              </button>
            </div>
          </div>

          <span class="project-create-licence__help" :class="{ 'field-error': licenceError }">
            {{ licenceError || 'Необязательный файл размером до 5 МБ.' }}
          </span>
        </div>

        <div class="project-create-licence__actions">
          <RouterLink
            class="button"
            :to="{
              name: 'projects.create.description',
              params: { gameId },
            }"
          >
            <ArrowLeft :size="18" :stroke-width="1.9" aria-hidden="true" />
            <span>Назад</span>
          </RouterLink>

          <button class="button button-primary" type="submit">
            <span>Далее</span>
            <ArrowRight :size="18" :stroke-width="1.9" aria-hidden="true" />
          </button>
        </div>
      </form>
    </section>
  </AppShell>
</template>

<style scoped>
.project-create-licence__header {
  gap: 12px;
}

.project-create-licence__back {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.project-create-licence__form {
  width: 100%;
  max-width: none;
  gap: 0;
}

.project-create-licence__field {
  padding-bottom: 24px;
}

.project-licence-upload {
  position: relative;
  display: flex;
  align-items: center;
  gap: 14px;
  width: min(100%, 720px);
  min-height: 96px;
  padding: 16px;
  background: var(--color-bg-soft);
  border: 1px dashed var(--color-border);
  border-radius: var(--radius-md);
}

.project-licence-upload--empty {
  cursor: pointer;
}

.project-licence-upload--empty:hover {
  background: var(--color-surface-hover);
  border-color: var(--color-primary);
}

.project-licence-upload--invalid {
  border-color: var(--color-danger);
}

.project-licence-upload input {
  position: absolute;
  width: 1px;
  height: 1px;
  opacity: 0;
  pointer-events: none;
}

.project-licence-upload__icon {
  display: grid;
  place-items: center;
  flex: 0 0 auto;
  width: 48px;
  height: 48px;
  color: var(--color-primary);
  background: color-mix(in srgb, var(--color-primary) 10%, transparent);
  border-radius: var(--radius-md);
}

.project-licence-upload__content {
  display: grid;
  gap: 4px;
  min-width: 0;
}

.project-licence-upload__content strong,
.project-licence-upload__content span {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.project-licence-upload__content span,
.project-create-licence__help {
  color: var(--color-text-muted);
  font-size: 13px;
}

.project-licence-upload__actions {
  display: flex;
  gap: 8px;
  margin-left: auto;
}

.project-licence-upload__action {
  display: grid;
  place-items: center;
  width: 36px;
  height: 36px;
  color: var(--color-primary);
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  cursor: pointer;
}

.project-licence-upload__action--danger {
  color: var(--color-danger);
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

@media (max-width: 520px) {
  .project-create-licence__actions {
    display: grid;
    grid-template-columns: 1fr;
  }

  .project-create-licence__actions .button {
    width: 100%;
  }
}
</style>
