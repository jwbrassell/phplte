<?php
/**
 * Reusable DataTable Component
 * Generates dynamic DataTables with search, sort, and export capabilities
 */

include('../config.php');

// Get configuration data
if (!isset($posted_data) || !is_array($posted_data)) {
    // Try reading from POST input if not passed directly
    $posted_data = [];
    $rawData = file_get_contents('php://input');
    if (!empty($rawData)) {
        $posted_data = json_decode($rawData, true);
    }
}

// Validate required parameters
if (!isset($posted_data['type']) || !isset($posted_data['data_directory'])) {
    echo "Please provide type and directory for datatables data.";
    return;
}

// Initialize variables with defaults
$TYPE = $posted_data['type'];
$table_id = $TYPE;
$data_key = isset($posted_data['data_key']) ? $posted_data['data_key'] : "data";
$DATA_DIR = $posted_data['data_directory'];
$row_height = isset($posted_data['row_height']) ? $posted_data['row_height'] : 'auto';
$root_data_dir = isset($posted_data['root_data_dir']) ? $posted_data['root_data_dir'] : 'data';

// Sort settings
$table_sort_column = isset($posted_data['table_sort_column']) ? $posted_data['table_sort_column'] : 0;
$table_sort_order = isset($posted_data['table_sort_order']) ? $posted_data['table_sort_order'] : "desc";

// Optional configurations
$table_id = isset($posted_data['table_id']) ? $posted_data['table_id'] : $table_id;
$exclude_columns = isset($posted_data['exclude_columns']) ? $posted_data['exclude_columns'] : array();
$fixed_width_columns = isset($posted_data['fixed_width_columns']) ? $posted_data['fixed_width_columns'] : array();

// Load data from JSON file
$file_path = __DIR__ . "/../$root_data_dir/$TYPE.json";
$file_contents = file_get_contents($file_path);
$file_data = json_decode($file_contents, true);

$table_data = $file_data[$data_key];
$headers = isset($posted_data['header_key']) ? $file_data[$posted_data['header_key']] : $file_data['headers'];
?>

<script>
var task_table_name = "";

$(document).ready(function() {
    var rowHeight = "<?php echo $row_height; ?>";
    var TYPE = "<?php echo $table_id; ?>";
    <?php echo 'var fixedWidthColumns = ' . json_encode($fixed_width_columns) . ';'; ?>

    // Process fixed width columns
    var columnWidths = {};
    if (typeof fixedWidthColumns === 'object' && fixedWidthColumns !== null && !Array.isArray(fixedWidthColumns)) {
        Object.entries(fixedWidthColumns).forEach(function([key, value]) {
            var columnIndex = parseInt(key, 10);
            if (!isNaN(columnIndex)) {
                columnWidths[columnIndex] = value;
            } else {
                console.error('Invalid column index:', key);
            }
        });
    }

    // Get excluded columns
    var exclude_columns = <?php echo json_encode($exclude_columns); ?>;

    // Initialize DataTable
    $('#table_' + TYPE + ' thead tr')
        .clone(true)
        .addClass('filters_' + TYPE)
        .appendTo('#table_' + TYPE + ' thead');

    var table = $('#table_' + TYPE).DataTable({
        order: [<?php echo $table_sort_column; ?>, "<?php echo $table_sort_order; ?>"],
        pageLength: 25,
        orderCellsTop: true,
        language: {
            emptyTable: "No data available in table"
        },
        data: <?php echo json_encode($table_data); ?>,
        initComplete: function() {
            var api = this.api();

            api.columns().each(function(colIdx) {
                var cell = $('.filters_' + TYPE + ' th').eq(
                    $(api.column(colIdx).header()).index()
                );
                $(cell).html('<input type="text" class="form-control">');

                $('input', $('.filters_' + TYPE + ' th').eq($(api.column(colIdx).header()).index()))
                    .off('keyup change')
                    .on('keyup change', function(e) {
                        e.stopPropagation();
                        $(this).attr('title', $(this).val());
                        var regexr = '({search})';
                        var cursorPosition = this.selectionStart;

                        api
                            .column(colIdx)
                            .search(
                                this.value != '' ?
                                regexr.replace('{search}', '(((' + this.value + ')))') :
                                '',
                                this.value != '',
                                this.value == ''
                            )
                            .draw();

                        $(this)
                            .focus()[0]
                            .setSelectionRange(cursorPosition, cursorPosition);
                    });
            });
        },
        dom: '<"row" <"col-sm-12 col-md-5"l><"col-sm-12 col-md-7r"B>>rt<"row" <"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        buttons: ["csv", "excel", "pdf"]
    });

    // Custom search function
    $.fn.dataTable.ext.search.push(
        function(settings, searchData, index, rowData, counter) {
            var searchValue = table.search().toLowerCase();
            if (!searchValue) return true;

            var columnData = table.cell(index, 2).node();
            var titles = $(columnData).find('a[title]').map(function() {
                return $(this).attr('title').toLowerCase();
            }).get();

            return titles.some(function(title) {
                return title.includes(searchValue);
            });
        }
    );
});
</script>

<table id="table_<?php echo $table_id; ?>" 
       name="<?php echo $table_id; ?>" 
       class="table table-bordered table-striped" 
       width="100%">
    <thead>
        <tr>
            <?php foreach ($headers as $key) { ?>
                <th><?php echo $key; ?></th>
            <?php } ?>
        </tr>
    </thead>
</table>
