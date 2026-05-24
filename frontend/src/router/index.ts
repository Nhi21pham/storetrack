import { createRouter, createWebHistory } from 'vue-router'
import LoginView from '@/views/LoginView.vue'
import RegisterView from '@/views/RegisterView.vue'
import ForgotPasswordView from '@/views/ForgotPasswordView.vue'
import VerifyCodeView from '@/views/VerifyCodeView.vue'
import ResetPasswordView from '@/views/ResetPasswordView.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import DashboardPage from '@/features/dashboard/pages/DashboardPage.vue'
import BusinessPage from '@/features/business/pages/BusinessPage.vue'
import StorePage from '@/features/stores/pages/StorePage.vue'
import ErrorView from '@/views/ErrorView.vue'
import AcceptInvitationView from '@/views/AcceptInvitationView.vue'
import UserManagementPage from '@/features/users/pages/UserManagementPage.vue'
import AuditLogPage from '@/features/audit/pages/AuditLogPage.vue'
import SupplierPage from '@/features/suppliers/pages/SupplierPage.vue'
import CustomerPage from '@/features/customers/pages/CustomerPage.vue'
import BanksPage from '@/features/banking/pages/BanksPage.vue'
import BankAccountsPage from '@/features/banking/pages/BankAccountsPage.vue'

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
          component: DashboardPage
        },
        {
          path: 'business',
          name: 'business',
          component: BusinessPage
        },
        {
          path: 'stores',
          name: 'stores',
          component: StorePage
        },
        {
          path: 'users',
          name: 'users',
          component: UserManagementPage
        },
        {
          path: 'audit-log',
          name: 'audit-log',
          component: AuditLogPage
        },
        {
          path: 'suppliers',
          name: 'suppliers',
          component: SupplierPage
        },
        {
          path: 'customers',
          name: 'customers',
          component: CustomerPage
        },
        {
          path: 'banking/banks',
          name: 'banking-banks',
          component: BanksPage
        },
        {
          path: 'banking/bank-accounts',
          name: 'banking-bank-accounts',
          component: BankAccountsPage
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