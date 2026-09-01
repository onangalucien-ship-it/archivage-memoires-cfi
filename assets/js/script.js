// Confirmation pour les actions sensibles (retrait, suppression, désactivation)
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-confirmer]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            var message = el.getAttribute('data-confirmer') || 'Confirmez-vous cette action ?';
            if (!window.confirm(message)) {
                e.preventDefault();
            }
        });
    });

    document.querySelectorAll('.alert').forEach(function (el) {
        setTimeout(function () { el.style.display = 'none'; }, 8000);
    });
});
