-- Default content: site settings, landing page sections and Metro Manila locations.
-- install.php runs this once on a fresh database.

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('site_name', 'K&M Urban Properties'),
('tagline', 'Your City. Your Space. Your Home.'),
('meta_description', 'Condominium rentals and property sales in Metro Manila, Philippines. Find your perfect urban home with K&M Urban Properties.'),
('phone', '+63 917 000 0000'),
('phone_alt', ''),
('email', 'hello@kmurbanproperties.com'),
('address', 'Metro Manila, Philippines'),
('office_hours', 'Mon – Sat, 9:00 AM – 6:00 PM'),
('facebook', ''),
('instagram', ''),
('tiktok', ''),
('viber', ''),
('whatsapp', ''),
('map_embed', ''),
('footer_about', 'K&M Urban Properties helps you find condominium units for rent and for sale across Metro Manila — with transparent, hassle-free service from viewing to move-in.'),
('currency', '₱'),
('logo', ''),
('logo_dark', ''),
('notify_email', '');

INSERT IGNORE INTO landing_sections (section_key, label, title, highlight, subtitle, body, image, button_text, button_link, item_limit, is_active, sort_order) VALUES
('hero', 'Hero banner', 'Your City. Your Space.', 'Your Home.', NULL, 'Discover the best condominium rentals and properties for sale in Metro Manila.', NULL, NULL, NULL, 0, 1, 10),
('features', 'Trust highlights', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, 20),
('featured_rent', 'Featured rentals', 'Condos for Rent', NULL, 'Move-in ready units in prime Metro Manila locations', NULL, NULL, 'View all rentals', 'properties.php?type=rent', 6, 1, 30),
('services', 'Our services', 'What We Do', NULL, 'Rentals and sales — handled end to end', NULL, NULL, NULL, NULL, 0, 1, 40),
('featured_sale', 'Featured for sale', 'Condos for Sale', NULL, 'Invest in your own space in the city', NULL, NULL, 'View all for sale', 'properties.php?type=sale', 6, 1, 50),
('locations', 'Locations', 'Prime Metro Manila Locations', NULL, 'Find a home close to work, school and the city life you love', NULL, NULL, NULL, NULL, 8, 1, 60),
('about', 'About us', 'Your Trusted Urban Real Estate Partner', NULL, 'About K&M Urban Properties', 'We are a Metro Manila real estate team focused on condominium living. Whether you are renting your first studio, upgrading to a family-sized unit, or investing in property, we guide you with honest advice, verified listings and a smooth, paperwork-ready process.', NULL, 'Learn more', 'about.php', 0, 1, 70),
('stats', 'Numbers', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, 80),
('testimonials', 'Testimonials', 'What Our Clients Say', NULL, 'Happy tenants, buyers and owners', NULL, NULL, NULL, NULL, 0, 1, 90),
('cta', 'List your property banner', 'Own a condo in Metro Manila?', NULL, NULL, 'Let us find qualified tenants or buyers for your unit. We handle marketing, viewings, screening and paperwork.', NULL, 'List Your Property', 'list-property.php', 0, 1, 100),
('contact', 'Contact', 'Get in Touch', NULL, 'Tell us what you are looking for and we will get back to you within the day.', NULL, NULL, NULL, NULL, 0, 1, 110);

INSERT INTO section_items (section_id, icon, title, subtitle, text, sort_order)
SELECT id, 'bi-hand-thumbs-up', 'Trusted', 'Real Estate Partner', NULL, 1 FROM landing_sections WHERE section_key='features' UNION ALL
SELECT id, 'bi-buildings', 'Prime Metro Manila', 'Locations', NULL, 2 FROM landing_sections WHERE section_key='features' UNION ALL
SELECT id, 'bi-shield-check', 'Transparent &', 'Hassle-Free Process', NULL, 3 FROM landing_sections WHERE section_key='features' UNION ALL
SELECT id, 'bi-house-heart', 'Find Your Perfect', 'Urban Home', NULL, 4 FROM landing_sections WHERE section_key='features' UNION ALL
SELECT id, 'bi-key', 'Condominium Rentals', NULL, 'Studios to 3-bedroom units, furnished or bare, on short or long-term leases. We arrange viewings, screen applications and prepare your lease.', 1 FROM landing_sections WHERE section_key='services' UNION ALL
SELECT id, 'bi-house-check', 'Property Sales', NULL, 'Pre-selling and ready-for-occupancy condos. We help you compare projects, compute payment terms and complete the transfer documents.', 2 FROM landing_sections WHERE section_key='services' UNION ALL
SELECT id, 'bi-clipboard-check', 'Property Management', NULL, 'For owners: tenant sourcing, rent collection, repairs and maintenance, and regular reports — so your investment runs itself.', 3 FROM landing_sections WHERE section_key='services' UNION ALL
SELECT id, NULL, '500+', NULL, 'Happy tenants', 1 FROM landing_sections WHERE section_key='stats' UNION ALL
SELECT id, NULL, '120+', NULL, 'Units sold', 2 FROM landing_sections WHERE section_key='stats' UNION ALL
SELECT id, NULL, '15', NULL, 'Cities covered', 3 FROM landing_sections WHERE section_key='stats' UNION ALL
SELECT id, NULL, '10 yrs', NULL, 'In Metro Manila real estate', 4 FROM landing_sections WHERE section_key='stats' UNION ALL
SELECT id, NULL, 'Andrea S.', 'Tenant, Makati', 'Very smooth from viewing to move-in. The team answered every question quickly and the paperwork was ready on the day.', 1 FROM landing_sections WHERE section_key='testimonials' UNION ALL
SELECT id, NULL, 'Mark R.', 'Buyer, BGC', 'They helped me compare three projects and explained the payment terms clearly. I closed on my first condo with no surprises.', 2 FROM landing_sections WHERE section_key='testimonials' UNION ALL
SELECT id, NULL, 'Liza T.', 'Unit owner, Ortigas', 'My unit was rented within two weeks and they take care of repairs and collections. Highly recommended for owners abroad.', 3 FROM landing_sections WHERE section_key='testimonials';

INSERT IGNORE INTO locations (name, slug, description, sort_order) VALUES
('Makati', 'makati', 'Central business district living', 1),
('Bonifacio Global City', 'bgc', 'Taguig''s modern urban hub', 2),
('Ortigas', 'ortigas', 'Pasig & Mandaluyong business center', 3),
('Quezon City', 'quezon-city', 'Schools, malls and green spaces', 4),
('Manila', 'manila', 'Historic capital, universities and the bay', 5),
('Pasay', 'pasay', 'Near the airport and Mall of Asia', 6),
('Parañaque', 'paranaque', 'Bay area and entertainment city', 7),
('Mandaluyong', 'mandaluyong', 'Between Makati and Ortigas', 8);
