import Alpine from 'alpinejs';

Alpine.data('contentForm', (config) => ({
    type: config.type,
    dirty: false,
    processing: config.processing,
    processingFinished: false,
    pollTimer: null,
    checking: false,
    get fileAccept() {
        return { book: '.pdf', music: '.mp3', painting: '.jpg,.jpeg,.png' }[this.type];
    },
    get fileHelp() {
        return {
            book: 'PDF: up to 50 MB and 500 pages. The first page becomes the public preview.',
            music: 'MP3: up to 100 MB. A separate 30-second preview is created automatically.',
            painting: 'JPG/PNG: up to 20 MB and 40 megapixels. A watermark is burned into the sample.',
        }[this.type];
    },
    init() {
        if (this.processing && config.statusUrl) {
            this.pollTimer = window.setInterval(() => this.checkProgress(config.statusUrl), 4000);
        }
    },
    async checkProgress(url) {
        if (this.checking) return;
        this.checking = true;
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!response.ok || !response.headers.get('content-type')?.includes('application/json')) {
                window.clearInterval(this.pollTimer);
                return;
            }
            const state = await response.json();
            if (!state.processing) {
                window.clearInterval(this.pollTimer);
                this.processing = false;
                this.processingFinished = true;
                if (!this.dirty) window.location.reload();
            }
        } catch {
            // Keep unsaved edits when a temporary network problem interrupts polling.
        } finally {
            this.checking = false;
        }
    },
    destroy() {
        window.clearInterval(this.pollTimer);
    },
}));

window.Alpine = Alpine;
Alpine.start();
