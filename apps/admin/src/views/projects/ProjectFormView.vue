<script setup lang="ts">
import { ArrowLeft } from '@lucide/vue'
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import AppShell from '@/components/layout/AppShell.vue'
import { fetchProject, type ProjectDetail } from '@/shared/projects/projects'

const route = useRoute()
const project = ref<ProjectDetail | null>(null)
const isLoading = ref(true)
const errorMessage = ref('')
const projectId = computed(() => Number(route.params.id))

async function loadProject(): Promise<void> {
  isLoading.value = true
  errorMessage.value = ''

  try {
    project.value = await fetchProject(projectId.value)
  } catch {
    errorMessage.value = 'Не удалось загрузить проект.'
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  void loadProject()
})
</script>

<template>
  <AppShell>
    <section class="data-page project-edit-page">
      <p v-if="isLoading" class="project-edit-page__state">Загрузка...</p>
      <p v-else-if="errorMessage" class="project-edit-page__state">{{ errorMessage }}</p>

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

        <div class="project-tabs" role="tablist" aria-label="Разделы редактирования проекта">
          <button
            class="project-tab project-tab--active"
            type="button"
            role="tab"
            aria-selected="true"
          >
            Основное
          </button>
        </div>

        <section class="project-edit-page__empty" aria-label="Основное"></section>
      </template>
    </section>
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

.project-edit-page__empty {
  min-height: 180px;
}
</style>
