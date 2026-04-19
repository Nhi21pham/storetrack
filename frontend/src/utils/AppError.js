export class AppError extends Error {
    constructor(code, message, statusCode = null) {
        super(message)
        this.code = code
        this.statusCode = statusCode
    }
}