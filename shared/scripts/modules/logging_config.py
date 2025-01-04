"""
Logging Configuration
Provides consistent logging setup across Python scripts
"""

import os
import logging
from datetime import datetime
from logging.handlers import RotatingFileHandler

def setup_logger(script_name, log_level=logging.INFO):
    """
    Configure logging for Python scripts
    
    Args:
        script_name (str): Name of the script (used for log file naming)
        log_level (int): Logging level (default: INFO)
        
    Returns:
        logging.Logger: Configured logger instance
    """
    
    # Create logs directory if it doesn't exist
    log_dir = '/var/www/html/portal/logs/python'
    if not os.path.exists(log_dir):
        try:
            os.makedirs(log_dir, mode=0o755)
        except Exception as e:
            # If we can't create the directory, log to system logger
            logging.error(f"Failed to create log directory {log_dir}: {str(e)}")
            return None

    # Set up log file path
    log_file = os.path.join(log_dir, f"{datetime.now().strftime('%Y%m%d')}_{script_name}.log")
    
    try:
        # Create logger
        logger = logging.getLogger(script_name)
        logger.setLevel(log_level)
        
        # Create handlers
        file_handler = RotatingFileHandler(
            log_file,
            maxBytes=10485760,  # 10MB
            backupCount=5,
            mode='a'
        )
        
        # Create formatters and add it to handlers
        log_format = '%(asctime)s||%(levelname)s||%(name)s||%(message)s'
        file_formatter = logging.Formatter(log_format)
        file_handler.setFormatter(file_formatter)
        
        # Add handlers to the logger
        logger.addHandler(file_handler)
        
        return logger
        
    except Exception as e:
        # Log to system logger if setup fails
        logging.error(f"Failed to setup logger for {script_name}: {str(e)}")
        return None

def log_with_context(logger, level, message, **context):
    """
    Log a message with additional context
    
    Args:
        logger (logging.Logger): Logger instance
        level (str): Log level ('debug', 'info', 'warning', 'error', 'critical')
        message (str): Log message
        **context: Additional context parameters to include in log
    """
    if not logger:
        return
        
    # Format context information
    context_str = ' '.join(f"{k}={v}" for k, v in context.items())
    full_message = f"{message} {context_str}".strip()
    
    # Log at appropriate level
    log_func = getattr(logger, level.lower(), logger.info)
    log_func(full_message)
