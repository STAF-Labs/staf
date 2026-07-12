import { ref } from 'vue'

type Theme = 'light' | 'dark'
const storageKey = 'theme'

function getInitTheme(): Theme {
  const savedTheme = localStorage.getItem(storageKey)

  if (savedTheme === 'light' || savedTheme === 'dark') {
    return savedTheme
  }

  return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
}

const theme = ref<Theme>(getInitTheme())

function applyTheme(value: Theme): void {
  theme.value = value
  document.documentElement.dataset.theme = value
  localStorage.setItem(storageKey, value)
}

applyTheme(theme.value)

export function useTheme() {
  function toggleTheme(): void {
    applyTheme(theme.value === 'dark' ? 'light' : 'dark')
  }

  return { theme, applyTheme, toggleTheme }
}
