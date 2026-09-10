import { apiClient, USE_MOCK_DATA } from './apiClient';
import { AuthResponse, User, UserRole } from '../types';
import { MOCK_BATCHES, MOCK_USERS } from '../data/mockData';
import { mapUser } from './apiMappers';

const TOKEN_KEY = 'schai_auth_token';
const USER_KEY = 'schai_user';

export interface LoginDto {
  email: string;
  password?: string;
  rememberMe?: boolean;
}

export interface RegisterDto {
  name: string;
  email: string;
  password?: string;
  batch: string;
  role?: UserRole;
}

export const authService = {
  async login(credentials: LoginDto): Promise<AuthResponse> {
    if (USE_MOCK_DATA) {
      await new Promise((resolve) => setTimeout(resolve, 300)); // Simulating network latency

      const email = credentials.email.toLowerCase().trim();
      let user: User | undefined;

      if (email.includes('rep') || email === 'representative@schai.test') {
        user = MOCK_USERS.representative;
      } else if (email === 'student@schai.test' || email.includes('student')) {
        user = MOCK_USERS.student;
      } else {
        // Allow dynamic testing with any university email
        user = {
          id: `usr_${Date.now()}`,
          name: email.split('@')[0].replace('.', ' '),
          email: email,
          role: 'student',
          batch: 'Batch CS-2026-A',
        };
      }

      const mockResponse: AuthResponse = {
        user,
        token: `mock_jwt_token_${user.role}_${Date.now()}`,
      };

      localStorage.setItem(TOKEN_KEY, mockResponse.token);
      localStorage.setItem(USER_KEY, JSON.stringify(mockResponse.user));
      return mockResponse;
    }

    const response = await apiClient.post<any>('/auth/login', credentials);
    const mapped: AuthResponse = { user: mapUser(response.user), token: response.token };
    localStorage.setItem(TOKEN_KEY, mapped.token);
    localStorage.setItem(USER_KEY, JSON.stringify(mapped.user));
    return mapped;
  },

  async register(data: RegisterDto): Promise<AuthResponse> {
    if (USE_MOCK_DATA) {
      await new Promise((resolve) => setTimeout(resolve, 400));

      const newUser: User = {
        id: `usr_${Date.now()}`,
        name: data.name,
        email: data.email,
        role: data.role || 'student', // Always default to student for public signups
        // `batch` carries a batch id; resolve it to a display name for the UI.
        batch: MOCK_BATCHES.find((b) => b.id === data.batch)?.name
          ?? data.batch
          ?? MOCK_BATCHES[0].name,
      };

      const mockResponse: AuthResponse = {
        user: newUser,
        token: `mock_jwt_token_student_${Date.now()}`,
      };

      localStorage.setItem(TOKEN_KEY, mockResponse.token);
      localStorage.setItem(USER_KEY, JSON.stringify(mockResponse.user));
      return mockResponse;
    }

    const response = await apiClient.post<any>('/auth/register', {
      name: data.name,
      email: data.email,
      password: data.password,
      password_confirmation: data.password,
      batch_id: data.batch,
    });
    const mapped: AuthResponse = { user: mapUser(response.user), token: response.token };
    localStorage.setItem(TOKEN_KEY, mapped.token);
    localStorage.setItem(USER_KEY, JSON.stringify(mapped.user));
    return mapped;
  },

  async logout(): Promise<void> {
    if (!USE_MOCK_DATA) {
      try {
        await apiClient.post('/auth/logout');
      } catch (e) {
        // Ignore logout network failures and clear local state anyway
      }
    }
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(USER_KEY);
  },

  getCurrentUser(): User | null {
    const raw = localStorage.getItem(USER_KEY);
    if (!raw) return null;
    try {
      return JSON.parse(raw) as User;
    } catch {
      return null;
    }
  },

  getStoredToken(): string | null {
    return localStorage.getItem(TOKEN_KEY);
  },

  switchDemoRole(role: UserRole): User {
    const targetUser = MOCK_USERS[role];
    localStorage.setItem(USER_KEY, JSON.stringify(targetUser));
    localStorage.setItem(TOKEN_KEY, `mock_jwt_token_${role}`);
    return targetUser;
  }
};
