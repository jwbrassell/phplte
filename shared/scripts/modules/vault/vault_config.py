"""
Vault Module Configuration
Sets up imports and configurations for vault operations
"""

import sys
import os

# Add parent directory to Python path for module imports
sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), '..')))

# Import shared configurations
from modules_config import *

# Vault specific configurations
VAULT_ENV_FILE = '/etc/vault.env'
DEFAULT_VAULT_URL = 'http://127.0.0.1:8200'
DEFAULT_MOUNT_POINT = 'kv'

# Logging configuration for vault operations
VAULT_LOG_LEVEL = 'INFO'
VAULT_LOG_FORMAT = '%(asctime)s - %(name)s - %(levelname)s - %(message)s'