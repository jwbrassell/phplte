# Python Logger Configuration

After setting up the Python virtual environment at `/opt/python-venv`, ensure the PythonLogger.php is using the correct Python interpreter path.

The file at `portal/includes/PythonLogger.php` should use:
```php
$command = sprintf('/opt/python-venv/bin/python %s %s %s %s 2>&1',
```

This ensures that the logging system uses the Python installation from the virtual environment that has all the required dependencies installed.

## Verifying Logger Setup

You can verify the logger is working by:

1. Checking permissions:
```bash
ls -l /opt/python-venv/bin/python
ls -l /var/www/html/shared/scripts/modules/logging/logger.py
```

2. Testing logger execution:
```bash
sudo -u apache /opt/python-venv/bin/python /var/www/html/shared/scripts/modules/logging/logger.py test "Test message" "{}"
```

3. Checking log files:
```bash
ls -l /var/www/html/shared/data/logs/system/test/
```

If you see JSON log files being created, the logging system is working correctly.
