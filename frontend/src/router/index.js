import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'

import Home from '../views/Home.vue'
import Login from '../views/Login.vue'
import Register from '../views/Register.vue'
import NotFound from '../views/NotFound.vue'

const ClientMenu = () => import('../views/client/Menu.vue')

const AdminDashboard = () => import('../views/admin/Dashboard.vue')
const AdminRestaurants = () => import('../views/admin/Restaurants.vue')
const AdminProducts = () => import('../views/admin/Products.vue')
const AdminUsers = () => import('../views/admin/Users.vue')
const AdminOnboarding = () => import('../views/admin/Onboarding.vue')
const AdminSettings = () => import('../views/admin/Settings.vue')

const LegalNotice = () => import('../views/legal/LegalNotice.vue')
const PrivacyPolicy = () => import('../views/legal/PrivacyPolicy.vue')
const CookiePolicy = () => import('../views/legal/CookiePolicy.vue')
const TermsConditions = () => import('../views/legal/TermsConditions.vue')

const routes = [
  { path: '/', name: 'Home', component: Home, meta: { public: true } },
  { path: '/login', name: 'Login', component: Login, meta: { public: true } },
  { path: '/register', name: 'Register', component: Register, meta: { public: true } },
  { path: '/restaurant/:id', name: 'RestaurantMenu', component: ClientMenu, meta: { public: true } },
  { path: '/legal/aviso-legal', name: 'LegalNotice', component: LegalNotice, meta: { public: true } },
  { path: '/legal/privacidad', name: 'PrivacyPolicy', component: PrivacyPolicy, meta: { public: true } },
  { path: '/legal/cookies', name: 'CookiePolicy', component: CookiePolicy, meta: { public: true } },
  { path: '/legal/terminos', name: 'TermsConditions', component: TermsConditions, meta: { public: true } },

  // Admin routes
  { path: '/admin', name: 'AdminDashboard', component: AdminDashboard, meta: { requiresAuth: true, roles: ['admin', 'superadmin'] } },
  { path: '/admin/onboarding', name: 'AdminOnboarding', component: AdminOnboarding, meta: { requiresAuth: true, roles: ['admin'] } },
  { path: '/admin/restaurants', name: 'AdminRestaurants', component: AdminRestaurants, meta: { requiresAuth: true, roles: ['admin', 'superadmin'] } },
  { path: '/admin/products', name: 'AdminProducts', component: AdminProducts, meta: { requiresAuth: true, roles: ['admin', 'superadmin'] } },
  { path: '/admin/users', name: 'AdminUsers', component: AdminUsers, meta: { requiresAuth: true, roles: ['superadmin'] } },
  { path: '/admin/settings', name: 'AdminSettings', component: AdminSettings, meta: { requiresAuth: true, roles: ['superadmin'] } },

  // 404
  { path: '/:pathMatch(.*)*', name: 'NotFound', component: NotFound, meta: { public: true } }
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior() {
    return { top: 0, behavior: 'instant' }
  }
})

router.beforeEach((to) => {
  const auth = useAuthStore()
  // Auth is already initialized in main.js before mount — no need to await here.

  const isPublic = Boolean(to.meta?.public)
  const requiresAuth = Boolean(to.meta?.requiresAuth)
  const allowedRoles = Array.isArray(to.meta?.roles) ? to.meta.roles : []
  const userRole = auth.userRole

  if (isPublic) {
    // Redirect authenticated users away from login, register, and home
    if (auth.isAuthenticated && (to.name === 'Login' || to.name === 'Register' || to.name === 'Home')) {
      return '/admin'
    }
    return true
  }

  if (requiresAuth) {
    if (!auth.isAuthenticated) {
      return { path: '/login', query: { redirect: to.fullPath } }
    }
    if (allowedRoles.length > 0 && !allowedRoles.includes(userRole)) {
      return '/admin'
    }
  }

  return true
})

export default router
