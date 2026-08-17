import { push } from 'notivue'

type OrganizationActionNotification = 'blocked' | 'deleted' | 'frozen' | 'unblocked' | 'unfrozen'

const organizationActionNotifications = {
  blocked: {
    title: 'Организация заблокирована',
    tone: 'info',
  },
  deleted: {
    title: 'Организация удалена',
    tone: 'error',
  },
  frozen: {
    title: 'Организация заморожена',
    tone: 'info',
  },
  unblocked: {
    title: 'Организация разблокирована',
    tone: 'info',
  },
  unfrozen: {
    title: 'Организация разморожена',
    tone: 'info',
  },
} satisfies Record<OrganizationActionNotification, { title: string; tone: 'error' | 'info' }>

export function pushOrganizationActionNotification(notification: OrganizationActionNotification): void {
  const config = organizationActionNotifications[notification]

  push[config.tone]({
    title: config.title,
  })
}
