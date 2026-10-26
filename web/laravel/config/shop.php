<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Shop
    |--------------------------------------------------------------------------
    |
    | The online shop for medicines that don't need a prescription. It is a
    | demo: orders are real, but payments are simulated and nothing is sent.
    | Values in [brackets] are placeholders from the design: replace them.
    |
    */

    // The most packs of one medicine a single order may have
    'max_packs' => 2,

    'ready_within' => '[2 hours]',

    // How long the answers to the pharmacist's questions are kept with an order
    'health_data_kept' => '[time]',

    // Package leaflets on CIMA, the Spanish Medicines Agency's database
    'leaflet_url' => '#',

    // The only card numbers the simulated payment accepts, and what each one does. Any other
    // number is refused, so nobody can type a real card into the demo.
    'demo_cards' => [
        '4242424242424242' => 'approved',
        '4000000000000002' => 'declined',
    ],

];
