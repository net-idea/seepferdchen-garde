(function () {
  const d: Document = document;

  const emailUser: number[] = [109, 97, 105, 108];
  const emailDomain: number[] = [115, 101, 101, 112, 102, 101, 114, 100, 99, 104, 101, 110, 45, 103, 97, 114, 100, 101, 46, 100, 101];

  const phoneDisplay: number[] = [48, 49, 55, 54, 56, 32, 47, 32, 51, 50, 51, 57, 48, 49, 49];
  const phoneDigits: number[] = [48, 49, 55, 54, 56, 51, 50, 51, 57, 48, 49, 49];

  const fromCodes = (arr: number[]): string => String.fromCharCode.apply(null, arr as unknown as number[]);

  const email: string = fromCodes(emailUser) + '@' + fromCodes(emailDomain);
  const phoneText: string = fromCodes(phoneDisplay);
  const phoneTel: string = fromCodes(phoneDigits);

  // Email links (supports multiple)
  const emailElements = Array.from(d.querySelectorAll<HTMLElement>('.contact-email'));
  const emailElementById = d.getElementById('contact-email');

  if (emailElements.length === 0 && emailElementById) emailElements.push(emailElementById);

  emailElements.forEach((emailElement) => {
    const a: HTMLAnchorElement = d.createElement('a');
    a.href = 'mailto:' + email;
    a.textContent = email;
    a.rel = 'nofollow';
    emailElement.replaceWith(a);
  });

  // Phone links (supports multiple)
  const phoneElements = Array.from(d.querySelectorAll<HTMLElement>('.contact-phone'));
  const phoneElementById = d.getElementById('contact-phone');

  if (phoneElements.length === 0 && phoneElementById) phoneElements.push(phoneElementById);

  phoneElements.forEach((phoneElement) => {
    const a: HTMLAnchorElement = d.createElement('a');
    a.href = 'tel:' + phoneTel;
    a.textContent = phoneText;
    a.rel = 'nofollow';
    phoneElement.replaceWith(a);
  });

  // WhatsApp links (supports multiple) - fetch SVG once
  const whatsappElements = Array.from(d.querySelectorAll<HTMLElement>('.contact-whatsapp'));
  const whatsappElementById = d.getElementById('contact-whatsapp');

  if (whatsappElements.length === 0 && whatsappElementById) whatsappElements.push(whatsappElementById);

  if (whatsappElements.length > 0) {
    const whatsappNumber: string = '49' + phoneTel.replace(/^0+/, '');

    const createAnchor = (): HTMLAnchorElement => {
      const a: HTMLAnchorElement = d.createElement('a');
      a.href = 'https://wa.me/' + whatsappNumber;
      a.target = '_blank';
      a.rel = 'nofollow noopener';
      return a;
    };

    fetch('/assets/icons/whatsapp.svg')
      .then((res: Response) => res.text())
      .then((svg: string) => {
        whatsappElements.forEach((whatsappElement) => {
          const a = createAnchor();
          // preserve classes from placeholder (so btn styles remain)
          a.className = whatsappElement.className;

          // if the placeholder had label text, keep it alongside the icon
          const label = (whatsappElement.textContent || '').trim();
          if (svg) {
            a.innerHTML = svg;
            if (label) a.appendChild(d.createTextNode(' ' + label));
          } else if (label) {
            a.textContent = label;
          } else {
            a.textContent = 'WhatsApp';
          }

          whatsappElement.replaceWith(a);
        });
      })
      .catch(() => {
        whatsappElements.forEach((whatsappElement) => {
          const a = createAnchor();
          a.className = whatsappElement.className;
          const label = (whatsappElement.textContent || '').trim();
          a.textContent = label || 'WhatsApp';
          whatsappElement.replaceWith(a);
        });
      });
  }
})();
