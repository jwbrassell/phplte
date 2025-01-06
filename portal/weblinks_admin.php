<?php
require_once 'config.php';
require_once 'header.php';

// Check if user has access to admin functionality
require_once 'includes/auth.php';
if (!check_access('weblinks_admin')) {
    debug_log("Access denied to weblinks admin");
    header('Location: 403.php');
    exit();
}
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>WebLinks Administration</h1>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <!-- Statistics Card -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Statistics</h3>
                </div>
                <div class="card-body">
                    <div class="row" id="statsRow">
                        <!-- Populated dynamically -->
                    </div>
                </div>
            </div>

            <!-- Bulk Upload Card -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Bulk Upload</h3>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <form id="uploadForm" enctype="multipart/form-data">
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input" id="csvFile" accept=".csv">
                                    <label class="custom-file-label" for="csvFile">Choose CSV file</label>
                                </div>
                            </form>
                        </div>
                        <div class="col-md-6">
                            <button class="btn btn-primary" onclick="uploadCSV()">
                                <i class="fas fa-upload"></i> Upload
                            </button>
                            <a href="weblinks.php?action=download_template" class="btn btn-secondary">
                                <i class="fas fa-download"></i> Download Template
                            </a>
                        </div>
                    </div>
                    <div id="uploadResult" style="display: none;">
                        <!-- Upload results shown here -->
                    </div>
                </div>
            </div>

            <!-- Tag Management Card -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Tag Management</h3>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <select id="bulkTags" class="form-control" multiple>
                                <!-- Tags populated dynamically -->
                            </select>
                        </div>
                        <div class="col-md-6">
                            <button class="btn btn-primary" onclick="saveBulkTags()">
                                <i class="fas fa-save"></i> Save Tags
                            </button>
                        </div>
                    </div>
                    <div id="tagsDistribution">
                        <!-- Tag distribution shown here -->
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
$(document).ready(function() {
    // Initialize Select2 for bulk tags
    $('#bulkTags').select2({
        theme: 'bootstrap4',
        tags: true,
        tokenSeparators: [',', ' '],
        placeholder: 'Add or edit tags'
    });

    // Load initial data
    loadStats();
    loadTags();

    // Setup file input
    bsCustomFileInput.init();
});

// Load and display statistics
function loadStats() {
    $.get('weblinks.php?action=get_stats', function(stats) {
        const statsHtml = `
            <div class="col-md-3 col-sm-6">
                <div class="info-box">
                    <span class="info-box-icon bg-info"><i class="fas fa-link"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Total Links</span>
                        <span class="info-box-number">${stats.total_links}</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="info-box">
                    <span class="info-box-icon bg-success"><i class="fas fa-tags"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Total Tags</span>
                        <span class="info-box-number">${stats.total_tags}</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="info-box">
                    <span class="info-box-icon bg-warning"><i class="fas fa-mouse-pointer"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Total Clicks</span>
                        <span class="info-box-number">${stats.total_clicks}</span>
                    </div>
                </div>
            </div>
        `;
        $('#statsRow').html(statsHtml);

        // Display tag distribution
        const tagsHtml = `
            <h5>Tag Distribution</h5>
            <div class="row">
                ${stats.tags_distribution.map(tag => `
                    <div class="col-md-3 col-sm-6 mb-2">
                        <span class="tag-badge">${tag.name}</span>
                        <span class="badge badge-primary">${tag.count}</span>
                    </div>
                `).join('')}
            </div>
        `;
        $('#tagsDistribution').html(tagsHtml);
    });
}

// Load tags for bulk management
function loadTags() {
    $.get('weblinks.php?action=get_tags', function(tags) {
        $('#bulkTags').empty();
        tags.forEach(tag => {
            $('#bulkTags').append(new Option(tag, tag, true, true));
        });
        $('#bulkTags').trigger('change');
    });
}

// Upload CSV file
function uploadCSV() {
    const fileInput = $('#csvFile')[0];
    if (!fileInput.files.length) {
        alert('Please select a file to upload');
        return;
    }

    const formData = new FormData();
    formData.append('file', fileInput.files[0]);

    $.ajax({
        url: 'weblinks.php?action=bulk_upload',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            const resultHtml = `
                <div class="alert ${response.success ? 'alert-success' : 'alert-danger'}">
                    <h5><i class="icon fas ${response.success ? 'fa-check' : 'fa-ban'}"></i> Upload Result</h5>
                    <p>Added: ${response.added}</p>
                    <p>Updated: ${response.updated}</p>
                    <p>Skipped: ${response.skipped}</p>
                    ${response.errors.length ? `
                        <p>Errors:</p>
                        <ul>
                            ${response.errors.map(error => `<li>${error}</li>`).join('')}
                        </ul>
                    ` : ''}
                </div>
            `;
            $('#uploadResult').html(resultHtml).show();
            loadStats();
        },
        error: function(xhr) {
            alert('Error uploading file: ' + xhr.responseText);
        }
    });
}

// Save bulk tags
function saveBulkTags() {
    const tags = $('#bulkTags').val();
    if (!tags.length) {
        alert('Please add some tags');
        return;
    }

    $.ajax({
        url: 'weblinks.php?action=bulk_tags',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({ tags: tags }),
        success: function(response) {
            if (response.success) {
                alert('Tags saved successfully');
                loadStats();
                loadTags();
            } else {
                alert('Error saving tags: ' + response.error);
            }
        },
        error: function(xhr) {
            alert('Error saving tags: ' + xhr.responseText);
        }
    });
}
</script>

<?php require_once 'footer.php'; ?>
