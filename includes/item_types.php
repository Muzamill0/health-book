<?php
/**
 * Repeating content blocks on the homepage, managed in Admin → Content.
 * 'fields' maps item columns to admin form labels; columns not listed are hidden for that type.
 */
return [
    'highlight' => [
        'label' => 'Hero Bullet Points', 'icon' => '✔︎',
        'help'  => 'Short bullet points shown next to the product and on the checkout summary.',
        'fields' => ['title' => 'Text', 'icon' => 'Icon / emoji (optional)'],
    ],
    'gallery' => [
        'label' => 'Product Gallery', 'icon' => '🖼',
        'help'  => 'Extra product images shown as thumbnails under the main image.',
        'fields' => ['title' => 'Alt text', 'image' => 'Image'],
    ],
    'feature' => [
        'label' => 'Why You’ll Love It', 'icon' => '❤',
        'help'  => 'Feature cards. Use an emoji icon or upload an image.',
        'fields' => ['title' => 'Title', 'body' => 'Description', 'icon' => 'Icon / emoji', 'image' => 'Image (optional, replaces icon)'],
    ],
    'bonus' => [
        'label' => 'Bonuses', 'icon' => '🎁',
        'help'  => 'Free bonuses included with the bundle.',
        'fields' => ['title' => 'Bonus title', 'body' => 'Description', 'icon' => 'Icon / emoji', 'image' => 'Image (optional, replaces icon)', 'value' => 'Value (e.g. $9)'],
    ],
    'testimonial' => [
        'label' => 'Reviews', 'icon' => '★',
        'help'  => 'Customer reviews. Only publish genuine reviews.',
        'fields' => ['title' => 'Customer name', 'subtitle' => 'Label (e.g. Verified buyer)', 'body' => 'Review text', 'value' => 'Stars (1–5)', 'image' => 'Photo (optional)'],
    ],
    'faq' => [
        'label' => 'FAQs', 'icon' => '?',
        'help'  => 'Questions and answers shown in the FAQ accordion.',
        'fields' => ['title' => 'Question', 'body' => 'Answer'],
    ],
];
