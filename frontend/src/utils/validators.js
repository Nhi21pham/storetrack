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
    }
}

export const validate = (rules) => {
    const errors = []
    for (const rule of rules) {
        const error = rule()
        if (error) errors.push(error)
    }
    return errors
}