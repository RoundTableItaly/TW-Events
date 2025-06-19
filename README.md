# TW-Events

This project aggregates event data from various Round Table external APIs, geocodes their locations, and displays them on a unified web interface with a map.

This version is a rewrite of the original Supabase/Deno project in PHP using the Laravel framework.

## Architecture

The application is composed of two main parts:

1.  **A Backend Laravel Application**:

    -   **Data Aggregation**: A scheduled Artisan command (`import:activities`) fetches data from the configured external APIs.
    -   **Geocoding**: During the import, if an activity has a location but no coordinates, the command attempts to geocode the address using the free Nominatim (OpenStreetMap) API.
    -   **Database**: The aggregated and cleaned data is stored in a MySQL database.
    -   **API**: A simple REST API endpoint (`/api/activities`) exposes the data to the frontend.

2.  **A Frontend Vanilla JS Application**:
    -   **User Interface**: The UI is built with Bootstrap and displays a list of activities and a Leaflet.js map.
    -   **Data Consumption**: The frontend fetches data from the backend's `/api/activities` endpoint.

## Requirements

-   PHP >= 8.2
-   Composer
-   MySQL
-   A web server (Nginx or Apache)

## Local Development Setup

### Option 1: Native (PHP/Composer/MySQL)

1.  **Clone the Repository**

    ```bash
    git clone https://github.com/your-repo/rt-it-activities.git
    cd rt-it-activities
    ```

2.  **Install PHP Dependencies**

    ```bash
    composer install
    ```

3.  **Configure Environment**

    -   Copy the environment example file: `cp .env.example .env`
    -   Generate an application key: `php artisan key:generate`
    -   Open the `.env` file and configure your database connection (`DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`).

4.  **Run Database Migrations**
    This will create the `activities` and `api_endpoints` tables in your database.

    ```bash
    php artisan migrate
    ```

5.  **Seed the Database with API Endpoints**
    You need to manually add the external API endpoints you want to fetch data from into the `api_endpoints` table.

    ```sql
    INSERT INTO `api_endpoints` (`url`, `token`, `description`, `area`)
    VALUES ('https://your-api.com/api', 'your-secret-token', 'API Description', 'Area Name');
    ```

6.  **Run the Data Importer Manually**
    Run the Artisan command to perform the initial data import.

    ```bash
    php artisan import:activities
    ```

7.  **Serve the Application**
    Use the built-in Laravel development server.
    ```bash
    php artisan serve
    ```
    The application will be available at `http://localhost:8000`.

### Option 2: Docker Compose

1. **Build and Start the Containers**

    ```bash
    docker-compose -f docker-compose-dev.yml up -d --build
    ```
    This will start the app and MySQL containers in the background. The app will be available at `http://localhost:8080` and MySQL at `localhost:3306`.

2. **First Setup**

    - Copy the environment file:
      ```bash
      cp .env.example .env
      ```
    - Generate the application key:
      ```bash
      docker-compose -f docker-compose-dev.yml exec app php artisan key:generate
      ```
    - Edit `.env` if needed (the default values should work with the provided docker-compose setup).

3. **Run Migrations**

    ```bash
    docker-compose -f docker-compose-dev.yml exec app php artisan migrate
    ```

4. **Import Data**

    ```bash
    docker-compose -f docker-compose-dev.yml exec app php artisan import:activities
    ```

5. **Access the Application**

    - Web: [http://localhost:8080](http://localhost:8080)
    - MySQL: `localhost:3306`, user: `root`, password: `password`, database: `laravel`

## Production Deployment

Deploying a Laravel application involves a few more steps than running it locally.

1.  **Web Server Configuration**: Configure your web server (Nginx or Apache) to point the document root to the project's `/public` directory. This is critical for security.

2.  **File Permissions**: Ensure the `storage` and `bootstrap/cache` directories are writable by the web server.

    ```bash
    chown -R www-data:www-data storage bootstrap/cache
    chmod -R 775 storage bootstrap/cache
    ```

3.  **Environment File**: Your production `.env` file should have `APP_ENV=production` and `APP_DEBUG=false`.

4.  **Optimization**: Run these commands on your server to cache configuration and routes for better performance.

    ```bash
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    ```

5.  **Scheduler (Cron Job)**: To keep the data updated automatically, you need to add a single cron entry to your server that runs Laravel's command scheduler every minute.
    ```cron
    * * * * * cd /path/to/your/project && php artisan schedule:run >> /dev/null 2>&1
    ```
    The scheduler is configured in `app/Console/Kernel.php` to run the `import:activities` command hourly by default.

## Project Structure

-   `app/Console/Commands/ImportActivities.php`: The core data aggregation and geocoding logic.
-   `app/Console/Kernel.php`: The scheduler configuration.
-   `app/Models/`: Contains the `Activity` and `ApiEndpoint` Eloquent models.
-   `app/Http/Controllers/Api/ActivityController.php`: Handles requests to the `/api/activities` endpoint.
-   `routes/api.php`: Defines the API routes.
-   `routes/web.php`: Defines the web route that serves the main page.
-   `database/migrations/`: Contains the database table schemas.
-   `resources/views/activities.blade.php`: The main view file (previously `index.html`).
-   `public/`: The web server's document root, containing CSS, JS, and other assets.
