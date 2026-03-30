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
    baseURL: '/bd-dine-restaurant/api',
    
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
    
    validatePhone(phone) {
        // Australian phone format
        const re = /^(\+61|0)[4-5][0-9]{8}$/;
        const cleaned = phone.replace(/[^0-9+]/g, '');
        return re.test(cleaned);
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
            const response = await API.post('register.php', formData);
            return response;
        } catch (error) {
            return { success: false, message: 'Registration failed. Please try again.' };
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
                phone: form.phone.value
            };
            
            // Validate
            let isValid = true;
            
            if (!FormValidator.validateEmail(formData.email)) {
                FormValidator.showError(form.email, 'Invalid email address');
                isValid = false;
            }
            
            if (!FormValidator.validatePhone(formData.phone)) {
                FormValidator.showError(form.phone, 'Invalid phone number (Australian format)');
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
            
            const result = await this.submit(formData);
            
            if (result.success) {
                alert('Registration successful! Please login.');
                window.location.href = 'login.html';
            } else {
                alert(result.message || 'Registration failed. Please try again.');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Create Account';
            }
        });
    }
};

// Login Handler
const Login = {
    currentStep: 1,
    userId: null,
    adminId: null,
    
    async authenticateStep1(credentials) {
        try {
            const isAdmin = window.location.pathname.includes('admin-login');
            const endpoint = isAdmin ? 'admin-login.php' : 'login.php';
            const response = await API.post(endpoint, credentials);
            return response;
        } catch (error) {
            return { success: false, message: 'Authentication failed. Please try again.' };
        }
    },
    
    async authenticateStep2(code) {
        try {
            const isAdmin = this.adminId !== null;
            const endpoint = isAdmin ? 'admin-verify-2fa.php' : 'verify-2fa.php';
            const response = await API.post(endpoint, {
                user_id: this.userId,
                admin_id: this.adminId,
                code: code
            });
            return response;
        } catch (error) {
            return { success: false, message: 'Verification failed. Please try again.' };
        }
    },
    
    showStep2() {
        document.getElementById('step-1').style.display = 'none';
        document.getElementById('step-2').style.display = 'block';
        this.currentStep = 2;
    },
    
    init() {
        const form = document.getElementById('login-form');
        if (!form) return;
        
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            if (this.currentStep === 1) {
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
                    this.userId = result.user_id || null;
                    this.adminId = result.admin_id || null;
                    this.showStep2();
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Login';
                } else {
                    alert(result.message || 'Login failed. Please try again.');
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Login';
                }
                
            } else if (this.currentStep === 2) {
                // Step 2: 2FA Code
                const code = form.code.value;
                
                if (code.length !== 6) {
                    FormValidator.showError(form.code, 'Please enter the 6-digit code');
                    return;
                }
                
                const submitBtn = form.querySelector('button[type="submit"]');
                submitBtn.disabled = true;
                submitBtn.textContent = 'Verifying...';
                
                const result = await this.authenticateStep2(code);
                
                if (result.success) {
                    const isAdmin = this.adminId !== null;
                    const redirectURL = isAdmin ? 'admin-dashboard.html' : 'customer-dashboard.html';
                    window.location.href = redirectURL;
                } else {
                    alert(result.message || 'Verification failed. Please try again.');
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Verify';
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
                special_requests: form.special_requests.value
            };
            
            const submitBtn = form.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Processing...';
            
            const result = await this.submit(bookingData);
            
            if (result.success) {
                alert('Booking successful! Confirmation sent to your email.');
                form.reset();
            } else {
                alert(result.message || 'Booking failed. Please try again.');
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
            await API.post('logout.php', {});
            window.location.href = 'index.html';
        } catch (error) {
            console.error('Logout failed:', error);
        }
    },
    
    initLogoutButtons() {
        document.querySelectorAll('.logout-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                if (confirm('Are you sure you want to logout?')) {
                    this.logout();
                }
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
});

// Export for use in other files
window.API = API;
window.FormValidator = FormValidator;
window.Session = Session;
