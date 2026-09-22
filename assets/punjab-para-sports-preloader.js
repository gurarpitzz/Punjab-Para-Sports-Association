/**
 * Punjab Para Sports Preloader Animation Component
 * Staggered Athletic Motion Lanes Architecture:
 * - NO BALL / NO THROWING (Universal Para-Sports Progression Concept)
 * - 5 Staggered Athletic Motion Lanes emerging from underneath the Punjab map
 * - Lanes Fan OUTWARD at progressive angles with staggered race-start endpoints
 * - Layer Order: Background (1) -> Motion Lanes (2) -> Punjab Map (3) -> Athlete (4) -> Typography (5)
 * - Athlete in strong competitive forward-racing wheelchair posture
 * - Progressive path-draw animations with staggered timing
 */

class PunjabParaSportsPreloader {
  constructor(options = {}) {
    this.options = Object.assign({
      containerId: 'pps-preloader-root',
      duration: 2.7,
      autoStart: true,
      useSessionStorage: false,
      storageKey: 'pps_preloader_seen',
      onComplete: null,
      showSkipButton: false
    }, options);

    this.isCompleted = false;
    this.timerId = null;

    if (this.options.useSessionStorage && sessionStorage.getItem(this.options.storageKey)) {
      this.isCompleted = true;
      return;
    }

    if (this.options.autoStart) {
      this.init();
    }
  }

  init() {
    if (this.isCompleted) return;

    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (prefersReducedMotion) {
      this.renderOverlay(true);
      setTimeout(() => this.finishImmediately(), 400);
      return;
    }

    this.renderOverlay(false);
    this.startAnimationSequence();
  }

  renderOverlay(isReducedMotion = false) {
    let container = document.getElementById(this.options.containerId);
    if (!container) {
      container = document.createElement('div');
      container.id = this.options.containerId;
      document.body.appendChild(container);
    }

    container.className = 'pps-preloader-overlay';
    container.setAttribute('aria-busy', 'true');
    container.setAttribute('aria-label', 'Punjab Para Sports Portal Loading');

    // Official Punjab Geographical Map vector path
    const punjabGeoPath = "M336.5 254l-0.9-0.1 0.3-0.4-0.1-0.1-0.1-0.1-0.5 0.4-0.2-0.3 0.1-0.1-0.2-0.3-0.5-0.1-0.1 0.1-0.2 0.1 0 0.1-0.1 0.2-0.2 0.2-0.3 0.2-0.7 0.3 0 0.1 0.5 0.7 0.1 0.6 0 0.1 0.4 0.2-0.1 0.1 0.2 0.2 0.2 0.3 0.2-0.1 1.3 0.7 0.3-0.3 0.1-0.2 0.3 0 0 0.8 1.2-0.4-0.1 1 0.4 0 0.7 0.7 0.4 0.6-0.4 1.1 0.1 0.5-0.5 0.3 0.9 0.5-0.2 1-0.5 0.1 0.1 1.1-0.2 0.6 0.3 0.3 0.3 0.8 0.4 0.4-0.8 0.8-0.5 0 0.1-0.6-0.5-0.8-0.4-0.2-0.4-0.6-0.7 0.7-0.8-0.1-0.8-0.4-0.7 0.2 0.1 0.5-0.8 0.4 1.1 1-1.1 1.1-0.8 0.2-0.5 0.7-1 0.7-0.9 0.2-1 0.3 0.1 0.8 0.7-0.7 0.4 0.1 0.6 0.9-0.2 0.3 1 0.3 0 0.7-0.5 0.4 0.2 1.2-0.3 1.2-0.8 0.5-0.9-0.6-0.6 0.5-0.9 0.2-0.3-0.6-1.1-0.2-0.2-0.7-0.5-0.7 0.1-1.1-0.2-0.5-0.6-0.1-0.5 0.5 0.6 0.8-0.5 0.4 0.1 0.6-1.1 0-0.6 0.6-0.9-0.3-0.4 0.1-0.8-0.1-1.1-1.2-0.1 0.9 0.7 0.6 0.6 0.1-0.6 0.8-0.9 1.4-0.3 1 0.4 1.9-0.6-0.1-0.4 0.6 0.4 0.2 0.1 0.9 0.4 0.3 0.7 0 0.5 0.5-0.4 0.4-0.9 0.3-0.8 0.9-0.7 0-0.7 0.7-0.6-0.5-0.4 0.2-0.1 0.7-0.4 0.3-0.5 0.8-0.5-0.3-0.5 0.2-0.9 0.6-1 0-0.6-0.2-0.5-0.6-0.4 0.3-0.5-0.2-0.5-0.7-0.7-1.2-0.6 0.2-0.7-0.6-0.6 0.5-1.2 0.1-0.2 1.7-0.5-0.1-0.4-0.5-1.1 0-0.1 0.6-1.6 0.3-0.2-0.7-1.4-0.2-1.7-1.1 0 0.7-1.2 0.8-0.4 0.8-0.1 0.7-0.5 0-0.8 0.8 0.4 0.5-0.8 0-0.3 1.2-0.5 0.4-0.2 0.5 0.6 0.5-0.4 0.9-1.4 0.3-0.7-1 0.2-0.8-0.6-0.5-0.7-0.2 0-0.6-0.4-0.6 0.9-0.4 0.2-0.5-0.3-0.5 0.3-0.6 0.6 0 0.3-0.6-0.5-1.1-0.4-0.3 0.1-1.1-0.5-0.3-0.8 0.6-0.1 0.9-0.5 0.2-0.2-0.6-0.6 0.1-0.3-0.6 0.3-0.6-0.5-0.2 0-0.4 0.4-0.3 0.1-0.9-0.7-0.5-0.3 1.1-0.8-0.1-0.6 0.5-0.7 0.3-0.6-0.8 0.1-0.3-0.8-0.4-0.5-1.2-1.4-0.6-0.6 0-0.8-0.4-0.6-0.6-0.7 0.5-1 0.2-0.4 0.2-0.8-0.3-0.4 0.7-1.1 0.9 0 0.6-1.5-0.4-0.3-0.3-1.5-0.6-1.4 0-1.1-0.2-0.6 0.1-3.2-0.3-1 0-5-0.4-4.9 0-0.3-0.9 0.5-1.8 0.6-0.4 1.2-1.9-0.1-0.5 0.2-1.6-0.3-2.2-0.6-1.3-1-1.4-0.6-0.4-0.1-0.7 0.9-1.1 0.6-0.1 0.3-0.7-0.2-0.5 0.9 0 0.3-0.9 0.4-0.6 1.4-1 0.4 0.5 0.6-0.3 0.2-0.4-0.4-0.4 0.6-0.5 0-0.8 0.9-1 0.7-0.1 1.3-1.3 1-1.5 0.6-0.1 0.5-0.7-0.2-0.9 0.7-0.2-0.2-0.6 0.6-0.7 0.3-0.9 0.4-0.1 0.8 0.2 0.3-0.3-0.3-0.6 0.1-0.4 0.8-0.6 0.7-0.2 0-0.5 1.4-1.3 0.7 0 0.2-0.3 0.9-0.7-0.1-0.3 0.1-0.8 0.4-0.3 0.1-0.6 0.5-0.2 0.7 0.8 1.6-0.4 0.7-0.6 0.3-0.7-0.4-1.1-1.4-0.2-1 0.3 0.2 0.5-0.3 0.6-0.6 0.3-0.5-0.2-0.1-0.9-1-0.6-0.2-1.4 0.4-0.9-0.1-0.5 0.3-0.8-0.3-0.8 0.5-0.8 0.6 0 1.2-3.2 0.8-0.8 0.5-1.2-1.3-1.3-0.4 0.2-0.2-0.8 0.7-0.2 0.2-0.5-0.1-0.8-0.7-1.8-1.3-2.4-0.3-0.8 0-0.7 0.9-0.4-0.2-0.6 0.4-1.3-0.1-0.4 1.1-1.2 0.1-0.6 0.6 0.1 0.9-0.9 0.6-0.4 0.8-0.1 0.1-1.1 0.8 0.7 1.7-0.8 0.6-0.5-0.1-0.6 0.7-0.4 0.7-0.8-0.2-0.4 1-0.2 0.2-0.4 0.6-0.1 0.9 0.4 1 0.8 0.7-0.3 0.8-0.1 0.5-0.5 0.4-0.2 1-0.6 0.8 0 0.2 0.4 0.8-0.5 0.2-1 0.5-0.1 0.7 1 0.9-0.8 0.3-0.9 0.8-0.5 0.3-1.5-0.1-0.5 0.8 0 0.4-0.7-0.1-0.6 0.2-0.3-0.7-0.7-0.1-1.1-0.4-0.5 0.1-0.1 0.4 0 0.1 0 0 0.1 0.9 0.3 1 0.2 0.2-0.1 0.3 0.1 1.1-0.3-0.1 0.5 0.6 0.2 0 0.7 0.4 0.3 0-0.1 0.1 0 0 0.1 0.3-0.1 0.4-0.5 0.3-1.6 1-0.8 0.7-0.3 1.6-0.2 1-0.4 0.7-0.6 0.9-1.7 1-0.1 0.7-0.4 0.5-0.7 0.4-0.9 0.2-0.3-0.2 0.2-0.1 0.6 0.1 0.7 1.6 1.6 0.6 0.8-0.3 0.4-1.4 0.5-0.8 0.6-2.2 2.5-0.3 0.5-0.5 0.4-1.6 0.3-0.7 0.3-0.8 0.7 0 1.6 0.9 0.2 0 1-1.2 1.5-0.9 0.6 0.6 0.5 1.4-0.3 1.1 0.5 0.5 0.8 0.7-0.1 1.1 1.2 0.6 0.4 1.1 0.2 0.8 0.5 0.8 0.6 1.5 3.2 0.1 0.8-0.9 0.2 0.4 1.1 0.3 0.1 0.8 2.5 0.8 1.3-0.1 0.5 0.6 1 0.6 0.4-0.1 0.4 1.2 1.7 0.6 1.4 0.6 1.6 0.4 0.4 0.4 0.9-0.7 0.7 0.2 1 0.7 0.3 0.2 1.1 0.9 0.1 0.5-0.3 0.9 0 0.1 0.3 0.8-0.3 0.3-0.4-0.1-0.5 0.9-0.1 0-1.1-0.4-0.7 0.9-0.1 0.3-0.3 0.1-0.9 0.7 1.4 0.1 0.6 1 1.8 0.5 0.6 0.5-0.2 0.6 1.2 0.6-0.3 1 0.9 0.7-0.8 0.8 0.9-0.7 0.8 0.3 0.3 0.8-0.4 0.6 0.5-0.7 0.4-0.6 0.6 0 0.8 0.3 0.2-0.3 0.8 1 0.5-0.6 1.1-0.1 0.7 0.4 1.1-0.1 0.5 1.1 0.7 0.5 0.4 0.7-0.1 0.5 0.3 0.3 0.8 0.7 0.3 0.5 0.7-0.2 0.5 0.2 0.5 0.9 1.1 0.7 0.3 0.2 0.9 0.3 0.4-0.5 0.9z";

    // Initial opacity for reduced-motion static render
    const initOpacity = isReducedMotion ? "1" : "0";

    container.innerHTML = `
      <div class="pps-preloader-stage">
        <div class="pps-svg-container" id="pps-art-stage">
          <svg class="pps-svg-main" viewBox="0 0 420 370" xmlns="http://www.w3.org/2000/svg">
            <defs>
              <filter id="pps-pulse-glow" x="-30%" y="-30%" width="160%" height="160%">
                <feGaussianBlur stdDeviation="8" result="blur" />
                <feComposite in="SourceGraphic" in2="blur" operator="over" />
              </filter>
            </defs>

            <!-- LAYER 1: 5 STAGGERED ATHLETIC MOTION LINES (Behind the Punjab Map) -->
            <!-- Emerging from underneath the lower edge of Punjab, fanning outward with staggered race-start endpoints -->
            <g id="pps-motion-lines-group">
              <!-- Lane 1 (Lead lane, Primary Blue, reaches furthest north to apex) -->
              <path id="pps-lane-1" class="pps-motion-lane"
                d="M 122,245 A 132,132 0 0,1 215,58"
                fill="none"
                stroke="#0D47A1"
                stroke-width="4.5"
                stroke-dasharray="245"
                stroke-dashoffset="${isReducedMotion ? '0' : '245'}"
                opacity="${isReducedMotion ? '0.95' : '0'}"
              />

              <!-- Lane 2 (Secondary lane, Brand Yellow, staggered offset behind Lane 1) -->
              <path id="pps-lane-2" class="pps-motion-lane"
                d="M 108,258 A 144,144 0 0,1 198,75"
                fill="none"
                stroke="#FFC107"
                stroke-width="3.8"
                stroke-dasharray="235"
                stroke-dashoffset="${isReducedMotion ? '0' : '235'}"
                opacity="${isReducedMotion ? '0.9' : '0'}"
              />

              <!-- Lane 3 (Third lane, Primary Blue, staggered offset behind Lane 2) -->
              <path id="pps-lane-3" class="pps-motion-lane"
                d="M 94,272 A 156,156 0 0,1 180,95"
                fill="none"
                stroke="#0D47A1"
                stroke-width="4.2"
                stroke-dasharray="220"
                stroke-dashoffset="${isReducedMotion ? '0' : '220'}"
                opacity="${isReducedMotion ? '0.95' : '0'}"
              />

              <!-- Lane 4 (Fourth lane, Light Blue, staggered offset behind Lane 3) -->
              <path id="pps-lane-4" class="pps-motion-lane"
                d="M 80,285 A 168,168 0 0,1 160,118"
                fill="none"
                stroke="#4FC3F7"
                stroke-width="3.2"
                stroke-dasharray="200"
                stroke-dashoffset="${isReducedMotion ? '0' : '200'}"
                opacity="${isReducedMotion ? '0.85' : '0'}"
              />

              <!-- Lane 5 (Trailing speed lane, Brand Yellow, lower flank) -->
              <path id="pps-lane-5" class="pps-motion-lane"
                d="M 68,296 A 180,180 0 0,1 140,145"
                fill="none"
                stroke="#FFC107"
                stroke-width="2.8"
                stroke-dasharray="175"
                stroke-dashoffset="${isReducedMotion ? '0' : '175'}"
                opacity="${isReducedMotion ? '0.8' : '0'}"
              />
            </g>

            <!-- LAYER 2: PUNJAB STATE MAP (Opaque Solid Yellow, Sits on Top of Lines) -->
            <g id="pps-punjab-map-group" transform="translate(195, 160) scale(2.82) translate(-297.4, -244.5)">
              <circle id="pps-map-pulse" cx="297.4" cy="244.5" r="9" fill="#FFC107" opacity="0" filter="url(#pps-pulse-glow)" />
              <path id="pps-punjab-map" 
                d="${punjabGeoPath}" 
                fill="#FFC107" 
                fill-opacity="${isReducedMotion ? '1' : '0'}"
                stroke="#EAB308" 
                stroke-width="1.8" 
                stroke-linecap="round" 
                stroke-linejoin="round"
                stroke-dasharray="750"
                stroke-dashoffset="${isReducedMotion ? '0' : '750'}"
              />
            </g>

            <!-- LAYER 3: WHEELCHAIR PARA-ATHLETE IN COMPETITIVE FORWARD-RACING POSTURE -->
            <!-- Completely removed all ball and throwing mechanics -->
            <g id="pps-athlete-group" opacity="${initOpacity}">
              
              <!-- Wheelchair Rear Wheel (Diameter 84px = 1.5x torso height) -->
              <g id="pps-wheel-group">
                <circle id="pps-wheel-rim" 
                  cx="170" 
                  cy="225" 
                  r="42" 
                  fill="none" 
                  stroke="#0D47A1" 
                  stroke-width="5" 
                  stroke-linecap="round"
                  stroke-dasharray="264"
                  stroke-dashoffset="${isReducedMotion ? '0' : '264'}"
                />
                <circle id="pps-wheel-hub" cx="170" cy="225" r="5.5" fill="#0D47A1" opacity="${initOpacity}" />
              </g>

              <!-- Wheelchair Frame, Seat & Front Caster -->
              <g id="pps-frame-group">
                <!-- Seat horizontal tube -->
                <path id="pps-frame-seat" 
                  d="M 166,205 L 212,205" 
                  fill="none" 
                  stroke="#0D47A1" 
                  stroke-width="4.5" 
                  stroke-linecap="round"
                  stroke-dasharray="50"
                  stroke-dashoffset="${isReducedMotion ? '0' : '50'}"
                />
                <!-- Backrest tube -->
                <path id="pps-frame-backrest" 
                  d="M 170,205 L 158,172" 
                  fill="none" 
                  stroke="#0D47A1" 
                  stroke-width="4.5" 
                  stroke-linecap="round"
                  stroke-dasharray="38"
                  stroke-dashoffset="${isReducedMotion ? '0' : '38'}"
                />
                <!-- Footrest downtube -->
                <path id="pps-frame-footrest" 
                  d="M 212,205 L 236,250" 
                  fill="none" 
                  stroke="#0D47A1" 
                  stroke-width="4" 
                  stroke-linecap="round"
                  stroke-dasharray="55"
                  stroke-dashoffset="${isReducedMotion ? '0' : '55'}"
                />
                <!-- Front Caster Wheel -->
                <circle id="pps-caster-wheel" 
                  cx="236" 
                  cy="256" 
                  r="9.5" 
                  fill="none" 
                  stroke="#0D47A1" 
                  stroke-width="3.5"
                  opacity="${initOpacity}"
                />
                <circle id="pps-caster-hub" cx="236" cy="256" r="3" fill="#0D47A1" opacity="${initOpacity}" />
              </g>

              <!-- Lower Body / Legs Resting on Chair -->
              <g id="pps-lowerbody-group">
                <path id="pps-legs-path" 
                  d="M 178,198 L 216,198 L 230,242" 
                  fill="none" 
                  stroke="#0D47A1" 
                  stroke-width="5" 
                  stroke-linecap="round" 
                  stroke-linejoin="round"
                  stroke-dasharray="90"
                  stroke-dashoffset="${isReducedMotion ? '0' : '90'}"
                />
              </g>

              <!-- Torso (Strong forward-leaning athletic racing posture, 31° lean) -->
              <g id="pps-torso-group">
                <path id="pps-athlete-torso" 
                  d="M 178,198 L 212,142" 
                  fill="none" 
                  stroke="#0D47A1" 
                  stroke-width="6" 
                  stroke-linecap="round"
                  stroke-dasharray="66"
                  stroke-dashoffset="${isReducedMotion ? '0' : '66'}"
                />
              </g>

              <!-- Head (Solid circular dot, 16px clean negative space gap above torso) -->
              <g id="pps-head-group">
                <circle id="pps-athlete-head" 
                  cx="222" 
                  cy="118" 
                  r="9" 
                  fill="#0D47A1" 
                  opacity="${initOpacity}"
                  style="transform-origin: 222px 118px;"
                />
              </g>

              <!-- Forward-Surging Competitive Drive Arms (Dynamic propulsion & momentum) -->
              <g id="pps-arm-group">
                <path id="pps-athlete-arm-drive" 
                  d="M 212,142 L 248,154 L 282,144" 
                  fill="none" 
                  stroke="#0D47A1" 
                  stroke-width="5.5" 
                  stroke-linecap="round" 
                  stroke-linejoin="round"
                  stroke-dasharray="75"
                  stroke-dashoffset="${isReducedMotion ? '0' : '75'}"
                />
              </g>

            </g>
          </svg>
        </div>

        <!-- LAYER 4: TYPOGRAPHY LOCKUP -->
        <div class="pps-brand-text ${isReducedMotion ? 'pps-active' : ''}" id="pps-brand-text">
          <h1 class="pps-logo-title">
            <span class="pps-blue-text">PUNJAB</span> 
            <span class="pps-yellow-text">PARA SPORTS</span>
          </h1>
          <div class="pps-tagline-container">
            <span class="pps-tagline-line"></span>
            <span class="pps-tagline">STRONGER TOGETHER</span>
            <span class="pps-tagline-line"></span>
          </div>
        </div>
      </div>
    `;
  }

  startAnimationSequence() {
    const totalMs = this.options.duration * 1000;

    const punjabMap = document.getElementById('pps-punjab-map');
    const mapPulse = document.getElementById('pps-map-pulse');

    // 5 Staggered Motion Lanes
    const l1 = document.getElementById('pps-lane-1');
    const l2 = document.getElementById('pps-lane-2');
    const l3 = document.getElementById('pps-lane-3');
    const l4 = document.getElementById('pps-lane-4');
    const l5 = document.getElementById('pps-lane-5');

    // Athlete Element Groups
    const athleteGroup = document.getElementById('pps-athlete-group');
    const wheelRim = document.getElementById('pps-wheel-rim');
    const wheelHub = document.getElementById('pps-wheel-hub');
    const frameSeat = document.getElementById('pps-frame-seat');
    const frameBackrest = document.getElementById('pps-frame-backrest');
    const frameFootrest = document.getElementById('pps-frame-footrest');
    const casterWheel = document.getElementById('pps-caster-wheel');
    const casterHub = document.getElementById('pps-caster-hub');
    const legsPath = document.getElementById('pps-legs-path');
    const athleteTorso = document.getElementById('pps-athlete-torso');
    const athleteHead = document.getElementById('pps-athlete-head');
    const armDrive = document.getElementById('pps-athlete-arm-drive');

    const artStage = document.getElementById('pps-art-stage');
    const brandText = document.getElementById('pps-brand-text');

    // PHASE 1 & 2: Punjab map outline draws in and fills with yellow (0.00 – 0.65s)
    punjabMap.animate([
      { strokeDashoffset: '750', stroke: '#CBD5E1', strokeWidth: '1.2' },
      { strokeDashoffset: '0', stroke: '#EAB308', strokeWidth: '1.8' }
    ], { duration: totalMs * 0.13, easing: 'ease-out', fill: 'forwards' });

    setTimeout(() => {
      if (this.isCompleted) return;
      mapPulse.animate([
        { opacity: 0, transform: 'scale(0.3)' },
        { opacity: 0.9, transform: 'scale(2.4)' },
        { opacity: 0, transform: 'scale(3.4)' }
      ], { duration: totalMs * 0.22, easing: 'ease-out', fill: 'forwards' });

      punjabMap.animate([
        { fillOpacity: 0, stroke: '#EAB308' },
        { fillOpacity: 0.5, stroke: '#FFC107' },
        { fillOpacity: 1, stroke: '#FFC107' }
      ], { duration: totalMs * 0.18, easing: 'ease-in-out', fill: 'forwards' });
    }, totalMs * 0.11);

    // PHASE 3, 4, 5: 5 Staggered Motion Lanes emerge and fan outward (0.45 – 1.15s)
    // Staggered race-start progression: Lane 1 -> +80ms Lane 2 -> +160ms Lane 3 -> +240ms Lane 4 -> +320ms Lane 5
    setTimeout(() => {
      if (this.isCompleted) return;

      // Lane 1: 0ms -> 600ms
      l1.animate([
        { strokeDashoffset: 245, opacity: 0 },
        { strokeDashoffset: 0, opacity: 0.95 }
      ], { duration: 600, easing: 'cubic-bezier(0.22, 1, 0.36, 1)', fill: 'forwards' });

      // Lane 2: +80ms -> 600ms
      setTimeout(() => {
        if (this.isCompleted) return;
        l2.animate([
          { strokeDashoffset: 235, opacity: 0 },
          { strokeDashoffset: 0, opacity: 0.9 }
        ], { duration: 600, easing: 'cubic-bezier(0.22, 1, 0.36, 1)', fill: 'forwards' });
      }, 80);

      // Lane 3: +160ms -> 580ms
      setTimeout(() => {
        if (this.isCompleted) return;
        l3.animate([
          { strokeDashoffset: 220, opacity: 0 },
          { strokeDashoffset: 0, opacity: 0.95 }
        ], { duration: 580, easing: 'cubic-bezier(0.22, 1, 0.36, 1)', fill: 'forwards' });
      }, 160);

      // Lane 4: +240ms -> 550ms
      setTimeout(() => {
        if (this.isCompleted) return;
        l4.animate([
          { strokeDashoffset: 200, opacity: 0 },
          { strokeDashoffset: 0, opacity: 0.85 }
        ], { duration: 550, easing: 'cubic-bezier(0.22, 1, 0.36, 1)', fill: 'forwards' });
      }, 240);

      // Lane 5: +320ms -> 520ms
      setTimeout(() => {
        if (this.isCompleted) return;
        l5.animate([
          { strokeDashoffset: 175, opacity: 0 },
          { strokeDashoffset: 0, opacity: 0.8 }
        ], { duration: 520, easing: 'cubic-bezier(0.22, 1, 0.36, 1)', fill: 'forwards' });
      }, 320);
    }, totalMs * 0.17);

    // PHASE 6: Wheelchair athlete assembles cleanly over Punjab map (0.70 – 1.25s)
    setTimeout(() => {
      if (this.isCompleted) return;
      athleteGroup.style.opacity = '1';

      // 1. Wheel draws in
      wheelRim.animate([
        { strokeDashoffset: 264 },
        { strokeDashoffset: 0 }
      ], { duration: totalMs * 0.18, easing: 'cubic-bezier(0.25, 1, 0.5, 1)', fill: 'forwards' });

      wheelHub.animate([
        { opacity: 0, transform: 'scale(0)' },
        { opacity: 1, transform: 'scale(1)' }
      ], { duration: totalMs * 0.10, fill: 'forwards' });

      // 2. Frame & Caster
      frameSeat.animate([{ strokeDashoffset: 50 }, { strokeDashoffset: 0 }], { duration: totalMs * 0.12, fill: 'forwards' });
      frameBackrest.animate([{ strokeDashoffset: 38 }, { strokeDashoffset: 0 }], { duration: totalMs * 0.12, fill: 'forwards' });
      frameFootrest.animate([{ strokeDashoffset: 55 }, { strokeDashoffset: 0 }], { duration: totalMs * 0.14, fill: 'forwards' });
      casterWheel.animate([{ opacity: 0 }, { opacity: 1 }], { duration: totalMs * 0.10, fill: 'forwards' });
      casterHub.animate([{ opacity: 0 }, { opacity: 1 }], { duration: totalMs * 0.10, fill: 'forwards' });

      // 3. Legs
      legsPath.animate([
        { strokeDashoffset: 90 },
        { strokeDashoffset: 0 }
      ], { duration: totalMs * 0.14, easing: 'ease-out', fill: 'forwards' });

      // 4. Torso
      athleteTorso.animate([
        { strokeDashoffset: 66 },
        { strokeDashoffset: 0 }
      ], { duration: totalMs * 0.15, easing: 'ease-out', fill: 'forwards' });

      // 5. Head
      athleteHead.animate([
        { opacity: 0, transform: 'scale(0.3)' },
        { opacity: 1, transform: 'scale(1)' }
      ], { duration: totalMs * 0.12, easing: 'cubic-bezier(0.175, 0.885, 0.32, 1.275)', fill: 'forwards' });

      // 6. Driving arm
      armDrive.animate([
        { strokeDashoffset: 75 },
        { strokeDashoffset: 0 }
      ], { duration: totalMs * 0.16, easing: 'ease-out', fill: 'forwards' });
    }, totalMs * 0.26);

    // PHASE 7: Athlete settles into position with subtle forward racing momentum (1.25 – 1.50s)
    setTimeout(() => {
      if (this.isCompleted) return;
      athleteGroup.animate([
        { transform: 'translate(0px, 0px)' },
        { transform: 'translate(2px, -1px)' },
        { transform: 'translate(0px, 0px)' }
      ], { duration: totalMs * 0.16, easing: 'ease-in-out' });
    }, totalMs * 0.46);

    // PHASE 8: Composition settles into official lockup (1.80 – 2.10s)
    setTimeout(() => {
      if (this.isCompleted) return;
      artStage.animate([
        { transform: 'scale(1.02)' },
        { transform: 'scale(1)' }
      ], { duration: totalMs * 0.14, easing: 'cubic-bezier(0.25, 1, 0.5, 1)', fill: 'forwards' });
    }, totalMs * 0.67);

    // PHASE 9: Typography lockup appears ("PUNJAB PARA SPORTS" + "STRONGER TOGETHER")
    setTimeout(() => {
      if (this.isCompleted) return;
      brandText.classList.add('pps-active');
    }, totalMs * 0.77);

    // PHASE 10: Hold briefly and fade into website (2.70s+)
    this.timerId = setTimeout(() => {
      this.complete();
    }, totalMs);
  }

  finishImmediately() {
    if (this.isCompleted) return;
    if (this.timerId) clearTimeout(this.timerId);

    const overlay = document.getElementById(this.options.containerId);
    if (overlay) {
      overlay.classList.add('pps-hiding');
      setTimeout(() => {
        overlay.classList.add('pps-hidden');
      }, 300);
    }

    if (this.options.useSessionStorage) {
      sessionStorage.setItem(this.options.storageKey, 'true');
    }

    this.isCompleted = true;
    if (typeof this.options.onComplete === 'function') {
      this.options.onComplete();
    }
  }

  complete() {
    if (this.isCompleted) return;
    const overlay = document.getElementById(this.options.containerId);
    if (overlay) {
      overlay.classList.add('pps-hiding');
      setTimeout(() => {
        overlay.classList.add('pps-hidden');
      }, 500);
    }

    if (this.options.useSessionStorage) {
      sessionStorage.setItem(this.options.storageKey, 'true');
    }

    this.isCompleted = true;
    if (typeof this.options.onComplete === 'function') {
      this.options.onComplete();
    }
  }
}

if (typeof window !== 'undefined') {
  window.PunjabParaSportsPreloader = PunjabParaSportsPreloader;
}
