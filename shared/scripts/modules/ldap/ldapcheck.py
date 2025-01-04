#!/opt/python-venv/bin/python3
"""
LDAP Authentication Check
Handles user authentication against LDAP server with comprehensive error handling
"""

import os
import sys
import ldap
import json
import warnings
import traceback
from typing import Tuple, Optional

# Add parent directory to path for importing modules
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from logging_config import setup_logger, log_with_context

# Add the directory containing vault_utility.py to the Python path
sys.path.append(os.path.join(os.path.dirname(__file__), '../'))
from vault import vault_utility

# Suppress specific deprecation warning
warnings.filterwarnings(
    "ignore",
    category=DeprecationWarning,
    message="The raise_on_deleted_version parameter will change its default value to False in hvac v3.0.0."
)

# Initialize logger
logger = setup_logger('ldapcheck')

# Load vault token from /etc/vault.env
def load_vault_token():
    try:
        with open('/etc/vault.env', 'r') as f:
            for line in f:
                if line.strip() and not line.startswith('#'):
                    key, value = line.strip().split('=', 1)
                    if key == 'VAULT_TOKEN':
                        return value.strip()
    except Exception as e:
        log_with_context(logger, 'critical',
                        'Failed to load vault token',
                        error=str(e))
        raise Exception("Failed to load vault token from /etc/vault.env")
    raise Exception("VAULT_TOKEN not found in /etc/vault.env")

# Initialize vault utility with token from file
vault_token = load_vault_token()
vault_util = vault_utility.VaultUtility(token=vault_token)

# Get LDAP configuration from vault
try:
    ldap_server = vault_util.get_value_for_key("wens/portal/framework/config/ldap/adom_server")
    ldap_intl_server = vault_util.get_value_for_key("wens/portal/framework/config/ldap/international_server")
    ldap_base_dn = vault_util.get_value_for_key("wens/portal/framework/config/ldap/base_dn")
    ldap_user_fqdn = vault_util.get_value_for_key("wens/portal/framework/config/ldap/user_fqdn")
    
    log_with_context(logger, 'info', 
                    'Retrieved LDAP config from vault',
                    server=ldap_server,
                    base_dn=ldap_base_dn,
                    user_fqdn=ldap_user_fqdn)
except Exception as e:
    log_with_context(logger, 'critical',
                    'Failed to get LDAP config from vault',
                    error=str(e))
    raise

class LDAPCheckError(Exception):
    """Custom exception for LDAP check errors"""
    pass

def get_ldap_connection(server: str) -> ldap.ldapobject.LDAPObject:
    """
    Create and return an LDAP connection
    
    Args:
        server (str): LDAP server URL
        
    Returns:
        ldap.ldapobject.LDAPObject: LDAP connection object
        
    Raises:
        LDAPCheckError: If connection fails
    """
    try:
        # Log LDAP module version
        log_with_context(logger, 'info', 
                        'LDAP module info', 
                        version=ldap.__version__)
        
        # Initialize connection
        log_with_context(logger, 'info', 
                        'Setting LDAP options', 
                        server=server)
        
        ldap.set_option(ldap.OPT_X_TLS_REQUIRE_CERT, ldap.OPT_X_TLS_NEVER)
        ldap.set_option(ldap.OPT_DEBUG_LEVEL, 255)  # Maximum debug output
        
        log_with_context(logger, 'info', 
                        'Initializing LDAP connection')
        conn = ldap.initialize(server)
        
        log_with_context(logger, 'info', 
                        'Setting connection options')
        conn.set_option(ldap.OPT_REFERRALS, 0)
        conn.set_option(ldap.OPT_PROTOCOL_VERSION, 3)
        conn.set_option(ldap.OPT_DEBUG_LEVEL, 255)
        
        # Test connection without binding
        log_with_context(logger, 'info', 
                        'Testing LDAP connection')
        conn.get_option(ldap.OPT_PROTOCOL_VERSION)
        
        return conn
    except ldap.LDAPError as e:
        error_msg = f"LDAP connection error: {str(e)}"
        log_with_context(logger, 'error', 
                        error_msg, 
                        server=server,
                        error_type=type(e).__name__)
        raise LDAPCheckError(error_msg)
    except Exception as e:
        error_msg = f"Unexpected error during LDAP connection: {str(e)}"
        log_with_context(logger, 'critical', 
                        error_msg,
                        server=server,
                        error_type=type(e).__name__,
                        traceback=traceback.format_exc())
        raise LDAPCheckError(error_msg)

def authenticate_user(username: str, password: str, application: str) -> Tuple[str, str]:
    """
    Authenticate user against LDAP
    
    Args:
        username (str): Username to authenticate
        password (str): Password to verify
        app (str): Application name for logging
        
    Returns:
        Tuple[str, str]: Status and response message
        
    Note:
        Return format is "status||response" where:
        - status is "OK" or "ERROR"
        - response contains either error message or user details
    """
    try:
        # Log authentication attempt
        log_with_context(logger, 'info', 
                        'Authentication attempt', 
                        username=username, 
                        app=application)

        # Validate input
        if not all([username, password, app]):
            raise LDAPCheckError("Missing required parameters")

        # Format LDAP username
        ldap_username = f"{username}@{ldap_user_fqdn}"
        log_with_context(logger, 'info',
                        'Authenticating user',
                        ldap_username=ldap_username)
        
        # Connect and bind to LDAP
        try:
            conn = ldap.initialize(ldap_server)
            conn.set_option(ldap.OPT_REFERRALS, 0)
            conn.simple_bind_s(ldap_username, password)
            log_with_context(logger, 'info', 'LDAP bind successful')
        except ldap.INVALID_CREDENTIALS:
            log_with_context(logger, 'warning',
                           'Invalid credentials',
                           username=username)
            return "ERROR", "Invalid credentials"
        except ldap.SERVER_DOWN:
            log_with_context(logger, 'error',
                           'LDAP server down',
                           server=ldap_server)
            return "ERROR", "LDAP issue"
            
        try:
            # Search for user
            search_filter = f"(sAMAccountName={username})"
            results = conn.search_s(ldap_base_dn, ldap.SCOPE_SUBTREE, search_filter)
            
            if not results:
                log_with_context(logger, 'warning',
                               'User not found',
                               username=username)
                return "ERROR", "User not found"
            
            # Get ADOM configuration from vault
            adom_raw = vault_util.get_value_for_key(f"wens/portal/{application}/config/access/adom")
            ADOM = adom_raw.strip('[]').replace('"', '').split(',')
            
            # Process user's groups
            adom_groups = []
            valid_user = False
            cngroup = None
            
            for item in results[0][1]["memberOf"]:
                adom_group = item.decode("utf-8").split('CN=')[1].split(',')[0]
                if adom_group not in adom_groups:
                    adom_groups.append(adom_group)
                if adom_group in ADOM:
                    if 'cngroup' not in locals():
                        cngroup = adom_group
                    valid_user = True
            
            if not valid_user:
                log_with_context(logger, 'warning',
                               'User not authorized',
                               username=username,
                               groups=adom_groups)
                return "ERROR", "User not authorized"
            
            # Extract user information
            user_attrs = results[0][1]
            employee_num = user_attrs["employeeNumber"][0].decode("utf-8")
            employee_name = user_attrs["displayName"][0].decode("utf-8")
            employee_email = user_attrs["mail"][0].decode("utf-8")
            employee_vzid = username
            
            # Format response
            response = f"{employee_num}|{employee_name}|{employee_email}|{cngroup}|{employee_vzid}|{adom_groups}"
            
            # Log successful authentication
            log_with_context(logger, 'info', 
                           'Authentication successful', 
                           username=username,
                           groups=adom_groups)
            
            return "OK", response
            
        except ldap.INVALID_CREDENTIALS:
            error_msg = "Invalid credentials"
            log_with_context(logger, 'warning', 
                           error_msg, 
                           username=username)
            return "ERROR", error_msg
            
        except ldap.LDAPError as e:
            error_msg = f"LDAP error: {str(e)}"
            log_with_context(logger, 'error', 
                           error_msg, 
                           username=username)
            return "ERROR", error_msg
            
        finally:
            try:
                conn.unbind_s()
            except:
                pass
                
    except LDAPCheckError as e:
        log_with_context(logger, 'error', 
                        str(e), 
                        username=username)
        return "ERROR", str(e)
        
    except Exception as e:
        error_msg = f"Unexpected error: {str(e)}"
        log_with_context(logger, 'critical', 
                        error_msg,
                        username=username,
                        traceback=traceback.format_exc())
        return "ERROR", error_msg

def main():
    """Main entry point"""
    try:
        # Validate arguments
        if len(sys.argv) != 4:
            print("ERROR||Usage: ldapcheck.py username password app")
            sys.exit(1)
            
        # Get arguments
        username = sys.argv[1]
        password = sys.argv[2]
        application = sys.argv[3]
        
        # Load vault token and initialize vault utility
        try:
            vault_token = load_vault_token()
            log_with_context(logger, 'info', 'Successfully loaded vault token')
        except Exception as e:
            print(f"ERROR||{str(e)}")
            sys.exit(1)
            
        # Perform authentication
        status, response = authenticate_user(username, password, application)
        
        # Print response in expected format
        print(f"{status}||{response}")
        
    except Exception as e:
        print(f"ERROR||Script error: {str(e)}")
        sys.exit(1)

if __name__ == "__main__":
    main()
