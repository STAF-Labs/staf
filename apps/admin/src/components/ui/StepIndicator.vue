<script setup lang="ts">
import { Check } from '@lucide/vue'

withDefaults(
  defineProps<{
    steps: readonly string[]
    currentStep: number
    ariaLabel?: string
    variant?: 'panel' | 'embedded'
  }>(),
  {
    ariaLabel: 'Этапы',
    variant: 'panel',
  },
)
</script>

<template>
  <nav class="step-indicator" :class="`step-indicator--${variant}`" :aria-label="ariaLabel">
    <ol class="step-indicator__list">
      <li v-for="(step, index) in steps" :key="step" class="step-indicator__item">
        <span
          class="step-indicator__step"
          :class="{
            'step-indicator__step--active': index + 1 === currentStep,
            'step-indicator__step--complete': index + 1 < currentStep,
          }"
          :aria-current="index + 1 === currentStep ? 'step' : undefined"
        >
          <span class="step-indicator__marker" aria-hidden="true">
            <Check v-if="index + 1 < currentStep" :size="14" :stroke-width="2.4" />
            <template v-else>{{ index + 1 }}</template>
          </span>
          <span class="step-indicator__label">{{ step }}</span>
        </span>

        <span
          v-if="index < steps.length - 1"
          class="step-indicator__line"
          :class="{ 'step-indicator__line--complete': index + 1 < currentStep }"
          aria-hidden="true"
        />
      </li>
    </ol>
  </nav>
</template>

<style scoped>
.step-indicator {
  padding: 14px 18px;
  overflow-x: auto;
  background: var(--color-bg-soft);
  border: 1px solid var(--color-border-soft);
  border-radius: var(--radius-lg);
}

.step-indicator--embedded {
  border-width: 0 0 1px;
  border-radius: 0;
}

.step-indicator__list {
  display: flex;
  align-items: center;
  min-width: max-content;
  margin: 0;
  padding: 0;
  list-style: none;
}

.step-indicator__item {
  display: flex;
  flex: 1 1 120px;
  align-items: center;
  min-width: 0;
}

.step-indicator__item:last-child {
  flex: 0 0 auto;
}

.step-indicator__step {
  display: inline-flex;
  flex: 0 0 auto;
  align-items: center;
  gap: 8px;
  color: var(--color-text-muted);
  font-size: 12px;
  font-weight: 800;
  white-space: nowrap;
}

.step-indicator__marker {
  display: grid;
  place-items: center;
  width: 24px;
  height: 24px;
  background: var(--color-bg-muted);
  border: 1px solid var(--color-border-soft);
  border-radius: 50%;
}

.step-indicator__step--active {
  color: var(--color-primary);
}

.step-indicator__step--active .step-indicator__marker {
  color: var(--color-primary-text);
  background: var(--color-primary);
  border-color: var(--color-primary);
}

.step-indicator__step--complete {
  color: var(--color-text);
}

.step-indicator__step--complete .step-indicator__marker {
  color: var(--color-primary);
  background: color-mix(in srgb, var(--color-primary) 12%, var(--color-bg-soft));
  border-color: color-mix(in srgb, var(--color-primary) 34%, var(--color-border-soft));
}

.step-indicator__line {
  flex: 1 1 36px;
  min-width: 36px;
  height: 1px;
  margin: 0 10px;
  background: var(--color-border-soft);
}

.step-indicator__line--complete {
  background: color-mix(in srgb, var(--color-primary) 48%, var(--color-border-soft));
}
</style>
