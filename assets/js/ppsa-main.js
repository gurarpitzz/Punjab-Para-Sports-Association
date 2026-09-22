/**
 * Punjab Para Sports Association (PPSA) - Interactive Master Controller
 * Handles: Hero Carousel, Header Sticky & Mobile Drawer, Dropdowns, Placeholder Modals
 */

document.addEventListener('DOMContentLoaded', () => {
  initHeroCarousel();
  initHeaderInteractions();
  initPlaceholderModals();
  initSportOverviewModals();
});

/* ==========================================================================
   1. HERO CAROUSEL CONTROLLER
   ========================================================================== */
function initHeroCarousel() {
  const track = document.getElementById('heroCarouselTrack');
  const slides = track ? track.querySelectorAll('.carousel-slide') : [];
  const dots = document.querySelectorAll('.carousel-dot');
  const btnPrev = document.getElementById('heroPrevBtn');
  const btnNext = document.getElementById('heroNextBtn');
  const heroSection = document.getElementById('heroCarousel');

  if (!slides.length) return;

  let currentIndex = 0;
  let autoplayTimer = null;
  const slideDuration = 6000; // 6 seconds per slide

  function showSlide(index) {
    slides[currentIndex].classList.remove('is-active');
    slides[currentIndex].setAttribute('aria-hidden', 'true');
    if (dots[currentIndex]) {
      dots[currentIndex].classList.remove('is-active');
      dots[currentIndex].setAttribute('aria-selected', 'false');
    }

    currentIndex = (index + slides.length) % slides.length;

    slides[currentIndex].classList.add('is-active');
    slides[currentIndex].setAttribute('aria-hidden', 'false');
    if (dots[currentIndex]) {
      dots[currentIndex].classList.add('is-active');
      dots[currentIndex].setAttribute('aria-selected', 'true');
    }
  }

  function nextSlide() {
    showSlide(currentIndex + 1);
  }

  function prevSlide() {
    showSlide(currentIndex - 1);
  }

  function startAutoplay() {
    stopAutoplay();
    autoplayTimer = setInterval(nextSlide, slideDuration);
  }

  function stopAutoplay() {
    if (autoplayTimer) {
      clearInterval(autoplayTimer);
      autoplayTimer = null;
    }
  }

  // Event Listeners
  if (btnNext) btnNext.addEventListener('click', () => { nextSlide(); startAutoplay(); });
  if (btnPrev) btnPrev.addEventListener('click', () => { prevSlide(); startAutoplay(); });

  dots.forEach((dot, idx) => {
    dot.addEventListener('click', () => {
      showSlide(idx);
      startAutoplay();
    });
  });

  // Pause on hover or focus for accessibility
  if (heroSection) {
    heroSection.addEventListener('mouseenter', stopAutoplay);
    heroSection.addEventListener('mouseleave', startAutoplay);
    heroSection.addEventListener('focusin', stopAutoplay);
    heroSection.addEventListener('focusout', startAutoplay);
  }

  // Touch Swipe Support
  let touchStartX = 0;
  let touchEndX = 0;

  if (heroSection) {
    heroSection.addEventListener('touchstart', (e) => {
      touchStartX = e.changedTouches[0].screenX;
    }, { passive: true });

    heroSection.addEventListener('touchend', (e) => {
      touchEndX = e.changedTouches[0].screenX;
      handleSwipe();
    }, { passive: true });
  }

  function handleSwipe() {
    const diff = touchEndX - touchStartX;
    if (Math.abs(diff) > 40) {
      if (diff < 0) nextSlide();
      else prevSlide();
      startAutoplay();
    }
  }

  startAutoplay();
}

/* ==========================================================================
   2. HEADER & MOBILE DRAWER CONTROLLER
   ========================================================================== */
function initHeaderInteractions() {
  const header = document.querySelector('.ppsa-header');
  const toggleBtn = document.getElementById('ppsaMobileToggle');
  const navDrawer = document.getElementById('ppsaNavDrawer');
  const dropdownTrigger = document.querySelector('.ppsa-nav-item.has-dropdown');

  // Sticky shadow elevation
  window.addEventListener('scroll', () => {
    if (window.scrollY > 20) {
      header?.classList.add('is-scrolled');
    } else {
      header?.classList.remove('is-scrolled');
    }
  }, { passive: true });

  // Mobile menu toggle
  if (toggleBtn && navDrawer) {
    toggleBtn.addEventListener('click', () => {
      const isOpen = navDrawer.classList.toggle('is-open');
      toggleBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      document.body.style.overflow = isOpen ? 'hidden' : '';
    });
  }

  // Mobile dropdown toggle
  if (dropdownTrigger) {
    const link = dropdownTrigger.querySelector('.ppsa-nav-link');
    link?.addEventListener('click', (e) => {
      if (window.innerWidth <= 868) {
        e.preventDefault();
        dropdownTrigger.classList.toggle('is-open');
      }
    });
  }

  // Close mobile drawer on link click
  document.querySelectorAll('.ppsa-nav-link:not(.has-dropdown > .ppsa-nav-link), .ppsa-dropdown-link').forEach(link => {
    link.addEventListener('click', () => {
      if (window.innerWidth <= 868 && navDrawer?.classList.contains('is-open')) {
        navDrawer.classList.remove('is-open');
        toggleBtn?.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
      }
    });
  });
}

/* ==========================================================================
   3. PLACEHOLDER MODALS (For About, Competitions, News, Guidelines)
   ========================================================================== */
function initPlaceholderModals() {
  const modal = document.getElementById('ppsaPlaceholderModal');
  const modalTitle = document.getElementById('placeholderModalTitle');
  const modalDesc = document.getElementById('placeholderModalDesc');
  const closeBtn = document.getElementById('closePlaceholderModal');

  const placeholderLinks = document.querySelectorAll('[data-placeholder]');

  placeholderLinks.forEach(link => {
    link.addEventListener('click', (e) => {
      e.preventDefault();
      const feature = link.getAttribute('data-placeholder') || 'Feature';
      if (modalTitle) modalTitle.textContent = `${feature} — Coming Soon`;
      if (modalDesc) {
        modalDesc.textContent = `The ${feature} portal for the Punjab Para Sports Association is currently undergoing accreditation and content integration. Full schedule, guidelines, and federation documents will be published shortly.`;
      }
      openModal(modal);
    });
  });

  if (closeBtn) closeBtn.addEventListener('click', () => closeModal(modal));
  if (modal) {
    modal.addEventListener('click', (e) => {
      if (e.target === modal) closeModal(modal);
    });
  }

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && modal?.classList.contains('is-active')) {
      closeModal(modal);
    }
  });
}

/* ==========================================================================
   4. SPORT OVERVIEW MODAL (Ludhiana State Games Details)
   ========================================================================== */
const PPSA_SPORTS_DATA = {
  athletics: {
    title: "Para Athletics (Track & Field)",
    venue: "Guru Nanak Stadium, Ludhiana",
    classes: "F-51 to F-57 (Wheelchair & Seated Throws), F-35, 36, 43, 64, F-11 to 47 | T-42, T-53, T-54, T-63, T-64, T-11 to 47 (Track)",
    disciplines: "Club Throw, Discus Throw, Shot Put, Javelin Throw, 100m, 200m, 400m, 800m, 1500m, 5000m, High Jump, Long Jump",
    desc: "Para Athletics is the premier track and field competition of the Punjab Para State Games. Athletes are categorized according to their functional impairment to ensure fair and rigorous athletic competition under World Para Athletics rules."
  },
  powerlifting: {
    title: "Para Powerlifting",
    venue: "Indoor Weightlifting Hall, Ludhiana",
    classes: "Men: 49kg, 54kg, 59kg, 65kg, 72kg, 80kg, 88kg, 97kg, 107kg, +107kg | Women: 41kg, 45kg, 50kg, 55kg, 61kg, 67kg, 73kg, 79kg, 86kg, +86kg",
    disciplines: "Bench Press Competition across 20 bodyweight divisions",
    desc: "Open to athletes with lower limb or hip impairments. Athletes compete in the bench press movement, showcasing extraordinary upper-body strength and discipline across 20 distinct weight classifications."
  },
  badminton: {
    title: "Para Badminton",
    venue: "Multipurpose Indoor Hall, Ludhiana",
    classes: "Wheelchair: WH-1, WH-2 | Standing: SL-3, SL-4, SU-5, Short Stature: SS-6",
    disciplines: "Men's Singles, Men's Doubles, Mixed Doubles, Women's Singles, Women's Doubles",
    desc: "Para Badminton features six sport classifications ensuring athletes with physical or short-stature impairments compete equally across singles, doubles, and mixed team events under BWF/PCI standards."
  },
  basketball: {
    title: "Wheelchair Basketball",
    venue: "Indoor Basketball Complex, Ludhiana",
    classes: "Point classification system (1.0 to 4.5 points based on functional ability)",
    disciplines: "Men's State Championship, Women's State Championship",
    desc: "One of the world's most dynamic and tactical team sports. State district teams compete on specialized sports wheelchairs with standard 10-foot baskets and regulation court dimensions."
  }
};

function initSportOverviewModals() {
  const modal = document.getElementById('ppsaSportModal');
  const titleEl = document.getElementById('sportModalTitle');
  const venueEl = document.getElementById('sportModalVenue');
  const classesEl = document.getElementById('sportModalClasses');
  const disciplinesEl = document.getElementById('sportModalDisciplines');
  const descEl = document.getElementById('sportModalDesc');
  const closeBtn = document.getElementById('closeSportModal');

  document.querySelectorAll('[data-sport-key]').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      const key = btn.getAttribute('data-sport-key');
      const data = PPSA_SPORTS_DATA[key];
      if (!data) return;

      if (titleEl) titleEl.textContent = data.title;
      if (venueEl) venueEl.textContent = data.venue;
      if (classesEl) classesEl.textContent = data.classes;
      if (disciplinesEl) disciplinesEl.textContent = data.disciplines;
      if (descEl) descEl.textContent = data.desc;

      openModal(modal);
    });
  });

  if (closeBtn) closeBtn.addEventListener('click', () => closeModal(modal));
  if (modal) {
    modal.addEventListener('click', (e) => {
      if (e.target === modal) closeModal(modal);
    });
  }
}

function openModal(el) {
  if (!el) return;
  el.classList.add('is-active');
  document.body.style.overflow = 'hidden';
}

function closeModal(el) {
  if (!el) return;
  el.classList.remove('is-active');
  document.body.style.overflow = '';
}
