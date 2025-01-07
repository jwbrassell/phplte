<?php
/**
 * DataTableComponent
 * Reusable component for rendering tables with Python data processing
 */
class DataTableComponent {
    private $pythonProcessor;
    private $pythonPath;
    private $processorScript;
    private $defaultConfig;

    public function __construct() {
        // Use virtual environment if available
        $this->pythonPath = VENV_DIR 
            ? VENV_DIR . '/bin/python3'
            : '/usr/bin/python3';
            
        // Set path to Python processor script
        $this->processorScript = dirname(dirname(dirname(__FILE__))) . 
            '/scripts/modules/data_processing/table_processor.py';
            
        // Set default DataTable configuration
        $this->defaultConfig = [
            'pageLength' => 10,
            'lengthMenu' => [[10, 25, 50, -1], [10, 25, 50, 'All']],
            'responsive' => true,
            'dom' => 'Bfrtip',
            'buttons' => ['copy', 'csv', 'excel', 'pdf', 'print'],
            'order' => [[0, 'asc']],
            'processing' => true,
            'serverSide' => false
        ];
        
        // Verify Python processor exists
        if (!file_exists($this->processorScript)) {
            throw new Exception('Data processor script not found: ' . $this->processorScript);
        }
    }

    /**
     * Process data through Python processor
     */
    private function processPythonData($command, $data, $options = null) {
        // Prepare command
        $jsonData = escapeshellarg(json_encode($data));
        $cmd = sprintf('%s %s %s %s',
            escapeshellarg($this->pythonPath),
            escapeshellarg($this->processorScript),
            escapeshellarg($command),
            $jsonData
        );
        
        // Add options if provided
        if ($options !== null) {
            $cmd .= ' ' . escapeshellarg(json_encode($options));
        }
        
        // Set PYTHONPATH to include modules directory
        $modulesDir = dirname(dirname($this->processorScript));
        $envStr = 'PYTHONPATH=' . escapeshellarg($modulesDir);
        
        // Execute command
        $output = [];
        $returnVar = 0;
        exec($envStr . ' ' . $cmd . ' 2>&1', $output, $returnVar);
        
        if ($returnVar !== 0) {
            throw new Exception('Failed to process data: ' . implode("\n", $output));
        }
        
        // Parse JSON response
        $result = json_decode(implode("\n", $output), true);
        if ($result === null) {
            throw new Exception('Invalid JSON response from processor');
        }
        
        return $result;
    }

    /**
     * Render table with dictionary data
     */
    public function renderDictionaryTable($data, $config = []) {
        try {
            // Process data through Python
            $processed = $this->processPythonData('process_dict', $data);
            
            // Generate unique table ID
            $tableId = 'datatable_' . uniqid();
            
            // Merge configurations
            $tableConfig = array_merge($this->defaultConfig, $config);
            $tableConfig['columns'] = $processed['columns'];
            
            // Start output buffering
            ob_start();
            ?>
            <table id="<?php echo $tableId; ?>" class="table table-striped table-bordered">
                <thead>
                    <tr>
                        <?php foreach ($processed['columns'] as $column): ?>
                        <th><?php echo htmlspecialchars($column['title']); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($processed['data'] as $row): ?>
                    <tr>
                        <?php foreach ($processed['columns'] as $column): ?>
                        <td><?php echo htmlspecialchars($row[$column['data']] ?? ''); ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <script>
            $(document).ready(function() {
                $('#<?php echo $tableId; ?>').DataTable(<?php echo json_encode($tableConfig); ?>);
            });
            </script>
            <?php
            return ob_get_clean();
            
        } catch (Exception $e) {
            error_log('DataTable Error: ' . $e->getMessage());
            return '<div class="alert alert-danger">Error rendering table: ' . 
                   htmlspecialchars($e->getMessage()) . '</div>';
        }
    }

    /**
     * Render table with list data
     */
    public function renderListTable($data, $config = []) {
        try {
            // Process data through Python
            $processed = $this->processPythonData('process_list', $data);
            
            // Generate unique table ID
            $tableId = 'datatable_' . uniqid();
            
            // Merge configurations
            $tableConfig = array_merge($this->defaultConfig, $config);
            $tableConfig['columns'] = $processed['columns'];
            
            // Start output buffering
            ob_start();
            ?>
            <table id="<?php echo $tableId; ?>" class="table table-striped table-bordered">
                <thead>
                    <tr>
                        <?php foreach ($processed['columns'] as $column): ?>
                        <th><?php echo htmlspecialchars($column['title']); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($processed['data'] as $row): ?>
                    <tr>
                        <?php foreach ($processed['columns'] as $column): ?>
                        <td><?php echo htmlspecialchars($row[$column['data']] ?? ''); ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <script>
            $(document).ready(function() {
                $('#<?php echo $tableId; ?>').DataTable(<?php echo json_encode($tableConfig); ?>);
            });
            </script>
            <?php
            return ob_get_clean();
            
        } catch (Exception $e) {
            error_log('DataTable Error: ' . $e->getMessage());
            return '<div class="alert alert-danger">Error rendering table: ' . 
                   htmlspecialchars($e->getMessage()) . '</div>';
        }
    }

    /**
     * Get JSON response for AJAX requests
     */
    public function getJsonResponse($data, $draw = 1, $start = 0, $length = 10, 
                                  $search = null, $order = null) {
        try {
            // Apply search filter if provided
            if ($search && !empty($search['value'])) {
                $data = $this->processPythonData('filter', $data, 
                    ['search' => $search['value']]);
            }
            
            // Apply sorting if provided
            if ($order && isset($order[0])) {
                $columnIdx = $order[0]['column'];
                $direction = $order[0]['dir'];
                $data = $this->processPythonData('sort', $data, 
                    ['column' => $columnIdx, 'direction' => $direction]);
            }
            
            // Calculate pagination
            $recordsTotal = count($data);
            $recordsFiltered = $recordsTotal;
            
            // Slice data for pagination
            $data = array_slice($data, $start, $length);
            
            return [
                'draw' => intval($draw),
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data' => $data
            ];
            
        } catch (Exception $e) {
            error_log('DataTable JSON Error: ' . $e->getMessage());
            return [
                'error' => $e->getMessage(),
                'draw' => intval($draw),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => []
            ];
        }
    }
}
?>
