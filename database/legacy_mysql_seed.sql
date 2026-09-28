-- =============================================================
-- Seed data: store settings + attribute definitions.
-- Products, categories content, orders and customers must be
-- created through the Admin dashboard (no fake commerce data).
-- The default admin password is NOT seeded here for security;
-- create it with:  php database/create_admin.php  (see README)
-- =============================================================
SET NAMES utf8mb4;

INSERT INTO settings (setting_key, setting_value) VALUES
 ('store_name','Ara Beddings'),
 ('store_description','Quality bedsheets, bedding & home essentials delivered across Pakistan.'),
 ('logo_text','Ara Beddings'),
 ('logo_image',''),
 ('favicon','/assets/img/favicon.svg'),
 ('currency_code','PKR'),
 ('currency_symbol','Rs.'),
 ('country','Pakistan'),
 ('language','en'),
 ('contact_phone','+92 300 0000000'),
 ('whatsapp_number','923000000000'),
 ('contact_email','info@example.com'),
 ('store_address','Main Bazaar, Lahore, Pakistan'),
 ('facebook_url',''),
 ('instagram_url',''),
 ('tiktok_url',''),
 ('youtube_url',''),
 ('twitter_url',''),
 ('shipping_mode','flat'),                -- flat | free_threshold | city | province
 ('shipping_flat_cost','250'),
 ('free_shipping_threshold','5000'),
 ('shipping_city_rates','{"Karachi":200,"Lahore":200,"Islamabad":250,"Rawalpindi":250,"Faisalabad":250,"Multan":300,"Peshawar":350,"Quetta":400,"Sialkot":300,"Gujranwala":250}'),
 ('shipping_province_rates','{"Punjab":250,"Sindh":250,"Khyber Pakhtunkhwa":350,"Balochistan":400,"Gilgit-Baltistan":450,"Azad Jammu & Kashmir":350}'),
 ('tax_enabled','0'),
 ('tax_rate','0'),
 ('cod_enabled','1'),
 ('bank_transfer_enabled','1'),
 ('easypaisa_enabled','1'),
 ('jazzcash_enabled','1'),
 ('bank_account_title',''),
 ('bank_account_name',''),
 ('bank_account_number',''),
 ('bank_iban',''),
 ('bank_name',''),
 ('easypaisa_number',''),
 ('easypaisa_title',''),
 ('jazzcash_number',''),
 ('jazzcash_title',''),
 ('return_policy_days','7'),
 ('return_policy','Unused items in original packaging may be returned within {days} days of delivery. Customized/sale items are non-returnable. Return shipping is customer''s responsibility unless the item is defective or wrong.'),
 ('terms_conditions','By placing an order you agree to product prices, delivery timelines and our return policy as published on this store.'),
 ('privacy_policy','We collect only the information needed to process and deliver your order. We never sell your data.'),
 ('hero_title','Comfort That Feels Like Home'),
 ('hero_subtitle','Premium bedsheets & bedding — Cash on Delivery across Pakistan'),
 ('hero_image','/assets/img/hero.svg'),
 ('hero_button_text','Shop Now'),
 ('show_home_sections','featured,new,bestsellers,categories,reviews,trust,payment,newsletter'),
 ('products_per_page','12'),
 ('low_stock_default','5')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

INSERT INTO attributes (name, slug, type, sort_order) VALUES
 ('Size','size','select',1),
 ('Color','color','select',2),
 ('Fabric','fabric','select',3),
 ('Pack Size','pack-size','select',4),
 ('Design','design','select',5)
ON DUPLICATE KEY UPDATE name=VALUES(name);

INSERT INTO attribute_values (attribute_id, value, sort_order)
SELECT id, v.n, x FROM attributes a
JOIN (SELECT 'King' n UNION SELECT 'Queen' UNION SELECT 'Single' UNION SELECT 'Double') v ON a.slug='size'
CROSS JOIN (SELECT 0 x) d ON DUPLICATE KEY UPDATE value=v.n;

INSERT INTO attribute_values (attribute_id, value, sort_order)
SELECT id, v.n, 0 FROM attributes a
JOIN (SELECT 'Blue' n UNION SELECT 'White' UNION SELECT 'Green' UNION SELECT 'Grey' UNION SELECT 'Maroon') v ON a.slug='color'
ON DUPLICATE KEY UPDATE value=v.n;

INSERT INTO attribute_values (attribute_id, value, sort_order)
SELECT id, v.n, 0 FROM attributes a
JOIN (SELECT 'Cotton' n UNION SELECT 'Flannel' UNION SELECT 'Silk' UNION SELECT 'Poly-Cotton') v ON a.slug='fabric'
ON DUPLICATE KEY UPDATE value=v.n;

INSERT IGNORE INTO categories (name, slug, description, sort_order) VALUES
 ('Bedsheets','bedsheets','Bedsheets in all sizes',1),
 ('Bedding Sets','bedding-sets','Complete bedding sets',2),
 ('Towels','towels','Bath & face towels',3),
 ('Cushion Covers','cushion-covers','Cushion covers & throws',4);
