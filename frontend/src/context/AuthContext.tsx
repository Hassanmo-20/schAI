import React, { createContext, useContext, useEffect, useState } from 'react';
import { User, UserRole } from '../types';
import { authService, LoginDto, RegisterDto } from '../services/authService';

interface AuthContextType {
  user: User | null;
  token: string | null;
  isLoading: boolean;
  isAuthenticated: boolean;
  role: UserRole | null;
  login: (credentials: LoginDto) => Promise<void>;
  register: (data: RegisterDto) => Promise<void>;
  logout: () => Promise<void>;
  switchDemoRole: (role: UserRole) => void;
  hasRole: (role: UserRole) => boolean;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export const AuthProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [user, setUser] = useState<User | null>(null);
  const [token, setToken] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState<boolean>(true);

  useEffect(() => {
    // Restore session from storage. No auto-login: unauthenticated users
    // must see /login so ProtectedRoute can do its job.
    const storedUser = authService.getCurrentUser();
    const storedToken = authService.getStoredToken();

    if (storedUser && storedToken) {
      setUser(storedUser);
      setToken(storedToken);
    }
    setIsLoading(false);

    const handleUnauthorized = () => {
      setUser(null);
      setToken(null);
    };

    window.addEventListener('schai:unauthorized', handleUnauthorized);
    return () => window.removeEventListener('schai:unauthorized', handleUnauthorized);
  }, []);

  const login = async (credentials: LoginDto) => {
    setIsLoading(true);
    try {
      const response = await authService.login(credentials);
      setUser(response.user);
      setToken(response.token);
    } finally {
      setIsLoading(false);
    }
  };

  const register = async (data: RegisterDto) => {
    setIsLoading(true);
    try {
      const response = await authService.register(data);
      setUser(response.user);
      setToken(response.token);
    } finally {
      setIsLoading(false);
    }
  };

  const logout = async () => {
    setIsLoading(true);
    try {
      await authService.logout();
      setUser(null);
      setToken(null);
    } finally {
      setIsLoading(false);
    }
  };

  const switchDemoRole = (targetRole: UserRole) => {
    const updated = authService.switchDemoRole(targetRole);
    setUser(updated);
    setToken(authService.getStoredToken());
  };

  const hasRole = (targetRole: UserRole) => {
    return user?.role === targetRole;
  };

  return (
    <AuthContext.Provider
      value={{
        user,
        token,
        isLoading,
        isAuthenticated: !!user && !!token,
        role: user?.role || null,
        login,
        register,
        logout,
        switchDemoRole,
        hasRole,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
};

export const useAuth = (): AuthContextType => {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
};
