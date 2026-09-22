# Punjab Para Sports Website — Preloader Animation

A minimal, lightweight, GPU-accelerated vector preloader animation component for the Punjab Para Sports Association web portal.

## Corrective Visual Architecture
- **Redrawn Paralympic Athlete Pictogram**: Distinct, non-overlapping vector components:
  - **Large Rear Wheel**: Unmistakable circular wheel (diameter: 84px = 1.5x torso height) with visible central hub (`stroke-width: 5px`).
  - **Wheelchair Frame & Seat**: Clean seat tube, backrest, and footrest downtube with front caster wheel.
  - **Lower Body / Legs**: Clean pictogram stroke resting naturally on wheelchair seat.
  - **Torso**: Single strong stroke (`6px`) leaning forward at 31° in an athletic throwing posture.
  - **Head**: Solid blue circular dot with **15px clear negative space gap** above the torso.
  - **Throwing Arm**: Two-segment arm originating from the shoulder and extending diagonally upward toward the ball.
  - **Sports Ball**: Solid blue circle with subtle dashed trajectory trail and 50px clean whitespace gap from hand.
- **Motion Curves**: 3.5–4.5px thick dynamic curves anchored responsively at `x: 25.7%, y: 30.9%`, keeping 20px+ clearance from the athlete.
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
