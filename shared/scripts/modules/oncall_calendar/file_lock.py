#!/usr/bin/env python3
"""File locking mechanism for atomic operations on JSON files."""

import os
import json
import fcntl
import shutil
from datetime import datetime
from pathlib import Path
from contextlib import contextmanager

class FileLockException(Exception):
    """Exception raised for file locking errors."""
    pass

class FileManager:
    """Manages atomic file operations with locking mechanism."""
    
    def __init__(self, base_path):
        """Initialize the file manager.
        
        Args:
            base_path (str): Base directory for JSON files
        """
        self.base_path = Path(base_path)
        self.backup_path = self.base_path / 'backups'
        self.base_path.mkdir(parents=True, exist_ok=True)
        self.backup_path.mkdir(parents=True, exist_ok=True)

    def _get_lock_path(self, filename):
        """Get the lock file path for a given filename."""
        return self.base_path / f"{filename}.lock"

    def _backup_file(self, filepath):
        """Create a backup of the file with timestamp."""
        if not filepath.exists():
            return
            
        timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
        backup_name = f"{filepath.stem}_{timestamp}{filepath.suffix}"
        backup_path = self.backup_path / backup_name
        shutil.copy2(filepath, backup_path)

    @contextmanager
    def atomic_write(self, filename, backup=True):
        """Context manager for atomic file operations with locking.
        
        Args:
            filename (str): Name of the JSON file to operate on
            backup (bool): Whether to create a backup before writing
        """
        filepath = self.base_path / filename
        lock_path = self._get_lock_path(filename)
        
        lock_file = None
        try:
            # Acquire lock
            lock_file = open(lock_path, 'w')
            fcntl.flock(lock_file.fileno(), fcntl.LOCK_EX)
            
            # Create backup if requested
            if backup and filepath.exists():
                self._backup_file(filepath)
            
            yield filepath
            
        except Exception as e:
            raise FileLockException(f"Error during atomic operation: {str(e)}")
        finally:
            if lock_file:
                fcntl.flock(lock_file.fileno(), fcntl.LOCK_UN)
                lock_file.close()
                try:
                    os.remove(lock_path)
                except OSError:
                    pass

    def read_json(self, filename, default=None):
        """Read JSON file with locking.
        
        Args:
            filename (str): Name of the JSON file to read
            default: Default value if file doesn't exist
        
        Returns:
            dict: JSON content or default value
        """
        filepath = self.base_path / filename
        if not filepath.exists():
            return default if default is not None else {}
            
        with self.atomic_write(filename, backup=False) as fp:
            try:
                with open(fp, 'r') as f:
                    return json.load(f)
            except json.JSONDecodeError:
                return default if default is not None else {}

    def write_json(self, filename, data, backup=True):
        """Write data to JSON file atomically.
        
        Args:
            filename (str): Name of the JSON file to write
            data: Data to write (must be JSON serializable)
            backup (bool): Whether to create a backup before writing
        """
        with self.atomic_write(filename, backup=backup) as fp:
            with open(fp, 'w') as f:
                json.dump(data, f, indent=2)

    def append_json(self, filename, data, key, backup=True):
        """Append data to a JSON array in file.
        
        Args:
            filename (str): Name of the JSON file
            data: Data to append (must be JSON serializable)
            key (str): Key of the array in JSON object
            backup (bool): Whether to create a backup before writing
        """
        current = self.read_json(filename, {key: []})
        if key not in current:
            current[key] = []
        current[key].append(data)
        self.write_json(filename, current, backup=backup)

    def update_json(self, filename, key, value, backup=True):
        """Update a specific key in JSON file.
        
        Args:
            filename (str): Name of the JSON file
            key (str): Key to update
            value: New value
            backup (bool): Whether to create a backup before writing
        """
        current = self.read_json(filename, {})
        current[key] = value
        self.write_json(filename, current, backup=backup)

    def delete_from_json(self, filename, key, backup=True):
        """Delete a key from JSON file.
        
        Args:
            filename (str): Name of the JSON file
            key: Key to delete
            backup (bool): Whether to create a backup before writing
        """
        current = self.read_json(filename, {})
        if key in current:
            del current[key]
            self.write_json(filename, current, backup=backup)
