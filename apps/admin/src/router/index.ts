import { createRouter, createWebHistory } from 'vue-router'
import { authLoaded, currentUser, fetchCurrentUser } from '../shared/auth/session'
import {
  projectBasicsAreComplete,
  projectCreateContinueTargetById,
  projectDescriptionIsComplete,
} from '@/shared/projects/project-create'
import ContentTypesImportView from '@/views/content-types/ContentTypesImportView.vue'
import ContentTypesView from '@/views/content-types/ContentTypesView.vue'
import DashboardView from '../views/dashboard/DashboardView.vue'
import LoginView from '../views/auth/LoginView.vue'
import UsersView from '../views/users/UsersView.vue'
import GameFormView from '@/views/games/GameFormView.vue'
import GameFiltersImportView from '@/views/games/GameFiltersImportView.vue'
import GameView from '@/views/games/GameView.vue'
import GamesView from '@/views/games/GamesView.vue'
import OrganizationsView from '@/views/orgs/OrganizationsView.vue'
import OrganizationView from '@/views/orgs/OrganizationView.vue'
import ProjectCreateDetailsView from '@/views/projects/ProjectCreateDetailsView.vue'
import ProjectCreateNextView from '@/views/projects/ProjectCreateNextView.vue'
import ProjectGameSelectView from '@/views/projects/ProjectGameSelectView.vue'
import ProjectPreviewView from '@/views/projects/ProjectPreviewView.vue'
import ProjectFormView from '@/views/projects/ProjectFormView.vue'
import ProjectReleaseFormView from '@/views/projects/ProjectReleaseFormView.vue'
import ProjectsView from '@/views/projects/ProjectsView.vue'
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
        breadcrumb: {
          label: 'Главная',
        },
        requiresAuth: true,
        title: 'Главная',
      },
    },
    {
      path: '/profile',
      name: 'profile.show',
      component: UserView,
      props: {
        profileMode: true,
      },
      meta: {
        breadcrumb: {
          label: 'Профиль',
          parentName: 'dashboard',
        },
        requiresAuth: true,
        title: 'Профиль',
      },
    },
    {
      path: '/users',
      name: 'users.index',
      component: UsersView,
      meta: {
        breadcrumb: {
          label: 'Пользователи',
          parentName: 'dashboard',
        },
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
        breadcrumb: {
          parentName: 'users.index',
        },
        requiresAuth: true,
        title: 'Пользователь',
      },
    },
    {
      path: '/games',
      name: 'games.index',
      component: GamesView,
      meta: {
        breadcrumb: {
          label: 'Игры',
          parentName: 'dashboard',
        },
        requiresAuth: true,
        title: 'Игры',
      },
    },
    {
      path: '/games/create',
      name: 'games.create',
      component: GameFormView,
      meta: {
        breadcrumb: {
          label: 'Создание игры',
          parentName: 'games.index',
        },
        requiresAuth: true,
        title: 'Создание игры',
      },
    },
    {
      path: '/games/:id/content-types/:gameContentTypeId/filters/import',
      name: 'games.content-types.filters.import',
      component: GameFiltersImportView,
      meta: {
        breadcrumb: {
          label: 'Импорт фильтров',
          parentName: 'games.index',
        },
        requiresAuth: true,
        title: 'Импорт фильтров',
      },
    },
    {
      path: '/games/:id',
      name: 'games.show',
      component: GameView,
      meta: {
        breadcrumb: {
          parentName: 'games.index',
        },
        requiresAuth: true,
        title: 'Просмотр игры',
      },
    },
    {
      path: '/games/:id/edit',
      name: 'games.edit',
      component: GameFormView,
      meta: {
        breadcrumb: {
          parentName: 'games.index',
        },
        requiresAuth: true,
        title: 'Редактирование игры',
      },
    },
    {
      path: '/projects',
      name: 'projects.index',
      component: ProjectsView,
      meta: {
        breadcrumb: {
          label: 'Проекты',
          parentName: 'dashboard',
        },
        requiresAuth: true,
        title: 'Проекты',
      },
    },
    {
      path: '/content-types',
      name: 'content-types.index',
      component: ContentTypesView,
      meta: {
        breadcrumb: {
          label: 'Типы контента',
          parentName: 'dashboard',
        },
        requiresAuth: true,
        title: 'Типы контента',
      },
    },
    {
      path: '/content-types/import',
      name: 'content-types.import',
      component: ContentTypesImportView,
      meta: {
        breadcrumb: {
          label: 'Импорт из Excel',
          parentName: 'content-types.index',
        },
        requiresAuth: true,
        title: 'Импорт типов контента',
      },
    },
    {
      path: '/projects/create',
      name: 'projects.create',
      component: ProjectGameSelectView,
      meta: {
        breadcrumb: {
          label: 'Создание проекта',
          parentName: 'projects.index',
        },
        requiresAuth: true,
        title: 'Создание проекта',
      },
    },
    {
      path: '/projects/:id/continue',
      name: 'projects.continue',
      component: ProjectGameSelectView,
      beforeEnter: async (to) => {
        const projectId = Number(to.params.id)

        if (!Number.isInteger(projectId) || projectId <= 0) {
          return { name: 'projects.index' }
        }

        try {
          return await projectCreateContinueTargetById(projectId)
        } catch {
          return { name: 'projects.index' }
        }
      },
      meta: {
        breadcrumb: {
          label: 'Продолжение создания',
          parentName: 'projects.index',
        },
        requiresAuth: true,
        title: 'Создание проекта',
      },
    },
    {
      path: '/projects/create/:gameId/details',
      name: 'projects.create.details',
      component: ProjectCreateDetailsView,
      meta: {
        breadcrumb: {
          label: 'Параметры проекта',
          parentName: 'projects.create',
        },
        requiresAuth: true,
        title: 'Создание проекта',
      },
    },
    {
      path: '/projects/create/:gameId/description',
      name: 'projects.create.description',
      component: ProjectCreateNextView,
      beforeEnter: (to) => {
        const gameId = String(to.params.gameId ?? '')
        const projectId = Number(to.query.projectId)

        if (Number.isInteger(projectId) && projectId > 0) {
          return true
        }

        return projectBasicsAreComplete(gameId)
          ? true
          : { name: 'projects.create.details', params: { gameId } }
      },
      meta: {
        breadcrumb: {
          label: 'Описание',
          parentName: 'projects.create',
        },
        requiresAuth: true,
        title: 'Создание проекта',
      },
    },
    {
      path: '/projects/create/:gameId/licence',
      name: 'projects.create.licence',
      component: () => import('@/views/projects/ProjectCreateLicenceView.vue'),
      beforeEnter: (to) => {
        const gameId = String(to.params.gameId ?? '')
        const projectId = Number(to.query.projectId)

        if (Number.isInteger(projectId) && projectId > 0) {
          return true
        }

        return projectDescriptionIsComplete(gameId)
          ? true
          : { name: 'projects.create.description', params: { gameId } }
      },
      meta: {
        breadcrumb: {
          label: 'Лицензия',
          parentName: 'projects.create',
        },
        requiresAuth: true,
        title: 'Создание проекта',
      },
    },
    {
      path: '/projects/create/:gameId/continue',
      name: 'projects.create.continue',
      component: () => import('@/views/projects/ProjectCreateContinueView.vue'),
      beforeEnter: (to) => {
        const gameId = String(to.params.gameId ?? '')
        const projectId = Number(to.query.projectId)

        if (Number.isInteger(projectId) && projectId > 0) {
          return true
        }

        return projectDescriptionIsComplete(gameId)
          ? true
          : { name: 'projects.create.description', params: { gameId } }
      },
      meta: {
        breadcrumb: {
          label: 'Подтверждение',
          parentName: 'projects.create',
        },
        requiresAuth: true,
        title: 'Создание проекта',
      },
    },
    {
      path: '/projects/:id/releases/create',
      name: 'projects.releases.create',
      component: ProjectReleaseFormView,
      meta: {
        breadcrumb: {
          label: 'Создание релиза',
          parentName: 'projects.index',
        },
        requiresAuth: true,
        title: 'Создание релиза',
      },
    },
    {
      path: '/projects/:id/releases/:releaseId/edit',
      name: 'projects.releases.edit',
      component: ProjectReleaseFormView,
      meta: {
        breadcrumb: {
          label: 'Редактирование релиза',
          parentName: 'projects.index',
        },
        requiresAuth: true,
        title: 'Редактирование релиза',
      },
    },
    {
      path: '/projects/:id',
      name: 'projects.show',
      component: ProjectPreviewView,
      meta: {
        breadcrumb: {
          label: 'Превью',
          parentName: 'projects.index',
        },
        requiresAuth: true,
        title: 'Превью проекта',
      },
    },
    {
      path: '/projects/:id/edit',
      name: 'projects.edit',
      component: ProjectFormView,
      beforeEnter: async (to) => {
        const projectId = Number(to.params.id)

        if (!Number.isInteger(projectId) || projectId <= 0) {
          return { name: 'projects.index' }
        }

        try {
          const target = await projectCreateContinueTargetById(projectId)

          return target.name === 'projects.show' ? true : target
        } catch {
          return true
        }
      },
      meta: {
        breadcrumb: { parentName: 'projects.index' },
        requiresAuth: true,
        title: 'Редактирование проекта',
      },
    },
    {
      path: '/organizations',
      name: 'orgs.index',
      component: OrganizationsView,
      meta: {
        breadcrumb: {
          label: 'Организации',
          parentName: 'dashboard',
        },
        requiresAuth: true,
        title: 'Организации',
      },
    },
    {
      path: '/organizations/:id',
      name: 'org.show',
      component: OrganizationView,
      meta: {
        breadcrumb: {
          parentName: 'orgs.index',
        },
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
