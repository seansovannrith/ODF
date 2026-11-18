import { createRouter, createWebHistory } from 'vue-router'
import { useAuth } from '@/stores/auth'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { settingsResources } from '@/views/settings/resources'

const CrudPage = () => import('@/views/settings/CrudPage.vue')

const settingsRoutes = Object.entries(settingsResources).map(([key, res]) => ({
  path: `settings/${key}`,
  name: `settings-${key}`,
  component: CrudPage,
  props: { resourceKey: key },
  meta: { permission: `${res.perm}_access` },
}))

const routes = [
  { path: '/login', name: 'login', component: () => import('@/views/LoginView.vue'), meta: { guest: true } },
  {
    path: '/documents/:id/print',
    name: 'document-print',
    component: () => import('@/views/documents/DocumentPrint.vue'),
    props: true,
    meta: { permission: 'document_print_access' },
  },
  {
    path: '/',
    component: AdminLayout,
    children: [
      { path: '', redirect: '/dashboard' },
      { path: 'dashboard', name: 'dashboard', component: () => import('@/views/DashboardView.vue') },

      { path: 'documents', name: 'documents', component: () => import('@/views/documents/DocumentList.vue'), meta: { permission: 'document_access' } },
      {
        path: 'documents/create', name: 'document-create',
        component: () => import('@/views/documents/DocumentForm.vue'), meta: { permission: 'document_create' },
      },
      {
        path: 'documents/:id/edit', name: 'document-edit', props: true,
        component: () => import('@/views/documents/DocumentForm.vue'), meta: { permission: 'document_edit' },
      },
      { path: 'documents/:id', name: 'document-show', props: true, component: () => import('@/views/documents/DocumentShow.vue'), meta: { permission: 'document_show' } },

      { path: 'comments', name: 'comments', component: () => import('@/views/comments/CommentList.vue'), meta: { permission: 'comment_access' } },
      { path: 'comments/:id', name: 'comment-show', props: true, component: () => import('@/views/comments/CommentShow.vue'), meta: { permission: 'comment_show' } },

      // User management
      { path: 'permissions', name: 'permissions', component: CrudPage, props: { resourceKey: 'permissions' }, meta: { permission: 'permission_access' } },
      { path: 'roles', name: 'roles', component: CrudPage, props: { resourceKey: 'roles' }, meta: { permission: 'role_access' } },
      { path: 'users', name: 'users', component: CrudPage, props: { resourceKey: 'users' }, meta: { permission: 'user_access' } },
      { path: 'user-infos', name: 'user-infos', component: CrudPage, props: { resourceKey: 'user-infos' }, meta: { permission: 'user_info_access' } },
      { path: 'audit-logs', name: 'audit-logs', component: () => import('@/views/logs/AuditLogList.vue'), meta: { permission: 'audit_log_access' } },
      { path: 'user-logs', name: 'user-logs', component: () => import('@/views/logs/UserLogList.vue'), meta: { permission: 'user_log_access' } },
      { path: 'user-alerts', name: 'user-alerts', component: () => import('@/views/AlertList.vue'), meta: { permission: 'user_alert_access' } },
      ...settingsRoutes,

      { path: 'profile/password', name: 'profile', component: () => import('@/views/ProfileView.vue') },
    ],
  },
  { path: '/:pathMatch(.*)*', redirect: '/dashboard' },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  linkActiveClass: 'active',
})

router.beforeEach(async (to) => {
  const auth = useAuth()
  if (to.meta.guest) return auth.isLoggedIn ? '/dashboard' : true
  if (!auth.isLoggedIn) return { name: 'login', query: { redirect: to.fullPath } }
  if (!auth.user) {
    try { await auth.fetchMe() } catch (_) { return { name: 'login' } }
  }
  if (to.meta.permission && !auth.can(to.meta.permission)) return '/dashboard'
  return true
})

export default router
