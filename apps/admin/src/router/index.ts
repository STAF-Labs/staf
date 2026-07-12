import { createRouter, createWebHistory } from 'vue-router'
import { authLoaded, currentUser, fetchCurrentUser } from '../shared/auth/session'
import DashboardView from '../views/dashboard/DashboardView.vue'
import LoginView from '../views/auth/LoginView.vue'
import UserProfilesView from '../views/users/UserProfilesView.vue'
import UsersView from '../views/users/UsersView.vue'

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
      name: 'users.profiles',
      component: UserProfilesView,
      meta: {
        requiresAuth: true,
        title: 'Профили',
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
