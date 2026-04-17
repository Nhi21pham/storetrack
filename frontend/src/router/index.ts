import { createRouter, createWebHistory } from 'vue-router'
import LoginView from '@/views/LoginView.vue'
import RegisterView from '@/views/RegisterView.vue'
import ForgotPasswordView from '@/views/ForgotPasswordView.vue'
import VerifyCodeView from '@/views/VerifyCodeView.vue'
import ResetPasswordView from '@/views/ResetPasswordView.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import DashboardView from '@/views/DashboardView.vue'
import BusinessView from '@/views/BusinessView.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    // Guest routes (no layout wrapper)
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

    // Authenticated routes (wrapped in AppLayout)
    {
      path: '/',
      component: AppLayout,
      meta: { auth: true },
      children: [
        {
          path: '',
          redirect: '/dashboard'
        },
        {
          path: 'dashboard',
          name: 'dashboard',
          component: DashboardView
        },
        {
          path: 'business',
          name: 'business',
          component: BusinessView
        },
        // Future pages — all get sidebar + navbar + modals for free
        // {
        //   path: 'stores',
        //   name: 'stores',
        //   component: () => import('@/views/StoreView.vue')
        // },
      ]
    }
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