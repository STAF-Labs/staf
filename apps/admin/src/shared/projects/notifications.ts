import { push } from 'notivue'

type ProjectActionNotification =
  | 'created'
  | 'deleted'
  | 'memberAdded'
  | 'releaseCreated'
  | 'releaseDeleted'
  | 'releaseUpdated'
  | 'screenshotDeleted'
  | 'screenshotsSaved'
  | 'statusChanged'
  | 'updated'

const projectActionNotifications = {
  created: {
    title: 'Проект создан',
    tone: 'success',
  },
  deleted: {
    title: 'Проект удален',
    tone: 'error',
  },
  memberAdded: {
    title: 'Участник добавлен',
    tone: 'success',
  },
  releaseCreated: {
    title: 'Релиз создан',
    tone: 'success',
  },
  releaseDeleted: {
    title: 'Релиз удален',
    tone: 'error',
  },
  releaseUpdated: {
    title: 'Релиз сохранен',
    tone: 'success',
  },
  screenshotDeleted: {
    title: 'Скриншот удален',
    tone: 'error',
  },
  screenshotsSaved: {
    title: 'Скриншоты сохранены',
    tone: 'success',
  },
  statusChanged: {
    title: 'Статус проекта изменен',
    tone: 'info',
  },
  updated: {
    title: 'Проект сохранен',
    tone: 'success',
  },
} satisfies Record<ProjectActionNotification, { title: string; tone: 'error' | 'info' | 'success' }>

export function pushProjectActionNotification(notification: ProjectActionNotification): void {
  const config = projectActionNotifications[notification]

  push[config.tone]({
    title: config.title,
  })
}
