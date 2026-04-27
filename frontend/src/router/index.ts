import { createRouter, createWebHistory } from 'vue-router'
import LoginView from '@/views/LoginView.vue'
import RegisterView from '@/views/RegisterView.vue'
import ForgotPasswordView from '@/views/ForgotPasswordView.vue'
import VerifyCodeView from '@/views/VerifyCodeView.vue'
import ResetPasswordView from '@/views/ResetPasswordView.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import DashboardView from '@/views/DashboardView.vue'
import BusinessView from '@/views/BusinessView.vue'
import StoreView from '@/views/StoreView.vue'
import ErrorView from '@/views/ErrorView.vue'
import AcceptInvitationView from '@/views/AcceptInvitationView.vue'
import UserManagementView from '@/views/UserManagementView.vue'
import AuditLogView from '@/views/AuditLogView.vue'
import SupplierView from '@/views/SupplierView.vue'
import CustomerView from '@/views/CustomerView.vue'

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
        {
          path: 'stores',
          name: 'stores',
          component: StoreView
        },
        {
          path: 'users',
          name: 'users',
          component: UserManagementView
        },
        {
          path: 'audit-log',
          name: 'audit-log',
          component: AuditLogView
        },
        {
          path: 'suppliers',
          name: 'suppliers',
          component: SupplierView
        },
        {
          path: 'customers',
          name: 'customers',
          component: CustomerView
        },
      ]
    },
    // Public invite route — no auth meta, view handles its own auth state
    {
      path: '/invite/:token',
      name: 'accept-invitation',
      component: AcceptInvitationView,
    },

    {
      path: '/error/:code',
      name: 'error',
      component: ErrorView
    },
    {
      path: '/:pathMatch(.*)*',
      name: 'not-found',
      component: ErrorView
    }
  ],
})

router.beforeEach((to) => {
  const token = localStorage.getItem('token')

  if (to.meta.auth && !token) {
    return '/login'
  } else if (to.meta.guest && token) {
    const redirect = to.query.redirect as string | undefined
    return redirect || '/dashboard'
  }
})

export default router