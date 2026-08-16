import { createApp } from 'vue'
import App from './App.vue'
import router from './router'
import { notivue } from '@/shared/notifications/notivue'
import '@staf/assets/styles/index.css'
import '@/assets/styles/notifications/index.css'
import '@/assets/styles/project-completion-card.css'

const app = createApp(App)
app.use(router)
app.use(notivue)
app.mount('#app')
