const siteHeader = document.getElementById('siteHeader');
const menuToggle = document.getElementById('menuToggle');
const mobileNav = document.getElementById('mobileNav');

function setHeaderState() {
  if (!siteHeader) return;
  siteHeader.classList.toggle('scrolled', window.scrollY > 20);
}

setHeaderState();
window.addEventListener('scroll', setHeaderState, { passive: true });

if (menuToggle && mobileNav) {
  menuToggle.addEventListener('click', () => {
    const isOpen = mobileNav.classList.toggle('open');
    menuToggle.classList.toggle('active', isOpen);
    menuToggle.setAttribute('aria-expanded', String(isOpen));
    document.body.classList.toggle('menu-open', isOpen);
  });

  mobileNav.querySelectorAll('a').forEach((link) => {
    link.addEventListener('click', () => {
      mobileNav.classList.remove('open');
      menuToggle.classList.remove('active');
      menuToggle.setAttribute('aria-expanded', 'false');
      document.body.classList.remove('menu-open');
    });
  });
}

const revealItems = document.querySelectorAll('.reveal-section');

if ('IntersectionObserver' in window) {
  const revealObserver = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        entry.target.classList.add('visible');
        revealObserver.unobserve(entry.target);
      }
    });
  }, { threshold: 0.14 });

  revealItems.forEach((item) => revealObserver.observe(item));
} else {
  revealItems.forEach((item) => item.classList.add('visible'));
}

/* ==========================================================
   UNIVERSAL SMOOTH FAQ ACCORDION
   Works with existing:
   <div class="faq-list">
     <details>
       <summary>Question?</summary>
       <p>Answer...</p>
     </details>
   </div>

   Behaviour:
   - One FAQ open at a time
   - Smooth open/close animation
   - No hard snap from native details toggle
   - Reduced-motion friendly
========================================================== */

document.querySelectorAll('.faq-list').forEach((faqList) => {
  const faqItems = Array.from(faqList.querySelectorAll('details'));

  faqItems.forEach((details) => {
    const summary = details.querySelector('summary');

    if (!summary) return;

    let answer = details.querySelector('.faq-answer');

    if (!answer) {
      answer = document.createElement('div');
      answer.className = 'faq-answer';

      const nodesToWrap = Array.from(details.childNodes).filter((node) => node !== summary);

      nodesToWrap.forEach((node) => {
        answer.appendChild(node);
      });

      details.appendChild(answer);
    }

    details.removeAttribute('open');
    details.classList.remove('is-open', 'is-closing');
    answer.style.height = '0px';
    answer.style.opacity = '0';

    summary.addEventListener('click', (event) => {
      event.preventDefault();

      const isOpen = details.classList.contains('is-open');

      if (isOpen) {
        closeFaq(details);
        return;
      }

      faqItems.forEach((item) => {
        if (item !== details && item.classList.contains('is-open')) {
          closeFaq(item);
        }
      });

      openFaq(details);
    });
  });
});

function openFaq(details) {
  const answer = details.querySelector('.faq-answer');
  if (!answer) return;

  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  details.open = true;
  details.classList.remove('is-closing');
  details.classList.add('is-open');

  if (prefersReducedMotion) {
    answer.style.height = 'auto';
    answer.style.opacity = '1';
    return;
  }

  answer.style.height = '0px';
  answer.style.opacity = '0';

  requestAnimationFrame(() => {
    answer.style.height = `${answer.scrollHeight}px`;
    answer.style.opacity = '1';
  });

  const onTransitionEnd = (event) => {
    if (event.propertyName !== 'height') return;

    answer.style.height = 'auto';
    answer.removeEventListener('transitionend', onTransitionEnd);
  };

  answer.addEventListener('transitionend', onTransitionEnd);
}

function closeFaq(details) {
  const answer = details.querySelector('.faq-answer');
  if (!answer) return;

  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  details.classList.remove('is-open');
  details.classList.add('is-closing');

  if (prefersReducedMotion) {
    answer.style.height = '0px';
    answer.style.opacity = '0';
    details.open = false;
    details.classList.remove('is-closing');
    return;
  }

  answer.style.height = `${answer.scrollHeight}px`;

  requestAnimationFrame(() => {
    answer.style.height = '0px';
    answer.style.opacity = '0';
  });

  const onTransitionEnd = (event) => {
    if (event.propertyName !== 'height') return;

    details.open = false;
    details.classList.remove('is-closing');
    answer.removeEventListener('transitionend', onTransitionEnd);
  };

  answer.addEventListener('transitionend', onTransitionEnd);
}