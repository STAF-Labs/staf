import { push } from 'notivue'

type GameActionNotification = 'created' | 'deleted' | 'filtersImported' | 'updated'

const gameActionNotifications = {
  created: {
    title: 'Игра создана',
    tone: 'success',
  },
  deleted: {
    title: 'Игра удалена',
    tone: 'error',
  },
  filtersImported: {
    title: 'Фильтры импортированы',
    tone: 'success',
  },
  updated: {
    title: 'Игра сохранена',
    tone: 'success',
  },
} satisfies Record<GameActionNotification, { title: string; tone: 'error' | 'success' }>

export function pushGameActionNotification(notification: GameActionNotification): void {
  const config = gameActionNotifications[notification]

  push[config.tone]({
    title: config.title,
  })
}
