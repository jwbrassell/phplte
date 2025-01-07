"""
RBAC Module Configuration
Sets up imports and configurations for Role-Based Access Control operations
"""

import sys
import os

# Add parent directory to Python path for module imports
sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), '..')))

# Import shared configurations
from modules_config import *