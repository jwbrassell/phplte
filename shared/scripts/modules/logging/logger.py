#!/usr/bin/env python3
import sys
import json
import os
from datetime import datetime
import uuid

def ensure_log_dir(level):
    """Ensure log directory exists for the given level."""
    # Get project root (4 levels up from this script)
    project_root = os.path.dirname(os.path.dirname(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))))
    log_dir = os.path.join(project_root, 'shared', 'data', 'logs', 'system', level)
    
    if not os.path.exists(log_dir):
        os.makedirs(log_dir, mode=0o755, exist_ok=True)
    
    return log_dir

def write_log(level, message, context):
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

def main():
    """Main entry point for logger script."""
    if len(sys.argv) < 4:
        print("Usage: logger.py <level> <message> <context>", file=sys.stderr)
        sys.exit(1)
    
    level = sys.argv[1]
    message = sys.argv[2]
    context = sys.argv[3]
    
    write_log(level, message, context)

if __name__ == '__main__':
    main()
