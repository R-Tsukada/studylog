import { createRouter, createWebHistory } from 'vue-router'

// ページコンポーネントをインポート
import LoginPage from './pages/LoginPage.vue'
import Dashboard from './pages/Dashboard.vue'
import StudySession from './pages/StudySession.vue'
import History from './pages/History.vue'
import Settings from './pages/Settings.vue'

const routes = [
  {
    path: '/',
    name: 'Home',
    redirect: to => {
      // 認証状態に応じてリダイレクト
      const token = localStorage.getItem('auth_token')
      return token ? '/dashboard' : '/login'
    }
  },
  {
    path: '/login',
    name: 'Login',
    component: LoginPage,
    meta: { requiresGuest: true }
  },
  {
    path: '/register',
    name: 'Register', 
    component: LoginPage,
    props: { showRegister: true },
    meta: { requiresGuest: true }
  },
  {
    path: '/dashboard',
    name: 'Dashboard',
    component: Dashboard,
    meta: { requiresAuth: true }
  },
  {
    path: '/study',
    name: 'StudySession',
    component: StudySession,
    meta: { requiresAuth: true }
  },
  {
    path: '/history',
    name: 'History',
    component: History,
    meta: { requiresAuth: true }
  },
  {
    path: '/settings',
    name: 'Settings',
    component: Settings,
    meta: { requiresAuth: true }
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'NotFound',
    redirect: '/'
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

// ナビゲーションガード
router.beforeEach((to, from, next) => {
  const token = localStorage.getItem('auth_token')
  const isAuthenticated = !!token

  // 認証が必要なページ
  if (to.meta.requiresAuth && !isAuthenticated) {
    next('/login')
    return
  }

  // ゲスト専用ページ（ログイン済みならダッシュボードへ）
  if (to.meta.requiresGuest && isAuthenticated) {
    next('/dashboard')
    return
  }

  next()
})

export default router