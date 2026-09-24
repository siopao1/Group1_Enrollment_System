/** Error thrown for any non-successful API envelope or transport failure. */
export class ApiError extends Error {
  constructor(message, { status = 0, errors = null, endpoint = "" } = {}) {
    super(message || "The request could not be completed.");
    this.name = "ApiError";
    this.status = status;
    this.errors = errors;
    this.endpoint = endpoint;
  }

  get isUnauthorized() {
    return this.status === 401;
  }

  get isForbidden() {
    return this.status === 403;
  }

  get isNotFound() {
    return this.status === 404;
  }

  get isValidation() {
    return this.status === 422;
  }
}
