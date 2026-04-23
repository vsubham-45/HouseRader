<?php
// api/chatbot.php
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$msg = strtolower(trim($input['message'] ?? ''));

function reply($text) {
    echo json_encode(['reply' => $text]);
    exit;
}

if ($msg === '') reply("Please type something 🙂");

if (str_contains($msg, 'buy')) {
    reply("You can browse properties on the homepage. Use filters to find flats, houses, or rentals.");
}

if (str_contains($msg, 'sell') || str_contains($msg, 'add property')) {
    reply("To sell a property, switch to Seller mode and click ‘Add Property’ from your dashboard.");
}

if (str_contains($msg, 'feature')) {
    reply("Featured listings get higher visibility. Open your property → ‘Feature Listing’ to see plans.");
}

if (str_contains($msg, 'price') || str_contains($msg, 'cost')) {
    reply("Listing properties is free. Featuring starts from ₹1499 depending on duration.");
}

if (str_contains($msg, 'contact') || str_contains($msg, 'chat')) {
    reply("You can chat directly with sellers from any property page using our secure inbox.");
}

reply("I can help with buying, selling, pricing, or site navigation. Try asking something like “How to sell my property?”");
