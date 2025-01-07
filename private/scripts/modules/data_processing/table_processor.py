#!/usr/bin/env python3
"""
TableDataProcessor
Handles data processing for DataTables components
"""

import json
import sys
from typing import Dict, List, Any, Union

class TableDataProcessor:
    """
    Process data for table display and manipulation
    """
    
    def process_json_dictionary(self, data: Dict[str, Any]) -> Dict[str, Any]:
        """
        Convert dictionary data to table format
        
        Args:
            data: Dictionary containing data to be processed
            
        Returns:
            Dictionary formatted for DataTables
        """
        try:
            # Extract headers from first item if available
            if not data or not isinstance(data, dict):
                return {"data": [], "columns": []}
                
            # Convert dictionary to list format
            table_data = []
            for key, value in data.items():
                if isinstance(value, dict):
                    row = {"id": key}
                    row.update(value)
                    table_data.append(row)
                else:
                    table_data.append({"id": key, "value": value})
            
            # Generate columns from all possible keys
            columns = set()
            for row in table_data:
                columns.update(row.keys())
            
            return {
                "data": table_data,
                "columns": [{"data": col, "title": col.replace("_", " ").title()} 
                          for col in sorted(columns)]
            }
            
        except Exception as e:
            return {
                "error": f"Failed to process dictionary data: {str(e)}",
                "data": [],
                "columns": []
            }
    
    def process_json_list(self, data: List[Any]) -> Dict[str, Any]:
        """
        Convert list data to table format
        
        Args:
            data: List containing data to be processed
            
        Returns:
            Dictionary formatted for DataTables
        """
        try:
            if not data or not isinstance(data, list):
                return {"data": [], "columns": []}
            
            # Handle list of dictionaries
            if isinstance(data[0], dict):
                # Get all possible columns from all rows
                columns = set()
                for row in data:
                    columns.update(row.keys())
                
                return {
                    "data": data,
                    "columns": [{"data": col, "title": col.replace("_", " ").title()} 
                              for col in sorted(columns)]
                }
            
            # Handle list of lists
            else:
                # Convert to list of dicts with numeric keys
                table_data = []
                for row in data:
                    if isinstance(row, list):
                        row_dict = {str(i): val for i, val in enumerate(row)}
                        table_data.append(row_dict)
                
                columns = [str(i) for i in range(max(len(row) for row in data))]
                
                return {
                    "data": table_data,
                    "columns": [{"data": col, "title": f"Column {int(col) + 1}"} 
                              for col in columns]
                }
                
        except Exception as e:
            return {
                "error": f"Failed to process list data: {str(e)}",
                "data": [],
                "columns": []
            }
    
    def apply_filters(self, data: List[Dict[str, Any]], 
                     filters: Dict[str, Any]) -> List[Dict[str, Any]]:
        """
        Apply data filters
        
        Args:
            data: List of dictionaries containing the data
            filters: Dictionary of column:value pairs to filter by
            
        Returns:
            Filtered list of dictionaries
        """
        try:
            if not filters:
                return data
                
            filtered_data = data.copy()
            
            for column, value in filters.items():
                if value is not None:
                    filtered_data = [
                        row for row in filtered_data
                        if column in row and str(value).lower() in str(row[column]).lower()
                    ]
            
            return filtered_data
            
        except Exception as e:
            print(f"Error applying filters: {str(e)}", file=sys.stderr)
            return data
    
    def sort_data(self, data: List[Dict[str, Any]], 
                 sort_key: str, direction: str = 'asc') -> List[Dict[str, Any]]:
        """
        Sort data by specified key
        
        Args:
            data: List of dictionaries containing the data
            sort_key: Key to sort by
            direction: Sort direction ('asc' or 'desc')
            
        Returns:
            Sorted list of dictionaries
        """
        try:
            if not sort_key or sort_key not in data[0]:
                return data
                
            reverse = direction.lower() == 'desc'
            
            # Handle numeric values
            try:
                return sorted(
                    data,
                    key=lambda x: float(str(x[sort_key]).replace(',', '')) 
                    if x[sort_key] is not None else float('-inf'),
                    reverse=reverse
                )
            except (ValueError, TypeError):
                # Fall back to string sorting if numeric conversion fails
                return sorted(
                    data,
                    key=lambda x: str(x[sort_key]).lower() 
                    if x[sort_key] is not None else '',
                    reverse=reverse
                )
                
        except Exception as e:
            print(f"Error sorting data: {str(e)}", file=sys.stderr)
            return data

def main():
    """
    Handle command line processing
    """
    if len(sys.argv) < 3:
        print("Usage: table_processor.py <command> <json_data> [options]")
        sys.exit(1)
        
    command = sys.argv[1]
    try:
        data = json.loads(sys.argv[2])
    except json.JSONDecodeError as e:
        print(f"Error decoding JSON data: {str(e)}", file=sys.stderr)
        sys.exit(1)
        
    processor = TableDataProcessor()
    
    try:
        if command == 'process_dict':
            result = processor.process_json_dictionary(data)
        elif command == 'process_list':
            result = processor.process_json_list(data)
        elif command == 'filter':
            if len(sys.argv) < 4:
                print("Filter command requires filters argument", file=sys.stderr)
                sys.exit(1)
            filters = json.loads(sys.argv[3])
            result = processor.apply_filters(data, filters)
        elif command == 'sort':
            if len(sys.argv) < 4:
                print("Sort command requires sort_key argument", file=sys.stderr)
                sys.exit(1)
            sort_key = sys.argv[3]
            direction = sys.argv[4] if len(sys.argv) > 4 else 'asc'
            result = processor.sort_data(data, sort_key, direction)
        else:
            print(f"Unknown command: {command}", file=sys.stderr)
            sys.exit(1)
            
        print(json.dumps(result))
        
    except Exception as e:
        print(f"Error processing data: {str(e)}", file=sys.stderr)
        sys.exit(1)

if __name__ == '__main__':
    main()
