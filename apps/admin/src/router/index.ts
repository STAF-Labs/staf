import { createRouter, createWebHistory } from 'vue-router'
import { authLoaded, currentUser, fetchCurrentUser } from '../shared/auth/session'
import DashboardView from '../views/dashboard/DashboardView.vue'
import LoginView from '../views/auth/LoginView.vue'
import UsersView from '../views/users/UsersView.vue'
import OrganizationsView from '@/views/orgs/OrganizationsView.vue'
import OrganizationView from '@/views/orgs/OrganizationView.vue'
import UserView from '@/views/users/UserView.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      redirect: { name: 'dashboard' },
    },
    {
      path: '/login',
      name: 'login',
      component: LoginView,
      meta: {
        guest: true,
        title: 'Вход',
      },
    },
    {
      path: '/dashboard',
      name: 'dashboard',
      component: DashboardView,
      meta: {
        requiresAuth: true,
        title: 'Главная',
      },
    },
    {
      path: '/users',
      name: 'users.index',
      component: UsersView,
      meta: {
        requiresAuth: true,
        title: 'Пользователи',
      },
    },
    {
      path: '/users/profiles',
      redirect: { name: 'users.index' },
    },
    {
      path: '/users/:id',
      name: 'user.show',
      component: UserView,
      meta: {
        requiresAuth: true,
        title: 'Пользователь',
      },
    },
    {
      path: '/organizations',
      name: 'orgs.index',
      component: OrganizationsView,
      meta: {
        requiresAuth: true,
        title: 'Организации',
      },
    },
    {
      path: '/organizations/:id',
      name: 'org.show',
      component: OrganizationView,
      meta: {
        requiresAuth: true,
        title: 'Организация',
      },
    },
  ],
})

router.beforeEach(async (to) => {
  if (!authLoaded.value) {
    await fetchCurrentUser()
  }

  if (to.meta.requiresAuth && !currentUser.value) {
    return { name: 'login' }
  }

  if (to.meta.guest && currentUser.value) {
    return { name: 'dashboard' }
  }
})

router.afterEach((to) => {
  const title = typeof to.meta.title === 'string' ? to.meta.title : ''

  document.title = title ? `${title} - STAF Admin` : 'STAF Admin'
})

export default router
