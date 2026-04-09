import { createRouter, createWebHistory } from 'vue-router'
import RegisterView from '@/views/RegisterView.vue'
import VerifyCodeView from '@/views/VerifyCodeView.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/register',
      name: 'register',
      component: RegisterView
    },
    { path: '/verify-code', name: 'verify-code', component: VerifyCodeView }
  ],
})

export default router
