#!/usr/bin/env python3
"""
Response Utility
Provides standardized response formatting for API handlers
"""

from typing import Dict, Any, Optional, Union
from datetime import datetime

class Response:
    def __init__(self):
        """Initialize response formatter"""
        pass
    
    def success(self, data: Any = None, message: str = None) -> Dict[str, Any]:
        """
        Format successful response
        
        Args:
            data: Response data
            message: Optional success message
            
        Returns:
            Formatted response dictionary
        """
        response = {
            'status': 'success',
            'timestamp': datetime.now().isoformat(),
            'data': data
        }
        
        if message:
            response['message'] = message
            
        return response
    
    def error(self, message: str, code: Union[str, int] = None, details: Any = None) -> Dict[str, Any]:
        """
        Format error response
        
        Args:
            message: Error message
            code: Optional error code
            details: Optional error details
            
        Returns:
            Formatted error response dictionary
        """
        response = {
            'status': 'error',
            'timestamp': datetime.now().isoformat(),
            'message': message
        }
        
        if code is not None:
            response['code'] = code
            
        if details is not None:
            response['details'] = details
            
        return response
    
    def paginated(self, data: Any, page: int, per_page: int, total: int) -> Dict[str, Any]:
        """
        Format paginated response
        
        Args:
            data: Page data
            page: Current page number
            per_page: Items per page
            total: Total number of items
            
        Returns:
            Formatted paginated response dictionary
        """
        total_pages = (total + per_page - 1) // per_page
        
        return {
            'status': 'success',
            'timestamp': datetime.now().isoformat(),
            'data': data,
            'pagination': {
                'page': page,
                'per_page': per_page,
                'total_items': total,
                'total_pages': total_pages,
                'has_next': page < total_pages,
                'has_prev': page > 1
            }
        }
    
    def stream(self, generator: Any, content_type: str = 'application/json') -> Dict[str, Any]:
        """
        Format streaming response
        
        Args:
            generator: Data generator function
            content_type: Response content type
            
        Returns:
            Formatted streaming response dictionary
        """
        return {
            'status': 'success',
            'timestamp': datetime.now().isoformat(),
            'stream': True,
            'content_type': content_type,
            'generator': generator
        }
    
    def file(self, file_path: str, file_name: str = None, content_type: str = None) -> Dict[str, Any]:
        """
        Format file download response
        
        Args:
            file_path: Path to file
            file_name: Optional download file name
            content_type: Optional content type
            
        Returns:
            Formatted file response dictionary
        """
        return {
            'status': 'success',
            'timestamp': datetime.now().isoformat(),
            'file': True,
            'file_path': file_path,
            'file_name': file_name,
            'content_type': content_type
        }
