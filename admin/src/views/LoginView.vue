<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { errorMessage, login, validationErrors } from '../shared/auth/session'

const router = useRouter()

const form = reactive({
  email: '',
  password: '',
  remember: true,
})

const errors = ref<Record<string, string[]>>({})
const message = ref('')
const isSubmitting = ref(false)

const emailError = computed(() => errors.value.email?.[0] ?? '')
const passwordError = computed(() => errors.value.password?.[0] ?? '')

async function submit(): Promise<void> {
  errors.value = {}
  message.value = ''
  isSubmitting.value = true

  try {
    await login(form)
    await router.push({ name: 'dashboard' })
  } catch (error) {
    errors.value = validationErrors(error)
    message.value = errorMessage(error)
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <main class="auth-shell">
    <section class="auth-panel" aria-labelledby="login-title">
      <p class="eyebrow">STAF Admin</p>
      <h1 id="login-title">Вход</h1>

      <form class="auth-form" @submit.prevent="submit">
        <label>
          Email
          <input
            v-model="form.email"
            type="email"
            autocomplete="email"
            required
            :aria-invalid="Boolean(emailError)"
          />
          <span v-if="emailError" class="field-error">{{ emailError }}</span>
        </label>

        <label>
          Пароль
          <input
            v-model="form.password"
            type="password"
            autocomplete="current-password"
            required
            :aria-invalid="Boolean(passwordError)"
          />
          <span v-if="passwordError" class="field-error">{{ passwordError }}</span>
        </label>

        <label class="check-row">
          <input v-model="form.remember" type="checkbox" />
          Запомнить меня
        </label>

        <p v-if="message" class="form-message">{{ message }}</p>

        <button type="submit" :disabled="isSubmitting">
          {{ isSubmitting ? 'Входим...' : 'Войти' }}
        </button>
      </form>
    </section>
  </main>
</template>
