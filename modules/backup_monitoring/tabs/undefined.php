<?php
// Undefined Emails Tab Content
// This file is loaded via AJAX into the main page
?>

<div class="mb-3">
    <button type="button" class="btn btn-danger" id="deleteBulkBtn" onclick="deleteSelectedUndefined()" style="display: none;">
        <i class="fas fa-trash me-1"></i>Delete Selected (<span id="selectedCount">0</span>)
    </button>
</div>

<div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead>
            <tr>
                <th style="width: 50px;">
                    <input type="checkbox" class="form-check-input" id="selectAllCheckbox" onchange="selectAllUndefined(this)">
                </th>
                <th>Source</th>
                <th>Device Type</th>
                <th>Status</th>
                <th>Backup Date</th>
                <th>Backup Size</th>
                <th>Email Subject</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="jobsTable">
            <tr>
                <td colspan="8" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading undefined emails...</p>
                </td>
            </tr>
        </tbody>
    </table>
</div>

<!-- Pagination Controls -->
<div class="d-flex justify-content-between align-items-center mt-3" id="paginationControls" style="display: none !important;">
    <div class="text-muted small" id="paginationInfo">
        Showing <span id="startRow">0</span> to <span id="endRow">0</span> of <span id="totalRows">0</span> entries
    </div>
    <nav aria-label="Page navigation">
        <ul class="pagination pagination-sm mb-0">
            <li class="page-item disabled" id="prevPageItem">
                <button class="page-link" onclick="changePage(-1)" tabindex="-1" aria-disabled="true">Previous</button>
            </li>
            <li class="page-item active" id="currentPageItem">
                <span class="page-link" id="currentPageDisplay">1</span>
            </li>
            <li class="page-item" id="nextPageItem">
                <button class="page-link" onclick="changePage(1)">Next</button>
            </li>
        </ul>
    </nav>
</div>

<script>
    // Initialize pagination variables
    let currentPage = 1;
    const itemsPerPage = 50; // Match backend limit
    
    // Helper function to remove SPAM prefix from email subjects
    function cleanEmailSubject(subject) {
        if (!subject) return 'N/A';
        return subject.replace(/^\*\*\*SPAM\*\*\*\s*/, '').trim();
    }
    
    function changePage(delta) {
        const newPage = currentPage + delta;
        if (newPage < 1) return;
        
        currentPage = newPage;
        fetchJobsWithPagination(currentPage);
    }
    
    function fetchJobsWithPagination(page) {
        // Show loading
        $("#jobsTable").html('<tr><td colspan="8" class="text-center py-4"><div class="spinner-border text-primary" role="status"></div><p class="mt-2 text-muted">Loading page ' + page + '...</p></td></tr>');
        
        const payload = {
            action: 'fetch_undefined',
            type: 'UNDEFINED',
            source: 'UNDEFINED',
            start: $('#startDate').val(),
            end: $('#endDate').val(),
            search: $('#searchText').val(),
            status: $('#statusFilter').val(),
            page: page,
            limit: itemsPerPage
        };

        $.post(typeof BACKEND_URL !== 'undefined' ? BACKEND_URL : "backend.php", payload, function(response) {
            try {
                // If response is JSON (pagination supported)
                if (typeof response === 'object' || (typeof response === 'string' && response.trim().startsWith('{'))) {
                    const data = typeof response === 'string' ? JSON.parse(response) : response;
                    
                    if (data.html) {
                        $("#jobsTable").html(data.html);
                        
                        // Reset checkbox selections after loading
                        $('#selectAllCheckbox').prop('checked', false);
                        updateDeleteButtonVisibility();
                        
                        // Update pagination controls
                        if (data.pagination) {
                            $('#paginationControls').show().css('display', 'flex'); // Force display flex
                            $('#startRow').text(data.pagination.start);
                            $('#endRow').text(data.pagination.end);
                            $('#totalRows').text(data.pagination.total);
                            $('#currentPageDisplay').text(data.pagination.page);
                            
                            // Update buttons state
                            if (data.pagination.page <= 1) {
                                $('#prevPageItem').addClass('disabled');
                                $('#prevPageItem button').attr('aria-disabled', 'true');
                            } else {
                                $('#prevPageItem').removeClass('disabled');
                                $('#prevPageItem button').removeAttr('aria-disabled');
                            }
                            
                            if (data.pagination.page >= data.pagination.pages) {
                                $('#nextPageItem').addClass('disabled');
                            } else {
                                $('#nextPageItem').removeClass('disabled');
                            }
                        } else {
                            $('#paginationControls').hide();
                        }
                    } else {
                        // Fallback if HTML is returned directly
                         $("#jobsTable").html(response);
                         $('#paginationControls').hide();
                    }
                } else {
                    // Legacy HTML response
                    $("#jobsTable").html(response);
                    $('#paginationControls').hide();
                }
            } catch (e) {
                console.error("Error parsing response:", e);
                // Fallback for non-JSON response (HTML fragment)
                $("#jobsTable").html(response);
                $('#paginationControls').hide();
            }
        }).fail(function(xhr, status, error) {
            console.error('AJAX Error Details:', {
                status: status,
                error: error,
                statusCode: xhr.status,
                statusText: xhr.statusText,
                responseText: xhr.responseText,
                url: xhr.responseURL || 'backend.php'
            });
            
            let errorMsg = 'Error loading undefined mails.';
            let errorDetails = '';
            
            if (xhr.responseText) {
                try {
                    const errorData = JSON.parse(xhr.responseText);
                    if (errorData.error) {
                        errorMsg = errorData.error;
                    }
                    if (errorData.timestamp) {
                        errorDetails = 'Error occurred at: ' + errorData.timestamp;
                    }
                } catch (e) {
                    errorMsg = xhr.responseText.substring(0, 200);
                }
            }
            
            let errorHtml = '<tr><td colspan="8" class="text-center text-danger py-4">';
            errorHtml += '<i class="fas fa-exclamation-triangle fa-2x mb-2"></i><br>';
            errorHtml += '<strong>Error Loading Data</strong><br>';
            errorHtml += '<div class="mt-2">' + errorMsg + '</div>';
            errorHtml += '<div class="mt-2"><small class="text-muted">';
            errorHtml += 'Status: ' + xhr.status + ' ' + xhr.statusText;
            if (errorDetails) {
                errorHtml += '<br>' + errorDetails;
            }
            errorHtml += '</small></div>';
            errorHtml += '<div class="mt-2"><small class="text-muted">';
            errorHtml += 'Please check the browser console (F12) for more details.';
            errorHtml += '</small></div>';
            errorHtml += '</td></tr>';
            
            $("#jobsTable").html(errorHtml);
            $('#paginationControls').hide();
        });
    }
    
    // Override the global fetchJobs function when this tab is active
    // This allows the main filter button to work with pagination
    window.fetchJobs = function() {
        currentPage = 1; // Reset to page 1 on new filter
        fetchJobsWithPagination(1);
    };
    
    // Initial load
    fetchJobsWithPagination(1);
    
    // View undefined mail details
    function openViewUndefined(id) {
        const backendUrl = typeof BACKEND_URL !== 'undefined' ? BACKEND_URL : 'backend.php';
        
        $.post(backendUrl, { action: 'get_undefined', id: id }, function(response) {
            try {
                const data = typeof response === 'string' ? JSON.parse(response) : response;
                if (data.success && data.data) {
                    const mail = data.data;
                    
                    // Decode email body if needed
                    let emailBody = mail.email_body || 'No email body available';
                    
                    // If it looks like base64, try to decode
                    if (emailBody.length > 100 && /^[A-Za-z0-9+\/=\s]+$/.test(emailBody.trim())) {
                        try {
                            const decoded = atob(emailBody.trim());
                            // Check if decoded looks like HTML or text
                            if (decoded.includes('<') || decoded.includes('&')) {
                                emailBody = decoded;
                            }
                        } catch (e) {
                            // Not base64, use as is
                        }
                    }
                    
                    // Clean up UTF-8 BOM and encoding artifacts (remove "Â" characters)
                    emailBody = emailBody.replace(/\u00EF\u00BB\u00BF/g, ''); // Remove UTF-8 BOM
                    emailBody = emailBody.replace(/\u00C2\u00A0/g, ' '); // Replace non-breaking space
                    emailBody = emailBody.replace(/\u00A0/g, ' '); // Replace non-breaking space (Unicode)
                    emailBody = emailBody.replace(/Â(?![\u0080-\u00BF])/g, ''); // Remove standalone Â
                    emailBody = emailBody.replace(/Â[\s\.,;:!?]/g, ''); // Remove Â followed by space/punctuation
                    
                    // Create modal content with proper structure
                    let modalContent = `
                        <div class="modal-content">
                            <div class="modal-header bg-primary text-white">
                                <h5 class="modal-title">Email Details: ${escapeHtml(cleanEmailSubject(mail.email_subject))}</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <strong>Source:</strong><br>
                                        <span class="badge bg-info">${escapeHtml(mail.source || 'N/A')}</span>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Device Type:</strong><br>
                                        ${escapeHtml(mail.device_type || 'N/A')}
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <strong>Status:</strong><br>
                                        <span class="badge bg-${mail.status === 'Success' ? 'success' : mail.status === 'Warning' ? 'warning' : 'danger'}">${escapeHtml(mail.status || 'UNKNOWN')}</span>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Backup Date:</strong><br>
                                        ${escapeHtml(mail.backup_date || 'N/A')}
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <strong>Backup Size:</strong><br>
                                        ${escapeHtml(mail.backup_size || 'N/A')}
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Created At:</strong><br>
                                        ${escapeHtml(mail.created_at || 'N/A')}
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <strong>Email Subject:</strong><br>
                                    <div class="p-2 bg-light rounded">${escapeHtml(cleanEmailSubject(mail.email_subject))}</div>
                                </div>
                                <div class="mb-3">
                                    <strong>Email Body:</strong><br>
                                    <div class="p-3 bg-white rounded border" style="max-height: 500px; overflow-y: auto; border: 1px solid #dee2e6;">
                                        <iframe id="emailBodyFrame_${id}" sandbox="allow-same-origin" style="width: 100%; min-height: 400px; border: none; background: white;"></iframe>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    `;
                    
                    // Create or update modal
                    let modal = $('#viewUndefinedModal');
                    if (modal.length === 0) {
                        modal = $('<div class="modal fade" id="viewUndefinedModal" tabindex="-1" aria-labelledby="viewUndefinedModalLabel" aria-hidden="true"><div class="modal-dialog modal-xl"></div></div>');
                        $('body').append(modal);
                    }
                    modal.find('.modal-dialog').html(modalContent);
                    
                    // Set iframe content after modal is created (to avoid template literal issues)
                    setTimeout(function() {
                        const iframe = document.getElementById('emailBodyFrame_' + id);
                        if (iframe) {
                            iframe.srcdoc = emailBody;
                        }
                    }, 100);
                    
                    // Use Bootstrap 5 modal if available, otherwise use jQuery
                    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                        const bsModal = new bootstrap.Modal(modal[0]);
                        bsModal.show();
                    } else {
                        modal.modal('show');
                    }
                } else {
                    alert('Error loading undefined mail details: ' + (data.error || 'Unknown error'));
                }
            } catch (e) {
                console.error('Error parsing response:', e);
                alert('Error loading undefined mail details');
            }
        }, 'json').fail(function(xhr, status, error) {
            alert('Error loading undefined mail: ' + error);
        });
    }
    
    // Edit undefined mail (create/update backup job)
    function openEditUndefined(id) {
        const backendUrl = typeof BACKEND_URL !== 'undefined' ? BACKEND_URL : 'backend.php';
        
        $.post(backendUrl, { action: 'get_undefined', id: id }, function(response) {
            try {
                const data = typeof response === 'string' ? JSON.parse(response) : response;
                if (data.success && data.data) {
                    const mail = data.data;
                    
                    // Fetch clients for dropdown from backend
                    $.ajax({
                        url: backendUrl,
                        type: 'POST',
                        data: { action: 'get_clients' },
                        dataType: 'json',
                        success: function(clients) {
                            let clientOptions = '<option value="">Select Client (Optional)</option>';
                            if (clients && Array.isArray(clients) && clients.length > 0) {
                                clients.forEach(function(client) {
                                    const contactPerson = escapeHtml(client.contact_person || '');
                                    clientOptions += `<option value="${client.id}" data-contact="${contactPerson}">${escapeHtml(client.client_name || 'N/A')}</option>`;
                                });
                            } else {
                                console.warn('No clients returned or invalid format:', clients);
                            }
                        
                        let modalContent = `
                            <div class="modal-content">
                                <div class="modal-header bg-warning text-dark">
                                    <h5 class="modal-title">Create Backup Job from Undefined Mail</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <form id="editUndefinedForm">
                                        <input type="hidden" name="id" value="${id}">
                                        <div class="mb-3">
                                            <label class="form-label">Device Type <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="device_type" value="${escapeHtml(mail.device_type || '')}" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Backup Source <span class="text-danger">*</span></label>
                                            <select class="form-select" name="backup_source" required>
                                                <option value="CLIENT" ${mail.source === 'CLIENT' ? 'selected' : ''}>CLIENT</option>
                                                <option value="VEEAM" ${mail.source === 'VEEAM' ? 'selected' : ''}>VEEAM</option>
                                                <option value="VEEAMCLOUD" ${mail.source === 'VEEAMCLOUD' ? 'selected' : ''}>VEEAMCLOUD</option>
                                                <option value="NAS" ${mail.source === 'NAS' ? 'selected' : ''}>NAS</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Client (Optional)</label>
                                            <select class="form-select" name="client_id" id="edit_client_select">
                                                ${clientOptions}
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Contact Person</label>
                                            <input type="text" class="form-control" name="contact_person" id="edit_contact_person" placeholder="Will be auto-filled when client is selected" readonly>
                                        </div>
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle me-2"></i>
                                            This will create or update a backup job and move this email to backup logs.
                                        </div>
                                    </form>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="button" class="btn btn-primary" onclick="saveEditUndefined()">Save</button>
                                </div>
                            </div>
                        `;
                        
                            showEditModal(modalContent);
                        },
                        error: function(xhr, status, error) {
                            console.error('Error fetching clients:', xhr, status, error);
                            // If clients API fails, still show form without client dropdown
                            let modalContent = `
                            <div class="modal-content">
                                <div class="modal-header bg-warning text-dark">
                                    <h5 class="modal-title">Edit/Create Backup Job from Undefined Mail</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <form id="editUndefinedForm">
                                        <input type="hidden" name="id" value="${id}">
                                        <div class="mb-3">
                                            <label class="form-label">Device Type <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="device_type" value="${escapeHtml(mail.device_type || '')}" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Backup Source <span class="text-danger">*</span></label>
                                            <select class="form-select" name="backup_source" required>
                                                <option value="CLIENT" ${mail.source === 'CLIENT' ? 'selected' : ''}>CLIENT</option>
                                                <option value="VEEAM" ${mail.source === 'VEEAM' ? 'selected' : ''}>VEEAM</option>
                                                <option value="VEEAMCLOUD" ${mail.source === 'VEEAMCLOUD' ? 'selected' : ''}>VEEAMCLOUD</option>
                                                <option value="NAS" ${mail.source === 'NAS' ? 'selected' : ''}>NAS</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Client ID (Optional)</label>
                                            <input type="number" class="form-control" name="client_id" id="edit_client_id_fallback" placeholder="Enter client ID">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Contact Person</label>
                                            <input type="text" class="form-control" name="contact_person" id="edit_contact_person_fallback" placeholder="Enter contact person">
                                        </div>
                                    </form>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="button" class="btn btn-primary" onclick="saveEditUndefined()">Save</button>
                                </div>
                            </div>
                        `;
                            showEditModal(modalContent);
                        }
                    });
                } else {
                    alert('Error loading undefined mail: ' + (data.error || 'Unknown error'));
                }
            } catch (e) {
                console.error('Error:', e);
                alert('Error loading undefined mail');
            }
        }, 'json').fail(function(xhr, status, error) {
            alert('Error loading undefined mail: ' + error);
        });
    }
    
    // Save edit undefined
    function saveEditUndefined() {
        const backendUrl = typeof BACKEND_URL !== 'undefined' ? BACKEND_URL : 'backend.php';
        const form = $('#editUndefinedForm');
        
        // Validate form
        if (!form[0].checkValidity()) {
            form[0].reportValidity();
            return;
        }
        
        const formData = {};
        form.find('input, select').each(function() {
            const name = $(this).attr('name');
            const value = $(this).val();
            if (name) {
                formData[name] = value;
            }
        });
        
        formData.action = 'edit_undefined';
        
        // Show loading
        const saveBtn = $('#editUndefinedModal .btn-primary');
        const originalText = saveBtn.html();
        saveBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Saving...');
        
        $.post(backendUrl, formData, function(response) {
            try {
                const data = typeof response === 'string' ? JSON.parse(response) : response;
                if (data.success) {
                    // Hide modal using Bootstrap or jQuery
                    const modal = $('#editUndefinedModal');
                    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                        const bsModal = bootstrap.Modal.getInstance(modal[0]);
                        if (bsModal) bsModal.hide();
                    } else {
                        modal.modal('hide');
                    }
                    alert('Backup job ' + (data.message || 'saved successfully'));
                    fetchJobsWithPagination(currentPage); // Refresh table
                } else {
                    alert('Error: ' + (data.error || 'Unknown error'));
                    saveBtn.prop('disabled', false).html(originalText);
                }
            } catch (e) {
                console.error('Error:', e, response);
                alert('Error saving backup job: ' + e.message);
                saveBtn.prop('disabled', false).html(originalText);
            }
        }, 'json').fail(function(xhr, status, error) {
            console.error('AJAX Error:', xhr, status, error);
            alert('Error saving: ' + error + '\nStatus: ' + xhr.status + '\nResponse: ' + xhr.responseText.substring(0, 200));
            saveBtn.prop('disabled', false).html(originalText);
        });
    }
    
    // Delete undefined mail
    function openDeleteUndefined(id) {
        if (!confirm('Are you sure you want to delete this undefined mail? This action cannot be undone.')) {
            return;
        }
        
        const backendUrl = typeof BACKEND_URL !== 'undefined' ? BACKEND_URL : 'backend.php';
        
        $.post(backendUrl, { action: 'delete_undefined', id: id }, function(response) {
            try {
                const data = typeof response === 'string' ? JSON.parse(response) : response;
                if (data.success) {
                    alert('Undefined mail deleted successfully');
                    fetchJobsWithPagination(currentPage); // Refresh table
                } else {
                    alert('Error: ' + (data.error || 'Unknown error'));
                }
            } catch (e) {
                console.error('Error:', e);
                alert('Error deleting undefined mail');
            }
        }, 'json').fail(function(xhr, status, error) {
            alert('Error deleting: ' + error);
        });
    }
    
    // Helper function to escape HTML
    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }
    
    // Helper function to show edit modal
    function showEditModal(modalContent) {
        let modal = $('#editUndefinedModal');
        if (modal.length === 0) {
            modal = $('<div class="modal fade" id="editUndefinedModal" tabindex="-1" aria-labelledby="editUndefinedModalLabel" aria-hidden="true"><div class="modal-dialog modal-lg"></div></div>');
            $('body').append(modal);
        }
        modal.find('.modal-dialog').html(modalContent);
        
        // Use Bootstrap 5 modal if available, otherwise use jQuery
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            const bsModal = new bootstrap.Modal(modal[0]);
            bsModal.show();
        } else {
            modal.modal('show');
        }
    }
    
    // Auto-fill contact person when client is selected (using event delegation)
    $(document).on('change', '#edit_client_select', function() {
        const backendUrl = typeof BACKEND_URL !== 'undefined' ? BACKEND_URL : 'backend.php';
        const selectedOption = $(this).find('option:selected');
        const contactPerson = selectedOption.data('contact') || '';
        $('#edit_contact_person').val(contactPerson);
        
        // If no contact in data attribute, fetch from backend
        if (!contactPerson && $(this).val()) {
            $.post(backendUrl, { action: 'get_client_contact', client_id: $(this).val() }, function(response) {
                if (response.success && response.contact_person) {
                    $('#edit_contact_person').val(response.contact_person);
                }
            }, 'json');
        }
    });
    
    // Bulk delete functions
    function selectAllUndefined(checkbox) {
        const isChecked = checkbox.checked;
        $('input.undefined-checkbox').prop('checked', isChecked);
        updateDeleteButtonVisibility();
    }
    
    function updateCheckboxSelection(checkboxElement) {
        updateDeleteButtonVisibility();
        
        // Update "Select All" checkbox state
        const totalCheckboxes = $('input.undefined-checkbox').length;
        const checkedCheckboxes = $('input.undefined-checkbox:checked').length;
        
        if (totalCheckboxes > 0) {
            if (checkedCheckboxes === totalCheckboxes) {
                $('#selectAllCheckbox').prop('checked', true).prop('indeterminate', false);
            } else if (checkedCheckboxes > 0) {
                $('#selectAllCheckbox').prop('indeterminate', true);
            } else {
                $('#selectAllCheckbox').prop('checked', false).prop('indeterminate', false);
            }
        }
    }
    
    function updateDeleteButtonVisibility() {
        const checkedCount = $('input.undefined-checkbox:checked').length;
        const deleteBtn = $('#deleteBulkBtn');
        const countSpan = $('#selectedCount');
        
        countSpan.text(checkedCount);
        
        if (checkedCount > 0) {
            deleteBtn.show();
        } else {
            deleteBtn.hide();
        }
    }
    
    function deleteSelectedUndefined() {
        const selectedIds = [];
        $('input.undefined-checkbox:checked').each(function() {
            selectedIds.push($(this).data('id'));
        });
        
        if (selectedIds.length === 0) {
            alert('Please select at least one item to delete');
            return;
        }
        
        if (!confirm('Are you sure you want to delete ' + selectedIds.length + ' selected mail(s)? This action cannot be undone.')) {
            return;
        }
        
        const backendUrl = typeof BACKEND_URL !== 'undefined' ? BACKEND_URL : 'backend.php';
        
        $.ajax({
            url: backendUrl,
            type: 'POST',
            data: {
                action: 'delete_undefined_bulk',
                ids: selectedIds
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert('Successfully deleted ' + response.deleted + ' mail(s)');
                    // Reload the table
                    fetchJobsWithPagination(1);
                } else {
                    alert('Error: ' + (response.message || 'Failed to delete selected items'));
                }
            },
            error: function(xhr, status, error) {
                console.error('Delete error:', error);
                alert('Error deleting items. Please try again.');
            }
        });
    }
    
    // Make functions globally accessible
    window.openViewUndefined = openViewUndefined;
    window.openEditUndefined = openEditUndefined;
    window.saveEditUndefined = saveEditUndefined;
    window.openDeleteUndefined = openDeleteUndefined;
    window.showEditModal = showEditModal;
    window.selectAllUndefined = selectAllUndefined;
    window.updateCheckboxSelection = updateCheckboxSelection;
    window.deleteSelectedUndefined = deleteSelectedUndefined;
</script>