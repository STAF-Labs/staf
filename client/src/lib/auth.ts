import { ref } from 'vue'
import { ApiError, apiRequest, csrfCookie } from './api'

export type User = {
  id: number
  username: string
  email: string
  status: string
}

type LoginPayload = {
  email: string
  password: string
  remember: boolean
}

type AuthResponse = {
  user: User
}

export const currentUser = ref<User | null>(null)
export const authLoaded = ref(false)

export async function fetchCurrentUser(): Promise<User | null> {
  try {
    const user = await apiRequest<User>('/api/user')

    currentUser.value = user

    return user
  } catch (error) {
    if (error instanceof ApiError && error.status === 401) {
      currentUser.value = null

      return null
    }

    throw error
  } finally {
    authLoaded.value = true
  }
}

export async function login(payload: LoginPayload): Promise<User> {
  await csrfCookie()

  const response = await apiRequest<AuthResponse>('/login', {
    method: 'POST',
    body: payload,
  })

  currentUser.value = response.user
  authLoaded.value = true

  return response.user
}

export async function logout(): Promise<void> {
  await csrfCookie()
  await apiRequest('/logout', {
    method: 'POST',
  })

  currentUser.value = null
  authLoaded.value = true
}
