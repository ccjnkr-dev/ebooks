<?php

return [
    'currency' => 'PHP',
    'gateway' => env('PAYMENT_GATEWAY', 'simulated'),
    'reservation_minutes' => 15,
    'preview_seconds' => 30,
    'pdftoppm' => env('PDFTOPPM_BINARY', 'pdftoppm'),
    'pdfinfo' => env('PDFINFO_BINARY', 'pdfinfo'),
    'ffmpeg' => env('FFMPEG_BINARY', 'ffmpeg'),
    'max_book_pages' => 500,
];
