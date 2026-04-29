/**
 * User Registration Portal JavaScript
 * File location: /event_ticketing/assets/js/user-reg.js
 */
(function() {
    'use strict';
    
    console.log('✅ user-reg.js loaded successfully');
    
    // ============================================
    // INITIALIZATION
    // ============================================
    document.addEventListener('DOMContentLoaded', function() {
        console.log('✅ DOM ready - initializing user portal');
        
        initCardAnimations();
        initAffiliationToggling();
        initTicketSelection();
        initFormValidation();
        initEmailValidation();
    });
    
    // ============================================
    // CARD ANIMATIONS
    // ============================================
    function initCardAnimations() {
        const cards = document.querySelectorAll('.event-card');
        if (!cards.length) return;
        
        cards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(30px)';
            card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            
            setTimeout(() => {
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 100);
        });
    }
    
    // ============================================
    // AFFILIATION FIELD TOGGLING
    // ============================================
    function initAffiliationToggling() {
        const affiliationRadios = document.querySelectorAll('input[name="attendee_type"]');
        if (!affiliationRadios.length) return;
        
        const fieldsMap = {
            'student': 'student-fields',
            'employee': 'employee-fields',
            'alumni': 'alumni-fields',
            'guest': 'guest-fields'
        };
        
        function toggleFields() {
            const selected = document.querySelector('input[name="attendee_type"]:checked');
            const type = selected ? selected.value : '';
            
            // Hide all fields
            Object.values(fieldsMap).forEach(id => {
                const el = document.getElementById(id);
                if (el) el.style.display = 'none';
            });
            
            // Remove selected class from all options
            document.querySelectorAll('.affiliation-option').forEach(el => {
                el.classList.remove('selected');
            });
            
            // Show relevant fields
            if (type && fieldsMap[type]) {
                const el = document.getElementById(fieldsMap[type]);
                if (el) el.style.display = 'block';
            }
            
            // Highlight selected option
            if (selected) {
                const parent = selected.closest('.affiliation-option');
                if (parent) parent.classList.add('selected');
            }
            
            // Update ticket eligibility
            if (typeof updateTicketEligibility === 'function') {
                updateTicketEligibility(type);
            }
        }
        
        // Event listeners for radio buttons
        affiliationRadios.forEach(radio => {
            radio.addEventListener('change', toggleFields);
        });
        
        // Click on entire card
        document.querySelectorAll('.affiliation-option').forEach(option => {
            option.addEventListener('click', function(e) {
                const radio = this.querySelector('input[type="radio"]');
                if (radio && e.target !== radio) {
                    radio.checked = true;
                    radio.dispatchEvent(new Event('change'));
                }
            });
        });
        
        // Initialize
        toggleFields();
    }
    
    // ============================================
    // TICKET SELECTION
    // ============================================
    function initTicketSelection() {
        const ticketRadios = document.querySelectorAll('input[name="category_id"]');
        if (!ticketRadios.length) return;
        
        function updateSelection() {
            document.querySelectorAll('.ticket-option').forEach(opt => {
                opt.classList.remove('selected');
            });
            
            const selected = document.querySelector('input[name="category_id"]:checked');
            if (selected) {
                const option = selected.closest('.ticket-option');
                if (option) option.classList.add('selected');
            }
        }
        
        ticketRadios.forEach(radio => {
            radio.addEventListener('change', updateSelection);
        });
        
        // Click on ticket card
        document.querySelectorAll('.ticket-option').forEach(option => {
            option.addEventListener('click', function(e) {
                if (this.classList.contains('disabled')) return;
                const radio = this.querySelector('input[type="radio"]');
                if (radio && e.target !== radio) {
                    radio.checked = true;
                    radio.dispatchEvent(new Event('change'));
                }
            });
        });
        
        updateSelection();
    }
    
    // ============================================
    // TICKET ELIGIBILITY
    // ============================================
    function updateTicketEligibility(attendeeType) {
        const ticketOptions = document.querySelectorAll('.ticket-option');
        const warningBox = document.getElementById('eligibility-warning');
        
        if (!ticketOptions.length) return;
        
        let hasEligible = false;
        
        ticketOptions.forEach(option => {
            const radio = option.querySelector('input[type="radio"]');
            if (!radio) return;
            
            const eligibleType = radio.dataset.eligible;
            const isEligible = eligibleType === 'all' || eligibleType === attendeeType || !attendeeType;
            
            option.classList.remove('disabled');
            option.style.opacity = '1';
            option.style.pointerEvents = 'auto';
            
            if (!isEligible && attendeeType) {
                option.classList.add('disabled');
                option.style.opacity = '0.4';
                option.style.pointerEvents = 'none';
                
                if (radio.checked) {
                    radio.checked = false;
                    option.classList.remove('selected');
                }
            } else {
                hasEligible = true;
            }
        });
        
        // Auto-select first eligible
        const selected = document.querySelector('input[name="category_id"]:checked');
        if (!selected && hasEligible) {
            const firstEligible = document.querySelector('.ticket-option:not(.disabled) input[type="radio"]');
            if (firstEligible) {
                firstEligible.checked = true;
                firstEligible.closest('.ticket-option').classList.add('selected');
            }
        }
        
        // Update warning
        if (warningBox) {
            if (selected && attendeeType) {
                const eligibleType = selected.dataset.eligible;
                if (eligibleType !== 'all' && eligibleType !== attendeeType) {
                    warningBox.style.display = 'block';
                    warningBox.innerHTML = '⚠️ This ticket is only for <strong>' + eligibleType + 's</strong>. Please select a different ticket or change your affiliation.';
                } else {
                    warningBox.style.display = 'none';
                }
            } else {
                warningBox.style.display = 'none';
            }
        }
    }
    
    // Make updateTicketEligibility globally accessible
    window.updateTicketEligibility = updateTicketEligibility;
    
    // ============================================
    // FORM VALIDATION
    // ============================================
    function initFormValidation() {
        const form = document.getElementById('registrationForm');
        if (!form) return;
        
        form.addEventListener('submit', function(e) {
            const errors = [];
            
            // Check required fields
            const requiredFields = [
                { id: 'first_name', name: 'First name' },
                { id: 'last_name', name: 'Last name' },
                { id: 'email', name: 'Email address' },
                { id: 'birth_date', name: 'Birth date' }
            ];
            
            requiredFields.forEach(field => {
                const el = document.getElementById(field.id);
                if (el && !el.value.trim()) {
                    errors.push(field.name + ' is required.');
                    el.style.borderColor = '#E74C3C';
                } else if (el) {
                    el.style.borderColor = '';
                }
            });
            
            // Validate email format
            const emailEl = document.getElementById('email');
            if (emailEl && emailEl.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailEl.value)) {
                errors.push('Please enter a valid email address.');
            }
            
            // Validate affiliation
            const affiliation = document.querySelector('input[name="attendee_type"]:checked');
            if (!affiliation) {
                errors.push('Please select your affiliation.');
            }
            
            // Validate ticket
            const ticket = document.querySelector('input[name="category_id"]:checked');
            if (!ticket) {
                errors.push('Please select a ticket category.');
            }
            
            // Display errors
            const oldErrors = document.querySelector('.error-box');
            if (oldErrors) oldErrors.remove();
            
            if (errors.length > 0) {
                e.preventDefault();
                
                const errorBox = document.createElement('div');
                errorBox.className = 'error-box';
                errorBox.style.cssText = 'background:#FCEBEB;border:1px solid #E24B4A;border-radius:12px;padding:16px;margin-bottom:20px;color:#791F1F;';
                errorBox.innerHTML = '<strong>⚠️ Please fix the following:</strong><ul style="margin:8px 0 0 20px;">' +
                    errors.map(err => '<li style="margin-bottom:4px;">' + err + '</li>').join('') +
                    '</ul>';
                
                form.insertBefore(errorBox, form.querySelector('.form-actions'));
                errorBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
            } else {
                // Show loading
                const submitBtn = document.getElementById('submitBtn');
                if (submitBtn) {
                    submitBtn.textContent = '⏳ Processing...';
                    submitBtn.disabled = true;
                }
            }
        });
        
        // Clear error styling on input
        form.querySelectorAll('input, select').forEach(el => {
            el.addEventListener('input', function() {
                this.style.borderColor = '';
            });
        });
    }
    
    // ============================================
    // EMAIL VALIDATION
    // ============================================
    function initEmailValidation() {
        const emailInput = document.getElementById('email');
        if (!emailInput) return;
        
        let debounceTimer;
        
        emailInput.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            const email = this.value.trim();
            
            if (email.length < 5 || !email.includes('@')) return;
            
            debounceTimer = setTimeout(() => {
                checkEmail(email);
            }, 500);
        });
    }
    
    function checkEmail(email) {
        fetch('/event-ticketing-v2/api/check-email.php?email=' + encodeURIComponent(email))
            .then(response => response.json())
            .then(data => {
                const statusEl = document.getElementById('email-status');
                if (!statusEl) return;
                
                if (data.exists) {
                    statusEl.textContent = '✓ Welcome back!';
                    statusEl.style.cssText = 'color:#2E7D32;font-size:12px;margin-top:4px;';
                } else {
                    statusEl.textContent = '✓ New registration';
                    statusEl.style.cssText = 'color:#1565C0;font-size:12px;margin-top:4px;';
                }
            })
            .catch(() => {});
    }
    
})();