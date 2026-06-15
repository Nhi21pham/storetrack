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
import UnitsPage from '@/features/units/pages/UnitsPage.vue'
import ProductsPage from '@/features/products/pages/ProductsPage.vue'
import ProductCategoriesPage from '@/features/productCategories/pages/ProductCategoriesPage.vue'
import TagsPage from '@/features/tags/pages/TagsPage.vue'
import PurchaseInvoicesPage from '@/features/invoices/pages/PurchaseInvoicesPage.vue'
import PurchaseInvoiceCreatePage from '@/features/invoices/pages/PurchaseInvoiceCreatePage.vue'
import SaleInvoicesPage from '@/features/invoices/pages/SaleInvoicesPage.vue'
import SaleInvoiceCreatePage from '@/features/invoices/pages/SaleInvoiceCreatePage.vue'
import StockReportPage from '@/features/reports/pages/StockReportPage.vue'
import SaleReportPage from '@/features/reports/pages/SaleReportPage.vue'
import ProfitReportPage from '@/features/reports/pages/ProfitReportPage.vue'
import ReceivablesReportPage from '@/features/reports/pages/ReceivablesReportPage.vue'
import PayablesReportPage from '@/features/reports/pages/PayablesReportPage.vue'
import PaymentsPage from '@/features/payments/pages/PaymentsPage.vue'

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
          component: DashboardPage,
        },
        {
          path: 'business',
          name: 'business',
          component: BusinessPage,
          meta: {
            breadcrumb: [
              { label: 'Dashboard', to: '/dashboard', icon: 'home' },
              { label: 'Business' },
            ],
          },
        },
        {
          path: 'stores',
          name: 'stores',
          component: StorePage,
          meta: {
            breadcrumb: [
              { label: 'Dashboard', to: '/dashboard', icon: 'home' },
              { label: 'Stores' },
            ],
          },
        },
        {
          path: 'users',
          name: 'users',
          component: UserManagementPage,
          meta: {
            breadcrumb: [
              { label: 'Dashboard', to: '/dashboard', icon: 'home' },
              { label: 'Users' },
            ],
          },
        },
        {
          path: 'audit-log',
          name: 'audit-log',
          component: AuditLogPage,
          meta: {
            breadcrumb: [
              { label: 'Dashboard', to: '/dashboard', icon: 'home' },
              { label: 'Audit Log' },
            ],
          },
        },
        {
          path: 'suppliers',
          name: 'suppliers',
          component: SupplierPage,
          meta: {
            breadcrumb: [
              { label: 'Dashboard', to: '/dashboard', icon: 'home' },
              { label: 'Suppliers' },
            ],
          },
        },
        {
          path: 'purchase-invoices',
          name: 'purchase-invoices',
          component: PurchaseInvoicesPage,
          meta: {
            breadcrumb: [
              { label: 'Dashboard', to: '/dashboard', icon: 'home' },
              { label: 'Purchase Invoices' },
            ],
          },
        },
        {
          path: 'purchase-invoices/new',
          name: 'purchase-invoice-create',
          component: PurchaseInvoiceCreatePage,
          meta: {
            breadcrumb: [
              { label: 'Dashboard', to: '/dashboard', icon: 'home' },
              { label: 'Purchase Invoices', to: '/purchase-invoices' },
              { label: 'New' },
            ],
          },
        },
        {
          path: 'purchase-invoices/:id/edit',
          name: 'purchase-invoice-edit',
          component: PurchaseInvoiceCreatePage,
          meta: {
            breadcrumb: [
              { label: 'Dashboard', to: '/dashboard', icon: 'home' },
              { label: 'Purchase Invoices', to: '/purchase-invoices' },
              { label: 'Edit' },
            ],
          },
        },
        {
          path: 'sale-invoices',
          name: 'sale-invoices',
          component: SaleInvoicesPage,
          meta: {
            breadcrumb: [
              { label: 'Dashboard', to: '/dashboard', icon: 'home' },
              { label: 'Sale Invoices' },
            ],
          },
        },
        {
          path: 'sale-invoices/new',
          name: 'sale-invoice-create',
          component: SaleInvoiceCreatePage,
          meta: {
            breadcrumb: [
              { label: 'Dashboard', to: '/dashboard', icon: 'home' },
              { label: 'Sale Invoices', to: '/sale-invoices' },
              { label: 'New' },
            ],
          },
        },
        {
          path: 'sale-invoices/:id/edit',
          name: 'sale-invoice-edit',
          component: SaleInvoiceCreatePage,
          meta: {
            breadcrumb: [
              { label: 'Dashboard', to: '/dashboard', icon: 'home' },
              { label: 'Sale Invoices', to: '/sale-invoices' },
              { label: 'Edit' },
            ],
          },
        },
        {
          path: 'reports/stock',
          name: 'stock-report',
          component: StockReportPage,
          meta: {
            breadcrumb: [
              { label: 'Dashboard', to: '/dashboard', icon: 'home' },
              { label: 'Reports' },
              { label: 'Stock Report' },
            ],
          },
        },
        {
          path: 'reports/sales',
          name: 'sale-report',
          component: SaleReportPage,
          meta: {
            breadcrumb: [
              { label: 'Dashboard', to: '/dashboard', icon: 'home' },
              { label: 'Reports' },
              { label: 'Sale Report' },
            ],
          },
        },
        {
          path: 'reports/profit',
          name: 'profit-report',
          component: ProfitReportPage,
          meta: {
            breadcrumb: [
              { label: 'Dashboard', to: '/dashboard', icon: 'home' },
              { label: 'Reports' },
              { label: 'Profit Report' },
            ],
          },
        },
        {
          path: 'reports/receivables',
          name: 'receivables-report',
          component: ReceivablesReportPage,
          meta: {
            breadcrumb: [
              { label: 'Dashboard', to: '/dashboard', icon: 'home' },
              { label: 'Reports' },
              { label: 'Debt Report' },
              { label: 'Customer Debt' },
            ],
          },
        },
        {
          path: 'reports/payables',
          name: 'payables-report',
          component: PayablesReportPage,
          meta: {
            breadcrumb: [
              { label: 'Dashboard', to: '/dashboard', icon: 'home' },
              { label: 'Reports' },
              { label: 'Debt Report' },
              { label: 'Supplier Debt' },
            ],
          },
        },
        {
          path: 'customers',
          name: 'customers',
          component: CustomerPage,
          meta: {
            breadcrumb: [
              { label: 'Dashboard', to: '/dashboard', icon: 'home' },
              { label: 'Customers' },
            ],
          },
        },
        {
          path: 'payments',
          name: 'payments',
          component: PaymentsPage,
          meta: {
            breadcrumb: [
              { label: 'Dashboard', to: '/dashboard', icon: 'home' },
              { label: 'Payments' },
            ],
          },
        },
        {
          path: 'banking/banks',
          name: 'banking-banks',
          component: BanksPage,
          meta: {
            breadcrumb: [
              { label: 'Dashboard', to: '/dashboard', icon: 'home' },
              { label: 'Others' },
              { label: 'Banking', icon: 'bank' },
              { label: 'Banks' },
            ],
          },
        },
        {
          path: 'banking/bank-accounts',
          name: 'banking-bank-accounts',
          component: BankAccountsPage,
          meta: {
            breadcrumb: [
              { label: 'Dashboard', to: '/dashboard', icon: 'home' },
              { label: 'Others' },
              { label: 'Banking', icon: 'bank' },
              { label: 'Bank Accounts' },
            ],
          },
        },
        {
          path: 'units',
          name: 'units',
          component: UnitsPage,
          meta: {
            breadcrumb: [
              { label: 'Dashboard', to: '/dashboard', icon: 'home' },
              { label: 'Others' },
              { label: 'Units', icon: 'unit' },
            ],
          },
        },
        {
          path: 'products',
          name: 'products',
          component: ProductsPage,
          meta: {
            breadcrumb: [
              { label: 'Dashboard', to: '/dashboard', icon: 'home' },
              { label: 'Products' },
            ],
          },
        },
        {
          path: 'product-categories',
          name: 'product-categories',
          component: ProductCategoriesPage,
          meta: {
            breadcrumb: [
              { label: 'Dashboard', to: '/dashboard', icon: 'home' },
              { label: 'Others' },
              { label: 'Product Categories' },
            ],
          },
        },
        {
          path: 'tags',
          name: 'tags',
          component: TagsPage,
          meta: {
            breadcrumb: [
              { label: 'Dashboard', to: '/dashboard', icon: 'home' },
              { label: 'Others' },
              { label: 'Tags' },
            ],
          },
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