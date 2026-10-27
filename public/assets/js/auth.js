const form = document.querySelector('[data-auth-form]');

if (form) {
    const password = form.querySelector('[name="password"]');
    const confirmation = form.querySelector('[name="confirm_password"]');

    if (confirmation) {
        const validateConfirmation = () => {
            confirmation.setCustomValidity(
                confirmation.value && confirmation.value !== password.value
                    ? 'Les mots de passe doivent être identiques.'
                    : ''
            );
        };

        password.addEventListener('input', validateConfirmation);
        confirmation.addEventListener('input', validateConfirmation);
    }

    // L'inscription est envoyée à PHP ; seule la connexion reste une maquette.
    // if (form.dataset.authForm === 'login') {
    //     form.addEventListener('submit', (event) => {
    //         event.preventDefault();
    //         const status = form.querySelector('[role="status"]');
    //         status.textContent = 'La connexion n’est pas encore disponible.';
    //         status.hidden = false;
    //     });
    // }
}
