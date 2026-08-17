import { push } from 'notivue'

type ContentTypeActionNotification =
  | 'created'
  | 'deleted'
  | 'importFailed'
  | 'imported'
  | 'statusChanged'
  | 'updated'

const contentTypeActionNotifications = {
  created: {
    title: 'Тип контента создан',
    tone: 'success',
  },
  deleted: {
    title: 'Тип контента удален',
    tone: 'error',
  },
  importFailed: {
    title: 'Не удалось импортировать типы контента',
    tone: 'error',
  },
  imported: {
    title: 'Импорт типов контента завершен',
    tone: 'success',
  },
  statusChanged: {
    title: 'Статус типа контента изменен',
    tone: 'info',
  },
  updated: {
    title: 'Тип контента обновлен',
    tone: 'success',
  },
} satisfies Record<ContentTypeActionNotification, { title: string; tone: 'error' | 'info' | 'success' }>

export function pushContentTypeActionNotification(notification: ContentTypeActionNotification): void {
  const config = contentTypeActionNotifications[notification]

  push[config.tone]({
    title: config.title,
  })
}
