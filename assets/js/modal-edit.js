/**
 * Overlay Edit Modal System
 * Opens edit forms in a modal overlay instead of navigating away
 */
(function() {
    'use strict';
    
    // Track if modal is open
    let modalOpen = false;
    let currentModal = null;
    
    /**
     * Create and show an edit modal
     * @param {string} url - The URL to load content from
     * @param {string} title - Modal title
     * @param {function} onSuccess - Callback when edit is successful
     */
    function openEditModal(url, title, onSuccess) {
        if (modalOpen) return;
        modalOpen = true;
        
        // Create overlay
        const overlay = document.createElement('div');
        overlay.className = 'modal-edit-overlay';
        overlay.innerHTML = `
            <div class="modal-edit-container">
                <div class="modal-edit-header">
                    <h3>${title}</h3>
                    <button class="modal-edit-close" onclick="closeEditModal()">&times;</button>
                </div>
                <div class="modal-edit-body">
                    <div class="modal-edit-loading">
                        <div class="loading-spinner"></div>
                        <p>Loading...</p>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(overlay);
        currentModal = overlay;
        
        // Prevent body scroll
        document.body.style.overflow = 'hidden';
        
        // Animate in
        requestAnimationFrame(() => {
            overlay.classList.add('active');
        });
        
        // Load content via fetch
        fetch(url)
            .then(response => {
                if (!response.ok) throw new Error('Failed to load');
                return response.text();
            })
            .then(html => {
                const body = overlay.querySelector('.modal-edit-body');
                
                // Extract the form portion from the HTML
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                
                // Find the main form content
                const formContent = doc.querySelector('.registration-form, .card form, form[method="POST"]');
                const pageContent = doc.querySelector('.page.active, .registration-form-wrapper');
                
                let content = '';
                if (formContent) {
                    content = formContent.outerHTML;
                } else if (pageContent) {
                    content = pageContent.innerHTML;
                } else {
                    content = '<p>Could not load edit form.</p>';
                }
                
                body.innerHTML = content;
                
                // Fix form action to submit via AJAX
                const form = body.querySelector('form');
                if (form) {
                    form.addEventListener('submit', function(e) {
                        e.preventDefault();
                        submitEditForm(form, url, onSuccess);
                    });
                    
                    // Fix Cancel button to close modal
                    const cancelBtn = form.querySelector('a.btn:not(.btn-primary), a[href*="cancel"], .btn-secondary');
                    if (cancelBtn) {
                        cancelBtn.addEventListener('click', function(e) {
                            e.preventDefault();
                            closeEditModal();
                        });
                    }
                }
                
                // Fix any hrefs in the modal
                body.querySelectorAll('a[href]').forEach(link => {
                    if (link.getAttribute('href').includes('cancel') || 
                        link.textContent.trim().toLowerCase() === 'cancel' ||
                        link.classList.contains('btn-secondary')) {
                        link.addEventListener('click', function(e) {
                            e.preventDefault();
                            closeEditModal();
                        });
                    }
                });
                
                // Initialize any scripts
                if (typeof toggleAffiliationFields === 'function') toggleAffiliationFields();
                if (typeof togglePaymentFields === 'function') togglePaymentFields();
            })
            .catch(error => {
                overlay.querySelector('.modal-edit-body').innerHTML = `
                    <div style="text-align:center;padding:40px;color:#dc2626;">
                        <p>Error loading form. Please try again.</p>
                        <button class="btn btn-sm" onclick="closeEditModal()">Close</button>
                    </div>
                `;
                console.error('Modal load error:', error);
            });
        
        // Close on overlay click
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) {
                closeEditModal();
            }
        });
        
        // Close on Escape
        document.addEventListener('keydown', handleEscape);
    }
    
    function submitEditForm(form, url, onSuccess) {
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Saving...';
        }
        
        const formData = new FormData(form);
        
        fetch(url, {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(html => {
            // Check if the response contains a success message
            if (html.includes('successfully') || html.includes('alert-success')) {
                closeEditModal();
                if (typeof onSuccess === 'function') {
                    onSuccess();
                } else {
                    // Reload the page to show updated data
                    window.location.reload();
                }
            } else {
                // Show errors - parse the response
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const errors = doc.querySelector('.alert-error, .error-box');
                const formContent = doc.querySelector('.registration-form, .card form, form[method="POST"]');
                
                if (errors) {
                    // Show errors above the form
                    const existingErrors = form.querySelector('.alert-error, .error-box');
                    if (existingErrors) existingErrors.remove();
                    form.insertBefore(errors, form.firstChild);
                } else if (formContent) {
                    // Update the form content
                    const body = currentModal.querySelector('.modal-edit-body');
                    body.innerHTML = formContent.outerHTML || formContent.innerHTML;
                }
            }
            
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Save Changes';
            }
        })
        .catch(error => {
            console.error('Form submit error:', error);
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Save Changes';
            }
        });
    }
    
    function closeEditModal() {
        if (currentModal) {
            currentModal.classList.remove('active');
            setTimeout(() => {
                if (currentModal && currentModal.parentNode) {
                    currentModal.parentNode.removeChild(currentModal);
                }
                currentModal = null;
                modalOpen = false;
                document.body.style.overflow = '';
            }, 200);
        }
        document.removeEventListener('keydown', handleEscape);
    }
    
    function handleEscape(e) {
        if (e.key === 'Escape') {
            closeEditModal();
        }
    }
    
    // Expose to global scope
    window.openEditModal = openEditModal;
    window.closeEditModal = closeEditModal;
    
})();