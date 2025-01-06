// Initialize DataTable and Select2 when document is ready
$(document).ready(function() {
    // Initialize Select2 for tags
    $('#linkTags').select2({
        theme: 'bootstrap4',
        tags: true,
        tokenSeparators: [',', ' '],
        placeholder: 'Select or type tags',
        ajax: {
            url: 'weblinks.php?action=get_tags',
            processResults: function(data) {
                return {
                    results: data.map(tag => ({ id: tag, text: tag }))
                };
            }
        }
    });

    // Initialize icon selector
    $('#linkIcon').select2({
        theme: 'bootstrap4',
        templateResult: formatIconOption,
        templateSelection: formatIconOption
    });

    // Populate icon selector with Font Awesome icons
    const commonIcons = [
        'fas fa-link', 'fas fa-globe', 'fas fa-book', 'fas fa-file',
        'fas fa-code', 'fas fa-database', 'fas fa-server', 'fas fa-cloud',
        'fas fa-tools', 'fas fa-cog', 'fas fa-chart-bar', 'fas fa-table',
        'fas fa-envelope', 'fas fa-calendar', 'fas fa-users', 'fas fa-folder'
    ];

    commonIcons.forEach(icon => {
        $('#linkIcon').append(new Option(icon, icon));
    });

    // Initialize DataTable
    $('#linksTable').DataTable({
        ajax: {
            url: 'weblinks.php?action=get_links',
            dataSrc: ''
        },
        columns: [
            { 
                data: 'icon',
                render: function(data) {
                    return `<i class="${data}"></i>`;
                }
            },
            { data: 'title' },
            { 
                data: 'url',
                render: function(data, type, row) {
                    return `<a href="${data}" target="_blank" onclick="recordClick(${row.id})">${data}</a>`;
                }
            },
            { 
                data: 'tags',
                render: function(data) {
                    return data.map(tag => 
                        `<span class="tag-badge">${tag}</span>`
                    ).join('');
                }
            },
            { data: 'created_by' },
            {
                data: null,
                render: function(data) {
                    return `
                        <div class="link-actions">
                            <button class="btn btn-sm btn-info" onclick="viewLink(${data.id})">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-primary" onclick="editLink(${data.id})">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>
                    `;
                }
            }
        ],
        order: [[1, 'asc']],
        responsive: true
    });

    // Load common links
    loadCommonLinks();
});

// Format icon options in select
function formatIconOption(icon) {
    if (!icon.id) return icon.text;
    return $(`<span><i class="${icon.id}"></i> ${icon.id}</span>`);
}

// Load and display common links
function loadCommonLinks() {
    $.get('weblinks.php?action=get_common_links', function(links) {
        const container = $('#commonLinks');
        container.empty();
        
        links.forEach(link => {
            container.append(`
                <div class="link-card">
                    <a href="${link.url}" target="_blank" onclick="recordClick(${link.id})">
                        <i class="${link.icon} link-icon"></i>
                        <span class="link-title">${link.title}</span>
                    </a>
                    <div class="link-meta">
                        ${link.tags.map(tag => 
                            `<span class="tag-badge">${tag}</span>`
                        ).join('')}
                    </div>
                </div>
            `);
        });
    });
}

// Record link click
function recordClick(id) {
    $.get(`weblinks.php?action=record_click&id=${id}`);
}

// Show add link modal
function showAddLinkModal() {
    $('#linkId').val('');
    $('#linkForm')[0].reset();
    $('#linkTags').val(null).trigger('change');
    $('#linkIcon').val('fas fa-link').trigger('change');
    $('#linkModalTitle').text('Add Link');
    $('#linkModal').modal('show');
}

// Show edit link modal
function editLink(id) {
    $.get(`weblinks.php?action=get_link&id=${id}`, function(link) {
        $('#linkId').val(link.id);
        $('#linkUrl').val(link.url);
        $('#linkTitle').val(link.title);
        $('#linkDescription').val(link.description);
        $('#linkIcon').val(link.icon).trigger('change');
        
        // Set tags
        const tagOptions = link.tags.map(tag => ({
            id: tag,
            text: tag
        }));
        $('#linkTags').empty().select2({
            theme: 'bootstrap4',
            data: tagOptions
        });
        $('#linkTags').val(link.tags).trigger('change');
        
        $('#linkModalTitle').text('Edit Link');
        $('#linkModal').modal('show');
    });
}

// Show link details modal
function viewLink(id) {
    $.get(`weblinks.php?action=get_link&id=${id}`, function(link) {
        const details = `
            <div class="mb-3">
                <h5><i class="${link.icon}"></i> ${link.title}</h5>
                <a href="${link.url}" target="_blank" onclick="recordClick(${link.id})">${link.url}</a>
            </div>
            <div class="mb-3">
                <strong>Description:</strong>
                <p>${link.description || 'No description'}</p>
            </div>
            <div class="mb-3">
                <strong>Tags:</strong><br>
                ${link.tags.map(tag => 
                    `<span class="tag-badge">${tag}</span>`
                ).join('')}
            </div>
            <div class="mb-3">
                <strong>Created by:</strong> ${link.created_by}<br>
                <strong>Created at:</strong> ${new Date(link.created_at).toLocaleString()}<br>
                <strong>Last updated:</strong> ${new Date(link.updated_at).toLocaleString()}<br>
                <strong>Click count:</strong> ${link.click_count}
            </div>
        `;
        $('#linkDetails').html(details);
        
        // Format history
        const history = link.history.map(entry => `
            <div class="history-entry">
                <div class="change-time">
                    ${new Date(entry.changed_at).toLocaleString()} by ${entry.changed_by}
                </div>
                <div class="change-details">
                    ${Object.entries(entry.changes).map(([field, change]) => `
                        <div>
                            <span class="change-field">${field}:</span>
                            <span class="change-old">${Array.isArray(change.old) ? change.old.join(', ') : change.old}</span>
                            →
                            <span class="change-new">${Array.isArray(change.new) ? change.new.join(', ') : change.new}</span>
                        </div>
                    `).join('')}
                </div>
            </div>
        `).join('');
        $('#linkHistory').html(history);
        
        $('#viewLinkModal').modal('show');
    });
}

// Save link (create or update)
function saveLink() {
    const id = $('#linkId').val();
    const data = {
        url: $('#linkUrl').val(),
        title: $('#linkTitle').val(),
        description: $('#linkDescription').val(),
        icon: $('#linkIcon').val(),
        tags: $('#linkTags').val() || []
    };
    
    const method = id ? 'PUT' : 'POST';
    const url = id ? `weblinks.php?action=update_link&id=${id}` : 'weblinks.php?action=create_link';
    
    $.ajax({
        url: url,
        method: method,
        contentType: 'application/json',
        data: JSON.stringify(data),
        success: function(response) {
            $('#linkModal').modal('hide');
            $('#linksTable').DataTable().ajax.reload();
            loadCommonLinks();
        },
        error: function(xhr) {
            alert('Error saving link: ' + xhr.responseText);
        }
    });
}
