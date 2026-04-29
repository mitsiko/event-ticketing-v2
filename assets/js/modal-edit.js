/**
 * Overlay Edit Modal System
 * Opens edit forms in a modal overlay instead of navigating away
 */
(function() {
    'use strict';
    
    let modalOpen = false;
    let currentModal = null;
    let currentOnSuccess = null;
    
    /**
     * Create and show an edit modal
     * @param {string} url - The URL to load content from
     * @param {string} title - Modal title
     * @param {function} onSuccess - Callback when edit is successful
     */
    function openEditModal(url, title, onSuccess) {
        if (modalOpen) {
            closeEditModal();
            setTimeout(function() {
                openEditModal(url, title, onSuccess);
            }, 250);
            return;
        }
        
        modalOpen = true;
        currentOnSuccess = onSuccess || null;
        
        // Create overlay
        var overlay = document.createElement('div');
        overlay.className = 'modal-edit-overlay';
        overlay.innerHTML = 
            '<div class="modal-edit-container">' +
                '<div class="modal-edit-header">' +
                    '<h3>' + escapeHTML(title) + '</h3>' +
                    '<button class="modal-edit-close" onclick="closeEditModal()">&times;</button>' +
                '</div>' +
                '<div class="modal-edit-body">' +
                    '<div class="modal-edit-loading">' +
                        '<div class="loading-spinner"></div>' +
                        '<p>Loading...</p>' +
                    '</div>' +
                '</div>' +
            '</div>';
        
        document.body.appendChild(overlay);
        currentModal = overlay;
        
        // Prevent body scroll
        document.body.style.overflow = 'hidden';
        
        // Animate in
        requestAnimationFrame(function() {
            overlay.classList.add('active');
        });
        
        // Load content
        loadModalContent(url, overlay);
        
        // Close on overlay click
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) {
                closeEditModal();
            }
        });
        
        // Close on Escape
        document.addEventListener('keydown', handleEscape);
    }
    
    function loadModalContent(url, overlay) {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', url, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        
        xhr.onload = function() {
            if (xhr.status >= 200 && xhr.status < 400) {
                var html = xhr.responseText;
                var body = overlay.querySelector('.modal-edit-body');
                
                // Parse the HTML to extract just the form
                var tempDiv = document.createElement('div');
                tempDiv.innerHTML = html;
                
                // Try to find the form or card content
                var form = tempDiv.querySelector('form[method="POST"]');
                var card = tempDiv.querySelector('.card form');
                var pageContent = tempDiv.querySelector('.page.active');
                
                if (card) {
                    body.innerHTML = card.outerHTML;
                } else if (form && form.closest('.card')) {
                    body.innerHTML = form.closest('.card').outerHTML;
                } else if (form) {
                    body.innerHTML = form.outerHTML;
                } else if (pageContent) {
                    // Extract just the form part from the page
                    var pageForm = pageContent.querySelector('form');
                    if (pageForm) {
                        body.innerHTML = pageForm.outerHTML;
                    } else {
                        body.innerHTML = '<p style="text-align:center;padding:20px;color:#991b1b;">Could not load edit form.</p>';
                    }
                } else {
                    body.innerHTML = '<p style="text-align:center;padding:20px;color:#991b1b;">Could not load edit form.</p>';
                }
                
                // Remove any alert-success messages (they're from the session flash)
                var alerts = body.querySelectorAll('.alert');
                alerts.forEach(function(alert) {
                    if (alert.classList.contains('alert-success') || alert.textContent.indexOf('successfully') !== -1) {
                        alert.remove();
                    }
                });
                
                // Fix the form to submit via AJAX
                var modalForm = body.querySelector('form');
                if (modalForm) {
                    modalForm.addEventListener('submit', function(e) {
                        e.preventDefault();
                        submitModalForm(modalForm, url, overlay);
                    });
                    
                    // Fix Cancel/Back links to close modal instead
                    var cancelLinks = modalForm.querySelectorAll('a[href]:not(.btn-primary)');
                    cancelLinks.forEach(function(link) {
                        if (link.textContent.trim().toLowerCase().indexOf('cancel') !== -1 ||
                            link.textContent.trim().toLowerCase().indexOf('back') !== -1 ||
                            link.classList.contains('btn-secondary')) {
                            link.addEventListener('click', function(e) {
                                e.preventDefault();
                                closeEditModal();
                            });
                        }
                    });
                    
                    // Fix buttons that say Cancel
                    var cancelBtns = modalForm.querySelectorAll('button:not([type="submit"])');
                    cancelBtns.forEach(function(btn) {
                        if (btn.textContent.trim().toLowerCase() === 'cancel') {
                            btn.addEventListener('click', function(e) {
                                e.preventDefault();
                                closeEditModal();
                            });
                        }
                    });
                }
                
                // Initialize any scripts needed
                initFormScripts(body);
                
            } else {
                overlay.querySelector('.modal-edit-body').innerHTML = 
                    '<div style="text-align:center;padding:40px;color:#991b1b;">' +
                        '<p>Error loading form (Status: ' + xhr.status + ').</p>' +
                        '<button class="btn btn-sm" onclick="closeEditModal()" style="margin-top:12px;">Close</button>' +
                    '</div>';
            }
        };
        
        xhr.onerror = function() {
            overlay.querySelector('.modal-edit-body').innerHTML = 
                '<div style="text-align:center;padding:40px;color:#991b1b;">' +
                    '<p>Network error. Please try again.</p>' +
                    '<button class="btn btn-sm" onclick="closeEditModal()" style="margin-top:12px;">Close</button>' +
                '</div>';
        };
        
        xhr.send();
    }
    
    function submitModalForm(form, url, overlay) {
        var submitBtn = form.querySelector('button[type="submit"]');
        var originalText = submitBtn ? submitBtn.textContent : 'Save';
        
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Saving...';
        }
        
        // Remove old errors
        var oldErrors = form.querySelector('.alert-error, .error-box');
        if (oldErrors) oldErrors.remove();
        
        var formData = new FormData(form);
        
        var xhr = new XMLHttpRequest();
        xhr.open('POST', url, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        
        xhr.onload = function() {
            if (xhr.status >= 200 && xhr.status < 400) {
                var html = xhr.responseText;
                
                // Check if the response indicates success
                // Look for success message or redirect
                if (html.indexOf('successfully') !== -1 || 
                    html.indexOf('alert-success') !== -1 ||
                    html.indexOf('Location:') !== -1) {
                    
                    // Success! Close modal and reload
                    closeEditModal();
                    
                    if (typeof currentOnSuccess === 'function') {
                        currentOnSuccess();
                    } else {
                        // Reload the page to show updated data
                        setTimeout(function() {
                            window.location.reload();
                        }, 300);
                    }
                } else {
                    // Show errors - parse the response for error messages
                    var tempDiv = document.createElement('div');
                    tempDiv.innerHTML = html;
                    
                    var errorBox = tempDiv.querySelector('.alert-error, .error-box');
                    
                    if (errorBox) {
                        // Insert errors at top of form
                        form.insertBefore(errorBox, form.firstChild);
                        errorBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    } else {
                        // No errors found but also no success - might need to update form
                        var newForm = tempDiv.querySelector('form');
                        if (newForm) {
                            form.innerHTML = newForm.innerHTML;
                            // Re-attach submit handler
                            form.addEventListener('submit', function(e) {
                                e.preventDefault();
                                submitModalForm(form, url, overlay);
                            });
                        }
                    }
                }
            } else {
                var errorDiv = document.createElement('div');
                errorDiv.className = 'alert alert-error';
                errorDiv.textContent = 'Server error (Status: ' + xhr.status + '). Please try again.';
                form.insertBefore(errorDiv, form.firstChild);
            }
            
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        };
        
        xhr.onerror = function() {
            var errorDiv = document.createElement('div');
            errorDiv.className = 'alert alert-error';
            errorDiv.textContent = 'Network error. Please check your connection.';
            form.insertBefore(errorDiv, form.firstChild);
            
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        };
        
        xhr.send(formData);
    }
    
    function closeEditModal() {
        if (currentModal) {
            currentModal.classList.remove('active');
            setTimeout(function() {
                if (currentModal && currentModal.parentNode) {
                    currentModal.parentNode.removeChild(currentModal);
                }
                currentModal = null;
                modalOpen = false;
                currentOnSuccess = null;
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
    
    function escapeHTML(str) {
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
    
    function initFormScripts(container) {
        // Initialize any toggle functions that might be needed
        var selects = container.querySelectorAll('select[onchange]');
        selects.forEach(function(select) {
            var attr = select.getAttribute('onchange');
            if (attr) {
                select.addEventListener('change', function() {
                    try {
                        eval(attr);
                    } catch(e) {}
                });
            }
        });
    }
    
    // Expose to global scope
    window.openEditModal = openEditModal;
    window.closeEditModal = closeEditModal;
    
    // Log that modal system is ready
    console.log('✅ Modal edit system loaded');
    
})();