(function($) {
  var kbapihost;
  var session;

  function initializeVariables() {
      kbapihost = masterkeyLoginData.kbapihost;
      session = masterkeyLoginData.session;
      console.log('KB API Host:', kbapihost);
      console.log('Session:', session);
  }

  function initializeBankVaultApi() {
      if (typeof BankVaultMobile !== 'undefined') {
        document.querySelectorAll('input[type="password"]').forEach(function(input) {
          // make the input readonly
          input.readOnly = "readonly";
        });
        BankVaultMobile.init({ session, host: kbapihost, secret: masterkeyLoginData.secret });
      } else if (typeof BankVaultApi !== 'undefined') {
        BankVaultApi.init({session, host: kbapihost});
      } else {
        console.error('BankVaultApi is not loaded');
      }
  }

  $(window).load(function() {
      initializeVariables();
      initializeBankVaultApi();
  });
})(jQuery);