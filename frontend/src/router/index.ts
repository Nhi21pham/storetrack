import { createRouter, createWebHistory } from 'vue-router'
import LoginView from '@/views/LoginView.vue'
import RegisterView from '@/views/RegisterView.vue'
import ForgotPasswordView from '@/views/ForgotPasswordView.vue'
import VerifyCodeView from '@/views/VerifyCodeView.vue'
import ResetPasswordView from '@/views/ResetPasswordView.vue'
import DashboardView from '@/views/DashboardView.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      redirect: '/login'
    },
    {
      path: '/login',
      name: 'login',
      component: LoginView,
      meta: { guest: true }
    },
    {
      path: '/register',
      name: 'register',
      component: RegisterView,
      meta: { guest: true }
    },
    {
      path: '/forgot-password',
      name: 'forgot-password',
      component: ForgotPasswordView,
      meta: { guest: true }
    },
    {
      path: '/reset-password',
      name: 'reset-password',
      component: ResetPasswordView,
      beforeEnter: (to) => {
        if (!to.query.email || !sessionStorage.getItem('canReset')) {
          return '/forgot-password'
        }
      }
    },
    {
      path: '/verify-code',
      name: 'verify-code',
      component: VerifyCodeView,
      meta: { guest: true },
      beforeEnter: (to) => {
        if (!to.query.email || !sessionStorage.getItem('canVerify')) {
          return '/register'
        }
      }
    },
    {
      path: '/dashboard',
      name: 'dashboard',
      component: DashboardView,
      meta: { auth: true }
    },
  ],
})

router.beforeEach((to, from) => {
  const token = localStorage.getItem('token')

  if (to.meta.auth && !token) {
    return '/login'
  } else if (to.meta.guest && token) {
    return '/dashboard'
  }
})

export default router
