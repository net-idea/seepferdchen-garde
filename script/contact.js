(function () {
  const d = document;

  // "mail"
  const emailUser = [109, 97, 105, 108];
  // "seepferchen-garde.de"
  const emailDomain = [115, 101, 101, 112, 102, 101, 114, 99, 104, 101, 110, 45, 103, 97, 114, 100, 101, 46, 100, 101];

  // Display: "01768 / 3239011"
  const phoneDisplay = [48, 49, 55, 54, 56, 32, 47, 32, 51, 50, 51, 57, 48, 49, 49];
  // Dial: "017683239011"
  const phoneDigits = [48, 49, 55, 54, 56, 51, 50, 51, 57, 48, 49, 49];

  const fromCodes = (arr) => String.fromCharCode.apply(null, arr);

  const email = fromCodes(emailUser) + '@' + fromCodes(emailDomain);
  const phoneText = fromCodes(phoneDisplay);
  const phoneTel = fromCodes(phoneDigits);

  const emailEl = d.getElementById('contact-email');

  if (emailEl) {
    const a = d.createElement('a');

    a.href = 'mailto:' + email;
    a.textContent = email;
    a.rel = 'nofollow';
    emailEl.replaceWith(a);
  }

  const phoneEl = d.getElementById('contact-phone');

  if (phoneEl) {
    const a = d.createElement('a');

    a.href = 'tel:' + phoneTel;
    a.textContent = phoneText;
    a.rel = 'nofollow';
    phoneEl.replaceWith(a);
  }
})();
