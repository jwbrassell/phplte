def convert_dictionary_to_list(data):
    """
    Convert dictionary data to list format for datatable.
    
    Args:
        data (dict): Dictionary containing title, headers, and data
        
    Returns:
        dict: Converted data with list format
    """
    result = {
        "title": data["title"],
        "last_updated": data["last_updated"],
        "headers": data["headers"],
        "data": []
    }
    
    # Map of header display names to dictionary keys
    header_to_key = {
        "Common Name": None,  # First column is the key itself
        "Scientific Name": "scientific_name",
        "Species Type": "species_type",
        "Period": "period",
        "Length (meters)": "length",
        "Diet": "diet"
    }
    
    # Convert each dictionary entry to a list
    for key, details in data["data"].items():
        # Create a row list starting with the key (first column)
        row = [key]
        
        # Add values for each header (skipping first header since it's the key)
        for header in data["headers"][1:]:
            # Get the corresponding dictionary key
            dict_key = header_to_key.get(header)
            if dict_key:
                # Get the value from details, default to empty string if not found
                value = details.get(dict_key, "")
                row.append(value)
            else:
                row.append("")
            
        result["data"].append(row)
    
    return result
