#!/usr/bin/env python3
"""
Cache Utility
Provides caching functionality for API responses and processed data
"""

import json
import os
import time
from typing import Dict, Any, Optional
from pathlib import Path

class Cache:
    def __init__(self, cache_dir: str = None, ttl: int = 300):
        """
        Initialize cache with directory and TTL
        
        Args:
            cache_dir: Directory to store cache files (default: logs/cache)
            ttl: Time to live in seconds (default: 300)
        """
        if cache_dir is None:
            # Get project root (3 levels up from this file)
            root_dir = Path(os.path.dirname(os.path.abspath(__file__))).parent.parent.parent
            cache_dir = os.path.join(root_dir, 'logs', 'cache')
            
        self.cache_dir = cache_dir
        self.ttl = ttl
        
        # Ensure cache directory exists
        os.makedirs(self.cache_dir, exist_ok=True)
        os.chmod(self.cache_dir, 0o775)  # rwxrwxr-x
    
    def get(self, key: str) -> Optional[Dict[str, Any]]:
        """
        Get cached data if it exists and is not expired
        
        Args:
            key: Cache key
            
        Returns:
            Cached data or None if not found/expired
        """
        cache_file = os.path.join(self.cache_dir, f"{key}.json")
        
        try:
            if not os.path.exists(cache_file):
                return None
                
            # Check if cache is expired
            if time.time() - os.path.getmtime(cache_file) > self.ttl:
                os.remove(cache_file)
                return None
                
            with open(cache_file, 'r') as f:
                return json.load(f)
                
        except Exception as e:
            print(f"Cache error: {str(e)}", file=sys.stderr)
            return None
    
    def set(self, key: str, data: Dict[str, Any]) -> bool:
        """
        Store data in cache
        
        Args:
            key: Cache key
            data: Data to cache
            
        Returns:
            True if successful, False otherwise
        """
        cache_file = os.path.join(self.cache_dir, f"{key}.json")
        
        try:
            with open(cache_file, 'w') as f:
                json.dump(data, f)
            os.chmod(cache_file, 0o664)  # rw-rw-r--
            return True
            
        except Exception as e:
            print(f"Cache error: {str(e)}", file=sys.stderr)
            return False
    
    def clear(self, key: str = None) -> bool:
        """
        Clear cache for a key or all cache if no key provided
        
        Args:
            key: Optional cache key to clear
            
        Returns:
            True if successful, False otherwise
        """
        try:
            if key:
                cache_file = os.path.join(self.cache_dir, f"{key}.json")
                if os.path.exists(cache_file):
                    os.remove(cache_file)
            else:
                # Clear all cache files
                for file in os.listdir(self.cache_dir):
                    if file.endswith('.json'):
                        os.remove(os.path.join(self.cache_dir, file))
            return True
            
        except Exception as e:
            print(f"Cache error: {str(e)}", file=sys.stderr)
            return False
    
    def cleanup(self) -> int:
        """
        Remove expired cache entries
        
        Returns:
            Number of entries removed
        """
        removed = 0
        try:
            for file in os.listdir(self.cache_dir):
                if not file.endswith('.json'):
                    continue
                    
                cache_file = os.path.join(self.cache_dir, file)
                if time.time() - os.path.getmtime(cache_file) > self.ttl:
                    os.remove(cache_file)
                    removed += 1
                    
        except Exception as e:
            print(f"Cache cleanup error: {str(e)}", file=sys.stderr)
            
        return removed
