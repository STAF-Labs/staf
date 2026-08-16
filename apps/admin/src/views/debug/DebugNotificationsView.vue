<script setup lang="ts">
import {
  AlertTriangle,
  Bell,
  CheckCircle2,
  ChevronRight,
  Info,
  LoaderCircle,
  ShieldAlert,
  type LucideIcon,
} from '@lucide/vue'
import { push } from 'notivue'
import DebugShell from '@/components/debug/DebugShell.vue'

function showSuccessNotification(): void {
  push.success({
    title: 'Данные сохранены',
    message: 'Изменения применены и доступны пользователям.',
  })
}

function showInfoNotification(): void {
  push.info({
    title: 'Новое сообщение',
    message: 'Пользователь отправил вам сообщение.',
  })
}

function showWarningNotification(): void {
  push.warning({
    title: 'Проверьте данные',
    message: 'Некоторые поля требуют внимания перед публикацией.',
  })
}

function showDangerNotification(): void {
  push.error({
    title: 'Не удалось выполнить действие',
    message: 'Повторите попытку или проверьте подключение.',
  })
}

function showPromiseNotification(): void {
  const notification = push.promise({
    title: 'Загрузка',
    message: 'Выполняем тестовую операцию.',
  })

  window.setTimeout(() => {
    notification.success({
      title: 'Операция завершена',
      message: 'Promise-уведомление перешло в success.',
    })
  }, 1400)
}

const notificationButtons = [
  {
    label: 'Success',
    badge: 'Успех',
    description: 'Показывает успешное завершение действия.',
    tone: 'success',
    icon: CheckCircle2,
    action: showSuccessNotification,
  },
  {
    label: 'Info',
    badge: 'Информация',
    description: 'Показывает нейтральное системное сообщение.',
    tone: 'info',
    icon: Info,
    action: showInfoNotification,
  },
  {
    label: 'Warning',
    badge: 'Предупреждение',
    description: 'Показывает предупреждение без блокировки сценария.',
    tone: 'warning',
    icon: AlertTriangle,
    action: showWarningNotification,
  },
  {
    label: 'Danger',
    badge: 'Ошибка',
    description: 'Показывает ошибку или опасное состояние.',
    tone: 'danger',
    icon: ShieldAlert,
    action: showDangerNotification,
  },
  {
    label: 'Promise',
    badge: 'Загрузка',
    description: 'Показывает loading, затем success после задержки.',
    tone: 'promise',
    icon: LoaderCircle,
    action: showPromiseNotification,
  },
] satisfies {
  label: string
  badge: string
  description: string
  tone: 'success' | 'info' | 'warning' | 'danger' | 'promise'
  icon: LucideIcon
  action: () => void
}[]
</script>

<template>
  <DebugShell>
    <section class="debug-notifications">
      <div class="debug-notifications__grid">
        <button
          v-for="button in notificationButtons"
          :key="button.tone"
          type="button"
          class="debug-notifications-panel"
          :class="`debug-notifications-panel--${button.tone}`"
          @click="button.action"
        >
          <span class="debug-notifications-panel__top">
            <span class="debug-notifications-panel__icon">
              <component :is="button.icon" :size="28" :stroke-width="2.2" />
            </span>

            <span class="debug-notifications-panel__title">{{ button.label }}</span>
            <span class="debug-notifications-panel__badge">{{ button.badge }}</span>
          </span>

          <span class="debug-notifications-panel__bottom">
            <span class="debug-notifications-panel__description">{{ button.description }}</span>
            <ChevronRight class="debug-notifications-panel__arrow" :size="20" :stroke-width="1.9" />
          </span>
        </button>
      </div>
    </section>
  </DebugShell>
</template>

<style scoped>
.debug-notifications {
  display: grid;
  gap: 28px;
}

.debug-notifications__header {
  display: flex;
  align-items: center;
  gap: 18px;
  max-width: 920px;
  padding-bottom: 28px;
  border-bottom: 1px solid color-mix(in srgb, var(--color-border) 72%, transparent);
}

.debug-notifications__header-icon {
  display: grid;
  place-items: center;
  flex: 0 0 auto;
  width: 74px;
  height: 74px;
  color: var(--color-primary);
  background:
    linear-gradient(145deg, color-mix(in srgb, var(--color-primary) 18%, transparent), transparent),
    color-mix(in srgb, var(--color-surface) 74%, transparent);
  border: 1px solid color-mix(in srgb, var(--color-primary) 46%, var(--color-border));
  border-radius: var(--radius-lg);
  box-shadow:
    inset 0 1px 0 rgb(255 255 255 / 0.08),
    0 18px 44px color-mix(in srgb, var(--color-primary) 18%, transparent);
}

.debug-notifications__header h2 {
  margin-bottom: 6px;
  font-size: 28px;
}

.debug-notifications__header p {
  max-width: 640px;
  margin: 0;
  color: var(--color-text-muted);
  font-size: 15px;
}

.debug-notifications__grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 320px), 400px));
  gap: 30px;
  align-items: stretch;
}

.debug-notifications-panel {
  --debug-card-accent: var(--color-primary);
  --debug-card-bg: rgb(255 255 255 / 0.06);
  --debug-card-glow: color-mix(in srgb, var(--debug-card-accent) 18%, transparent);

  position: relative;
  display: flex;
  flex-direction: column;
  gap: 22px;
  width: 100%;
  max-width: 400px;
  min-height: 196px;
  padding: 28px 24px 28px 28px;
  text-align: left;
  background:
    radial-gradient(circle at 18% 20%, var(--debug-card-glow), transparent 42%),
    linear-gradient(135deg, color-mix(in srgb, var(--debug-card-accent) 12%, transparent), transparent 54%),
    var(--debug-card-bg);
  backdrop-filter: blur(18px);
  border: 1px solid color-mix(in srgb, var(--debug-card-accent) 22%, var(--color-border));
  border-left-width: 4px;
  border-left-color: var(--debug-card-accent);
  border-radius: var(--radius-md);
  box-shadow:
    inset 0 1px 0 rgb(255 255 255 / 0.08),
    0 18px 46px rgb(0 0 0 / 0.18);
  transition:
    background-color 160ms ease,
    border-color 160ms ease,
    transform 160ms ease;
}

.debug-notifications-panel:hover {
  border-color: color-mix(in srgb, var(--debug-card-accent) 42%, var(--color-border));
  transform: translateY(-1px);
}

.debug-notifications-panel__top {
  display: grid;
  grid-template-columns: 72px minmax(0, 1fr) max-content;
  align-items: center;
  column-gap: 18px;
  width: 100%;
}

.debug-notifications-panel__icon {
  display: grid;
  place-items: center;
  flex: 0 0 auto;
  width: 72px;
  height: 72px;
  color: var(--debug-card-accent);
  background:
    radial-gradient(circle at 38% 34%, color-mix(in srgb, var(--debug-card-accent) 26%, transparent), transparent 62%),
    color-mix(in srgb, var(--debug-card-accent) 10%, var(--color-surface));
  border: 1px solid color-mix(in srgb, var(--debug-card-accent) 26%, var(--color-border));
  border-radius: var(--radius-md);
}

.debug-notifications-panel__title {
  min-width: 0;
  color: var(--color-text);
  font-size: 16px;
  font-weight: 700;
  overflow-wrap: anywhere;
}

.debug-notifications-panel__bottom {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 24px;
  align-items: end;
  gap: 12px;
  width: 100%;
  margin-top: auto;
}

.debug-notifications-panel__description {
  margin-bottom: 0;
  color: var(--color-text-muted);
  font-size: 14px;
  line-height: 1.45;
}

.debug-notifications-panel__badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  justify-self: end;
  min-width: 74px;
  max-width: 124px;
  min-height: 34px;
  padding: 0 14px;
  color: var(--debug-card-accent);
  font-size: 13px;
  font-weight: 700;
  line-height: 1.15;
  text-align: center;
  white-space: nowrap;
  background: color-mix(in srgb, var(--debug-card-accent) 14%, transparent);
  border-radius: 999px;
}

.debug-notifications-panel__arrow {
  align-self: end;
  color: var(--color-text-soft);
}

.debug-notifications-panel--success {
  --debug-card-accent: var(--color-success);
}

.debug-notifications-panel--info {
  --debug-card-accent: var(--color-info);
}

.debug-notifications-panel--warning {
  --debug-card-accent: var(--color-warning);
}

.debug-notifications-panel--danger {
  --debug-card-accent: var(--color-danger);
}

.debug-notifications-panel--promise {
  --debug-card-accent: var(--color-primary);
}

@media (max-width: 700px) {
  .debug-notifications__header {
    align-items: flex-start;
  }

  .debug-notifications-panel {
    min-height: 132px;
    padding: 20px;
  }

  .debug-notifications-panel__top {
    grid-template-columns: 56px minmax(0, 1fr) max-content;
    column-gap: 14px;
  }

  .debug-notifications-panel__icon {
    width: 56px;
    height: 56px;
  }

  .debug-notifications-panel__badge {
    max-width: 116px;
    padding-inline: 10px;
    font-size: 12px;
  }
}

@media (max-width: 520px) {
  .debug-notifications__header {
    display: grid;
  }

  .debug-notifications__grid {
    grid-template-columns: 1fr;
  }
}
</style>
