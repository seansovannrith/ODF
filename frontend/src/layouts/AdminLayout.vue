<template>
  <div class="odf-shell" :class="{ collapsed }">
    <!-- Sidebar (AdminLTE style) -->
    <aside class="odf-sidebar">
      <RouterLink to="/dashboard" class="brand">
        <img :src="lookups.branding.logo_url || appLogo" :alt="lookups.branding.app_name || 'ODF'" class="brand-logo" />
        <span class="brand-text">{{ lookups.branding.app_name || 'ODF' }}</span>
      </RouterLink>

      <div class="user-panel">
        <AuthImage v-if="auth.user?.photo_url" :key="auth.user.photo_url" :url="auth.user.photo_url" class="avatar avatar-photo" :alt="ln(auth.user)" />
        <span v-else class="avatar"><i class="bi bi-person-fill"></i></span>
        <span class="name">{{ ln(auth.user) }}</span>
      </div>

      <nav class="menu">
        <RouterLink to="/dashboard"><i class="bi bi-speedometer2"></i><span>{{ t('menu.dashboard') }}</span></RouterLink>
        <template v-if="auth.can('document_access')">
          <RouterLink :to="{ name: 'documents' }" active-class="" exact-active-class="" :class="{ active: route.path.startsWith('/documents') && route.query.tab !== 'inbox' }">
            <i class="bi bi-file-earmark-text"></i><span>{{ t('menu.documents') }}</span>
          </RouterLink>
          <RouterLink :to="{ name: 'documents', query: { tab: 'inbox' } }" active-class="" exact-active-class="" :class="{ active: route.query.tab === 'inbox' }">
            <i class="bi bi-inbox"></i><span>{{ t('menu.inbox') }}</span>
            <span v-if="dash.my_turn" class="badge bg-danger ms-auto">{{ dash.my_turn }}</span>
          </RouterLink>
        </template>
        <RouterLink v-if="auth.can('comment_access')" to="/comments"><i class="bi bi-chat-left-text"></i><span>{{ t('menu.comments') }}</span></RouterLink>

        <template v-if="auth.can('user_management_access')">
          <a href="#" class="group" :class="{ open: open.users }" @click.prevent="open.users = !open.users">
            <i class="bi bi-people-fill"></i><span>{{ t('menu.userManagement') }}</span>
            <i class="bi caret" :class="open.users ? 'bi-chevron-down' : 'bi-chevron-left'"></i>
          </a>
          <div v-show="open.users" class="submenu">
            <template v-for="m in userMenu" :key="m.to">
              <RouterLink v-if="auth.can(m.perm)" :to="m.to"><i class="bi" :class="m.icon"></i><span>{{ t(m.label) }}</span></RouterLink>
            </template>
          </div>
        </template>

        <template v-if="auth.can('setting_access')">
          <a href="#" class="group" :class="{ open: open.settings }" @click.prevent="open.settings = !open.settings">
            <i class="bi bi-gear-wide-connected"></i><span>{{ t('menu.settings') }}</span>
            <i class="bi caret" :class="open.settings ? 'bi-chevron-down' : 'bi-chevron-left'"></i>
          </a>
          <div v-show="open.settings" class="submenu">
            <template v-for="s in settingsMenu" :key="s.key">
              <RouterLink v-if="auth.can(s.perm)" :to="`/settings/${s.key}`"><i class="bi" :class="s.icon"></i><span>{{ t(s.label) }}</span></RouterLink>
            </template>
          </div>
        </template>

        <RouterLink v-if="auth.can('user_alert_access')" to="/user-alerts"><i class="bi bi-bell"></i><span>{{ t('menu.userAlerts') }}</span></RouterLink>
        <RouterLink to="/profile/password"><i class="bi bi-key-fill"></i><span>{{ t('menu.changePassword') }}</span></RouterLink>
        <a href="#" @click.prevent="logout"><i class="bi bi-box-arrow-right"></i><span>{{ t('menu.logout') }}</span></a>
      </nav>
    </aside>

    <!-- Main -->
    <div class="odf-main">
      <header class="odf-navbar">
        <button class="btn btn-link text-secondary fs-4 p-0" @click="collapsed = !collapsed"><i class="bi bi-list"></i></button>
        <div class="ms-auto d-flex align-items-center gap-3">
          <LanguageSwitch />
          <strong class="d-none d-md-inline">{{ ln(auth.user) }}</strong>

          <div class="position-relative">
            <button class="btn btn-link text-secondary p-0 position-relative" @click="toggleBell">
              <i class="bi bi-bell fs-5"></i>
              <span v-if="alerts.unread" class="badge rounded-pill bg-danger bell-badge">{{ alerts.unread }}</span>
            </button>
            <div v-if="bellOpen" class="bell-menu shadow">
              <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                <strong>{{ t('alert.plural') }}</strong>
                <a v-if="alerts.unread" href="#" class="small" @click.prevent="readAll">{{ t('alert.markAll') }}</a>
              </div>
              <div v-if="!alerts.items.length" class="p-3 text-muted small">{{ t('alert.none') }}</div>
              <a
                v-for="a in alerts.items" :key="a.id" href="#" class="bell-item" :class="{ unread: !a.read_at }"
                @click.prevent="openAlert(a)"
              >
                <div>{{ alertText(a.alert_text) }}</div>
                <small class="text-muted">{{ formatDate(a.created_at) }}</small>
              </a>
            </div>
          </div>
        </div>
      </header>

      <main class="odf-content" @click="bellOpen = false">
        <RouterView :key="route.fullPath" />
      </main>

      <footer class="odf-footer">
        <span>{{ lookups.branding.app_name }} © {{ t('app.rights') }}</span>
        <strong>{{ t('app.version') }} 1.0</strong>
      </footer>
    </div>
  </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import http from '@/api/http'
import { useAuth } from '@/stores/auth'
import { useLookups } from '@/stores/lookups'
import LanguageSwitch from '@/components/LanguageSwitch.vue'
import AuthImage from '@/components/AuthImage.vue'
import { ln, formatDate } from '@/utils/helpers'
import appLogo from '@/assets/odf-logo.png'

const { t, locale } = useI18n()
const auth = useAuth()
const lookups = useLookups()
const route = useRoute()
const router = useRouter()

const collapsed = ref(window.innerWidth < 900)
const userPaths = ['/permissions', '/roles', '/users', '/user-infos', '/audit-logs', '/user-logs']
const open = reactive({ users: userPaths.includes(route.path), settings: route.path.startsWith('/settings') })
const alerts = reactive({ unread: 0, items: [] })
const dash = reactive({ my_turn: 0 })
const bellOpen = ref(false)

const userMenu = [
  { to: '/permissions', label: 'menu.permissions', icon: 'bi-unlock-fill', perm: 'permission_access' },
  { to: '/roles', label: 'menu.roles', icon: 'bi-briefcase-fill', perm: 'role_access' },
  { to: '/users', label: 'menu.users', icon: 'bi-person-fill', perm: 'user_access' },
  { to: '/user-infos', label: 'menu.userInfos', icon: 'bi-person-vcard-fill', perm: 'user_info_access' },
  { to: '/audit-logs', label: 'menu.auditLogs', icon: 'bi-file-earmark-text-fill', perm: 'audit_log_access' },
  { to: '/user-logs', label: 'menu.userLogs', icon: 'bi-file-earmark-fill', perm: 'user_log_access' },
]
const settingsMenu = [
  { key: 'departments', label: 'menu.departments', icon: 'bi-building', perm: 'department_access' },
  { key: 'titles', label: 'menu.titles', icon: 'bi-person-vcard', perm: 'title_access' },
  { key: 'positions', label: 'menu.positions', icon: 'bi-person-lines-fill', perm: 'position_access' },
  { key: 'categories', label: 'menu.categories', icon: 'bi-diagram-2', perm: 'category_access' },
  { key: 'organisations', label: 'menu.organisations', icon: 'bi-buildings', perm: 'organisation_access' },
  { key: 'document-types', label: 'menu.documentTypes', icon: 'bi-file-earmark-ruled', perm: 'document_type_access' },
  { key: 'staff', label: 'menu.staff', icon: 'bi-person-workspace', perm: 'staff_access' },
  { key: 'app-settings', label: 'menu.appSettings', icon: 'bi-sliders', perm: 'app_setting_access' },
]

/** Alerts created by the flow are stored as "ខ្មែរ | English" */
function alertText(text) {
  const parts = String(text).split(' | ')
  return parts.length === 2 ? (locale.value === 'km' ? parts[0] : parts[1]) : text
}

async function loadAlerts() {
  try {
    const [{ data: a }, { data: d }] = await Promise.all([http.get('/alerts/mine'), http.get('/dashboard')])
    Object.assign(alerts, a)
    dash.my_turn = d.my_turn
  } catch (_) { /* ignore polling errors */ }
}
function toggleBell() { bellOpen.value = !bellOpen.value; if (bellOpen.value) loadAlerts() }
async function openAlert(a) {
  bellOpen.value = false
  if (!a.read_at) await http.post(`/alerts/${a.id}/read`)
  loadAlerts()
  if (a.alert_link) router.push(a.alert_link)
}
async function readAll() { await http.post('/alerts/read-all'); loadAlerts() }
async function logout() { await auth.logout(); router.push('/login') }

let poll
onMounted(() => {
  lookups.loadBranding()
  lookups.load()
  loadAlerts()
  poll = setInterval(loadAlerts, 60_000)
})
onBeforeUnmount(() => clearInterval(poll))
</script>
