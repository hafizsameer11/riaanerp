<div class="table-responsive">
    <table class="table table-striped table-hover">
        <thead class="table-dark">
            <tr>
                <th>Client</th>
                <th>Contact</th>
                <th>Device Type</th>
                <th>Status</th>
                <th>Latest Backup</th>
                <th>Backup Size</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="jobsTable">
            <tr>
                <td colspan="7" class="text-center">Loading...</td>
            </tr>
        </tbody>
    </table>
</div>

<script>
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
    
    // Override global fetchJobs function for this tab
    window.fetchJobs = function() {
        console.log('CLIENT fetchJobs called');
        const type = 'CLIENT';
        const start = $('#startDate').val() || '';
        const end = $('#endDate').val() || '';
        const search = $('#searchText').val() || '';
        const status = $('#statusFilter').val() || '';
        
        $("#jobsTable").html('<tr><td colspan="7" class="text-center"><div class="spinner-border text-primary" role="status"></div><p class="mt-2 text-muted">Loading...</p></td></tr>');
        
        console.log('Making AJAX request to backend.php', { action: 'fetch', type: type, source: type, start: start, end: end, search: search, status: status });
        
        $.ajax({
            url: typeof BACKEND_URL !== 'undefined' ? BACKEND_URL : 'backend.php',
            type: 'POST',
            data: {
                action: 'fetch',
                type: type,
                source: type,
                start: start,
                end: end,
                search: search,
                status: status
            },
            dataType: 'html',
            timeout: 30000, // 30 second timeout
            success: function(response) {
                console.log('Response received:', response);
                if (response && typeof response === 'string') {
                    $("#jobsTable").html(response);
                } else if (response && response.html) {
                    $("#jobsTable").html(response.html);
                } else if (response) {
                    $("#jobsTable").html(String(response));
                } else {
                    $("#jobsTable").html("<tr><td colspan='7' class='text-center text-warning'>No data received from server</td></tr>");
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error Details:', {
                    status: status,
                    error: error,
                    statusCode: xhr.status,
                    statusText: xhr.statusText,
                    responseText: xhr.responseText ? xhr.responseText.substring(0, 500) : 'No response',
                    url: xhr.responseURL || 'backend.php'
                });
                
                let errorMsg = 'Error loading data.';
                let errorDetails = '';
                
                // Check if response is HTML (successful response but treated as error)
                if (xhr.responseText && xhr.responseText.trim().startsWith('<')) {
                    console.log('Response is HTML, treating as success');
                    $("#jobsTable").html(xhr.responseText);
                    return;
                }
                
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
                        // Not JSON, use response text directly
                        errorMsg = xhr.responseText.substring(0, 200);
                    }
                }
                
                // Handle timeout
                if (status === 'timeout') {
                    errorMsg = 'Request timed out. The server may be slow or unresponsive.';
                }
                
                let errorHtml = '<tr><td colspan="7" class="text-center text-danger py-4">';
                errorHtml += '<i class="fas fa-exclamation-triangle fa-2x mb-2"></i><br>';
                errorHtml += '<strong>Error Loading Data</strong><br>';
                errorHtml += '<div class="mt-2">' + escapeHtml(errorMsg) + '</div>';
                errorHtml += '<div class="mt-2"><small class="text-muted">';
                errorHtml += 'Status: ' + (xhr.status || 'N/A') + ' ' + (xhr.statusText || status);
                if (errorDetails) {
                    errorHtml += '<br>' + escapeHtml(errorDetails);
                }
                errorHtml += '</small></div>';
                errorHtml += '<div class="mt-2"><small class="text-muted">';
                errorHtml += 'Please check the browser console (F12) for more details.';
                errorHtml += '</small></div>';
                errorHtml += '</td></tr>';
                
                $("#jobsTable").html(errorHtml);
            }
        });
    };
    
    // Ensure jQuery is loaded and then call fetchJobs
    (function() {
        function initFetchJobs() {
            if (typeof jQuery !== 'undefined' && typeof $ !== 'undefined') {
                console.log('CLIENT tab ready, calling fetchJobs');
                console.log('fetchJobs type:', typeof window.fetchJobs);
                try {
                    // Explicitly call window.fetchJobs to ensure we use the tab-specific version
                    if (typeof window.fetchJobs === 'function') {
                        window.fetchJobs();
                    } else {
                        console.error('window.fetchJobs is not a function!', typeof window.fetchJobs);
                        $("#jobsTable").html('<tr><td colspan="7" class="text-center text-danger">Error: fetchJobs function not defined</td></tr>');
                    }
                } catch (e) {
                    console.error('Error calling fetchJobs:', e);
                    $("#jobsTable").html('<tr><td colspan="7" class="text-center text-danger">Error: ' + e.message + '</td></tr>');
                }
            } else {
                console.warn('jQuery not available, retrying in 100ms...');
                setTimeout(initFetchJobs, 100);
            }
        }
        
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initFetchJobs);
        } else {
            // DOM already loaded
            setTimeout(initFetchJobs, 50);
        }
    })();
</script>
