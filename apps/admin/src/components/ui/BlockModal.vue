<script setup lang="ts">
import { Ban, Snowflake } from '@lucide/vue'

withDefaults(defineProps<{
  open: boolean
  title: string
  description: string
  icon?: 'ban' | 'snowflake'
  cancelText?: string
  confirmText?: string
  loading?: boolean
  tone?: 'danger' | 'info' | 'warning'
}>(), {
  icon: 'ban',
  cancelText: 'Отмена',
  confirmText: 'Заблокировать',
  loading: false,
  tone: 'danger',
})

const emit = defineEmits<{
  cancel: []
  confirm: []
}>()
</script>

<template>
  <Transition name="modal">
    <div v-if="open" class="modal block-modal" role="presentation">
      <div class="modal__backdrop" @click="emit('cancel')" />

      <section
        class="modal__dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="block-modal-title"
      >
        <div class="modal__content">
          <div
            class="modal__icon block-modal__icon"
            :class="`block-modal__icon--${tone}`"
            aria-hidden="true"
          >
            <Snowflake v-if="icon === 'snowflake'" :size="34" :stroke-width="1.9" />
            <Ban v-else :size="34" :stroke-width="1.9" />
          </div>

          <div class="modal__copy">
            <h3 id="block-modal-title">{{ title }}</h3>
            <p>{{ description }}</p>
          </div>
        </div>

        <footer class="modal__actions">
          <button
            class="modal__button modal__button--secondary"
            type="button"
            :disabled="loading"
            @click="emit('cancel')"
          >
            {{ cancelText }}
          </button>

          <button
            class="modal__button"
            :class="`block-modal__button--${tone}`"
            type="button"
            :disabled="loading"
            @click="emit('confirm')"
          >
            {{ confirmText }}
          </button>
        </footer>
      </section>
    </div>
  </Transition>
</template>

<style scoped>
.block-modal__icon--warning {
  color: var(--color-warning);
  background: color-mix(in srgb, var(--color-warning) 12%, transparent);
  border: 1px solid color-mix(in srgb, var(--color-warning) 34%, transparent);
}

.block-modal__icon--info {
  color: var(--color-info);
  background: color-mix(in srgb, var(--color-info) 12%, transparent);
  border: 1px solid color-mix(in srgb, var(--color-info) 34%, transparent);
}

.block-modal__icon--danger {
  color: var(--color-danger);
  background: color-mix(in srgb, var(--color-danger) 10%, transparent);
  border: 1px solid color-mix(in srgb, var(--color-danger) 28%, transparent);
}

.block-modal__button--warning {
  color: var(--color-primary-text);
  background: var(--color-warning);
}

.block-modal__button--info {
  color: var(--color-primary-text);
  background: var(--color-info);
}

.block-modal__button--danger {
  color: var(--color-primary-text);
  background: var(--color-danger);
}
</style>
