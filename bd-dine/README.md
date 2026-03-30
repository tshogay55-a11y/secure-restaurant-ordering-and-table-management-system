# BD Dine Restaurant - Secure Table Management System

## 🍽️ Overview

BD Dine Restaurant is a comprehensive, production-ready restaurant ordering and table management system built with security as the top priority. Located in Canberra, Australia, this system features advanced security measures including two-factor authentication, encrypted data storage, and secure session management.

## ✨ Features

### Security Features
- **Two-Factor Authentication (2FA)**: SMS-based verification for both customers and admins
- **Data Encryption**: AES-256-GCM encryption for sensitive data
- **Secure Sessions**: Session validation with IP address tracking and automatic expiration
- **Password Security**: Bcrypt hashing with complexity validation
- **Audit Trail**: Comprehensive logging of all system activities
- **CSRF Protection**: Built-in token validation for forms
- **SQL Injection Prevention**: Prepared statements throughout

### User Features
- **Customer Registration & Login**: Secure account creation with email verification
- **Table Reservations**: Easy-to-use booking system with time slot selection
- **Menu Browsing**: Full menu with categories, pricing (AUD), and images
- **Profile Management**: Update personal information and view booking history
- **Responsive Design**: Mobile-friendly interface with light/dark theme toggle

### Admin Features
- **Admin Dashboard**: Real-time overview of bookings, users, and revenue
- **User Management**: View and manage customer accounts
- **Booking Management**: Approve, modify, or cancel reservations
- **Menu Management**: Add, edit, or remove menu items
- **Table Management**: Configure table capacity and availability
- **Analytics**: View booking trends and revenue reports
- **Audit Logs**: Track all system activities for security compliance

### Technical Features
- **Database**: MySQL with optimized schema and indexes
- **Frontend**: HTML/CSS/JavaScript with modern design
- **Backend**: PHP with PDO for secure database access
- **Payment Integration**: Ready for Stripe/PayPal integration
- **Session Management**: Encrypted session storage with automatic cleanup
- **API Architecture**: RESTful endpoints for all operations

## 🚀 Installation

### Prerequisites
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- SSL certificate (for production)

### Step 1: Database Setup

1. Create the database:
```bash
mysql -u root -p < database/schema.sql
```

2. Verify database creation:
```bash
mysql -u root -p
USE bd_dine_restaurant;
SHOW TABLES;
```

### Step 2: Configuration

1. Update database credentials in `config/database.php`:
```php
private $host = 'localhost';
private $db_name = 'bd_dine_restaurant';
private $username = 'your_db_username';
private $password = 'your_db_password';
```

2. Generate a secure encryption key:
```bash
php -r "echo bin2hex(random_bytes(16));"
```
Update `ENCRYPTION_KEY` in `config/database.php` with the generated key.

3. Configure SMS API (for 2FA):
- Sign up for an SMS provider (Twilio, AWS SNS, etc.)
- Update SMS settings in `config/database.php`:
```php
define('SMS_API_KEY', 'your-sms-api-key');
define('SMS_API_URL', 'https://api.sms-provider.com/send');
define('SMS_FROM_NUMBER', '+61412345678');
```

4. Configure Payment Gateway (Stripe example):
```php
define('STRIPE_PUBLIC_KEY', 'pk_test_your_key');
define('STRIPE_SECRET_KEY', 'sk_test_your_key');
```

### Step 3: File Permissions

Set proper permissions for security:
```bash
chmod 755 /path/to/bd-dine-restaurant
chmod 644 /path/to/bd-dine-restaurant/config/*.php
chmod 755 /path/to/bd-dine-restaurant/api
chmod 755 /path/to/bd-dine-restaurant/admin
```

Create logs directory:
```bash
mkdir logs
chmod 755 logs
```

### Step 4: Web Server Configuration

#### Apache (.htaccess)
```apache
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

<FilesMatch "\.(php|inc)$">
    Order allow,deny
    Allow from all
</FilesMatch>

<Files "*.php">
    php_flag display_errors off
    php_value error_log /path/to/logs/php_errors.log
</Files>
```

#### Nginx
```nginx
server {
    listen 80;
    server_name yourdomain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl;
    server_name yourdomain.com;
    root /path/to/bd-dine-restaurant;
    index index.html index.php;

    ssl_certificate /path/to/certificate.crt;
    ssl_certificate_key /path/to/private.key;

    location / {
        try_files $uri $uri/ =404;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
    }
}
```

### Step 5: Testing

1. Access the website: `https://yourdomain.com`
2. Test admin login:
   - URL: `https://yourdomain.com/admin-login.html`
   - Default credentials: `admin` / `Admin@123`
3. Create a test customer account
4. Make a test booking

## 📊 Database Schema

### Key Tables

**users**: Customer accounts with encrypted data
- Stores: email (encrypted), password_hash, phone, encryption_key
- Indexes: email, phone for fast lookups

**admin_users**: Administrative accounts
- Roles: super_admin, manager, staff
- Separate authentication flow from customers

**bookings**: Table reservations
- Encrypted special requests
- Payment integration ready
- Status tracking (pending, confirmed, cancelled, completed)

**secure_sessions**: Session management
- Encrypted session data
- IP address validation
- Automatic expiration

**two_factor_codes**: 2FA verification
- 6-digit codes with 5-minute expiration
- Separate for users and admins
- Automatic cleanup of expired codes

**audit_log**: Security auditing
- Tracks all critical actions
- IP address and user agent logging
- Compliance-ready

See `database/ERD.md` for complete entity relationship diagram.

## 🔐 Security Features Explained

### Two-Factor Authentication (2FA)

1. **Login Flow**:
   - User enters email/password
   - System validates credentials
   - 6-digit code sent to registered phone
   - User enters code to complete login

2. **SMS Integration**:
   - Configured in `config/database.php`
   - Uses industry-standard SMS APIs
   - Codes expire after 5 minutes

3. **Admin 2FA**:
   - Separate authentication flow
   - Higher security requirements
   - Additional IP validation

### Data Encryption

**AES-256-GCM Encryption**:
- All sensitive data encrypted at rest
- User emails, phone numbers, payment data
- Individual encryption keys per user

**Implementation**:
```php
$encryption = new Encryption();
$encryptedData = $encryption->encrypt($sensitiveData);
$decryptedData = $encryption->decrypt($encryptedData);
```

### Session Management

**Secure Sessions**:
- Custom session handling (not PHP default)
- Session IDs: 128-character hash
- Encrypted session data storage
- IP address validation
- User agent verification
- Automatic expiration (1 hour default)

**Session Validation**:
```php
$security = new Security($database);
$session = $security->validateSession($sessionId);
if ($session) {
    // Session valid, proceed
} else {
    // Session invalid, redirect to login
}
```

### Password Security

**Requirements**:
- Minimum 8 characters
- Uppercase letter
- Lowercase letter
- Number
- Special character

**Storage**: Bcrypt with cost factor 12

## 🎨 Theme System

### Light/Dark Mode

The system includes a sophisticated theme toggle:

**Implementation**:
```javascript
// Theme persists across sessions
localStorage.setItem('theme', 'dark');
document.documentElement.setAttribute('data-theme', 'dark');
```

**CSS Variables**:
- All colors defined as CSS custom properties
- Smooth transitions between themes
- Consistent across all pages

**Color Palette**:
- Light theme: Warm, earthy tones (#FFFBF5, #D47318)
- Dark theme: Elegant, modern (#1A1410, #E8A871)

## 📱 API Endpoints

### Authentication
- `POST /api/login.php` - User login (step 1)
- `POST /api/verify-2fa.php` - Verify 2FA code (step 2)
- `POST /api/register.php` - User registration
- `POST /api/admin-login.php` - Admin login
- `POST /api/logout.php` - Logout

### Bookings
- `POST /api/booking.php` - Create booking
- `GET /api/bookings.php` - Get user bookings
- `PUT /api/booking.php?id=X` - Update booking
- `DELETE /api/booking.php?id=X` - Cancel booking

### Admin
- `GET /admin/users.php` - List users
- `PUT /admin/users.php?id=X` - Update user
- `GET /admin/bookings.php` - List all bookings
- `PUT /admin/bookings.php?id=X` - Manage booking

All endpoints return JSON and require authentication (except login/register).

## 🛠️ Customization

### Changing Colors
Edit CSS variables in `css/style.css`:
```css
:root {
    --accent-primary: #D47318; /* Your brand color */
    --bg-primary: #FFFBF5; /* Background */
}
```

### Adding Menu Items
1. Admin Dashboard → Menu Management
2. Or directly in database:
```sql
INSERT INTO menu_items (category_id, item_name, description, price, image_url)
VALUES (2, 'New Dish', 'Description', 45.00, 'images/menu/dish.jpg');
```

### Modifying Tables
Update `restaurant_tables` in database:
```sql
INSERT INTO restaurant_tables (table_number, capacity, location)
VALUES (11, 4, 'Window Side');
```

## 📧 Email Configuration

For production, configure email notifications:

```php
// config/database.php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'noreply@bddine.com.au');
define('SMTP_PASSWORD', 'your-app-password');
```

## 🔍 Monitoring & Logs

### Audit Logs
View security audit trail:
```sql
SELECT * FROM audit_log
WHERE created_at > NOW() - INTERVAL 24 HOUR
ORDER BY created_at DESC;
```

### Error Logs
PHP errors: `logs/php_errors.log`
Database errors: Logged to `audit_log` table

### Session Monitoring
```sql
SELECT COUNT(*) as active_sessions
FROM secure_sessions
WHERE is_valid = 1 AND expires_at > NOW();
```

## 🚨 Troubleshooting

### Common Issues

**"Database connection failed"**:
- Check MySQL is running: `systemctl status mysql`
- Verify credentials in `config/database.php`
- Ensure database exists: `SHOW DATABASES;`

**"2FA code not working"**:
- Check SMS API credentials
- Verify phone number format (+61 for Australia)
- Check `two_factor_codes` table for generated codes

**"Session expired"**:
- Normal behavior after 1 hour
- User needs to re-login
- Adjust `SESSION_LIFETIME` in config if needed

**"Permission denied"**:
- Check file permissions: `ls -la`
- Ensure web server can write to `logs/`

## 📝 License & Support

**License**: Proprietary - BD Dine Restaurant
**Support**: info@bddine.com.au
**Phone**: (02) 6234 5678

## 🎯 Production Checklist

Before going live:

- [ ] Change all default passwords
- [ ] Generate new encryption keys
- [ ] Configure SSL certificate
- [ ] Set up SMS API
- [ ] Configure payment gateway
- [ ] Enable error logging (disable display)
- [ ] Set secure cookie settings
- [ ] Configure backup system
- [ ] Test 2FA on production phone
- [ ] Review database indexes
- [ ] Set up monitoring/alerting
- [ ] Test booking flow end-to-end
- [ ] Verify email notifications
- [ ] Load test with expected traffic
- [ ] Security audit/penetration testing

## 🎉 Credits

Designed and developed with security and user experience in mind.

**Technologies Used**:
- PHP 7.4+
- MySQL 5.7+
- HTML5/CSS3
- JavaScript (ES6+)
- Google Fonts (Cormorant Garamond, Montserrat)
- Font Awesome Icons

---

**BD Dine Restaurant** - Experience the finest culinary journey in Canberra 🍽️
