#!/usr/bin/env python3
import json
import os
from datetime import datetime
from typing import Dict, List, Optional

class WeblinksManager:
    def __init__(self):
        self.data_dir = os.path.join(os.path.dirname(os.path.dirname(os.path.dirname(os.path.dirname(__file__)))), 
                                    'data', 'weblinks')
        self.data_file = os.path.join(self.data_dir, 'weblinks.json')
        self._ensure_data_file()

    def _ensure_data_file(self) -> None:
        """Ensure the data file and directory exist"""
        os.makedirs(self.data_dir, exist_ok=True)
        if not os.path.exists(self.data_file):
            initial_data = {
                "links": [],
                "tags": [],
                "next_id": 1
            }
            with open(self.data_file, 'w') as f:
                json.dump(initial_data, f, indent=4)

    def _load_data(self) -> Dict:
        """Load data from JSON file"""
        with open(self.data_file, 'r') as f:
            return json.load(f)

    def _save_data(self, data: Dict) -> None:
        """Save data to JSON file"""
        with open(self.data_file, 'w') as f:
            json.dump(data, f, indent=4)

    def get_all_links(self) -> List[Dict]:
        """Get all weblinks"""
        data = self._load_data()
        return data['links']

    def get_link(self, link_id: int) -> Optional[Dict]:
        """Get a specific weblink by ID"""
        data = self._load_data()
        for link in data['links']:
            if link['id'] == link_id:
                return link
        return None

    def create_link(self, url: str, title: str, description: str, icon: str, 
                   tags: List[str], created_by: str) -> Dict:
        """Create a new weblink"""
        data = self._load_data()
        link_id = data['next_id']
        data['next_id'] += 1

        # Handle tags
        for tag in tags:
            if tag not in data['tags']:
                data['tags'].append(tag)

        new_link = {
            'id': link_id,
            'url': url,
            'title': title,
            'description': description,
            'icon': icon,
            'tags': tags,
            'created_by': created_by,
            'created_at': datetime.utcnow().isoformat(),
            'updated_at': datetime.utcnow().isoformat(),
            'click_count': 0,
            'history': []
        }

        data['links'].append(new_link)
        self._save_data(data)
        return new_link

    def update_link(self, link_id: int, updates: Dict, changed_by: str) -> Optional[Dict]:
        """Update an existing weblink"""
        data = self._load_data()
        
        for i, link in enumerate(data['links']):
            if link['id'] == link_id:
                # Track changes for history
                changes = {}
                for key, new_value in updates.items():
                    if key in link and link[key] != new_value:
                        changes[key] = {
                            'old': link[key],
                            'new': new_value
                        }
                        link[key] = new_value

                # Handle tags
                if 'tags' in updates:
                    for tag in updates['tags']:
                        if tag not in data['tags']:
                            data['tags'].append(tag)

                # Add history entry if there were changes
                if changes:
                    history_entry = {
                        'changed_by': changed_by,
                        'changed_at': datetime.utcnow().isoformat(),
                        'changes': changes
                    }
                    link['history'].append(history_entry)
                    link['updated_at'] = datetime.utcnow().isoformat()

                self._save_data(data)
                return link
        return None

    def record_click(self, link_id: int) -> bool:
        """Record a click for a weblink"""
        data = self._load_data()
        for link in data['links']:
            if link['id'] == link_id:
                link['click_count'] = link.get('click_count', 0) + 1
                self._save_data(data)
                return True
        return False

    def get_common_links(self, limit: int = 10) -> List[Dict]:
        """Get common links (most clicked and recent)"""
        data = self._load_data()
        # Sort by click count and get top links
        sorted_links = sorted(data['links'], key=lambda x: x.get('click_count', 0), reverse=True)
        return sorted_links[:limit]

    def get_all_tags(self) -> List[str]:
        """Get all tags"""
        data = self._load_data()
        return data['tags']

    def add_tags(self, tags: List[str]) -> bool:
        """Add new tags"""
        data = self._load_data()
        added = False
        for tag in tags:
            if tag not in data['tags']:
                data['tags'].append(tag)
                added = True
        if added:
            self._save_data(data)
        return added

    def bulk_upload(self, links: List[Dict]) -> Dict:
        """Bulk upload links from CSV"""
        data = self._load_data()
        added = 0
        updated = 0
        skipped = 0
        errors = []

        for link in links:
            try:
                # Check for required fields
                if not link.get('url') or not link.get('title'):
                    skipped += 1
                    continue

                # Check for duplicate URL
                existing_link = None
                for l in data['links']:
                    if l['url'] == link['url']:
                        existing_link = l
                        break

                if existing_link:
                    # Update existing link
                    changes = {}
                    for key in ['title', 'description', 'icon']:
                        if key in link and link[key] != existing_link[key]:
                            changes[key] = {
                                'old': existing_link[key],
                                'new': link[key]
                            }
                            existing_link[key] = link[key]

                    # Handle tags
                    if 'tags' in link:
                        old_tags = set(existing_link['tags'])
                        new_tags = set(link['tags'])
                        if old_tags != new_tags:
                            changes['tags'] = {
                                'old': list(old_tags),
                                'new': list(new_tags)
                            }
                            existing_link['tags'] = link['tags']
                            # Add new tags to global tags list
                            for tag in link['tags']:
                                if tag not in data['tags']:
                                    data['tags'].append(tag)

                    if changes:
                        history_entry = {
                            'changed_by': link['created_by'],
                            'changed_at': datetime.utcnow().isoformat(),
                            'changes': changes
                        }
                        existing_link['history'].append(history_entry)
                        existing_link['updated_at'] = datetime.utcnow().isoformat()
                        updated += 1
                else:
                    # Create new link
                    link_id = data['next_id']
                    data['next_id'] += 1

                    # Handle tags
                    for tag in link.get('tags', []):
                        if tag not in data['tags']:
                            data['tags'].append(tag)

                    new_link = {
                        'id': link_id,
                        'url': link['url'],
                        'title': link['title'],
                        'description': link.get('description', ''),
                        'icon': link.get('icon', 'fas fa-link'),
                        'tags': link.get('tags', []),
                        'created_by': link['created_by'],
                        'created_at': datetime.utcnow().isoformat(),
                        'updated_at': datetime.utcnow().isoformat(),
                        'click_count': 0,
                        'history': []
                    }
                    data['links'].append(new_link)
                    added += 1

            except Exception as e:
                errors.append(f"Error processing link {link.get('url')}: {str(e)}")

        self._save_data(data)
        return {
            'success': True,
            'added': added,
            'updated': updated,
            'skipped': skipped,
            'errors': errors
        }

    def get_stats(self) -> Dict:
        """Get weblinks statistics"""
        data = self._load_data()
        total_links = len(data['links'])
        total_clicks = sum(link.get('click_count', 0) for link in data['links'])
        total_tags = len(data['tags'])
        
        # Get popular links
        popular_links = sorted(data['links'], key=lambda x: x.get('click_count', 0), reverse=True)[:10]
        
        # Get tags distribution
        tags_count = {}
        for link in data['links']:
            for tag in link.get('tags', []):
                tags_count[tag] = tags_count.get(tag, 0) + 1
        
        return {
            'total_links': total_links,
            'total_clicks': total_clicks,
            'total_tags': total_tags,
            'popular_links': [{'title': link['title'], 'clicks': link.get('click_count', 0)} 
                            for link in popular_links],
            'tags_distribution': [{'name': tag, 'count': count} 
                                for tag, count in tags_count.items()]
        }

def setup_logging():
    import logging
    log_file = os.path.join(os.path.dirname(__file__), 'weblinks.log')
    logging.basicConfig(
        filename=log_file,
        level=logging.DEBUG,
        format='%(asctime)s - %(levelname)s - %(message)s'
    )
    return logging.getLogger(__name__)

if __name__ == '__main__':
    import sys
    import json
    
    logger = setup_logging()
    logger.debug(f"Command arguments: {sys.argv}")
    
    if len(sys.argv) < 2:
        print(json.dumps({'error': 'No command provided'}))
        sys.exit(1)

    command = sys.argv[1]
    args = {}
    if len(sys.argv) > 2:
        try:
            args = json.loads(sys.argv[2])
        except json.JSONDecodeError:
            print(json.dumps({'error': 'Invalid JSON arguments'}))
            sys.exit(1)

    manager = WeblinksManager()

    try:
        if command == 'get_all_links':
            result = manager.get_all_links()
        elif command == 'get_link':
            result = manager.get_link(args.get('id'))
        elif command == 'create_link':
            result = manager.create_link(
                url=args.get('url'),
                title=args.get('title'),
                description=args.get('description', ''),
                icon=args.get('icon', 'fas fa-link'),
                tags=args.get('tags', []),
                created_by=args.get('created_by', 'system')
            )
        elif command == 'update_link':
            result = manager.update_link(
                link_id=args.get('id'),
                updates=args.get('updates', {}),
                changed_by=args.get('changed_by', 'system')
            )
        elif command == 'record_click':
            result = manager.record_click(args.get('id'))
        elif command == 'get_common_links':
            result = manager.get_common_links()
        elif command == 'get_all_tags':
            result = manager.get_all_tags()
        elif command == 'add_tags':
            result = manager.add_tags(args.get('tags', []))
        elif command == 'get_stats':
            result = manager.get_stats()
        elif command == 'bulk_upload':
            result = manager.bulk_upload(args.get('links', []))
        else:
            result = {'error': f'Unknown command: {command}'}
            
        # Log the result for debugging
        logger.debug(f"Command: {command}")
        logger.debug(f"Result type: {type(result)}")
        logger.debug(f"Result: {result}")
        
        # Basic output handling
        try:
            # Pre-process result to convert datetime objects
            def process_data(data):
                if isinstance(data, dict):
                    return {k: process_data(v) for k, v in data.items()}
                elif isinstance(data, list):
                    return [process_data(item) for item in data]
                elif isinstance(data, datetime):
                    return data.isoformat()
                else:
                    return data

            # Convert datetime objects to strings first
            def convert_datetime(obj):
                if isinstance(obj, datetime):
                    return obj.isoformat()
                return obj

            # Basic JSON output with datetime handling
            if isinstance(result, (dict, list)):
                # Convert result to a simple dictionary
                if isinstance(result, list):
                    result = {'items': result}
                elif not isinstance(result, dict):
                    result = {'value': result}
                
                # Add success flag
                result['success'] = True
                
                # Encode with proper JSON formatting
                print(json.dumps(result, default=str, separators=(',', ': ')))
            else:
                print('{"error":"Invalid result type"}')
        except Exception as e:
            logger.error(f"Error encoding result: {e}")
            print(json.dumps({'error': 'Failed to encode result'}))
    except Exception as e:
        print(json.dumps({'error': str(e)}))
