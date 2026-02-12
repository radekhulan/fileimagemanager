import axios, { type AxiosInstance, type AxiosRequestConfig } from 'axios'

let csrfToken = ''

export function setCsrfToken(token: string): void {
  csrfToken = token
}

export function getCsrfToken(): string {
  return csrfToken
}

/**
 * Detect the base path from the current page URL.
 * Works whether the app is served from /, /public/, /filemanager/, etc.
 */
function getBasePath(): string {
  const path = window.location.pathname
  return path.endsWith('/') ? path : path.substring(0, path.lastIndexOf('/') + 1)
}

const apiClient: AxiosInstance = axios.create({
  baseURL: getBasePath() + 'api',
  headers: {
    'Accept': 'application/json',
  },
  withCredentials: true,
})

// Automatically attach CSRF token to POST requests
apiClient.interceptors.request.use((config) => {
  if (config.method === 'post' && csrfToken) {
    config.headers['X-CSRF-Token'] = csrfToken
  }
  return config
})

// Session retry guard - prevents infinite loops
let sessionRetrying = false

// Handle error responses
apiClient.interceptors.response.use(
  (response) => response,
  async (error) => {
    if (error.response?.status === 403) {
      const data = error.response.data

      if (data?.error?.includes('Session not verified') && !sessionRetrying) {
        // Session expired - try to re-initialize once before giving up
        sessionRetrying = true
        try {
          const initResponse = await axios.get(
            getBasePath() + 'api/session/init',
            { withCredentials: true },
          )
          if (initResponse.data?.csrfToken) {
            setCsrfToken(initResponse.data.csrfToken)
          }
          sessionRetrying = false
          // Retry the original request
          return apiClient.request(error.config)
        } catch {
          sessionRetrying = false
          // Re-init also failed, nothing we can do
        }
      }

      if (data?.error?.includes('CSRF') && !sessionRetrying) {
        // CSRF token expired, reload page once
        window.location.reload()
        return Promise.reject(error)
      }
    }
    return Promise.reject(error)
  },
)

export default apiClient

/**
 * Upload files with progress tracking.
 */
export function uploadFiles(
  files: File[],
  path: string,
  onProgress?: (percent: number) => void,
  signal?: AbortSignal,
): Promise<any> {
  const formData = new FormData()
  formData.append('path', path)
  files.forEach((file) => {
    formData.append('files[]', file)
  })

  return apiClient.post('/upload', formData, {
    headers: {
      'Content-Type': 'multipart/form-data',
    },
    onUploadProgress: (event) => {
      if (event.total && onProgress) {
        onProgress(Math.round((event.loaded * 100) / event.total))
      }
    },
    signal,
  })
}
