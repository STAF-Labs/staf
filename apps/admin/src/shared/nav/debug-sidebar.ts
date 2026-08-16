import { Bell, House } from '@lucide/vue'
import type { SidebarItem } from '@/shared/nav/sidebar'

export const debugSidebarItems: SidebarItem[] = [
  {
    type: 'link',
    label: 'Главная',
    routeName: 'debug.dashboard',
    icon: House,
  },
  {
    type: 'link',
    label: 'Уведомления',
    routeName: 'debug.notifications',
    icon: Bell,
  },
]
