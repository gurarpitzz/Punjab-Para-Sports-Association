/**
 * Punjab Para Sports Association (PPSA) - State Games Event Catalog & Cascading Matrix
 * Source: Official Para State Games Notification Ref. No. PPSA/26/180 (Venue: Ludhiana)
 */

const PPSA_CATALOG = {
  "athletics": {
    "name": "Para Athletics",
    "venue": "Guru Nanak Stadium, Ludhiana",
    "categories": {
      "F-51 (WC)": ["Club Throw", "Discus Throw"],
      "F-52 (WC)": ["Discus Throw"],
      "F-53, 54, 55 (WC)": ["Discus Throw", "Shot Put", "Javelin Throw"],
      "F-56, 57 (S)": ["Discus Throw", "Shot Put", "Javelin Throw"],
      "F-35": ["Shot Put"],
      "F-36": ["Shot Put"],
      "F-43": ["Javelin Throw"],
      "F-64": ["Discus Throw", "Shot Put", "Javelin Throw"],
      "F-11, 12, 13, 20, 32, 33, 34, 35, 36, 37, 38, 44, 45, 46, 47": ["Discus Throw", "Shot Put", "Javelin Throw"],
      "T-42": ["100m", "High Jump", "Long Jump"],
      "T-53": ["100m", "200m", "400m", "800m", "1500m", "5000m"],
      "T-54": ["100m", "400m", "800m", "1500m", "5000m"],
      "T-63": ["100m", "Long Jump"],
      "T-64": ["100m", "200m", "Long Jump"],
      "T-11, 12, 13, 20, 33, 34, 35, 36, 37, 38, 44, 46, 47": [
        "100m", "200m", "400m", "800m", "1500m", "5000m", "Long Jump", "High Jump"
      ]
    }
  },
  "powerlifting": {
    "name": "Power Lifting",
    "venue": "Indoor Weightlifting Hall, Ludhiana",
    "categories": {
      "Female Weight Categories": [
        "41 kg Division",
        "45 kg Division",
        "50 kg Division",
        "55 kg Division",
        "61 kg Division",
        "67 kg Division",
        "73 kg Division",
        "79 kg Division",
        "86 kg Division",
        "86+ kg Division"
      ],
      "Male Weight Categories": [
        "49 kg Division",
        "54 kg Division",
        "59 kg Division",
        "65 kg Division",
        "72 kg Division",
        "80 kg Division",
        "88 kg Division",
        "97 kg Division",
        "107 kg Division",
        "107+ kg Division"
      ]
    }
  },
  "badminton": {
    "name": "Para Badminton",
    "venue": "Multipurpose Indoor Hall, Ludhiana",
    "categories": {
      "WH-1, 2 (Wheelchair)": [
        "Men Single",
        "Men Double",
        "Mix Double",
        "Women Single",
        "Women Double"
      ],
      "SL-3, 4 (Standing Lower)": [
        "Men Single",
        "Men Double",
        "Mix Double",
        "Women Single",
        "Women Double"
      ],
      "SU-5 (Standing Upper)": [
        "Men Single",
        "Men Double",
        "Mix Double",
        "Women Single",
        "Women Double"
      ],
      "SS-6 (Short Stature)": [
        "Men Single",
        "Men Double",
        "Mix Double",
        "Women Single",
        "Women Double"
      ]
    }
  },
  "basketball": {
    "name": "Wheel-Chair Basket Ball",
    "venue": "Indoor Basketball Complex, Ludhiana",
    "categories": {
      "Male": ["State Championship Tournament Entry"],
      "Female": ["State Championship Tournament Entry"]
    }
  }
};

if (typeof module !== 'undefined' && module.exports) {
  module.exports = PPSA_CATALOG;
}
