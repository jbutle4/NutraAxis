(function () {
  function initPasswordToggles(root) {
    var scope = root || document;
    scope.querySelectorAll('.signup-input-toggle[data-toggle-for]').forEach(function (button) {
      if (button.dataset.toggleBound === '1') {
        return;
      }
      button.dataset.toggleBound = '1';

      var inputId = button.getAttribute('data-toggle-for');
      var input = inputId ? scope.getElementById(inputId) : null;
      if (!input) {
        return;
      }

      var fieldLabel = button.getAttribute('data-toggle-label') || 'field';

      button.addEventListener('click', function () {
        var showing = input.type === 'text';
        input.type = showing ? 'password' : 'text';
        button.textContent = showing ? 'Show' : 'Hide';
        button.setAttribute('aria-pressed', showing ? 'false' : 'true');
        button.setAttribute(
          'aria-label',
          (showing ? 'Show' : 'Hide') + ' ' + fieldLabel
        );
      });
    });
  }

  function initAchMatchValidation(root) {
    var scope = root || document;
    scope.querySelectorAll('form.signup-form').forEach(function (form) {
      if (form.dataset.achMatchBound === '1') {
        return;
      }

      var account = form.querySelector('#ach_account_number');
      var confirm = form.querySelector('#ach_account_number_confirm');
      if (!account || !confirm) {
        return;
      }

      form.dataset.achMatchBound = '1';
      form.addEventListener('submit', function (event) {
        var accountValue = (account.value || '').replace(/\D+/g, '');
        if (accountValue === '') {
          return;
        }

        var confirmValue = (confirm.value || '').replace(/\D+/g, '');
        if (confirmValue === '') {
          event.preventDefault();
          window.alert('Please confirm your ACH account number.');
          confirm.focus();
          return;
        }

        if (accountValue !== confirmValue) {
          event.preventDefault();
          window.alert('ACH account numbers do not match. Please re-enter both fields.');
          confirm.focus();
        }
      });
    });
  }

  function init() {
    initPasswordToggles(document);
    initAchMatchValidation(document);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
