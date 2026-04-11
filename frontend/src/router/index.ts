import { createRouter, createWebHistory } from 'vue-router'
import RegisterView from '@/views/RegisterView.vue'
import VerifyCodeView from '@/views/VerifyCodeView.vue'
import LoginView from '@/views/LoginView.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/register',
      name: 'register',
      component: RegisterView
    },
    {
      path: '/verify-code',
      name: 'verify-code',
      component: VerifyCodeView
    },
    {
      path: '/login',
      name: 'login',
      component: LoginView
    }
  ],
})

export default router
