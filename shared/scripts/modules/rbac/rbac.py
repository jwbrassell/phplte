#!/opt/python-venv/bin/python3
"""
RBAC (Role-Based Access Control) Management
Handles RBAC configuration, menu navigation, and page permissions
"""

import uuid
import sys
import os
import json
from datetime import datetime
from rbac_config import *
from file_operations import FileLock

def compare_dicts(old_dict, new_dict):
    """
    Compare two dictionaries and return their differences
    
    Args:
        old_dict (dict): Original dictionary
        new_dict (dict): New dictionary to compare against
        
    Returns:
        dict: Dictionary containing differences
    """
    diff = {}
    all_keys = set(old_dict.keys()).union(set(new_dict.keys()))
    
    for key in all_keys:
        old_value = old_dict.get(key)
        new_value = new_dict.get(key)
        
        if isinstance(old_value, dict) and isinstance(new_value, dict):
            nested_diff = compare_dicts(old_value, new_value)
            if nested_diff:
                diff[key] = nested_diff
        elif old_value != new_value:
            diff[key] = new_value
            
    return diff

def update_menu_nav_data(app):
    """
    Update navigation menu data based on RBAC configuration
    
    Args:
        app (str): Application identifier
    """
    nav_file = f"/var/www/html/{app}/portal/config/menu-bar.json"
    rbac_file = f"/var/www/html/{app}/portal/config/rbac.json"

    # Read navigation data
    with FileLock(nav_file) as file_lock:
        result = file_lock.read()
        if result['success']:
            nav_data = result['data']
        else:
            print(result['error'])
            return False

    # Read RBAC data
    with FileLock(rbac_file) as file_lock:
        result = file_lock.read()
        if result['success']:
            rbac_data = result['data']
        else:
            print(result['error'])
            return False

    # Process each page in RBAC data
    for page in rbac_data['pages']:
        page_data = rbac_data['pages'][page]
        link_name = page_data['link_name']
        link_type = page_data['link_type']
        category_found = False

        # Handle category-based pages
        if "category" in link_type:
            category = page_data['category']
            img = rbac_data['categories'][category]['icon']
            category_found = True
        else:
            img = page_data['img']
        
        url = page_data['url']
        roles = page_data['roles']

        # Update single pages
        if not category_found:
            single_page_updated = False
            for nav_item in nav_data:
                if nav_item == link_name:
                    nav_data[link_name] = {
                        "type": "single",
                        "urls": {
                            link_name: {
                                "url": url,
                                "roles": roles
                            }
                        },
                        "img": img
                    }
                    single_page_updated = True

            if not single_page_updated:
                nav_data[link_name] = {
                    "type": "single",
                    "urls": {
                        link_name: {
                            "url": url,
                            "roles": roles
                        }
                    },
                    "img": img
                }

        # Update category pages
        else:
            category_page_data = {
                "url": page,
                "roles": roles
            }

            if category not in nav_data:
                nav_data[category] = {
                    "type": "category",
                    "urls": {},
                    "img": img
                }

            nav_data[category]["urls"][link_name] = category_page_data
            nav_data[category]["img"] = img

    # Save updated navigation data
    with FileLock(nav_file) as locked_file:
        write_result = locked_file.write(nav_data)
        if write_result['success']:
            print("Updated navigation data")
            return True
        else:
            print("FAILED to update navigation data")
            return False

def save_page_rbac(data):
    """
    Save RBAC configuration for a page
    
    Args:
        data (dict): Page RBAC configuration data
    """
    # Extract data
    created_date = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    link_name = data.get('link_name')
    link_type = data.get('link_type')
    filename = data.get('filename')
    old_adom_groups = data.get('old_adom_groups')
    new_adom_groups = data.get('new_adom_groups')
    full_name = data.get('full_name')
    vzid = data.get('vzid')
    app = data.get('app')
    image_icon = data.get('image')

    rbac_file = f"/var/www/html/{app}/portal/config/rbac.json"

    # Read current RBAC data
    with FileLock(rbac_file) as file_lock:
        result = file_lock.read()
        if result['success']:
            rbac_data = result['data']
        else:
            print(result['error'])
            return False

    # Prepare page data update
    update_page_data = {
        "link_name": link_name,
        "link_type": link_type,
        "url": filename,
        "roles": new_adom_groups
    }

    # Handle category-specific data
    if "category" in link_type:
        category = data.get('category')
        update_page_data['category'] = category

        # Update category icon
        if category in rbac_data['categories']:
            if rbac_data['categories'][category]['icon'] != image_icon:
                rbac_data['categories'][category]['icon'] = image_icon
        else:
            rbac_data['categories'][category] = {
                "icon": image_icon,
                "urls": {},
                "name": category
            }

        if filename not in rbac_data['categories'][category]['urls']:
            rbac_data['categories'][category]['urls'][filename] = {}

    # Update page data
    rbac_data['pages'][filename] = update_page_data

    # Update group lists
    for group in new_adom_groups:
        if group not in rbac_data["adom_groups"]:
            rbac_data["adom_groups"].append(group)
            rbac_data["roles"].append(group)

    # Build page row for display
    page_link = (f"{link_name}<br>"
                f'<button type="submit" name="btn_edit" id="btn_edit" '
                f'class="btn btn-sm btn-success" '
                f'onclick="manage_page(\'{link_name}\', \'{filename}\');">Edit</button>')
    
    role_badges = " ".join([f'<span class="badge badge-info">{role}</span>' 
                           for role in new_adom_groups])
    
    page_row = [page_link, filename, role_badges]

    # Update page table data
    row_match = False
    for row in rbac_data["pages_table_data"]:
        page_name = row[0].split("<br>")[0]
        if page_name == link_name and filename in row[0]:
            rbac_data["pages_table_data"].remove(row)
            rbac_data["pages_table_data"].append(page_row)
            row_match = True

    if not row_match:
        rbac_data["pages_table_data"].append(page_row)

    # Save updated RBAC data
    with FileLock(rbac_file) as locked_file:
        write_result = locked_file.write(rbac_data)
        if write_result['success']:
            update_menu_nav_data(app)
            return True
        else:
            print("FAILED to update RBAC data")
            return False

def main():
    """
    Main function to handle RBAC operations
    """
    try:
        payload = sys.argv[1]
        try:
            request_data = json.loads(payload)
        except json.JSONDecodeError as e:
            print(f"Error decoding JSON: {e}")
            sys.exit(1)

        data = request_data['data']
        action_type = data.get('action_type')

        if action_type == 'save_page_rbac':
            save_page_rbac(data)
        elif action_type == 'delete':
            delete_document(data, args)
        else:
            print(f"Unknown action type: {action_type}")
            sys.exit(1)

    except Exception as e:
        print(f"Error in main: {e}")
        sys.exit(1)

if __name__ == "__main__":
    main()