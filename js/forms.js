(function(){
  function showMessage(container, text, type){
    if(!container) return;
    container.textContent = text;
    container.classList.remove('d-none', 'alert-success', 'alert-danger');
    container.classList.add(type === 'success' ? 'alert-success' : 'alert-danger');
  }

  function clearMessage(container){
    if(!container) return;
    container.classList.add('d-none');
    container.textContent = '';
  }

  function setupForm(form){
    // Ensure the form never submits to a mailto: handler.
    var endpoint = form.getAttribute('action');
    if(!endpoint || endpoint.indexOf('mailto:') === 0){
      endpoint = '/contact.php';
      form.setAttribute('action', endpoint);
    }

    var feedback = form.querySelector('.form-feedback');
    if(!feedback){
      feedback = document.createElement('div');
      feedback.className = 'alert d-none form-feedback mt-3';
      feedback.setAttribute('role', 'alert');
      form.insertAdjacentElement('afterend', feedback);
    }

    form.addEventListener('submit', function(event){
      event.preventDefault();
      clearMessage(feedback);

      endpoint = form.getAttribute('action') || '/contact.php';

      if(!form.reportValidity || form.reportValidity()){
        var submitButton = form.querySelector('button[type="submit"]');
        if(submitButton){
          submitButton.disabled = true;
          submitButton.dataset.originalText = submitButton.textContent;
          submitButton.textContent = 'Šaljemo...';
        }

        var formData = new FormData(form);
        if(!formData.get('form_type') && form.dataset.contactForm){
          formData.set('form_type', form.dataset.contactForm === 'prijava' ? 'application' : 'contact');
        }

        fetch(endpoint, {
          method: 'POST',
          body: formData
        }).then(function(response){
          return response.json();
        }).then(function(data){
          var type = data && data.success ? 'success' : 'error';
          var message = (data && data.message) ? data.message : 'Došlo je do pogreške pri slanju poruke. Molimo pokušajte ponovno kasnije.';
          showMessage(feedback, message, type === 'success' ? 'success' : 'error');
          if(type === 'success'){
            form.reset();
          }
        }).catch(function(){
          showMessage(feedback, 'Došlo je do pogreške pri slanju poruke. Molimo pokušajte ponovno kasnije.', 'error');
        }).finally(function(){
          if(submitButton){
            submitButton.disabled = false;
            submitButton.textContent = submitButton.dataset.originalText || 'Pošalji';
          }
        });
      }
    });
  }

  document.addEventListener('DOMContentLoaded', function(){
    var forms = document.querySelectorAll('[data-contact-form]');
    forms.forEach(setupForm);
  });
})();
