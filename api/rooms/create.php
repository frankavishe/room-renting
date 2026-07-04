<?php
// api/rooms/create.php
// POST — landlord creates a new room listing.
// Accepts JSON: { title, description, price_per_month, location, amenities[], images[] }
// Requires Authorization: Bearer <JWT> header.

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["message" => "Method not allowed."]);
    exit();
}

require_once '../../config/database.php';
require_once '../auth/jwt_helper.php';

// Extract and verify the JWT from the Authorization header.
// The header looks like: Authorization: Bearer eyJhbGci...
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';
$token = str_replace('Bearer ', '', $authHeader);

$decoded = verifyJWT($token);
if (!$decoded) {
    http_response_code(401);
    echo json_encode(["message" => "Unauthorized. Please log in."]);
    exit();
}

// Only landlords can create listings.
if ($decoded['role'] !== 'landlord' && $decoded['role'] !== 'admin') {
    http_response_code(403);  // 403 Forbidden — authenticated but not allowed
    echo json_encode(["message" => "Only landlords can create room listings."]);
    exit();
}

$data = json_decode(file_get_contents("php://input"));

if (empty($data->title) || empty($data->description) ||
    empty($data->price_per_month) || empty($data->location)) {
    http_response_code(400);
    echo json_encode(["message" => "Title, description, price, and location are required."]);
    exit();
}

$amenities = json_encode($data->amenities ?? []);
$images    = json_encode($data->images ?? []);

$stmt = $db->prepare(
    "INSERT INTO rooms (title, description, price_per_month, location, amenities, images, owner_id)
     VALUES (:title, :desc, :price, :location, :amenities, :images, :owner_id)"
);

$stmt->execute([
    ':title'     => trim($data->title),
    ':desc'      => trim($data->description),
    ':price'     => (float) $data->price_per_month,
    ':location'  => trim($data->location),
    ':amenities' => $amenities,
    ':images'    => $images,
    ':owner_id'  => $decoded['user_id'],
]);

http_response_code(201);
echo json_encode([
    "message" => "Room listing created successfully.",
    "room_id" => $db->lastInsertId()
]);
