<?php
/**
 * Every editable site setting, grouped into admin tabs.
 * type: text | textarea | html | image | file (private PDF) | color | number | datetime | toggle
 * The 'default' value is used when the installer seeds the database.
 */
return [
    'general' => [
        'label' => 'General',
        'fields' => [
            'site_name'        => ['label' => 'Site name', 'type' => 'text', 'default' => 'The Healing Kitchen'],
            'site_tagline'     => ['label' => 'Tagline', 'type' => 'text', 'default' => 'Anti-inflammatory cookbooks for everyday life'],
            'logo'             => ['label' => 'Logo image (optional, replaces site name)', 'type' => 'image', 'default' => ''],
            'favicon'          => ['label' => 'Favicon (optional)', 'type' => 'image', 'default' => ''],
            'meta_description' => ['label' => 'Meta description (SEO)', 'type' => 'textarea', 'default' => 'A complete bundle of anti-inflammatory cookbooks, meal plans and bonus guides to help you eat well and feel better every day.'],
            'contact_email'    => ['label' => 'Contact email', 'type' => 'text', 'default' => 'support@example.com'],
        ],
    ],
    'header' => [
        'label' => 'Announcement & Header',
        'fields' => [
            'announcement_enabled' => ['label' => 'Show announcement bar', 'type' => 'toggle', 'default' => '1'],
            'announcement_text'    => ['label' => 'Announcement text (one message per line, rotates)', 'type' => 'textarea', 'default' => "Limited-time offer: save over 80% today\nInstant digital download — start cooking tonight"],
            'topbar_timer_enabled' => ['label' => 'Show timer in announcement bar (always 1–2 hours left, adds 1 hour when it drops below 1 hour)', 'type' => 'toggle', 'default' => '1'],
            'topbar_timer_text'    => ['label' => 'Timer label', 'type' => 'text', 'default' => 'Offer ends in'],
            'nav_cta_text'         =>['label' => 'Header button text', 'type' => 'text', 'default' => 'Get the Bundle'],
        ],
    ],
    'hero' => [
        'label' => 'Product / Hero',
        'fields' => [
            'product_badge'      => ['label' => 'Small badge above title', 'type' => 'text', 'default' => 'Bestseller · 2026 Edition'],
            'product_title'      => ['label' => 'Product title', 'type' => 'text', 'default' => 'The Anti-Inflammatory Cookbook Bundle 2026 — Plus 10 Free Bonuses'],
            'product_rating'     => ['label' => 'Rating (e.g. 4.9)', 'type' => 'text', 'default' => '4.9'],
            'product_reviews'    => ['label' => 'Review count text', 'type' => 'text', 'default' => '2,300+ happy home cooks'],
            'product_subtitle'   => ['label' => 'Short description', 'type' => 'textarea', 'default' => 'Fifteen beautifully designed digital cookbooks packed with simple, whole-food recipes that calm inflammation, support digestion and keep the whole family satisfied.'],
            'product_image'      => ['label' => 'Main product image', 'type' => 'image', 'default' => 'assets/img/bundle.svg'],
            'countdown_enabled'  => ['label' => 'Show countdown timer', 'type' => 'toggle', 'default' => '1'],
            'countdown_label'    => ['label' => 'Countdown label', 'type' => 'text', 'default' => 'Sale ends in'],
            'countdown_mode'     => ['label' => 'Countdown mode: "daily" (resets at midnight) or "fixed"', 'type' => 'text', 'default' => 'daily'],
            'countdown_end'      => ['label' => 'Fixed countdown end (used when mode is fixed)', 'type' => 'datetime', 'default' => ''],
            'buy_button_text'    => ['label' => 'Buy button text', 'type' => 'text', 'default' => 'Get Instant Access'],
            'guarantee_text'     => ['label' => 'Text under buy button (trust line)', 'type' => 'text', 'default' => 'Secure checkout · Instant PDF download · Lifetime access'],
            'format_note'        => ['label' => 'Format note', 'type' => 'text', 'default' => 'Digital product — works on phone, tablet, computer or print at home.'],
        ],
    ],
    // Edited on Admin → Bundle (not listed under Website Settings).
    'bundle' => [
        'label' => 'Bundle',
        'fields' => [
            'bundle_name'          => ['label' => 'Bundle name (shown on the website, cart, invoice and orders)', 'type' => 'text', 'default' => 'Complete Cookbook Bundle'],
            'price'              => ['label' => 'Bundle price', 'type' => 'number', 'default' => '27.00'],
            'compare_price'      => ['label' => 'Bundle compare-at price (leave 0 to use the total of the bundle books’ prices)', 'type' => 'number', 'default' => '197.00'],
        ],
    ],
    'pricing' => [
        'label' => 'Currency & Cart',
        'fields' => [
            'currency'           => ['label' => 'Currency code (e.g. usd, gbp, eur — must be a currency your Paddle account supports)', 'type' => 'text', 'default' => 'usd'],
            'currency_symbol'    => ['label' => 'Currency symbol', 'type' => 'text', 'default' => '$'],
            'add_to_cart_text'     => ['label' => 'Add to cart button text', 'type' => 'text', 'default' => 'Add to cart'],
            'cart_hint_text'       => ['label' => 'Books section hint', 'type' => 'textarea', 'default' => 'Buy any cookbook on its own, or save with the {bundle_name}: {bundle_count} books for just {bundle_price}.'],
        ],
    ],
    'sections' => [
        'label' => 'Section Headings',
        'fields' => [
            'features_enabled'     => ['label' => 'Show the "Why you\'ll love it" section', 'type' => 'toggle', 'default' => '0'],
            'bonuses_enabled'      => ['label' => 'Show the Bonuses section', 'type' => 'toggle', 'default' => '0'],
            'features_heading'     => ['label' => '"Why you\'ll love it" heading', 'type' => 'text', 'default' => 'Why You’ll Love This Bundle'],
            'features_subheading'  => ['label' => '"Why you\'ll love it" intro', 'type' => 'textarea', 'default' => 'Everything you need to start eating in a way that helps your body feel calmer, lighter and more energised.'],
            'books_heading'        => ['label' => '"What\'s inside" heading', 'type' => 'text', 'default' => 'What’s Inside the Bundle'],
            'books_subheading'     => ['label' => '"What\'s inside" intro', 'type' => 'textarea', 'default' => 'Fifteen complete cookbooks, each focused on a different part of your day and your plate.'],
            'bonuses_heading'      => ['label' => 'Bonuses heading', 'type' => 'text', 'default' => 'Plus 10 Free Bonuses'],
            'bonuses_subheading'   => ['label' => 'Bonuses intro', 'type' => 'textarea', 'default' => 'Order today and these extras are included at no additional cost.'],
            'testimonials_heading' => ['label' => 'Reviews heading', 'type' => 'text', 'default' => 'Loved by Home Cooks Everywhere'],
            'faq_heading'          => ['label' => 'FAQ heading', 'type' => 'text', 'default' => 'Frequently Asked Questions'],
        ],
    ],
    'comparison' => [
        'label' => 'Before / After Section',
        'fields' => [
            'comparison_enabled'      => ['label' => 'Show the before / after section', 'type' => 'toggle', 'default' => '1'],
            'comparison_before_image' => ['label' => 'Before image', 'type' => 'image', 'default' => 'assets/img/before.svg'],
            'comparison_after_image'  => ['label' => 'After image', 'type' => 'image', 'default' => 'assets/img/after.svg'],
            'comparison_before_label' => ['label' => 'Before label (leave empty to hide)', 'type' => 'text', 'default' => 'Before'],
            'comparison_after_label'  => ['label' => 'After label (leave empty to hide)', 'type' => 'text', 'default' => 'After'],
            'comparison_heading'      => ['label' => 'Heading (optional)', 'type' => 'text', 'default' => ''],
            'comparison_text'         => ['label' => 'Text — leave an empty line between paragraphs; wrap words in **double stars** to make them bold', 'type' => 'textarea', 'default' => "The **28-Day Meal Plan** walks you step by step into a calmer, more balanced way of eating. Each week brings simple whole-food recipes, colourful produce, healthy fats and satisfying proteins, all built around anti-inflammatory ingredients.\n\nClear weekly guidance, ready-made shopping lists and practical tips help you build habits that last, without strict rules or complicated tracking.\n\nIt is ideal for anyone who wants to feel lighter, enjoy nourishing food and make healthier eating feel effortless."],
            'comparison_button_text'  => ['label' => 'Button text (leave empty to hide the button)', 'type' => 'text', 'default' => 'Start Your Journey Now'],
            'comparison_button_link'  => ['label' => 'Button link (leave empty to open the bundle checkout)', 'type' => 'text', 'default' => ''],
        ],
    ],
    'value' => [
        'label' => 'Value / Final CTA',
        'fields' => [
            'value_heading'    => ['label' => 'Value section heading', 'type' => 'text', 'default' => 'Why Pay Full Price?'],
            'value_text'       => ['label' => 'Value section text', 'type' => 'textarea', 'default' => "Bought one by one, these cookbooks and guides would cost well over $197. Today you get the complete collection for a single small payment — and lifetime access to every update."],
            'value_total'      => ['label' => 'Total value text', 'type' => 'text', 'default' => 'Total value: $197+'],
            'cta_heading'      => ['label' => 'Final call-to-action heading', 'type' => 'text', 'default' => 'Start Cooking for a Calmer Body Tonight'],
            'cta_text'         => ['label' => 'Final call-to-action text', 'type' => 'textarea', 'default' => 'Download instantly, pick a recipe, and enjoy a delicious meal that loves you back.'],
        ],
    ],
    'footer' => [
        'label' => 'Newsletter & Footer',
        'fields' => [
            'newsletter_enabled'  => ['label' => 'Show newsletter signup', 'type' => 'toggle', 'default' => '1'],
            'newsletter_heading'  => ['label' => 'Newsletter heading', 'type' => 'text', 'default' => 'Subscribe to our emails'],
            'newsletter_text'     => ['label' => 'Newsletter text', 'type' => 'textarea', 'default' => 'Weekly recipes, meal-prep tips and members-only offers. No spam, ever.'],
            'footer_about'        => ['label' => 'Footer about text', 'type' => 'textarea', 'default' => 'Simple, nourishing recipes that make anti-inflammatory eating easy and delicious.'],
            'footer_disclaimer'   => ['label' => 'Footer disclaimer', 'type' => 'textarea', 'default' => 'This content is for informational purposes only and is not a substitute for professional medical advice.'],
            'copyright'           => ['label' => 'Copyright text', 'type' => 'text', 'default' => '© {year} The Healing Kitchen. All rights reserved.'],
        ],
    ],
    'delivery' => [
        'label' => 'Delivery',
        'fields' => [
            'delivery_file'     => ['label' => 'Complete-bundle extra PDF (bonuses — only complete-bundle buyers get it; each book has its own PDF under Books)', 'type' => 'file', 'default' => ''],
            'delivery_filename' => ['label' => 'File name for the complete-bundle extra PDF', 'type' => 'text', 'default' => 'Bundle-Bonuses.pdf'],
            'download_limit'    => ['label' => 'Max downloads per file, per order (0 = unlimited)', 'type' => 'text', 'default' => '2'],
            'download_url'      => ['label' => 'External URL for the complete-bundle extra PDF (only used if none is uploaded; buyers never see this link)', 'type' => 'text', 'default' => ''],
            'thankyou_heading'  => ['label' => 'Thank-you page heading', 'type' => 'text', 'default' => 'Thank you for your order!'],
            'thankyou_text'     => ['label' => 'Thank-you page text', 'type' => 'textarea', 'default' => 'Your payment was successful. Your cookbooks are downloading now — you can also download each one below.'],
        ],
    ],
    'theme' => [
        'label' => 'Theme Colors',
        'fields' => [
            'color_primary' => ['label' => 'Primary (buttons, accents)', 'type' => 'color', 'default' => '#c1622d'],
            'color_dark'    => ['label' => 'Dark (header, footer)', 'type' => 'color', 'default' => '#22332f'],
            'color_sage'    => ['label' => 'Sage section background', 'type' => 'color', 'default' => '#e4e8d9'],
            'color_cream'   => ['label' => 'Cream page background', 'type' => 'color', 'default' => '#f7f1e4'],
        ],
    ],
];
