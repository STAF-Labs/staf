import { createApp } from 'vue'
import App from './App.vue'
import router from './router'
import '@staf/assets/styles/index.css'
import '@/assets/styles/project-completion-card.css'

const app = createApp(App)
app.use(router)
app.mount('#app')
