<script setup lang="ts">
import { Tags } from '@lucide/vue'
import { ref, watch } from 'vue'

const props = withDefaults(defineProps<{
  open: boolean
  mode?: 'create' | 'edit'
  initialName?: string
  initialIsPublic?: boolean
  loading?: boolean
  error?: string
}>(), {
  mode: 'create',
  initialName: '',
  initialIsPublic: true,
  loading: false,
  error: '',
})

const emit = defineEmits<{
  cancel: []
  submit: [payload: { name: string, isPublic: boolean }]
}>()

const name = ref('')
const isPublic = ref(true)

function resetForm(): void {
  name.value = props.initialName
  isPublic.value = props.initialIsPublic
}

function cancel(): void {
  if (props.loading) {
    return
  }

  emit('cancel')
}

function submit(): void {
  const trimmedName = name.value.trim()

  if (!trimmedName || props.loading) {
    return
  }

  emit('submit', {
    name: trimmedName,
    isPublic: isPublic.value,
  })
}

watch(() => props.open, (isOpen) => {
  if (isOpen) {
    resetForm()
  }
})
</script>

<template>
  <Transition name="modal">
    <div v-if="open" class="modal content-type-modal" role="presentation">
      <div class="modal__backdrop" @click="cancel" />

      <form
        class="modal__dialog content-type-modal__dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="content-type-modal-title"
        @submit.prevent="submit"
      >
        <div class="modal__content content-type-modal__content">
          <div class="modal__icon content-type-modal__icon" aria-hidden="true">
            <Tags :size="34" :stroke-width="1.9" />
          </div>

          <div class="modal__copy">
            <h3 id="content-type-modal-title">
              {{ mode === 'edit' ? 'Редактировать тип контента' : 'Создать тип контента' }}
            </h3>
            <p>
              {{ mode === 'edit'
                ? 'Измените название или публичность типа контента.'
                : 'Добавьте название и выберите, будет ли тип доступен публично.'
              }}
            </p>
          </div>

          <div class="content-type-modal__fields">
            <label class="content-type-modal__field">
              <span>Название</span>
              <input
                v-model="name"
                class="content-type-modal__input"
                type="text"
                maxlength="64"
                required
                autofocus
              >
            </label>

            <label class="content-type-modal__toggle">
              <input
                v-model="isPublic"
                class="checkbox-control"
                type="checkbox"
              >
              <span>Публичный</span>
            </label>

            <p v-if="error" class="content-type-modal__error">{{ error }}</p>
          </div>
        </div>

        <footer class="modal__actions">
          <button
            class="modal__button modal__button--secondary"
            type="button"
            :disabled="loading"
            @click="cancel"
          >
            Отмена
          </button>

          <button
            class="modal__button content-type-modal__button--primary"
            type="submit"
            :disabled="loading || !name.trim()"
          >
            {{ mode === 'edit' ? 'Сохранить' : 'Создать' }}
          </button>
        </footer>
      </form>
    </div>
  </Transition>
</template>

<style scoped>
.content-type-modal__dialog {
  width: min(460px, 100%);
}

.content-type-modal__content {
  justify-items: stretch;
  text-align: left;
}

.content-type-modal__icon {
  justify-self: center;
  color: var(--color-primary);
  background: color-mix(in srgb, var(--color-primary) 10%, transparent);
  border: 1px solid color-mix(in srgb, var(--color-primary) 28%, transparent);
}

.content-type-modal__fields {
  display: grid;
  gap: 14px;
}

.content-type-modal__field {
  display: grid;
  gap: 8px;
  color: var(--color-text-muted);
  font-size: 12px;
  font-weight: 800;
}

.content-type-modal__input {
  width: 100%;
  min-height: 42px;
  padding: 0 12px;
  color: var(--color-text);
  background: var(--color-bg-soft);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  outline: none;
  font: inherit;
  font-size: 14px;
  font-weight: 650;
}

.content-type-modal__input:focus {
  background: var(--color-surface);
  border-color: var(--color-primary);
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-focus) 18%, transparent);
}

.content-type-modal__toggle {
  display: flex;
  align-items: center;
  gap: 8px;
  width: fit-content;
  color: var(--color-text);
  font-size: 14px;
  font-weight: 700;
}

.content-type-modal__error {
  margin: 0;
  color: var(--color-danger);
  font-size: 13px;
  font-weight: 700;
}

.content-type-modal__button--primary {
  color: var(--color-primary-text);
  background: var(--color-primary);
}
</style>
