import Alpine from 'alpinejs';

const PENDING_STATUSES = ['created', 'waiting_payment', 'manual_verification'];

Alpine.data('paymentPage', ({ status, expiresAt, statusUrl }) => ({
    status,
    remaining: '',
    countdownTimer: null,
    pollTimer: null,

    init() {
        this.tickCountdown(expiresAt);
        this.countdownTimer = setInterval(() => this.tickCountdown(expiresAt), 1000);

        if (PENDING_STATUSES.includes(this.status)) {
            this.pollTimer = setInterval(() => this.checkStatus(), 5000);
        }
    },

    tickCountdown(expiresAt) {
        if (!expiresAt) {
            this.remaining = '';
            return;
        }

        const diffMs = new Date(expiresAt).getTime() - Date.now();

        if (diffMs <= 0) {
            this.remaining = '00:00';
            clearInterval(this.countdownTimer);
            window.location.reload();
            return;
        }

        const minutes = Math.floor(diffMs / 60000);
        const seconds = Math.floor((diffMs % 60000) / 1000);
        this.remaining = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    },

    async checkStatus() {
        const response = await fetch(statusUrl, { headers: { Accept: 'application/json' } });

        if (!response.ok) {
            return;
        }

        const data = await response.json();

        if (data.status !== this.status) {
            window.location.reload();
        }
    },
}));

window.Alpine = Alpine;
Alpine.start();
