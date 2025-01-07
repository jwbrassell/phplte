#!/opt/python-venv/bin/python3
"""
File Operations Handler
Manages file operations with locking, backup, and error handling
"""

import os
import sys
import portalocker
import logging
import json
from modules_config import *

class FileLock:
    def __init__(self, file_name_and_path):
        """
        Initialize FileLock with a file path
        
        Args:
            file_name_and_path (str): Path to the file to be managed
        """
        self.file_name_and_path = file_name_and_path
        self.file = None
        self.file_created = False

        # Create directory structure if needed
        os.makedirs(os.path.dirname(self.file_name_and_path), exist_ok=True)

        # Initialize file if it doesn't exist
        if not os.path.exists(self.file_name_and_path):
            # Check for backup files
            backup_exists = any(
                fname.startswith(self.file_name_and_path) and 
                fname.endswith('.bck') 
                for fname in os.listdir(os.path.dirname(self.file_name_and_path))
            )
            
            if not backup_exists:
                # Create new file with empty dictionary
                with open(self.file_name_and_path, 'w') as file:
                    json.dump({}, file)
                self.file_created = True

    def __enter__(self):
        """
        Context manager entry point - acquire file lock
        """
        self.file = open(self.file_name_and_path, 'r+')
        portalocker.lock(self.file, portalocker.LOCK_EX)
        return self

    def read(self, attempts=3, delay=1):
        """
        Read JSON data from file with retry mechanism
        
        Args:
            attempts (int): Number of read attempts
            delay (float): Delay between attempts in seconds
            
        Returns:
            dict: Success status and data or error message
        """
        for _ in range(attempts):
            try:
                data = json.load(self.file)
                return {
                    "success": True,
                    "data": data,
                    "file_created": self.file_created
                }
            except json.JSONDecodeError:
                time.sleep(delay)  # Wait before retrying
        
        # If reading fails after all attempts, try to restore from backup
        try:
            self.restore_from_backup()
            return {
                "success": True,
                "data": json.load(self.file),
                "file_created": self.file_created
            }
        except:
            return {
                "success": False,
                "error": "JSONDecodeError"
            }

    def write(self, data, attempts=3, delay=1):
        """
        Write JSON data to file with retry mechanism
        
        Args:
            data: Data to write to file
            attempts (int): Number of write attempts
            delay (float): Delay between attempts in seconds
            
        Returns:
            dict: Success status and file creation info
        """
        for _ in range(attempts):
            try:
                self.file.seek(0)
                json.dump(data, self.file, indent=4)
                self.file.truncate()
                return {
                    "success": True,
                    "file_created": self.file_created
                }
            except Exception as e:
                logging.error(f"Error writing to file: {str(e)}")
                time.sleep(delay)
        
        return {
            "success": False,
            "error": f"Failed to write after {attempts} attempts"
        }

    def restore_from_backup(self):
        """
        Restore file from most recent backup if available
        """
        backup_dir = os.path.dirname(self.file_name_and_path)
        backup_files = [
            f for f in os.listdir(backup_dir) 
            if f.startswith(os.path.basename(self.file_name_and_path)) and 
            f.endswith('.bck')
        ]
        
        if backup_files:
            # Sort backup files by modification time (newest first)
            newest_backup = max(
                backup_files,
                key=lambda f: os.path.getmtime(os.path.join(backup_dir, f))
            )
            backup_path = os.path.join(backup_dir, newest_backup)
            
            with open(backup_path, 'r') as backup_file:
                backup_data = json.load(backup_file)
            
            # Write backup data to main file
            self.file.seek(0)
            json.dump(backup_data, self.file, indent=4)
            self.file.truncate()
        else:
            raise FileNotFoundError("No backup files found")

    def __exit__(self, exc_type, exc_val, exc_tb):
        """
        Context manager exit point - release file lock
        """
        if self.file:
            portalocker.unlock(self.file)
            self.file.close()
        return None