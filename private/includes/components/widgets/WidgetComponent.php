<?php
/**
 * Base Widget Component
 * Provides reusable UI elements with Python data processing
 */
class WidgetComponent {
    private $pythonProcessor;
    private $processorScript;
    private $defaultConfig;
    
    public function __construct() {
        // Use virtual environment if available
        $this->pythonPath = VENV_DIR 
            ? VENV_DIR . '/bin/python3'
            : '/usr/bin/python3';
            
        // Set path to Python processor script
        $this->processorScript = dirname(dirname(dirname(__FILE__))) . 
            '/scripts/modules/data_processing/widget_processor.py';
            
        // Set default widget configuration
        $this->defaultConfig = [
            'class' => 'widget',
            'theme' => 'light',
            'animation' => true,
            'cache' => true
        ];
    }
    
    /**
     * Process widget data through Python processor
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
            throw new Exception('Failed to process widget data: ' . implode("\n", $output));
        }
        
        // Parse JSON response
        $result = json_decode(implode("\n", $output), true);
        if ($result === null) {
            throw new Exception('Invalid JSON response from processor');
        }
        
        return $result;
    }
    
    /**
     * Render card widget
     */
    public function renderCard($data, $config = []) {
        try {
            // Process data through Python
            $processed = $this->processPythonData('process_card', $data);
            
            // Generate unique widget ID
            $widgetId = 'widget_' . uniqid();
            
            // Merge configurations
            $widgetConfig = array_merge($this->defaultConfig, $config);
            
            // Start output buffering
            ob_start();
            ?>
            <div id="<?php echo $widgetId; ?>" 
                 class="card <?php echo htmlspecialchars($widgetConfig['class']); ?>"
                 data-theme="<?php echo htmlspecialchars($widgetConfig['theme']); ?>"
                 data-animation="<?php echo $widgetConfig['animation'] ? 'true' : 'false'; ?>">
                
                <?php if ($processed['header']): ?>
                    <div class="card-header">
                        <?php if ($processed['header']['icon']): ?>
                            <i class="<?php echo htmlspecialchars($processed['header']['icon']); ?>"></i>
                        <?php endif; ?>
                        
                        <?php echo htmlspecialchars($processed['header']['title']); ?>
                        
                        <?php if ($processed['header']['tools']): ?>
                            <div class="card-tools">
                                <?php foreach ($processed['header']['tools'] as $tool): ?>
                                    <button type="button" class="btn btn-tool" 
                                            data-action="<?php echo htmlspecialchars($tool['action']); ?>"
                                            title="<?php echo htmlspecialchars($tool['title']); ?>">
                                        <i class="<?php echo htmlspecialchars($tool['icon']); ?>"></i>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <div class="card-body">
                    <?php if ($processed['title']): ?>
                        <h5 class="card-title"><?php echo htmlspecialchars($processed['title']); ?></h5>
                    <?php endif; ?>
                    
                    <?php if ($processed['subtitle']): ?>
                        <h6 class="card-subtitle mb-2 text-muted">
                            <?php echo htmlspecialchars($processed['subtitle']); ?>
                        </h6>
                    <?php endif; ?>
                    
                    <?php if ($processed['content']): ?>
                        <div class="card-text">
                            <?php echo $processed['content']; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($processed['footer']): ?>
                    <div class="card-footer">
                        <?php echo $processed['footer']; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if ($widgetConfig['animation']): ?>
            <script>
            $(document).ready(function() {
                const widget = document.getElementById('<?php echo $widgetId; ?>');
                
                // Initialize animations
                if (widget.dataset.animation === 'true') {
                    widget.classList.add('fade');
                    widget.style.opacity = '0';
                    
                    // Show widget when in viewport
                    const observer = new IntersectionObserver((entries) => {
                        entries.forEach(entry => {
                            if (entry.isIntersecting) {
                                entry.target.style.opacity = '1';
                                observer.unobserve(entry.target);
                            }
                        });
                    });
                    
                    observer.observe(widget);
                }
                
                // Initialize tool buttons
                $(widget).find('[data-action]').click(function() {
                    const action = $(this).data('action');
                    
                    // Handle tool actions
                    switch (action) {
                        case 'collapse':
                            $(widget).find('.card-body').slideToggle();
                            break;
                            
                        case 'remove':
                            $(widget).fadeOut(() => $(widget).remove());
                            break;
                            
                        case 'refresh':
                            $(widget).find('.card-body').addClass('loading');
                            // Simulated refresh delay
                            setTimeout(() => {
                                $(widget).find('.card-body').removeClass('loading');
                            }, 1000);
                            break;
                    }
                });
            });
            </script>
            <?php endif; ?>
            <?php
            return ob_get_clean();
            
        } catch (Exception $e) {
            error_log('Widget Error: ' . $e->getMessage());
            return '<div class="alert alert-danger">Error rendering widget: ' . 
                   htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
    
    /**
     * Render stats widget
     */
    public function renderStats($data, $config = []) {
        try {
            // Process data through Python
            $processed = $this->processPythonData('process_stats', $data);
            
            // Generate unique widget ID
            $widgetId = 'widget_' . uniqid();
            
            // Merge configurations
            $widgetConfig = array_merge($this->defaultConfig, $config);
            
            // Start output buffering
            ob_start();
            ?>
            <div id="<?php echo $widgetId; ?>" 
                 class="small-box <?php echo htmlspecialchars($widgetConfig['class']); ?>"
                 data-theme="<?php echo htmlspecialchars($widgetConfig['theme']); ?>"
                 data-animation="<?php echo $widgetConfig['animation'] ? 'true' : 'false'; ?>">
                
                <div class="inner">
                    <h3><?php echo htmlspecialchars($processed['value']); ?></h3>
                    <p><?php echo htmlspecialchars($processed['label']); ?></p>
                </div>
                
                <?php if ($processed['icon']): ?>
                    <div class="icon">
                        <i class="<?php echo htmlspecialchars($processed['icon']); ?>"></i>
                    </div>
                <?php endif; ?>
                
                <?php if ($processed['link']): ?>
                    <a href="<?php echo htmlspecialchars($processed['link']['url']); ?>" 
                       class="small-box-footer">
                        <?php echo htmlspecialchars($processed['link']['text']); ?>
                        <i class="fas fa-arrow-circle-right"></i>
                    </a>
                <?php endif; ?>
            </div>
            
            <?php if ($widgetConfig['animation']): ?>
            <script>
            $(document).ready(function() {
                const widget = document.getElementById('<?php echo $widgetId; ?>');
                
                // Initialize animations
                if (widget.dataset.animation === 'true') {
                    widget.classList.add('fade');
                    widget.style.opacity = '0';
                    
                    // Show widget when in viewport
                    const observer = new IntersectionObserver((entries) => {
                        entries.forEach(entry => {
                            if (entry.isIntersecting) {
                                entry.target.style.opacity = '1';
                                observer.unobserve(entry.target);
                            }
                        });
                    });
                    
                    observer.observe(widget);
                }
            });
            </script>
            <?php endif; ?>
            <?php
            return ob_get_clean();
            
        } catch (Exception $e) {
            error_log('Widget Error: ' . $e->getMessage());
            return '<div class="alert alert-danger">Error rendering widget: ' . 
                   htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
    
    /**
     * Render chart widget
     */
    public function renderChart($data, $config = []) {
        try {
            // Process data through Python
            $processed = $this->processPythonData('process_chart', $data);
            
            // Generate unique widget ID
            $widgetId = 'widget_' . uniqid();
            
            // Merge configurations
            $widgetConfig = array_merge($this->defaultConfig, $config);
            
            // Start output buffering
            ob_start();
            ?>
            <div id="<?php echo $widgetId; ?>" 
                 class="card <?php echo htmlspecialchars($widgetConfig['class']); ?>"
                 data-theme="<?php echo htmlspecialchars($widgetConfig['theme']); ?>"
                 data-animation="<?php echo $widgetConfig['animation'] ? 'true' : 'false'; ?>">
                
                <?php if ($processed['title']): ?>
                    <div class="card-header">
                        <h3 class="card-title"><?php echo htmlspecialchars($processed['title']); ?></h3>
                    </div>
                <?php endif; ?>
                
                <div class="card-body">
                    <canvas id="<?php echo $widgetId; ?>_chart"></canvas>
                </div>
            </div>
            
            <script>
            $(document).ready(function() {
                const widget = document.getElementById('<?php echo $widgetId; ?>');
                const ctx = document.getElementById('<?php echo $widgetId; ?>_chart').getContext('2d');
                
                // Initialize chart
                new Chart(ctx, <?php echo json_encode($processed['config']); ?>);
                
                // Initialize animations
                if (widget.dataset.animation === 'true') {
                    widget.classList.add('fade');
                    widget.style.opacity = '0';
                    
                    // Show widget when in viewport
                    const observer = new IntersectionObserver((entries) => {
                        entries.forEach(entry => {
                            if (entry.isIntersecting) {
                                entry.target.style.opacity = '1';
                                observer.unobserve(entry.target);
                            }
                        });
                    });
                    
                    observer.observe(widget);
                }
            });
            </script>
            <?php
            return ob_get_clean();
            
        } catch (Exception $e) {
            error_log('Widget Error: ' . $e->getMessage());
            return '<div class="alert alert-danger">Error rendering widget: ' . 
                   htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}
?>
