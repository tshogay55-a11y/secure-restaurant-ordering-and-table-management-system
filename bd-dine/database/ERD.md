# BD Dine Restaurant - Entity Relationship Diagram (ERD)

## Database Overview
This database supports a secure restaurant ordering and table management system with encryption, 2FA, and comprehensive auditing.

## Entity Relationships

### 1. USERS (Customer Accounts)
**Primary Key:** user_id
**Relationships:**
- One-to-Many with BOOKINGS (one user can have multiple bookings)
- One-to-Many with ORDERS (one user can have multiple orders)
- One-to-Many with TWO_FACTOR_CODES (one user can have multiple 2FA codes)
- One-to-Many with SECURE_SESSIONS (one user can have multiple sessions)
- One-to-Many with AUDIT_LOG (one user can have multiple audit entries)

**Key Fields:**
- email (UNIQUE, encrypted in storage)
- password_hash (bcrypt hashed)
- phone (for 2FA SMS)
- encryption_key (for data encryption)

---

### 2. ADMIN_USERS
**Primary Key:** admin_id
**Relationships:**
- One-to-Many with TWO_FACTOR_CODES
- One-to-Many with SECURE_SESSIONS
- One-to-Many with AUDIT_LOG

**Key Fields:**
- username (UNIQUE)
- role (super_admin, manager, staff)
- password_hash

---

### 3. BOOKINGS (Table Reservations)
**Primary Key:** booking_id
**Foreign Keys:**
- user_id → USERS
**Relationships:**
- Many-to-One with USERS
- One-to-Many with ORDERS
- One-to-Many with PAYMENT_TRANSACTIONS

**Key Fields:**
- booking_date, booking_time
- table_number
- encrypted_data (stores sensitive booking info)
- payment_status

---

### 4. ORDERS
**Primary Key:** order_id
**Foreign Keys:**
- user_id → USERS
- booking_id → BOOKINGS (optional)
**Relationships:**
- Many-to-One with USERS
- Many-to-One with BOOKINGS (optional)
- One-to-Many with ORDER_ITEMS
- One-to-Many with PAYMENT_TRANSACTIONS

**Key Fields:**
- total_amount
- encrypted_payment_data
- order_status

---

### 5. ORDER_ITEMS
**Primary Key:** order_item_id
**Foreign Keys:**
- order_id → ORDERS
- item_id → MENU_ITEMS
**Relationships:**
- Many-to-One with ORDERS
- Many-to-One with MENU_ITEMS

---

### 6. MENU_ITEMS
**Primary Key:** item_id
**Foreign Keys:**
- category_id → MENU_CATEGORIES
**Relationships:**
- Many-to-One with MENU_CATEGORIES
- One-to-Many with ORDER_ITEMS

**Key Fields:**
- price (AUD)
- dietary flags (vegetarian, vegan, gluten_free)
- allergen_info

---

### 7. MENU_CATEGORIES
**Primary Key:** category_id
**Relationships:**
- One-to-Many with MENU_ITEMS

---

### 8. RESTAURANT_TABLES
**Primary Key:** table_id
**Key Fields:**
- table_number (UNIQUE)
- capacity
- is_available (real-time availability)

---

### 9. TWO_FACTOR_CODES
**Primary Key:** code_id
**Foreign Keys:**
- user_id → USERS (nullable)
- admin_id → ADMIN_USERS (nullable)
**Relationships:**
- Many-to-One with USERS or ADMIN_USERS

**Key Fields:**
- code (6-digit SMS code)
- expires_at (5-minute expiration)
- verified (boolean)

---

### 10. SECURE_SESSIONS
**Primary Key:** session_id (128-char hash)
**Foreign Keys:**
- user_id → USERS (nullable)
- admin_id → ADMIN_USERS (nullable)
**Relationships:**
- Many-to-One with USERS or ADMIN_USERS

**Key Fields:**
- session_data (encrypted)
- ip_address (for security validation)
- expires_at
- is_valid

---

### 11. PAYMENT_TRANSACTIONS
**Primary Key:** transaction_id
**Foreign Keys:**
- order_id → ORDERS (nullable)
- booking_id → BOOKINGS (nullable)
**Relationships:**
- Many-to-One with ORDERS or BOOKINGS

**Key Fields:**
- amount (AUD)
- encrypted_card_data (PCI-compliant encryption)
- transaction_reference (from payment gateway)
- payment_status

---

### 12. AUDIT_LOG
**Primary Key:** log_id
**Foreign Keys:**
- user_id → USERS (nullable)
- admin_id → ADMIN_USERS (nullable)
**Relationships:**
- Many-to-One with USERS or ADMIN_USERS

**Purpose:** Security auditing and compliance
**Key Fields:**
- action (login, booking, payment, etc.)
- ip_address
- details (JSON-encoded event data)

---

## Security Features

### Data Encryption
- User emails and phone numbers encrypted at rest
- Booking special requests encrypted
- Payment card data encrypted with AES-256
- Session data encrypted

### Session Management
- Secure session IDs (128-character hash)
- IP address validation
- User agent tracking
- Automatic expiration
- Session invalidation on logout

### Two-Factor Authentication
- SMS-based verification codes
- 6-digit codes with 5-minute expiration
- Rate limiting on code generation
- Separate 2FA for users and admins

### Audit Trail
- All critical actions logged
- IP address and user agent captured
- Timestamps for compliance
- Soft deletion support

---

## Cardinality Summary

```
USERS (1) ──────< (∞) BOOKINGS
USERS (1) ──────< (∞) ORDERS
USERS (1) ──────< (∞) TWO_FACTOR_CODES
USERS (1) ──────< (∞) SECURE_SESSIONS

ADMIN_USERS (1) ──────< (∞) TWO_FACTOR_CODES
ADMIN_USERS (1) ──────< (∞) SECURE_SESSIONS

BOOKINGS (1) ──────< (∞) PAYMENT_TRANSACTIONS
ORDERS (1) ──────< (∞) ORDER_ITEMS
ORDERS (1) ──────< (∞) PAYMENT_TRANSACTIONS

MENU_CATEGORIES (1) ──────< (∞) MENU_ITEMS
MENU_ITEMS (1) ──────< (∞) ORDER_ITEMS
```

---

## Indexes for Performance

All tables have appropriate indexes on:
- Primary keys (automatic)
- Foreign keys
- Frequently queried columns (email, phone, booking_date, etc.)
- Status fields for filtering
- Timestamp fields for range queries

This ensures optimal query performance even with large datasets.
