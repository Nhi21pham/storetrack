<template>
  <div class="customer-page">
    <div class="page-header">
      <div>
        <h1>Customers</h1>
        <p class="subtitle">Manage your customer contacts and information.</p>
      </div>
      <button class="btn-create" @click="openCreate">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        New Customer
      </button>
    </div>

    <SearchBar v-model="searchQuery" placeholder="Search by name, email, tax code, phone or address..." />

    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <span>Loading customers...</span>
    </div>

    <div v-else-if="customers.length === 0" class="empty-state">
      <div class="empty-icon">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
          <circle cx="9" cy="7" r="4"/>
          <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
          <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
        </svg>
      </div>
      <h3>No customers yet</h3>
      <p>Add your first customer to get started.</p>
    </div>

    <div v-else-if="filteredCustomers.length === 0" class="empty-state">
      <p>No customers matching "{{ searchQuery }}"</p>
    </div>

    <div v-else class="table-wrapper" :class="{ resizing: isResizing }">
      <table>
        <colgroup>
          <col v-for="(w, i) in colWidths" :key="i" :style="{ width: w + 'px' }" />
        </colgroup>
        <thead>
          <tr>
            <th v-for="(col, i) in columns" :key="col.key">
              {{ col.label }}
              <div class="resize-handle" @mousedown.prevent="startResize($event, i)"></div>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="customer in filteredCustomers" :key="customer.id">
            <td><span class="id-badge">#{{ customer.id }}</span></td>
            <td><span class="name-text">{{ customer.name }}</span></td>
            <td>
              <span v-if="customer.tax_code" class="mono">{{ customer.tax_code }}</span>
              <span v-else class="empty-val">—</span>
            </td>
            <td>
              <span v-if="customer.email">{{ customer.email }}</span>
              <span v-else class="empty-val">—</span>
            </td>
            <td>
              <span v-if="customer.phone" class="mono">{{ customer.phone }}</span>
              <span v-else class="empty-val">—</span>
            </td>
            <td>
              <span v-if="customer.address" :title="customer.address" class="truncate">{{ customer.address }}</span>
              <span v-else class="empty-val">—</span>
            </td>
            <td>
              <div class="row-actions">
                <button class="action-btn" @click="openEdit(customer)" title="Edit">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </button>
                <button class="action-btn danger" @click="confirmDelete(customer)" title="Delete">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <CustomerFormModal
      v-if="showForm"
      :customer="editingCustomer"
      @close="showForm = false"
      @saved="onSaved"
    />

    <ConfirmDialog
      v-if="deletingCustomer"
      title="Delete Customer"
      :message="`Are you sure you want to delete '${deletingCustomer.name}'? This action cannot be undone.`"
      confirm-text="Yes, delete"
      cancel-text="Cancel"
      type="danger"
      @confirm="handleDelete"
      @cancel="deletingCustomer = null"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, inject } from 'vue'
import { graphql } from '@/api'
import SearchBar from '@/components/common/SearchBar.vue'
import ConfirmDialog from '@/components/common/ConfirmDialog.vue'
import CustomerFormModal from '@/components/customer/CustomerFormModal.vue'

const showToast = inject('showToast')

const customers = ref([])
const loading = ref(true)
const showForm = ref(false)
const editingCustomer = ref(null)
const deletingCustomer = ref(null)
const searchQuery = ref('')

const columns = [
  { key: 'id',       label: 'ID'       },
  { key: 'name',     label: 'Name'     },
  { key: 'tax_code', label: 'Tax Code' },
  { key: 'email',    label: 'Email'    },
  { key: 'phone',    label: 'Phone'    },
  { key: 'address',  label: 'Address'  },
  { key: 'actions',  label: ''         },
]

const colWidths = ref([70, 160, 120, 190, 130, 220, 80])

const isResizing = ref(false)
let resizeState = null

const startResize = (e, index) => {
  resizeState = {
    index,
    startX: e.clientX,
    startWidth: colWidths.value[index],
  }
  isResizing.value = true

  const onMove = (e) => {
    if (!resizeState) return
    const delta = e.clientX - resizeState.startX
    colWidths.value[resizeState.index] = Math.max(50, resizeState.startWidth + delta)
  }

  const onUp = () => {
    resizeState = null
    isResizing.value = false
    window.removeEventListener('mousemove', onMove)
    window.removeEventListener('mouseup', onUp)
  }

  window.addEventListener('mousemove', onMove)
  window.addEventListener('mouseup', onUp)
}

onBeforeUnmount(() => {
  isResizing.value = false
  resizeState = null
})

const filteredCustomers = computed(() => {
  if (!searchQuery.value.trim()) return customers.value
  const q = searchQuery.value.toLowerCase()
  return customers.value.filter(c =>
    c.name.toLowerCase().includes(q) ||
    c.email?.toLowerCase().includes(q) ||
    c.tax_code?.toLowerCase().includes(q) ||
    c.address?.toLowerCase().includes(q) ||
    c.phone?.includes(q)
  )
})

const fetchCustomers = async () => {
  loading.value = true
  try {
    const data = await graphql(`query {
      customers { id name email phone address tax_code }
    }`)
    customers.value = data.customers
  } catch (err) {
    showToast(err.message, 'error')
  } finally {
    loading.value = false
  }
}

const openCreate = () => { editingCustomer.value = null; showForm.value = true }
const openEdit = (c) => { editingCustomer.value = { ...c }; showForm.value = true }

const onSaved = () => {
  showForm.value = false
  fetchCustomers()
  showToast(editingCustomer.value ? 'Customer updated successfully!' : 'Customer created successfully!')
}

const confirmDelete = (c) => { deletingCustomer.value = c }

const handleDelete = async () => {
  try {
    await graphql(
      `mutation DeleteCustomer($id: ID!) { deleteCustomer(id: $id) }`,
      { id: deletingCustomer.value.id }
    )
    deletingCustomer.value = null
    fetchCustomers()
    showToast('Customer deleted successfully!')
  } catch (err) {
    showToast(err.message, 'error')
  }
}

onMounted(fetchCustomers)
</script>

<style scoped>
.customer-page { padding: 32px; max-width: 1100px; margin: 0 auto; }

.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 28px; }
.page-header h1 { font-size: 22px; font-weight: 700; color: #111; }
.subtitle { font-size: 14px; color: #6b7280; margin-top: 4px; }

.btn-create { padding: 10px 18px; background: #111; color: #fff; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px; transition: background 0.2s; white-space: nowrap; }
.btn-create:hover { background: #333; }

.loading-state { display: flex; align-items: center; justify-content: center; gap: 12px; padding: 60px 0; color: #6b7280; font-size: 14px; }
.spinner { width: 20px; height: 20px; border: 2.5px solid #e5e7eb; border-top-color: #111; border-radius: 50%; animation: spin 0.6s linear infinite; }

.empty-state { text-align: center; padding: 60px 20px; }
.empty-icon { width: 72px; height: 72px; background: #f3f4f6; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; color: #9ca3af; }
.empty-state h3 { font-size: 16px; font-weight: 600; color: #111; margin-bottom: 6px; }
.empty-state p { font-size: 14px; color: #6b7280; }

.table-wrapper { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; overflow-x: auto; }
.table-wrapper.resizing { cursor: col-resize; user-select: none; }

table { width: 100%; border-collapse: collapse; font-size: 13.5px; table-layout: fixed; }

thead { background: #f9fafb; }
th { position: relative; padding: 11px 16px; text-align: left; font-size: 11.5px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; border-bottom: 1px solid #e5e7eb; white-space: nowrap; overflow: hidden; }

td { padding: 13px 16px; color: #374151; border-bottom: 1px solid #f3f4f6; vertical-align: middle; overflow: hidden; }
tbody tr:last-child td { border-bottom: none; }
tbody tr:hover { background: #fafafa; }

.resize-handle { position: absolute; top: 0; right: -6px; width: 12px; height: 100%; cursor: col-resize; z-index: 1; display: flex; align-items: stretch; justify-content: center; }
.resize-handle::after { content: ''; width: 2px; border-radius: 2px; background: #d1d5db; transition: background 0.15s; }
.resize-handle:hover::after { background: #6b7280; }
.resizing .resize-handle::after { background: #111; }

.id-badge { font-size: 12px; font-weight: 600; color: #9ca3af; font-family: monospace; }
.name-text { font-weight: 600; color: #111; }
.mono { font-family: monospace; font-size: 13px; }
.empty-val { color: #d1d5db; }
.truncate { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.row-actions { display: flex; gap: 4px; justify-content: flex-end; }
.action-btn { display: flex; align-items: center; justify-content: center; width: 30px; height: 30px; background: none; border: 1px solid #e5e7eb; border-radius: 6px; color: #6b7280; cursor: pointer; transition: all 0.15s; }
.action-btn:hover { background: #f3f4f6; color: #111; border-color: #d1d5db; }
.action-btn.danger:hover { background: #fef2f2; color: #dc2626; border-color: #fecaca; }

@keyframes spin { to { transform: rotate(360deg); } }
</style>
