# BD Dine Restaurant - Quick Start Guide

## 🚀 Quick Installation (5 Minutes)

### Prerequisites
- XAMPP, WAMP, or LAMP stack installed
- MySQL running
- Web browser

### Step 1: Extract Files
1. Extract the `bd-dine-restaurant.zip` to your web server directory:
   - XAMPP: `C:\xampp\htdocs\`
   - WAMP: `C:\wamp64\www\`
   - Linux: `/var/www/html/`

### Step 2: Create Database
1. Open phpMyAdmin: `http://localhost/phpmyadmin`
2. Click "New" to create a database
3. Name it: `bd_dine_restaurant`
4. Click "Import" tab
5. Choose file: `database/schema.sql`
6. Click "Go" to import

### Step 3: Configure Database Connection
1. Open: `config/database.php`
2. Update these lines (if needed):
```php
private $host = 'localhost';
private $db_name = 'bd_dine_restaurant';
private $username = 'root';      // Your MySQL username
private $password = '';          // Your MySQL password (usually empty for XAMPP)
```

### Step 4: Generate Encryption Key
1. Open terminal/command prompt
2. Run: `php -r "echo bin2hex(random_bytes(16));"`
3. Copy the output
4. In `config/database.php`, replace:
```php
define('ENCRYPTION_KEY', 'paste-your-generated-key-here');
```

### Step 5: Access the Website
1. Open browser: `http://localhost/bd-dine-restaurant/`
2. You should see the homepage!

### Step 6: Test Admin Login
1. Go to: `http://localhost/bd-dine-restaurant/admin-login.html`
2. Username: `admin`
3. Password: `Admin@123`
4. **For demo**: 2FA code will be logged to `logs/php_errors.log` (check this file)
5. OR configure SMS API in `config/database.php` for real SMS

## 📱 SMS Configuration (Optional but Recommended)

For 2FA to work via SMS, sign up for an SMS provider:

### Option 1: Twilio (Recommended)
1. Sign up: https://www.twilio.com/
2. Get API credentials
3. Update `config/database.php`:
```php
define('SMS_API_KEY', 'your-twilio-account-sid');
define('SMS_API_URL', 'https://api.twilio.com/2010-04-01/Accounts/YOUR_SID/Messages.json');
define('SMS_FROM_NUMBER', '+61412345678'); // Your Twilio number
```

### Option 2: AWS SNS
1. Set up AWS SNS
2. Get credentials
3. Update config accordingly

**Note**: Without SMS configured, 2FA codes will only be logged to the error log file for testing.

## 🎨 Default Accounts

### Admin Account
- Username: `admin`
- Password: `Admin@123`
- Access: `admin-login.html`

### Customer Account
- Create your own by registering at: `register.html`

## 📁 Project Structure

```
bd-dine-restaurant/
├── index.html              # Homepage
├── menu.html               # Menu page
├── about.html              # About page
├── contact.html            # Contact page
├── booking.html            # Reservation page
├── login.html              # Customer login
├── register.html           # Customer registration
├── admin-login.html        # Admin login
├── admin-dashboard.html    # Admin dashboard
├── css/
│   └── style.css          # Main stylesheet
├── js/
│   └── main.js            # Main JavaScript
├── api/
│   ├── login.php          # Login API
│   ├── register.php       # Registration API
│   ├── verify-2fa.php     # 2FA verification
│   └── booking.php        # Booking API
├── config/
│   └── database.php       # Database config
├── includes/
│   ├── auth.php           # Authentication
│   ├── security.php       # Security utilities
│   └── encryption.php     # Encryption utilities
├── database/
│   ├── schema.sql         # Database schema
│   └── ERD.md            # Entity Relationship Diagram
└── README.md             # Full documentation
```

## 🔧 Troubleshooting

### "Database connection failed"
- Start MySQL: XAMPP Control Panel → Start MySQL
- Check credentials in `config/database.php`
- Verify database exists: phpMyAdmin → Check `bd_dine_restaurant`

### "Permission denied" errors
- Windows: Right-click folder → Properties → Uncheck "Read-only"
- Linux/Mac: `chmod -R 755 bd-dine-restaurant/`

### "Session expired immediately"
- Check that cookies are enabled in browser
- Clear browser cache and cookies
- Verify `session.cookie_secure` is set to 0 for HTTP (local development)

### 2FA code not received
- Check `logs/php_errors.log` for the code (development mode)
- Configure SMS API for production use
- Verify phone number format: +61 4XX XXX XXX (Australian)

## 🎯 Next Steps

1. ✅ **Test the system**: Create account, make booking, login as admin
2. ✅ **Customize design**: Edit colors in `css/style.css`
3. ✅ **Add menu items**: Use admin dashboard or database
4. ✅ **Configure SMS**: Set up Twilio or AWS SNS for 2FA
5. ✅ **Set up payments**: Integrate Stripe or PayPal
6. ✅ **Deploy**: Move to production server with HTTPS

## 📚 Full Documentation

For complete documentation including:
- Security features explained
- API endpoints reference
- Database schema details
- Production deployment checklist
- Advanced customization

Read: `README.md`

## 🆘 Support

- Email: info@bddine.com.au
- Phone: (02) 6234 5678

## 🔐 Security Reminder

Before going live:
1. Change default admin password
2. Generate new encryption key
3. Enable HTTPS (SSL certificate)
4. Configure real SMS API
5. Review security settings

---

**You're all set!** Visit `http://localhost/bd-dine-restaurant/` to see your restaurant website.
