#!/usr/bin/env python3
import json
import os
from datetime import datetime
from pathlib import Path
import sys

class Logger:
    def __init__(self, log_type='general'):
        try:
            self.log_type = log_type
            # Get the absolute path to the script
            script_path = Path(os.path.abspath(__file__))
            # Navigate up to project root (from shared/scripts/modules/logging)
            script_dir = os.path.dirname(os.path.abspath(__file__))
            root_dir = os.path.dirname(os.path.dirname(os.path.dirname(os.path.dirname(script_dir))))
            
            # Set log directory under shared/data/logs/system
            self.base_dir = Path(root_dir) / 'shared' / 'data' / 'logs' / 'system'
            self.log_dir = self.base_dir / log_type
            
            self.ensure_log_directory()
        except Exception as e:
            sys.stderr.write(f"CRITICAL: Logger initialization failed: {str(e)}\n")
            raise

    def ensure_log_directory(self):
        """Ensure the log directory exists with proper permissions"""
        try:
            self.log_dir.mkdir(parents=True, exist_ok=True)
            os.chmod(self.log_dir, 0o755)
            
            # Check if directory is writable
            if not os.access(self.log_dir, os.W_OK):
                sys.stderr.write(f"CRITICAL: Directory {self.log_dir} is not writable\n")
                
        except Exception as e:
            sys.stderr.write(f"CRITICAL: Failed to create log directory: {str(e)}\n")
            raise

    def get_log_file(self):
        """Get the current log file path"""
        date = datetime.now().strftime('%Y-%m-%d')
        log_file = self.log_dir / f"{date}.json"
        
        # Ensure proper permissions (644 for files)
        if log_file.exists():
            os.chmod(log_file, 0o644)
        
        return log_file

    def ensure_log_file(self, log_file):
        """Ensure the log file exists and has proper permissions"""
        if not log_file.exists():
            log_file.touch()
            os.chmod(log_file, 0o644)
        
        # Initialize file with empty array if new
        if log_file.stat().st_size == 0:
            with open(log_file, 'w') as f:
                json.dump([], f)

    def write_log(self, data):
        """Write a log entry to the JSON file with atomic operations and file locking"""
        import fcntl
        import tempfile
        
        log_file = self.get_log_file()
        self.ensure_log_file(log_file)
        
        # Create a temporary file in the same directory
        temp_dir = self.log_dir
        temp_dir.mkdir(parents=True, exist_ok=True)
        
        temp_file = None
        try:
            temp_file = tempfile.NamedTemporaryFile(mode='w+', dir=temp_dir, delete=False)
            
            # Get exclusive lock on temp file
            fcntl.flock(temp_file.fileno(), fcntl.LOCK_EX)
            
            # Read existing logs
            try:
                with open(log_file, 'r') as f:
                    # Lock the source file while reading
                    fcntl.flock(f.fileno(), fcntl.LOCK_SH)
                    logs = json.load(f)
                    fcntl.flock(f.fileno(), fcntl.LOCK_UN)
            except (json.JSONDecodeError, FileNotFoundError):
                logs = []
            
            # Append new log
            logs.append(data)
            
            # Write to temp file
            json.dump(logs, temp_file, indent=2)
            temp_file.flush()
            os.fsync(temp_file.fileno())
            
            # Close file before moving
            temp_file.close()
            
            # Atomically move temp file to target
            os.replace(temp_file.name, log_file)
            
            # Set file permissions to 644
            os.chmod(log_file, 0o644)
            
        except Exception as e:
            sys.stderr.write(f"CRITICAL: Failed to write log: {str(e)}\n")
            if temp_file:
                try:
                    os.unlink(temp_file.name)
                except:
                    pass
            raise

    def log(self, message, level='INFO', context=None):
        """Main logging method"""
        context = context or {}
        
        # Extract user info and common fields
        log_data = {
            'timestamp': datetime.now().isoformat(),
            'type': context.get('type', 'general'),
            'user': context.get('user', 'anonymous'),
            'user_id': context.get('user_id', 'unknown'),
            'user_email': context.get('user_email', 'unknown'),
            'user_groups': context.get('user_groups', 'none'),
            'page': context.get('page', 'unknown'),
            'message': message,
            'level': level,
            'ip': context.get('ip', 'unknown'),
            'user_agent': context.get('user_agent', 'unknown'),
            'session_id': context.get('session_id', 'no_session')
        }

        # Remove extracted fields from context to avoid duplication
        context_copy = context.copy()
        for field in ['type', 'user', 'user_id', 'user_email', 'user_groups', 
                     'page', 'ip', 'user_agent', 'session_id']:
            context_copy.pop(field, None)

        # Add remaining context as details
        if context_copy:
            log_data['details'] = context_copy

        self.write_log(log_data)

def main():
    """Handle logging from command line"""
    try:
        if len(sys.argv) < 4:
            print("Usage: logger.py <type> <message> <json_context>")
            sys.exit(1)

        log_type = sys.argv[1]
        message = sys.argv[2]
        try:
            context = json.loads(sys.argv[3])
        except json.JSONDecodeError as e:
            sys.stderr.write(f"ERROR: Failed to decode context: {str(e)}\n")
            context = {}

        logger = Logger(log_type)
        logger.log(message, context.get('level', 'INFO'), context)
        
    except Exception as e:
        sys.stderr.write(f"CRITICAL: Logger failed: {str(e)}\n")
        sys.exit(1)

if __name__ == '__main__':
    main()
