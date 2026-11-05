<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Seeded owner
    |--------------------------------------------------------------------------
    |
    | The first operator is created by OwnerSeeder. Password stays in the
    | environment and is never committed.
    |
    */

    'seed_user_name' => env('SEED_USER_NAME', 'Nelson Mboya'),

    'seed_user_email' => env('SEED_USER_EMAIL'),

    'seed_user_password' => env('SEED_USER_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | Company facts for the AI sales playbook
    |--------------------------------------------------------------------------
    |
    | Taken from kemettech.com. Every AI reply, follow-up and lead read uses
    | these facts and points people to the website. Edit here when offers
    | change; the AI must not quote anything that is not listed.
    |
    */

    'company' => [
        'name' => 'Kemet Technologies (Kemettech)',
        'website' => env('KEMET_WEBSITE', 'https://kemettech.com'),
        'tagline' => 'Management systems for Kenyan businesses: POS, hotels, laundry, car wash and more. Plug the leaks. Get control. Grow faster.',
        'whatsapp' => '+254 746 507 979',
        'email' => 'support@kemettech.com',
        'location' => 'Applewood, Nairobi',
        'demo_url' => 'https://kemettech.com/contact',
        'purchase_url' => 'https://kemettech.com/purchase',
        'portfolio_url' => 'https://kemettech.com/portfolio',
        'proof' => [
            'Trusted by 500+ businesses across Kenya and East Africa.',
            'Free demo, and setup in about 48 hours from purchase.',
            '30-day money-back guarantee; one-time payment, own it forever.',
            'Nairobi support in Swahili and English, replies within about 2 hours.',
        ],
        'products' => [
            ['match' => ['pos', 'point of sale', 'retail', 'shop'], 'signals' => ['pos', 'point of sale', 'pharmacy', 'chemist', 'supermarket', 'shop', 'shops', 'hardware', 'agrovet', 'restaurant', 'wines', 'boutique', 'minimart', 'retail shop', 'my shop', 'stock'], 'key' => 'pos', 'name' => 'Kemet POS', 'url' => 'https://kemettech.com/products/pos', 'price' => 'USD 150 one-time (lifetime licence, 6 months priority support)', 'pitch' => 'M-Pesa STK Push, works 100% offline, stock and expiry alerts, multi-branch, 30+ reports.'],
            ['match' => ['hotel', 'roomops', 'lodge', 'airbnb', 'guest'], 'signals' => ['hotel', 'hotels', 'airbnb', 'airbnbs', 'lodge', 'guesthouse', 'guest house', 'room', 'rooms', 'room management', 'rentals', 'apartment', 'apartments', 'lodges', 'guesthouses', 'booking.com', 'bnb', 'resort'], 'key' => 'hotel', 'name' => 'RoomOps hotel system', 'url' => 'https://kemettech.com/products/hotel', 'price' => 'USD 350 one-time (lifetime licence, 12 months priority support, white-label)', 'pitch' => 'Booking website, channel manager with Airbnb/Booking.com sync, POS, housekeeping, M-Pesa.'],
            ['match' => ['laundry', 'dry clean'], 'signals' => ['laundry', 'laundries', 'laundromat', 'dry clean', 'dry cleaning', 'dry cleaner', 'dry cleaners', 'drycleaning', 'dobi', 'garment', 'clothes'], 'key' => 'laundry', 'name' => 'Kemet Laundry', 'url' => 'https://kemettech.com/products/laundry', 'price' => 'USD 349 one-time', 'pitch' => 'Ticketing, order tracking and M-Pesa for laundries; fewer lost items and complaints.'],
            ['match' => ['car wash', 'carwash'], 'signals' => ['car wash', 'car washes', 'carwash', 'carwashes', 'car-wash', 'detailing'], 'key' => 'carwash', 'name' => 'Kemet Car Wash', 'url' => 'https://kemettech.com/products/carwash', 'price' => 'USD 349 one-time', 'pitch' => 'Track washes, staff and payments; stop cash leaks.'],
            ['match' => ['accounting'], 'key' => 'accounting', 'name' => 'Kemet Accounting', 'url' => 'https://kemettech.com/products/accounting', 'price' => 'USD 700 one-time', 'pitch' => 'Accounting built for Kenyan businesses.'],
            ['match' => ['hrm', 'payroll', 'human resource'], 'key' => 'hrm', 'name' => 'Kemet HRM', 'url' => 'https://kemettech.com/products/hrm', 'price' => 'USD 657 one-time', 'pitch' => 'Staff, leave and payroll records.'],
            ['match' => ['loan', 'debtor'], 'key' => 'loan', 'name' => 'Loan & Debtor Management', 'url' => 'https://kemettech.com/products/loan', 'price' => null, 'pitch' => 'Track loans, repayments and debtors.'],
            ['match' => ['attendance'], 'key' => 'attendance', 'name' => 'Kemet Attendance', 'url' => 'https://kemettech.com/products/attendance', 'price' => null, 'pitch' => 'Staff attendance tracking.'],
            ['match' => ['appointment', 'booking', 'salon', 'clinic'], 'key' => 'appointment', 'name' => 'Kemet Appointments', 'url' => 'https://kemettech.com/products/appointment', 'price' => null, 'pitch' => 'Online bookings and reminders.'],
            ['match' => ['website', 'web design', 'growth'], 'key' => 'website', 'name' => 'Website design & growth', 'url' => 'https://kemettech.com/websites', 'price' => 'Website Launchpad USD 300, Growth Engine USD 500, Scale Pro USD 800', 'pitch' => 'Websites that capture leads, delivered in about 7 days.'],
        ],
    ],

];
