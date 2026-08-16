import { createNotivue, type NotivueConfig } from 'notivue'

export const notivueConfig = {
  position: 'top-right',
  limit: 4,
  enqueue: true,
  avoidDuplicates: true,
  pauseOnHover: true,
  pauseOnTouch: true,
  pauseOnTabChange: true,
  notifications: {
    global: {
      duration: 5000,
      ariaLive: 'polite',
      ariaRole: 'status',
    },
    success: {
      duration: 4000,
    },
    info: {
      duration: 6000,
    },
    warning: {
      duration: 7000,
    },
    error: {
      duration: 9000,
      ariaLive: 'assertive',
      ariaRole: 'alert',
    },
  },
} satisfies NotivueConfig

export const notivue = createNotivue(notivueConfig)
