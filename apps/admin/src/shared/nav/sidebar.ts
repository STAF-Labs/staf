import type { Component } from 'vue'
import {
  Building2,
  FolderKanban,
  Gamepad2,
  House,
  Library,
  Tags,
  Users,
} from '@lucide/vue'

export type SidebarLinkItem = {
  type: 'link'
  label: string
  routeName: string
  icon: Component
}

export type SidebarGroupItem = {
  type: 'group'
  key: string
  label: string
  icon: Component
  children: SidebarLinkItem[]
}

export type SidebarItem = SidebarLinkItem | SidebarGroupItem

export const sidebarItems: SidebarItem[] = [
  {
    type: 'link',
    label: 'Главная',
    routeName: 'dashboard',
    icon: House,
  },
  {
    type: 'link',
    label: 'Пользователи',
    routeName: 'users.index',
    icon: Users,
  },
  {
    type: 'group',
    key: 'content',
    label: 'Контент',
    icon: Library,
    children: [
      {
        type: 'link',
        label: 'Игры',
        routeName: 'games.index',
        icon: Gamepad2,
      },
      {
        type: 'link',
        label: 'Проекты',
        routeName: 'projects.index',
        icon: FolderKanban,
      },
      {
        type: 'link',
        label: 'Типы контента',
        routeName: 'content-types.index',
        icon: Tags,
      },
    ],
  },
  {
    type: 'group',
    key: 'orgs',
    label: 'Организации',
    icon: Building2,
    children: [
      {
        type: 'link',
        label: 'Организации',
        routeName: 'orgs.index',
        icon: Building2,
      },
      // {
      //   type: 'link',
      //   label: 'Члены организации',
      //   routeName: 'orgs.members',
      //   icon: UserRound,
      // },
    ],
  },
  // {
  //   type: 'link',
  //   label: 'Настройки',
  //   routeName: 'system.settings',
  //   icon: Settings2,
  // },
]
