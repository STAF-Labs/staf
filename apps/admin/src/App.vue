<script setup lang="ts">
import { Notification, NotificationProgress, Notivue } from 'notivue'
import { RouterView } from 'vue-router'

type StafNotificationActionTone = 'default' | 'primary' | 'danger'

interface StafNotificationAction {
  label: string
  tone?: StafNotificationActionTone
  onClick: () => void
}

interface StafNotificationProps {
  actions?: StafNotificationAction[]
  actor?: {
    initials: string
    label: string
    meta?: string
  }
  variant?: 'action'
}

function getNotificationActions(item: { props?: StafNotificationProps }): StafNotificationAction[] {
  return item.props?.actions ?? []
}

function isActionNotification(item: { props?: StafNotificationProps }): boolean {
  return item.props?.variant === 'action'
}
</script>

<template>
  <Notivue v-slot="item" class="staf-notivue" list-aria-label="Notifications">
    <Notification
      v-if="!isActionNotification(item)"
      :item="item"
      :class="{ 'staf-notification--with-actions': getNotificationActions(item).length }"
      close-aria-label="Close notification"
    >
      <NotificationProgress :item="item" />

      <div v-if="getNotificationActions(item).length" class="staf-notification-actions">
        <button
          v-for="action in getNotificationActions(item)"
          :key="action.label"
          type="button"
          class="staf-notification-action"
          :class="action.tone ? `staf-notification-action--${action.tone}` : undefined"
          @click="action.onClick"
        >
          {{ action.label }}
        </button>
      </div>
    </Notification>

    <article v-else class="staf-action-notification" :data-notivue="item.type" role="status">
      <div class="staf-action-notification__avatar" aria-hidden="true">
        {{ item.props.actor?.initials ?? 'ST' }}
        <span class="staf-action-notification__avatar-status"></span>
      </div>

      <div class="staf-action-notification__content">
        <p v-if="item.props.actor?.meta" class="staf-action-notification__meta">
          {{ item.props.actor.meta }}
        </p>
        <p class="staf-action-notification__message">
          <strong>{{ item.props.actor?.label ?? item.title }}</strong> <span v-if="item.message">{{ item.message }}</span>
        </p>

        <div class="staf-notification-actions staf-notification-actions--inline">
          <button
            v-for="action in getNotificationActions(item)"
            :key="action.label"
            type="button"
            class="staf-notification-action"
            :class="action.tone ? `staf-notification-action--${action.tone}` : undefined"
            @click="action.onClick"
          >
            {{ action.label }}
          </button>
        </div>
      </div>

      <NotificationProgress :item="item" />
    </article>
  </Notivue>
  <RouterView />
</template>
