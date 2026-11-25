# Amazing Grace Beauty Salon

A PHP 8 / MySQL salon website and management dashboard. It connects to the existing MySQL database named `salon management` by default; it does not create a database.

## Run locally with XAMPP

1. Start Apache and MySQL in the XAMPP Control Panel.
2. The application defaults to `127.0.0.1`, database `salon management`, user `root`, and an empty password. Override these with `SALON_DB_HOST`, `SALON_DB_PORT`, `SALON_DB_NAME`, `SALON_DB_USER`, and `SALON_DB_PASS` environment variables when needed.
3. Visit `/salon%20management/setup.php` once, enter the first administrator's name, email and a password of at least 10 characters, then initialize the tables and sample salon listings in the existing database.
4. Sign in at `/salon%20management/index.php?page=admin-login`. Customers can register from the website.

The database setup is repeatable and uses `CREATE TABLE IF NOT EXISTS` plus idempotent sample inserts. `schema.sql` can also be run manually against the existing database. Neither setup method contains `CREATE DATABASE`.

## Features

- Customer registration, sign-in, profile and password change; one-time password reset links for customer and administrator accounts.
- Searchable, category-filtered hairstyle and product catalogues, services, reviews, and a contact form.
- Appointment requests with Tuesday–Saturday time slots, live availability refresh, server-side validation, and a unique active-slot constraint to prevent concurrent double bookings.
- Session shopping cart, transactional checkout, stock decrement, order history, and stock restoration on administrator order cancellation.
- Admin dashboard, hairstyle/service/product/category management and image upload, customer and contact-message views, appointment/order status management, review approval, salon settings and sales/appointment reports.
- Responsive black, warm white, beige and gold design. Sample hairstyle photos are Pexels images depicting natural cornrows, Bantu knots, an afro and a short relaxed style; salon-uploaded images replace them.

## Operational notes

- PHP requires PDO MySQL and Fileinfo. The `uploads` directory must be writable by Apache. Uploads are limited to JPG, PNG and WebP files up to 5 MB.
- Password reset email uses PHP's configured `mail()` transport. Configure mail delivery in the PHP/XAMPP environment before relying on reset emails.
- Change the default database credentials through environment variables in production, serve the site over HTTPS, and use an administrator password that is unique to this salon.
- The database setup page only creates the first administrator; subsequent administrators should be provisioned by a trusted operator.
- Sample hairstyle images: [cornrows (Pexels 11728787)](https://www.pexels.com/photo/a-woman-with-a-cornrow-wearing-a-black-shirt-11728787/), [Bantu knots (Pexels 31952853)](https://www.pexels.com/photo/portrait-of-a-woman-with-natural-hairstyle-outdoors-31952853/), and [natural afro (Pexels 18039499)](https://www.pexels.com/photo/portrait-of-smiling-young-woman-with-afro-hair-18039499/).
- Relaxed-hair reference image: [short-hair portrait (Pexels 9486659)](https://www.pexels.com/photo/a-cheerful-woman-in-a-black-tank-top-and-shorts-9486659/).

## Deploy to Render

The repository includes a Dockerfile and `render.yaml` Blueprint for a PHP/Apache web service and a private, self-managed MySQL 8 service. Render does not provide managed MySQL, and a Render service cannot reach your XAMPP computer's `127.0.0.1`. The Blueprint provisions a paid 0.5 CPU / 512 MB instance for each service, a 1 GB uploads disk, and a 10 GB MySQL disk. Review Render's current prices before confirming; the database service also needs ongoing backup and maintenance.

1. Sign up for Render, connect the GitHub account that owns this repository, and create a Blueprint from `Bertha-3/amazing-grace-beauty-salon`. Confirm the two paid services and disks.
2. Set the prompted `SALON_SETUP_TOKEN` and `SALON_MIGRATION_TOKEN` to different, long random values. Do not put either token or a database export in GitHub.
3. If you want to migrate the XAMPP data, export it locally to a file outside the repository. For XAMPP on Windows, a command like this writes the SQL dump to a private temporary directory:

   ```powershell
   $dumpPath = Join-Path $env:TEMP 'salon-management.sql'
   & 'C:\xampp\mysql\bin\mysqldump.exe' --host=127.0.0.1 --user=root --skip-password --single-transaction --skip-lock-tables --add-drop-table --default-character-set=utf8mb4 "--result-file=$dumpPath" 'salon management'
   ```

   After both Render services are live, visit `https://<your-render-host>/migrate.php`, enter `SALON_MIGRATION_TOKEN`, and upload the SQL dump. It replaces matching tables in the hosted database. On success, remove `SALON_MIGRATION_TOKEN` in Render immediately; the endpoint also records a one-time completion marker.
4. Open `/setup.php`, enter `SALON_SETUP_TOKEN`, and create the first administrator. Remove `SALON_SETUP_TOKEN` from the Render environment after setup. The setup page refuses to create another administrator once one exists.
5. Uploaded images persist on the disk mounted at `/var/www/html/uploads`; bundled sample photos live in `assets/images` so the disk mount does not hide them. Render sets `RENDER_EXTERNAL_URL`, which the app uses for password-reset links and HTTPS session cookies.

The SQL export contains customer and business data: keep it private, upload it only to your own Render service, and delete the temporary local copy when you have verified the migration. Never commit it to this public repository. Render's MySQL guide recommends regular `mysqldump` backups; do not rely on disk snapshots alone for database recovery. Password-reset emails also require a working mail transport; configure one before relying on resets in production.
