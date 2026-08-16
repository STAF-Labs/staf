<script setup lang="ts">
import { Notification, NotificationProgress, Notivue } from 'notivue'
import { RouterView } from 'vue-router'
</script>

<template>
  <Notivue v-slot="item" class="staf-notivue" list-aria-label="Notifications">
    <Notification :item="item" close-aria-label="Close notification">
      <NotificationProgress :item="item" />
    </Notification>
  </Notivue>
  <RouterView />
</template>
