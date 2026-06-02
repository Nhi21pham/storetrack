<template>
  <div class="modal-overlay" @click.self="handleClickOutside">
    <div class="modal">
      <div class="modal-header">
        <h2>{{ isEdit ? 'Edit Product' : 'New Product' }}</h2>
        <button class="close-btn" @click="handleClose">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
          </svg>
        </button>
      </div>

      <div class="modal-body">
        <div v-if="isEdit" class="form-group">
          <label>Code</label>
          <input
            :value="form.code_display"
            type="text"
            disabled
            class="code-display"
          />
          <p class="hint">Auto-generated. Cannot be changed.</p>
        </div>

        <div class="form-group">
          <label>Category <span class="required">*</span></label>
          <div class="picker-row">
            <select
              v-model="form.product_category_id"
              :class="{ error: errors.product_category_id }"
              :disabled="categoriesLoading || isEdit"
            >
              <option value="" disabled>{{ categoriesLoading ? 'Loading categories…' : 'Select a category' }}</option>
              <option v-for="c in availableCategories" :key="c.id" :value="String(c.id)">
                {{ c.code }} — {{ displayCategoryName(c) }}
              </option>
            </select>
            <AddItemButton
              v-if="!isEdit"
              size="small"
              title="Create a new category"
              :disabled="categoriesLoading"
              @click="showCategoryForm = true"
            />
          </div>
          <span v-if="errors.product_category_id" class="error-text">{{ errors.product_category_id }}</span>
          <p v-if="!isEdit && form.product_category_id && nextCodePreview" class="hint">
            Next code will be: <strong>{{ nextCodePreview }}</strong>
          </p>
          <p v-else-if="!isEdit" class="hint">
            The product code is auto-generated from the category prefix (e.g. SV000001).
          </p>
          <p v-if="isEdit" class="hint">Category cannot be changed once a product has a code.</p>
        </div>

        <div class="form-group" :class="{ 'has-suggestions': showNameSuggestions }">
          <label>Product Name <span class="required">*</span></label>
          <input
            v-model="form.name"
            type="text"
            placeholder="e.g. Áo thun trắng"
            :class="{ error: errors.name }"
            @focus="activeField = 'name'"
            @blur="onBlurField"
          />
          <span v-if="errors.name" class="error-text">{{ errors.name }}</span>
          <SuggestionList
            v-if="showNameSuggestions"
            :items="nameSuggestions"
            @pick="onPickSuggestion"
          />
        </div>

        <div class="form-group">
          <label>Unit <span class="required">*</span></label>
          <div class="picker-row">
            <select
              v-model="form.unit_id"
              :class="{ error: errors.unit_id }"
              :disabled="unitsLoading"
            >
              <option value="" disabled>{{ unitsLoading ? 'Loading units…' : 'Select a unit' }}</option>
              <option v-for="u in activeUnits" :key="u.id" :value="String(u.id)">{{ u.name }}</option>
            </select>
            <AddItemButton
              size="small"
              title="Create a new unit"
              :disabled="unitsLoading"
              @click="showUnitForm = true"
            />
          </div>
          <span v-if="errors.unit_id" class="error-text">{{ errors.unit_id }}</span>
        </div>

        <div v-if="isEdit" class="form-group toggle-group">
          <div class="toggle-row">
            <ToggleSwitch v-model="form.is_active" />
            <span class="toggle-text">Active</span>
          </div>
          <p class="hint">Inactive products won't appear in pickers for new transactions.</p>
        </div>

        <div v-if="apiError" class="api-error">{{ apiError }}</div>
      </div>

      <div class="modal-footer">
        <button class="btn-cancel" @click="handleClose" :disabled="loading">Cancel</button>
        <button class="btn-submit" @click="handleSubmit" :disabled="loading || !isDirty">
          <span v-if="loading" class="spinner"></span>
          {{ isEdit ? 'Save Changes' : 'Create Product' }}
        </button>
      </div>
    </div>
  </div>

  <ConfirmDialog
    v-if="showUnsavedWarning"
    title="Unsaved Changes"
    message="You have unsaved changes. Are you sure you want to discard them?"
    confirm-text="Yes, discard"
    cancel-text="Keep editing"
    @confirm="$emit('close')"
    @cancel="showUnsavedWarning = false"
  />

  <UnitFormModal
    v-if="showUnitForm"
    :unit="null"
    :store-id="storeId"
    @close="showUnitForm = false"
    @saved="onUnitCreated"
    @pick-existing="onUnitCreated"
  />

  <ProductCategoryFormModal
    v-if="showCategoryForm"
    :category="null"
    :store-id="storeId"
    @close="showCategoryForm = false"
    @saved="onCategoryCreated"
    @pick-existing="onCategoryCreated"
  />
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import ConfirmDialog from '@/components/common/ConfirmDialog.vue'
import ToggleSwitch from '@/components/common/ToggleSwitch.vue'
import AddItemButton from '@/components/common/AddItemButton.vue'
import SuggestionList from '@/features/products/components/ProductSuggestionList.vue'
import UnitFormModal from '@/features/units/components/UnitFormModal.vue'
import ProductCategoryFormModal from '@/features/productCategories/components/ProductCategoryFormModal.vue'
import { createProduct, updateProduct, searchProducts } from '@/features/products/services/productService'
import { fetchUnits } from '@/features/units/services/unitService'
import { fetchProductCategories } from '@/features/productCategories/services/productCategoryService'
import { displayCategoryName } from '@/features/productCategories/constants'
import { normalizeText } from '@/utils/textNormalizer'

const props = defineProps({
  product: { type: Object, default: null },
  storeId: { type: [String, Number], default: null },
})

const emit = defineEmits(['close', 'saved', 'pick-existing'])

const isEdit = computed(() => !!props.product)
const loading = ref(false)
const apiError = ref('')
const showUnsavedWarning = ref(false)
const showUnitForm = ref(false)
const showCategoryForm = ref(false)

const errors = ref({ name: '', unit_id: '', product_category_id: '' })

const initialForm = () => ({
  name: props.product?.name || '',
  unit_id: props.product?.unit_id ? String(props.product.unit_id) : '',
  product_category_id: props.product?.product_category_id ? String(props.product.product_category_id) : '',
  code_display: props.product?.code || '',
  is_active: props.product?.is_active ?? true,
})

const form = ref(initialForm())
const originalForm = ref(JSON.stringify(initialForm()))
const isDirty = computed(() => JSON.stringify(form.value) !== originalForm.value)

const units = ref([])
const unitsLoading = ref(false)
const activeUnits = computed(() => {
  const list = units.value.filter(u => u.is_active)
  // If editing and the current unit is inactive, surface it so the picker shows
  // the current value rather than dropping it silently.
  const currentId = props.product?.unit_id ? String(props.product.unit_id) : null
  if (currentId && !list.some(u => String(u.id) === currentId)) {
    const found = units.value.find(u => String(u.id) === currentId)
    if (found) return [found, ...list]
  }
  return list
})

const categories = ref([])
const categoriesLoading = ref(false)
const availableCategories = computed(() => {
  const list = categories.value.filter(c => c.is_active)
  const currentId = props.product?.product_category_id ? String(props.product.product_category_id) : null
  if (currentId && !list.some(c => String(c.id) === currentId)) {
    const found = categories.value.find(c => String(c.id) === currentId)
    if (found) return [found, ...list]
  }
  return list
})

const nextCodePreview = computed(() => {
  const id = form.value.product_category_id
  if (!id) return ''
  const cat = categories.value.find(c => String(c.id) === id)
  if (!cat) return ''
  const next = (cat.last_sequence || 0) + 1
  return cat.code + String(next).padStart(6, '0')
})

const loadOptions = async () => {
  if (!props.storeId) return
  unitsLoading.value = true
  categoriesLoading.value = true
  try {
    const [unitList, categoryList] = await Promise.all([
      fetchUnits({ storeId: props.storeId, includeInactive: true }),
      fetchProductCategories({ storeId: props.storeId, includeInactive: true }),
    ])
    units.value = unitList
    categories.value = categoryList
  } finally {
    unitsLoading.value = false
    categoriesLoading.value = false
  }
}

onMounted(loadOptions)
watch(() => props.storeId, loadOptions)

const onUnitCreated = async (unit) => {
  showUnitForm.value = false
  if (props.storeId) {
    units.value = await fetchUnits({ storeId: props.storeId, includeInactive: true })
  }
  if (unit?.id != null) {
    form.value.unit_id = String(unit.id)
  }
}

const onCategoryCreated = async (category) => {
  showCategoryForm.value = false
  if (props.storeId) {
    categories.value = await fetchProductCategories({ storeId: props.storeId, includeInactive: true })
  }
  if (category?.id != null) {
    form.value.product_category_id = String(category.id)
  }
}

const activeField = ref(null)
const nameSuggestions = ref([])
const showNameSuggestions = computed(() => activeField.value === 'name' && nameSuggestions.value.length > 0)

let searchTimer = null

const runSearch = async (value) => {
  const q = value.trim()
  if (q.length < 1 || !props.storeId) {
    nameSuggestions.value = []
    return
  }
  try {
    const results = await searchProducts({ storeId: props.storeId, q, includeInactive: true, limit: 8 })
    const currentId = props.product?.id ? String(props.product.id) : null
    nameSuggestions.value = (results || []).filter(r => String(r.id) !== currentId)
  } catch (e) {
    nameSuggestions.value = []
  }
}

watch(
  () => [form.value.name, activeField.value],
  () => {
    if (searchTimer) clearTimeout(searchTimer)
    if (activeField.value !== 'name') {
      nameSuggestions.value = []
      return
    }
    searchTimer = setTimeout(() => runSearch(form.value.name), 250)
  }
)

watch(() => props.product, () => {
  form.value = initialForm()
  originalForm.value = JSON.stringify(initialForm())
  errors.value = { name: '', unit_id: '', product_category_id: '' }
  apiError.value = ''
  nameSuggestions.value = []
})

const onBlurField = () => {
  setTimeout(() => {
    activeField.value = null
    nameSuggestions.value = []
  }, 150)
}

const onPickSuggestion = (product) => {
  nameSuggestions.value = []
  emit('pick-existing', product)
}

const validateForm = () => {
  errors.value = { name: '', unit_id: '', product_category_id: '' }
  const name = form.value.name.trim()
  if (!name) errors.value.name = 'Product name is required.'
  else if (name.length > 100) errors.value.name = 'Product name must be at most 100 characters.'

  if (!form.value.unit_id) errors.value.unit_id = 'Unit is required.'
  if (!isEdit.value && !form.value.product_category_id) {
    errors.value.product_category_id = 'Category is required.'
  }

  if (!isEdit.value) {
    const norm = normalizeText(name)
    const dup = nameSuggestions.value.find(s => normalizeText(s.name) === norm)
    if (dup) {
      apiError.value = `A product with this name already exists: ${dup.name}. Open it from the suggestion list above to edit.`
      return false
    }
  }

  return !Object.values(errors.value).some(e => e !== '')
}

const handleSubmit = async () => {
  apiError.value = ''
  if (!validateForm()) return

  loading.value = true
  try {
    if (isEdit.value) {
      const input = {
        name: form.value.name,
        unit_id: form.value.unit_id,
        is_active: form.value.is_active,
      }
      const result = await updateProduct({ id: props.product.id, input })
      emit('saved', result)
    } else {
      const input = {
        name: form.value.name,
        unit_id: form.value.unit_id,
        product_category_id: form.value.product_category_id,
      }
      const result = await createProduct({ storeId: props.storeId, input })
      emit('saved', result)
    }
  } catch (err) {
    apiError.value = err.message
  } finally {
    loading.value = false
  }
}

const handleClickOutside = () => {
  if (isDirty.value) showUnsavedWarning.value = true
  else emit('close')
}

const handleClose = () => {
  if (isDirty.value) showUnsavedWarning.value = true
  else emit('close')
}
</script>

<style scoped>
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center; z-index: 1000; }
.modal { background: #fff; border-radius: 14px; width: 100%; max-width: 520px; box-shadow: 0 24px 80px rgba(0,0,0,0.15); overflow: visible; }

.modal-header { display: flex; align-items: center; justify-content: space-between; padding: 20px 24px 0; }
.modal-header h2 { font-size: 18px; font-weight: 700; color: #111; }
.close-btn { background: none; border: none; color: #9ca3af; cursor: pointer; padding: 4px; border-radius: 6px; transition: all 0.15s; }
.close-btn:hover { color: #374151; background: #f3f4f6; }

.modal-body { padding: 20px 24px; }

.form-group { margin-bottom: 16px; position: relative; }
.form-group label { display: block; font-size: 13px; font-weight: 500; color: #374151; margin-bottom: 6px; }
.required { color: #dc2626; }

.picker-row { display: flex; align-items: stretch; gap: 8px; }
.picker-row > select { flex: 1; min-width: 0; }

.form-group input[type="text"], .form-group select { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; color: #111; background: #fff; transition: border-color 0.15s; outline: none; box-sizing: border-box; }
.form-group input[type="text"]:focus, .form-group select:focus { border-color: #111; box-shadow: 0 0 0 3px rgba(17,24,39,0.08); }
.form-group input[type="text"].error, .form-group select.error { border-color: #dc2626; }
.form-group input.code-display { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-weight: 700; color: #4338ca; background: #f9fafb; cursor: not-allowed; }

.form-group select {
  appearance: none;
  -webkit-appearance: none;
  padding-right: 36px;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 12px center;
  background-size: 14px;
}
.form-group select:disabled { background-color: #f9fafb; color: #6b7280; cursor: not-allowed; }

.error-text { display: block; font-size: 12px; color: #dc2626; margin-top: 4px; }

.toggle-group { display: flex; flex-direction: column; gap: 6px; }
.toggle-row { display: flex; align-items: center; gap: 10px; }
.toggle-text { font-size: 14px; font-weight: 500; color: #111; }
.hint { margin: 4px 0 0; font-size: 12px; color: #6b7280; }
.hint.warn { color: #b45309; }

.api-error { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; padding: 10px 14px; border-radius: 8px; font-size: 13px; margin-top: 4px; }

.modal-footer { display: flex; justify-content: flex-end; gap: 10px; padding: 16px 24px; border-top: 1px solid #f3f4f6; }

.btn-cancel { padding: 10px 20px; background: #f3f4f6; color: #111; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; }
.btn-cancel:hover { background: #e9eaec; }
.btn-cancel:disabled { opacity: 0.5; cursor: not-allowed; }

.btn-submit { padding: 10px 20px; background: #111; color: #fff; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px; }
.btn-submit:hover:not(:disabled) { background: #333; }
.btn-submit:disabled { opacity: 0.5; cursor: not-allowed; }

.spinner { width: 14px; height: 14px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.6s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
