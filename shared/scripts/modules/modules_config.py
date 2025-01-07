"""
Modules Configuration
Sets up Python environment and imports required modules
"""

# Standard library imports
import sys
import os
import warnings

# LDAP authentication
import ldap

# Add parent directory to Python path for module imports
sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), '../')))

# Import all shared configurations
from shared_scripts_config import *

# Suppress warnings if needed
# warnings.filterwarnings('ignore')