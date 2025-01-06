import json
import os
import sys
from typing import Dict, List, Any
from datetime import datetime

# Debug information
print(f"Python executable: {sys.executable}", file=sys.stderr)
print(f"Current working directory: {os.getcwd()}", file=sys.stderr)

class DatatableHandler:
    def _resolve_data_dir(self) -> str:
        """Resolve the absolute path to the data directory."""
        script_dir = os.path.dirname(os.path.abspath(__file__))
        print(f"Debug: Script directory is {script_dir}", file=sys.stderr)
        
        # Go up 4 levels: examples -> modules -> scripts -> shared
        base_dir = os.path.dirname(os.path.dirname(os.path.dirname(os.path.dirname(script_dir))))
        print(f"Debug: Base directory is {base_dir}", file=sys.stderr)
        
        data_dir = os.path.realpath(os.path.join(base_dir, "shared", "data", "examples", "datatable"))
        print(f"Debug: Data directory is {data_dir}", file=sys.stderr)
        
        return data_dir

    def __init__(self, data_dir: str = None):
        self.data_dir = data_dir if data_dir is not None else self._resolve_data_dir()

    def read_json_file(self, filename: str) -> Dict[str, Any]:
        """Read and parse a JSON file from the data directory."""
        file_path = os.path.join(self.data_dir, filename)
        print(f"Debug: Full file path is {file_path}", file=sys.stderr)
        print(f"Debug: Current working directory is {os.getcwd()}", file=sys.stderr)
        print(f"Debug: File exists: {os.path.exists(file_path)}", file=sys.stderr)
        if os.path.exists(self.data_dir):
            print(f"Debug: Directory contents: {os.listdir(self.data_dir)}", file=sys.stderr)
        else:
            print(f"Debug: Data directory does not exist", file=sys.stderr)
        try:
            with open(file_path, 'r') as f:
                return json.load(f)
        except Exception as e:
            return {
                "error": f"Failed to read file: {str(e)}",
                "title": "Error",
                "last_updated": datetime.now().strftime("%Y-%m-%d"),
                "headers": [],
                "data": []
            }

    def get_datatable_data(self, filename: str) -> Dict[str, Any]:
        """Get formatted data for datatable display."""
        data = self.read_json_file(filename)
        
        return {
            "title": data.get("title", "Untitled Report"),
            "last_updated": data.get("last_updated", datetime.now().strftime("%Y-%m-%d")),
            "headers": data.get("headers", []),
            "data": data.get("data", [])
        }

if __name__ == "__main__":
    import sys
    try:
        if len(sys.argv) != 2:
            raise ValueError("Missing filename argument")
            
        filename = sys.argv[1]
        handler = DatatableHandler()
        result = handler.get_datatable_data(filename)
        
        # Ensure we have valid data
        if not result.get('headers') or not result.get('data'):
            raise ValueError("Invalid data format")
            
        print(json.dumps(result))
        
    except Exception as e:
        error_response = {
            "error": str(e),
            "title": "Error Loading Data",
            "last_updated": datetime.now().strftime("%Y-%m-%d"),
            "headers": [],
            "data": []
        }
        print(json.dumps(error_response))
        sys.exit(1)
