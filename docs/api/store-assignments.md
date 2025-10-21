# Store User Assignments API

This API manages user assignments to stores, including roles and permissions within each store.

## Endpoints

### Get Store Assignments

Get all user assignments for a specific store.

```http
GET /api/v1/store-assignments/stores/{store_id}
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": "uuid",
      "store_id": "uuid",
      "user_id": 1,
      "assignment_role": "owner",
      "is_primary": true,
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-01T00:00:00Z",
      "store": {
        "id": "uuid",
        "name": "Store Name",
        "code": "STORE001",
        "address": "Store Address"
      },
      "user": {
        "id": 1,
        "name": "User Name",
        "email": "user@example.com"
      },
      "role_display": "Store Owner",
      "assignment_status": "Primary Assignment"
    }
  ]
}
```

### Create Store Assignment

Assign a user to a store with a specific role.

```http
POST /api/v1/store-assignments
```

**Request Body:**
```json
{
  "store_id": "uuid",
  "user_id": 1,
  "assignment_role": "staff",
  "is_primary": false
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": "uuid",
    "store_id": "uuid",
    "user_id": 1,
    "assignment_role": "staff",
    "is_primary": false,
    "created_at": "2024-01-01T00:00:00Z",
    "updated_at": "2024-01-01T00:00:00Z"
  },
  "message": "User assigned to store successfully"
}
```

### Update Store Assignment

Update an existing store assignment.

```http
PUT /api/v1/store-assignments/{assignment_id}
```

**Request Body:**
```json
{
  "assignment_role": "manager",
  "is_primary": true
}
```

### Delete Store Assignment

Remove a user from a store.

```http
DELETE /api/v1/store-assignments/{assignment_id}
```

**Response:**
```json
{
  "success": true,
  "message": "User removed from store successfully"
}
```

### Get User Stores

Get all stores assigned to a specific user.

```http
GET /api/v1/store-assignments/users/{user_id}/stores
```

### Set Primary Store

Set a store as the primary store for a user.

```http
POST /api/v1/store-assignments/users/{user_id}/primary-store
```

**Request Body:**
```json
{
  "store_id": "uuid"
}
```

## Assignment Roles

The system supports the following assignment roles:

- **staff**: Basic staff member with limited permissions
- **manager**: Store manager with operational permissions
- **admin**: Store administrator with advanced permissions
- **owner**: Store owner with full permissions

## Role Hierarchy

Roles have a hierarchy system where higher-level roles can manage lower-level roles:

1. **Owner** (Level 4) - Can manage all other roles
2. **Admin** (Level 3) - Can manage Manager and Staff
3. **Manager** (Level 2) - Can manage Staff only
4. **Staff** (Level 1) - Cannot manage other roles

## Permissions by Role

### Owner
- All permissions (*)

### Admin
- Products: view, create, update, delete
- Orders: view, create, update, delete
- Inventory: view, update
- Reports: view
- Staff: view, create, update
- Members: all operations
- Tables: all operations
- Categories: all operations
- Discounts: all operations
- Payments: all operations

### Manager
- Products: view, create, update
- Orders: all operations
- Inventory: view, update
- Reports: view
- Staff: view only
- Members: all operations
- Tables: all operations
- Categories: view only
- Discounts: view only
- Payments: view, create

### Staff
- Products: view only
- Orders: view, create, update
- Inventory: view only
- Members: view, create
- Tables: view, update
- Payments: view, create

## Business Rules

1. **Unique Assignments**: A user can only have one assignment per store
2. **Primary Store**: Each user can have only one primary store assignment
3. **Role Management**: Users can only manage assignments with lower hierarchy levels
4. **Owner Protection**: Primary owner assignments cannot be deleted
5. **Admin System Override**: Users with 'admin_sistem' role bypass all store-level restrictions

## Error Responses

### 409 Conflict
```json
{
  "success": false,
  "message": "User is already assigned to this store"
}
```

### 403 Forbidden
```json
{
  "success": false,
  "message": "Insufficient permissions for this action"
}
```

### 404 Not Found
```json
{
  "success": false,
  "message": "User is not assigned to this store"
}
```