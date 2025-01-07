"""
Documents Module Configuration
Sets up Python path and imports required modules for document operations
"""

import sys
import os

# Add parent directory to Python path for module imports
sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), '..')))

# Import all shared configurations
from modules_config import *