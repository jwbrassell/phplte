<?php
/**
 * Base Form Component
 * Provides reusable form functionality with Python data processing
 */
class FormComponent {
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
            '/scripts/modules/data_processing/form_processor.py';
            
        // Set default form configuration
        $this->defaultConfig = [
            'method' => 'POST',
            'enctype' => 'multipart/form-data',
            'class' => 'needs-validation',
            'novalidate' => true,
            'csrf' => true
        ];
    }
    
    /**
     * Process form data through Python processor
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
            throw new Exception('Failed to process form data: ' . implode("\n", $output));
        }
        
        // Parse JSON response
        $result = json_decode(implode("\n", $output), true);
        if ($result === null) {
            throw new Exception('Invalid JSON response from processor');
        }
        
        return $result;
    }
    
    /**
     * Render form with the given fields and configuration
     */
    public function renderForm($fields, $config = []) {
        try {
            // Process fields through Python
            $processed = $this->processPythonData('process_fields', $fields);
            
            // Generate unique form ID
            $formId = 'form_' . uniqid();
            
            // Merge configurations
            $formConfig = array_merge($this->defaultConfig, $config);
            
            // Start output buffering
            ob_start();
            ?>
            <form id="<?php echo $formId; ?>" 
                  method="<?php echo htmlspecialchars($formConfig['method']); ?>"
                  enctype="<?php echo htmlspecialchars($formConfig['enctype']); ?>"
                  class="<?php echo htmlspecialchars($formConfig['class']); ?>"
                  <?php echo $formConfig['novalidate'] ? 'novalidate' : ''; ?>>
                
                <?php if ($formConfig['csrf']): ?>
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
                <?php endif; ?>
                
                <?php foreach ($processed['fields'] as $field): ?>
                    <div class="form-group">
                        <?php if ($field['label']): ?>
                            <label for="<?php echo htmlspecialchars($field['id']); ?>">
                                <?php echo htmlspecialchars($field['label']); ?>
                                <?php if ($field['required']): ?>
                                    <span class="text-danger">*</span>
                                <?php endif; ?>
                            </label>
                        <?php endif; ?>
                        
                        <?php echo $this->renderField($field); ?>
                        
                        <?php if ($field['help']): ?>
                            <small class="form-text text-muted">
                                <?php echo htmlspecialchars($field['help']); ?>
                            </small>
                        <?php endif; ?>
                        
                        <div class="invalid-feedback">
                            <?php echo htmlspecialchars($field['error_message'] ?? 'This field is required.'); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Submit</button>
                </div>
            </form>
            
            <script>
            $(document).ready(function() {
                const form = document.getElementById('<?php echo $formId; ?>');
                
                form.addEventListener('submit', function(event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                });
                
                // Initialize any special field types
                $(form).find('select').select2({
                    theme: 'bootstrap4'
                });
                
                $(form).find('[data-toggle="datepicker"]').datepicker({
                    format: 'yyyy-mm-dd',
                    autoclose: true
                });
            });
            </script>
            <?php
            return ob_get_clean();
            
        } catch (Exception $e) {
            error_log('Form Error: ' . $e->getMessage());
            return '<div class="alert alert-danger">Error rendering form: ' . 
                   htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
    
    /**
     * Render individual form field based on type
     */
    private function renderField($field) {
        $attributes = [
            'id' => $field['id'],
            'name' => $field['name'],
            'class' => 'form-control ' . ($field['class'] ?? ''),
            'required' => $field['required'] ?? false,
            'placeholder' => $field['placeholder'] ?? '',
            'value' => $field['value'] ?? '',
            'min' => $field['min'] ?? null,
            'max' => $field['max'] ?? null,
            'step' => $field['step'] ?? null,
            'pattern' => $field['pattern'] ?? null,
            'readonly' => $field['readonly'] ?? false,
            'disabled' => $field['disabled'] ?? false
        ];
        
        switch ($field['type']) {
            case 'select':
                return $this->renderSelect($field, $attributes);
                
            case 'textarea':
                return $this->renderTextarea($field, $attributes);
                
            case 'checkbox':
            case 'radio':
                return $this->renderCheckboxRadio($field, $attributes);
                
            case 'file':
                return $this->renderFile($field, $attributes);
                
            default:
                return $this->renderInput($field, $attributes);
        }
    }
    
    /**
     * Render select field
     */
    private function renderSelect($field, $attributes) {
        $html = '<select';
        foreach ($attributes as $key => $value) {
            if ($value !== null && $value !== false) {
                $html .= ' ' . $key . '="' . htmlspecialchars($value) . '"';
            }
        }
        if ($field['multiple'] ?? false) {
            $html .= ' multiple';
        }
        $html .= '>';
        
        foreach ($field['options'] as $option) {
            $html .= '<option value="' . htmlspecialchars($option['value']) . '"';
            if ($option['selected'] ?? false) {
                $html .= ' selected';
            }
            $html .= '>' . htmlspecialchars($option['label']) . '</option>';
        }
        
        $html .= '</select>';
        return $html;
    }
    
    /**
     * Render textarea field
     */
    private function renderTextarea($field, $attributes) {
        $html = '<textarea';
        foreach ($attributes as $key => $value) {
            if ($key !== 'value' && $value !== null && $value !== false) {
                $html .= ' ' . $key . '="' . htmlspecialchars($value) . '"';
            }
        }
        if ($field['rows'] ?? false) {
            $html .= ' rows="' . (int)$field['rows'] . '"';
        }
        $html .= '>';
        $html .= htmlspecialchars($attributes['value']);
        $html .= '</textarea>';
        return $html;
    }
    
    /**
     * Render checkbox or radio field
     */
    private function renderCheckboxRadio($field, $attributes) {
        $html = '<div class="' . $field['type'] . '-group">';
        foreach ($field['options'] as $option) {
            $html .= '<div class="' . $field['type'] . '">';
            $html .= '<input type="' . $field['type'] . '" ';
            $html .= 'id="' . htmlspecialchars($attributes['id'] . '_' . $option['value']) . '" ';
            $html .= 'name="' . htmlspecialchars($attributes['name']) . '" ';
            $html .= 'value="' . htmlspecialchars($option['value']) . '" ';
            if ($option['checked'] ?? false) {
                $html .= 'checked ';
            }
            if ($attributes['required']) {
                $html .= 'required ';
            }
            if ($attributes['disabled']) {
                $html .= 'disabled ';
            }
            $html .= 'class="' . $field['type'] . '-input">';
            
            $html .= '<label class="' . $field['type'] . '-label" for="';
            $html .= htmlspecialchars($attributes['id'] . '_' . $option['value']) . '">';
            $html .= htmlspecialchars($option['label']);
            $html .= '</label>';
            $html .= '</div>';
        }
        $html .= '</div>';
        return $html;
    }
    
    /**
     * Render file upload field
     */
    private function renderFile($field, $attributes) {
        $html = '<div class="custom-file">';
        $html .= '<input type="file"';
        foreach ($attributes as $key => $value) {
            if ($value !== null && $value !== false) {
                $html .= ' ' . $key . '="' . htmlspecialchars($value) . '"';
            }
        }
        if ($field['accept'] ?? false) {
            $html .= ' accept="' . htmlspecialchars($field['accept']) . '"';
        }
        if ($field['multiple'] ?? false) {
            $html .= ' multiple';
        }
        $html .= ' class="custom-file-input">';
        
        $html .= '<label class="custom-file-label" for="' . htmlspecialchars($attributes['id']) . '">';
        $html .= htmlspecialchars($field['placeholder'] ?? 'Choose file');
        $html .= '</label>';
        $html .= '</div>';
        return $html;
    }
    
    /**
     * Render input field
     */
    private function renderInput($field, $attributes) {
        $html = '<input type="' . htmlspecialchars($field['type']) . '"';
        foreach ($attributes as $key => $value) {
            if ($value !== null && $value !== false) {
                $html .= ' ' . $key . '="' . htmlspecialchars($value) . '"';
            }
        }
        $html .= '>';
        return $html;
    }
}
?>
