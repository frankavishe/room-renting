-- Seed data: 10 additional rooms in different Dar es Salaam neighborhoods
-- All owned by owner_id=5 (FRANK KAVISHE, landlord)

INSERT INTO rooms (title, description, price_per_month, location, amenities, images, owner_id, is_available) VALUES
('Spacious Room in Masaki', 'Bright, spacious room in the upscale Masaki area, close to embassies and restaurants.', 350000.00, 'Masaki, Dar es Salaam', '["WiFi","Water","Security","Parking"]', '[]', 5, 1),
('Affordable Room in Sinza', 'Simple and affordable room in a quiet Sinza neighborhood, great for students.', 150000.00, 'Sinza, Dar es Salaam', '["Water","Electricity"]', '[]', 5, 1),
('Modern Room near Mbezi Beach', 'Modern tiled room a short walk from Mbezi Beach with a private entrance.', 260000.00, 'Mbezi Beach, Dar es Salaam', '["WiFi","Water","Security"]', '[]', 5, 1),
('Central Room in Kariakoo', 'Well-located room in the heart of Kariakoo market district, ideal for traders.', 170000.00, 'Kariakoo, Dar es Salaam', '["Water","Electricity","Market Access"]', '[]', 5, 1),
('Quiet Room in Upanga', 'Peaceful room in Upanga with reliable backup power and easy access to the city center.', 300000.00, 'Upanga, Dar es Salaam', '["WiFi","Water","Security","Backup Generator"]', '[]', 5, 1),
('Budget Room in Mbagala', 'Budget-friendly room in Mbagala, suitable for individuals or small families.', 120000.00, 'Mbagala, Dar es Salaam', '["Water","Electricity"]', '[]', 5, 1),
('Riverside Room in Kigamboni', 'Comfortable room in Kigamboni with parking space and calm surroundings.', 200000.00, 'Kigamboni, Dar es Salaam', '["WiFi","Water","Parking"]', '[]', 5, 1),
('Convenient Room near Ubungo', 'Convenient room near Ubungo bus terminal, perfect for frequent travelers.', 160000.00, 'Ubungo, Dar es Salaam', '["Water","Electricity","Bus Stand Access"]', '[]', 5, 1),
('Coastal Room in Tegeta', 'Airy coastal room in Tegeta with steady WiFi and water supply.', 230000.00, 'Tegeta, Dar es Salaam', '["WiFi","Water","Security"]', '[]', 5, 1),
('Peaceful Room in Kunduchi', 'Peaceful room close to Kunduchi beach area with strong security.', 280000.00, 'Kunduchi, Dar es Salaam', '["WiFi","Water","Security","Beach Access"]', '[]', 5, 1);
