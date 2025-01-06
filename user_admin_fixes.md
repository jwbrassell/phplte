# User Admin Page Analysis & Fixes

## Problem Description
The user_admin.php page is experiencing issues with DataTable initialization and duplicate headers. The table is stuck loading and not displaying properly.

## Files Affected
- portal/user_admin.php

## Root Causes
1. The table structure from datatable.php is being modified before proper initialization
2. Syntax errors in the JavaScript code (missing closing brackets)
3. Improper handling of the DataTable initialization timing
4. Incorrect placement of catch blocks and error handling

## Required Corrections

### 1. Table Loading & Structure
- Remove our predefined table structure from HTML
- Let datatable.php provide the initial table structure
- Add filter row after the table is properly loaded
- Ensure proper timing of DOM manipulation

### 2. DataTable Initialization
```javascript
function loadTable() {
    $('#rbac_pages_table_div').html(`
        <div class="text-center py-4">
            <i class="fas fa-spinner fa-spin fa-2x text-info mb-3"></i>
            <p class="text-muted mb-0">Loading pages...</p>
        </div>
    `);

    const url = 'modals/datatable.php';
    const postData = {
        type: "rbac_pages",
        data_directory: "",
        root_data_dir: "config",
        data_key: "pages_table_data",
        header_key: "pages_table_headers",
        row_height: 30
    };

    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(postData)
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.text();
    })
    .then(data => {
        // Load the complete table from datatable.php
        $('#rbac_pages_table_div').html(data);

        // Initialize DataTable
        const tableElement = $('#rbac_pages_table');
        if (!tableElement.length) {
            throw new Error('Table element not found');
        }

        // Remove any existing DataTable instance
        if ($.fn.DataTable.isDataTable(tableElement)) {
            tableElement.DataTable().destroy();
        }

        // Add filter row after the header row
        const thead = tableElement.find('thead');
        if (thead.length) {
            const filterRow = $('<tr>');
            thead.find('tr:first th').each(function(i) {
                if (i !== 4) { // Skip Actions column
                    filterRow.append(`<th><input type="text" class="form-control form-control-sm" placeholder="Filter ${$(this).text()}"></th>`);
                } else {
                    filterRow.append('<th></th>');
                }
            });
            thead.append(filterRow);
        }

        // Initialize DataTable with all options
        const table = tableElement.DataTable({
            responsive: {
                details: {
                    display: $.fn.dataTable.Responsive.display.modal({
                        header: function(row) {
                            return 'Page Details';
                        }
                    })
                }
            },
            pageLength: 10,
            ordering: true,
            searching: true,
            dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
                 "<'row'<'col-sm-12'tr>>" +
                 "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
            orderCellsTop: true,
            fixedHeader: true,
            initComplete: function() {
                // Setup column filters
                this.api().columns().every(function(i) {
                    if (i !== 4) { // Skip Actions column
                        var column = this;
                        var input = $('thead tr:eq(1) th:eq(' + i + ') input');
                        
                        input.on('keyup change', function() {
                            if (column.search() !== this.value) {
                                column.search(this.value).draw();
                            }
                        });
                    }
                });

                // Enhance search input
                $('.dataTables_filter input')
                    .addClass('form-control-sm')
                    .attr('aria-label', 'Search pages');
                
                // Enhance length select
                $('.dataTables_length select')
                    .addClass('form-control-sm')
                    .attr('aria-label', 'Entries per page');
            },
            language: {
                search: '<i class="fas fa-search"></i>',
                searchPlaceholder: 'Search pages...',
                lengthMenu: 'Show _MENU_ entries',
                info: 'Showing _START_ to _END_ of _TOTAL_ pages',
                infoEmpty: 'No pages available',
                infoFiltered: '(filtered from _MAX_ total pages)',
                zeroRecords: 'No matching pages found'
            },
            columnDefs: [
                {
                    targets: [2],
                    className: 'custom-word-wrap'
                }
            ]
        });
    })
    .catch(error => {
        console.error('Error:', error);
        $('#rbac_pages_table_div').html(`
            <div class="alert alert-danger m-3" role="alert">
                <i class="fas fa-exclamation-circle mr-2"></i>
                Failed to load pages table. Please try refreshing the page.
                <small class="d-block mt-2">${error.message}</small>
            </div>
        `);
        showToastr('error', 'Failed to load table: ' + error.message, 'Error');
    });
}
```

### 3. CSS Improvements
```css
/* Column Filter Styles */
thead input {
    width: 100%;
    padding: 3px;
    box-sizing: border-box;
}

thead tr:first-child th {
    border-bottom: none;
    padding: 8px;
}

thead tr:nth-child(2) th {
    padding: 4px;
    border-top: none;
}

.dataTables_wrapper .dataTables_filter input {
    width: auto;
    margin-left: 0.5em;
}

.dataTables_wrapper .dataTables_length select {
    width: auto;
    display: inline-block;
}

.dataTables_wrapper .dataTables_paginate .paginate_button {
    margin: 0 2px;
}

.dataTables_wrapper .dataTables_paginate .paginate_button.current {
    background: var(--info) !important;
    border-color: var(--info) !important;
    color: white !important;
}
```

## Implementation Steps
1. Update the HTML to remove predefined table structure
2. Replace the loadTable function with the corrected version
3. Update the CSS styles for better filter row appearance
4. Test the table initialization and filtering functionality
