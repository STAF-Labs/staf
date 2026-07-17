/// <reference types="vite/client" />

import 'vue-router'

declare module 'vue-router' {
  interface RouteMeta {
    breadcrumb?: {
      label?: string
      parentName?: string
    }
    breadcrumbLabel?: string
    guest?: boolean
    requiresAuth?: boolean
    title?: string
  }
}
