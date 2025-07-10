# API Routes Documentation

## Overview

This document describes the updated API routes with role-based authorization, multi-tenant support, and production-ready configuration.

## Route Structure

### 🔓 **Public Routes** (No Authentication Required)
```
POST /api/login                    - User login
POST /api/register                 - User registration
POST /api/forgot-password          - Password reset request
POST /api/update-password          - Password reset confirmation
GET  /api/health                   - Health check endpoint
```

### 🔐 **Admin-Only Routes** (Authentication + Admin Role Required)
```
GET    /api/users                  - List all users
POST   /api/users                  - Create new user
PUT    /api/users/{id}             - Update user
DELETE /api/users/{id}             - Delete user
```

### 🔒 **Protected Routes** (Authentication Required for All Users)
```
# Authentication
POST /api/logout                   - User logout
POST /api/change-password          - Change password

# User Profile
GET  /api/user                     - Get authenticated user profile

# Product Management (Multi-tenant)
GET    /api/products               - List user's products
POST   /api/products               - Create new product
GET    /api/products/{id}          - Get specific product
PUT    /api/products/{id}          - Update product
DELETE /api/products/{id}          - Delete product

# Sales Management (Multi-tenant)
POST   /api/sales                  - Create new sale
GET    /api/sales                  - Get sales history
GET    /api/sales/{id}             - Get specific sale
DELETE /api/sales/{id}             - Delete sale
```

## Authorization Levels

### **Admin Users** (role_id = 2)
- ✅ Access to all endpoints
- ✅ Can manage all users (CRUD operations)
- ✅ Can access their own products and sales
- ✅ Full system administration capabilities

### **Normal Users** (role_id = 1)
- ✅ Access to authentication endpoints
- ✅ Can manage their own profile
- ✅ Can manage their own products (multi-tenant)
- ✅ Can manage their own sales (multi-tenant)
- ❌ Cannot access user management endpoints

### **Unauthenticated Users**
- ✅ Can access public authentication endpoints
- ✅ Can access health check endpoint
- ❌ Cannot access any protected endpoints

## Middleware Configuration

### **isAdmin Middleware**
```php
// Checks if user has role_id = 2 (Admin)
if (!$user || $user->role_id !== 2) {
    return response()->json([
        'message' => 'Unauthorized: Only admins can perform this action.'
    ], 403);
}
```

### **auth:sanctum Middleware**
- Ensures user is authenticated via Laravel Sanctum
- Provides access to `$request->user()`
- Returns 401 for unauthenticated requests

## Production Features

### ✅ **Security Measures**
- **Role-based Authorization**: Admin-only routes properly protected
- **Multi-tenant Isolation**: Users can only access their own data
- **Input Validation**: All endpoints have proper validation
- **Error Handling**: Consistent error responses without sensitive data exposure
- **Rate Limiting**: Configured via `ThrottleRequests` middleware

### ✅ **Monitoring & Health**
- **Health Check Endpoint**: `/api/health` for production monitoring
- **Fallback Route**: Proper 404 responses for undefined endpoints
- **Consistent Response Format**: Standardized JSON responses

### ✅ **Performance Optimizations**
- **Eager Loading**: Proper relationship loading in controllers
- **Query Optimization**: User-scoped queries for multi-tenancy
- **Pagination**: Implemented for large datasets
- **Caching Ready**: Structure supports future caching implementation

## API Response Format

### **Success Response**
```json
{
    "message": "Operation completed successfully",
    "data": { ... },
    "pagination": { ... } // if applicable
}
```

### **Error Response**
```json
{
    "message": "Error description",
    "error": "Detailed error information" // in development only
}
```

### **Authorization Error**
```json
{
    "message": "Unauthorized: Only admins can perform this action."
}
```

## Usage Examples

### **Admin Creating a User**
```bash
curl -X POST /api/users \
  -H "Authorization: Bearer {admin_token}" \
  -H "Content-Type: application/json" \
  -d '{
    "user": "newuser",
    "name": "New User",
    "email": "newuser@example.com",
    "password": "password123",
    "password_confirmation": "password123",
    "role_id": 1
  }'
```

### **Normal User Creating a Product**
```bash
curl -X POST /api/products \
  -H "Authorization: Bearer {user_token}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Sample Product",
    "price": 29.99,
    "unit_cost": 15.00
  }'
```

### **Normal User Trying to Access Admin Endpoint**
```bash
curl -X GET /api/users \
  -H "Authorization: Bearer {normal_user_token}"
```
**Response**: 403 Unauthorized

## Security Checklist

- ✅ **Authentication**: All protected routes require valid tokens
- ✅ **Authorization**: Admin routes properly protected
- ✅ **Input Validation**: All endpoints validate input data
- ✅ **Multi-tenancy**: Users isolated to their own data
- ✅ **Error Handling**: No sensitive data in error responses
- ✅ **Rate Limiting**: API throttling configured
- ✅ **CORS**: Properly configured for cross-origin requests
- ✅ **HTTPS**: Ready for HTTPS enforcement
- ✅ **No Debug Routes**: Production-safe endpoints only

## Testing Scenarios

### **Authentication Tests**
1. ✅ Unauthenticated access to protected routes returns 401
2. ✅ Valid tokens provide access to appropriate endpoints
3. ✅ Invalid tokens are properly rejected

### **Authorization Tests**
1. ✅ Normal users cannot access admin endpoints
2. ✅ Admin users can access all endpoints
3. ✅ Role-based restrictions work correctly

### **Multi-tenant Tests**
1. ✅ Users can only access their own products
2. ✅ Users can only access their own sales
3. ✅ Data isolation works correctly

### **Production Tests**
1. ✅ Health check endpoint responds correctly
2. ✅ Undefined endpoints return proper 404
3. ✅ Error responses don't expose sensitive information
4. ✅ Rate limiting works as expected

## Maintenance Notes

### **Adding New Routes**
1. Determine appropriate authorization level
2. Add to correct middleware group
3. Update this documentation
4. Test with different user roles

### **Modifying Authorization**
1. Update `IsAdmin` middleware if role IDs change
2. Test all affected endpoints
3. Update documentation
4. Verify production deployment

### **Monitoring**
- Use `/api/health` for uptime monitoring
- Monitor 403/401 responses for security issues
- Track API usage patterns
- Monitor rate limiting effectiveness 