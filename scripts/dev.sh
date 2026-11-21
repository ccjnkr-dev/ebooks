#!/usr/bin/env bash
set -euo pipefail
project_root="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$project_root"
if [[ -x "$project_root/.runtime/bin/php" ]]; then
    export PATH="$project_root/.runtime/bin:$PATH"
fi
if ! php -r 'exit(extension_loaded("pdo_sqlite") && extension_loaded("gd") && extension_loaded("mbstring") ? 0 : 1);'; then
    printf '%s\n' 'Enable the PHP extensions listed in README.md before starting.' >&2
    exit 1
fi
php artisan queue:work --timeout=600 --tries=1 --memory=512 &
commerce_queue_pid=$!
php artisan schedule:work &
commerce_schedule_pid=$!
trap 'kill "$commerce_queue_pid" "$commerce_schedule_pid" 2>/dev/null || true' EXIT
trap 'exit 130' INT TERM
php artisan serve --host=127.0.0.1 --port=8000 --no-reload
