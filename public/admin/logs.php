<?php
require_once dirname(dirname(__FILE__)) . '/config/config.php';
require_once dirname(dirname(__FILE__)) . '/private/includes/auth/auth.php';
require_once dirname(dirname(__FILE__)) . '/private/includes/components/datatables/DataTableComponent.php';

// Ensure user has admin access
if (!in_array('admin', $adom_groups)) {
    header("Location: ../403.php");
    exit;
}

// Function to get available log dates and types
function getLogInfo() {
    $logDir = dirname(dirname(dirname(__FILE__))) . '/logs/system';
    $dates = [];
    $types = [];
    
    if (is_dir($logDir)) {
        // Get all log types (directories)
        foreach (scandir($logDir) as $type) {
            if ($type === '.' || $type === '..') continue;
            
            $typeDir = $logDir . '/' . $type;
            if (!is_dir($typeDir)) continue;
            
            $types[] = $type;
            
            // Get all dates for this type
            foreach (scandir($typeDir) as $file) {
                if (preg_match('/^(\d{4}-\d{2}-\d{2})\.json$/', $file, $matches)) {
                    $dates[$matches[1]] = true;
                }
            }
        }
    }
    
    // Convert dates to array and sort descending
    $dates = array_keys($dates);
    rsort($dates);
    
    // Sort types alphabetically
    sort($types);
    
    return [
        'dates' => $dates,
        'types' => $types,
        'latest_date' => !empty($dates) ? $dates[0] : date('Y-m-d')
    ];
}

$logInfo = getLogInfo();
$PAGE_TITLE = "System Logs";
require_once dirname(dirname(__FILE__)) . '/includes/header.php';
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <h1>System Logs</h1>
        </div>
    </section>

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
                                    <select id="logType" class="form-control select2">
                                        <option value="all">All Logs</option>
                                        <?php foreach ($logInfo['types'] as $type): ?>
                                        <option value="<?= htmlspecialchars($type) ?>"<?= isset($_GET['type']) && $_GET['type'] === $type ? ' selected' : '' ?>>
                                            <?= ucfirst(htmlspecialchars($type)) ?> Logs
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <select id="dateFilter" class="form-control select2">
                                        <?php foreach ($logInfo['dates'] as $date): ?>
                                        <option value="<?= htmlspecialchars($date) ?>"<?= isset($_GET['date']) && $_GET['date'] === $date ? ' selected' : ($date === $logInfo['latest_date'] && !isset($_GET['date']) ? ' selected' : '') ?>>
                                            <?= htmlspecialchars($date) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
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
$(function () {
    // Initialize Select2
    $('.select2').select2({
        theme: 'bootstrap4'
    });

    // Initialize DataTable with custom settings
    var table = $('#logsTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '../api/get_logs.php',
            type: 'POST',
            data: function(d) {
                d.logType = $('#logType').val();
                d.dateFilter = $('#dateFilter').val();
            }
        },
        columns: [
            { 
                data: 'timestamp',
                render: function(data) {
                    return data ? moment(data).format('YYYY-MM-DD HH:mm:ss') : 'N/A';
                }
            },
            { 
                data: 'type',
                render: function(data) {
                    return data ? data.charAt(0).toUpperCase() + data.slice(1) : 'unknown';
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
                            if (!data || Object.keys(data).length === 0) {
                                return '<span class="text-muted">No details</span>';
                            }
                            return '<button type="button" class="btn btn-info btn-sm" onclick=\'showDetails(' + 
                                   JSON.stringify(data) + ')\'><i class="fas fa-info-circle"></i></button>';
                    }
                    return data;
                }
            }
        ],
        order: [[0, 'desc']],
        pageLength: 25,
        dom: 'Bfrtip',
        buttons: ['copy', 'csv', 'excel', 'pdf', 'print']
    });

    // Refresh table when filters change
    $('#logType, #dateFilter').change(function() {
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

<?php require_once dirname(dirname(__FILE__)) . '/includes/footer.php'; ?>
