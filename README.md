# Barangay Equipment System

This guide is the complete handoff procedure for installing the application on
a Windows server on the client's local office network (LAN).

The application is a Laravel system for equipment inventory, borrowing,
reservations, returns, maintenance, user access, and audit logging.

Administrators can transfer Admin ownership from the Manage Users page. The
current Admin selects a Semi-Admin, confirms the warning, and becomes a
Semi-Admin. The selected account becomes the Admin in one atomic operation.

## Deployment architecture

Use one always-on Windows PC or server as the application server:

```text
Client browsers
       |
       | office LAN / HTTP
       v
Windows server
  - IIS, Apache, or Nginx
  - PHP 8.3+
  - MySQL 8+
  - Laravel application
```

Only the Laravel `public` directory must be exposed by the web server. Never
point IIS, Apache, or Nginx at the project root.

## 1. Before visiting the client

Prepare these items:

- The complete project folder.
- The Git repository URL or deployment ZIP.
- A reserved LAN IP address from the client's network administrator, for
  example `192.168.1.50`.
- The desired application name and barangay name.
- The client's initial Admin username, email, and strong password.
- A database password for the dedicated application database user.
- A backup destination, such as an external drive or protected network folder.

Do not place passwords in Git, email or screenshots.

## 2. Install the server prerequisites

On the client's dedicated Windows server, install:

1. PHP 8.3 or newer with these extensions enabled:
   `curl`, `fileinfo`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`,
   `xml`, `zip`, and `gd`.
2. Composer 2.
3. Node.js 20 or newer with npm.
4. MySQL 8.0 or newer.
5. One web server:
   - IIS with PHP FastCGI and URL Rewrite, or
   - Apache with `mod_rewrite`, or
   - Nginx with PHP-FPM.

Verify the command-line tools in PowerShell:

```powershell
php -v
composer -V
node -v
npm -v
```

If a command is not found, finish installing or configure its Windows PATH
entry before continuing.

## 3. Configure the server network

1. Give the server a reserved IP address through the client's router or DHCP
   server. Do not rely on an address that changes after reboot.
2. Record the final address, for example `192.168.1.50`.
3. Create a Windows Firewall inbound rule for the web server port:
   - TCP 80 for normal HTTP, or
   - the agreed custom port if port 80 is unavailable.
4. Do not expose MySQL port 3306 to the office network unless there is a
   specific administrative requirement.
5. From a client workstation, confirm the server is reachable:

```powershell
Test-NetConnection 192.168.1.50 -Port 80
```

## 4. Create the MySQL database

Create a database and a dedicated user. Do not use the MySQL root account in
the application.

Run the following in MySQL Workbench or the MySQL command line, replacing both
password placeholders with strong unique values:

```sql
CREATE DATABASE barangay_equipment
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

CREATE USER 'barangay_app'@'127.0.0.1'
    IDENTIFIED BY 'REPLACE_WITH_DATABASE_PASSWORD';

GRANT ALL PRIVILEGES ON barangay_equipment.*
    TO 'barangay_app'@'127.0.0.1';

FLUSH PRIVILEGES;
```

If MySQL is configured to authenticate the application as `localhost` instead
of `127.0.0.1`, create the matching user host as well:

```sql
CREATE USER 'barangay_app'@'localhost'
    IDENTIFIED BY 'REPLACE_WITH_DATABASE_PASSWORD';

GRANT ALL PRIVILEGES ON barangay_equipment.*
    TO 'barangay_app'@'localhost';

FLUSH PRIVILEGES;
```

## 5. Copy the application

Create a deployment directory, for example:

```text
C:\Sites\barangay-equipment
```

Copy the project there. Open PowerShell as an administrator when installing
under a protected directory or configuring IIS.

From the project root:

```powershell
cd C:\Sites\barangay-equipment
```

Make sure these files are present:

```powershell
Test-Path .\artisan
Test-Path .\composer.json
Test-Path .\package.json
```

## 6. Create and configure `.env`

Create the environment file:

```powershell
Copy-Item .env.example .env
php artisan key:generate
```

Open `.env` and set values like these. Replace every example value with the
client's real values:

```dotenv
APP_NAME="Barangay Equipment System"
APP_ENV=production
APP_DEBUG=false
APP_URL=http://192.168.1.50
APP_TIMEZONE=Asia/Manila

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=barangay_equipment
DB_USERNAME=barangay_app
DB_PASSWORD=REPLACE_WITH_DATABASE_PASSWORD

INITIAL_ADMIN_USERNAME=admin
INITIAL_ADMIN_EMAIL=admin@barangay.local
INITIAL_ADMIN_PASSWORD=REPLACE_WITH_INITIAL_ADMIN_PASSWORD

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local
MAIL_MAILER=log
```

Important:

- Keep `APP_DEBUG=false`.
- Keep the generated `APP_KEY`; do not regenerate it during a routine update.
- `APP_URL` must exactly match the address users will open. Include a port if
  the site is not using port 80.
- `INITIAL_ADMIN_PASSWORD` is required when seeding in production.
- Never commit `.env` or send it to the client through an insecure channel.

Check the effective configuration:

```powershell
php artisan about
```

Confirm that the environment is `production`, debug mode is disabled, the URL
is correct, and the database driver is `mysql`.

## 7. Prepare the database and application

### New preparation database: keep only the Admin account

Use this only when the database is new or the client explicitly approved
deleting all existing application data:

```powershell
Set-ExecutionPolicy -Scope Process Bypass
.\deploy.ps1 -ResetData
```

This permanently removes all existing users, equipment, transactions,
reservations, audit records, system settings, and application database data.
It recreates only the required roles, inventory categories, and the configured
Admin account.

### Existing client database: preserve its data

For a normal first installation or application update, use:

```powershell
Set-ExecutionPolicy -Scope Process Bypass
.\deploy.ps1
```

This does not use `migrate:fresh`; it runs pending migrations and seeds missing
baseline records without intentionally deleting the existing application data.

The script performs these steps:

1. Installs production PHP dependencies.
2. Installs JavaScript dependencies.
3. Builds production Vite assets.
4. Runs migrations and seeders.
5. Creates the public storage link.
6. Caches Laravel configuration, routes, and views.

If the script stops with an error, fix the reported issue and run it again.
Do not continue to web-server configuration until it completes successfully.

## 8. Configure the web server

### IIS

1. Install IIS, the CGI role, PHP FastCGI, and the IIS URL Rewrite module.
2. Create a site whose physical path is:

```text
C:\Sites\barangay-equipment\public
```

3. Bind the site to the server's LAN IP and port 80.
4. Ensure the IIS application pool identity can read the project and write to:

```text
C:\Sites\barangay-equipment\storage
C:\Sites\barangay-equipment\bootstrap\cache
```

5. Configure PHP as the FastCGI handler.
6. Enable URL Rewrite so Laravel routes are forwarded to `index.php`.
7. Browse to the server from a client workstation.

If IIS does not rewrite routes correctly, install URL Rewrite before changing
Laravel routes or controllers.

### Apache

1. Create a virtual host with the document root:

```text
C:/Sites/barangay-equipment/public
```

2. Enable `mod_rewrite`.
3. Allow overrides for the `public` directory so Laravel's `.htaccess` works.
4. Configure PHP through the installed Apache PHP handler.
5. Restart Apache and test from a client workstation.

### Nginx

Configure the site root as:

```text
C:/Sites/barangay-equipment/public
```

Forward PHP requests to the configured PHP-FPM socket or Windows PHP FastCGI
endpoint, and route unknown requests to `/index.php`.

## 9. Verify the installation

From the application server:

```powershell
php artisan route:list
php artisan migrate:status
```

From a different computer on the LAN, test all of these:

- Login and logout.
- Admin password change.
- Create a resident or staff user.
- Add equipment.
- View inventory.
- Submit and approve a borrowing request.
- Create and cancel a reservation.
- Return equipment.
- Upload a profile photo or system background.
- Download an audit export.
- Create a system backup.
- Confirm unauthorized users cannot open Admin pages.

Also confirm that the browser address uses the configured LAN hostname or IP
and that no Laravel exception details are displayed.

## 10. First client handoff

After verifying the system:

1. Sign in using the initial Admin account.
2. Change the Admin password.
3. Create named user accounts for the client's authorized staff.
4. Do not share one Admin password among all staff.
5. Record the application URL and support contact.
6. Give the client a short operating demonstration.
7. Confirm where backups are stored and who is responsible for them.
8. Remove or securely store any temporary deployment files and credentials.

## 11. Backups and recovery

Back up both:

1. The MySQL database.
2. `storage/app/public`, which contains uploaded images and branding assets.

The Admin System page can create an application backup. Store backups on a
different disk or protected network location, not only on the application
server.

For a database backup from a server with `mysqldump` available:

```powershell
mysqldump -h 127.0.0.1 -P 3306 -u barangay_app -p `
  barangay_equipment > C:\Backups\barangay_equipment.sql
```

Test a backup by restoring it to a separate database. A backup that has never
been restored is not a verified recovery plan.

## 12. Updating the application later

Before every update:

1. Notify users and choose a maintenance window.
2. Take a database and uploaded-files backup.
3. Keep a copy of the currently working application folder.

Then from the new project folder:

```powershell
php artisan down
.\deploy.ps1
php artisan up
```

Never use `.\deploy.ps1 -ResetData` for a routine update. Never run
`php artisan migrate:fresh` against a database whose data must be retained.

## 13. Troubleshooting

### The site shows a 500 error

Check the latest application log:

```powershell
Get-Content .\storage\logs\laravel.log -Tail 100
```

Confirm `APP_DEBUG=false`, file permissions, PHP extensions, and the database
credentials.

### The site cannot connect to MySQL

Check that MySQL is running and confirm:

```powershell
php artisan about
php artisan migrate:status
```

Verify `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD`.

### CSS or JavaScript is missing

Run:

```powershell
npm ci
npm run build
php artisan optimize:clear
php artisan optimize
```

Confirm that the web server can read `public/build`.

### Uploaded images are missing

Run:

```powershell
php artisan storage:link
```

Confirm that `public/storage` points to `storage/app/public` and that the web
server account has read access.

### The page works on the server but not from another computer

Check the server IP, Windows Firewall, web-server binding, client network,
and `APP_URL`. Test the port from the client:

```powershell
Test-NetConnection 192.168.1.50 -Port 80
```

### Routes return 404 under IIS or Apache

The web server is probably not rewriting requests to `public/index.php`.
Verify IIS URL Rewrite or Apache `mod_rewrite` configuration.

## 14. Useful maintenance commands

```powershell
php artisan about
php artisan route:list
php artisan migrate:status
php artisan optimize:clear
php artisan optimize
php artisan storage:link
php artisan test
composer audit
```

Do not run destructive commands such as `migrate:fresh` unless the client has
approved a complete data reset and a verified backup exists.
