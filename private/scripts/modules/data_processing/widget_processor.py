#!/usr/bin/env python3
"""
Widget Processor
Handles widget data processing and transformations
"""

import json
import sys
from typing import Dict, Any, List, Optional
from ..utilities.response import Response

class WidgetProcessor:
    def __init__(self):
        """Initialize widget processor"""
        self.response = Response()
    
    def process_card(self, data: Dict[str, Any]) -> Dict[str, Any]:
        """
        Process card widget data
        
        Args:
            data: Card widget data
            
        Returns:
            Processed card data
        """
        # Ensure required data
        if not isinstance(data, dict):
            raise ValueError('Card data must be a dictionary')
            
        # Process header
        header = data.get('header', {})
        if header:
            if not isinstance(header, dict):
                raise ValueError('Header must be a dictionary')
                
            # Ensure header properties
            header.setdefault('title', '')
            header.setdefault('icon', '')
            header.setdefault('tools', [])
            
            # Process tools
            tools = []
            for tool in header['tools']:
                if isinstance(tool, str):
                    # Convert string to tool object
                    tools.append({
                        'action': tool,
                        'title': tool.title(),
                        'icon': self._get_tool_icon(tool)
                    })
                else:
                    # Ensure tool properties
                    tool.setdefault('action', '')
                    tool.setdefault('title', tool['action'].title())
                    tool.setdefault('icon', self._get_tool_icon(tool['action']))
                    tools.append(tool)
            header['tools'] = tools
            
        # Process content
        content = data.get('content', '')
        if content:
            # Convert markdown to HTML if needed
            if data.get('markdown', False):
                try:
                    import markdown
                    content = markdown.markdown(content)
                except ImportError:
                    pass
                
        return {
            'header': header,
            'title': data.get('title', ''),
            'subtitle': data.get('subtitle', ''),
            'content': content,
            'footer': data.get('footer', '')
        }
    
    def process_stats(self, data: Dict[str, Any]) -> Dict[str, Any]:
        """
        Process stats widget data
        
        Args:
            data: Stats widget data
            
        Returns:
            Processed stats data
        """
        # Ensure required data
        if not isinstance(data, dict):
            raise ValueError('Stats data must be a dictionary')
            
        if 'value' not in data:
            raise ValueError('Stats value is required')
            
        # Format value
        value = data['value']
        if isinstance(value, (int, float)):
            if value > 1000000:
                value = f"{value/1000000:.1f}M"
            elif value > 1000:
                value = f"{value/1000:.1f}K"
            else:
                value = str(value)
                
        # Process link
        link = data.get('link', {})
        if link and isinstance(link, str):
            link = {
                'url': link,
                'text': 'More info'
            }
            
        return {
            'value': value,
            'label': data.get('label', ''),
            'icon': data.get('icon', ''),
            'link': link
        }
    
    def process_chart(self, data: Dict[str, Any]) -> Dict[str, Any]:
        """
        Process chart widget data
        
        Args:
            data: Chart widget data
            
        Returns:
            Processed chart data
        """
        # Ensure required data
        if not isinstance(data, dict):
            raise ValueError('Chart data must be a dictionary')
            
        if 'type' not in data:
            raise ValueError('Chart type is required')
            
        if 'data' not in data:
            raise ValueError('Chart data is required')
            
        # Process chart configuration
        config = {
            'type': data['type'],
            'data': self._process_chart_data(data['data']),
            'options': self._process_chart_options(data.get('options', {}))
        }
        
        return {
            'title': data.get('title', ''),
            'config': config
        }
    
    def _get_tool_icon(self, action: str) -> str:
        """Get icon class for tool action"""
        icons = {
            'collapse': 'fas fa-minus',
            'remove': 'fas fa-times',
            'refresh': 'fas fa-sync-alt',
            'maximize': 'fas fa-expand',
            'settings': 'fas fa-cog'
        }
        return icons.get(action, 'fas fa-wrench')
    
    def _process_chart_data(self, data: Dict[str, Any]) -> Dict[str, Any]:
        """Process chart data configuration"""
        if not isinstance(data, dict):
            raise ValueError('Chart data must be a dictionary')
            
        # Process datasets
        datasets = []
        for dataset in data.get('datasets', []):
            if isinstance(dataset, (list, tuple)):
                # Convert simple array to dataset object
                datasets.append({
                    'data': dataset,
                    'label': f'Dataset {len(datasets) + 1}'
                })
            else:
                datasets.append(dataset)
                
        return {
            'labels': data.get('labels', []),
            'datasets': datasets
        }
    
    def _process_chart_options(self, options: Dict[str, Any]) -> Dict[str, Any]:
        """Process chart options configuration"""
        if not isinstance(options, dict):
            return {}
            
        # Set default options
        options.setdefault('responsive', True)
        options.setdefault('maintainAspectRatio', False)
        
        return options

def main():
    """Main entry point"""
    if len(sys.argv) < 3:
        print(json.dumps({
            'error': 'Invalid arguments. Usage: widget_processor.py <command> <data> [options]'
        }))
        sys.exit(1)
        
    command = sys.argv[1]
    data = json.loads(sys.argv[2])
    options = json.loads(sys.argv[3]) if len(sys.argv) > 3 else None
    
    processor = WidgetProcessor()
    
    try:
        if command == 'process_card':
            result = processor.process_card(data)
        elif command == 'process_stats':
            result = processor.process_stats(data)
        elif command == 'process_chart':
            result = processor.process_chart(data)
        else:
            result = processor.response.error(f'Unknown command: {command}')
            
        print(json.dumps(result))
        
    except Exception as e:
        print(json.dumps(processor.response.error(str(e))))
        sys.exit(1)

if __name__ == '__main__':
    main()
