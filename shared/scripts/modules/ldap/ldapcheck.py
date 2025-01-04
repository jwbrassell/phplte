#!/opt/python-venv/bin/python3

import sys
import os
import warnings
import base64
import requests
import logging
import ldap
from datetime import datetime
import pandas as pd
import json

# Suppress specific deprecation warning
warnings.filterwarnings("ignore", category=DeprecationWarning, 
    message="The raise_on_deleted_version parameter will change its default value to False in hvac v3.0.0.")

# Configure logging to suppress debug output
logging.basicConfig(level=logging.ERROR)

# Add the directory containing modules to the Python path
script_dir = os.path.dirname(os.path.abspath(__file__))
modules_dir = os.path.dirname(script_dir)  # parent directory containing all modules
sys.path.append(modules_dir)

from vault.vault_utility import VaultUtility
vault_utility = VaultUtility()

# Get LDAP configuration from vault
ldap_user = vault_utility.get_value_for_key("wens/portal/framework/config/ldap/username")
ldap_password = vault_utility.get_value_for_key("wens/portal/framework/config/ldap/password")
ldap_server = vault_utility.get_value_for_key("wens/portal/framework/config/ldap/adom_server")
ldap_intl_server = vault_utility.get_value_for_key("wens/portal/framework/config/ldap/international_server")
ldap_base_dn = vault_utility.get_value_for_key("wens/portal/framework/config/ldap/base_dn")
ldap_user_fqdn = vault_utility.get_value_for_key("wens/portal/framework/config/ldap/user_fqdn")

# Import file operations from modules directory
from file_operations import FileLock

# Check command line arguments
if len(sys.argv) != 4:
    print("Usage: %s <username> <password> <app>" % (sys.argv[0]))
    sys.exit(1)

username = sys.argv[1]
ldapuser = f"{username}@{ldap_user_fqdn}"
password = "%s" % sys.argv[2]
application = sys.argv[3]
valid_user = 0

try:
    ldap_client = ldap.initialize(ldap_server)
    ldap_client.set_option(ldap.OPT_REFERRALS, 0)
    ldap_client.simple_bind_s(ldapuser, password)
except ldap.INVALID_CREDENTIALS:
    print("ERROR! Invalid credentials")
    ldap_client.unbind()
    sys.exit(1)
except ldap.SERVER_DOWN:
    print("ERROR! LDAP issue")
    sys.exit(1)

try:
    results = ldap_client.search_s(ldap_base_dn, ldap.SCOPE_SUBTREE, "(sAMAccountName=%s)" % username)
    if results:
        adom_raw = vault_utility.get_value_for_key(f"wens/portal/{application}/config/access/adom")
        ADOM = adom_raw.strip('[]').replace('"', '').split(',')
        adom_groups = []

        for item in results[0][1]["memberOf"]:
            adom_group = item.decode('utf-8').split('CN=')[1].split(',')[0]
            if adom_group not in adom_groups:
                adom_groups.append(adom_group)
            
            if adom_group in ADOM:
                if 'cngroup' not in globals():
                    cngroup = adom_group
                valid_user = 1

        if valid_user == 1:
            employee_num = results[0][1]["employeeNumber"][0].decode("utf-8")
            employee_name = results[0][1]["displayName"][0].decode("utf-8")
            employee_mail = results[0][1]["mail"][0].decode("utf-8")
            employee_vzid = results[0][1]["extensionAttribute8"][0].decode("utf-8")
            print("OK!|{0}|{1}|{2}|{3}|{4}|{5}".format(
                employee_num, employee_name, employee_mail, 
                cngroup, employee_vzid, adom_groups))
        else:
            print("ERROR! User not authorized")
    else:
        print("ERROR! User not found")
except KeyError as e:
    print(f"ERROR! Missing key in LDAP results: {e}")
except IndexError as e:
    print(f"ERROR! Index error in LDAP results: {e}")
except Exception as error:
    print(f"ERROR! {error}")
finally:
    ldap_client.unbind()
