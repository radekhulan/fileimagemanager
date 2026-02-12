export interface ApiResponse<T = unknown> {
  success: boolean
  message?: string
  error?: string
  [key: string]: unknown
}

export interface ApiSuccessResponse<T = unknown> extends ApiResponse<T> {
  success: true
}

export interface ApiErrorResponse extends ApiResponse {
  success: false
  error: string
}
