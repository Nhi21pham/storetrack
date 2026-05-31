<template>
  <PageContainer :maxWidth="1100">
    <PageHeader title="Products" subtitle="Manage the products and services available in this store.">
      <template v-if="currentBusiness && currentStore" #actions>
        <button class="btn-create" @click="openCreate">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
          </svg>
          New Product
        </button>
      </template>
    </PageHeader>

    <EmptyState
      v-if="!currentBusiness"
      title="No business found"
      description="You need to create or select a business before managing products."
    />

    <EmptyState
      v-else-if="!currentStore"
      title="No store selected"
      description="Select a store to manage products."
    />

    <template v-else>
      <div class="toolbar">
        <SearchBar v-model="searchQuery" placeholder="Search by name..." />
        <ColumnSelector
          :togglable-columns="columnVisibility.togglableColumns"
          :is-visible="columnVisibility.isVisible"
          :toggle-column="columnVisibility.toggleColumn"
          :reset-columns="columnVisibility.resetColumns"
        />
      </div>

      <LoadingState v-if="loading">Loading products...</LoadingState>

      <EmptyState
        v-else-if="products.length === 0"
        title="No products yet"
        description="Add your first product to get started."
      />

      <div v-else class="table-wrap">
        <ResizableTable :key="tableKey" :columns="columnVisibility.visibleColumns.value" :initial-widths="visibleWidths">
          <template v-for="col in columnVisibility.visibleColumns.value" :key="col.key" #[`header-${col.key}`]="{ col: c }">
            <SortableHeader
              v-if="c.sortable"
              :label="c.label"
              :sort-info="sort.getSortInfo(c.key)"
              :rank="sort.sortCriteria.length > 1 && sort.getSortInfo(c.key) ? sort.sortRank(c.key) : null"
              @sort="(dir) => sort.toggleSort(c.key, dir)"
            />
            <template v-else>{{ c.label }}</template>
          </template>

          <template #filter-category>
            <SearchableSelect
              :modelValue="categoryFilter"
              :options="categoryOptions"
              all-label="(All categories)"
              search-placeholder="Filter category..."
              teleport
              @update:modelValue="categoryFilter = $event"
            />
          </template>
          <template #filter-unit>
            <SearchableSelect
              :modelValue="unitFilter"
              :options="unitOptions"
              all-label="(All units)"
              search-placeholder="Filter unit..."
              teleport
              @update:modelValue="unitFilter = $event"
            />
          </template>
          <template #filter-status>
            <SearchableSelect
              :modelValue="statusFilter"
              :options="STATUS_OPTIONS"
              all-label="(All)"
              search-placeholder="Filter status..."
              teleport
              @update:modelValue="statusFilter = $event"
            />
          </template>

          <tr v-if="filteredProducts.length === 0">
            <td :colspan="columnVisibility.visibleColumns.value.length" class="empty-row">
              No products match the current filters.
            </td>
          </tr>
          <tr v-for="product in paginatedProducts" :key="product.id" :class="{ inactive: !product.is_active }">
            <td v-if="columnVisibility.isVisible('id')" class="id-col">{{ product.id }}</td>
            <td v-if="columnVisibility.isVisible('code')" class="code-col">{{ product.code }}</td>
            <td v-if="columnVisibility.isVisible('name')">
              <button class="name-link" @click="detailProduct = product">{{ product.name }}</button>
            </td>
            <td v-if="columnVisibility.isVisible('category')">
              <span v-if="product.category">{{ displayCategoryName(product.category) }}</span>
              <span v-else class="empty-val">—</span>
            </td>
            <td v-if="columnVisibility.isVisible('unit')">
              <span v-if="product.unit?.name">{{ product.unit.name }}</span>
              <span v-else class="empty-val">—</span>
            </td>
            <td v-if="columnVisibility.isVisible('status')">
              <ToggleSwitch
                :model-value="product.is_active"
                :title="product.is_active ? 'Click to deactivate' : 'Click to activate'"
                @change="onToggleActive(product)"
              />
            </td>
            <td v-if="columnVisibility.isVisible('created_at')">{{ formatDateTime(product.created_at) }}</td>
            <td class="actions-col">
              <button class="action-btn" @click="openEdit(product)" title="Edit">
                <Icon name="edit" :size="14" />
              </button>
              <button v-if="canDelete" class="action-btn danger" @click="confirmDelete(product)" title="Delete">
                <Icon name="delete" :size="14" />
              </button>
            </td>
          </tr>
        </ResizableTable>
        <Pagination
          v-if="total > 0"
          :current-page="currentPage"
          :total-pages="totalPages"
          :total="total"
          :per-page="perPage"
          @update:current-page="currentPage = $event"
          @update:per-page="setPerPage"
        />
      </div>
    </template>

    <ProductFormModal
      v-if="showForm"
      :product="editingProduct"
      :store-id="currentStore?.id"
      @close="closeForm"
      @saved="onSaved"
      @pick-existing="onPickExisting"
    />

    <ProductDetailModal
      v-if="detailProduct"
      :product="detailProduct"
      :can-edit="true"
      @close="detailProduct = null"
      @edit="onDetailEdit"
    />

    <ConfirmDialog
      v-if="deleteTarget"
      :title="`Delete ${deleteTarget.name}?`"
      message="This will permanently remove the product from the store."
      confirm-text="Delete"
      cancel-text="Cancel"
      @confirm="performDelete"
      @cancel="deleteTarget = null"
    />

    <ConfirmDialog
      v-if="deactivateTarget"
      :title="`Product is in use`"
      :message="`${deactivateTarget.name} is referenced elsewhere and cannot be deleted. Deactivate it instead? Inactive products stay linked to existing references but won't appear in new pickers.`"
      confirm-text="Deactivate"
      cancel-text="Cancel"
      @confirm="performDeactivate"
      @cancel="deactivateTarget = null"
    />

    <ConfirmDialog
      v-if="togglingProduct"
      :title="togglingProduct.is_active ? 'Deactivate Product' : 'Reactivate Product'"
      :message="togglingProduct.is_active
        ? `Are you sure you want to deactivate '${togglingProduct.name}'? Existing references stay, but it won't appear in new pickers.`
        : `Are you sure you want to reactivate '${togglingProduct.name}'?`"
      :confirm-text="togglingProduct.is_active ? 'Yes, deactivate' : 'Yes, reactivate'"
      cancel-text="Cancel"
      :type="togglingProduct.is_active ? 'warning' : 'success'"
      @confirm="handleToggle"
      @cancel="togglingProduct = null"
    />
  </PageContainer>
</template>

<script setup>
import { ref, computed, watch, inject, onMounted } from 'vue'
import PageContainer from '@/components/common/PageContainer.vue'
import PageHeader from '@/components/common/PageHeader.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import LoadingState from '@/components/common/LoadingState.vue'
import SearchBar from '@/components/common/SearchBar.vue'
import ConfirmDialog from '@/components/common/ConfirmDialog.vue'
import Icon from '@/components/common/Icon.vue'
import ToggleSwitch from '@/components/common/ToggleSwitch.vue'
import Pagination from '@/components/common/Pagination.vue'
import ResizableTable from '@/components/common/ResizableTable.vue'
import ColumnSelector from '@/components/common/ColumnSelector.vue'
import SortableHeader from '@/components/common/SortableHeader.vue'
import SearchableSelect from '@/components/common/SearchableSelect.vue'
import ProductFormModal from '@/features/products/components/ProductFormModal.vue'
import ProductDetailModal from '@/features/products/components/ProductDetailModal.vue'
import { useClientPagination } from '@/composables/useClientPagination'
import { useColumnVisibility } from '@/composables/useColumnVisibility'
import { useSortCriteria } from '@/composables/useSortCriteria'
import { fetchProducts, deleteProduct, updateProduct } from '@/features/products/services/productService'
import { fetchUnits } from '@/features/units/services/unitService'
import { fetchProductCategories } from '@/features/productCategories/services/productCategoryService'
import { displayCategoryName } from '@/features/productCategories/constants'
import { PRODUCT_COLUMNS, PRODUCT_INITIAL_COL_WIDTHS, STATUS_OPTIONS } from '@/features/products/constants'
import { ErrorCode } from '@/utils/errorCodes'
import { normalizeText } from '@/utils/textNormalizer'
import { formatDateTime } from '@/utils/datetime'

const columnVisibility = useColumnVisibility({
  storageKey: 'products',
  columns: PRODUCT_COLUMNS,
  lockedKeys: ['actions'],
})

const visibleWidths = computed(() => columnVisibility.filterWidths(PRODUCT_INITIAL_COL_WIDTHS))
const tableKey = computed(() => columnVisibility.visibleColumnKeys.value.join('|'))

const currentBusiness = inject('currentBusiness')
const currentStore = inject('currentStore')

const products = ref([])
const units = ref([])
const categories = ref([])
const loading = ref(false)
const searchQuery = ref('')
const statusFilter = ref('')
const unitFilter = ref('')
const categoryFilter = ref('')

const unitOptions = computed(() =>
  units.value.map(u => ({ value: String(u.id), label: u.name }))
)

const categoryOptions = computed(() =>
  categories.value.map(c => ({
    value: String(c.id),
    label: `${c.code} — ${displayCategoryName(c)}`,
  }))
)

const showForm = ref(false)
const editingProduct = ref(null)
const detailProduct = ref(null)
const deleteTarget = ref(null)
const deactivateTarget = ref(null)
const togglingProduct = ref(null)

const onDetailEdit = (product) => {
  detailProduct.value = null
  openEdit(product)
}

const canDelete = computed(() => {
  const role = String(currentStore?.value?.my_role || '').toLowerCase()
  return role === 'owner' || role === 'accountant'
})

const filteredProducts = computed(() => {
  const needle = normalizeText(searchQuery.value)
  return products.value.filter(p => {
    if (statusFilter.value === 'active'   && !p.is_active) return false
    if (statusFilter.value === 'inactive' &&  p.is_active) return false
    if (unitFilter.value && String(p.unit_id) !== unitFilter.value) return false
    if (categoryFilter.value && String(p.product_category_id) !== categoryFilter.value) return false
    if (!needle) return true
    return (
      normalizeText(p.code || '').includes(needle) ||
      normalizeText(p.name).includes(needle) ||
      normalizeText(p.unit?.name || '').includes(needle) ||
      normalizeText(displayCategoryName(p.category || {})).includes(needle)
    )
  })
})

const sort = useSortCriteria()
const sortedProducts = computed(() =>
  sort.sortItems(filteredProducts.value, (product, key) => {
    if (key === 'status')   return product.is_active ? 1 : 0
    if (key === 'unit')     return normalizeText(product.unit?.name || '')
    if (key === 'category') return normalizeText(displayCategoryName(product.category || {}))
    if (key === 'id')       return Number(product.id) || 0
    const v = product[key]
    return typeof v === 'string' ? normalizeText(v) : (v ?? '')
  })
)

const {
  currentPage,
  perPage,
  total,
  totalPages,
  paginated: paginatedProducts,
  setPerPage,
  resetPage,
} = useClientPagination(sortedProducts)

watch([searchQuery, statusFilter, unitFilter, categoryFilter, () => sort.sortCriteria.value], resetPage, { deep: true })

const load = async () => {
  if (!currentStore?.value?.id) {
    products.value = []
    units.value = []
    categories.value = []
    return
  }
  loading.value = true
  try {
    const [productList, unitList, categoryList] = await Promise.all([
      fetchProducts({ storeId: currentStore.value.id, includeInactive: true }),
      fetchUnits({ storeId: currentStore.value.id, includeInactive: true }),
      fetchProductCategories({ storeId: currentStore.value.id, includeInactive: true }),
    ])
    products.value = productList
    units.value = unitList
    categories.value = categoryList
  } finally {
    loading.value = false
  }
}

onMounted(load)

watch(() => currentStore?.value?.id, load)

const openCreate = () => {
  editingProduct.value = null
  showForm.value = true
}

const openEdit = (product) => {
  editingProduct.value = product
  showForm.value = true
}

const closeForm = () => {
  showForm.value = false
  editingProduct.value = null
}

const onSaved = async () => {
  closeForm()
  await load()
}

const onPickExisting = (product) => {
  editingProduct.value = product
  showForm.value = true
}

const confirmDelete = (product) => {
  deleteTarget.value = product
}

const performDelete = async () => {
  const product = deleteTarget.value
  deleteTarget.value = null
  try {
    await deleteProduct({ id: product.id })
    await load()
  } catch (err) {
    if (err.code === ErrorCode.PRODUCT_IN_USE) {
      deactivateTarget.value = product
    } else {
      alert(err.message)
    }
  }
}

const performDeactivate = async () => {
  const product = deactivateTarget.value
  deactivateTarget.value = null
  try {
    await updateProduct({ id: product.id, input: { is_active: false } })
    await load()
  } catch (err) {
    alert(err.message)
  }
}

const onToggleActive = (product) => {
  togglingProduct.value = product
}

const handleToggle = async () => {
  const product = togglingProduct.value
  togglingProduct.value = null
  if (!product) return
  const nextValue = !product.is_active
  const previous = product.is_active
  product.is_active = nextValue
  try {
    await updateProduct({ id: product.id, input: { is_active: nextValue } })
  } catch (err) {
    product.is_active = previous
    alert(err.message)
  }
}
</script>

<style scoped>
.toolbar { display: flex; align-items: center; gap: 16px; margin-bottom: 16px; }
.toolbar :deep(.search-bar) { flex: 1; margin-bottom: 0; }

.empty-row { padding: 24px 16px; text-align: center; color: #9ca3af; font-size: 13px; }

.btn-create { display: flex; align-items: center; gap: 6px; padding: 9px 16px; background: #111; color: #fff; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; }
.btn-create:hover { background: #333; }

.table-wrap { background: transparent; border-radius: 12px; overflow: visible; }
tbody tr.inactive { background: #fafafa; }
tbody tr.inactive td { color: #6b7280; }

.id-col { color: #6b7280; font-variant-numeric: tabular-nums; }
.code-col { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-weight: 700; color: #4338ca; }
.name-link { background: none; border: none; padding: 0; font: inherit; font-weight: 600; color: #111; cursor: pointer; text-align: left; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 100%; }
.name-link:hover { color: #2563eb; text-decoration: underline; }
.empty-val { color: #d1d5db; }

.actions-col { text-align: right; white-space: nowrap; }
.action-btn { display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; background: none; border: 1px solid #e5e7eb; border-radius: 6px; color: #6b7280; cursor: pointer; transition: all 0.15s; margin-left: 4px; }
.action-btn:hover { background: #f3f4f6; color: #111; border-color: #d1d5db; }
.action-btn.danger:hover { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
</style>
