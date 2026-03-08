<?php
/**
 * Client Database Configuration
 * 
 * This file contains the database connection settings for accessing
 * individual client databases.
 * 
 * IMPORTANT: Update these credentials with your actual database settings
 */

// Database connection settings
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'root');  // Change this to your database username
define('DB_PASSWORD', '');  // Change this to your database password (use '' for blank password)
define('DB_CHARSET', 'utf8mb4');

/**
 * EXAMPLES:
 * 
 * With password:
 * define('DB_PASSWORD', 'mySecretPassword123');
 * 
 * Without password (blank):
 * define('DB_PASSWORD', '');
 */

/**
 * SECURITY NOTE:
 * 
 * 1. Make sure this file is not accessible from the web
 * 2. Set proper file permissions (chmod 600)
 * 3. Never commit this file with actual credentials to version control
 * 4. Consider using environment variables for production
 */