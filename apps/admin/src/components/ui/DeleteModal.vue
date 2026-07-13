<script setup lang="ts">
import { TriangleAlert } from '@lucide/vue'

withDefaults(defineProps<{
  open: boolean
  title: string
  description: string
  cancelText?: string
  confirmText?: string
  loading?: boolean
}>(), {
  cancelText: 'Отмена',
  confirmText: 'Удалить',
  loading: false,
})

const emit = defineEmits<{
  cancel: []
  confirm: []
}>()
</script>

<template>
  <Transition name="modal">
    <div v-if="open" class="modal delete-modal" role="presentation">
      <div class="modal__backdrop" @click="emit('cancel')" />

      <section
        class="modal__dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="delete-modal-title"
      >
        <div class="modal__content">
          <div class="modal__icon delete-modal__icon" aria-hidden="true">
            <TriangleAlert :size="34" :stroke-width="1.9" />
          </div>

          <div class="modal__copy">
            <h3 id="delete-modal-title">{{ title }}</h3>
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
            class="modal__button delete-modal__button--danger"
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
.delete-modal__icon {
  color: var(--color-danger);
  background: color-mix(in srgb, var(--color-danger) 10%, transparent);
  border: 1px solid color-mix(in srgb, var(--color-danger) 28%, transparent);
}

.delete-modal__button--danger {
  color: var(--color-primary-text);
  background: var(--color-danger);
}
</style>
