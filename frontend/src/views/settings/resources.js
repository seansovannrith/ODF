/**
 * Configuration for every simple CRUD screen (Settings + User management).
 *   perm     permission prefix: <perm>_access / _create / _edit / _show / _delete
 *   columns  what the table shows. types: boolean | badges | datetime | date | role ; format(row) for custom text
 *   fields   what the add/edit form shows. types: text | textarea | number | checkbox | select |
 *            multiselect | checklist (grouped checkboxes) | date | file | password | email
 *            select-type fields use `lookup` (key in the lookups store) or `options` [{value, label}]
 *            fromRow(row) picks the value from the row when editing (for nested data)
 */
import { ln, formatDate } from '@/utils/helpers'

const nameCols = [
  { key: 'id', label: 'common.id', sortable: true, searchable: true, width: 80 },
  { key: 'name', label: 'common.name', sortable: true, searchable: true },
  { key: 'name_kh', label: 'common.nameKh', sortable: true, searchable: true },
]
const nameFields = [
  { key: 'name', label: 'common.name', required: true },
  { key: 'name_kh', label: 'common.nameKh' },
]
const descCol = { key: 'description', label: 'common.description', sortable: true, searchable: true }
const descField = { key: 'description', label: 'common.description', type: 'textarea' }
const genderOptions = [{ value: 'Male', label: 'settings.male' }, { value: 'Female', label: 'settings.female' }]

export const settingsResources = {
  departments: {
    endpoint: '/settings/departments', perm: 'department', title: 'settings.department', menu: 'menu.departments',
    columns: [...nameCols,
      { key: 'abbr', label: 'settings.abr', sortable: true, searchable: true },
      { key: 'abbr_kh', label: 'settings.abrKh', sortable: true, searchable: true }, descCol],
    fields: [...nameFields, { key: 'abbr', label: 'settings.abr' }, { key: 'abbr_kh', label: 'settings.abrKh' },
      { key: 'code', label: 'common.code' }, descField],
  },
  titles: {
    endpoint: '/settings/titles', perm: 'title', title: 'settings.title', menu: 'menu.titles',
    columns: [...nameCols,
      { key: 'abbr', label: 'settings.abr', sortable: true, searchable: true },
      { key: 'abbr_kh', label: 'settings.abrKh', sortable: true, searchable: true }, descCol],
    fields: [...nameFields, { key: 'abbr', label: 'settings.abr' }, { key: 'abbr_kh', label: 'settings.abrKh' }, descField],
  },
  positions: {
    endpoint: '/settings/positions', perm: 'position', title: 'settings.position', menu: 'menu.positions',
    columns: [...nameCols, descCol, { key: 'active', label: 'common.active', type: 'boolean' }],
    fields: [...nameFields, descField, { key: 'active', label: 'common.active', type: 'checkbox', default: true }],
  },
  categories: {
    endpoint: '/settings/categories', perm: 'category', title: 'settings.category', menu: 'menu.categories',
    columns: [...nameCols, descCol], fields: [...nameFields, descField],
  },
  organisations: {
    endpoint: '/settings/organisations', perm: 'organisation', title: 'settings.organisation', menu: 'menu.organisations',
    columns: [...nameCols, descCol], fields: [...nameFields, descField],
  },
  'document-types': {
    endpoint: '/settings/document-types', perm: 'document_type', title: 'settings.documentType', menu: 'menu.documentTypes',
    columns: [
      { key: 'id', label: 'common.id', sortable: true, width: 80 },
      { key: 'value', label: 'common.value', sortable: true, searchable: true },
      { key: 'name', label: 'common.name', sortable: true, searchable: true },
      { key: 'name_kh', label: 'common.nameKh', sortable: true, searchable: true }, descCol],
    fields: [{ key: 'value', label: 'common.value', required: true }, ...nameFields, descField],
  },
  staff: {
    endpoint: '/settings/staff', perm: 'staff', title: 'settings.staff', menu: 'menu.staff',
    columns: [
      { key: 'id', label: 'common.id', sortable: true, width: 70 },
      { key: 'staff_code', label: 'settings.staffCode', sortable: true, searchable: true },
      { key: 'name', label: 'common.name', sortable: true, searchable: true },
      { key: 'name_kh', label: 'common.nameKh', sortable: true, searchable: true },
      { key: 'gender', label: 'settings.gender', sortable: true, searchable: true, filterOptions: genderOptions },
      { key: 'title', label: 'user.title', relation: true },
      { key: 'position', label: 'user.position', relation: true },
    ],
    fields: [
      { key: 'staff_code', label: 'settings.staffCode', required: true },
      ...nameFields,
      { key: 'gender', label: 'settings.gender', type: 'select', required: true, options: genderOptions, default: 'Male' },
      { key: 'title_id', label: 'user.title', type: 'select', lookup: 'titles' },
      { key: 'position_id', label: 'user.position', type: 'select', lookup: 'positions' },
      { key: 'department_id', label: 'user.department', type: 'select', lookup: 'departments' },
      { key: 'user_id', label: 'settings.linkedUser', type: 'select', lookup: 'users' },
      { key: 'phone', label: 'user.phone' },
    ],
  },
  'app-settings': {
    endpoint: '/settings/app-settings', perm: 'app_setting', title: 'settings.appSetting', menu: 'menu.appSettings', multipart: true,
    columns: [
      { key: 'id', label: 'common.id', sortable: true, width: 70 },
      { key: 'name', label: 'common.name', sortable: true, searchable: true },
      { key: 'value', label: 'common.value', sortable: true, searchable: true },
      descCol,
      { key: 'photo', label: 'common.photo', type: 'image' },
    ],
    fields: [
      { key: 'name', label: 'common.name', required: true },
      { key: 'value', label: 'common.value' },
      descField,
      { key: 'photo', label: 'common.photo', type: 'file' },
    ],
  },
}

// ---------------- User management ----------------

export const userManagementResources = {
  permissions: {
    endpoint: '/permissions', perm: 'permission', title: 'perm.singular', menu: 'menu.permissions',
    columns: [
      { key: 'id', label: 'common.id', sortable: true, searchable: true, width: 120 },
      { key: 'title', label: 'perm.title', sortable: true, searchable: true },
    ],
    fields: [{ key: 'title', label: 'perm.title', required: true, hint: 'perm.titleHint' }],
  },

  roles: {
    endpoint: '/roles', perm: 'role', title: 'role.singular', menu: 'menu.roles',
    columns: [
      { key: 'id', label: 'common.id', sortable: true, width: 80 },
      { key: 'title', label: 'perm.title', sortable: true, searchable: true },
      { key: 'permissions', label: 'role.permissions', type: 'badges', badgeKey: 'title' },
      { key: 'users_count', label: 'role.users' },
    ],
    fields: [
      { key: 'title', label: 'perm.title', required: true },
      { key: 'permissions', label: 'role.permissions', type: 'checklist', lookup: 'permissions', labelKey: 'title',
        fromRow: (row) => (row.permissions || []).map((p) => p.id) },
    ],
  },

  users: {
    endpoint: '/users', perm: 'user', title: 'user.singular', menu: 'menu.users',
    columns: [
      { key: 'id', label: 'common.id', sortable: true, width: 70 },
      { key: 'name', label: 'common.name', sortable: true, searchable: true },
      { key: 'username', label: 'user.username', sortable: true, searchable: true },
      { key: 'email', label: 'user.email', sortable: true, searchable: true },
      { key: 'email_verified_at', label: 'user.emailVerifiedAt', sortable: true, type: 'datetime' },
      { key: 'two_factor_enabled', label: 'user.twoFactor', sortable: true, type: 'boolean' },
      { key: 'roles', label: 'user.roles', type: 'badges', badgeKey: 'title' },
      { key: 'staff', label: 'user.staff', format: (r) => r.staff?.name || '' },
      { key: 'staff_code', label: 'settings.staffCode', format: (r) => r.staff?.staff_code || '' },
      { key: 'active', label: 'common.active', type: 'boolean', sortable: true },
    ],
    fields: [
      { key: 'name', label: 'common.name', required: true },
      { key: 'name_kh', label: 'common.nameKh' },
      { key: 'username', label: 'user.username' },
      { key: 'email', label: 'user.email', type: 'email', required: true },
      { key: 'password', label: 'user.password', type: 'password', hint: 'user.passwordHint' },
      { key: 'roles', label: 'user.roles', type: 'multiselect', lookup: 'roles', labelKey: 'title', required: true,
        fromRow: (row) => (row.roles || []).map((r) => r.id) },
      { key: 'title_id', label: 'user.title', type: 'select', lookup: 'titles' },
      { key: 'position_id', label: 'user.position', type: 'select', lookup: 'positions' },
      { key: 'department_id', label: 'user.department', type: 'select', lookup: 'departments' },
      { key: 'phone', label: 'user.phone' },
      { key: 'active', label: 'common.active', type: 'checkbox', default: true },
      { key: 'email_verified', label: 'user.emailVerified', type: 'checkbox', default: true, fromRow: (row) => !!row.email_verified_at },
      { key: 'two_factor_enabled', label: 'user.twoFactor', type: 'checkbox', hint: 'user.twoFactorHint', editOnly: true },
    ],
  },

  'user-infos': {
    endpoint: '/user-infos', perm: 'user_info', title: 'userInfo.singular', menu: 'menu.userInfos',
    columns: [
      { key: 'id', label: 'common.id', sortable: true, width: 70 },
      { key: 'user.name', label: 'userInfo.user', searchable: true, format: (r) => r.user?.name },
      { key: 'user.email', label: 'user.email', searchable: true, format: (r) => r.user?.email },
      { key: 'title', label: 'user.title', format: (r) => r.user?.title?.name || '' },
      { key: 'title_kh', label: 'common.nameKh', format: (r) => r.user?.title?.name_kh || '' },
      { key: 'position', label: 'user.position', format: (r) => r.user?.position?.name || '' },
      { key: 'position_kh', label: 'common.nameKh', format: (r) => r.user?.position?.name_kh || '' },
      { key: 'department', label: 'user.department', format: (r) => r.user?.department?.name || '' },
      { key: 'department_kh', label: 'common.nameKh', format: (r) => r.user?.department?.name_kh || '' },
      { key: 'gender', label: 'settings.gender', sortable: true, searchable: true, filterOptions: genderOptions },
      { key: 'date_of_birth', label: 'userInfo.dob', sortable: true, type: 'date' },
    ],
    fields: [
      { key: 'user_id', label: 'userInfo.user', type: 'select', lookup: 'users', required: true },
      { key: 'title_id', label: 'user.title', type: 'select', lookup: 'titles', fromRow: (r) => r.user?.title_id ?? null },
      { key: 'position_id', label: 'user.position', type: 'select', lookup: 'positions', fromRow: (r) => r.user?.position_id ?? null },
      { key: 'department_id', label: 'user.department', type: 'select', lookup: 'departments', fromRow: (r) => r.user?.department_id ?? null },
      { key: 'gender', label: 'settings.gender', type: 'select', options: genderOptions },
      { key: 'date_of_birth', label: 'userInfo.dob', type: 'date' },
      { key: 'place_of_birth', label: 'userInfo.pob' },
      { key: 'id_card_no', label: 'userInfo.idCard' },
      { key: 'phone', label: 'user.phone' },
      { key: 'address', label: 'userInfo.address', type: 'textarea' },
    ],
  },
}

/** Text shown for one cell (used by the table and exports) */
export function cellText(row, c, t) {
  const v = row[c.key]
  if (c.format) return c.format(row) ?? ''
  if (c.type === 'boolean') return v ? '✔' : ''
  if (c.type === 'badges') return (v || []).map((x) => x[c.badgeKey || 'title']).join(', ')
  if (c.type === 'datetime') return formatDate(v)
  if (c.type === 'date') return formatDate(v, false)
  if (c.relation) return ln(v)
  return v ?? ''
}

export function getResource(key) {
  return userManagementResources[key] || settingsResources[key]
}
