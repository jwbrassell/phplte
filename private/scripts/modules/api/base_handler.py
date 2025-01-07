#!/usr/bin/env python3
"""
Base API Handler
Provides common functionality for all API handlers
"""

import json
import sys
from typing import Dict, Any, Optional
from ..utilities.cache import Cache
from ..utilities.response import Response

class BaseHandler:
    def __init__(self):
        self.cache = Cache()
        self.response = Response()
    
    def handle_request(self, request_data: Dict[str, Any]) -> Dict[str, Any]:
        """
        Handle incoming API request
        
        Args:
            request_data: Dictionary containing request data
            
        Returns:
            Dictionary containing response data
        """
        try:
            # Validate request
            if not self.validate_request(request_data):
                return self.response.error("Invalid request format")
            
            # Check cache
            cache_key = self.get_cache_key(request_data)
            cached_response = self.cache.get(cache_key)
            if cached_response:
                return cached_response
            
            # Process request
            result = self.process_request(request_data)
            
            # Cache response
            self.cache.set(cache_key, result)
            
            return result
            
        except Exception as e:
            return self.response.error(str(e))
    
    def validate_request(self, request_data: Dict[str, Any]) -> bool:
        """
        Validate request format
        
        Args:
            request_data: Dictionary containing request data
            
        Returns:
            True if request is valid, False otherwise
        """
        required_fields = ['action', 'data']
        return all(field in request_data for field in required_fields)
    
    def get_cache_key(self, request_data: Dict[str, Any]) -> str:
        """
        Generate cache key from request data
        
        Args:
            request_data: Dictionary containing request data
            
        Returns:
            String cache key
        """
        return f"{request_data['action']}:{hash(json.dumps(request_data['data'], sort_keys=True))}"
    
    def process_request(self, request_data: Dict[str, Any]) -> Dict[str, Any]:
        """
        Process request (to be implemented by subclasses)
        
        Args:
            request_data: Dictionary containing request data
            
        Returns:
            Dictionary containing response data
        """
        raise NotImplementedError("Subclasses must implement process_request")
