<?php
declare(strict_types=1);

/**
 * Curated topic clusters for internal linking. An item's "related" blocks are
 * built from every cluster it appears in (clusters earlier in the list win when
 * there is not room for everything). Slugs that do not exist or are not
 * published are skipped, so a cluster can safely list content that is still
 * being written. Edit here, no database change needed.
 */
return [
    ['label' => 'Iguazú Falls',        'items' => ['iguazu-falls-from-asuncion', 'iguazu-falls', 'waterfalls-paraguay', 'itaipu-dam-tour', 'paraguay-travel-advice']],
    ['label' => 'Itaipú and the east', 'items' => ['itaipu-dam-tour', 'exploring-ciudad-del-este', 'iguazu-falls-from-asuncion', 'shopping-beaches']],
    ['label' => 'Jesuit missions',     'items' => ['jesuit-ruins-tour', 'jesuit-missions-paraguay', 'shopping-beaches', 'paraguay-culture-tour']],
    ['label' => 'Lake and day trips',  'items' => ['san-bernardino-trip', 'san-bernardino', 'day-trips-asuncion', 'caacupe', 'paraguay-culture-tour']],
    ['label' => 'Nature and wildlife', 'items' => ['bird-watching', 'fishing-charters', 'salto-cristal-tour', 'atlantic-forest-paraguay', 'pantanal-paraguayo', 'paraguay-national-parks', 'serrania-san-luis-national-park', 'mbatovi-ecoadventure', 'complejo-ecologico-techapyra', 'cerro-cora-park', 'waterfalls-paraguay', 'filadelfia-chaco', 'paraguay-beaches']],
    ['label' => 'Food and drink',      'items' => ['food-paraguay-tour', 'restaurants-asuncion-guide', 'bars-asuncion-tour', 'yerba-mate-tour', 'paraguayan-cuisine', 'food-park-mburucuya-a-culinary-oasis-in-asuncion', 'villa-morra-food-park']],
    ['label' => 'Shopping',            'items' => ['shopping-asuncion', 'paraguay-souvenirs', 'shopping-in-asuncion', 'shopping-beaches', 'exploring-ciudad-del-este']],
    ['label' => 'Asunción',            'items' => ['asuncion-city-tour', 'paraguay-culture-tour', 'day-trips-asuncion', 'what-to-do-in-asuncion', 'top-places-paraguay', 'paraguay-sightseeing', 'essential-guide-paraguay', 'paraguay-tour']],
    ['label' => 'Safety and advice',   'items' => ['is-paraguay-safe', 'paraguay-travel-advice', 'essential-guide-paraguay', 'travel-planner', 'private-driver']],
    ['label' => 'Getting around',      'items' => ['airport-transfer', 'private-driver', 'travel-planner', 'airport-transfer-paraguay', 'asuncion-bus-terminal', 'paraguay-travel-advice']],
    ['label' => 'Moving to Paraguay',  'items' => ['apartment-hunting', 'paraguay-residency-service', 'school-placement', 'healthcare-paraguay', 'paraguay-real-estate-tour', 'best-neighborhoods-to-live-in-asuncion-where-should-you-settle-down-in-2025', 'cost-of-living-paraguay', 'renting-apartment-asuncion', 'remote-work-life-in-asuncion-cafes-coworking-internet-you-can-rely-on']],
];
