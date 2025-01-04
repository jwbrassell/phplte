#!/opt/python-venv/bin/python3
"""
Document Management System
Handles document operations including saving, updating, and configuration management
"""

import uuid
import sys
import os
from documents_config import *
from modules_config import *
from file_operations import FileLock

def update_documents_config(data):
    """
    Update document configuration with new categories and tags
    
    Args:
        data (dict): Document data including app, category, and tags
    """
    try:
        app = data.get('app')
        category = data.get('category')
        tags = data.get('tags')
        config_file = f"/var/www/html/{app}/portal/config/config.json"

        with FileLock(config_file) as file_lock:
            result = file_lock.read()
            if result['success']:
                existing_data = result['data']
            else:
                existing_data = {}

            # Initialize data structure if needed
            if "data" not in existing_data:
                existing_data["data"] = []

            # Get existing categories and tags
            existing_categories = existing_data.get(app, {}).get('docs', {}).get('categories', [])
            existing_tags = existing_data.get(app, {}).get('docs', {}).get('tags', [])

            # Add new tags if they don't exist
            for tag in tags:
                if len(tag) > 1 and tag not in existing_tags:
                    existing_tags.append(tag)

            # Add new category if it doesn't exist
            if category not in existing_categories:
                existing_categories.append(category)

            # Update the configuration structure
            if app not in existing_data:
                existing_data[app] = {'docs': {}}
            if 'docs' not in existing_data[app]:
                existing_data[app]['docs'] = {}

            existing_data[app]['docs']['categories'] = existing_categories
            existing_data[app]['docs']['tags'] = existing_tags

            # Write updated configuration
            with FileLock(config_file) as locked_file:
                write_result = locked_file.write(existing_data)
                if not write_result['success']:
                    raise Exception(f"Failed to write config: {write_result.get('error')}")

    except Exception as e:
        print(f"Error in update_documents_config: {e}")
        return False
    
    return True

def save_document(data):
    """
    Save a new document with metadata
    
    Args:
        data (dict): Document data including content and metadata
    """
    # Extract document data
    vzid = data.get('vzid')
    user_email = data.get('user_email')
    app = data.get('app')
    file_name = data.get('file_name')
    category = data.get('category')
    adom = data.get('adom')
    tags = data.get('tags')
    summernote_content = data.get('summernote_content')

    # Set up paths
    documentation_directory = f"/var/www/html/{app}/portal/data/{app}/docs/"
    docs_file = f"/var/www/html/{app}/portal/data/{app}/docs/docs.json"

    # Generate metadata
    unique_id = str(uuid.uuid4())
    created_date = datetime.now().strftime("%Y-%m-%d %H:%M:%S")

    # Read existing documents
    with FileLock(docs_file) as file_lock:
        result = file_lock.read()
        if result['success']:
            existing_data = result['data']
        else:
            print(result['error'])
            return False

        # Initialize data structure if needed
        if "data" not in existing_data:
            existing_data["data"] = []
            existing_data["headers"] = ["Title", "Tags", "Category", "ADOM"]

        # Prepare document data
        file_name_and_path = f"{documentation_directory}{unique_id}.json"
        data_dict = {
            "app": app,
            "title": file_name,
            "category": category,
            "adom": adom,
            "tags": tags,
            "summernote_content": summernote_content,
            "created_date": created_date
        }

        # Add new document data
        new_row = [file_name, tags, category, adom]
        if new_row not in existing_data["data"]:
            existing_data["data"].append(new_row)
        existing_data[unique_id] = data_dict

        # Write updated document data
        update_config_file = False
        with FileLock(docs_file) as locked_file:
            write_result = locked_file.write(existing_data)
            if write_result['success']:
                update_config_file = True
                print("Created Doc")

        # Update configuration if needed
        if update_config_file:
            update_documents_config(data)

def delete_document(data, args):
    """
    Delete a document and update configurations
    
    Args:
        data (dict): Document data
        args: Additional arguments for deletion
    """
    # Implementation for delete_document
    pass

def main():
    """
    Main function to handle document operations
    """
    try:
        payload = sys.argv[1]
        try:
            request_data = json.loads(payload)
        except json.JSONDecodeError as e:
            print(f"Error decoding JSON: {e}")
            print("\n")
            print(payload)
            sys.exit(1)

        data = request_data['data']
        action_type = data.get('action_type')

        if action_type == 'save':
            save_document(data)
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