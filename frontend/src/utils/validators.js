import { t } from '@/i18n'

// Localized client-side validators. Each returns a translated message string
// when invalid, or null when valid. Field names and shared patterns
// (required / min length / too long) are interpolated from the `validation`
// namespace so messages follow the active language.
const required = (fieldKey) => t('validation.required', { field: t(`validation.field.${fieldKey}`) })
const minLength = (fieldKey, min) => t('validation.minLength', { field: t(`validation.field.${fieldKey}`), min })
const tooLong = (fieldKey) => t('validation.tooLong', { field: t(`validation.field.${fieldKey}`) })

const EMAIL_REGEX = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

export const validators = {
    email: (value) => {
        if (!value) return required('email')
        if (!EMAIL_REGEX.test(value)) return t('validation.emailInvalid')
        return null
    },

    password: (value) => {
        if (!value) return required('password')
        if (value.length < 8) return minLength('password', 8)
        return null
    },

    confirmPassword: (value, original) => {
        if (!value) return t('validation.confirmRequired')
        if (value !== original) return t('validation.passwordsMismatch')
        return null
    },

    name: (value) => {
        if (!value) return required('name')
        if (value.length < 2) return minLength('name', 2)
        if (value.length > 255) return tooLong('name')
        return null
    },

    code: (value) => {
        if (!value) return required('verificationCode')
        if (value.length !== 6) return t('validation.codeLength')
        if (!/^\d+$/.test(value)) return t('validation.codeNumbersOnly')
        return null
    },

    businessName: (value) => {
        if (!value || !value.trim()) return required('businessName')
        if (value.trim().length < 2) return minLength('businessName', 2)
        if (value.length > 255) return tooLong('businessName')
        return null
    },

    taxCode: (value) => {
        if (!value || !value.trim()) return required('taxCode')
        if (!/^\d+$/.test(value.trim())) return t('validation.taxCodeNumbersOnly')
        if (value.trim().length < 5) return t('validation.taxCodeMinDigits', { min: 5 })
        if (value.length > 50) return tooLong('taxCode')
        return null
    },

    businessEmail: (value) => {
        if (!value || !value.trim()) return null  // optional field
        if (!EMAIL_REGEX.test(value)) return t('validation.emailInvalid')
        return null
    },

    businessPhone: (value) => {
        if (!value || !value.trim()) return null
        if (!/^\d{10,11}$/.test(value.trim())) return t('validation.phoneInvalid')
        return null
    },

    businessAddress: (value) => {
        if (!value || !value.trim()) return null  // optional field
        if (value.length > 500) return tooLong('address')
        return null
    },

    storeName: (value) => {
        if (!value || !value.trim()) return required('storeName')
        if (value.trim().length < 2) return minLength('storeName', 2)
        if (value.length > 255) return tooLong('storeName')
        return null
    },

    supplierName: (value) => {
        if (!value || !value.trim()) return required('supplierName')
        if (value.trim().length < 2) return minLength('supplierName', 2)
        if (value.length > 255) return tooLong('supplierName')
        return null
    },

    supplierTaxCode: (value) => {
        if (!value || !value.trim()) return null
        if (value.trim().length > 50) return tooLong('taxCode')
        return null
    },

    customerName: (value) => {
        if (!value || !value.trim()) return required('customerName')
        if (value.trim().length < 2) return minLength('customerName', 2)
        if (value.length > 255) return tooLong('customerName')
        return null
    },

    customerTaxCode: (value) => {
        if (!value || !value.trim()) return null
        if (value.trim().length > 50) return tooLong('taxCode')
        return null
    },

    customerPhone: (value) => {
        if (!value || !value.trim()) return required('phone')
        if (!/^\d{10,11}$/.test(value.trim())) return t('validation.phoneInvalid')
        return null
    },
}

export const validate = (rules) => {
    const errors = []
    for (const rule of rules) {
        const error = rule()
        if (error) errors.push(error)
    }
    return errors
}
