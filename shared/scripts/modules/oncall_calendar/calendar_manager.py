#!/usr/bin/env python3
"""Calendar manager for on-call rotations using JSON storage."""

import json
import os
import sys
from datetime import datetime, timedelta, timezone
from pathlib import Path

# Add parent directory to Python path for imports
current_dir = os.path.dirname(os.path.abspath(__file__))
sys.path.append(os.path.dirname(current_dir))
from oncall_calendar.file_lock import FileManager

class CalendarManager:
    """Manages on-call calendar operations using JSON files."""
    
    def __init__(self, data_dir):
        """Initialize the calendar manager.
        
        Args:
            data_dir (str): Directory for JSON data files
        """
        self.file_manager = FileManager(data_dir)
        self.teams_file = "teams.json"
        self.rotations_file = "rotations.json"
        self.holidays_file = "holidays.json"

    def _generate_id(self, existing_ids):
        """Generate a new unique ID."""
        if not existing_ids:
            return 1
        return max(existing_ids) + 1

    # Team Operations
    def get_teams(self):
        """Get all teams."""
        data = self.file_manager.read_json(self.teams_file, {"teams": []})
        return data.get("teams", [])

    def add_team(self, name, color="primary"):
        """Add a new team.
        
        Args:
            name (str): Team name
            color (str): Bootstrap color class
        
        Returns:
            dict: Created team
        """
        teams = self.get_teams()
        
        # Check if team already exists
        if any(t["name"] == name for t in teams):
            raise ValueError("Team already exists")
            
        # Generate new team ID
        team_id = self._generate_id([t["id"] for t in teams]) if teams else 1
        
        team = {
            "id": team_id,
            "name": name,
            "color": color,
            "created_at": datetime.utcnow().isoformat()
        }
        
        teams.append(team)
        self.file_manager.write_json(self.teams_file, {"teams": teams})
        return team

    def update_team(self, team_id, name=None, color=None):
        """Update a team.
        
        Args:
            team_id (int): Team ID
            name (str, optional): New team name
            color (str, optional): New team color
        
        Returns:
            dict: Updated team
        """
        teams = self.get_teams()
        for team in teams:
            if team["id"] == team_id:
                if name:
                    team["name"] = name
                if color:
                    team["color"] = color
                self.file_manager.write_json(self.teams_file, {"teams": teams})
                return team
        raise ValueError("Team not found")

    def delete_team(self, team_id):
        """Delete a team and its rotations.
        
        Args:
            team_id (int): Team ID
        """
        teams = self.get_teams()
        teams = [t for t in teams if t["id"] != team_id]
        self.file_manager.write_json(self.teams_file, {"teams": teams})
        
        # Delete team's rotations
        rotations = self.get_rotations()
        rotations = [r for r in rotations if r["team_id"] != team_id]
        self.file_manager.write_json(self.rotations_file, {"rotations": rotations})

    # Rotation Operations
    def get_rotations(self, team_id=None, start_date=None, end_date=None):
        """Get on-call rotations with optional filters.
        
        Args:
            team_id (int, optional): Filter by team
            start_date (datetime, optional): Filter by start date
            end_date (datetime, optional): Filter by end date
        
        Returns:
            list: Filtered rotations
        """
        data = self.file_manager.read_json(self.rotations_file, {"rotations": []})
        rotations = data.get("rotations", [])
        
        if team_id:
            rotations = [r for r in rotations if r["team_id"] == team_id]
            
        if start_date:
            rotations = [r for r in rotations if datetime.fromisoformat(r["end_time"]) > start_date]
            
        if end_date:
            rotations = [r for r in rotations if datetime.fromisoformat(r["start_time"]) < end_date]
            
        return rotations

    def add_rotation(self, team_id, person_name, phone_number, week_number, year):
        """Add a new on-call rotation.
        
        Args:
            team_id (int): Team ID
            person_name (str): Name of person on call
            phone_number (str): Contact phone number
            week_number (int): ISO week number
            year (int): Year
        
        Returns:
            dict: Created rotation
            
        Raises:
            ValueError: If a rotation already exists for this team during the specified time period
        """
        # Calculate dates
        jan_first = datetime(year, 1, 1)
        days_to_monday = (jan_first.weekday() - 0) % 7
        first_monday = jan_first - timedelta(days=days_to_monday)
        target_monday = first_monday + timedelta(weeks=week_number-1)
        target_friday = target_monday + timedelta(days=4)
        
        # Create time in Central Time (UTC-6)
        central_offset = timedelta(hours=-6)
        central_tz = timezone(central_offset)
        new_start_time = datetime.combine(
            target_friday.date(),
            datetime.strptime("17:00", "%H:%M").time()
        ).replace(tzinfo=central_tz)
        
        new_end_time = new_start_time + timedelta(days=7)
        
        # Convert to UTC
        new_start_time_utc = new_start_time.astimezone(timezone.utc)
        new_end_time_utc = new_end_time.astimezone(timezone.utc)
        
        # Check for existing rotations in this time period
        rotations = self.get_rotations(team_id)
        for rotation in rotations:
            existing_start = datetime.fromisoformat(rotation["start_time"])
            existing_end = datetime.fromisoformat(rotation["end_time"])
            
            # Check for overlap
            if (new_start_time_utc <= existing_end and 
                new_end_time_utc >= existing_start):
                raise ValueError(
                    f"A rotation already exists for team {team_id} during week {week_number}"
                )
        
        # Create rotation
        rotations = self.get_rotations()
        rotation_id = self._generate_id([r["id"] for r in rotations]) if rotations else 1
        
        rotation = {
            "id": rotation_id,
            "team_id": team_id,
            "person_name": person_name,
            "phone_number": phone_number,
            "week_number": week_number,
            "year": year,
            "start_time": new_start_time_utc.isoformat(),
            "end_time": new_end_time_utc.isoformat(),
            "created_at": datetime.utcnow().isoformat()
        }
        
        rotations.append(rotation)
        self.file_manager.write_json(self.rotations_file, {"rotations": rotations})
        return rotation

    def get_current_oncall(self, team_id=None):
        """Get the current on-call person.
        
        Args:
            team_id (int, optional): Filter by team
            
        Returns:
            dict: Current on-call rotation or None
        """
        current_time = datetime.now(timezone.utc)
        rotations = self.get_rotations(team_id)
        
        for rotation in rotations:
            start_time = datetime.fromisoformat(rotation["start_time"])
            end_time = datetime.fromisoformat(rotation["end_time"])
            
            if start_time <= current_time <= end_time:
                return rotation
                
        return None

    # Holiday Operations
    def get_holidays(self, year=None):
        """Get company holidays.
        
        Args:
            year (int, optional): Filter by year
            
        Returns:
            list: Holidays
        """
        data = self.file_manager.read_json(self.holidays_file, {"holidays": []})
        holidays = data.get("holidays", [])
        
        if year:
            holidays = [h for h in holidays if datetime.fromisoformat(h["date"]).year == year]
            
        return holidays

    def add_holiday(self, name, date):
        """Add a company holiday.
        
        Args:
            name (str): Holiday name
            date (str): Holiday date (YYYY-MM-DD)
            
        Returns:
            dict: Created holiday
        """
        holidays = self.get_holidays()
        holiday_id = self._generate_id([h["id"] for h in holidays]) if holidays else 1
        
        holiday = {
            "id": holiday_id,
            "name": name,
            "date": date,
            "created_at": datetime.utcnow().isoformat()
        }
        
        holidays.append(holiday)
        self.file_manager.write_json(self.holidays_file, {"holidays": holidays})
        return holiday

    def import_holidays(self, holiday_data):
        """Import multiple holidays.
        
        Args:
            holiday_data (list): List of holiday dicts with name and date
            
        Returns:
            list: Created holidays
        """
        holidays = []
        for holiday in holiday_data:
            try:
                h = self.add_holiday(holiday["name"], holiday["date"])
                holidays.append(h)
            except Exception as e:
                # Log error but continue processing
                print(f"Error importing holiday: {str(e)}")
                
        return holidays

    def generate_weekly_rotation(self, team_id, year, names_and_phones):
        """Generate a weekly rotation schedule.
        
        Args:
            team_id (int): Team ID
            year (int): Year to generate for
            names_and_phones (list): List of (name, phone) tuples
            
        Returns:
            list: Generated rotations
        """
        # Calculate total weeks
        jan_first = datetime(year, 1, 1)
        dec_31 = datetime(year, 12, 31)
        total_weeks = int((dec_31 - jan_first).days / 7) + 1
        
        # Generate schedule
        schedule = []
        person_index = 0
        num_people = len(names_and_phones)
        
        for week_num in range(1, total_weeks + 1):
            # Get person for this week
            person_name, phone_number = names_and_phones[person_index]
            
            # Create rotation
            rotation = self.add_rotation(
                team_id=team_id,
                person_name=person_name,
                phone_number=phone_number,
                week_number=week_num,
                year=year
            )
            
            schedule.append(rotation)
            
            # Move to next person, wrapping around if needed
            person_index = (person_index + 1) % num_people
        
        return schedule

    def generate_schedule(self, team_id, year, names_and_phones):
        """Generate a full year schedule.
        
        Args:
            team_id (int): Team ID
            year (int): Year to generate for
            names_and_phones (list): List of (name, phone) tuples
            
        Returns:
            list: Generated rotations
        """
        # Get holidays for the year
        holidays = self.get_holidays(year)
        holiday_dates = {h["date"] for h in holidays}
        
        # Calculate total weeks
        jan_first = datetime(year, 1, 1)
        dec_31 = datetime(year, 12, 31)
        total_weeks = int((dec_31 - jan_first).days / 7) + 1
        
        # Create list of weeks with holiday counts
        weeks = []
        for week_num in range(1, total_weeks + 1):
            # Calculate week dates
            days_to_monday = (jan_first.weekday() - 0) % 7
            first_monday = jan_first - timedelta(days=days_to_monday)
            target_monday = first_monday + timedelta(weeks=week_num-1)
            
            # Count holidays in week
            holiday_count = sum(1 for i in range(7) if 
                (target_monday + timedelta(days=i)).strftime("%Y-%m-%d") in holiday_dates)
            
            weeks.append({
                "week_number": week_num,
                "holiday_count": holiday_count
            })
        
        # Sort weeks by holiday count (descending)
        weeks.sort(key=lambda x: x["holiday_count"], reverse=True)
        
        # Track assignments per person
        assignments = {name: 0 for name, _ in names_and_phones}
        
        # Generate schedule
        schedule = []
        for week in weeks:
            # Find person with fewest assignments
            person_name = min(assignments.items(), key=lambda x: x[1])[0]
            phone_number = next(phone for name, phone in names_and_phones if name == person_name)
            
            # Create rotation
            rotation = self.add_rotation(
                team_id=team_id,
                person_name=person_name,
                phone_number=phone_number,
                week_number=week["week_number"],
                year=year
            )
            
            schedule.append(rotation)
            assignments[person_name] += 1
        
        return schedule
