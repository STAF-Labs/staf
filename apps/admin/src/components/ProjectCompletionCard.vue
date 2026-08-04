<script setup lang="ts">
import { Check, Circle } from '@lucide/vue'
import { computed } from 'vue'
import {
  calculateProjectPercentageComplete,
  projectCompletionChecklist,
} from '@/shared/projects/project-create'

const percentageComplete = computed(() => calculateProjectPercentageComplete())
const checklist = computed(() => projectCompletionChecklist())
const isComplete = computed(() => percentageComplete.value === 100)
</script>

<template>
  <aside class="project-completion-card" aria-label="Заполненность проекта">
    <div class="project-completion-card__heading">
      <div>
        <span class="project-completion-card__eyebrow">Заполненность</span>
        <strong>{{ percentageComplete }}%</strong>
      </div>
      <span
        class="project-completion-card__status"
        :class="{ 'project-completion-card__status--complete': isComplete }"
      >
        {{ isComplete ? 'Готов к публикации' : 'Черновик' }}
      </span>
    </div>

    <progress
      class="project-completion-card__progress"
      :value="percentageComplete"
      max="100"
    >
      {{ percentageComplete }}%
    </progress>

    <ul class="project-completion-card__checklist">
      <li
        v-for="item in checklist"
        :key="item.id"
        :class="{ 'is-complete': item.complete }"
      >
        <span class="project-completion-card__icon" aria-hidden="true">
          <Check v-if="item.complete" :size="14" :stroke-width="2.5" />
          <Circle v-else :size="12" :stroke-width="2" />
        </span>
        <span>{{ item.label }}</span>
      </li>
    </ul>
  </aside>
</template>
