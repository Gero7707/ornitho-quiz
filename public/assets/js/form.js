const btnPassword = document.querySelectorAll('.btn-password');

const btnSupprimerCompte = document.getElementById('btnValiderSuppression');
const password = document.getElementById('password');
const email = document.getElementById('suppimerCompte');
const errorMessage = document.querySelector('.error-message');

btnPassword.forEach(btn =>{
    btn.addEventListener('click' , () =>{
        const input = document.getElementById(btn.dataset.target);
        if(input.type === "password"){
            btn.innerHTML = '<i class="fa-solid fa-eye"></i>';
            btn.setAttribute('aria-label', 'Masquer le mot de passe');
            input.type = "text";
        }else if(input.type === "text"){
            btn.innerHTML = '<i class="fa-regular fa-eye"></i>';
            btn.setAttribute('aria-label', 'Voir le mot de passe');
            input.type = "password";
        }
    });
});

