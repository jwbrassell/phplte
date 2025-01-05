<?php
require_once(__DIR__ . "/includes/init.php");
require_once(__DIR__ . "/includes/auth.php");

// Ensure user has admin access
if (!in_array('admin', $adom_groups)) {
    header("Location: 403.php");
    exit;
}

$PAGE_TITLE = "System Logs";
require_once("header.php");
?>

<!-- Content Wrapper -->
<div class="content-wrapper">
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">System Logs</h1>
                </div>
            </div>
        </div>
    </div>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Log Entries</h3>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <select id="logType" class="form-control">
                                        <option value="all">All Logs</option>
                                        <option value="access">Access Logs</option>
                                        <option value="errors">Error Logs</option>
                                        <option value="client">Client Logs</option>
                                        <option value="python">Python Logs</option>
                                        <option value="audit">Audit Logs</option>
                                        <option value="performance">Performance Logs</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <input type="date" id="dateFilter" class="form-control" value="2024-01-05">
                                </div>
                            </div>
                            <table id="logsTable" class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>Timestamp</th>
                                        <th>Type</th>
                                        <th>User</th>
                                        <th>Message</th>
                                        <th>Details</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
$(document).ready(function() {
    let loadingTimer;
    
    var table = $('#logsTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'api/get_logs.php',
            type: 'POST',
            data: function(d) {
                d.logType = $('#logType').val();
                d.dateFilter = $('#dateFilter').val();
            },
            beforeSend: function() {
                // Clear any existing timer
                if (loadingTimer) clearTimeout(loadingTimer);
                
                // Show loading after a slight delay to prevent flashing
                loadingTimer = setTimeout(() => {
                    Swal.fire({
                        title: 'Loading Logs',
                        text: 'Please wait while we fetch the log entries...',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                }, 250);
            },
            error: function(xhr, error, thrown) {
                // Clear loading timer and close any open alerts
                if (loadingTimer) clearTimeout(loadingTimer);
                Swal.close();
                
                console.error('DataTables error:', {
                    status: xhr.status,
                    statusText: xhr.statusText,
                    responseText: xhr.responseText,
                    error: error,
                    thrown: thrown
                });
                
                let errorMessage = 'Failed to load log entries. ';
                let errorDetails = '';
                
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.error) {
                        errorMessage += response.error;
                        errorDetails = `Status: ${xhr.status}\nResponse: ${JSON.stringify(response, null, 2)}`;
                    }
                } catch (e) {
                    errorMessage += `Server error (${xhr.status}): ${xhr.statusText}`;
                    errorDetails = `Error: ${error}\nDetails: ${thrown}`;
                }
                
                // Show error to user
                Swal.fire({
                    icon: 'error',
                    title: 'Error Loading Logs',
                    html: `<p>${errorMessage}</p>
                          <pre class="text-left" style="margin-top: 1em; background: #f8f9fa; padding: 1em;">
                          ${errorDetails}</pre>`,
                    confirmButtonText: 'Retry',
                    showCancelButton: true,
                    cancelButtonText: 'Close',
                    customClass: {
                        popup: 'swal-wide',
                        content: 'text-left'
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        table.ajax.reload();
                    }
                });
            },
            complete: function(response) {
                // Clear loading timer and close any open alerts
                if (loadingTimer) clearTimeout(loadingTimer);
                Swal.close();
                
                // Check if we got a "no data" message
                try {
                    const data = JSON.parse(response.responseText);
                    if (data.message && data.recordsTotal === 0) {
                        // Show message in the table
                        $('#logsTable tbody').html(
                            '<tr class="odd"><td valign="top" colspan="5" class="dataTables_empty">' + 
                            data.message + '</td></tr>'
                        );
                    }
                } catch (e) {
                    // JSON parse error, ignore
                }
            }
        },
        pageLength: 25,
        columns: [
            { 
                data: 'timestamp',
                render: function(data) {
                    return data || 'N/A';
                }
            },
            { 
                data: 'type',
                render: function(data) {
                    return data || 'unknown';
                }
            },
            { 
                data: 'user',
                render: function(data) {
                    return data || 'system';
                }
            },
            { 
                data: 'message',
                render: function(data) {
                    return data || '';
                }
            },
            { 
                data: 'details',
                render: function(data, type, row) {
                    if (type === 'display') {
                        try {
                            // If data is already a string, parse it
                            const details = typeof data === 'string' ? JSON.parse(data) : data;
                            // Filter out null values and format nested objects
                            const cleanDetails = {};
                            Object.entries(details).forEach(([key, value]) => {
                                if (value !== null) {
                                    if (typeof value === 'object') {
                                        cleanDetails[key] = JSON.stringify(value, null, 2);
                                    } else {
                                        cleanDetails[key] = value;
                                    }
                                }
                            });
                            if (Object.keys(cleanDetails).length === 0) {
                                return '<span class="text-muted">No details</span>';
                            }
                            return '<button type="button" class="btn btn-info btn-sm" onclick=\'showDetails(' + 
                                   JSON.stringify(cleanDetails) + ')\'><i class="fas fa-info-circle"></i></button>';
                        } catch (e) {
                            console.error('Error parsing details:', e);
                            console.error('Raw data:', data);
                            return '<span class="text-danger">Invalid JSON</span>';
                        }
                    }
                    return data;
                }
            }
        ],
        order: [[0, 'desc']],
        dom: '<"row"<"col-sm-12 col-md-6"B><"col-sm-12 col-md-6"f>>rtip',
        buttons: [
            {
                extend: 'collection',
                text: 'Export',
                buttons: ['copy', 'csv', 'excel', 'pdf']
            }
        ],
        language: {
            emptyTable: "No log entries found for the selected date and type",
            zeroRecords: "No matching log entries found",
            processing: '<i class="fa fa-spinner fa-spin fa-3x fa-fw"></i><span class="sr-only">Loading...</span>'
        }
    });

    // Debug DataTables response
    table.on('xhr', function() {
        var json = table.ajax.json();
        console.log('DataTables response:', json);
    });

    // Refresh table when filters change
    $('#logType, #dateFilter').change(function() {
        console.log('Filter changed:', {
            logType: $('#logType').val(),
            dateFilter: $('#dateFilter').val()
        });
        table.ajax.reload();
    });
});

function showDetails(details) {
    // Format details for display
    const formattedDetails = Object.entries(details)
        .map(([key, value]) => {
            try {
                // Try to parse the value as JSON if it's a string
                if (typeof value === 'string' && value.trim().startsWith('{')) {
                    const parsed = JSON.parse(value);
                    return `${key}:\n${JSON.stringify(parsed, null, 2)}`;
                }
            } catch (e) {
                // If parsing fails, use the value as-is
            }
            return `${key}: ${value}`;
        })
        .join('\n');

    Swal.fire({
        title: 'Log Details',
        html: '<pre class="text-left" style="max-height: 400px; overflow-y: auto;">' + 
              formattedDetails + '</pre>',
        width: '800px',
        customClass: {
            popup: 'swal-wide',
            content: 'text-left'
        }
    });
}
</script>

<?php require_once("footer.php"); ?>
