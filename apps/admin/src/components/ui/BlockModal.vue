<script setup lang="ts">
import { Ban } from '@lucide/vue'

withDefaults(defineProps<{
  open: boolean
  title: string
  description: string
  cancelText?: string
  confirmText?: string
  loading?: boolean
}>(), {
  cancelText: 'Отмена',
  confirmText: 'Заблокировать',
  loading: false,
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
          <div class="modal__icon block-modal__icon" aria-hidden="true">
            <Ban :size="34" :stroke-width="1.9" />
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
            class="modal__button block-modal__button--warning"
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
.block-modal__icon {
  color: var(--color-warning);
  background: color-mix(in srgb, var(--color-warning) 12%, transparent);
  border: 1px solid color-mix(in srgb, var(--color-warning) 34%, transparent);
}

.block-modal__button--warning {
  color: var(--color-primary-text);
  background: var(--color-warning);
}
</style>
