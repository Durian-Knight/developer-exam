import axios, { AxiosError } from 'axios'

const TOKEN_KEY = 'ttm.token'

export const tokenStorage = {
  get: (): string | null => localStorage.getItem(TOKEN_KEY),
  set: (token: string) => localStorage.setItem(TOKEN_KEY, token),
  clear: () => localStorage.removeItem(TOKEN_KEY),
}

/** Single configured axios instance - components never call axios directly. */
export const http = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? '/api',
  headers: { Accept: 'application/json' },
  timeout: 15000,
})

http.interceptors.request.use((config) => {
  const token = tokenStorage.get()
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

let onUnauthorized: (() => void) | null = null

/** Registered once in main.ts (keeps this module free of router/store imports). */
export function setUnauthorizedHandler(handler: () => void) {
  onUnauthorized = handler
}

http.interceptors.response.use(
  (response) => response,
  (error: AxiosError) => {
    // Token expired / revoked -> drop session. Login itself returns 422, not 401.
    if (error.response?.status === 401 && onUnauthorized) {
      onUnauthorized()
    }
    return Promise.reject(error)
  },
)

export interface ApiError {
  status: number | null
  message: string
  fieldErrors: Record<string, string[]>
}

/** Normalises any error thrown by `http` into something the UI can render. */
export function toApiError(error: unknown): ApiError {
  if (axios.isAxiosError(error)) {
    const status = error.response?.status ?? null
    const body = error.response?.data as { message?: string; errors?: Record<string, string[]> } | undefined

    if (!error.response) {
      return { status, message: 'Cannot reach the server. Check your connection.', fieldErrors: {} }
    }

    const fallback: Record<number, string> = {
      403: 'You are not allowed to do this.',
      404: 'Not found.',
      422: 'Please fix the highlighted fields.',
      429: 'Too many attempts. Please wait a minute.',
    }

    return {
      status,
      message: (status === 422 ? fallback[422] : body?.message) ?? fallback[status ?? 0] ?? 'Something went wrong.',
      fieldErrors: body?.errors ?? {},
    }
  }

  return { status: null, message: 'Unexpected error.', fieldErrors: {} }
}
