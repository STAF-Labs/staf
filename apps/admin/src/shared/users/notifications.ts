import { push } from 'notivue'

type UserActionNotification = 'blocked' | 'deleted' | 'frozen' | 'unblocked' | 'unfrozen'

const userActionNotifications = {
  blocked: {
    title: 'Пользователь заблокирован',
    tone: 'info',
  },
  deleted: {
    title: 'Пользователь удален',
    tone: 'error',
  },
  frozen: {
    title: 'Пользователь заморожен',
    tone: 'info',
  },
  unblocked: {
    title: 'Пользователь разблокирован',
    tone: 'info',
  },
  unfrozen: {
    title: 'Пользователь разморожен',
    tone: 'info',
  },
} satisfies Record<UserActionNotification, { title: string; tone: 'error' | 'info' }>

export function pushUserActionNotification(notification: UserActionNotification): void {
  const config = userActionNotifications[notification]

  push[config.tone]({
    title: config.title,
  })
}
