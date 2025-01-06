<?php
require_once __DIR__ . '/config.php';  // Get $APP variable first
require_once __DIR__ . '/includes/init.php';  // This will handle session start
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/OnCallCalendar.php';

// Ensure user is authenticated
if (!isset($_SESSION[$APP."_user_name"])) {
    header('Location: login.php');
    exit;
}

// Initialize calendar manager
$calendar = new OnCallCalendar();

// Get teams for dropdown
try {
    $teamsResult = $calendar->getTeams();
    $teams = $teamsResult['teams'] ?? [];
} catch (Exception $e) {
    error_log("Error fetching teams: " . $e->getMessage());
    $teams = [];
}

// Check if user is admin
$isAdmin = isset($_SESSION[$APP."_adom_groups"]) && 
          in_array('admin', array_map('trim', explode(',', $_SESSION[$APP."_adom_groups"])));

$pageTitle = "On-Call Schedule";
require_once __DIR__ . '/header.php';
?>

<style>
    /* Calendar Styles */
    .calendar-day {
        height: 120px;
        padding: 10px !important;
        vertical-align: top !important;
    }

    .calendar-day.disabled {
        background-color: #f8f9fa;
    }

    .calendar-day.today {
        background-color: rgba(0,0,0,.05);
    }

    .calendar-day .date {
        font-size: 1.2em;
        font-weight: bold;
        margin-bottom: 5px;
    }

    .calendar-day .event {
        padding: 5px;
        margin-bottom: 5px;
        border-radius: 3px;
        font-size: 0.85em;
        word-break: break-word;
    }

    /* Team Colors */
    .bg-primary { background-color: #007bff !important; }
    .bg-secondary { background-color: #6c757d !important; }
    .bg-success { background-color: #28a745 !important; }
    .bg-danger { background-color: #dc3545 !important; }
    .bg-warning { background-color: #ffc107 !important; }
    .bg-info { background-color: #17a2b8 !important; }

    /* Holiday Events */
    .holiday-event {
        border-radius: 0;
        opacity: 0.3;
    }

    /* Modal Fixes */
    .modal-backdrop {
        z-index: 1050;
    }
    .modal {
        z-index: 1055;
    }
    
    /* Loading States */
    .btn:disabled {
        cursor: not-allowed;
        opacity: 0.65;
    }

    /* Calendar Navigation */
    #current-month {
        min-width: 200px;
    }

    /* Responsive Calendar */
    @media (max-width: 768px) {
        .calendar-day {
            height: auto;
            min-height: 100px;
        }
        
        .calendar-day .event {
            font-size: 0.75em;
        }
    }
</style>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>On-Call Schedule</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                        <li class="breadcrumb-item active">On-Call Schedule</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-3">
                    <div class="sticky-top mb-3">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Current On-Call</h3>
                            </div>
                            <div class="card-body">
                                <div class="btn-group w-100 mb-3">
                                    <button class="btn btn-info">
                                        <i class="fas fa-user mr-2"></i>
                                        <span id="current-name">No one currently on call</span>
                                    </button>
                                    <button class="btn btn-outline-info">
                                        <i class="fas fa-phone mr-1"></i>
                                        <span id="current-phone">-</span>
                                    </button>
                                </div>
                                <div class="form-group">
                                    <label for="team-filter" class="control-label">Filter by Team</label>
                                    <select class="form-control" id="team-filter">
                                        <option value="">All Teams</option>
                                        <?php foreach ($teams as $team): ?>
                                        <option value="<?= htmlspecialchars($team['id']) ?>">
                                            <?= htmlspecialchars($team['name']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <?php if ($isAdmin): ?>
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Upload Schedule</h3>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($teams)): ?>
                                <button type="button" class="btn btn-primary btn-block mb-3" data-toggle="modal" data-target="#uploadModal">
                                    <i class="fas fa-upload mr-2"></i>Upload Schedule
                                </button>
                                <div class="dropdown mb-3">
                                    <button class="btn btn-secondary btn-block dropdown-toggle" type="button" id="templateDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        <i class="fas fa-download mr-2"></i>Download Template
                                    </button>
                                    <div class="dropdown-menu w-100" aria-labelledby="templateDropdown">
                                        <a class="dropdown-item" href="static/templates/oncall_template.csv">
                                            <i class="fas fa-file-csv mr-2"></i>Manual Schedule Template
                                        </a>
                                        <a class="dropdown-item" href="static/templates/oncall_auto_template.csv">
                                            <i class="fas fa-file-csv mr-2"></i>Auto Schedule Template
                                        </a>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-success btn-block mb-3" data-toggle="modal" data-target="#holidayModal">
                                    <i class="fas fa-calendar mr-2"></i>Manage Holidays
                                </button>
                                <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle mr-2"></i>
                                    Please create a team first before uploading a schedule.
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h3 class="card-title">Teams</h3>
                                <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#teamModal">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                            <div class="card-body p-0">
                                <div class="list-group list-group-flush" id="team-list">
                                    <?php foreach ($teams as $team): ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="fas fa-circle text-<?= htmlspecialchars($team['color']) ?> mr-2"></i>
                                            <?= htmlspecialchars($team['name']) ?>
                                        </div>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-secondary" onclick="editTeam(<?= htmlspecialchars($team['id']) ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-danger" onclick="deleteTeam(<?= htmlspecialchars($team['id']) ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                    <?php if (empty($teams)): ?>
                                    <div class="list-group-item text-center text-muted">
                                        No teams created yet
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-9">
                    <div class="card card-primary">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="btn-group">
                                    <button type="button" id="prev-month" class="btn btn-default">
                                        <i class="fas fa-chevron-left"></i>
                                    </button>
                                    <button type="button" id="current-month" class="btn btn-default">
                                        December 2024
                                    </button>
                                    <button type="button" id="next-month" class="btn btn-default">
                                        <i class="fas fa-chevron-right"></i>
                                    </button>
                                </div>
                                <button type="button" id="today" class="btn btn-default">Today</button>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div id="calendar"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php if ($isAdmin): ?>
    <!-- Upload Modal -->
    <div class="modal fade" id="uploadModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="upload-form" class="ajax-form" method="POST" enctype="multipart/form-data">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-upload mr-2"></i>Upload Schedule
                        </h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="team" class="control-label">Team</label>
                            <select class="form-control" id="team" name="team" required>
                                <?php foreach ($teams as $team): ?>
                                <option value="<?= htmlspecialchars($team['id']) ?>">
                                    <?= htmlspecialchars($team['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="year" class="control-label">Year</label>
                            <input type="number" class="form-control" id="year" name="year" 
                                value="<?= date('Y') ?>" required>
                        </div>
                        <div class="form-group">
                                <div class="form-group">
                                    <label for="schedule_type" class="control-label">Schedule Type</label>
                                    <select class="form-control" id="schedule_type" name="schedule_type">
                                        <option value="manual">Manual Schedule</option>
                                        <option value="auto">Auto-Generate Schedule</option>
                                        <option value="auto_weekly">Auto Weekly Rotation</option>
                                    </select>
                                    <small class="form-text text-muted d-block schedule-help" data-type="manual">
                                        Upload a CSV with week, name, and phone columns for manual scheduling
                                    </small>
                                    <small class="form-text text-muted d-block schedule-help" data-type="auto" style="display: none;">
                                        Upload a CSV with just names and phone numbers to auto-generate a fair schedule
                                    </small>
                                    <small class="form-text text-muted d-block schedule-help" data-type="auto_weekly" style="display: none;">
                                        Upload a CSV with names and phone numbers for automatic weekly rotation
                                    </small>
                                </div>
                            <label for="file" class="control-label">CSV File</label>
                            <input type="file" class="form-control" id="file" name="file" 
                                accept=".csv" required>
                            <div class="form-text" id="csv-help-text">
                                Required columns: week, name, phone
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="upload-button">
                            <i class="fas fa-upload mr-2"></i>Upload
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Holiday Modal -->
    <div class="modal fade" id="holidayModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-calendar mr-2"></i>Manage Holidays
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="holiday-form" class="ajax-form" method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="holiday-file" class="control-label">Upload Holidays CSV</label>
                            <input type="file" class="form-control" id="holiday-file" name="file" 
                                accept=".csv" required>
                            <div class="form-text">
                                Required columns: name, date (YYYY-MM-DD)
                            </div>
                        </div>
                        <div class="d-flex justify-content-between">
                            <a href="static/templates/holidays_template.csv" class="btn btn-secondary">
                                <i class="fas fa-download mr-2"></i>Download Template
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-upload mr-2"></i>Upload Holidays
                            </button>
                        </div>
                    </form>
                    <hr>
                    <div id="current-holidays" class="mt-3">
                        <h6>Current Holidays</h6>
                        <div class="list-group" id="holiday-list">
                            <!-- Holidays will be loaded here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Team Modal -->
    <div class="modal fade" id="teamModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="team-form" class="ajax-form">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-users mr-2"></i><span id="team-modal-title">Add Team</span>
                        </h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="team-id">
                        <div class="form-group">
                            <label for="team-name" class="control-label">Team Name</label>
                            <input type="text" class="form-control" id="team-name" required>
                        </div>
                        <div class="form-group">
                            <label class="control-label">Team Color</label>
                            <div class="btn-group w-100" role="group">
                                <?php foreach (['primary', 'secondary', 'success', 'danger', 'warning', 'info'] as $color): ?>
                                <input type="radio" class="btn-check" name="team-color" id="color-<?= $color ?>" 
                                       value="<?= $color ?>" <?= $color === 'primary' ? 'checked' : '' ?>>
                                <label class="btn btn-outline-<?= $color ?>" for="color-<?= $color ?>">
                                    <i class="fas fa-circle"></i>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script src="plugins/fullcalendar/main.min.js"></script>
<script src="plugins/moment/moment.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize FullCalendar
    var calendarEl = document.getElementById('calendar');
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: false,
        height: 'auto',
        events: function(info, successCallback, failureCallback) {
            // Get team filter
            var teamId = document.getElementById('team-filter').value;
            
            // Fetch events from API
            fetch(`api/oncall.php?endpoint=events&start=${info.startStr}&end=${info.endStr}${teamId ? '&team=' + teamId : ''}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        failureCallback(data.error);
                    } else {
                        successCallback(data.events || []);
                    }
                })
                .catch(error => failureCallback(error));
        },
        eventDidMount: function(info) {
            // Add tooltips
            if (info.event.extendedProps.phone) {
                info.el.title = `Phone: ${info.event.extendedProps.phone}`;
            }
        }
    });
    
    calendar.render();
    
    // Calendar navigation
    document.getElementById('prev-month').addEventListener('click', function() {
        calendar.prev();
        updateCurrentMonth();
    });
    
    document.getElementById('next-month').addEventListener('click', function() {
        calendar.next();
        updateCurrentMonth();
    });
    
    document.getElementById('today').addEventListener('click', function() {
        calendar.today();
        updateCurrentMonth();
    });
    
    function updateCurrentMonth() {
        var date = calendar.getDate();
        document.getElementById('current-month').textContent = moment(date).format('MMMM YYYY');
    }
    
    updateCurrentMonth();
    
    // Team filter
    document.getElementById('team-filter').addEventListener('change', function() {
        calendar.refetchEvents();
        updateCurrentOnCall();
    });
    
    // Current on-call updater
    function updateCurrentOnCall() {
        var teamId = document.getElementById('team-filter').value;
        fetch(`api/oncall.php?endpoint=current${teamId ? '&team=' + teamId : ''}`)
            .then(response => response.json())
            .then(data => {
                document.getElementById('current-name').textContent = data.name;
                document.getElementById('current-phone').textContent = data.phone;
            })
            .catch(console.error);
    }
    
    // Update current on-call every minute
    updateCurrentOnCall();
    setInterval(updateCurrentOnCall, 60000);
    
    <?php if ($isAdmin): ?>
    // Schedule type handling
    document.getElementById('schedule_type').addEventListener('change', function() {
        var selectedType = this.value;
        var helpText = document.getElementById('csv-help-text');
        
        // Hide all help texts first
        document.querySelectorAll('.schedule-help').forEach(el => el.style.display = 'none');
        
        // Show relevant help text
        document.querySelector(`.schedule-help[data-type="${selectedType}"]`).style.display = 'block';
        
        switch(selectedType) {
            case 'manual':
                helpText.textContent = 'Required columns: week, name, phone';
                break;
            case 'auto':
            case 'auto_weekly':
                helpText.textContent = 'Required columns: name, phone';
                break;
        }
    });
    
    document.getElementById('upload-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        var formData = new FormData(this);
        var button = document.getElementById('upload-button');
        var scheduleType = document.getElementById('schedule_type').value;
        
        // Add schedule type to form data
        formData.append('schedule_type', scheduleType);
        button.disabled = true;
        
        fetch('api/oncall.php?endpoint=upload', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert(data.error);
            } else {
                alert(data.message);
                calendar.refetchEvents();
                $('#uploadModal').modal('hide');
            }
        })
        .catch(error => alert('Upload failed: ' + error))
        .finally(() => button.disabled = false);
    });
    
    // Holiday form handling
    document.getElementById('holiday-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        var formData = new FormData(this);
        
        fetch('api/oncall.php?endpoint=holidays', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert(data.error);
            } else {
                alert(data.message);
                calendar.refetchEvents();
                loadHolidays();
                $('#holidayModal').modal('hide');
            }
        })
        .catch(error => alert('Upload failed: ' + error));
    });
    
    // Team form handling
    document.getElementById('team-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        var teamId = document.getElementById('team-id').value;
        var name = document.getElementById('team-name').value;
        var color = document.querySelector('input[name="team-color"]:checked').value;
        
        var method = teamId ? 'PUT' : 'POST';
        var data = teamId ? { id: teamId, name, color } : { name, color };
        
        fetch('api/oncall.php?endpoint=teams', {
            method: method,
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert(data.error);
            } else {
                location.reload();
            }
        })
        .catch(error => alert('Operation failed: ' + error));
    });
    
    // Load current holidays
    function loadHolidays() {
        var year = new Date().getFullYear();
        fetch(`api/oncall.php?endpoint=holidays&year=${year}`)
            .then(response => response.json())
            .then(data => {
                var list = document.getElementById('holiday-list');
                list.innerHTML = '';
                
                if (data.holidays && data.holidays.length > 0) {
                    data.holidays.forEach(holiday => {
                        var date = new Date(holiday.date).toLocaleDateString();
                        list.innerHTML += `
                            <div class="list-group-item">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1">${holiday.name}</h6>
                                    <small>${date}</small>
                                </div>
                            </div>
                        `;
                    });
                } else {
                    list.innerHTML = '<div class="list-group-item text-center text-muted">No holidays found</div>';
                }
            })
            .catch(console.error);
    }
    
    loadHolidays();
    <?php endif; ?>
});

<?php if ($isAdmin): ?>
function editTeam(teamId) {
    // Find team data
    var teams = <?= json_encode($teams) ?>;
    var team = teams.find(t => t.id === teamId);
    if (!team) return;
    
    // Populate form
    document.getElementById('team-id').value = team.id;
    document.getElementById('team-name').value = team.name;
    document.querySelector(`input[name="team-color"][value="${team.color}"]`).checked = true;
    
    // Update modal title
    document.getElementById('team-modal-title').textContent = 'Edit Team';
    
    // Show modal
    $('#teamModal').modal('show');
}

function deleteTeam(teamId) {
    if (!confirm('Are you sure you want to delete this team?')) return;
    
    fetch('api/oncall.php?endpoint=teams', {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ id: teamId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            alert(data.error);
        } else {
            location.reload();
        }
    })
    .catch(error => alert('Delete failed: ' + error));
}
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
