import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import { useAuthStore } from './stores/auth'

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)

// Auth must be initialized BEFORE the router is installed.
// app.use(router) triggers the first navigation synchronously, which runs the
// route guard. If the guard runs before /api/me resolves, auth.user is null
// and any protected route redirects to /login — even with a valid token.
const auth = useAuthStore()
auth.initFromStorage()
  .catch(() => {})
  .finally(() => {
    app.use(router)   // install router AFTER auth is ready → guard has correct state
    app.mount('#app')
  })
