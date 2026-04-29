/**
 * Event Management & Ticketing System
 * Client-side JavaScript
 */

// Confirm delete actions
document.addEventListener('DOMContentLoaded', function() {
    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.3s';
            alert.style.opacity = '0';
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.style.display = 'none';
                }
            }, 300);
        }, 5000);
    });
});

// Toggle subtype fields for attendee forms
function toggleSubtypeFields() {
    const typeSelect = document.getElementById('attendee_type');
    if (!typeSelect) return;
    
    const type = typeSelect.value;
    const studentFields = document.getElementById('student_fields');
    const employeeFields = document.getElementById('employee_fields');
    const alumniFields = document.getElementById('alumni_fields');
    const guestFields = document.getElementById('guest_fields');
    
    if (studentFields) studentFields.style.display = type === 'student' ? 'grid' : 'none';
    if (employeeFields) employeeFields.style.display = type === 'employee' ? 'grid' : 'none';
    if (alumniFields) alumniFields.style.display = type === 'alumni' ? 'grid' : 'none';
    if (guestFields) guestFields.style.display = type === 'guest' ? 'block' : 'none';
}

// Form validation
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return true;
    
    const required = form.querySelectorAll('[required]');
    let isValid = true;
    
    required.forEach(field => {
        if (!field.value.trim()) {
            field.style.borderColor = '#E24B4A';
            isValid = false;
        } else {
            field.style.borderColor = '';
        }
    });
    
    const emailFields = form.querySelectorAll('input[type="email"]');
    emailFields.forEach(field => {
        if (field.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(field.value)) {
            field.style.borderColor = '#E24B4A';
            isValid = false;
        }
    });
    
    const startTime = form.querySelector('input[name="start_time"]');
    const endTime = form.querySelector('input[name="end_time"]');
    if (startTime && endTime && startTime.value && endTime.value) {
        if (startTime.value >= endTime.value) {
            startTime.style.borderColor = '#E24B4A';
            endTime.style.borderColor = '#E24B4A';
            alert('End time must be after start time.');
            isValid = false;
        }
    }
    
    return isValid;
}

// Print ticket
function printTicket() {
    window.print();
}

// Prefill validation code
function prefillValidation(code) {
    const input = document.querySelector('input[name="code"]');
    if (input) {
        input.value = code;
        input.form.submit();
    }
}

// Mobile menu (if needed)
function toggleMobileMenu() {
    const sidebar = document.querySelector('.sidebar');
    if (sidebar) {
        sidebar.classList.toggle('mobile-open');
    }
}
// Fix for type filter buttons in Attendees page
function fixTypeFilterButtons() {
    const typeSelect = document.getElementById('type-select');
    const filterForm = document.getElementById('filter-form');
    
    if (!typeSelect || !filterForm) return;
    
    const typeMap = {
        'All Types': '',
        'Student': 'student',
        'Employee': 'employee',
        'Alumni': 'alumni',
        'Guest': 'guest'
    };
    
    const types = ['All Types', 'Student', 'Employee', 'Alumni', 'Guest'];
    const allElements = document.querySelectorAll('div, span, button, a, .badge, .filter-option');
    
    types.forEach(typeText => {
        allElements.forEach(el => {
            if (el.textContent.trim() === typeText && 
                (el.tagName === 'BUTTON' || 
                 el.classList.contains('btn') || 
                 el.classList.contains('badge') ||
                 el.getAttribute('role') === 'button')) {
                
                el.style.cursor = 'pointer';
                el.style.transition = 'all 0.2s ease';
                
                const newEl = el.cloneNode(true);
                el.parentNode.replaceChild(newEl, el);
                
                newEl.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    let selectValue = '';
                    switch(this.textContent.trim()) {
                        case 'All Types':
                            selectValue = '';
                            break;
                        case 'Student':
                            selectValue = 'student';
                            break;
                        case 'Employee':
                            selectValue = 'employee';
                            break;
                        case 'Alumni':
                            selectValue = 'alumni';
                            break;
                        case 'Guest':
                            selectValue = 'guest';
                            break;
                    }
                    
                    typeSelect.value = selectValue;
                    filterForm.submit();
                });
            }
        });
    });
}

// Fix for payment filter buttons - same as manage events and attendees pages
document.addEventListener('DOMContentLoaded', function() {
    // Find the payment select dropdown and filter form
    const paymentSelect = document.getElementById('payment-select');
    const filterForm = document.getElementById('filter-form');
    
    if (!paymentSelect || !filterForm) return;
    
    // Find all elements that might be payment filter buttons
    const allElements = document.querySelectorAll('div, span, button, a, .badge, .filter-option');
    const paymentTypes = ['All Payment', 'Free', 'Paid', 'Pending'];
    
    paymentTypes.forEach(paymentText => {
        allElements.forEach(el => {
            // Check if element text matches payment type and element is clickable-style
            if (el.textContent.trim() === paymentText && 
                (el.tagName === 'BUTTON' || 
                 el.classList.contains('btn') || 
                 el.classList.contains('badge') ||
                 el.getAttribute('role') === 'button')) {
                
                // Make it look clickable
                el.style.cursor = 'pointer';
                el.style.transition = 'all 0.2s ease';
                
                // Remove existing event listeners to avoid duplicates
                const newEl = el.cloneNode(true);
                el.parentNode.replaceChild(newEl, el);
                
                // Add click event
                newEl.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Map button text to select option value
                    let selectValue = '';
                    switch(this.textContent.trim()) {
                        case 'All Payment':
                            selectValue = '';
                            break;
                        case 'Free':
                            selectValue = 'free';
                            break;
                        case 'Paid':
                            selectValue = 'paid';
                            break;
                        case 'Pending':
                            selectValue = 'pending';
                            break;
                    }
                    
                    // Set the select dropdown value
                    paymentSelect.value = selectValue;
                    
                    // Submit the form
                    filterForm.submit();
                });
            }
        });
    });
});