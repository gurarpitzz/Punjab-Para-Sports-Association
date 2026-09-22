# Punjab Para Sports Website — Preloader Animation

A minimal, lightweight, GPU-accelerated vector preloader animation component for the Punjab Para Sports Association web portal.

## Corrective Visual Architecture
- **Universal Multi-Sport Concept**: Completely removed all ball and throwing mechanics. Represents multiple para-sports across Punjab (movement, competition, racing lanes, inclusion, forward momentum).
- **4 Concentric Athletic Racing Lanes / Motion Arcs**:
  - Positioned **AROUND THE MAP** on the white canvas, **NEVER ON THE MAP** (10px–36px clear whitespace clearance).
  - Sweeping smoothly from underneath the southern tip of Punjab, curving around the bottom-left corner and western flank, and reaching up to the northwestern shoulder.
  - Cohesive athletic blue color palette (`#0D47A1`, `#1565C0`, `#1976D2`, `#0288D1`) with staggered starting points and endpoints.
- **Redrawn Paralympic Athlete Pictogram**:
  - **Large Rear Wheel**: Unmistakable circular wheel (diameter: 84px = 1.5x torso height) with visible central hub (`stroke-width: 5px`).
  - **Wheelchair Frame & Seat**: Clean seat tube, backrest, and footrest downtube with front caster wheel.
  - **Lower Body / Legs**: Clean pictogram stroke resting naturally on wheelchair seat.
  - **Torso**: Single strong stroke (`6px`) leaning forward at 31° in a strong competitive forward-racing posture.
  - **Head**: Solid blue circular dot with **16px clear negative space gap** above the torso.
  - **Driving Arm**: Powerful forward propulsion stroke driving the wheel/momentum forward.
- **Punjab Map**: Authentic state silhouette in official brand yellow (`#FFC107`).
- **Typography**: `PUNJAB` in brand blue (`#0D47A1`), `PARA SPORTS` in brand yellow/amber, with `— STRONGER TOGETHER —` tagline.
- **Accessibility**: Full `prefers-reduced-motion` compliance.

## Project Structure

```
punjab-para-sports/
├── assets/
│   ├── punjab-para-sports-preloader.js     # Standalone preloader JS class
│   └── punjab-para-sports-preloader.css    # Fullscreen overlay & animation styles
├── index.html                              # Interactive showcase demo & landing preview
└── README.md                               # Project documentation
```

## Running the Demo

Server command:
```bash
python -m http.server 8080
```
Open [http://localhost:8080/](http://localhost:8080/) in your web browser.
