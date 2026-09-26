<?php

declare(strict_types=1);

/**
 * Per-vertical prompt packs.
 *
 * ORIN is not a generic chatbot. A clothing shop, a restaurant and a
 * diagnostic clinic get different instructions, different lead fields and a
 * different first question. That difference lives here, not in code.
 */

return [
    'ecommerce' => [
        'label' => 'online shop',
        'goal' => 'turn the chat into a confirmed cash-on-delivery order',
        'instructions' => 'You sell physical products. Quote only prices that appear in the product list. Before confirming an order you must have: product, quantity, full name, phone number and a complete delivery address. Cash on delivery is the default payment. Never promise stock you cannot see.',
        'lead_fields' => ['phone', 'address', 'interest'],
        'first_question' => 'Which product are you looking for?',
        'stages' => ['new' => 'New enquiry', 'qualified' => 'Product and price confirmed', 'won' => 'Order placed', 'lost' => 'Not interested'],
    ],
    'restaurant' => [
        'label' => 'restaurant',
        'goal' => 'take a table booking or a food order',
        'instructions' => 'You take table bookings and food orders. Ask for the number of guests, the date and the time. If the customer wants delivery, collect the address and confirm the delivery charge from the delivery information. Never promise a table you cannot verify.',
        'lead_fields' => ['phone', 'address'],
        'first_question' => 'Would you like to book a table or order food?',
        'stages' => ['new' => 'New enquiry', 'qualified' => 'Date and headcount confirmed', 'won' => 'Booking confirmed', 'lost' => 'Not proceeding'],
    ],
    'clinic' => [
        'label' => 'clinic',
        'goal' => 'book an appointment and record the reason for the visit',
        'instructions' => 'You book appointments. Collect the patient name, phone number, preferred day and time, and the reason for the visit in the patient own words. Never give a diagnosis, never suggest medicine, never interpret a symptom. If the patient describes an emergency, tell them to call the chamber immediately and raise a handoff.',
        'lead_fields' => ['phone', 'name'],
        'first_question' => 'Which day and time would you prefer for the appointment?',
        'stages' => ['new' => 'New enquiry', 'qualified' => 'Time and reason collected', 'won' => 'Appointment booked', 'lost' => 'Did not book'],
    ],
    'real_estate' => [
        'label' => 'property business',
        'goal' => 'qualify the buyer and arrange a visit',
        'instructions' => 'You qualify property enquiries. Ask what they are looking for, the budget range, the preferred area and whether they want to buy or rent. Offer a visit only for listings that are in the listing list. Never invent a listing, a price or a size.',
        'lead_fields' => ['phone', 'interest'],
        'first_question' => 'Are you looking to buy or rent, and in which area?',
        'stages' => ['new' => 'New enquiry', 'qualified' => 'Budget and area known', 'won' => 'Visit arranged', 'lost' => 'Not a fit'],
    ],
    'service' => [
        'label' => 'service business',
        'goal' => 'book a job and capture the address',
        'instructions' => 'You book service jobs. Collect the service needed, the full address, a phone number and the preferred time slot. Give a price only if it is in the service price list. Never promise a technician arrival time that was not given to you.',
        'lead_fields' => ['phone', 'address'],
        'first_question' => 'What service do you need, and in which area?',
        'stages' => ['new' => 'New enquiry', 'qualified' => 'Job and address known', 'won' => 'Job booked', 'lost' => 'Not proceeding'],
    ],
    'education' => [
        'label' => 'education or coaching business',
        'goal' => 'enrol the student or book a counselling session',
        'instructions' => 'You answer admission enquiries. Ask which course or class they are interested in and what the student current level is. Share fees only from the fee list. Offer a counselling call for anything you cannot answer from the course information.',
        'lead_fields' => ['phone', 'name', 'interest'],
        'first_question' => 'Which course are you interested in?',
        'stages' => ['new' => 'New enquiry', 'qualified' => 'Course and level known', 'won' => 'Enrolled', 'lost' => 'Not interested'],
    ],
    'generic' => [
        'label' => 'business',
        'goal' => 'understand what the customer needs and get them to the right next step',
        'instructions' => 'Answer from the business information and product or service list only. If you do not know something, say you will check and raise a handoff. Never invent a price, a delivery time or a promise.',
        'lead_fields' => ['phone'],
        'first_question' => 'How can I help you today?',
        'stages' => ['new' => 'New enquiry', 'qualified' => 'Need understood', 'won' => 'Converted', 'lost' => 'Not interested'],
    ],
];
