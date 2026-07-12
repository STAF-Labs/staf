<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import ThemeToggler from '@/components/ui/ThemeToggler.vue'
import { errorMessage, login, validationErrors } from '../../shared/auth/session.ts'

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
      <ThemeToggler />

      <div class="auth-header">
        <p class="eyebrow">STAF Admin</p>
        <h1 id="login-title">Вход</h1>
        <p>Авторизуйтесь, чтобы продолжить работу в панели управления.</p>
      </div>

      <form class="form" @submit.prevent="submit">
        <label class="form-field">
          <span class="form-label">Почта</span>
          <input
            v-model="form.email"
            class="form-control"
            type="email"
            autocomplete="email"
            placeholder="admin@example.com"
            required
            :aria-invalid="Boolean(emailError)"
          />
          <span v-if="emailError" class="field-error">{{ emailError }}</span>
        </label>

        <label class="form-field">
          <span class="form-label">Пароль</span>
          <input
            v-model="form.password"
            class="form-control"
            type="password"
            autocomplete="current-password"
            placeholder="Введите пароль"
            required
            :aria-invalid="Boolean(passwordError)"
          />
          <span v-if="passwordError" class="field-error">{{ passwordError }}</span>
        </label>

        <label class="checkbox-field">
          <input v-model="form.remember" class="checkbox-control" type="checkbox" />
          <span>Запомнить меня</span>
        </label>

        <p v-if="message" class="form-message">{{ message }}</p>

        <button class="button button-primary" type="submit" :disabled="isSubmitting">
          {{ isSubmitting ? 'Входим...' : 'Войти' }}
        </button>
      </form>
    </section>
  </main>
</template>

<style scoped>
.auth-shell {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: flex-start;
  padding: 24px;
  background:
    radial-gradient(circle at 78% 22%, color-mix(in srgb, var(--color-primary) 18%, transparent), transparent 30%),
    linear-gradient(135deg, var(--color-bg), var(--color-bg-soft));
}

.auth-panel {
  position: relative;
  display: flex;
  flex-direction: column;
  justify-content: center;
  width: 100%;
  max-width: 440px;
  padding: 48px;
  background: var(--color-surface);
  border: 1px solid var(--color-border-soft);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-md);
}

.auth-header {
  margin-bottom: 32px;
}

.auth-header h1 {
  margin-bottom: 10px;
  font-size: 34px;
}

.auth-header p:last-child {
  max-width: 34ch;
  margin-bottom: 0;
}

.eyebrow {
  margin-bottom: 10px;
  color: var(--color-primary);
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.toggler-theme {
  position: absolute;
  top: 20px;
  right: 20px;
}

@media (max-width: 560px) {
  .auth-shell {
    padding: 0;
  }

  .auth-panel {
    max-width: none;
    padding: 84px 24px 32px;
    border-width: 0;
    border-radius: 0;
  }
}
</style>
