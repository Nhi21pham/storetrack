const API_URL = '/graphql'

export async function graphql(query, variables = {}) {
    const token = localStorage.getItem('token')

    let res
    try {
        res = await fetch(API_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            },
            body: JSON.stringify({ query, variables })
        })
    } catch (err) {
        throw new Error('Network error. Please check your connection.')
    }

    if (!res.ok) {
        throw new Error('Server error. Please try again.')
    }

    const data = await res.json()

    // Session expired → redirect to login
    if (data.errors?.some(e => e.message === 'Unauthenticated.')) {
        localStorage.removeItem('token')
        localStorage.removeItem('user')
        localStorage.removeItem('currentStoreId')
        localStorage.removeItem('currentBusinessId')
        window.location.href = '/login'
        throw new Error('Session expired. Please log in again.')
    }

    // GraphQL errors
    if (data.errors) {
        throw new Error(data.errors[0].message)
    }

    return data.data
}