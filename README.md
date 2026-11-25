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

The repository includes a Dockerfile and `render.yaml` Blueprint for a PHP/Apache web service. Render does not provide managed MySQL, and a Render service cannot reach your XAMPP computer's `127.0.0.1`. Before deployment:

1. Push this project to a GitHub repository and create a Render Blueprint from it. The Blueprint uses Frankfurt, a paid 0.5 CPU / 512 MB web instance, and a 1 GB persistent disk for customer/admin image uploads. Review Render's current service and disk pricing before confirming.
2. Provision a reachable MySQL 8-compatible database with your database provider. Use the existing logical database name `salon management` if supported, and export/import your existing database data if you want to retain it. The app does not create databases. Permit connections from Render's outbound addresses and use TLS where the provider supports it.
3. Enter the database host, port, database name, user and password when Render prompts for the Blueprint's `SALON_DB_*` variables. For a provider-supplied CA certificate, upload it to Render as a secret file and set `SALON_DB_SSL_CA` to its mounted path; otherwise leave it blank.
4. Set `SALON_SETUP_TOKEN` to a long, unique random value in Render. After deployment, open `/setup.php`, enter that token, and create the first administrator. Remove `SALON_SETUP_TOKEN` from the service environment after setup. The setup page rejects requests without the token on Render and refuses to create another administrator once one exists.
5. Uploaded images persist on the disk mounted at `/var/www/html/uploads`; bundled sample photos live in `assets/images` so the disk mount does not hide them. Render sets `RENDER_EXTERNAL_URL`, which the app uses for secure password-reset links and HTTPS session cookies.

The project has no Git remote or Render account connection configured in this workspace, so pushing the repository, entering database secrets, and creating the live Render service must be completed in your GitHub and Render accounts. Password-reset emails also require a working mail transport; configure one before relying on resets in production.
