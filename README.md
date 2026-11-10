# Chair & Blade

A booking website for a fictional barbershop, built with Laravel 13, Blade, Tailwind CSS 4, Vite, and SQLite.

Customers can browse services, choose an available time, pay through Stripe Checkout, and manage their appointments. Admins can search and filter bookings. Confirmation and reminder emails run through Laravel's scheduler.

## Requirements

- PHP 8.3+ and Composer
- Node.js 22.12+ and npm
- Stripe CLI for local payment webhooks
- Stripe test credentials and an SMTP provider for payment and email testing

## Local setup

For a fresh installation:

```sh
cd chair-and-blade
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
npm ci
npm run build
```

Configure `.env`, then start the application:

```sh
composer run dev
```

Open [127.0.0.1:8001](http://127.0.0.1:8001). The command starts the web server, Vite, scheduler, and Stripe webhook listener. Keep it running while testing payments and emails. Stop any existing server on port 8001 before starting it.

To run each component in a separate terminal:

```sh
php artisan serve --host=127.0.0.1 --port=8001
npm run dev
php artisan schedule:work
php artisan payments:listen
```

## Payments and email

Set the following values in `.env`:

```dotenv
APP_URL=http://127.0.0.1:8001
STRIPE_MODE=test
STRIPE_SECRET=your_test_secret
APPOINTMENT_EMAILS_ENABLED=true
```

Configure `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, and `MAIL_FROM_NAME` for your mail provider. Keep credentials out of source control.

The local `payments:listen` command forwards Stripe events to `/stripe/webhook` and saves `STRIPE_WEBHOOK_SECRET` in `.env`. It requires the Stripe CLI and network access.

Bookings reserve their time for 35 minutes while the customer completes checkout. Payment confirmation requires a signed webhook or a server-side lookup with matching amount, currency, mode, and appointment details. The return URL alone cannot confirm a payment. Retrying checkout reuses the existing session.

The scheduler checks expired reservations before releasing their times. Paid appointments receive a confirmation and a reminder within 24 hours of the appointment. If a booking is already within that window, the confirmation also serves as its reminder. Failed email attempts remain eligible for retry; SMTP acceptance does not guarantee inbox delivery.

## Accounts and administration

| Page | Purpose |
| --- | --- |
| `/` | Introduction, services, prices, and opening hours |
| `/book` | Availability calendar and booking |
| `/register`, `/login`, `/forgot-password` | Registration, sign-in, and password reset |
| `/account` | Customer appointments and payment status |
| `/admin/appointments` | Admin booking search and filters |

Register an account, then grant it admin access from the terminal:

```sh
php artisan users:make-admin your-email@example.com
```

Registration cannot grant admin access. Customers can access only their own appointments; admins can view all bookings.

## Booking rules

The shop has one chair, shared across all services. Configure its schedule in `config/booking.php`:

- Tuesday–Saturday, 09:00–13:00 and 14:00–18:00.
- Appointments start every 15 minutes, with at least 45 minutes' notice.
- Customers can book up to 60 days ahead.
- Times display in Europe/Malta and are stored in UTC.

Unique database constraints prevent overlapping appointments, including simultaneous requests. Prices and durations come from the selected service and are saved with the booking.

## Tests and build

```sh
composer test
npm run build
```

Tests use an in-memory SQLite database, fake mail, and mocked Stripe responses. They cover accounts, ownership, admin access, availability, overlapping bookings, timezone conversion, payment verification, webhook signatures, reservation expiry, and email retries.

## Deployment

Set `APP_ENV=production`, `APP_DEBUG=false`, and an HTTPS `APP_URL`. Configure the database and mail provider, build frontend assets, and register `/stripe/webhook` with its own signing secret in Stripe. Live payments require matching live credentials and `STRIPE_MODE=live`.

Run the scheduler every minute:

```cron
* * * * * cd /path/to/barbershop && php artisan schedule:run >> /dev/null 2>&1
```

This is a portfolio demo with fictional services. Multiple barbers, holiday closures, customer cancellations, and refunds are not implemented.
