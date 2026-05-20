/**
 * BD Dine Restaurant - Main JavaScript
 * Theme toggle, API interactions, and form handling
 */

// Theme Management
const themeToggle = {
    init() {
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);
        
        const toggleBtn = document.getElementById('theme-toggle');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', this.toggle.bind(this));
            this.updateIcon(savedTheme);
        }
    },
    
    toggle() {
        const currentTheme = document.documentElement.getAttribute('data-theme');
        const newTheme = currentTheme === 'light' ? 'dark' : 'light';
        
        document.documentElement.setAttribute('data-theme', newTheme);
        localStorage.setItem('theme', newTheme);
        this.updateIcon(newTheme);
    },
    
    updateIcon(theme) {
        const icon = document.querySelector('#theme-toggle i');
        if (icon) {
            icon.className = theme === 'light' ? 'fas fa-moon' : 'fas fa-sun';
        }
    }
};

// API Helper
const API = {
    baseURL: '/secure-restaurant-ordering-and-table-management-system/bd-dine/api',
    
    async request(endpoint, options = {}) {
        const url = `${this.baseURL}/${endpoint}`;
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json'
            }
        };
        
        try {
            const response = await fetch(url, { ...defaultOptions, ...options });
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('API Request failed:', error);
            throw error;
        }
    },
    
    async post(endpoint, data) {
        return this.request(endpoint, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    },
    
    async get(endpoint) {
        return this.request(endpoint, {
            method: 'GET'
        });
    }
};

// Form Validation
const FormValidator = {
    validateEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    },
    
    validatePassword(password) {
        if (password.length < 8) {
            return { valid: false, message: 'Password must be at least 8 characters long' };
        }
        if (!/[A-Z]/.test(password)) {
            return { valid: false, message: 'Password must contain at least one uppercase letter' };
        }
        if (!/[a-z]/.test(password)) {
            return { valid: false, message: 'Password must contain at least one lowercase letter' };
        }
        if (!/[0-9]/.test(password)) {
            return { valid: false, message: 'Password must contain at least one number' };
        }
        if (!/[^A-Za-z0-9]/.test(password)) {
            return { valid: false, message: 'Password must contain at least one special character' };
        }
        return { valid: true, message: 'Password is strong' };
    },
    
    showError(inputElement, message) {
        const errorElement = inputElement.parentElement.querySelector('.form-error');
        if (errorElement) {
            errorElement.textContent = message;
            errorElement.style.display = 'block';
        }
        inputElement.classList.add('error');
    },
    
    clearError(inputElement) {
        const errorElement = inputElement.parentElement.querySelector('.form-error');
        if (errorElement) {
            errorElement.textContent = '';
            errorElement.style.display = 'none';
        }
        inputElement.classList.remove('error');
    }
};

// Registration Handler
const Registration = {
    async submit(formData) {
    try {
        const response = await fetch('http://localhost/secure-restaurant-ordering-and-table-management-system/bd-dine/api/register.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        });

       const data = await response.json();

if (data.success) {
    showToast('Account created successfully!', 'success');
    setTimeout(() => window.location.href = "login.html", 2000);
} else {
    showToast(data.message, 'error');
}

return data;
    } catch (error) {
        return { success: false, message: error.message };
    }
},
    
    init() {
        const form = document.getElementById('registration-form');
        if (!form) return;
        
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = {
                email: form.email.value,
                password: form.password.value,
                first_name: form.first_name.value,
                last_name: form.last_name.value,
            };
            
            // Validate
            let isValid = true;
            
            if (!FormValidator.validateEmail(formData.email)) {
                FormValidator.showError(form.email, 'Invalid email address');
                isValid = false;
            }
            
            const passwordValidation = FormValidator.validatePassword(formData.password);
            if (!passwordValidation.valid) {
                FormValidator.showError(form.password, passwordValidation.message);
                isValid = false;
            }
            
            if (formData.password !== form.confirm_password.value) {
                FormValidator.showError(form.confirm_password, 'Passwords do not match');
                isValid = false;
            }
            
            if (!isValid) return;
            
            // Submit
            const submitBtn = form.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Creating Account...';
            
            await this.submit(formData);
            submitBtn.disabled = false;
            submitBtn.textContent = 'Create Account';
            
        });
    }
};

// Login Handler
const Login = {
    currentStep: 1,
    userId: null,
    adminId: null,
    
    async checkIfAlreadyLoggedIn() {
    try {
        const result = await API.get('check-session.php');
        if (result.valid) {
            window.location.replace('customer-dashboard.html');
        }
    } catch (error) {
        console.error('Session check failed:', error);
    }
},

    async authenticateStep1(credentials) {
    try {
        const response = await API.post('login.php', credentials);
        return response;
    } catch (error) {
    return { success: false, message: 'Authentication failed. Please try again.' };
    }
},
    
    init() {
        const form = document.getElementById('login-form');
        if (!form) return;

        this.checkIfAlreadyLoggedIn();
        
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            if (Login.currentStep === 1) {
                // Step 1: Username/Email + Password
                const isAdmin = window.location.pathname.includes('admin-login');
                const credentials = isAdmin
                    ? {
                        username: form.username.value,
                        password: form.password.value
                    }
                    : {
                        email: form.email.value,
                        password: form.password.value
                    };
                
                const submitBtn = form.querySelector('button[type="submit"]');
                submitBtn.disabled = true;
                submitBtn.textContent = 'Authenticating...';
                
                const result = await this.authenticateStep1(credentials);
                
                if (result.success && result.requires_2fa) {
                    // Store user_id for step 2
                    Login.userId = result.user_id;
                    Login.currentStep = 2;
    
                    // Show step 2, hide step 1
                    document.getElementById('step-1').classList.remove('active');
                    document.getElementById('step-2').classList.add('active');
    
                    // Update step 2 message
                    document.querySelector('#step-2 p').textContent = 'We have sent a 6-digit code to your email address.';
                                        
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Login <i class="fas fa-arrow-right"></i>';

            } else if (result.success && !result.requires_2fa) {
                showToast('Login successful!', 'success');
                    setTimeout(() => window.location.href = 'customer-dashboard.html', 1500);
            } else {
                showToast(result.message || 'Login failed. Please try again.', 'error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Login <i class="fas fa-arrow-right"></i>';
            }
            } else {
                // Step 2: Verify 2FA code
                const code = form.code.value;
                const submitBtn = form.querySelector('button[type="submit"]');
                submitBtn.disabled = true;
                submitBtn.textContent = 'Verifying...';
                
                const result = await API.post('verify-2fa.php', {
                    user_id: Login.userId,
                    code: code
                });
                
                if (result.success) {
                    showToast('Login successful!', 'success');
                    setTimeout(() => window.location.href = 'customer-dashboard.html', 1500);
                } else {
                    showToast(result.message || 'Invalid code. Please try again.', 'error');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Verify <i class="fas fa-check"></i>';
                }
            }
        });
        }
};

// Booking Handler
const Booking = {
    async submit(bookingData) {
        try {
            const response = await API.post('booking.php', bookingData);
            return response;
        } catch (error) {
            return { success: false, message: 'Booking failed. Please try again.' };
        }
    },
    
    init() {
        const form = document.getElementById('booking-form');
        if (!form) return;
        
        // Set minimum date to today
        const dateInput = form.booking_date;
        if (dateInput) {
            const today = new Date().toISOString().split('T')[0];
            dateInput.min = today;
        }
        
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const bookingData = {
                booking_date: form.booking_date.value,
                booking_time: form.booking_time.value,
                number_of_guests: form.number_of_guests.value,
                special_requests: form.special_requests.value,
                phone: form.phone.value,
                csrf_token: document.getElementById('csrf_token').value
            };
            
            const submitBtn = form.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Processing...';
            
            const result = await this.submit(bookingData);
            
            if (result.success) {
                showToast("Reservation submitted! You'll be notified once your booking is confirmed.", 'success');
                form.reset();
            } else {
                showToast(result.message || 'Booking failed. Please try again.', 'error');
            }
            submitBtn.disabled = false;
            submitBtn.textContent = 'Reserve Table';
        });
    }
};

// Scroll Effects
const ScrollEffects = {
    init() {
        window.addEventListener('scroll', () => {
            const navbar = document.querySelector('.navbar');
            if (navbar) {
                if (window.scrollY > 100) {
                    navbar.classList.add('scrolled');
                } else {
                    navbar.classList.remove('scrolled');
                }
            }
        });
        
        // Intersection Observer for animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -100px 0px'
        };
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate-fade-in-up');
                }
            });
        }, observerOptions);
        
        document.querySelectorAll('.card, .section-header').forEach(el => {
            observer.observe(el);
        });
    }
};

// Session Management
const Session = {
    async check() {
        try {
            const response = await API.get('check-session.php');
            return response;
        } catch (error) {
            return { valid: false };
        }
    },
    
   async logout() {
    try {
        showToast('Logging out...', 'info');
        setTimeout(async () => {
            await API.post('logout.php', {});
            window.location.href = 'login.html';
        }, 1500);
    } catch (error) {
        console.error('Logout failed:', error);
    }
},
    
    initLogoutButtons() {
        document.querySelectorAll('.logout-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                    this.logout();
            });
        });
    }
};

// Initialize on DOM load
document.addEventListener('DOMContentLoaded', () => {
    themeToggle.init();
    ScrollEffects.init();
    Registration.init();
    Login.init();
    Booking.init();
    Session.initLogoutButtons();

    Session.check().then(result => {
        if (result.valid) {
            document.querySelectorAll('.logout-btn').forEach(btn => {
                btn.style.display = 'inline-flex';
            });

            document.querySelectorAll('.auth-only').forEach(item => {
                item.style.display = 'inline-block';
            });

            document.querySelectorAll('.nav-menu a[href="login.html"]').forEach(link => {
            link.textContent = 'Dashboard';
            link.href = 'customer-dashboard.html';
            });
        }
    });
});

// Export for use in other files
window.API = API;
window.FormValidator = FormValidator;
window.Session = Session;

// CART MENU TOGGLE 
const menuToggle = document.getElementById("cart-menu-toggle");
const dropdown = document.getElementById("cart-menu-dropdown");

if (menuToggle && dropdown) {
    menuToggle.addEventListener("click", () => {
        dropdown.style.display =
            dropdown.style.display === "none" ? "block" : "none";
    });
}
function showToast(message, type = 'info', duration = 4000) {
    const existing = document.querySelector('.toast');
    if (existing) existing.remove();

    const icons = {
        success: 'fas fa-check-circle',
        error: 'fas fa-exclamation-circle',
        info: 'fas fa-info-circle'
    };

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `<i class="${icons[type]}"></i> ${message}`;
    document.body.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.3s';
        setTimeout(() => toast.remove(), 300);
    }, duration);
}