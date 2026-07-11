<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ApiError } from '../lib/api'
import { login } from '../lib/auth'

const router = useRouter()

const form = reactive({
  email: '',
  password: '',
  remember: true,
})

const errors = ref<Record<string, string[]>>({})
const isSubmitting = ref(false)
const message = ref('')

const emailError = computed(() => errors.value.email?.[0] ?? '')
const passwordError = computed(() => errors.value.password?.[0] ?? '')

async function submit(): Promise<void> {
  isSubmitting.value = true
  errors.value = {}
  message.value = ''

  try {
    await login(form)
    await router.push({ name: 'dashboard' })
  } catch (error) {
    if (error instanceof ApiError) {
      errors.value = error.errors
      message.value = error.message

      return
    }

    message.value = 'Не удалось войти. Проверьте соединение и попробуйте снова.'
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <main class="auth-shell">
    <section class="auth-panel" aria-labelledby="login-title">
      <div>
        <p class="eyebrow">STAF</p>
        <h1 id="login-title">Вход</h1>
      </div>

      <form class="auth-form" @submit.prevent="submit">
        <label>
          Email
          <input
            v-model="form.email"
            type="email"
            name="email"
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
            name="password"
            autocomplete="current-password"
            required
            :aria-invalid="Boolean(passwordError)"
          />
          <span v-if="passwordError" class="field-error">{{ passwordError }}</span>
        </label>

        <label class="check-row">
          <input v-model="form.remember" type="checkbox" name="remember" />
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
