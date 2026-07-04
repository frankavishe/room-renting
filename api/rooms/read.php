<?php
// api/rooms/read.php
// GET /api/rooms/read.php
// Optional query params: ?location=Kinondoni&max_price=250000&min_price=50000
// Returns: JSON array of room objects with owner name included.

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["message" => "Method not allowed."]);
    exit();
}

require_once '../../config/database.php';

// Start with the base query.
// We JOIN users so the frontend can show the landlord's name on the room card.
// We only return rooms where is_available = 1 (not occupied).
$sql = "SELECT r.id, r.title, r.description, r.price_per_month, r.location,
               r.amenities, r.images, r.is_available, r.created_at,
               u.name AS owner_name, u.phone AS owner_phone
        FROM rooms r
        INNER JOIN users u ON r.owner_id = u.id
        WHERE r.is_available = 1";

// Dynamically build filter conditions based on what the user passed in the URL.
// We collect them here and add them to the SQL safely using prepared statements.
$params = [];

if (!empty($_GET['location'])) {
    $sql .= " AND r.location LIKE :location";
    $params[':location'] = '%' . $_GET['location'] . '%';
}

if (!empty($_GET['max_price']) && is_numeric($_GET['max_price'])) {
    $sql .= " AND r.price_per_month <= :max_price";
    $params[':max_price'] = (float) $_GET['max_price'];
}

if (!empty($_GET['min_price']) && is_numeric($_GET['min_price'])) {
    $sql .= " AND r.price_per_month >= :min_price";
    $params[':min_price'] = (float) $_GET['min_price'];
}

$sql .= " ORDER BY r.created_at DESC";  // Newest listings first

$stmt = $db->prepare($sql);
$stmt->execute($params);
$rooms = $stmt->fetchAll();

// Decode amenities and images from JSON string to actual arrays
// so the frontend gets clean JavaScript arrays, not raw strings.
foreach ($rooms as &$room) {
    $room['amenities'] = json_decode($room['amenities'], true) ?? [];
    $room['images']    = json_decode($room['images'], true) ?? [];
}

http_response_code(200);
echo json_encode([
    "count" => count($rooms),
    "rooms" => $rooms
]);
