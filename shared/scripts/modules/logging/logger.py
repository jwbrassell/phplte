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
            print(f"Script path: {script_path}")
            
            # Navigate up to project root (from shared/scripts/modules/logging)
            script_dir = os.path.dirname(os.path.abspath(__file__))
            root_dir = os.path.dirname(os.path.dirname(os.path.dirname(os.path.dirname(script_dir))))
            print(f"Root directory: {root_dir}")
            
            # Set log directory under shared/data/logs/system
            self.base_dir = Path(root_dir) / 'shared' / 'data' / 'logs' / 'system'
            self.log_dir = self.base_dir / log_type
            print(f"Log directory: {self.log_dir}")
            
            self.ensure_log_directory()
        except Exception as e:
            print(f"Error in initialization: {str(e)}")
            raise

    def ensure_log_directory(self):
        """Ensure the log directory exists with proper permissions"""
        try:
            print(f"Creating directory: {self.log_dir}")
            self.log_dir.mkdir(parents=True, exist_ok=True)
            
            # Set directory permissions to 755
            print("Setting directory permissions")
            os.chmod(self.log_dir, 0o755)
            
            # Verify permissions
            perms = oct(os.stat(self.log_dir).st_mode)[-3:]
            print(f"Directory permissions: {perms}")
            
            # Check if directory is writable
            if not os.access(self.log_dir, os.W_OK):
                print(f"Warning: Directory {self.log_dir} is not writable")
                
        except Exception as e:
            print(f"Error ensuring directory: {str(e)}")
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
        print(f"Writing to log file: {log_file}")
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
            print(f"Error writing log: {str(e)}")
            if temp_file:
                try:
                    os.unlink(temp_file.name)
                except:
                    pass
            raise

    def log(self, message, level='INFO', context=None):
        """Main logging method"""
        context = context or {}
        
        log_data = {
            'timestamp': datetime.now().isoformat(),
            'type': context.get('type', 'general'),
            'user': context.get('user', 'anonymous'),
            'message': message,
            'details': {
                'level': level,
                'ip': context.get('ip', 'unknown'),
                'user_agent': context.get('user_agent', 'unknown'),
                'session_id': context.get('session_id', 'no_session'),
                **context
            }
        }

        self.write_log(log_data)

def main():
    """Handle logging from command line"""
    try:
        if len(sys.argv) < 4:
            print("Usage: logger.py <type> <message> <json_context>")
            sys.exit(1)

        log_type = sys.argv[1]
        message = sys.argv[2]
        print(f"Log type: {log_type}")
        print(f"Message: {message}")
        
        try:
            context = json.loads(sys.argv[3])
            print(f"Context: {json.dumps(context, indent=2)}")
        except json.JSONDecodeError as e:
            print(f"Error decoding context: {str(e)}")
            context = {}

        logger = Logger(log_type)
        logger.log(message, context.get('level', 'INFO'), context)
        print("Log written successfully")
        
    except Exception as e:
        print(f"Error in main: {str(e)}")
        sys.exit(1)

if __name__ == '__main__':
    main()
