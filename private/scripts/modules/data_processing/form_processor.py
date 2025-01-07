#!/usr/bin/env python3
"""
Form Processor
Handles form field validation, processing, and data transformation
"""

import json
import sys
from typing import Dict, Any, List, Optional
from ..utilities.response import Response

class FormProcessor:
    def __init__(self):
        """Initialize form processor"""
        self.response = Response()
        
    def process_fields(self, fields: List[Dict[str, Any]]) -> Dict[str, Any]:
        """
        Process form fields
        
        Args:
            fields: List of field definitions
            
        Returns:
            Dictionary containing processed fields
        """
        processed_fields = []
        
        for field in fields:
            # Ensure required field properties
            field = self._ensure_field_properties(field)
            
            # Process field based on type
            if field['type'] == 'select':
                field = self._process_select_field(field)
            elif field['type'] in ['checkbox', 'radio']:
                field = self._process_choice_field(field)
            elif field['type'] == 'file':
                field = self._process_file_field(field)
            else:
                field = self._process_input_field(field)
            
            processed_fields.append(field)
        
        return {
            'fields': processed_fields
        }
    
    def _ensure_field_properties(self, field: Dict[str, Any]) -> Dict[str, Any]:
        """
        Ensure all required field properties are present
        
        Args:
            field: Field definition
            
        Returns:
            Field with all required properties
        """
        # Required properties
        if 'name' not in field:
            raise ValueError('Field name is required')
            
        if 'type' not in field:
            field['type'] = 'text'
            
        # Generate ID if not provided
        if 'id' not in field:
            field['id'] = f"field_{field['name']}"
            
        # Default properties
        field.setdefault('label', '')
        field.setdefault('required', False)
        field.setdefault('disabled', False)
        field.setdefault('readonly', False)
        field.setdefault('class', '')
        field.setdefault('placeholder', '')
        field.setdefault('help', '')
        field.setdefault('error_message', None)
        
        return field
    
    def _process_select_field(self, field: Dict[str, Any]) -> Dict[str, Any]:
        """
        Process select field
        
        Args:
            field: Field definition
            
        Returns:
            Processed field
        """
        if 'options' not in field:
            field['options'] = []
            
        # Ensure option properties
        processed_options = []
        for option in field['options']:
            if isinstance(option, (str, int, float)):
                # Convert simple value to option object
                processed_options.append({
                    'value': str(option),
                    'label': str(option),
                    'selected': False
                })
            else:
                # Ensure option has required properties
                option.setdefault('value', '')
                option.setdefault('label', option['value'])
                option.setdefault('selected', False)
                processed_options.append(option)
                
        field['options'] = processed_options
        
        # Handle multiple select
        field.setdefault('multiple', False)
        
        return field
    
    def _process_choice_field(self, field: Dict[str, Any]) -> Dict[str, Any]:
        """
        Process checkbox or radio field
        
        Args:
            field: Field definition
            
        Returns:
            Processed field
        """
        if 'options' not in field:
            field['options'] = []
            
        # Ensure option properties
        processed_options = []
        for option in field['options']:
            if isinstance(option, (str, int, float)):
                # Convert simple value to option object
                processed_options.append({
                    'value': str(option),
                    'label': str(option),
                    'checked': False
                })
            else:
                # Ensure option has required properties
                option.setdefault('value', '')
                option.setdefault('label', option['value'])
                option.setdefault('checked', False)
                processed_options.append(option)
                
        field['options'] = processed_options
        
        return field
    
    def _process_file_field(self, field: Dict[str, Any]) -> Dict[str, Any]:
        """
        Process file upload field
        
        Args:
            field: Field definition
            
        Returns:
            Processed field
        """
        # Handle file type restrictions
        field.setdefault('accept', None)
        
        # Handle multiple files
        field.setdefault('multiple', False)
        
        # Set appropriate class
        field['class'] = 'custom-file-input ' + field['class']
        
        return field
    
    def _process_input_field(self, field: Dict[str, Any]) -> Dict[str, Any]:
        """
        Process standard input field
        
        Args:
            field: Field definition
            
        Returns:
            Processed field
        """
        # Handle numeric fields
        if field['type'] in ['number', 'range']:
            field.setdefault('min', None)
            field.setdefault('max', None)
            field.setdefault('step', None)
            
        # Handle pattern validation
        if field['type'] in ['text', 'tel', 'email', 'password']:
            field.setdefault('pattern', None)
            
        # Handle date/time fields
        if field['type'] in ['date', 'time', 'datetime-local']:
            field.setdefault('min', None)
            field.setdefault('max', None)
            
        return field

def main():
    """Main entry point"""
    if len(sys.argv) < 3:
        print(json.dumps({
            'error': 'Invalid arguments. Usage: form_processor.py <command> <data> [options]'
        }))
        sys.exit(1)
        
    command = sys.argv[1]
    data = json.loads(sys.argv[2])
    options = json.loads(sys.argv[3]) if len(sys.argv) > 3 else None
    
    processor = FormProcessor()
    
    try:
        if command == 'process_fields':
            result = processor.process_fields(data)
        else:
            result = processor.response.error(f'Unknown command: {command}')
            
        print(json.dumps(result))
        
    except Exception as e:
        print(json.dumps(processor.response.error(str(e))))
        sys.exit(1)

if __name__ == '__main__':
    main()
