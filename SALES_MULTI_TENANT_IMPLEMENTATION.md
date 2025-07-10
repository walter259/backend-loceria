# Multi-Tenant Sales Management Implementation

## Overview

This implementation converts the Laravel SalesController to support multi-tenancy where each user has their own company/business. Each user can only create sales with their own products and access their own sales data.

## Key Features

### ✅ **Multi-Tenant Security**
- Users can only sell products they own
- Users can only view their own sales
- Users can only delete their own sales
- Complete data isolation between users

### ✅ **Product Ownership Validation**
- Validates that all products in a sale belong to the authenticated user
- Prevents unauthorized sales of other users' products
- Maintains transaction integrity for multi-product sales

### ✅ **Authorization System**
- Uses Laravel Policies for robust authorization
- Comprehensive permission checks for all operations
- Clear error messages for unauthorized access

## Changes Made

### 1. SalePolicy for Authorization
- **File**: `app/Policies/SalePolicy.php`
- **Purpose**: Handles all authorization logic for sale operations
- **Methods**:
  - `viewAny()`: Users can view their own sales
  - `view()`: Users can only view their own sales
  - `create()`: Authenticated users can create sales
  - `createWithProducts()`: Validates product ownership before sale creation
  - `delete()`: Users can only delete their own sales

### 2. Sale Model Updates
- **File**: `app/Models/Sale.php`
- **Changes**:
  - Added `scopeForUser()` query scope for cleaner queries
  - Maintained existing relationships and utility calculations
  - Enhanced scoping for multi-tenant operations

### 3. SalesController Multi-Tenant Implementation
- **File**: `app/Http/Controllers/SaleController.php`
- **Key Features**:
  - **CREATE**: Validates product ownership before creating sales
  - **READ**: Only shows sales belonging to authenticated user
  - **DELETE**: Only allows deleting user's own sales
  - **Authorization**: Uses Laravel's built-in authorization system
  - **Error Handling**: Comprehensive error handling for all scenarios
  - **Transaction Integrity**: Maintains data consistency for multi-product sales

### 4. AuthServiceProvider Registration
- **File**: `app/Providers/AuthServiceProvider.php`
- **Purpose**: Registers SalePolicy with Laravel's authorization system

## API Endpoints

All endpoints require authentication via Laravel Sanctum tokens.

### POST /api/sales
- **Purpose**: Create a new sale for authenticated user
- **Body**: Array of products with product_id and quantity
- **Security**: 
  - Validates that all products belong to authenticated user
  - Uses transaction for data consistency
  - Comprehensive error handling
- **Response**: Created sales with summary totals

### GET /api/sales
- **Purpose**: Get sales history for authenticated user
- **Features**:
  - Date range filtering (start_date, end_date)
  - Pagination support
  - Only shows user's own sales
- **Query Parameters**:
  - `start_date`: Filter sales from this date
  - `end_date`: Filter sales until this date
  - `per_page`: Number of items per page (default: 15)

### GET /api/sales/{id}
- **Purpose**: Get specific sale details
- **Security**: Only returns sales belonging to authenticated user
- **Authorization**: Uses `view` policy method

### DELETE /api/sales/{id}
- **Purpose**: Delete a sale
- **Security**: Only allows deleting user's own sales
- **Authorization**: Uses `delete` policy method

## Security Features

### 1. Product Ownership Validation
```php
// Verify that all products belong to the authenticated user
$userProducts = Product::where('user_id', $user->id)
                      ->whereIn('id', $productIds)
                      ->get()
                      ->keyBy('id');

// Check if all requested products belong to the user
if ($userProducts->count() !== $productIds->count()) {
    return response()->json([
        'message' => 'Some products do not belong to you or do not exist',
        'error' => 'Unauthorized to sell products that do not belong to you',
    ], 403);
}
```

### 2. User Scoping
- All queries are scoped to the authenticated user
- Uses `Sale::forUser($request->user()->id)` pattern
- Leverages `scopeForUser()` for cleaner queries

### 3. Authorization Checks
- Every operation checks authorization via policies
- Uses `$this->authorize()` method for consistent authorization
- Proper error responses for unauthorized access

### 4. Transaction Integrity
- Uses database transactions for multi-product sales
- Ensures all-or-nothing operations
- Proper rollback on errors

## Database Schema

```sql
CREATE TABLE sales (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    utility DECIMAL(10,2) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX (user_id),
    INDEX (product_id)
);
```

## Usage Examples

### Creating a Sale
```bash
curl -X POST /api/sales \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "products": [
      {
        "product_id": 1,
        "quantity": 2
      },
      {
        "product_id": 3,
        "quantity": 1
      }
    ]
  }'
```

**Response**:
```json
{
  "message": "Sale registered successfully",
  "sales": [
    {
      "id": 1,
      "product_id": 1,
      "quantity": 2,
      "unit_price": "29.99",
      "total": "59.98",
      "utility": "30.00",
      "created_at": "2025-01-27T10:00:00.000000Z"
    }
  ],
  "summary": {
    "total_income": 89.97,
    "total_cost": 45.00,
    "total_utility": 44.97,
    "items_count": 2
  }
}
```

### Getting Sales History
```bash
curl -X GET "/api/sales?start_date=2025-01-01&end_date=2025-01-31&per_page=10" \
  -H "Authorization: Bearer {token}"
```

### Deleting a Sale
```bash
curl -X DELETE /api/sales/1 \
  -H "Authorization: Bearer {token}"
```

## Error Handling

### 1. Unauthorized Product Access
```json
{
  "message": "Some products do not belong to you or do not exist",
  "error": "Unauthorized to sell products that do not belong to you"
}
```

### 2. Sale Not Found
```json
{
  "message": "Sale not found or you do not have permission to access it"
}
```

### 3. Validation Errors
```json
{
  "message": "Validation failed",
  "errors": {
    "products.0.quantity": ["The quantity must be at least 1."]
  }
}
```

## Business Logic Maintained

### ✅ **Utility Calculations**
- Maintains existing utility calculation logic
- Calculates total income, cost, and utility
- Provides summary totals for multi-product sales

### ✅ **Transaction Integrity**
- Uses database transactions for consistency
- Ensures all-or-nothing operations
- Proper error handling and rollback

### ✅ **Pagination and Filtering**
- Maintains existing pagination functionality
- Supports date range filtering
- Efficient query optimization

### ✅ **Relationships**
- Maintains existing User and Product relationships
- Proper eager loading for performance
- Clean data transformation

## Testing Scenarios

1. **Authentication**: Ensure all endpoints require valid tokens
2. **Product Ownership**: Verify users can only sell their own products
3. **Sales Access**: Verify users can only access their own sales
4. **Multi-Product Sales**: Test transaction integrity
5. **Error Handling**: Test all error scenarios
6. **Data Isolation**: Test with multiple users

## Benefits

1. **Data Security**: Complete isolation between users
2. **Business Logic**: Maintains existing utility calculations
3. **Performance**: Efficient queries with proper scoping
4. **Scalability**: Supports multi-product sales efficiently
5. **Maintainability**: Clean, well-documented code
6. **Laravel Best Practices**: Uses built-in authorization and relationships

## Integration with Product Multi-Tenancy

This sales implementation works seamlessly with the multi-tenant ProductController:

- Sales can only be created with products owned by the authenticated user
- Product ownership is validated before sale creation
- Maintains referential integrity between products and sales
- Supports the complete multi-tenant business workflow 