#!/usr/bin/env python3
"""API interface for on-call calendar operations."""

import sys
import json
from datetime import datetime
from pathlib import Path
import os
import sys

# Add parent directory to Python path for imports
current_dir = os.path.dirname(os.path.abspath(__file__))
sys.path.append(os.path.dirname(current_dir))
from oncall_calendar.calendar_manager import CalendarManager

def handle_teams(manager, action, data=None):
    """Handle team-related operations.
    
    Args:
        manager: CalendarManager instance
        action: Action to perform (get/add/update/delete)
        data: Optional data for the action
    
    Returns:
        dict: Response data
    """
    try:
        if action == 'get':
            teams = manager.get_teams()
            return {'teams': teams}
            
        elif action == 'add':
            if not data or 'name' not in data:
                raise ValueError("Team name is required")
            team = manager.add_team(
                name=data['name'],
                color=data.get('color', 'primary')
            )
            return {'team': team}
            
        elif action == 'update':
            if not data or 'id' not in data:
                raise ValueError("Team ID is required")
            team = manager.update_team(
                team_id=int(data['id']),
                name=data.get('name'),
                color=data.get('color')
            )
            return {'team': team}
            
        elif action == 'delete':
            if not data or 'id' not in data:
                raise ValueError("Team ID is required")
            manager.delete_team(int(data['id']))
            return {'message': 'Team deleted successfully'}
            
        else:
            raise ValueError(f"Unknown team action: {action}")
            
    except Exception as e:
        return {'error': str(e)}

def handle_events(manager, action, data=None):
    """Handle calendar event operations.
    
    Args:
        manager: CalendarManager instance
        action: Action to perform (get/current)
        data: Optional filter data
    
    Returns:
        dict: Response data
    """
    try:
        if action == 'get':
            if not data or 'start' not in data or 'end' not in data:
                raise ValueError("Start and end dates are required")
                
            start_date = datetime.fromisoformat(data['start'].replace('Z', '+00:00'))
            end_date = datetime.fromisoformat(data['end'].replace('Z', '+00:00'))
            team_id = int(data['team']) if 'team' in data and data['team'] else None
            
            rotations = manager.get_rotations(team_id, start_date, end_date)
            holidays = manager.get_holidays(start_date.year)
            
            # Format events for calendar
            events = []
            
            # Add rotations
            for rotation in rotations:
                events.append({
                    'id': f"rotation-{rotation['id']}",
                    'title': rotation['person_name'],
                    'start': rotation['start_time'],
                    'end': rotation['end_time'],
                    'description': f"Phone: {rotation['phone_number']}",
                    'classNames': [f"bg-{rotation.get('color', 'primary')}"],
                    'textColor': '#ffffff',
                    'allDay': True,
                    'display': 'block',
                    'extendedProps': {
                        'week_number': rotation['week_number'],
                        'phone': rotation['phone_number'],
                        'team_id': rotation['team_id']
                    }
                })
            
            # Add holidays
            for holiday in holidays:
                if start_date.date() <= datetime.fromisoformat(holiday['date']).date() <= end_date.date():
                    events.append({
                        'id': f"holiday-{holiday['id']}",
                        'title': f"🎉 {holiday['name']}",
                        'start': holiday['date'],
                        'allDay': True,
                        'display': 'background',
                        'backgroundColor': '#ff9f89',
                        'classNames': ['holiday-event']
                    })
            
            return {'events': events}
            
        elif action == 'current':
            team_id = int(data['team']) if data and 'team' in data else None
            rotation = manager.get_current_oncall(team_id)
            
            if rotation:
                return {
                    'name': rotation['person_name'],
                    'phone': rotation['phone_number'],
                    'start': rotation['start_time'],
                    'end': rotation['end_time']
                }
            return {
                'name': 'No one currently on call',
                'phone': '-'
            }
            
        else:
            raise ValueError(f"Unknown events action: {action}")
            
    except Exception as e:
        return {'error': str(e)}

def handle_holidays(manager, action, data=None):
    """Handle holiday operations.
    
    Args:
        manager: CalendarManager instance
        action: Action to perform (get)
        data: Optional filter data
    
    Returns:
        dict: Response data
    """
    try:
        if action == 'get':
            year = int(data['year']) if data and 'year' in data else datetime.now().year
            holidays = manager.get_holidays(year)
            return {'holidays': holidays}
            
        else:
            raise ValueError(f"Unknown holidays action: {action}")
            
    except Exception as e:
        return {'error': str(e)}

def main():
    """Main entry point for calendar API."""
    if len(sys.argv) < 4:
        print(json.dumps({
            'error': 'Missing required arguments. Usage: calendar_api.py <data_dir> <entity> <action> [data_json]'
        }))
        sys.exit(1)
    
    try:
        data_dir = sys.argv[1]
        entity = sys.argv[2]
        action = sys.argv[3]
        data = None
        if len(sys.argv) > 4 and sys.argv[4] != 'null':
            data = json.loads(sys.argv[4])
        
        manager = CalendarManager(data_dir)
        
        if entity == 'teams':
            result = handle_teams(manager, action, data)
        elif entity == 'events':
            result = handle_events(manager, action, data)
        elif entity == 'holidays':
            result = handle_holidays(manager, action, data)
        else:
            result = {'error': f"Unknown entity: {entity}"}
        
        print(json.dumps(result))
        
    except Exception as e:
        try:
            print(json.dumps({'error': str(e)}))
        except:
            print(json.dumps({'error': 'Internal server error'}))
        sys.exit(1)

if __name__ == '__main__':
    main()
