<script setup lang="ts">
import { ArrowLeft, ArrowRight, Info } from '@lucide/vue'
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppShell from '@/components/layout/AppShell.vue'
import StepIndicator from '@/components/ui/StepIndicator.vue'
import { fetchLicences, type LicenceOption } from '@/shared/licences/licences'
import { projectCreateDraft, projectCreateSteps } from '@/shared/projects/project-create'

const route = useRoute()
const router = useRouter()
const licenceOptions = ref<LicenceOption[]>([])
const licencesLoading = ref(false)
const licencesError = ref('')
const gameId = String(route.params.gameId ?? '')

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

async function continueToNextStep(): Promise<void> {
  await router.push({
    name: 'projects.create.continue',
    params: { gameId },
  })
}

onMounted(() => {
  void loadLicences()
})
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
          <p class="data-page__subtitle">Выберите условия распространения проекта.</p>
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
        <div class="project-create-licence__content">
          <div class="project-create-licence__fields">
            <label class="form-field project-create-licence__field">
              <span class="form-label">Название лицензии</span>
              <select
                v-model="projectCreateDraft.licenceName"
                class="form-control"
                :disabled="licencesLoading || licencesError !== ''"
              >
                <option value="">
                  {{ licencesLoading ? 'Загрузка…' : licencesError || 'Не выбрана' }}
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

@media (max-width: 900px) {
  .project-create-licence__content {
    grid-template-columns: 1fr;
  }
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
