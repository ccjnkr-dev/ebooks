# Muse & Folio — Content Commerce

A Laravel storefront for online books, music streaming, and physical painting sales. Temporary branding is editable under **Admin → Store settings**. Payments are simulations; this project does not accept real money.

## Requirements

- PHP 8.5 recommended (the committed dependency lock requires PHP 8.4+), Composer 2, Node 22.12+ with npm.
- PHP extensions: ctype, curl, dom, fileinfo, filter, gd, hash, iconv, mbstring, openssl, pcre, PDO, pdo_sqlite, session, tokenizer, xml, xmlreader, xmlwriter, zip. SQLite is the development database.
- Poppler utilities (`pdfinfo`, `pdftoppm`) and FFmpeg with the `libmp3lame` encoder, available on PATH or configured using absolute paths in `.env`.
- Recommended PHP upload settings: `upload_max_filesize=100M`, `post_max_size=110M`, `memory_limit=512M`. Coordinate web-server request limits too.

## Local installation

Run from `C:\Users\RalphIan\Desktop\PROJECTS\websites\content_site_commerce` (or its WSL path).

```sh
composer install
```

Copy `.env.example` to `.env` if it does not exist (`Copy-Item .env.example .env` in PowerShell or `cp .env.example .env` in Bash). Set `APP_URL` to your local URL. Never overwrite an existing `APP_KEY` after storing data.

```sh
php artisan key:generate
php artisan migrate --seed
npm ci
npm run build
php artisan admin:create owner@example.com
```

The admin command securely prompts for a password; there is no default admin password. Sign in at `/login`, then visit `/admin`. Normal customer accounts can be created at `/register`.

### Prepared WSL runtime

This workspace includes an ignored `.runtime/` directory with the missing PHP extensions, image/PDF libraries, and media tools for this machine's Linux PHP 8.5. It is a local development helper, not a production distribution. The FFmpeg helper is used for development verification; install a maintained FFmpeg release before production.

From WSL, start the server, queue worker, and scheduler together:

```sh
bash scripts/dev.sh
```

Use `.runtime/bin/php artisan admin:create owner@example.com` to create the first admin, or `.runtime/bin/php artisan test` to use the prepared extensions. Stop the launcher with Ctrl+C. Standard Windows PHP still uses the requirements and installation steps above. `.env` contains no `/tmp` tool paths. If you move the folder or change PHP versions, replace this local runtime with an installed, compatible PHP toolchain.

Run these in **three terminals**:

```sh
php artisan serve --host=127.0.0.1 --port=8000
php artisan queue:work --timeout=600 --tries=1 --memory=512
php artisan schedule:work
```

Open **http://127.0.0.1:8000**. For frontend editing, run `npm run dev` in another terminal; the built frontend works without Vite running. `composer dev` also starts the standard Laravel development stack, but use the queue command above for long PDF uploads.

`DB_QUEUE_RETRY_AFTER=660` must exceed the worker timeout. The scheduler releases painting stock after 15-minute unpaid checkout holds. Checkout also cleans up expired holds before creating a new order.

### Windows media tools

Install Poppler and FFmpeg separately, then either add their `bin` directories to PATH or configure quoted executable paths, for example:

```dotenv
PDFINFO_BINARY="C:/tools/poppler/Library/bin/pdfinfo.exe"
PDFTOPPM_BINARY="C:/tools/poppler/Library/bin/pdftoppm.exe"
FFMPEG_BINARY="C:/tools/ffmpeg/bin/ffmpeg.exe"
```

Use paths matching your actual installation. Do not mix WSL executable paths with Windows PHP. If PHP reports a missing extension, enable it in the PHP configuration used by that terminal (`php --ini`) before running Composer.

## Demo data and uploading

`php artisan db:seed` adds nine sample product records, editable settings/categories, and two clearly simulated historical transactions. The inactive `demo-collector@example.test` account owns those records and has no published login credentials. Seeders do not create or download media. Existing seeded records/settings are retained when seeding again.

Media directories are initially empty apart from ignore files:

- `storage/app/private/originals/{books,music,paintings}` — private uploads.
- `storage/app/private/processed` — generated pages, clips, and watermarked samples.
- `storage/app/private/previews` — reserved for future preview workflows.

Sample listings show placeholders and cannot be purchased until an admin uploads real content. To make a product available:

1. Open Add content, or edit an existing listing. Fill in its details and choose the content file in the same form: PDF (50 MB, 1–500 pages, unencrypted), MP3 (100 MB), or JPG/PNG (20 MB, 40 megapixels, at most 10,000 pixels per side). Painting stock appears only for paintings.
2. Choose Published and click **Save content** once. The upload and product details are saved together. Existing files can be kept by leaving the file field empty.
3. Keep the queue worker running (or use `bash scripts/dev.sh`). New content stays private while previews are prepared, then publishes automatically. The edit page updates when preparation finishes, preserving unsaved edits. To retry a failed file, use Save content again or select a replacement. Choosing Draft or Archived cancels pending publication. A replacement becomes active only after successful processing; old files stay private.

Paintings have available inventory and are **pickup only**. Configure actual pickup instructions and a contact email before taking orders. Orders retain the pickup instructions they were created with. Admins move paid painting orders from awaiting → ready → collected.

## Content access and its practical limits

- Original PDFs and painting files are never exposed through storefront routes. No public storage symlink is needed. The local filesystem's generic file-server route is disabled.
- Public book previews serve only the first rasterized page; changing a query parameter cannot expose later pages. Purchased readers request one page at a time and show a customer-specific watermark.
- Public music previews use a separately encoded clip of up to 30 seconds. Full audio is served only after session authentication and entitlement checks, with HTTP range support for seeking.
- Painting samples are downscaled with a watermark burned into their pixels. CSS overlays are not used as the protection mechanism.
- Purchased media URLs expire after 15 minutes, are tied to the signed-in customer, and recheck ownership on every request. Refunds and disabled accounts stop further requests immediately. Protected responses use `private, no-store`.
- No download button is provided. **Any image/audio delivered to a browser can still be saved or recorded**, including through developer tools. These controls protect unpaid content and private originals; they are not DRM and cannot guarantee that authorized playback cannot be copied.

Deploy with **only `public/` as the web root**. Never expose `storage/`, `.env`, SQLite files, or the repository root through the web server. Never add private media to `public/` or a public CDN. Back up the database and private media together; both are required to preserve purchases.

## Payments and the future provider

`App\Contracts\PaymentGateway` is bound to `SimulatedGateway`. Its local checkout can simulate success, failure, or cancellation. The server stores and verifies totals, processes repeated confirmation idempotently, and grants access only upon successful settlement. Digital products cannot be purchased twice while owned or while another checkout remains pending.

Simulation is allowed only in `local` and `testing`, with `PAYMENT_GATEWAY=simulated`. Production checkout returns 503 until a real provider is implemented. Do not enable public real-money sales with this adapter.

To integrate PayMongo or another provider:

1. Implement a new `PaymentGateway`, bind it in `AppServiceProvider`, and store API credentials only in environment secrets.
2. Replace the simulated confirmation form/route with provider checkout plus a webhook endpoint. Verify provider signatures, references, currency, amount, and final status **server-side** before calling `CommerceService::settle`.
3. Use `success`, `failure`, or `cancel` only as normalized **verified server-side results**. Never pass an unverified customer/browser result into settlement. Redirects alone must not grant access.
4. Add provider event IDs/reconciliation and real refund API handling. The current refund action only simulates a full refund and revokes access; collected paintings cannot be refunded through it.
5. Test duplicate, out-of-order, forged, and delayed webhooks before switching the configured provider. No webhook endpoint is exposed by the demo.

## Administration and deletion

`/admin` provides product/category management, uploads and processing retry, pricing, inventory, orders/pickups, payment records, simulated full refunds, customer enable/disable, digital-access revocation, date-filtered reports/CSV, store branding, and audit history. All admin routes enforce an active admin account.

Deleting a purchased product archives its listing while retaining customer access and order history. Deleting an unpurchased product moves it to **Deleted content**. Explicit permanent cleanup removes its private files only when no order references or pending processing jobs exist. Historical orders and payments are never deleted by content cleanup.

Available painting stock excludes pending reservations. Adjust it with that in mind. Refunding an uncollected painting restores its reserved units; repeated refunds do not restore stock twice.

Reports group orders by their creation date. Gross simulated sales include paid/refunded orders; refunds are subtracted to calculate net sales. CSV exports are available only to admins and contain order records, not payment credentials.

## Verification

```sh
php artisan test
php artisan migrate:status
php artisan route:list
npm run build
vendor/bin/pint --format agent
```

With PCOV or Xdebug installed:

```sh
php artisan test --coverage --min=85
```

The suite verifies preview isolation, signed URL authorization/expiry, watermark generation, real PDF rendering and MP3 clipping, checkout totals/idempotency, stock holds/expiry, simulated refunds, pickup transitions, admin access, upload validation, content deletion, and account behavior. PDF/audio integration tests skip explicitly when their executables are unavailable. Test fixtures are generated in isolated test storage and are not demo content.

Before a real deployment: configure HTTPS, a production database/backup strategy, queue and scheduler supervisors, real pickup/contact details, transactional email/password recovery, and the real payment adapter. Email delivery and self-service forgotten-password recovery are not configured in this local prototype.

### Completed implementation checks

Verified 46 automated tests (264 assertions), with no failures or skipped media integrations and 96% application line coverage. The production frontend build passes. Browser checks covered admin PDF/MP3/image uploads and publication, customer registration, mixed-cart simulated checkout, purchased second-page reading, audio playback, pickup order display, mobile navigation, and layouts at 360/390/768/1440 pixels. QA media, accounts, and purchases were isolated from the delivered database.
