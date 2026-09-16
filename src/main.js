import 'bootstrap-icons/font/bootstrap-icons.css'
import '@hands-on/glass/styles.css'
import './assets/main.css'

import { createApp } from 'vue'
import { initTheme } from '@hands-on/glass/theme'
import App from './App.vue'
import router from './router'
import { i18n } from './i18n'

initTheme()

const app = createApp(App)
app.use(i18n)
app.use(router)
app.mount('#app')
