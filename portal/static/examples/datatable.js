$(document).ready(function() {
    // Add the search input row first
    $('#datatable-list-view thead tr')
        .clone(true)
        .addClass('filters')
        .appendTo('#datatable-list-view thead');

    // Initialize DataTable
    var table = $('#datatable-list-view').DataTable({
        orderCellsTop: true,
        fixedHeader: true,
        responsive: true,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
        dom: '<"row"<"col-sm-6"l><"col-sm-6 text-right"B>><"row"<"col-sm-12"tr>><"row"<"col-sm-5"i><"col-sm-7"p>>',
        buttons: [
            {
                extend: 'csv',
                className: 'btn btn-sm btn-secondary',
                text: '<i class="fas fa-file-csv"></i> CSV'
            },
            {
                extend: 'excel',
                className: 'btn btn-sm btn-secondary',
                text: '<i class="fas fa-file-excel"></i> Excel'
            },
            {
                extend: 'pdf',
                className: 'btn btn-sm btn-secondary',
                text: '<i class="fas fa-file-pdf"></i> PDF'
            },
            {
                extend: 'print',
                className: 'btn btn-sm btn-secondary',
                text: '<i class="fas fa-print"></i> Print'
            }
        ],
        initComplete: function() {
            var api = this.api();
            
            // For each column
            api.columns().eq(0).each(function(colIdx) {
                // Set the header cell to contain the input element
                var cell = $('.filters th').eq(
                    $(api.column(colIdx).header()).index()
                );
                var title = $(cell).text();
                $(cell).html('<input type="text" class="form-control form-control-sm" placeholder="Search ' + title + '" />');
                
                // On keyup and change, filter the column
                $('input', $('.filters th').eq($(api.column(colIdx).header()).index()))
                    .off('keyup change')
                    .on('keyup change', function (e) {
                        e.stopPropagation();
                        $(this).attr('title', $(this).val());
                        var regexr = '({search})';
                        
                        api
                            .column(colIdx)
                            .search(
                                this.value != ''
                                    ? regexr.replace('{search}', '(((' + this.value + ')))')
                                    : '',
                                this.value != '',
                                this.value == ''
                            )
                            .draw();
                    });
            });
        }
    });
});
