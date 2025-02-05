#!/opt/python-venv/bin/python3
"""
Vault Utility
Manages interactions with HashiCorp Vault for secret management
"""

import hvac
import warnings
import os
import logging

# Suppress specific deprecation warning for hvac
warnings.filterwarnings(
    "ignore",
    category=DeprecationWarning,
    message="The raise_on_deleted_version parameter will change its default value to False in hvac v3.0.0."
)

class VaultUtility:
    """
    Utility class for interacting with HashiCorp Vault
    Handles authentication, secret management, and key-value operations
    """
    
    def __init__(self, vault_url=None, token=None, env_file_path='/etc/vault.env'):
        """
        Initialize VaultUtility with connection parameters
        
        Args:
            vault_url (str): URL of the Vault server
            token (str): Authentication token
            env_file_path (str): Path to environment file
        """
        # Set logging to ERROR only
        logging.basicConfig(level=logging.ERROR)
        
        # Initialize connection parameters
        self.vault_url = vault_url or os.getenv('VAULT_URL', 'http://127.0.0.1:8200')
        self.token = token or os.getenv('VAULT_TOKEN')

        # Load environment variables if token not provided
        if not self.token:
            self.load_env_file(env_file_path)
            self.token = os.getenv('VAULT_TOKEN')
            
            if not self.token:
                logging.error("Failed to obtain Vault token")
                raise Exception("VAULT_TOKEN environment variable not set")

        # Initialize Vault client
        self.client = self.authenticate_vault(self.vault_url, self.token)
        self.kv_v2_mount_point = self.get_kv_v2_mount_point()

    def load_env_file(self, filepath):
        """
        Load environment variables from file
        
        Args:
            filepath (str): Path to environment file
            
        Raises:
            Exception: If file loading fails
        """
        try:
            with open(filepath) as f:
                for line in f:
                    if line.strip() and not line.startswith('#'):
                        key, value = line.strip().split('=', 1)
                        key = key.replace('export ', '')
                        value = value.replace('\n', '')
                        os.environ[key] = value
        except Exception as e:
            raise Exception(f"Failed to load environment variables from {filepath}: {e}")

    def authenticate_vault(self, vault_url, token):
        """
        Authenticate with Vault server
        
        Args:
            vault_url (str): Vault server URL
            token (str): Authentication token
            
        Returns:
            hvac.Client: Authenticated Vault client
            
        Raises:
            Exception: If authentication fails
        """
        client = hvac.Client(url=vault_url, token=token)
        if not client.is_authenticated():
            raise Exception("Vault authentication failed")
        return client

    def list_keys_recursively(self, path=""):
        """
        List all keys and values recursively for the kv-v2 engine
        
        Args:
            path (str): Starting path for recursion
            
        Returns:
            dict: Dictionary of keys and their values
        """
        try:
            # List keys at the current path
            response = self.client.secrets.kv.v2.list_secrets(
                mount_point=self.kv_v2_mount_point,
                path=path
            )
            
            keys = response['data']['keys']
            result = {}
            
            for key in keys:
                full_path = f"{path}/{key}".strip('/')
                
                if key.endswith('/'):
                    # Recurse into directories
                    result.update(self.list_keys_recursively(full_path))
                else:
                    # Read secret value
                    try:
                        secret = self.client.secrets.kv.v2.read_secret_version(
                            mount_point=self.kv_v2_mount_point,
                            path=full_path,
                            raise_on_deleted_version=True
                        )
                        result[full_path] = secret['data']['data']
                    except hvac.exceptions.InvalidPath:
                        logging.warning(f"Invalid path: {full_path}")
                    except Exception as e:
                        logging.error(f"Error reading secret at {full_path}: {str(e)}")
            
            return result
            
        except hvac.exceptions.InvalidPath:
            logging.warning(f"Invalid path: {path}")
            return {}
        except Exception as e:
            logging.error(f"Error listing keys: {str(e)}")
            return {}

    def get_kv_v2_mount_point(self):
        """
        Get the mount point for the kv-v2 secrets engine
        
        Returns:
            str: Mount point for kv-v2 engine
            
        Raises:
            Exception: If kv-v2 engine not found
        """
        try:
            mounts = self.client.sys.list_mounted_secrets_engines()['data']
            logging.debug(f"Mounted secrets engines: {mounts}")
            
            for mount_point, mount_info in mounts.items():
                logging.debug(f"Checking mount point: {mount_point}, mount info: {mount_info}")
                
                if mount_info['type'] == 'kv' and mount_info['options'].get('version') == '2':
                    logging.info(f"Found kv-v2 mount point: {mount_point.strip('/')}")
                    return mount_point.strip('/')
                    
            raise Exception("No kv-v2 secrets engine found")
            
        except Exception as e:
            logging.error(f"Failed to get kv-v2 mount point: {e}")
            raise

    def get_value_for_key(self, key):
        """
        Get the value for a specific key in the kv-v2 engine
        
        Args:
            key (str): Key to retrieve
            
        Returns:
            str: Value associated with the key or None if not found
        """
        try:
            secret = self.client.secrets.kv.v2.read_secret_version(
                mount_point=self.kv_v2_mount_point,
                path=key,
                raise_on_deleted_version=True
            )
            value = secret['data']['data']
            return value['value']
            
        except hvac.exceptions.InvalidPath:
            logging.warning(f"Invalid path: {key}")
            return None
        except Exception as e:
            logging.error(f"Error retrieving value for key {key}: {str(e)}")
            return None
