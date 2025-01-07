# Python Logger Configuration

After setting up the Python virtual environment at `shared/venv`, ensure the PythonLogger.php is using the correct Python interpreter path.

The file at `portal/includes/PythonLogger.php` is already configured to look for the Python interpreter at:
```php
$this->projectRoot . '/shared/venv/bin/python'
```

This ensures that the logging system uses the Python installation from the virtual environment that has all the required dependencies installed.

## Verifying Logger Setup

You can verify the logger is working by:

1. Checking permissions:
```bash
ls -l shared/venv/bin/python
ls -l shared/scripts/modules/logging/logger.py
```

2. Testing logger execution:
```bash
sudo -u apache shared/venv/bin/python shared/scripts/modules/logging/logger.py test "Test message" "{}"
```

3. Checking log files:
```bash
ls -l shared/data/logs/system/test/
```

If you see JSON log files being created, the logging system is working correctly.
