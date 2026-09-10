const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api';
export const USE_MOCK_DATA = import.meta.env.VITE_USE_MOCK_DATA !== 'false';

export interface ApiError {
  message: string;
  status: number;
  errors?: Record<string, string[]>;
}

class ApiClient {
  private baseUrl: string;

  constructor(baseUrl: string) {
    this.baseUrl = baseUrl;
  }

  /**
   * `isMultipart` omits Content-Type so the browser can set the multipart
   * boundary itself — forcing application/json there corrupts file uploads.
   */
  private getHeaders(isMultipart = false): HeadersInit {
    const headers: Record<string, string> = {
      Accept: 'application/json',
    };
    if (!isMultipart) {
      headers['Content-Type'] = 'application/json';
    }
    const token = localStorage.getItem('schai_auth_token');
    if (token) {
      headers['Authorization'] = `Bearer ${token}`;
    }
    return headers;
  }

  async request<T>(endpoint: string, options: RequestInit = {}): Promise<T> {
    const url = `${this.baseUrl}${endpoint.startsWith('/') ? endpoint : `/${endpoint}`}`;
    const isMultipart = options.body instanceof FormData;

    try {
      const response = await fetch(url, {
        ...options,
        headers: {
          ...this.getHeaders(isMultipart),
          ...options.headers,
        },
      });

      if (!response.ok) {
        if (response.status === 401) {
          localStorage.removeItem('schai_auth_token');
          localStorage.removeItem('schai_user');
          // Dispatched event so auth context can listen without circular imports
          window.dispatchEvent(new Event('schai:unauthorized'));
        }

        let errorData: any = null;
        try {
          errorData = await response.json();
        } catch {
          // Response was not JSON
        }

        const apiError: ApiError = {
          message: errorData?.message || `Request failed with status ${response.status}`,
          status: response.status,
          errors: errorData?.errors,
        };
        throw apiError;
      }

      // 204/empty bodies are valid successes — don't blow up parsing them.
      if (response.status === 204 || response.headers.get('content-length') === '0') {
        return undefined as T;
      }

      return (await response.json()) as T;
    } catch (err: any) {
      if (err.status) {
        throw err;
      }
      // Network or unexpected error
      throw {
        message: err.message || 'Network error. Please check your connection.',
        status: 0,
      } as ApiError;
    }
  }

  get<T>(endpoint: string): Promise<T> {
    return this.request<T>(endpoint, { method: 'GET' });
  }

  post<T>(endpoint: string, body?: any): Promise<T> {
    return this.request<T>(endpoint, {
      method: 'POST',
      body: body instanceof FormData ? body : body ? JSON.stringify(body) : undefined,
    });
  }

  put<T>(endpoint: string, body?: any): Promise<T> {
    return this.request<T>(endpoint, {
      method: 'PUT',
      body: body instanceof FormData ? body : body ? JSON.stringify(body) : undefined,
    });
  }

  delete<T>(endpoint: string): Promise<T> {
    return this.request<T>(endpoint, { method: 'DELETE' });
  }
}

export const apiClient = new ApiClient(API_BASE_URL);
