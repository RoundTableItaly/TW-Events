# How to Install on a Server Without SSH Access

Deploying a Laravel project without direct command-line (SSH) access is a common scenario for shared hosting environments that use control panels like cPanel or Plesk. The core strategy is to perform all command-line operations locally and then upload the complete, production-ready application to the server.

## Sentry Error Tracking Configuration

This application includes Sentry integration for error tracking and performance monitoring on both backend (PHP/Laravel) and frontend (JavaScript) levels.

### Environment Variables

**⚠️ SECURITY WARNING**: Never commit your actual Sentry DSN to a public repository. Always use environment variables.

1. **Get your Sentry DSN**:
   - Log into your Sentry dashboard
   - Go to Settings → Projects → [Your Project] → Client Keys (DSN)
   - Copy the DSN URL

2. **Add the following environment variables to your `.env` file**:

```bash
# Sentry Configuration
SENTRY_LARAVEL_DSN=https://YOUR_SENTRY_DSN_HERE
SENTRY_DSN=https://YOUR_SENTRY_DSN_HERE
SENTRY_TRACES_SAMPLE_RATE=1.0
SENTRY_PROFILES_SAMPLE_RATE=1.0
```

**Note**: Both `SENTRY_LARAVEL_DSN` and `SENTRY_DSN` are needed for backend and frontend respectively.

3. **Create a `.env.example` file** (if it doesn't exist) with placeholder values:
   ```bash
   # Sentry Configuration
   SENTRY_LARAVEL_DSN=https://YOUR_SENTRY_DSN_HERE
   SENTRY_DSN=https://YOUR_SENTRY_DSN_HERE
   SENTRY_TRACES_SAMPLE_RATE=1.0
   SENTRY_PROFILES_SAMPLE_RATE=1.0
   ```

### Backend Configuration

The Sentry Laravel package is already installed and configured:

1. **Configuration file**: Located at `config/sentry.php`
2. **Exception handler**: Integrated in `bootstrap/app.php` via `Integration::handles($exceptions)`

This setup will automatically capture:

- All PHP exceptions and errors
- Database queries and performance metrics
- HTTP requests and responses
- Queue jobs and commands
- Cache operations
- Unhandled exceptions with full stack traces

### Frontend Configuration

The Sentry browser SDK is automatically loaded and configured in the main template. It will capture:

- JavaScript errors and exceptions
- User interactions (button clicks, form changes)
- API calls and network requests
- Performance metrics and user experience data

### Testing Sentry Integration

To test that Sentry is working correctly:

1. **Backend Test - Using Artisan Command**:
   ```bash
   docker-compose -f docker-compose-dev.yml exec app php artisan sentry:test
   ```
   This command will send a test exception to Sentry to verify the integration.

2. **Backend Test - Using Test Route** (alternative):
   Create a test route in `routes/web.php`:
   ```php
   Route::get('/debug-sentry', function () {
       throw new Exception('My first Sentry error!');
   });
   ```
   Visit `/debug-sentry` in your browser to trigger the exception.

3. **Frontend Test**: 
   Open browser console and run:
   ```javascript
   Sentry.captureException(new Error('Test error'))
   ```

4. **Check your Sentry dashboard** for the captured events at https://sentry.io

**Important**: Make sure your `.env` file contains the correct DSN and is not committed to version control.

### Production Considerations

- Set `SENTRY_TRACES_SAMPLE_RATE` to a lower value (e.g., 0.1) in production to reduce data volume
- Monitor your Sentry quota and adjust sampling rates accordingly
- Consider setting up alerts for critical errors

---

## The Workflow: Prepare Locally, Upload, and Configure

There are two ways to prepare your project locally. Choose the one that suits you best.

### Option 1: Prepare Locally Using PHP on Your Machine

This is the traditional approach if you have PHP and Composer installed on your computer.

#### A. Install Composer Dependencies for Production

Run composer with special flags to download all necessary libraries and optimize the autoloader for better performance. This command creates the crucial `/vendor` directory.

```bash
# This downloads dependencies, excluding developer tools (like testing libraries),
# and creates an optimized class map.
composer install --optimize-autoloader --no-dev
```

#### B. Generate Your Application Key

Every Laravel application needs a unique, secure key. If you haven't already, generate one:

```bash
php artisan key:generate
```
Open your local `.env` file and copy the generated key (the long string starting with `base64:...`). You will need to paste this into the server's `.env` file later.

#### C. Get the Database Schema (SQL File)

This is now a two-step process to ensure a perfectly clean and compatible SQL file.

**1. Run the Migrations**

First, execute the `migrate` command inside the `app` container. This will connect to the `db` container and create all the tables. The `--force` flag is used to run the command without interactive prompts, which is necessary in a script.

```bash
docker-compose exec app php artisan migrate --force
```

**2. Dump the Database Schema**

Next, use the `mysqldump` utility directly from the `db` container. This command connects to the database and exports the table structures *without any data* or log messages, saving it to `schema.sql`.

```bash
docker-compose exec db mysqldump -u root -ppassword --no-data laravel > schema.sql
```

#### D. Create a ZIP Archive

Create a ZIP file of your entire project directory.

*   **INCLUDE** the `/vendor` directory created in step A.
*   **DO NOT** include your local `.env` file (for security).
*   **DO NOT** include the `/node_modules` directory if it exists.

---

### Option 2: Prepare Locally Using Docker

This method is ideal if you don't want to install PHP or Composer on your machine. It requires [Docker Desktop](https://www.docker.com/products/docker-desktop/) to be installed and running.

First, ensure `Dockerfile` and `docker-compose.yml` are in your project root. Then, Artisan commands require an `.env` file to exist. Create one by copying the example file. In your terminal:

```bash
# If using PowerShell or Git Bash on Windows:
cp .env.example .env

# If using the old Windows Command Prompt (cmd.exe):
copy .env.example .env
```

Next, build the Docker image and start the containers (app and database) in the background.

```bash
docker-compose up -d --build
```
Your project directory is now accessible inside the `app` container at `/app`, and it is pre-configured to connect to the `db` container. **Note:** The first time you run this, it may take a minute for the database server to initialize.

#### A. Install Composer Dependencies for Production

Run Composer to download all necessary libraries. This creates the crucial `/vendor` directory.

```bash
docker-compose exec app composer install --optimize-autoloader --no-dev
```

#### B. Generate Your Application Key

Generate the application key using Artisan:

```bash
docker-compose exec app php artisan key:generate
```
This updates the `APP_KEY` in your local `.env` file. Open the `.env` file and **copy the generated key** (the long string starting with `base64:...`). You will need this for the server.

#### C. Get the Database Schema (SQL File)

This is now a two-step process to ensure a perfectly clean and compatible SQL file.

**1. Run the Migrations**

First, execute the `migrate` command inside the `app` container. This will connect to the `db` container and create all the tables. The `--force` flag is used to run the command without interactive prompts, which is necessary in a script.

```bash
docker-compose exec app php artisan migrate --force
```

**2. Dump the Database Schema**

Next, use the `mysqldump` utility directly from the `db` container. This command connects to the database and exports the table structures *without any data* or log messages, saving it to `schema.sql`.

```bash
docker-compose exec db mysqldump -u root -ppassword --no-data laravel > schema.sql
```

#### D. Stop the Docker Containers

Once the preparation is complete, you can shut down the containers.

```bash
docker-compose down
```

#### E. Create a ZIP Archive

Create a ZIP file of your entire project directory.

*   **INCLUDE** the `/vendor` directory created in step A.
*   **DO NOT** include your local `.env` file (for security).
*   **DO NOT** include the `/node_modules` directory if it exists.
*   **DO NOT** include `Dockerfile`, `docker-compose.yml`, or the `.git` directory.

---

### Step 2: Configure the Server (via Control Panel)

Log in to your web hosting control panel (cPanel, Plesk, etc.).

#### A. Upload and Extract

Use the **File Manager** to upload the ZIP file you created into the root directory for your domain (e.g., `public_html` or `httpdocs`). Then, use the "Extract" feature in the File Manager to unzip the application files.

#### B. Point the Domain to the `/public` Directory (CRITICAL)

This is the most important step for security. A Laravel application's web root **must** be the `/public` folder, not the project root.

*   In your control panel, find the settings for your domain or subdomain.
*   Look for the "Document Root" or "Web Root" setting.
*   Change it from its default (e.g., `public_html`) to the `public` subfolder (e.g., `public_html/public`). This ensures that sensitive files like `.env` and the `/vendor` directory are not directly accessible from the web.

#### C. Create the Database

*   Use the "MySQL Databases" or "Database Wizard" tool in your control panel.
*   Create a new database.
*   Create a new database user and give it a strong password.
*   Assign the user to the database with all privileges.
*   **Take note of the database name, username, and password.** They are often prefixed by your control panel username (e.g., `cpaneluser_mydb`).

#### D. Import the Database Schema

*   Open **phpMyAdmin** from your control panel.
*   Select the new database you just created from the list on the left.
*   Go to the **SQL** or **Import** tab.
*   Upload the `schema.sql` file you generated.
*   **IMPORTANT**: Before you click "Import", look for an option like "Enable foreign key checks" (`Abilita i controlli sulle chiavi esterne`) and **uncheck it**. This prevents errors caused by the import tool trying to create tables in the wrong order. The dump file will re-enable checks by itself at the end.
*   Run the import. This will create the necessary tables.

#### E. Create the `.env` file on the Server

*   In the File Manager, navigate to the project root (e.g., `public_html`).
*   Create a **new file** and name it exactly `.env`.
*   Edit this new file and paste the content of your local `README.md` file into it.
*   Now, update the following lines with the values you noted down:
    *   `APP_KEY=` (Paste the key from Step 1B).
    *   `DB_DATABASE=` (Your database name from Step 2C).
    *   `DB_USERNAME=` (Your database username from Step 2C).
    *   `DB_PASSWORD=` (Your database password from Step 2C).
    *   **IMPORTANT**: Set `APP_ENV=production` and `APP_DEBUG=false`.

---

### Step 3: Handling the Scheduled Task (Cron Job)

Since you can't run `php artisan schedule:run` from a terminal, you can trigger the importer using a web request that is automated by a cron job.

#### A. Create a Secure Web Route for the Command

In your `routes/web.php` file, add a route that will execute the Artisan command. Use a very long, random, secret URL to prevent others from discovering and running it.

```php
// In routes/web.php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

// ... other routes

// Add this route.
// Choose a long, unguessable, secret string for the URL.
Route::get('/run-importer-a9b8c7d6e5f4g3h2j1k0', function () {
    // A simple check to make sure the command is not run in a local environment by mistake.
    if (app()->environment('production')) {
        Artisan::call('import:activities');
        return "Import command executed.";
    }
    return "Not in production.";
});
```
*After adding this, you will need to re-upload your modified `routes/web.php` file.*

#### B. Set up the Cron Job in Your Control Panel

*   In your hosting control panel, find the **Cron Jobs** section.
*   Create a new cron job. You can set it to run once per hour or as often as you need.
*   In the **Command** field, use `curl` or `wget` to make a web request to the secret URL. This is the standard way to trigger scripts via cron on shared hosts.

    ```bash
    # Using curl (recommended)
    /usr/bin/curl --silent "https://yourdomain.com/run-importer-a9b8c7d6e5f4g3h2j1k0" > /dev/null 2>&1

    # Or using wget
    /usr/bin/wget -q -O - "https://yourdomain.com/run-importer-a9b8c7d6e5f4g3h2j1k0" > /dev/null 2>&1
    ```

This command tells the server to visit your secret URL periodically, which in turn runs the import command. The `> /dev/null 2>&1` part prevents your host from emailing you the output of the command every time it runs.

Your application is now deployed and will automatically update its data without you ever needing to SSH into the server.

---

## Manual Cache Management (Without Terminal Access)

When you don't have SSH access, you can manually clear Laravel's cache by deleting specific files and directories through your hosting control panel's File Manager.

### A. Clear Application Cache

1. **Navigate to the Storage Directory**
   - In your File Manager, go to `storage/framework/cache/data/`
   - **Delete all files** in this directory (but keep the directory itself)
   - These are the cached application data files

2. **Clear Configuration Cache**
   - Go to `bootstrap/cache/`
   - **Delete the file** `config.php` if it exists
   - This file contains cached configuration settings

3. **Clear Route Cache**
   - In the same `bootstrap/cache/` directory
   - **Delete the file** `routes-v7.php` (or similar route cache files) if they exist
   - This file contains cached route definitions

4. **Clear View Cache**
   - Go to `storage/framework/views/`
   - **Delete all files** in this directory (but keep the directory itself)
   - These are the compiled Blade template files

### B. Clear Session Data (Optional)

If you need to clear all user sessions:

1. Go to `storage/framework/sessions/`
2. **Delete all files** in this directory (but keep the directory itself)
3. This will log out all users and clear session data

### C. Clear Log Files (Optional)

To clean up log files that might be taking up space:

1. Go to `storage/logs/`
2. **Delete old log files** (like `laravel-2024-01-15.log`)
3. **Keep the current log file** (`laravel.log`) if you want to preserve recent logs

### D. Important Notes

- **Always keep the directories themselves** - only delete the files inside them
- **Make a backup** before clearing cache if you're unsure
- **Cache will be automatically regenerated** the next time someone visits your website
- **After clearing cache**, your website might be slightly slower for the first few requests as Laravel rebuilds the cache

### E. When to Clear Cache

You should manually clear cache when:
- You've updated your `.env` file
- You've made changes to configuration files
- You've updated route definitions
- You've modified Blade templates
- You're experiencing unexpected behavior that might be cache-related 