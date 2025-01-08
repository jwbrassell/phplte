# Understanding Our Setup Process

## Introduction

When you're setting up a web application, there are many pieces that need to work together, kind of like building a complex machine. Our setup process is designed to make this as smooth as possible, while also making sure everything is secure and works well. In this document, we'll walk through every part of our setup process, explaining what we're doing and why we're doing it that way.

## Why We Split the Setup Into Parts

Our setup process is divided into several smaller scripts instead of having one big script. Think of it like building a house - you don't try to do everything at once. Instead, you lay the foundation first, then build the walls, then add the roof, and so on. Each step builds on the previous ones.

Here are the main parts of our setup:
1. Initial setup (00_init.sh)
2. Security settings (01_selinux.sh)
3. Installing required software (02_packages.sh)
4. Creating directories (03_directories.sh)
5. Setting up Python (04_python.sh)
6. Setting up the web server (05_nginx.sh)
7. Setting up PHP (06_php.sh)

This makes it easier to:
- Find and fix problems (if something goes wrong, we know which part failed)
- Update specific parts without changing everything
- Understand what each part does
- Test each part separately

## The Installation Process

### Step 1: Getting the Files in Place

We start by putting all our files in the `/var/www/html` directory. This is like setting up your workspace before starting a project. We chose this location because:
- It's the standard place for web files on Linux systems
- Most web servers are already configured to look here
- System administrators expect to find web files here
- It's secure by default (normal users can't write to this directory)

Instead of copying files around, we clone our repository directly into this location. This is better because:
- We get all the files in one step
- We maintain the git history (helpful for tracking changes)
- It's faster than copying files
- There's less chance of files being missed or corrupted

### Step 2: Setting Up Security (SELinux)

SELinux is like a security guard for your system. It's very strict about what programs can do. For our initial setup, we temporarily disable it because:
- It makes setup easier (we don't have to configure all the security rules first)
- We can test if everything works without security getting in the way
- Once everything works, we can turn it back on and add the security rules

This is like building a house with the scaffolding up - it's easier to work on everything first, then remove the scaffolding when you're done.

### Step 3: Installing Required Software

Our application needs several pieces of software to run:
- Nginx (the web server that handles internet requests)
- PHP (the programming language our application uses)
- Python (for running background tasks and scripts)
- Various helper programs and libraries

We install all of these at once to:
- Make sure they're all compatible versions
- Avoid having to stop and start for each installation
- Ensure we have everything we need before continuing

### Step 4: Creating the Directory Structure

Our application uses several directories to keep different types of files separate:
- portal/ (the main application files)
- shared/ (files that different parts of the application share)
- private/ (sensitive files that shouldn't be public)

This organization is important because:
- It keeps sensitive files away from public access
- It makes it easier to find things
- It helps prevent accidents (like accidentally making private files public)
- It makes backups easier (you can backup different parts separately)

### Step 5: Setting Up Python

Our application uses Python for some background tasks. Setting up Python involves:
1. Creating a virtual environment (like a separate workspace for Python)
2. Installing the Python packages we need
3. Setting up logging so we can track any problems

We use a virtual environment because:
- It keeps our Python packages separate from the system's Python
- We can easily list and install exactly the packages we need
- If something goes wrong, we can delete and recreate it without affecting the system
- Different applications can use different versions of the same packages

### Step 6: Configuring the Web Server (Nginx)

Nginx is our web server - it handles requests from users' web browsers. Our configuration does several important things:

1. Clean URLs:
   - Users see nice, simple URLs (like dogcrayons.com/login.php)
   - We hide the actual directory structure
   - This looks more professional and is easier to remember

2. Security:
   - We use HTTPS (encrypted connections)
   - We block access to private files
   - We handle errors properly

3. Performance:
   - We cache static files
   - We handle connections efficiently
   - We log errors for troubleshooting

The most important part of our Nginx configuration is how it handles URLs. We make it serve files from the portal directory without showing "portal" in the URL because:
- It's cleaner and more professional
- It hides our directory structure (good for security)
- It's easier to change things later without breaking links
- Users don't need to know about our internal organization

### Step 7: Setting Up PHP

PHP runs our application code. Our setup:
1. Makes PHP work with Nginx
2. Sets memory limits and timeouts
3. Configures error logging
4. Sets up session handling

For initial setup, we make PHP more permissive (it shows errors and allows more operations) because:
- It helps us find and fix problems during setup
- We can see what's going wrong if something fails
- It makes testing easier

Later, we can make it more strict for better security.

## File Permissions and Ownership

File permissions are like locks that control who can do what with each file. We set these carefully:

1. Most files:
   - Can be read by anyone (644)
   - Can't be changed except by administrators
   - This is safe because they're just program files

2. Directories:
   - Can be accessed by anyone (755)
   - Can only be modified by administrators
   - This lets users see files but not change them

3. Log files:
   - Can be written by the application (775)
   - Can be read by administrators
   - This lets us track problems without security risks

4. Private files:
   - Can only be accessed by the application (750)
   - Hidden from public view
   - This keeps sensitive information safe

## Why We Use Apache and Nginx Together

You might notice we use both Apache (for PHP) and Nginx (for the web server). This might seem complicated, but there are good reasons:

1. Nginx is great at:
   - Handling lots of connections
   - Serving static files (like images)
   - Managing SSL (encryption)
   - URL rewriting (making URLs nice)

2. Apache's PHP module is:
   - Very stable
   - Well-tested
   - Good at running PHP
   - Easy to configure

By using both, we get the best of both worlds:
- Nginx handles all the web traffic efficiently
- Apache runs PHP reliably
- They each do what they're best at

## Logging and Troubleshooting

We set up extensive logging because when something goes wrong, you need to know why. Our logs are:

1. Separated by type:
   - Access logs (who's visiting the site)
   - Error logs (what's going wrong)
   - PHP logs (application problems)
   - Python logs (background task issues)

2. Easy to find:
   - All in one place (/var/www/html/portal/logs)
   - Named clearly
   - Organized by type

3. Properly permissioned:
   - Can be written by the application
   - Can be read by administrators
   - Protected from public access

This makes it much easier to:
- Find problems when they happen
- Track down the cause of issues
- Monitor the application's health
- See who's using the application

## Security Considerations

Our setup starts with security that's a bit relaxed to make initial setup easier, but it's designed to be tightened later. This is like building a house - you need the doors open while you're moving furniture in, but you lock everything up properly once you're done.

Initial setup has:
- SELinux disabled
- PHP errors visible
- More permissive file permissions

But it's ready for:
- SELinux rules to be added
- PHP security to be tightened
- File permissions to be restricted
- Additional security measures

This approach lets us:
1. Get everything working first
2. Test all the features
3. Fix any problems we find
4. Then add security without breaking things

## Maintenance and Updates

Our setup makes maintenance easier because:

1. Files are organized logically:
   - Each type of file has its place
   - Related files are kept together
   - Important files are protected

2. Configurations are centralized:
   - All in standard locations
   - Well-documented
   - Easy to find and change

3. Logs are comprehensive:
   - Show what's happening
   - Help find problems
   - Track usage

This means when you need to:
- Update the application
- Fix a problem
- Add new features
- Change settings

You can do it quickly and safely.

## Conclusion

Our setup process is designed to be:
- Reliable (it works the same way every time)
- Understandable (you can see what it's doing)
- Maintainable (you can fix or change things easily)
- Secure (once fully configured)

While it might seem complex at first, each part has a specific purpose and fits together with the others to create a complete, working system. By understanding how and why things are set up this way, you can better maintain, troubleshoot, and improve the application in the future.
