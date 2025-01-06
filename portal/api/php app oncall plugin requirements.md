php app oncall plugin requirements

- create a oncall plugin that uses a calendar with ability to upload a file of those on call. they will be displayed on the calendar visually
- try to use as much code from oncall_flask_asbuilt
- DO NOT USE ANY DATABASE
- all database operations will be converted to ATOMIC JSON WITH FILELOCK OPERATIONS
- use python as much as possible
- any csv or json files will be stored in shared/data/oncall_calendar/
- any python file will go in shared/scripts/modules/oncall_calendar/
- use as little javascript as possible 
- user as little php as possible 
- user as much python as possible
- ensure backups of files to preven data lost
