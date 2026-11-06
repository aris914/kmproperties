<?php
/**
 * Which fields each landing section uses, and whether it has repeating items.
 * fields: title, highlight, subtitle, body, image, button, limit
 * items: null, or labels for icon/title/subtitle/text/image/link
 */
function section_config($key)
{
    $cfg = [
        'hero' => [
            'help' => 'The large banner at the top with the search bar.',
            'fields' => ['title' => 'Headline', 'highlight' => 'Script text (gold, handwritten)', 'body' => 'Intro text', 'image' => 'Background image (wide, at least 1600px)', 'button' => 'Optional button'],
            'items' => null,
        ],
        'features' => [
            'help' => 'The cream strip with icons under the banner. Four items look best.',
            'fields' => [],
            'items' => ['icon' => 'Icon', 'title' => 'First line', 'subtitle' => 'Second line'],
        ],
        'featured_rent' => [
            'help' => 'Shows rental listings marked “Featured” first, then the newest.',
            'fields' => ['title' => 'Heading', 'subtitle' => 'Small text above heading', 'limit' => 'Number of listings', 'button' => 'Button below the listings'],
            'items' => null,
        ],
        'featured_sale' => [
            'help' => 'Shows for-sale listings marked “Featured” first, then the newest.',
            'fields' => ['title' => 'Heading', 'subtitle' => 'Small text above heading', 'limit' => 'Number of listings', 'button' => 'Button below the listings'],
            'items' => null,
        ],
        'services' => [
            'help' => 'Service cards (rentals, sales, property management…). Also shown on the About page.',
            'fields' => ['title' => 'Heading', 'subtitle' => 'Small text above heading'],
            'items' => ['icon' => 'Icon', 'title' => 'Service name', 'text' => 'Description', 'link' => 'Link (optional)'],
        ],
        'locations' => [
            'help' => 'Location tiles. Manage the list and pictures under Locations.',
            'fields' => ['title' => 'Heading', 'subtitle' => 'Small text above heading', 'limit' => 'Number of locations to show'],
            'items' => null,
        ],
        'about' => [
            'help' => 'About block with picture. Also used on the About Us page.',
            'fields' => ['title' => 'Heading', 'highlight' => 'Script text (optional)', 'subtitle' => 'Small text above heading', 'body' => 'Text (blank line = new paragraph)', 'image' => 'Picture', 'button' => 'Button'],
            'items' => null,
        ],
        'stats' => [
            'help' => 'Numbers band (e.g. “500+ Happy tenants”).',
            'fields' => [],
            'items' => ['title' => 'Number', 'text' => 'Label'],
        ],
        'testimonials' => [
            'help' => 'Client quotes.',
            'fields' => ['title' => 'Heading', 'subtitle' => 'Small text above heading'],
            'items' => ['title' => 'Client name', 'subtitle' => 'Role / location', 'text' => 'Quote', 'image' => 'Photo (optional)'],
        ],
        'cta' => [
            'help' => 'Call-to-action banner, usually inviting owners to list their unit.',
            'fields' => ['title' => 'Heading', 'highlight' => 'Script text (optional)', 'body' => 'Text', 'image' => 'Background image', 'button' => 'Button'],
            'items' => null,
        ],
        'contact' => [
            'help' => 'Contact details (from Site Settings) and the inquiry form.',
            'fields' => ['title' => 'Heading', 'subtitle' => 'Text above the form'],
            'items' => null,
        ],
    ];
    return isset($cfg[$key]) ? $cfg[$key] : ['help' => '', 'fields' => ['title' => 'Heading', 'subtitle' => 'Subtitle', 'body' => 'Text', 'image' => 'Image', 'button' => 'Button'], 'items' => null];
}

function icon_choices()
{
    return ['bi-hand-thumbs-up', 'bi-buildings', 'bi-building', 'bi-shield-check', 'bi-house-heart', 'bi-house-check', 'bi-house-door', 'bi-key', 'bi-clipboard-check',
        'bi-tools', 'bi-cash-coin', 'bi-graph-up-arrow', 'bi-people', 'bi-person-check', 'bi-geo-alt', 'bi-star', 'bi-award', 'bi-heart', 'bi-lightning',
        'bi-clock', 'bi-telephone', 'bi-chat-dots', 'bi-camera', 'bi-file-earmark-check', 'bi-megaphone', 'bi-wifi', 'bi-car-front', 'bi-tree', 'bi-water'];
}
