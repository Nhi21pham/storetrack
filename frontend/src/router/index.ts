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
import ScanPurchaseInvoicePage from '@/features/invoices/pages/ScanPurchaseInvoicePage.vue'
import ScanSaleInvoicePage from '@/features/invoices/pages/ScanSaleInvoicePage.vue'
import SaleInvoicesPage from '@/features/invoices/pages/SaleInvoicesPage.vue'
import SaleInvoiceCreatePage from '@/features/invoices/pages/SaleInvoiceCreatePage.vue'
import StockReportPage from '@/features/reports/pages/StockReportPage.vue'
import SaleReportPage from '@/features/reports/pages/SaleReportPage.vue'
import ProfitReportPage from '@/features/reports/pages/ProfitReportPage.vue'
import ReceivablesReportPage from '@/features/reports/pages/ReceivablesReportPage.vue'
import PayablesReportPage from '@/features/reports/pages/PayablesReportPage.vue'
import TopProductsReportPage from '@/features/reports/pages/TopProductsReportPage.vue'
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
              { labelKey: 'nav.dashboard', to: '/dashboard', icon: 'home' },
              { labelKey: 'nav.business' },
            ],
          },
        },
        {
          path: 'stores',
          name: 'stores',
          component: StorePage,
          meta: {
            breadcrumb: [
              { labelKey: 'nav.dashboard', to: '/dashboard', icon: 'home' },
              { labelKey: 'nav.stores' },
            ],
          },
        },
        {
          path: 'users',
          name: 'users',
          component: UserManagementPage,
          meta: {
            breadcrumb: [
              { labelKey: 'nav.dashboard', to: '/dashboard', icon: 'home' },
              { labelKey: 'nav.users' },
            ],
          },
        },
        {
          path: 'audit-log',
          name: 'audit-log',
          component: AuditLogPage,
          meta: {
            breadcrumb: [
              { labelKey: 'nav.dashboard', to: '/dashboard', icon: 'home' },
              { labelKey: 'nav.auditLog' },
            ],
          },
        },
        {
          path: 'suppliers',
          name: 'suppliers',
          component: SupplierPage,
          meta: {
            breadcrumb: [
              { labelKey: 'nav.dashboard', to: '/dashboard', icon: 'home' },
              { labelKey: 'nav.suppliers' },
            ],
          },
        },
        {
          path: 'purchase-invoices',
          name: 'purchase-invoices',
          component: PurchaseInvoicesPage,
          meta: {
            breadcrumb: [
              { labelKey: 'nav.dashboard', to: '/dashboard', icon: 'home' },
              { labelKey: 'nav.purchaseInvoices' },
            ],
          },
        },
        {
          path: 'purchase-invoices/scan',
          name: 'purchase-invoice-scan',
          component: ScanPurchaseInvoicePage,
          meta: {
            breadcrumb: [
              { labelKey: 'nav.dashboard', to: '/dashboard', icon: 'home' },
              { labelKey: 'nav.purchaseInvoices', to: '/purchase-invoices' },
              { labelKey: 'nav.scan' },
            ],
          },
        },
        {
          path: 'purchase-invoices/new',
          name: 'purchase-invoice-create',
          component: PurchaseInvoiceCreatePage,
          meta: {
            breadcrumb: [
              { labelKey: 'nav.dashboard', to: '/dashboard', icon: 'home' },
              { labelKey: 'nav.purchaseInvoices', to: '/purchase-invoices' },
              { labelKey: 'nav.new' },
            ],
          },
        },
        {
          path: 'purchase-invoices/:id/edit',
          name: 'purchase-invoice-edit',
          component: PurchaseInvoiceCreatePage,
          meta: {
            breadcrumb: [
              { labelKey: 'nav.dashboard', to: '/dashboard', icon: 'home' },
              { labelKey: 'nav.purchaseInvoices', to: '/purchase-invoices' },
              { labelKey: 'nav.edit' },
            ],
          },
        },
        {
          path: 'sale-invoices',
          name: 'sale-invoices',
          component: SaleInvoicesPage,
          meta: {
            breadcrumb: [
              { labelKey: 'nav.dashboard', to: '/dashboard', icon: 'home' },
              { labelKey: 'nav.saleInvoices' },
            ],
          },
        },
        {
          path: 'sale-invoices/scan',
          name: 'sale-invoice-scan',
          component: ScanSaleInvoicePage,
          meta: {
            breadcrumb: [
              { labelKey: 'nav.dashboard', to: '/dashboard', icon: 'home' },
              { labelKey: 'nav.saleInvoices', to: '/sale-invoices' },
              { labelKey: 'nav.scan' },
            ],
          },
        },
        {
          path: 'sale-invoices/new',
          name: 'sale-invoice-create',
          component: SaleInvoiceCreatePage,
          meta: {
            breadcrumb: [
              { labelKey: 'nav.dashboard', to: '/dashboard', icon: 'home' },
              { labelKey: 'nav.saleInvoices', to: '/sale-invoices' },
              { labelKey: 'nav.new' },
            ],
          },
        },
        {
          path: 'sale-invoices/:id/edit',
          name: 'sale-invoice-edit',
          component: SaleInvoiceCreatePage,
          meta: {
            breadcrumb: [
              { labelKey: 'nav.dashboard', to: '/dashboard', icon: 'home' },
              { labelKey: 'nav.saleInvoices', to: '/sale-invoices' },
              { labelKey: 'nav.edit' },
            ],
          },
        },
        {
          path: 'reports/stock',
          name: 'stock-report',
          component: StockReportPage,
          meta: {
            breadcrumb: [
              { labelKey: 'nav.dashboard', to: '/dashboard', icon: 'home' },
              { labelKey: 'nav.reports' },
              { labelKey: 'nav.stockReport' },
            ],
          },
        },
        {
          path: 'reports/sales',
          name: 'sale-report',
          component: SaleReportPage,
          meta: {
            breadcrumb: [
              { labelKey: 'nav.dashboard', to: '/dashboard', icon: 'home' },
              { labelKey: 'nav.reports' },
              { labelKey: 'nav.saleReport' },
            ],
          },
        },
        {
          path: 'reports/profit',
          name: 'profit-report',
          component: ProfitReportPage,
          meta: {
            breadcrumb: [
              { labelKey: 'nav.dashboard', to: '/dashboard', icon: 'home' },
              { labelKey: 'nav.reports' },
              { labelKey: 'nav.profitReport' },
            ],
          },
        },
        {
          path: 'reports/top-products',
          name: 'top-products-report',
          component: TopProductsReportPage,
          meta: {
            breadcrumb: [
              { labelKey: 'nav.dashboard', to: '/dashboard', icon: 'home' },
              { labelKey: 'nav.reports' },
              { labelKey: 'nav.topProducts' },
            ],
          },
        },
        {
          path: 'reports/receivables',
          name: 'receivables-report',
          component: ReceivablesReportPage,
          meta: {
            breadcrumb: [
              { labelKey: 'nav.dashboard', to: '/dashboard', icon: 'home' },
              { labelKey: 'nav.reports' },
              { labelKey: 'nav.debtReport' },
              { labelKey: 'nav.customerDebt' },
            ],
          },
        },
        {
          path: 'reports/payables',
          name: 'payables-report',
          component: PayablesReportPage,
          meta: {
            breadcrumb: [
              { labelKey: 'nav.dashboard', to: '/dashboard', icon: 'home' },
              { labelKey: 'nav.reports' },
              { labelKey: 'nav.debtReport' },
              { labelKey: 'nav.supplierDebt' },
            ],
          },
        },
        {
          path: 'customers',
          name: 'customers',
          component: CustomerPage,
          meta: {
            breadcrumb: [
              { labelKey: 'nav.dashboard', to: '/dashboard', icon: 'home' },
              { labelKey: 'nav.customers' },
            ],
          },
        },
        {
          path: 'payments',
          name: 'payments',
          component: PaymentsPage,
          meta: {
            breadcrumb: [
              { labelKey: 'nav.dashboard', to: '/dashboard', icon: 'home' },
              { labelKey: 'nav.payments' },
            ],
          },
        },
        {
          path: 'banking/banks',
          name: 'banking-banks',
          component: BanksPage,
          meta: {
            breadcrumb: [
              { labelKey: 'nav.dashboard', to: '/dashboard', icon: 'home' },
              { labelKey: 'nav.others' },
              { labelKey: 'nav.banking', icon: 'bank' },
              { labelKey: 'nav.banks' },
            ],
          },
        },
        {
          path: 'banking/bank-accounts',
          name: 'banking-bank-accounts',
          component: BankAccountsPage,
          meta: {
            breadcrumb: [
              { labelKey: 'nav.dashboard', to: '/dashboard', icon: 'home' },
              { labelKey: 'nav.others' },
              { labelKey: 'nav.banking', icon: 'bank' },
              { labelKey: 'nav.bankAccounts' },
            ],
          },
        },
        {
          path: 'units',
          name: 'units',
          component: UnitsPage,
          meta: {
            breadcrumb: [
              { labelKey: 'nav.dashboard', to: '/dashboard', icon: 'home' },
              { labelKey: 'nav.others' },
              { labelKey: 'nav.units', icon: 'unit' },
            ],
          },
        },
        {
          path: 'products',
          name: 'products',
          component: ProductsPage,
          meta: {
            breadcrumb: [
              { labelKey: 'nav.dashboard', to: '/dashboard', icon: 'home' },
              { labelKey: 'nav.products' },
            ],
          },
        },
        {
          path: 'product-categories',
          name: 'product-categories',
          component: ProductCategoriesPage,
          meta: {
            breadcrumb: [
              { labelKey: 'nav.dashboard', to: '/dashboard', icon: 'home' },
              { labelKey: 'nav.others' },
              { labelKey: 'nav.productCategories' },
            ],
          },
        },
        {
          path: 'tags',
          name: 'tags',
          component: TagsPage,
          meta: {
            breadcrumb: [
              { labelKey: 'nav.dashboard', to: '/dashboard', icon: 'home' },
              { labelKey: 'nav.others' },
              { labelKey: 'nav.tags' },
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