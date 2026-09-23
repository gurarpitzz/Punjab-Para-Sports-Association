<?php
// includes/sports_catalog.php - Single Source of Truth for PPSA State Games (Ludhiana)
// Extracted from official notification PPSA/26/180

function getPpsaSportsCatalog(): array {
    return [
        'para_athletics' => [
            'name'       => 'Para Athletics',
            'venue'      => 'Ludhiana',
            'form_type'  => 'classification_event', // Game -> Classification -> Event
            'categories' => [
                'F-51 (WC)' => ['Club Throw', 'Discus Throw'],
                'F-52 (WC)' => ['Discus Throw'],
                'F-53 (WC)' => ['Discus Throw', 'Shot Put', 'Javelin Throw'],
                'F-54 (WC)' => ['Discus Throw', 'Shot Put', 'Javelin Throw'],
                'F-55 (WC)' => ['Discus Throw', 'Shot Put', 'Javelin Throw'],
                'F-56 (S)'  => ['Discus Throw', 'Shot Put', 'Javelin Throw'],
                'F-57 (S)'  => ['Discus Throw', 'Shot Put', 'Javelin Throw'],
                'F-35'      => ['Shot Put'],
                'F-36'      => ['Shot Put'],
                'F-43'      => ['Javelin'],
                'F-64'      => ['Discus Throw', 'Shot Put', 'Javelin Throw'],
                // Visual Impairment & CP Standing Throws
                'F-11, F-12, F-13 (Visual)' => ['Discus Throw', 'Shot Put', 'Javelin Throw'],
                'F-20 (Intellectual)'       => ['Discus Throw', 'Shot Put', 'Javelin Throw'],
                'F-32, F-33, F-34 (CP Sitting)' => ['Discus Throw', 'Shot Put', 'Javelin Throw'],
                'F-37, F-38 (CP Standing)'  => ['Discus Throw', 'Shot Put', 'Javelin Throw'],
                'F-44, F-45, F-46, F-47 (Limb)' => ['Discus Throw', 'Shot Put', 'Javelin Throw'],
                // Track & Jumps
                'T-42'      => ['High Jump', 'Long Jump', '100m'],
                'T-53 (Wheelchair Track)' => ['100m', '200m', '400m', '800m', '1500m', '5000m'],
                'T-54 (Wheelchair Track)' => ['100m', '400m', '800m', '1500m', '5000m'],
                'T-63 (Limb Track/Jump)'  => ['100m', 'Long Jump'],
                'T-64 (Limb Track/Jump)'  => ['100m', '200m', 'Long Jump'],
                'T-11, T-12, T-13 (Visual Track)' => ['100m', '200m', '400m', '800m', '1500m', '5000m', 'Long Jump', 'High Jump'],
                'T-20 (Intellectual Track)'       => ['100m', '200m', '400m', '800m', '1500m', '5000m', 'Long Jump', 'High Jump'],
                'T-33, T-34, T-35, T-36, T-37, T-38 (CP Track)' => ['100m', '200m', '400m', '800m', '1500m', '5000m', 'Long Jump', 'High Jump'],
                'T-44, T-46, T-47 (Upper Limb)'   => ['100m', '200m', '400m', '800m', '1500m', '5000m', 'Long Jump', 'High Jump']
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
            'form_type'  => 'gender_event', // Game -> Gender -> Event
            'genders'    => ['male', 'female'],
            'event_name' => 'Wheelchair Basketball Tournament Entry'
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
            if (empty($classification) || !isset($entry['categories'][$classification])) {
                return ['valid' => false, 'error' => 'Valid athletics classification category is required.'];
            }
            if (!in_array($event, $entry['categories'][$classification], true)) {
                return ['valid' => false, 'error' => "Discipline '{$event}' is not valid for classification '{$classification}'."];
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
            $normGender = strtolower($gender);
            if (!in_array($normGender, ['male', 'female'], true)) {
                return ['valid' => false, 'error' => 'Valid gender is required for wheelchair basketball.'];
            }
            return ['valid' => true];

        default:
            return ['valid' => false, 'error' => 'Invalid sport discipline.'];
    }
}
