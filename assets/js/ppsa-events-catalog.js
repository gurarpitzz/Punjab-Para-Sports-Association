/**
 * Punjab Para Sports Association (PPSA) - State Games Event Catalog & Cascading Matrix
 * Source: Official Para State Games Notification Ref. No. PPSA/26/180 (Venue: Ludhiana)
 * Single Source of Truth matching includes/sports_catalog.php
 */

window.PPSA_CATALOG = {
  "para_athletics": {
    "name": "Para Athletics",
    "venue": "Guru Nanak Stadium, Ludhiana",
    "form_type": "classification_event",
    "verticals": {
      "Category of Player - Track": {
        "T-11": ["100m", "200m", "400m", "800m", "1500m", "5000m", "Long Jump"],
        "T-12": ["100m", "200m", "400m", "800m", "1500m", "5000m", "Long Jump"],
        "T-13": ["100m", "200m", "400m", "800m", "1500m", "5000m", "Long Jump"],
        "T-20": ["100m", "200m", "400m", "800m", "1500m", "Long Jump", "High Jump"],
        "T-33": ["100m", "200m", "400m", "800m"],
        "T-34": ["100m", "200m", "400m", "800m", "1500m"],
        "T-35": ["100m", "200m", "400m"],
        "T-36": ["100m", "200m", "400m", "800m"],
        "T-37": ["100m", "200m", "400m", "800m", "1500m", "Long Jump"],
        "T-38": ["100m", "200m", "400m", "800m", "1500m", "Long Jump"],
        "T-42": ["100m", "200m", "Long Jump", "High Jump", "Shot Put", "Discus Throw", "Javelin Throw"],
        "T-43": ["100m", "200m", "Long Jump", "High Jump", "Shot Put", "Discus Throw", "Javelin Throw"],
        "T-44": ["100m", "200m", "400m", "Long Jump", "High Jump"],
        "T-45": ["100m", "200m", "400m", "800m", "1500m", "5000m", "High Jump"],
        "T-46": ["100m", "200m", "400m", "800m", "1500m", "5000m", "Long Jump", "High Jump"],
        "T-47": ["100m", "200m", "400m", "5000m", "Long Jump", "High Jump"],
        "T-51": ["100m", "200m", "400m"],
        "T-52": ["100m", "200m", "400m", "800m", "1500m"],
        "T-53": ["100m", "200m", "400m", "800m"],
        "T-54": ["100m", "200m", "400m", "800m", "1500m", "5000m"],
        "T-62": ["100m", "200m", "400m"],
        "T-63": ["100m", "200m", "Long Jump", "High Jump"],
        "T-64": ["100m", "200m", "Long Jump", "High Jump"],
        "Other (Track)": ["100m", "200m", "400m", "800m", "1500m", "5000m", "Long Jump", "High Jump"]
      },
      "Category of Player - Field": {
        "F-11": ["Shot Put", "Discus Throw", "Javelin Throw"],
        "F-12": ["Shot Put", "Discus Throw", "Javelin Throw"],
        "F-13": ["Shot Put", "Discus Throw", "Javelin Throw"],
        "F-20": ["Shot Put", "Discus Throw", "Javelin Throw"],
        "F-32": ["Shot Put", "Club Throw", "Discus Throw"],
        "F-33": ["Shot Put", "Discus Throw", "Javelin Throw"],
        "F-34": ["Shot Put", "Discus Throw", "Javelin Throw"],
        "F-35": ["Shot Put", "Discus Throw"],
        "F-37": ["Shot Put", "Discus Throw", "Javelin Throw"],
        "F-38": ["Shot Put", "Discus Throw", "Javelin Throw"],
        "F-40": ["Shot Put", "Discus Throw", "Javelin Throw"],
        "F-41": ["Shot Put", "Discus Throw", "Javelin Throw"],
        "F-42": ["Shot Put", "Discus Throw", "Javelin Throw"],
        "F-44": ["Shot Put", "Discus Throw", "Javelin Throw"],
        "F-46": ["Shot Put", "Discus Throw", "Javelin Throw"],
        "F-51": ["Club Throw", "Discus Throw"],
        "F-52": ["Discus Throw", "Shot Put"],
        "F-53": ["Shot Put", "Discus Throw", "Javelin Throw"],
        "F-54": ["Shot Put", "Discus Throw", "Javelin Throw"],
        "F-55": ["Shot Put", "Discus Throw", "Javelin Throw"],
        "F-56": ["Shot Put", "Discus Throw", "Javelin Throw"],
        "F-57": ["Shot Put", "Discus Throw", "Javelin Throw"],
        "F-63": ["Shot Put", "Discus Throw", "Javelin Throw"],
        "F-64": ["Shot Put", "Discus Throw", "Javelin Throw"],
        "Other (Field)": ["Shot Put", "Discus Throw", "Javelin Throw", "Club Throw"]
      }
    }
  },
  "powerlifting": {
    "name": "Power Lifting",
    "venue": "Indoor Weightlifting Hall, Ludhiana",
    "form_type": "gender_weight_event",
    "event_name": "Bench Press",
    "weights": {
      "female": [
        "41 kg", "45 kg", "50 kg", "55 kg", "61 kg",
        "67 kg", "73 kg", "79 kg", "86 kg", "86 kg+"
      ],
      "male": [
        "49 kg", "54 kg", "59 kg", "65 kg", "72 kg",
        "80 kg", "88 kg", "97 kg", "107 kg", "107 kg+"
      ]
    }
  },
  "para_badminton": {
    "name": "Para Badminton",
    "venue": "Multipurpose Indoor Hall, Ludhiana",
    "form_type": "classification_event",
    "classifications": ["WH-1", "WH-2", "SL-3", "SL-4", "SU-5", "SS-6"],
    "events": [
      "Men Single", "Men Double", "Mixed Double",
      "Women Single", "Women Double"
    ]
  },
  "wheelchair_basketball": {
    "name": "Wheelchair Basketball",
    "venue": "Indoor Basketball Complex, Ludhiana",
    "form_type": "gender_event",
    "categories": ["Male Division", "Female Division", "Other Category"],
    "events": [
      "Wheelchair Basketball Tournament Entry",
      "3x3 Wheelchair Basketball",
      "5x5 Wheelchair Basketball"
    ]
  }
};
var PPSA_CATALOG = window.PPSA_CATALOG;
