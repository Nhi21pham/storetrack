export const validators = {
    email: (value) => {
        if (!value) return 'Email is required.'
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
        if (!emailRegex.test(value)) return 'Please enter a valid email address.'
        return null
    },

    password: (value) => {
        if (!value) return 'Password is required.'
        if (value.length < 8) return 'Password must be at least 8 characters.'
        return null
    },

    confirmPassword: (value, original) => {
        if (!value) return 'Please confirm your password.'
        if (value !== original) return 'Passwords do not match.'
        return null
    },

    name: (value) => {
        if (!value) return 'Name is required.'
        if (value.length < 2) return 'Name must be at least 2 characters.'
        if (value.length > 255) return 'Name is too long.'
        return null
    },

    code: (value) => {
        if (!value) return 'Verification code is required.'
        if (value.length !== 6) return 'Code must be 6 digits.'
        if (!/^\d+$/.test(value)) return 'Code must contain numbers only.'
        return null
    },
    businessName: (value) => {
        if (!value || !value.trim()) return 'Business name is required.'
        if (value.trim().length < 2) return 'Business name must be at least 2 characters.'
        if (value.length > 255) return 'Business name is too long.'
        return null
    },

    taxCode: (value) => {
        if (!value || !value.trim()) return 'Tax code is required.'
        if (!/^\d+$/.test(value.trim())) return 'Tax code must contain numbers only.'
        if (value.trim().length < 5) return 'Tax code must be at least 5 digits.'
        if (value.length > 50) return 'Tax code is too long.'
        return null
    },

    businessEmail: (value) => {
        if (!value || !value.trim()) return null  // optional field
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
        if (!emailRegex.test(value)) return 'Please enter a valid email address.'
        return null
    },

    businessPhone: (value) => {
        if (!value || !value.trim()) return null
        if (!/^\d{10}$/.test(value.trim())) return 'Invalid phone number. Must be exactly 10 digits.'
        return null
    },

    businessAddress: (value) => {
        if (!value || !value.trim()) return null  // optional field
        if (value.length > 500) return 'Address is too long.'
        return null
    },

    storeName: (value) => {
        if (!value || !value.trim()) return 'Store name is required.'
        if (value.trim().length < 2) return 'Store name must be at least 2 characters.'
        if (value.length > 255) return 'Store name is too long.'
        return null
    },

    supplierName: (value) => {
        if (!value || !value.trim()) return 'Supplier name is required.'
        if (value.trim().length < 2) return 'Supplier name must be at least 2 characters.'
        if (value.length > 255) return 'Supplier name is too long.'
        return null
    },

    supplierTaxCode: (value) => {
        if (!value || !value.trim()) return null
        if (value.trim().length > 50) return 'Tax code is too long.'
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