#!/opt/python-venv/bin/python3

import sys
import os
import warnings
import base64
import requests
import logging
import ldap
import json
import pandas as pd
from datetime import datetime
from file_operations import FileLock

# Add the directory containing vault_utility.py to the Python path
sys.path.append('../')
sys.path.append(os.path.join(os.path.dirname(__file__)))

# Suppress specific deprecation warning
warnings.filterwarnings(
    "ignore",
    category=DeprecationWarning,
    message='The raise_on_deleted_version parameter will change its default value to False in hvac v3.0.0.'
)

# Import vault utility after path setup
from vault import vault_utility
vault_utility = vault_utility.VaultUtility()

# Get LDAP configuration from vault
ldap_user = vault_utility.get_value_for_key("config/ldap/username")
ldap_password = vault_utility.get_value_for_key("config/ldap/password")
ldap_server = vault_utility.get_value_for_key("config/ldap/adom_server")
ldap_intl_server = vault_utility.get_value_for_key("config/ldap/international_server")
ldap_base_dn = vault_utility.get_value_for_key("config/ldap/base_dn")
ldap_user_fqdn = vault_utility.get_value_for_key("config/ldap/user_fqdn")

def main():
    # Check command line arguments
    if len(sys.argv) != 4:
        print(f"Usage: {sys.argv[0]} <username> <password> <app>")
        sys.exit(1)

    username = sys.argv[1]
    ldapuser = f"{username}@{ldap_user_fqdn}"
    password = sys.argv[2]
    application = sys.argv[3]
    valid_user = 0

    # Initialize LDAP connection
    try:
        ldap_client = ldap.initialize(ldap_server)
        ldap_client.set_option(ldap.OPT_REFERRALS, 0)
        ldap_client.simple_bind_s(ldapuser, password)
    except ldap.INVALID_CREDENTIALS:
        print("ERROR||Invalid credentials")
        ldap_client.unbind()
        sys.exit(1)
    except ldap.SERVER_DOWN:
        print("ERROR||LDAP issue")
        sys.exit(1)

    # Search for user and verify permissions
    try:
        results = ldap_client.search_s(
            ldap_base_dn,
            ldap.SCOPE_SUBTREE,
            f"(sAMAccountName={username})"
        )

        if results:
            # Get ADOM configuration
            adom_raw = vault_utility.get_value_for_key(f"wens/portal/{application}/config/access/adom")
            ADOM = adom_raw.strip('[]"').replace('"', '').split(',')
            adom_groups = []

            # Process user groups
            for item in results[0][1]["memberOf"]:
                adom_group = item.decode("utf-8").split('CN=')[1].split(',')[0]
                if adom_group not in adom_groups:
                    adom_groups.append(adom_group)
                if adom_group in ADOM:
                    if 'cngroup' not in globals():
                        cngroup = adom_group
                    valid_user = 1

            if valid_user == 1:
                # Get user details
                employee_num = results[0][1]["employeeNumber"][0].decode("utf-8")
                employee_name = results[0][1]["displayName"][0].decode("utf-8")
                employee_mail = results[0][1]["mail"][0].decode("utf-8")
                employee_vzid = results[0][1]["vzid"][0].decode("utf-8")
                
                print(f"OK||{employee_num}||{employee_name}||{employee_mail}||{cngroup}||{employee_vzid}||{adom_groups}")
            else:
                print("ERROR||User not authorized")
        else:
            print("ERROR||User not found")

    except KeyError as e:
        print(f"ERROR||Missing key in LDAP results: {e}")
    except IndexError as e:
        print(f"ERROR||Index error in LDAP results: {e}")
    except Exception as error:
        print(f"ERROR||{error}")
    finally:
        ldap_client.unbind()

if __name__ == "__main__":
    main()