api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => Promise.reject(error)
);

// Response interceptor
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('token');
      window.location.href = '/login';
    }
    return Promise.reject(error);
  }
);

export default api;
```

---

#### 2. Auth Store (Vuex/Pinia)

```javascript
// src/stores/auth.js (Pinia)
import { defineStore } from 'pinia';
import api from '@/plugins/axios';

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: JSON.parse(localStorage.getItem('user')) || null,
    token: localStorage.getItem('token') || null,
  }),

  getters: {
    isAuthenticated: (state) => !!state.token,
    userRole: (state) => state.user?.role,
    userPermissions: (state) => state.user?.permissions || [],
  },

  actions: {
    async login(email, password) {
      try {
        const response = await api.post('/login', { email, password });
        const { token, user } = response.data;
        
        this.token = token;
        this.user = user;
        
        localStorage.setItem('token', token);
        localStorage.setItem('user', JSON.stringify(user));
        
        return user;
      } catch (error) {
        throw error.response?.data || error;
      }
    },

    async register(userData) {
      try {
        const response = await api.post('/register', userData);
        const { token, user } = response.data;
        
        this.token = token;
        this.user = user;
        
        localStorage.setItem('token', token);
        localStorage.setItem('user', JSON.stringify(user));
        
        return user;
      } catch (error) {
        throw error.response?.data || error;
      }
    },

    async logout() {
      try {
        await api.post('/logout');
      } catch (error) {
        console.error('Logout error:', error);
      } finally {
        this.token = null;
        this.user = null;
        localStorage.removeItem('token');
        localStorage.removeItem('user');
      }
    },

    async fetchUser() {
      try {
        const response = await api.get('/me');
        this.user = response.data.user;
        localStorage.setItem('user', JSON.stringify(this.user));
        return this.user;
      } catch (error) {
        throw error.response?.data || error;
      }
    },
  },
});
```

---

### Angular Example

#### 1. Auth Service

```typescript
// src/app/services/auth.service.ts
import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { BehaviorSubject, Observable, tap } from 'rxjs';
import { environment } from '../environments/environment';

interface User {
  id: number;
  name: string;
  email: string;
  role: string;
  permissions: string[];
}

interface AuthResponse {
  message: string;
  user: User;
  token: string;
}

@Injectable({
  providedIn: 'root'
})
export class AuthService {
  private apiUrl = 'http://localhost:8000/api';
  private userSubject = new BehaviorSubject<User | null>(this.getUserFromStorage());
  public user$ = this.userSubject.asObservable();

  constructor(private http: HttpClient) {}

  login(email: string, password: string): Observable<AuthResponse> {
    return this.http.post<AuthResponse>(`${this.apiUrl}/login`, { email, password })
      .pipe(
        tap(response => {
          localStorage.setItem('token', response.token);
          localStorage.setItem('user', JSON.stringify(response.user));
          this.userSubject.next(response.user);
        })
      );
  }

  register(userData: any): Observable<AuthResponse> {
    return this.http.post<AuthResponse>(`${this.apiUrl}/register`, userData)
      .pipe(
        tap(response => {
          localStorage.setItem('token', response.token);
          localStorage.setItem('user', JSON.stringify(response.user));
          this.userSubject.next(response.user);
        })
      );
  }

  logout(): Observable<any> {
    return this.http.post(`${this.apiUrl}/logout`, {})
      .pipe(
        tap(() => {
          localStorage.removeItem('token');
          localStorage.removeItem('user');
          this.userSubject.next(null);
        })
      );
  }

  getToken(): string | null {
    return localStorage.getItem('token');
  }

  isAuthenticated(): boolean {
    return !!this.getToken();
  }

  private getUserFromStorage(): User | null {
    const userStr = localStorage.getItem('user');
    return userStr ? JSON.parse(userStr) : null;
  }
}
```

---

#### 2. HTTP Interceptor

```typescript
// src/app/interceptors/auth.interceptor.ts
import { Injectable } from '@angular/core';
import { HttpInterceptor, HttpRequest, HttpHandler, HttpEvent } from '@angular/common/http';
import { Observable } from 'rxjs';
import { AuthService } from '../services/auth.service';

@Injectable()
export class AuthInterceptor implements HttpInterceptor {
  constructor(private authService: AuthService) {}

  intercept(req: HttpRequest<any>, next: HttpHandler): Observable<HttpEvent<any>> {
    const token = this.authService.getToken();
    
    if (token) {
      req = req.clone({
        setHeaders: {
          Authorization: `Bearer ${token}`,
          Accept: 'application/json'
        }
      });
    }

    return next.handle(req);
  }
}
```

---

## 🔒 Permission System

The API returns user permissions as an array. Use these to control UI elements:

### Available Permissions:
- `view_leads` - Can view leads
- `create_leads` - Can create new leads
- `edit_leads` - Can edit leads
- `delete_leads` - Can delete leads
- `manage_users` - Can manage users
- `view_reports` - Can view reports

### Example Permission Check:

```javascript
// Check if user has permission
const hasPermission = (permission) => {
  const user = authService.getUser();
  return user?.permissions?.includes(permission) || false;
};

// Usage in React
{hasPermission('delete_leads') && (
  <button onClick={handleDelete}>Delete Lead</button>
)}

// Usage in Vue template
<button v-if="hasPermission('delete_leads')" @click="handleDelete">
  Delete Lead
</button>
```

---

## 🚨 Error Handling

### Common HTTP Status Codes:

| Status | Meaning | Action |
|--------|---------|--------|
| 200 | Success | Process response data |
| 201 | Created | Resource created successfully |
| 401 | Unauthorized | Redirect to login, clear token |
| 403 | Forbidden | Show "Access Denied" message |
| 422 | Validation Error | Display field errors to user |
| 500 | Server Error | Show generic error message |

### Error Response Format:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email field is required."],
    "password": ["The password must be at least 8 characters."]
  }
}
```

### Error Handling Example:

```javascript
try {
  await authService.login(email, password);
} catch (error) {
  if (error.errors) {
    // Validation errors (422)
    Object.keys(error.errors).forEach(field => {
      console.error(`${field}: ${error.errors[field].join(', ')}`);
    });
  } else if (error.message) {
    // Other errors
    console.error(error.message);
  }
}
```

---

## 📱 CORS Information

CORS is already configured on the backend to accept requests from any origin during development.

**Current CORS Settings:**
- Allowed Origins: `*` (all)
- Allowed Methods: `*` (all)
- Allowed Headers: `*` (all)

**Note:** These settings will need to be restricted in production.

---

## 🧪 Testing the API

### Using Postman:

1. **Import Collection:**
   - Create a new collection called "Lead CRM API"
   - Add base URL variable: `{{base_url}}` = `http://localhost:8000/api`

2. **Login Request:**
   ```
   POST {{base_url}}/login
   Body (JSON):
   {
     "email": "admin@crm.com",
     "password": "password"
   }
   ```

3. **Save Token:**
   Add to Tests tab:
   ```javascript
   pm.environment.set("token", pm.response.json().token);
   ```

4. **Use Token in Protected Routes:**
   Add to Headers:
   ```
   Authorization: Bearer {{token}}
   ```

---

### Using cURL:

**Login:**
```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email": "admin@crm.com", "password": "password"}'
```

**Get User (replace TOKEN):**
```bash
curl -X GET http://localhost:8000/api/me \
  -H "Authorization: Bearer TOKEN" \
  -H "Accept: application/json"
```

---

## 📊 Data Models Reference

### User Object
```typescript
{
  id: number;
  name: string;
  email: string;
  phone: string | null;
  role: string; // "Admin" | "Manager" | "Sales Agent"
  department: string; // "Management" | "Sales" | etc.
  status: string; // "Active" | "Inactive"
  permissions: string[]; // Array of permission strings
  avatar: string | null;
  last_active: string; // ISO 8601 datetime
  created_at: string; // ISO 8601 datetime
}
```

---

## 🔄 State Management Recommendations

### React:
- **Small Apps:** Context API
- **Medium Apps:** Zustand or Redux Toolkit
- **Large Apps:** Redux Toolkit

### Vue:
- **Vue 2:** Vuex
- **Vue 3:** Pinia (recommended)

### Angular:
- **Small/Medium Apps:** Services with RxJS
- **Large Apps:** NgRx

---

## ⚡ Performance Tips

1. **Store token in memory** for sensitive apps (instead of localStorage)
2. **Refresh token silently** before expiration
3. **Cache user data** to avoid repeated /me calls
4. **Use interceptors** for global auth handling
5. **Implement loading states** for better UX
6. **Debounce API calls** on search/filter inputs

---

## 🔐 Security Best Practices

1. ✅ Always use HTTPS in production
2. ✅ Never expose tokens in URLs or logs
3. ✅ Implement CSRF protection for web apps
4. ✅ Use httpOnly cookies for tokens (alternative to localStorage)
5. ✅ Implement token refresh mechanism
6. ✅ Clear tokens on logout
7. ✅ Validate user permissions on frontend AND backend
8. ✅ Implement rate limiting on login attempts

---

## 📞 Support & Next Steps

### Documentation Files:
- **API_DOCUMENTATION.md** - Complete API endpoint documentation
- **API_DEVELOPMENT_GUIDE.md** - Backend development guidelines
- **SETUP_COMPLETE.md** - Backend setup information

### Coming Soon (Backend will implement):
- Lead management endpoints (CRUD)
- Follow-up management endpoints
- Call log endpoints
- Notes endpoints
- Customer management endpoints
- Dashboard statistics endpoints
- User management endpoints (Admin only)

### Need Help?
- Check the API_DOCUMENTATION.md for detailed endpoint information
- Test endpoints with Postman before implementing in frontend
- Contact backend team for any API issues

---

## ✅ Checklist for Frontend Integration

- [ ] Setup API client (axios/fetch)
- [ ] Implement authentication service
- [ ] Create login component
- [ ] Create register component (if needed)
- [ ] Implement token storage
- [ ] Setup request interceptors (add token)
- [ ] Setup response interceptors (handle 401)
- [ ] Create protected route component
- [ ] Implement logout functionality
- [ ] Test with provided test accounts
- [ ] Implement permission checks in UI
- [ ] Handle validation errors (422)
- [ ] Add loading states
- [ ] Add error messages
- [ ] Test error scenarios

---

**🎉 Your backend is ready! Start building your frontend now!**

For any questions or issues, refer to the API_DOCUMENTATION.md file or contact the backend team.
# Frontend Integration Guide - Lead Management CRM API

## 📋 Quick Start

This guide provides everything you need to integrate your frontend application with the Lead Management CRM backend API.

---

## 🔗 API Base URL

```
http://localhost:8000/api
```

**Note:** Update this URL when deploying to production.

---

## 🔐 Authentication

This API uses **Bearer Token Authentication** via Laravel Sanctum.

### Authentication Flow

1. User logs in via `POST /api/login`
2. Backend returns a token
3. Store the token (localStorage/sessionStorage/cookie)
4. Include token in all subsequent requests
5. User logs out via `POST /api/logout` (optional - revokes token)

### Required Headers

**For All Requests:**
```javascript
{
  "Content-Type": "application/json",
  "Accept": "application/json"
}
```

**For Protected Routes (after login):**
```javascript
{
  "Content-Type": "application/json",
  "Accept": "application/json",
  "Authorization": "Bearer YOUR_TOKEN_HERE"
}
```

---

## 🎯 Available Endpoints

### Authentication Endpoints

#### 1. User Registration
**POST** `/api/register`

**Request Body:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "phone": "+1-555-123-4567",
  "role": "Sales Agent",
  "department": "Sales"
}
```

**Success Response (201):**
```json
{
  "message": "Registration successful",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "+1-555-123-4567",
    "role": "Sales Agent",
    "department": "Sales",
    "status": "Active",
    "permissions": ["view_leads", "create_leads", "edit_leads"]
  },
  "token": "1|abc123xyz..."
}
```

**Error Response (422):**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email has already been taken."],
    "password": ["The password confirmation does not match."]
  }
}
```

---

#### 2. User Login
**POST** `/api/login`

**Request Body:**
```json
{
  "email": "admin@crm.com",
  "password": "password"
}
```

**Success Response (200):**
```json
{
  "message": "Login successful",
  "user": {
    "id": 1,
    "name": "Admin User",
    "email": "admin@crm.com",
    "phone": "+1-555-100-0001",
    "role": "Admin",
    "department": "Management",
    "status": "Active",
    "permissions": [
      "view_leads",
      "create_leads",
      "edit_leads",
      "delete_leads",
      "manage_users",
      "view_reports"
    ],
    "avatar": null
  },
  "token": "1|abc123xyz..."
}
```

**Error Response (422):**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The provided credentials are incorrect."]
  }
}
```

**Error Response (403):**
```json
{
  "message": "Your account is not active. Please contact administrator."
}
```

---

#### 3. Get Current User
**GET** `/api/me`

**Headers Required:**
```
Authorization: Bearer {token}
```

**Success Response (200):**
```json
{
  "user": {
    "id": 1,
    "name": "Admin User",
    "email": "admin@crm.com",
    "phone": "+1-555-100-0001",
    "role": "Admin",
    "department": "Management",
    "status": "Active",
    "permissions": ["view_leads", "create_leads", "edit_leads", "delete_leads", "manage_users", "view_reports"],
    "avatar": null,
    "last_active": "2026-03-04T10:30:00.000000Z",
    "created_at": "2026-03-04T09:15:00.000000Z"
  }
}
```

**Error Response (401):**
```json
{
  "message": "Unauthenticated."
}
```

---

#### 4. User Logout
**POST** `/api/logout`

**Headers Required:**
```
Authorization: Bearer {token}
```

**Success Response (200):**
```json
{
  "message": "Logout successful"
}
```

---

## 👥 Test Accounts

Use these credentials for testing:

| Role | Email | Password | Permissions |
|------|-------|----------|-------------|
| **Admin** | admin@crm.com | password | Full access (view, create, edit, delete, manage users, reports) |
| **Manager** | sarah@crm.com | password | View, create, edit leads + reports |
| **Sales Agent** | john@crm.com | password | View, create, edit leads |
| **Sales Agent** | emily@crm.com | password | View, create, edit leads |

---

## 💻 Frontend Implementation Examples

### React/JavaScript Example

#### 1. Setup Axios Instance

```javascript
// src/api/axios.js
import axios from 'axios';

const api = axios.create({
  baseURL: 'http://localhost:8000/api',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

// Add token to requests if available
api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

// Handle 401 errors (unauthorized)
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      // Clear token and redirect to login
      localStorage.removeItem('token');
      localStorage.removeItem('user');
      window.location.href = '/login';
    }
    return Promise.reject(error);
  }
);

export default api;
```

---

#### 2. Authentication Service

```javascript
// src/services/authService.js
import api from '../api/axios';

export const authService = {
  // Login
  async login(email, password) {
    try {
      const response = await api.post('/login', { email, password });
      const { token, user } = response.data;
      
      // Store token and user data
      localStorage.setItem('token', token);
      localStorage.setItem('user', JSON.stringify(user));
      
      return { token, user };
    } catch (error) {
      throw error.response?.data || error.message;
    }
  },

  // Register
  async register(userData) {
    try {
      const response = await api.post('/register', userData);
      const { token, user } = response.data;
      
      // Store token and user data
      localStorage.setItem('token', token);
      localStorage.setItem('user', JSON.stringify(user));
      
      return { token, user };
    } catch (error) {
      throw error.response?.data || error.message;
    }
  },

  // Get current user
  async getCurrentUser() {
    try {
      const response = await api.get('/me');
      const { user } = response.data;
      
      // Update stored user data
      localStorage.setItem('user', JSON.stringify(user));
      
      return user;
    } catch (error) {
      throw error.response?.data || error.message;
    }
  },

  // Logout
  async logout() {
    try {
      await api.post('/logout');
    } catch (error) {
      console.error('Logout error:', error);
    } finally {
      // Clear local storage regardless of API response
      localStorage.removeItem('token');
      localStorage.removeItem('user');
    }
  },

  // Check if user is authenticated
  isAuthenticated() {
    return !!localStorage.getItem('token');
  },

  // Get stored user data
  getUser() {
    const userStr = localStorage.getItem('user');
    return userStr ? JSON.parse(userStr) : null;
  },
};
```

---

#### 3. Login Component Example

```jsx
// src/components/Login.jsx
import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { authService } from '../services/authService';

const Login = () => {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const navigate = useNavigate();

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setLoading(true);

    try {
      const { user } = await authService.login(email, password);
      console.log('Login successful:', user);
      navigate('/dashboard');
    } catch (err) {
      setError(err.message || 'Login failed');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="login-container">
      <h2>Login to Lead CRM</h2>
      
      {error && <div className="error-message">{error}</div>}
      
      <form onSubmit={handleSubmit}>
        <div className="form-group">
          <label>Email:</label>
          <input
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            required
            placeholder="admin@crm.com"
          />
        </div>

        <div className="form-group">
          <label>Password:</label>
          <input
            type="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
            placeholder="password"
          />
        </div>

        <button type="submit" disabled={loading}>
          {loading ? 'Logging in...' : 'Login'}
        </button>
      </form>

      {/* Test credentials hint */}
      <div className="test-hint">
        <p><strong>Test Account:</strong></p>
        <p>Email: admin@crm.com</p>
        <p>Password: password</p>
      </div>
    </div>
  );
};

export default Login;
```

---

#### 4. Protected Route Component

```jsx
// src/components/ProtectedRoute.jsx
import React from 'react';
import { Navigate } from 'react-router-dom';
import { authService } from '../services/authService';

const ProtectedRoute = ({ children }) => {
  if (!authService.isAuthenticated()) {
    return <Navigate to="/login" replace />;
  }

  return children;
};

export default ProtectedRoute;
```

---

#### 5. Auth Context (Optional - for global state)

```jsx
// src/context/AuthContext.jsx
import React, { createContext, useContext, useState, useEffect } from 'react';
import { authService } from '../services/authService';

const AuthContext = createContext();

export const useAuth = () => {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within AuthProvider');
  }
  return context;
};

export const AuthProvider = ({ children }) => {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    // Check if user is logged in on mount
    const storedUser = authService.getUser();
    if (storedUser) {
      setUser(storedUser);
    }
    setLoading(false);
  }, []);

  const login = async (email, password) => {
    const { user } = await authService.login(email, password);
    setUser(user);
    return user;
  };

  const register = async (userData) => {
    const { user } = await authService.register(userData);
    setUser(user);
    return user;
  };

  const logout = async () => {
    await authService.logout();
    setUser(null);
  };

  const value = {
    user,
    login,
    register,
    logout,
    isAuthenticated: !!user,
    loading,
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
};
```

---

### Vue.js Example

#### 1. Setup Axios

```javascript
// src/plugins/axios.js
import axios from 'axios';

const api = axios.create({
  baseURL: 'http://localhost:8000/api',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

// Request interceptor

