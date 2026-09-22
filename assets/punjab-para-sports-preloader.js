/**
 * Punjab Para Sports Preloader Animation Component
 * Redrawn Wheelchair Para-Athlete Pictogram Architecture:
 * - Clean, non-overlapping Paralympic sports pictogram
 * - Unmistakable large rear wheelchair wheel (1.5x torso height) with visible hub
 * - Distinct wheelchair frame, seat, and front caster
 * - Natural lower body / leg indication resting on wheelchair
 * - Strong, forward-leaning athletic torso stroke (31° lean)
 * - Distinct circular head with 15px negative space clearance above torso
 * - Two-segment throwing arm originating from shoulder, pointing directly toward ball
 * - 50px whitespace between hand and sports ball with curved trajectory
 * - Motion curves positioned cleanly behind/around athlete with 20px+ clearance
 * - 11-Phase sequential construction animation
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
          
          <!-- LAYER 1: PUNJAB STATE MAP -->
          <svg class="pps-svg-main" viewBox="0 0 420 370" xmlns="http://www.w3.org/2000/svg" style="position: absolute; top: 0; left: 0; z-index: 1;">
            <defs>
              <filter id="pps-pulse-glow" x="-30%" y="-30%" width="160%" height="160%">
                <feGaussianBlur stdDeviation="8" result="blur" />
                <feComposite in="SourceGraphic" in2="blur" operator="over" />
              </filter>
            </defs>

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
          </svg>

          <!-- LAYER 2: DECORATIVE MOTION CURVES (Anchored at x = 25.7%, y = 30.9%) -->
          <div class="pps-motion-curves-container">
            <svg viewBox="0 0 420 370" xmlns="http://www.w3.org/2000/svg" style="width: 100%; height: 100%; overflow: visible;">
              <!-- Curve 1: Yellow Outer Energy Wave (Balanced length ~260px) -->
              <path id="pps-curve-1" class="pps-motion-stroke"
                d="M 105,260 A 148,148 0 0,1 190,52" 
                fill="none" 
                stroke="#FFC107" 
                stroke-width="3.5" 
                stroke-dasharray="260"
                stroke-dashoffset="${isReducedMotion ? '0' : '260'}"
                opacity="${isReducedMotion ? '0.9' : '0'}"
              />

              <!-- Curve 2: Primary Blue Main Wave (Concentric offset, length ~235px) -->
              <path id="pps-curve-2" class="pps-motion-stroke"
                d="M 118,245 A 134,134 0 0,1 198,68" 
                fill="none" 
                stroke="#0D47A1" 
                stroke-width="4.2" 
                stroke-dasharray="235"
                stroke-dashoffset="${isReducedMotion ? '0' : '235'}"
                opacity="${isReducedMotion ? '0.95' : '0'}"
              />

              <!-- Curve 3: Light Blue Accent Wave (Concentric offset, length ~180px) -->
              <path id="pps-curve-3" class="pps-motion-stroke"
                d="M 128,232 A 120,120 0 0,1 180,98" 
                fill="none" 
                stroke="#4FC3F7" 
                stroke-width="3.2" 
                stroke-dasharray="180"
                stroke-dashoffset="${isReducedMotion ? '0' : '180'}"
                opacity="${isReducedMotion ? '0.85' : '0'}"
              />
            </svg>
          </div>

          <!-- LAYER 3: REDRAWN WHEELCHAIR PARA-ATHLETE PICTOGRAM (Non-Overlapping Distinct Components) -->
          <svg class="pps-svg-main" viewBox="0 0 420 370" xmlns="http://www.w3.org/2000/svg" style="position: absolute; top: 0; left: 0; z-index: 3; pointer-events: none;">
            
            <!-- Group 1: Large Rear Wheelchair Wheel (Diameter: 84px = 1.5x torso height) -->
            <g id="pps-wheel-group" opacity="${initOpacity}">
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

            <!-- Group 2: Wheelchair Frame, Seat & Front Caster -->
            <g id="pps-frame-group" opacity="${initOpacity}">
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

            <!-- Group 3: Lower Body / Legs Resting in Chair -->
            <g id="pps-lowerbody-group" opacity="${initOpacity}">
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

            <!-- Group 4: Athlete Torso (Strong forward-leaning athletic posture, 31° lean) -->
            <g id="pps-torso-group" opacity="${initOpacity}">
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

            <!-- Group 5: Athlete Head (Solid circular dot, 15px clean negative space above torso) -->
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

            <!-- Group 6: Two-Segment Throwing Arm (Originating from shoulder, reaching toward ball) -->
            <g id="pps-arm-group" opacity="${initOpacity}">
              <path id="pps-athlete-arm" 
                d="M 212,142 L 252,126 L 296,106" 
                fill="none" 
                stroke="#0D47A1" 
                stroke-width="5.5" 
                stroke-linecap="round" 
                stroke-linejoin="round"
                stroke-dasharray="100"
                stroke-dashoffset="${isReducedMotion ? '0' : '100'}"
              />
            </g>

            <!-- Group 7: Ball Trajectory (Dashed path beginning near hand) -->
            <g id="pps-trajectory-group">
              <path id="pps-ball-trajectory" 
                d="M 304,102 Q 324,91 336,87" 
                fill="none" 
                stroke="#0D47A1" 
                stroke-width="2.5" 
                stroke-dasharray="3 4"
                opacity="${isReducedMotion ? '0.85' : '0'}"
              />
            </g>

            <!-- Group 8: Sports Ball (Solid circular blue ball) -->
            <g id="pps-ball-group">
              <circle id="pps-sports-ball" 
                cx="346" 
                cy="84" 
                r="9.5" 
                fill="#0D47A1" 
                opacity="${initOpacity}"
                style="transform-origin: 346px 84px;"
              />
            </g>
          </svg>

        </div>

        <!-- LAYER 6: FINAL LOGO LOCKUP TYPOGRAPHY -->
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
    
    // Motion curves
    const c1 = document.getElementById('pps-curve-1');
    const c2 = document.getElementById('pps-curve-2');
    const c3 = document.getElementById('pps-curve-3');

    // Athlete Element Groups
    const wheelGroup = document.getElementById('pps-wheel-group');
    const wheelRim = document.getElementById('pps-wheel-rim');
    const wheelHub = document.getElementById('pps-wheel-hub');

    const frameGroup = document.getElementById('pps-frame-group');
    const frameSeat = document.getElementById('pps-frame-seat');
    const frameBackrest = document.getElementById('pps-frame-backrest');
    const frameFootrest = document.getElementById('pps-frame-footrest');
    const casterWheel = document.getElementById('pps-caster-wheel');
    const casterHub = document.getElementById('pps-caster-hub');

    const lowerBodyGroup = document.getElementById('pps-lowerbody-group');
    const legsPath = document.getElementById('pps-legs-path');

    const torsoGroup = document.getElementById('pps-torso-group');
    const athleteTorso = document.getElementById('pps-athlete-torso');

    const athleteHead = document.getElementById('pps-athlete-head');

    const armGroup = document.getElementById('pps-arm-group');
    const athleteArm = document.getElementById('pps-athlete-arm');

    const ballTrail = document.getElementById('pps-ball-trajectory');
    const sportsBall = document.getElementById('pps-sports-ball');

    const artStage = document.getElementById('pps-art-stage');
    const brandText = document.getElementById('pps-brand-text');

    // PHASE 1: Punjab map outline draws in (0.00 – 0.35s)
    punjabMap.animate([
      { strokeDashoffset: '750', stroke: '#CBD5E1', strokeWidth: '1.2' },
      { strokeDashoffset: '0', stroke: '#EAB308', strokeWidth: '1.8' }
    ], { duration: totalMs * 0.13, easing: 'ease-out', fill: 'forwards' });

    // PHASE 2: Yellow fill spreads across Punjab map (0.30 – 0.70s)
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

    // PHASE 3: Large Wheelchair wheel draws in (0.50 – 0.85s)
    setTimeout(() => {
      if (this.isCompleted) return;
      wheelGroup.style.opacity = '1';
      wheelRim.animate([
        { strokeDashoffset: 264 },
        { strokeDashoffset: 0 }
      ], { duration: totalMs * 0.18, easing: 'cubic-bezier(0.25, 1, 0.5, 1)', fill: 'forwards' });

      wheelHub.animate([
        { opacity: 0, transform: 'scale(0)' },
        { opacity: 1, transform: 'scale(1)' }
      ], { duration: totalMs * 0.10, fill: 'forwards' });
    }, totalMs * 0.19);

    // PHASE 4: Wheelchair Frame, Seat & Caster appear (0.65 – 0.95s)
    setTimeout(() => {
      if (this.isCompleted) return;
      frameGroup.style.opacity = '1';
      frameSeat.animate([{ strokeDashoffset: 50 }, { strokeDashoffset: 0 }], { duration: totalMs * 0.12, fill: 'forwards' });
      frameBackrest.animate([{ strokeDashoffset: 38 }, { strokeDashoffset: 0 }], { duration: totalMs * 0.12, fill: 'forwards' });
      frameFootrest.animate([{ strokeDashoffset: 55 }, { strokeDashoffset: 0 }], { duration: totalMs * 0.14, fill: 'forwards' });
      casterWheel.animate([{ opacity: 0 }, { opacity: 1 }], { duration: totalMs * 0.10, fill: 'forwards' });
      casterHub.animate([{ opacity: 0 }, { opacity: 1 }], { duration: totalMs * 0.10, fill: 'forwards' });
    }, totalMs * 0.24);

    // PHASE 5: Lower body / legs appear (0.75 – 1.00s)
    setTimeout(() => {
      if (this.isCompleted) return;
      lowerBodyGroup.style.opacity = '1';
      legsPath.animate([
        { strokeDashoffset: 90 },
        { strokeDashoffset: 0 }
      ], { duration: totalMs * 0.14, easing: 'ease-out', fill: 'forwards' });
    }, totalMs * 0.28);

    // PHASE 6: Torso draws upward from wheelchair (0.85 – 1.10s)
    setTimeout(() => {
      if (this.isCompleted) return;
      torsoGroup.style.opacity = '1';
      athleteTorso.animate([
        { strokeDashoffset: 66 },
        { strokeDashoffset: 0 }
      ], { duration: totalMs * 0.15, easing: 'ease-out', fill: 'forwards' });
    }, totalMs * 0.32);

    // PHASE 7: Head appears with clean pop/scale (0.95 – 1.18s)
    setTimeout(() => {
      if (this.isCompleted) return;
      athleteHead.animate([
        { opacity: 0, transform: 'scale(0.3)' },
        { opacity: 1, transform: 'scale(1)' }
      ], { duration: totalMs * 0.12, easing: 'cubic-bezier(0.175, 0.885, 0.32, 1.275)', fill: 'forwards' });
    }, totalMs * 0.36);

    // PHASE 8: Two-segment Throwing Arm extends outward (1.05 – 1.35s)
    setTimeout(() => {
      if (this.isCompleted) return;
      armGroup.style.opacity = '1';
      athleteArm.animate([
        { strokeDashoffset: 100 },
        { strokeDashoffset: 0 }
      ], { duration: totalMs * 0.18, easing: 'ease-out', fill: 'forwards' });
    }, totalMs * 0.40);

    // PHASE 9: Motion curves sweep around athlete (1.15 – 1.65s)
    setTimeout(() => {
      if (this.isCompleted) return;

      c1.animate([
        { strokeDashoffset: 260, opacity: 0 },
        { strokeDashoffset: 0, opacity: 0.9 }
      ], { duration: 550, easing: 'cubic-bezier(0.22, 1, 0.36, 1)', fill: 'forwards' });

      setTimeout(() => {
        if (this.isCompleted) return;
        c2.animate([
          { strokeDashoffset: 235, opacity: 0 },
          { strokeDashoffset: 0, opacity: 0.95 }
        ], { duration: 520, easing: 'cubic-bezier(0.22, 1, 0.36, 1)', fill: 'forwards' });
      }, 100);

      setTimeout(() => {
        if (this.isCompleted) return;
        c3.animate([
          { strokeDashoffset: 180, opacity: 0 },
          { strokeDashoffset: 0, opacity: 0.85 }
        ], { duration: 500, easing: 'cubic-bezier(0.22, 1, 0.36, 1)', fill: 'forwards' });
      }, 180);
    }, totalMs * 0.43);

    // Arm micro-movement throw
    setTimeout(() => {
      if (this.isCompleted) return;
      athleteArm.animate([
        { transform: 'rotate(0deg)', transformOrigin: '212px 142px' },
        { transform: 'rotate(-4deg)', transformOrigin: '212px 142px' },
        { transform: 'rotate(0deg)', transformOrigin: '212px 142px' }
      ], { duration: totalMs * 0.14, easing: 'ease-in-out' });
    }, totalMs * 0.50);

    // PHASE 10: Ball trajectory & Sports ball moves to endpoint (1.40 – 1.85s)
    setTimeout(() => {
      if (this.isCompleted) return;

      ballTrail.animate([
        { opacity: 0 },
        { opacity: 0.85 }
      ], { duration: totalMs * 0.10, fill: 'forwards' });

      sportsBall.animate([
        { opacity: 0, transform: 'translate(-42px, 16px) scale(0.4)' },
        { opacity: 1, transform: 'translate(-10px, -4px) scale(1.15)' },
        { opacity: 1, transform: 'translate(0px, 0px) scale(1)' }
      ], { duration: totalMs * 0.18, easing: 'cubic-bezier(0.25, 0.46, 0.45, 0.94)', fill: 'forwards' });
    }, totalMs * 0.52);

    // PHASE 11: Composition settles into official lockup (1.85 – 2.15s)
    setTimeout(() => {
      if (this.isCompleted) return;
      artStage.animate([
        { transform: 'scale(1.02)' },
        { transform: 'scale(1)' }
      ], { duration: totalMs * 0.14, easing: 'cubic-bezier(0.25, 1, 0.5, 1)', fill: 'forwards' });
    }, totalMs * 0.68);

    // PHASE 12: Typography lockup appears ("PUNJAB PARA SPORTS" + "STRONGER TOGETHER")
    setTimeout(() => {
      if (this.isCompleted) return;
      brandText.classList.add('pps-active');
    }, totalMs * 0.78);

    // Hold briefly and fade into website (2.70s+)
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
