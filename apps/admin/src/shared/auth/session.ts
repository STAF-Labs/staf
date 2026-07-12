import { ref } from 'vue'
import { AxiosError } from 'axios'
import { http } from '../api/http'

export type User = {
  id: number
  username: string
  email: string
  status: string
}

export type ValidationErrors = Record<string, string[]>

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

export function validationErrors(error: unknown): ValidationErrors {
  if (error instanceof AxiosError && error.response?.status === 422) {
    return error.response.data.errors ?? {}
  }

  return {}
}

export function errorMessage(error: unknown): string {
  if (error instanceof AxiosError) {
    return error.response?.data.message ?? 'Запрос не выполнен.'
  }

  return 'Запрос не выполнен.'
}

export async function fetchCurrentUser(): Promise<User | null> {
  try {
    const response = await http.get<User>('/api/user')

    currentUser.value = response.data

    return response.data
  } catch (error) {
    if (error instanceof AxiosError) {
      currentUser.value = null

      return null
    }

    throw error
  } finally {
    authLoaded.value = true
  }
}

export async function login(payload: LoginPayload): Promise<User> {
  await http.get('/sanctum/csrf-cookie')

  const response = await http.post<AuthResponse>('/login', payload)

  currentUser.value = response.data.user
  authLoaded.value = true

  return response.data.user
}

export async function logout(): Promise<void> {
  await http.get('/sanctum/csrf-cookie')
  await http.post('/logout')

  currentUser.value = null
  authLoaded.value = true
}
