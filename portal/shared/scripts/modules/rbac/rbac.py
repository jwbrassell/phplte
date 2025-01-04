#!/usr/bin/env python3
import json
import sys
import os
from pathlib import Path

def update_menu_config(data):
    """Updates menu-bar.json with new RBAC configuration"""
    try:
        # Get portal directory path
        script_dir = Path(__file__).resolve().parent
        portal_dir = script_dir.parent.parent.parent
        menu_file = portal_dir / 'config' / 'menu-bar.json'
        
        # Load current configuration
        with open(menu_file) as f:
            menu_data = json.load(f)
        
        # Extract request data
        page_data = data['data']
        link_name = page_data['link_name']
        link_type = page_data['link_type']
        filename = page_data['filename']
        new_groups = page_data['new_adom_groups']
        image = page_data['image']
        
        # Prepare new page configuration
        page_config = {
            'type': link_type,
            'img': image,
            'urls': {
                link_name: {
                    'url': filename,
                    'roles': new_groups
                }
            }
        }
        
        # Update configuration
        if link_type == 'category' and 'category' in page_data:
            menu_data[page_data['category']] = page_config
        else:
            # Use sanitized link name as key
            key = ''.join(c if c.isalnum() else '_' for c in link_name.lower())
            menu_data[key] = page_config
        
        # Save updated configuration
        with open(menu_file, 'w') as f:
            json.dump(menu_data, f, indent=2)
            
        # Output the entire updated file content
        with open(menu_file) as f:
            print(f.read())
            
        return True
    except Exception as e:
        print(f"Error: {str(e)}", file=sys.stderr)
        return False

if __name__ == '__main__':
    if len(sys.argv) > 1:
        try:
            request_data = json.loads(sys.argv[1])
            if update_menu_config(request_data):
                sys.exit(0)
        except json.JSONDecodeError as e:
            print(f"Error decoding JSON: {str(e)}", file=sys.stderr)
        except Exception as e:
            print(f"Error: {str(e)}", file=sys.stderr)
    sys.exit(1)
