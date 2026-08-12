import jsQR from 'jsqr';

const MAX_DIMENSION = 1600;

function readFileAsDataUrl(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = (event) => resolve(event.target.result);
        reader.onerror = reject;
        reader.readAsDataURL(file);
    });
}

function loadImage(dataUrl) {
    return new Promise((resolve, reject) => {
        const image = new Image();
        image.onload = () => resolve(image);
        image.onerror = reject;
        image.src = dataUrl;
    });
}

function decodeQrFromImage(image) {
    const scale = Math.min(1, MAX_DIMENSION / Math.max(image.width, image.height));
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(image.width * scale);
    canvas.height = Math.round(image.height * scale);

    const context = canvas.getContext('2d');
    context.drawImage(image, 0, 0, canvas.width, canvas.height);

    const imageData = context.getImageData(0, 0, canvas.width, canvas.height);
    const result = jsQR(imageData.data, imageData.width, imageData.height);

    return result ? result.data : null;
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('qrisPhotoScan', () => ({
        targetStatePath: null,
        isProcessing: false,
        error: null,

        async handleFile(event) {
            const [file] = event.target.files;
            event.target.value = '';

            if (!file) {
                return;
            }

            this.error = null;
            this.isProcessing = true;

            try {
                const image = await loadImage(await readFileAsDataUrl(file));
                const text = decodeQrFromImage(image);

                if (!text) {
                    this.error = 'QR tidak terbaca dari foto ini. Coba foto yang lebih jelas dan tidak buram.';
                    return;
                }

                this.$wire.set(this.targetStatePath, text);
            } catch (e) {
                this.error = 'Gagal membaca foto.';
            } finally {
                this.isProcessing = false;
            }
        },
    }));
});
