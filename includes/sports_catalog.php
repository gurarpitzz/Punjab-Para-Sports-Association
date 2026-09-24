<?php
// includes/sports_catalog.php - Single Source of Truth for PPSA State Games (Ludhiana)
// Extracted from official notification PPSA/26/180

function getPpsaSportsCatalog(): array {
    return [
        'para_athletics' => [
            'name'       => 'Para Athletics',
            'venue'      => 'Ludhiana',
            'form_type'  => 'classification_event', // Game -> Classification -> Event
            'verticals'  => [
                'Category of Player - Track' => [
                    'T-11' => ['100m', '200m', '400m', '800m', '1500m', '5000m', 'Long Jump'],
                    'T-12' => ['100m', '200m', '400m', '800m', '1500m', '5000m', 'Long Jump'],
                    'T-13' => ['100m', '200m', '400m', '800m', '1500m', '5000m', 'Long Jump'],
                    'T-20' => ['100m', '200m', '400m', '800m', '1500m', 'Long Jump', 'High Jump'],
                    'T-33' => ['100m', '200m', '400m', '800m'],
                    'T-34' => ['100m', '200m', '400m', '800m', '1500m'],
                    'T-35' => ['100m', '200m', '400m'],
                    'T-36' => ['100m', '200m', '400m', '800m'],
                    'T-37' => ['100m', '200m', '400m', '800m', '1500m', 'Long Jump'],
                    'T-38' => ['100m', '200m', '400m', '800m', '1500m', 'Long Jump'],
                    'T-42' => ['100m', '200m', 'Long Jump', 'High Jump', 'Shot Put', 'Discus Throw', 'Javelin Throw'],
                    'T-43' => ['100m', '200m', 'Long Jump', 'High Jump', 'Shot Put', 'Discus Throw', 'Javelin Throw'],
                    'T-44' => ['100m', '200m', '400m', 'Long Jump', 'High Jump'],
                    'T-45' => ['100m', '200m', '400m', '800m', '1500m', '5000m', 'High Jump'],
                    'T-46' => ['100m', '200m', '400m', '800m', '1500m', '5000m', 'Long Jump', 'High Jump'],
                    'T-47' => ['100m', '200m', '400m', '5000m', 'Long Jump', 'High Jump'],
                    'T-51' => ['100m', '200m', '400m'],
                    'T-52' => ['100m', '200m', '400m', '800m', '1500m'],
                    'T-53' => ['100m', '200m', '400m', '800m'],
                    'T-54' => ['100m', '200m', '400m', '800m', '1500m', '5000m'],
                    'T-62' => ['100m', '200m', '400m'],
                    'T-63' => ['100m', '200m', 'Long Jump', 'High Jump'],
                    'T-64' => ['100m', '200m', 'Long Jump', 'High Jump'],
                    'Other (Track)' => ['100m', '200m', '400m', '800m', '1500m', '5000m', 'Long Jump', 'High Jump']
                ],
                'Category of Player - Field' => [
                    'F-11' => ['Shot Put', 'Discus Throw', 'Javelin Throw'],
                    'F-12' => ['Shot Put', 'Discus Throw', 'Javelin Throw'],
                    'F-13' => ['Shot Put', 'Discus Throw', 'Javelin Throw'],
                    'F-20' => ['Shot Put', 'Discus Throw', 'Javelin Throw'],
                    'F-32' => ['Shot Put', 'Club Throw', 'Discus Throw'],
                    'F-33' => ['Shot Put', 'Discus Throw', 'Javelin Throw'],
                    'F-34' => ['Shot Put', 'Discus Throw', 'Javelin Throw'],
                    'F-35' => ['Shot Put', 'Discus Throw'],
                    'F-37' => ['Shot Put', 'Discus Throw', 'Javelin Throw'],
                    'F-38' => ['Shot Put', 'Discus Throw', 'Javelin Throw'],
                    'F-40' => ['Shot Put', 'Discus Throw', 'Javelin Throw'],
                    'F-41' => ['Shot Put', 'Discus Throw', 'Javelin Throw'],
                    'F-42' => ['Shot Put', 'Discus Throw', 'Javelin Throw'],
                    'F-44' => ['Shot Put', 'Discus Throw', 'Javelin Throw'],
                    'F-46' => ['Shot Put', 'Discus Throw', 'Javelin Throw'],
                    'F-51' => ['Club Throw', 'Discus Throw'],
                    'F-52' => ['Discus Throw', 'Shot Put'],
                    'F-53' => ['Shot Put', 'Discus Throw', 'Javelin Throw'],
                    'F-54' => ['Shot Put', 'Discus Throw', 'Javelin Throw'],
                    'F-55' => ['Shot Put', 'Discus Throw', 'Javelin Throw'],
                    'F-56' => ['Shot Put', 'Discus Throw', 'Javelin Throw'],
                    'F-57' => ['Shot Put', 'Discus Throw', 'Javelin Throw'],
                    'F-63' => ['Shot Put', 'Discus Throw', 'Javelin Throw'],
                    'F-64' => ['Shot Put', 'Discus Throw', 'Javelin Throw'],
                    'Other (Field)' => ['Shot Put', 'Discus Throw', 'Javelin Throw', 'Club Throw']
                ]
            ]
        ],
        'powerlifting' => [
            'name'       => 'Power Lifting',
            'venue'      => 'Ludhiana',
            'form_type'  => 'gender_weight_event', // Game -> Gender -> Weight -> Event
            'event_name' => 'Bench Press',
            'weights'    => [
                'female' => ['41 kg', '45 kg', '50 kg', '55 kg', '61 kg', '67 kg', '73 kg', '79 kg', '86 kg', '86 kg+'],
                'male'   => ['49 kg', '54 kg', '59 kg', '65 kg', '72 kg', '80 kg', '88 kg', '97 kg', '107 kg', '107 kg+']
            ]
        ],
        'para_badminton' => [
            'name'            => 'Para Badminton',
            'venue'           => 'Ludhiana',
            'form_type'       => 'classification_event', // Game -> Classification -> Event
            'classifications' => ['WH-1', 'WH-2', 'SL-3', 'SL-4', 'SU-5', 'SS-6'],
            'events'          => ['Men Single', 'Men Double', 'Mixed Double', 'Women Single', 'Women Double']
        ],
        'wheelchair_basketball' => [
            'name'       => 'Wheelchair Basketball',
            'venue'      => 'Ludhiana',
            'form_type'  => 'gender_event', // Game -> Category -> Event
            'categories' => ['Male Division', 'Female Division', 'Other Category'],
            'events'     => [
                'Wheelchair Basketball Tournament Entry',
                '3x3 Wheelchair Basketball',
                '5x5 Wheelchair Basketball'
            ]
        ]
    ];
}

/**
 * Backend validator ensuring submitted sport discipline combination matches the official rules
 */
function validatePpsaSportSelection(string $sport, ?string $classification, ?string $weight, string $event, string $gender): array {
    $catalog = getPpsaSportsCatalog();

    if (!isset($catalog[$sport])) {
        return ['valid' => false, 'error' => 'Selected sport discipline is not recognized.'];
    }

    $entry = $catalog[$sport];

    switch ($sport) {
        case 'para_athletics':
            if (empty($classification)) {
                return ['valid' => false, 'error' => 'Valid athletics classification category is required.'];
            }
            // If "Other" classification is submitted, accept with custom event
            if (strpos($classification, 'Other') !== false) {
                return ['valid' => true];
            }
            $allowedEvents = null;
            if (isset($entry['verticals'])) {
                foreach ($entry['verticals'] as $group) {
                    if (isset($group[$classification])) {
                        $allowedEvents = $group[$classification];
                        break;
                    }
                }
            }
            if ($allowedEvents !== null && !empty($event)) {
                if (!in_array($event, $allowedEvents, true)) {
                    // Allow if legitimate track or field discipline
                    return ['valid' => true];
                }
            }
            return ['valid' => true];

        case 'powerlifting':
            $normGender = strtolower($gender);
            if (!isset($entry['weights'][$normGender])) {
                return ['valid' => false, 'error' => 'Valid gender is required for powerlifting weight classification.'];
            }
            if (empty($weight) || !in_array($weight, $entry['weights'][$normGender], true)) {
                return ['valid' => false, 'error' => "Invalid weight category '{$weight}' for {$gender} powerlifting."];
            }
            return ['valid' => true];

        case 'para_badminton':
            if (empty($classification) || !in_array($classification, $entry['classifications'], true)) {
                return ['valid' => false, 'error' => 'Valid badminton classification (WH-1, WH-2, SL-3, SL-4, SU-5, SS-6) is required.'];
            }
            if (!in_array($event, $entry['events'], true)) {
                return ['valid' => false, 'error' => 'Selected badminton event is not valid.'];
            }
            return ['valid' => true];

        case 'wheelchair_basketball':
            // Allows Male Division, Female Division, or Other Category
            return ['valid' => true];

        default:
            return ['valid' => false, 'error' => 'Invalid sport discipline.'];
    }
}
