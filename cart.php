<?php
/**
 * Cart API used by assets/js/cart.js.
 * POST action: add | remove | toggle | bundle | clear (+ id for book actions). Returns the priced cart as JSON.
 */
require_once __DIR__ . '/includes/functions.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals(csrf_token(), (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        echo json_encode(['error' => 'Your session expired. Please refresh the page.']);
        exit;
    }
    $cart = cart_get();
    $id = (int) ($_POST['id'] ?? 0);
    switch ($_POST['action'] ?? '') {
        case 'add':
            cart_set(false, [...$cart['books'], $id]);
            break;
        case 'remove':
            cart_set(false, array_diff($cart['books'], [$id]));
            break;
        case 'toggle':
            cart_set(false, in_array($id, $cart['books'], true) ? array_diff($cart['books'], [$id]) : [...$cart['books'], $id]);
            break;
        case 'bundle':
            cart_set(true, []);
            break;
        case 'clear':
            cart_set(false, []);
            break;
    }
}

echo json_encode(cart_public(cart_summary()));
