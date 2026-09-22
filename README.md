# Punjab Para Sports Website — Preloader Animation

A minimal, lightweight, GPU-accelerated vector preloader animation component for the Punjab Para Sports Association web portal.

## Corrective Visual Architecture
- **Universal Multi-Sport Concept**: Completely removed all ball and throwing mechanics. Represents multiple para-sports across Punjab (movement, competition, racing lanes, inclusion, forward momentum).
- **3 Concentric Athletic Racing Lanes / Motion Arcs**:
  - Positioned **AROUND THE MAP** on the white canvas, **NEVER ON THE MAP** (10px–32px clear whitespace clearance).
  - Sweeping smoothly from underneath the southern tip of Punjab, curving around the bottom-left corner and western flank, and reaching up to the northwestern shoulder.
  - Cohesive athletic blue color palette (`#0D47A1`, `#1565C0`, `#1976D2`) with staggered starting points and endpoints.
- **Bold Paralympic Athlete Pictogram**:
  - **Large Rear Wheel**: Unmistakable circular wheel (diameter: 84px) with bold `7px` stroke and prominent `7.5px` hub.
  - **Wheelchair Frame & Seat**: Bold `6.5px` seat tube, `6.5px` backrest, `6px` footrest downtube, and `10.5px` front caster with `4.5px` stroke.
  - **Lower Body / Legs**: Bold `7px` pictogram stroke resting naturally on the wheelchair seat.
  - **Torso**: Single bold `8.5px` stroke leaning forward at 31° in a strong competitive forward-racing posture.
  - **Head**: Bold solid circular dot (`r=11.5px`) with clean negative space gap above torso.
  - **Driving Arm**: Bold `7.5px` forward propulsion stroke driving the wheel and momentum forward.
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
