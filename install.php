<?php
/**
 * One-time installer: creates the database, tables, starter content and the first admin.
 * It locks itself (config/installed.lock) once finished.
 */
require_once __DIR__ . '/config/config.php';

$lock = __DIR__ . '/config/installed.lock';
if (file_exists($lock)) {
    exit('Already installed. Delete config/installed.lock to run the installer again.');
}

$error = '';
$done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username'] ?? '');
    $pass = (string) ($_POST['password'] ?? '');
    if ($user === '' || strlen($pass) < 8) {
        $error = 'Choose a username and a password of at least 8 characters.';
    } else {
        try {
            $server = DB_SOCKET !== '' ? 'mysql:unix_socket=' . DB_SOCKET : 'mysql:host=' . DB_HOST . ';port=' . DB_PORT;
            $pdo = new PDO($server . ';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $pdo->exec('USE `' . DB_NAME . '`');

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS admins (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    username VARCHAR(100) NOT NULL UNIQUE,
                    password_hash VARCHAR(255) NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB;

                CREATE TABLE IF NOT EXISTS settings (
                    setting_key VARCHAR(100) PRIMARY KEY,
                    setting_value MEDIUMTEXT
                ) ENGINE=InnoDB;

                CREATE TABLE IF NOT EXISTS items (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    type VARCHAR(30) NOT NULL,
                    title VARCHAR(255) NOT NULL DEFAULT '',
                    subtitle VARCHAR(255) NOT NULL DEFAULT '',
                    body TEXT,
                    image VARCHAR(255) NOT NULL DEFAULT '',
                    icon VARCHAR(50) NOT NULL DEFAULT '',
                    value VARCHAR(50) NOT NULL DEFAULT '',
                    sort_order INT NOT NULL DEFAULT 0,
                    is_active TINYINT(1) NOT NULL DEFAULT 1,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX (type, is_active, sort_order)
                ) ENGINE=InnoDB;

                CREATE TABLE IF NOT EXISTS pages (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    slug VARCHAR(100) NOT NULL UNIQUE,
                    title VARCHAR(255) NOT NULL,
                    content MEDIUMTEXT,
                    sort_order INT NOT NULL DEFAULT 0,
                    is_active TINYINT(1) NOT NULL DEFAULT 1,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB;

                CREATE TABLE IF NOT EXISTS orders (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    order_no VARCHAR(32) NOT NULL UNIQUE,
                    access_token VARCHAR(64) NOT NULL,
                    customer_name VARCHAR(150) NOT NULL,
                    customer_email VARCHAR(190) NOT NULL,
                    referrer_id INT DEFAULT NULL,
                    product_name VARCHAR(255) NOT NULL,
                    order_type VARCHAR(20) NOT NULL DEFAULT 'bundle',
                    subtotal DECIMAL(10,2) DEFAULT NULL,
                    discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
                    tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
                    amount DECIMAL(10,2) NOT NULL,
                    currency VARCHAR(10) NOT NULL,
                    status VARCHAR(20) NOT NULL DEFAULT 'pending',
                    payment_provider VARCHAR(20) NOT NULL DEFAULT 'paddle',
                    paddle_customer_id VARCHAR(100) DEFAULT NULL,
                    paddle_transaction_id VARCHAR(100) DEFAULT NULL,
                    stripe_customer_id VARCHAR(100) DEFAULT NULL,
                    stripe_invoice_id VARCHAR(100) DEFAULT NULL,
                    invoice_number VARCHAR(100) DEFAULT NULL,
                    invoice_url VARCHAR(500) DEFAULT NULL,
                    invoice_pdf VARCHAR(500) DEFAULT NULL,
                    stripe_payment_intent VARCHAR(100) DEFAULT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    paid_at DATETIME DEFAULT NULL,
                    download_count INT NOT NULL DEFAULT 0,
                    INDEX (stripe_payment_intent),
                    INDEX (stripe_invoice_id),
                    INDEX (paddle_transaction_id),
                    INDEX (referrer_id),
                    INDEX (status)
                ) ENGINE=InnoDB;

                CREATE TABLE IF NOT EXISTS books (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    title VARCHAR(255) NOT NULL,
                    category VARCHAR(255) NOT NULL DEFAULT '',
                    description TEXT,
                    image VARCHAR(255) NOT NULL DEFAULT '',
                    price DECIMAL(10,2) NOT NULL DEFAULT 0,
                    compare_price DECIMAL(10,2) DEFAULT NULL,
                    pdf_file VARCHAR(255) NOT NULL DEFAULT '',
                    in_bundle TINYINT(1) NOT NULL DEFAULT 1,
                    sort_order INT NOT NULL DEFAULT 0,
                    is_active TINYINT(1) NOT NULL DEFAULT 1,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX (is_active, sort_order)
                ) ENGINE=InnoDB;

                CREATE TABLE IF NOT EXISTS order_items (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    order_id INT NOT NULL,
                    book_id INT DEFAULT NULL,
                    title VARCHAR(255) NOT NULL,
                    unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
                    INDEX (order_id)
                ) ENGINE=InnoDB;

                CREATE TABLE IF NOT EXISTS order_downloads (
                    order_id INT NOT NULL,
                    file_key VARCHAR(50) NOT NULL,
                    download_count INT NOT NULL DEFAULT 0,
                    PRIMARY KEY (order_id, file_key)
                ) ENGINE=InnoDB;

                CREATE TABLE IF NOT EXISTS referrers (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(150) NOT NULL,
                    code VARCHAR(50) NOT NULL UNIQUE,
                    notes VARCHAR(255) NOT NULL DEFAULT '',
                    clicks INT NOT NULL DEFAULT 0,
                    is_active TINYINT(1) NOT NULL DEFAULT 1,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB;

                CREATE TABLE IF NOT EXISTS subscribers (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    email VARCHAR(190) NOT NULL UNIQUE,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB;
            ");

            // Settings defaults
            $schema = require __DIR__ . '/includes/settings_schema.php';
            $ins = $pdo->prepare('INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)');
            foreach ($schema as $group) {
                foreach ($group['fields'] as $key => $f) {
                    $ins->execute([$key, $f['default']]);
                }
            }

            // Starter content (original placeholder copy — edit everything in the admin panel)
            if ((int) $pdo->query('SELECT COUNT(*) FROM items')->fetchColumn() === 0) {
                $item = $pdo->prepare('INSERT INTO items (type, title, subtitle, body, image, icon, value, sort_order) VALUES (?,?,?,?,?,?,?,?)');
                $seed = [
                    ['highlight', '{bundle_count} complete digital cookbooks in one download', '', '', '', '✓', '', 1],
                    ['highlight', '600+ easy whole-food recipes', '', '', '', '✓', '', 2],
                    ['highlight', '10 free bonus guides & planners included', '', '', '', '✓', '', 3],
                    ['highlight', 'Lifetime access + free future updates', '', '', '', '✓', '', 4],

                    ['feature', '{bundle_count} Cookbooks in One Collection', '', 'A complete kitchen library covering breakfasts, lunches, dinners, snacks, soups, smoothies and desserts.', '', '📚', '', 1],
                    ['feature', 'Simple, Real Ingredients', '', 'Every recipe uses whole foods you can find at any grocery store — no hard-to-find powders required.', '', '🥦', '', 2],
                    ['feature', 'Everyday Meals & Weight Balance', '', 'Satisfying portions built around fibre, healthy fats and lean protein to keep you full and energised.', '', '🍽️', '', 3],
                    ['feature', 'Ready in 30 Minutes or Less', '', 'Most dishes come together on a weeknight with minimal prep and very little clean-up.', '', '⏱️', '', 4],
                    ['feature', 'Gentle on Digestion', '', 'Recipes that favour gut-friendly foods and avoid common triggers, with easy swaps for sensitivities.', '', '🌿', '', 5],
                    ['feature', 'Family-Friendly Flavour', '', 'Comforting dishes the whole table will enjoy — nobody will feel like they are “on a diet”.', '', '👨‍👩‍👧', '', 6],


                    ['bonus', '28-Day Meal Plan', '', 'Four weeks of done-for-you breakfasts, lunches and dinners.', '', '🗓️', '$17', 1],
                    ['bonus', 'Printable Grocery Lists', '', 'Weekly shopping lists organised by store section.', '', '🛒', '$9', 2],
                    ['bonus', 'Foods to Enjoy & Avoid Guide', '', 'A quick-reference chart to stick on your fridge.', '', '📋', '$9', 3],
                    ['bonus', 'Spice & Herb Handbook', '', 'How to use turmeric, ginger and friends for maximum flavour.', '', '🌶️', '$7', 4],
                    ['bonus', 'Meal-Prep Masterclass Guide', '', 'Step-by-step system for prepping a week of food in two hours.', '', '🥡', '$12', 5],
                    ['bonus', 'Eating Out Cheat Sheet', '', 'Smart choices at restaurants, cafés and parties.', '', '🍴', '$7', 6],
                    ['bonus', 'Symptom & Food Journal', '', 'Track meals and how you feel to discover your triggers.', '', '📓', '$9', 7],
                    ['bonus', 'Healthy Swaps Chart', '', 'Simple replacements for refined flour, sugar and oils.', '', '🔄', '$7', 8],
                    ['bonus', 'Budget-Friendly Recipe Pack', '', 'Twenty filling meals that cost under $3 per serving.', '', '💰', '$9', 9],
                    ['bonus', 'Kitchen Essentials Checklist', '', 'The tools and pantry staples that make cooking easier.', '', '✅', '$5', 10],

                    ['testimonial', 'Sarah M.', 'Verified buyer', 'I was nervous about changing how I eat, but these recipes are genuinely tasty. The meal plan took all the guesswork out of my week.', '', '', '5', 1],
                    ['testimonial', 'David R.', 'Verified buyer', 'Great value for the price. My whole family enjoys the one-pan dinners and I love how quick they are after work.', '', '', '5', 2],
                    ['testimonial', 'Priya K.', 'Verified buyer', 'Beautifully laid out and easy to follow on my tablet. The grocery lists alone have saved me so much time.', '', '', '5', 3],
                    ['testimonial', 'Linda T.', 'Verified buyer', 'I feel lighter and less bloated after a few weeks of cooking from these books. Highly recommend the soups!', '', '', '5', 4],
                    ['testimonial', 'Omar S.', 'Verified buyer', 'Finally healthy food that does not taste like health food. The smoothie book is a morning staple now.', '', '', '4', 5],
                    ['testimonial', 'Emma J.', 'Verified buyer', 'Downloaded instantly and printed my favourites. Clear instructions and simple ingredients.', '', '', '5', 6],

                    ['faq', 'Is this a physical book?', '', 'No — this is a digital bundle. You receive instant access to PDF files you can read on any device or print at home.', '', '', '', 1],
                    ['faq', 'How do I receive my cookbooks?', '', 'Right after checkout you will see a download button, and a receipt is emailed to you.', '', '', '', 2],
                    ['faq', 'Do I need special ingredients or equipment?', '', 'Not at all. The recipes use everyday ingredients and standard kitchen tools.', '', '', '', 3],
                    ['faq', 'Are the recipes suitable for vegetarians?', '', 'Yes. A full cookbook is dedicated to plant-based meals and many other recipes include vegetarian swaps.', '', '', '', 4],
                    ['faq', 'Is my payment secure?', '', 'Payments are processed securely by Paddle, our authorised reseller and merchant of record. We never see or store your card details.', '', '', '', 5],
                    ['faq', 'Can I get a refund?', '', 'Because the cookbooks are digital files delivered instantly after payment, all sales are final and we do not offer refunds. Please review the books included before you buy.', '', '', '', 6],
                ];
                foreach ($seed as $row) {
                    $item->execute($row);
                }
            }

            if ((int) $pdo->query('SELECT COUNT(*) FROM books')->fetchColumn() === 0) {
                $book = $pdo->prepare('INSERT INTO books (title, category, description, image, price, compare_price, sort_order) VALUES (?,?,?,?,?,?,?)');
                foreach ([
                    ['Bright Mornings', 'Breakfast & Brunch', 'Warm oats, savoury bakes and quick egg dishes to start the day right.', 'assets/img/book-1.svg', 4.99, 19, 1],
                    ['Midday Bowls', 'Lunch Favourites', 'Colourful grain bowls, wraps and salads that travel well.', 'assets/img/book-2.svg', 4.99, 19, 2],
                    ['One-Pan Dinners', 'Weeknight Mains', 'Sheet-pan and skillet meals with very little washing up.', 'assets/img/book-3.svg', 4.99, 19, 3],
                    ['Soothing Soups', 'Soups & Stews', 'Nourishing broths, chowders and slow-cooked stews.', 'assets/img/book-4.svg', 4.99, 15, 4],
                    ['Green Blends', 'Smoothies & Drinks', 'Smoothies, tonics and golden milks packed with goodness.', 'assets/img/book-5.svg', 4.99, 12, 5],
                    ['Smart Snacks', 'Snacks & Bites', 'Energy bites, dips and crunchy snacks for between meals.', 'assets/img/book-6.svg', 4.99, 12, 6],
                    ['Sweet Without Guilt', 'Desserts', 'Naturally sweetened treats, bakes and frozen desserts.', 'assets/img/book-1.svg', 4.99, 15, 7],
                    ['Plant Power', 'Vegetarian & Vegan', 'Hearty meat-free mains full of protein and flavour.', 'assets/img/book-2.svg', 4.99, 15, 8],
                    ['From the Sea', 'Fish & Seafood', 'Omega-rich salmon, sardine and shellfish recipes.', 'assets/img/book-3.svg', 4.99, 15, 9],
                    ['Slow & Simple', 'Slow Cooker', 'Set-and-forget meals that are ready when you are.', 'assets/img/book-4.svg', 4.99, 15, 10],
                    ['Air Fryer Fresh', 'Air Fryer', 'Crispy favourites made lighter with your air fryer.', 'assets/img/book-5.svg', 4.99, 12, 11],
                    ['Batch & Freeze', 'Meal Prep', 'Cook once, eat all week — with storage tips for every dish.', 'assets/img/book-6.svg', 4.99, 15, 12],
                    ['Gut Comfort', 'Digestive Health', 'Gentle recipes designed around gut-friendly ingredients.', 'assets/img/book-1.svg', 4.99, 15, 13],
                    ['Kids at the Table', 'Family Meals', 'Fun, mild recipes that children actually ask for.', 'assets/img/book-2.svg', 4.99, 12, 14],
                    ['Celebrate Well', 'Holidays & Gatherings', 'Festive menus that feel indulgent but stay nourishing.', 'assets/img/book-3.svg', 4.99, 15, 15],
                ] as $row) {
                    $book->execute($row);
                }
            }

            if ((int) $pdo->query('SELECT COUNT(*) FROM pages')->fetchColumn() === 0) {
                $page = $pdo->prepare('INSERT INTO pages (slug, title, content, sort_order) VALUES (?,?,?,?)');
                $page->execute(['contact', 'Contact Us', '<p>Have a question about your order? Email us at <a href="mailto:support@example.com">support@example.com</a> and we will reply within one business day.</p>', 1]);
                $page->execute(['refund-policy', 'Refund Policy', '<p>All sales are final. Because our cookbooks are digital products delivered instantly after payment, we do not offer refunds, returns or exchanges once an order is completed.</p><p>Please review the product details and the books included before purchasing. If you have trouble downloading your files, contact us and we will help you get access.</p>', 2]);
                $page->execute(['privacy-policy', 'Privacy Policy', '<p>We only collect the information needed to process your order and send you your download. Payments are handled securely by Paddle, our merchant of record. We never sell your data.</p>', 3]);
                $page->execute(['terms', 'Terms of Service', '<p>By purchasing you receive a personal, non-transferable licence to use the digital products. Redistribution or resale is not permitted.</p>', 4]);
            }

            $pdo->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?) ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)')
                ->execute([$user, password_hash($pass, PASSWORD_DEFAULT)]);

            file_put_contents($lock, date('c'));
            $done = true;
        } catch (Throwable $ex) {
            $error = $ex->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Install Health Book</title>
<link rel="stylesheet" href="admin/admin.css">
</head>
<body class="login-body">
<div class="login-card">
    <h1>Install</h1>
    <?php if ($done): ?>
        <p class="alert success">Installation complete.</p>
        <p><a class="btn" href="admin/">Go to admin panel</a> <a class="btn btn-light" href="./">View website</a></p>
    <?php else: ?>
        <p class="muted">Database <strong><?= htmlspecialchars(DB_NAME) ?></strong> will be created using the settings in <code>config/config.php</code>.</p>
        <?php if ($error): ?><p class="alert error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
        <form method="post">
            <label>Admin username<input name="username" required value="admin"></label>
            <label>Admin password (min 8 chars)<input type="password" name="password" required minlength="8"></label>
            <button class="btn" type="submit">Install</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
