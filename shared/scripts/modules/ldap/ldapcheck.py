#!/usr/bin/env python3
"""
LDAP Authentication Check
Handles user authentication against LDAP server with comprehensive error handling
"""

import os
import sys
import ldap
import json
import traceback
from typing import Tuple, Optional

# Add parent directory to path for importing logging_config
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from logging_config import setup_logger, log_with_context

# Initialize logger
logger = setup_logger('ldapcheck')

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
        # Initialize connection
        ldap.set_option(ldap.OPT_X_TLS_REQUIRE_CERT, ldap.OPT_X_TLS_NEVER)
        conn = ldap.initialize(server)
        conn.set_option(ldap.OPT_REFERRALS, 0)
        conn.set_option(ldap.OPT_PROTOCOL_VERSION, 3)
        return conn
    except ldap.LDAPError as e:
        error_msg = f"LDAP connection error: {str(e)}"
        log_with_context(logger, 'error', error_msg, server=server)
        raise LDAPCheckError(error_msg)

def authenticate_user(username: str, password: str, app: str) -> Tuple[str, str]:
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
                        app=app)

        # Validate input
        if not all([username, password, app]):
            raise LDAPCheckError("Missing required parameters")

        # LDAP configuration
        ldap_server = os.getenv('LDAP_SERVER', 'ldap://localhost:389')
        base_dn = os.getenv('LDAP_BASE_DN', 'dc=example,dc=com')
        
        # Connect to LDAP
        conn = get_ldap_connection(ldap_server)
        
        try:
            # Bind with user credentials
            user_dn = f"uid={username},{base_dn}"
            conn.simple_bind_s(user_dn, password)
            
            # Search for user attributes
            search_filter = f"(uid={username})"
            attrs = ['employeeNumber', 'cn', 'mail', 'memberOf']
            
            result = conn.search_s(base_dn, ldap.SCOPE_SUBTREE, 
                                 search_filter, attrs)
            
            if not result:
                raise LDAPCheckError("User not found in LDAP")
                
            # Extract user information
            user_attrs = result[0][1]
            employee_num = user_attrs.get('employeeNumber', [b''])[0].decode()
            employee_name = user_attrs.get('cn', [b''])[0].decode()
            employee_email = user_attrs.get('mail', [b''])[0].decode()
            
            # Process group memberships
            groups = [g.decode().split(',')[0].split('=')[1] 
                     for g in user_attrs.get('memberOf', [])]
            
            # Determine ADOM group (first group or 'user')
            adom_group = groups[0] if groups else 'user'
            
            # Format response
            response_data = [
                employee_num,
                employee_name,
                employee_email,
                adom_group,
                username,  # vzid
                json.dumps(groups)  # adom_groups
            ]
            
            response = '|'.join(response_data)
            
            # Log successful authentication
            log_with_context(logger, 'info', 
                           'Authentication successful', 
                           username=username,
                           groups=groups)
            
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
        app = sys.argv[3]
        
        # Perform authentication
        status, response = authenticate_user(username, password, app)
        
        # Print response in expected format
        print(f"{status}||{response}")
        
    except Exception as e:
        print(f"ERROR||Script error: {str(e)}")
        sys.exit(1)

if __name__ == "__main__":
    main()
