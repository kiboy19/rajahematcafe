/*
 * Interaksi kecil yang perlu JavaScript di aplikasi server-rendered.
 * Sengaja minimal: shared hosting tanpa daemon, polling tetap lewat meta refresh.
 */
document.addEventListener('DOMContentLoaded', () => {
    // F8 mengirim form checkout POS (label tombol menyebut F8).
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'F8') {
            return;
        }

        const form = document.getElementById('checkout-form');
        if (form) {
            event.preventDefault();
            form.requestSubmit();
        }
    });

    // Cegah klik ganda pada form uang: tombol submit dimatikan saat form dikirim.
    document.querySelectorAll('form[data-single-submit]').forEach((form) => {
        form.addEventListener('submit', () => {
            form.querySelectorAll('button[type="submit"]').forEach((button) => {
                button.disabled = true;
                button.setAttribute('aria-busy', 'true');
            });
        });
    });
});