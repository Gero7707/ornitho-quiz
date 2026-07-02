document.addEventListener('DOMContentLoaded', function() {

    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            const radios = this.querySelectorAll('input[type="radio"]');
            if(radios.length > 0) {
                const checked = this.querySelector('input[type="radio"]:checked');
                if(!checked) {
                    e.preventDefault();
                    const errorMess = this.querySelector('.error-mess');
                    if(errorMess) {
                        errorMess.textContent = 'Veuillez sélectionner une réponse avant de valider.';
                        errorMess.style.color = 'red';
                    }
                }
            }
        });
    });
});