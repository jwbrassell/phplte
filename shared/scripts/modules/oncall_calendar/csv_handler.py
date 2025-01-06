#!/usr/bin/env python3
"""CSV import handler for on-call calendar."""

import csv
import sys
import json
import os
from datetime import datetime
from pathlib import Path

# Add parent directory to Python path for imports
current_dir = os.path.dirname(os.path.abspath(__file__))
sys.path.append(os.path.dirname(current_dir))
from oncall_calendar.calendar_manager import CalendarManager

def parse_csv(file_path, auto_generate=False):
    """Parse CSV file content.
    
    Args:
        file_path (str): Path to CSV file
        auto_generate (bool): Whether to parse for auto-generation
        
    Returns:
        list: Parsed rows
    """
    required_fields = ['name', 'phone']
    if not auto_generate:
        required_fields.append('week')
    
    try:
        with open(file_path, 'r', encoding='utf-8-sig') as f:
            reader = csv.DictReader(f)
            
            # Validate headers
            if not all(field in reader.fieldnames for field in required_fields):
                raise ValueError(f"CSV must contain columns: {', '.join(required_fields)}")
            
            # Parse rows
            rows = []
            for row_num, row in enumerate(reader, start=1):
                # Validate required fields
                if not all(row.get(field, '').strip() for field in required_fields):
                    raise ValueError(f"Missing values in row {row_num}")
                
                if not auto_generate:
                    try:
                        week = int(row['week'])
                        if week < 1 or week > 53:
                            raise ValueError(f"Invalid week number in row {row_num}: {week}")
                    except ValueError:
                        raise ValueError(f"Invalid week format in row {row_num}: {row['week']}")
                
                rows.append({
                    'name': row['name'].strip(),
                    'phone': row['phone'].strip(),
                    'week': int(row['week']) if not auto_generate else None
                })
            
            if not rows:
                raise ValueError("No valid rows found in CSV")
            
            return rows
            
    except (csv.Error, UnicodeDecodeError) as e:
        raise ValueError(f"Error reading CSV file: {str(e)}")

def parse_holiday_csv(file_path):
    """Parse holiday CSV file.
    
    Args:
        file_path (str): Path to CSV file
        
    Returns:
        list: Parsed holidays
    """
    required_fields = ['name', 'date']
    
    try:
        with open(file_path, 'r', encoding='utf-8-sig') as f:
            reader = csv.DictReader(f)
            
            # Validate headers
            if not all(field in reader.fieldnames for field in required_fields):
                raise ValueError(f"CSV must contain columns: {', '.join(required_fields)}")
            
            # Parse rows
            holidays = []
            for row_num, row in enumerate(reader, start=1):
                # Validate required fields
                if not all(row.get(field, '').strip() for field in required_fields):
                    raise ValueError(f"Missing values in row {row_num}")
                
                # Validate date format
                try:
                    date = datetime.strptime(row['date'].strip(), '%Y-%m-%d').date()
                except ValueError:
                    raise ValueError(f"Invalid date format in row {row_num}. Use YYYY-MM-DD")
                
                holidays.append({
                    'name': row['name'].strip(),
                    'date': date.isoformat()
                })
            
            if not holidays:
                raise ValueError("No valid holidays found in CSV")
            
            return holidays
            
    except (csv.Error, UnicodeDecodeError) as e:
        raise ValueError(f"Error reading CSV file: {str(e)}")

def main():
    """Main entry point for CSV processing."""
    if len(sys.argv) < 5:
        print(json.dumps({
            'error': 'Missing required arguments. Usage: csv_handler.py <data_dir> <action> <team_id> <file_path> [year] [auto_generate]'
        }))
        sys.exit(1)
    
    try:
        data_dir = sys.argv[1]
        action = sys.argv[2]
        team_id = int(sys.argv[3])
        file_path = sys.argv[4]
        
        # Optional arguments
        year = int(sys.argv[5]) if len(sys.argv) > 5 else datetime.now().year
        schedule_mode = sys.argv[6] if len(sys.argv) > 6 else 'manual'
        auto_generate = schedule_mode in ['auto', 'weekly']
        
        if not os.path.exists(file_path):
            raise ValueError(f"File not found: {file_path}")
        
        manager = CalendarManager(data_dir)
        
        if action == 'upload_schedule':
            rows = parse_csv(file_path, auto_generate)
            
            if auto_generate:
                # Extract names and phones for schedule generation
                names_and_phones = [(row['name'], row['phone']) for row in rows]
                if schedule_mode == 'weekly':
                    schedule = manager.generate_weekly_rotation(team_id, year, names_and_phones)
                else:
                    schedule = manager.generate_schedule(team_id, year, names_and_phones)
                print(json.dumps({
                    'message': f'Successfully generated {len(schedule)} rotations'
                }))
            else:
                # Process each row individually
                for row in rows:
                    manager.add_rotation(
                        team_id=team_id,
                        person_name=row['name'],
                        phone_number=row['phone'],
                        week_number=row['week'],
                        year=year
                    )
                print(json.dumps({
                    'message': f'Successfully uploaded {len(rows)} rotations'
                }))
                
        elif action == 'upload_holidays':
            holidays = parse_holiday_csv(file_path)
            imported = manager.import_holidays(holidays)
            print(json.dumps({
                'message': f'Successfully imported {len(imported)} holidays'
            }))
            
        else:
            raise ValueError(f"Unknown action: {action}")
            
    except Exception as e:
        try:
            print(json.dumps({'error': str(e)}))
        except:
            print(json.dumps({'error': 'Internal server error'}))
        sys.exit(1)

if __name__ == '__main__':
    main()
