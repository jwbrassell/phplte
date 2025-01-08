#!/usr/bin/env python3
import sys
import json
import os
import time
from datetime import datetime
import uuid
from typing import Dict, Any, Optional

def cleanup_old_logs(directory: str, days_to_keep: int = 7) -> None:
    """Remove log files older than specified days.
    
    Args:
        directory: The directory to clean up
        days_to_keep: Number of days to keep logs for (default: 7)
    """
    current_time = time.time()
    cutoff = current_time - (days_to_keep * 86400)
    
    try:
        for root, _, files in os.walk(directory):
            for file in files:
                if not file.endswith('.json'):
                    continue
                    
                file_path = os.path.join(root, file)
                if os.path.getmtime(file_path) < cutoff:
                    try:
                        os.remove(file_path)
                    except OSError as e:
                        print(f"Error removing old log file {file_path}: {e}", file=sys.stderr)
    except Exception as e:
        print(f"Error during log cleanup: {e}", file=sys.stderr)

def ensure_log_dir(level: str) -> str:
    """Ensure log directory exists for the given level."""
    # Get project root (4 levels up from this script)
    project_root = os.path.dirname(os.path.dirname(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))))
    log_dir = os.path.join(project_root, 'shared', 'data', 'logs', 'system', level)
    
    if not os.path.exists(log_dir):
        os.makedirs(log_dir, mode=0o775, exist_ok=True)
    
    return log_dir

def write_log(level: str, message: str, context: Dict[str, Any]) -> bool:
    """Write a log entry to a JSON file."""
    try:
        # Parse context
        if isinstance(context, str):
            context = json.loads(context)
        
        # Create log entry
        log_entry = {
            'id': str(uuid.uuid4()),
            'timestamp': datetime.now().isoformat(),
            'level': level,
            'message': message,
            'context': context
        }
        
        # Get log directory
        log_dir = ensure_log_dir(level)
        
        # Create filename with timestamp
        filename = f"{datetime.now().strftime('%Y%m%d_%H%M%S')}_{uuid.uuid4().hex[:8]}.json"
        log_file = os.path.join(log_dir, filename)
        
        # Write log entry
        with open(log_file, 'w') as f:
            json.dump(log_entry, f, indent=2)
        
        return True
        
    except Exception as e:
        print(f"Error writing log: {str(e)}", file=sys.stderr)
        sys.exit(1)

def main() -> None:
    """Main entry point for logger script."""
    if len(sys.argv) < 4:
        print("Usage: logger.py <level> <message> <context>", file=sys.stderr)
        sys.exit(1)
    
    level = sys.argv[1]
    message = sys.argv[2]
    context = sys.argv[3]
    
    try:
        # Clean up old logs before writing new ones
        project_root = os.path.dirname(os.path.dirname(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))))
        log_dir = os.path.join(project_root, 'shared', 'data', 'logs', 'system')
        cleanup_old_logs(log_dir)
        
        # Write new log
        write_log(level, message, context)
    except Exception as e:
        print(f"Error in main: {e}", file=sys.stderr)
        sys.exit(1)

if __name__ == '__main__':
    main()
