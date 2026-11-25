-- =====================================================================
-- YATIYA COUTURE - Authentic Haute Couture Catalogue Seed
-- Single-Brand Luxury Collection & Subcategories Taxonomy
-- =====================================================================

-- 1. Insert Subcategories
INSERT INTO `categories` (`parent_id`, `name`, `slug`, `description`, `image_url`, `display_order`, `is_featured`, `is_active`)
VALUES
(1, 'Bridal & Occasion Lehengas', 'women-lehengas', 'Heirloom zardozi and raw silk lehengas for weddings and festive galas.', 'https://images.unsplash.com/photo-1583391733956-3750e0ff4e8b?auto=format&fit=crop&w=800&q=80', 1, 1, 1),
(1, 'Handwoven Saris & Drapes', 'women-saris', 'Pure Banarasi Katan silk, tissue, and Chanderi artisanal saris.', 'https://images.unsplash.com/photo-1610030469983-98e550d6193c?auto=format&fit=crop&w=800&q=80', 2, 1, 1),
(1, 'Gowns & Anarkalis', 'women-gowns', 'Floor-length silk chiffon gowns and kalidar anarkali suits.', 'https://images.unsplash.com/photo-1566174053879-31528523f8ae?auto=format&fit=crop&w=800&q=80', 3, 1, 1),
(1, 'Tailored Blazers & Sets', 'women-blazers', 'Architecturally structured atelier blazers and silk coordinates.', 'https://images.unsplash.com/photo-1548624149-f7b3eb404a60?auto=format&fit=crop&w=800&q=80', 4, 1, 1),
(2, 'Imperial Bandhgalas', 'men-bandhgalas', 'Regal high-collar structured jackets tailored in fine merino wool and velvet.', 'https://images.unsplash.com/photo-1507679799987-c73779587ccf?auto=format&fit=crop&w=800&q=80', 1, 1, 1),
(2, 'Kurta Sets & Sherwanis', 'men-kurtas', 'Pure raw silk kurta ensembles and hand-embroidered wedding sherwanis.', 'https://images.unsplash.com/photo-1617137984095-74e4e5e3613f?auto=format&fit=crop&w=800&q=80', 2, 1, 1),
(2, 'Luxury Linen & Shirts', 'men-shirts', 'Crisp handloom organic linen and pure silk dress shirts.', 'https://images.unsplash.com/photo-1602810318383-e386cc2a3ccf?auto=format&fit=crop&w=800&q=80', 3, 1, 1),
(5, 'Handcrafted Juttis & Mojaris', 'footwear-mojaris', 'Full-grain calfskin mojaris with dabka and zardozi embroidery.', 'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?auto=format&fit=crop&w=800&q=80', 1, 1, 1),
(6, 'Minaudières & Evening Bags', 'bags-clutches', 'Jewelled brass minaudières and pearl-encrusted bridal potlis.', 'https://images.unsplash.com/photo-1584917865442-de89df76afd3?auto=format&fit=crop&w=800&q=80', 1, 1, 1),
(8, 'Heirloom Polki & Kundan', 'jewellery-chokers', 'Uncut gemstone polki chokers gilded in 24K pure gold leaf.', 'https://images.unsplash.com/photo-1515562141207-7a88fb7ce338?auto=format&fit=crop&w=800&q=80', 1, 1, 1)
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- 2. Insert Authentic Luxury Products
INSERT INTO `products` (
    `id`, `category_id`, `brand_id`, `name`, `slug`, `sku`, `short_description`, `description`, 
    `specifications`, `original_price`, `selling_price`, `cost_price`, `stock_quantity`, 
    `low_stock_threshold`, `is_featured`, `is_new`, `is_bestseller`, `average_rating`, `review_count`, `status`
) VALUES
-- Product 1: Women Bridal Lehenga
(
    1, 1, 1,
    'The Maharani Zardozi Velvet Lehenga',
    'maharani-zardozi-velvet-lehenga',
    'YTY-WMN-LHG-001',
    'Heirloom bridal lehenga sculpted in deep crimson silk velvet with over 450 hours of hand-embossed zardozi and real pearls.',
    'A masterpiece of royal Indian couture. The Maharani Lehenga is hand-embroidered by three master karigars using centuries-old karchobi frames in Old Delhi. Layered over pure Mulberry silk lining with an architectural cancan structure, paired with a matching embroidered blouse and a sheer tissue dupatta bordered in antique marodi zari.',
    '{"Fabric": "Pure Silk Velvet & Banarasi Tissue Dupatta", "Embroidery": "Hand Zardozi, Dabka & Untreated Seed Pearls", "Lining": "100% Mulberry Silk", "Origin": "Crafted in Delhi Atelier", "Care": "Specialist Dry Clean Only", "Includes": "Lehenga Skirt, Blouse, Dupatta, Garment Bag"}',
    89900.00, 74900.00, 32000.00, 8, 3, 1, 1, 1, 5.00, 0, 'active'
),

-- Product 2: Women Banarasi Silk Sari
(
    2, 1, 1,
    'Royal Katan Banarasi Silk Sari in Royal Champagne',
    'royal-katan-banarasi-silk-sari-champagne',
    'YTY-WMN-SRI-002',
    'Handloom Katan silk sari woven with real gold-plated silver zari in antique floral jaal motifs.',
    'Handwoven on traditional pit looms in Varanasi over 28 continuous days. The pallu features intricate Mughal hunting and floral jaal patterns. The drape flows with unmatched liquid grace, making it an heirloom to pass down through generations.',
    '{"Fabric": "100% Pure Katan Silk", "Zari": "Tested Silver-Gilt Zari", "Weave": "Kadwa Handloom Technique", "Length": "5.5 meters + 0.8 meter blouse piece", "Care": "Dry Clean Only, Store in Muslin"}',
    38900.00, 32500.00, 14000.00, 15, 4, 1, 1, 1, 4.90, 0, 'active'
),

-- Product 3: Women Silk Anarkali Suit
(
    3, 1, 3,
    'Chanderi Silk Kalidar Anarkali in Emerald Jade',
    'chanderi-silk-kalidar-anarkali-emerald',
    'YTY-WMN-AKL-003',
    '24-kali flared Anarkali handcrafted in organic Chanderi silk with gota patti and resham floral vines.',
    'Effortless royal volume. This 24-kali silhouette features a fitted bodice adorned with delicate hand gota work, teamed with silk churidar trousers and an ethereal organza dupatta showered in golden sitara motifs.',
    '{"Fabric": "Handspun Chanderi Silk & Pure Organza", "Work": "Gota Patti & Resham Embroidery", "Silhouette": "24-Kali Flared Anarkali", "Origin": "Jaipur Atelier", "Care": "Professional Dry Clean"}',
    28500.00, 24900.00, 9500.00, 12, 3, 1, 1, 0, 4.80, 0, 'active'
),

-- Product 4: Women Atelier Blazer
(
    4, 1, 2,
    'The Empress Structured Raw Silk Blazer in Obsidian',
    'empress-structured-raw-silk-blazer-obsidian',
    'YTY-WMN-BLZ-004',
    'Architectural peak-lapel blazer tailored from handspun raw Matka silk with hammered 24K gold buttons.',
    'Blurring royal heritage with contemporary power tailoring. Designed with sculpted shoulders, a nipped waistline, and handcrafted buttonholes, finished with our signature monogram jacquard lining.',
    '{"Fabric": "100% Organic Raw Matka Silk", "Buttons": "Hammered 24K Gold-Dipped Brass", "Lining": "Signature Monogram Cupro", "Fit": "Tailored Architectural Silhouette", "Care": "Dry Clean Only"}',
    24500.00, 19900.00, 7800.00, 10, 2, 1, 0, 1, 4.75, 0, 'active'
),

-- Product 5: Women Silk Chiffon Evening Gown
(
    5, 1, 2,
    'Aura Pleated Silk Chiffon Evening Gown in Ivory',
    'aura-pleated-silk-chiffon-evening-gown-ivory',
    'YTY-WMN-GWN-005',
    'Dramatic draped evening gown with sunray accordion pleats and hand-crystallized waist accent.',
    'Designed for gala soirées and red carpets. Lightweight silk chiffon cascades effortlessly from an asymmetrical cowl neckline into an ethereal train, cinched with an artisanal crystal-encrusted belt.',
    '{"Fabric": "100% Mulberry Silk Chiffon", "Details": "Hand-Pressed Sunray Pleating & Austrian Crystals", "Closure": "Concealed Side Zipper", "Care": "Specialist Couture Care"}',
    46000.00, 39900.00, 16000.00, 6, 2, 1, 1, 0, 5.00, 0, 'active'
),

-- Product 6: Men Imperial Bandhgala
(
    6, 2, 1,
    'The Viceroy Bespoke Bandhgala in Midnight Navy',
    'viceroy-bespoke-bandhgala-midnight-navy',
    'YTY-MEN-BDG-006',
    'Regal high-collar structured jacket crafted in Australian Super 150s merino wool with antique lion-crest buttons.',
    'The quintessential Indian formal jacket. Featuring a tailored chest piece, hand-padded collar, piped jetted pockets, and lined with smooth breathable silk cupro. Imparts an authoritative, stately presence.',
    '{"Fabric": "Super 150s Merino Wool & Velvet Accents", "Buttons": "Handcrafted Antique Brass Crest", "Lining": "Breathable Pure Silk Cupro", "Fit": "Regal Structured Tailoring", "Care": "Dry Clean Only"}',
    42000.00, 36000.00, 15000.00, 10, 3, 1, 1, 1, 4.95, 0, 'active'
),

-- Product 7: Men Royal Sherwani
(
    7, 2, 1,
    'The Nawab Raw Silk Wedding Sherwani in Ivory Gold',
    'nawab-raw-silk-wedding-sherwani-ivory',
    'YTY-MEN-SHR-007',
    'Royal wedding sherwani hand-embroidered with tonal resham, French knots and micro-sequins on pure tussar silk.',
    'Created for the discerning groom. Features a balanced flared kalidar skirt, regal Mandarin collar, concealed placket, and accompanied by a pure silk safa and stole with hand-tied tassels.',
    '{"Fabric": "Pure Tussar & Raw Silk", "Embroidery": "Tonal Resham & Micro Zari", "Includes": "Sherwani, Kurta, Churidar, Stole", "Origin": "Lucknow Atelier", "Care": "Dry Clean Only"}',
    68000.00, 58000.00, 24000.00, 5, 2, 1, 0, 1, 5.00, 0, 'active'
),

-- Product 8: Men Kurta Set
(
    8, 2, 3,
    'Artisanal Raw Silk Kurta Set in Sand Champagne',
    'artisanal-raw-silk-kurta-set-sand',
    'YTY-MEN-KRT-008',
    'Minimalist royal kurta set woven from organic Bhagalpur raw silk with mother-of-pearl buttons.',
    'A staple of refined Indian elegance. The tactile slub texture of unbleached raw silk provides natural drape and thermal comfort, paired with straight-cut silk pyjama trousers.',
    '{"Fabric": "100% Handloom Bhagalpur Raw Silk", "Buttons": "Genuine Carved Mother-of-Pearl", "Includes": "Kurta & Trousers", "Origin": "Crafted in Bihar & Delhi", "Care": "Gentle Handwash or Dry Clean"}',
    16500.00, 13900.00, 5200.00, 20, 5, 0, 1, 1, 4.70, 0, 'active'
),

-- Product 9: Men Linen Dress Shirt
(
    9, 2, 3,
    'Sartorial Pure Linen Spread Collar Shirt in White',
    'sartorial-pure-linen-spread-collar-shirt-white',
    'YTY-MEN-SHT-009',
    'Bespoke casual luxury shirt spun from long-staple French flax linen with single-needle stitching.',
    'Breathable everyday distinction. Features Australian shell buttons, removable collar stays, and an unrestrictive modern fit designed for year-round effortless refinement.',
    '{"Fabric": "100% European Certified Flax Linen", "Buttons": "Australian Mother-of-Pearl", "Stitch": "Single-Needle French Seams", "Care": "Machine Wash Cold, Hang Dry"}',
    8500.00, 6900.00, 2400.00, 25, 5, 0, 1, 0, 4.60, 0, 'active'
),

-- Product 10: Footwear Juttis
(
    10, 5, 1,
    'The Darbar Handcrafted Zardozi Leather Mojaris',
    'darbar-handcrafted-zardozi-leather-mojaris',
    'YTY-FTW-JUT-010',
    'Full-grain calfskin mojaris embroidered in antique gold dabka with cushioned memory insoles.',
    'Sculpted by 4th-generation shoemakers. The supple untreated leather molds naturally to the patron\'s feet, lined with soft suede to prevent bite and finished with a non-slip leather sole.',
    '{"Upper": "Calfskin Leather & Antique Dabka Zari", "Insole": "Double-Padded Memory Cushion", "Sole": "Hand-Stitched Argentine Leather", "Care": "Store with Shoe Trees"}',
    9500.00, 7900.00, 2800.00, 18, 4, 1, 1, 1, 4.90, 0, 'active'
),

-- Product 11: Footwear Sculpted Stilettos
(
    11, 5, 2,
    'The Versailles Sculpted Silk Satin Stilettos in Gold',
    'versailles-sculpted-silk-satin-stilettos-gold',
    'YTY-FTW-STL-011',
    'Pointed-toe stilettos wrapped in lustrous Duchess silk satin with a 90mm architectural golden heel.',
    'The pinnacle of evening glamour. Lined in soft Italian kidskin with an engineered pitch that balances poise with extended evening comfort.',
    '{"Upper": "Duchess Silk Satin", "Heel Height": "90mm Sculpted Metallic Pillar", "Lining": "100% Italian Kidskin", "Origin": "Handmade in Florence", "Care": "Wipe with Soft Dry Cloth"}',
    22000.00, 18500.00, 7200.00, 14, 3, 0, 1, 0, 4.85, 0, 'active'
),

-- Product 12: Bag Minaudière
(
    12, 6, 1,
    'The Noor Jewelled Brass Minaudière Clutch',
    'noor-jewelled-brass-minaudiere-clutch',
    'YTY-BAG-CLT-012',
    'Sculpted solid brass evening clutch inlaid with natural mother-of-pearl and detachable chain strap.',
    'Hand-cast and hand-polished by heirloom metal artisans. Fits all modern smartphones, cards, and compacts, lined with rich black velvet.',
    '{"Body": "Solid Cast Brass with 24K Gold Lacquer", "Inlay": "Natural Iridescent Mother-of-Pearl", "Lining": "Plush Velvet Interior", "Includes": "Detachable 105cm Snake Chain Strap"}',
    18500.00, 14900.00, 5500.00, 15, 4, 1, 1, 1, 5.00, 0, 'active'
),

-- Product 13: Bag Leather Tote
(
    13, 6, 2,
    'The Sovereign Italian Calfskin Weekender Bag in Tan',
    'sovereign-italian-calfskin-weekender-tan',
    'YTY-BAG-TOT-013',
    'Full-grain Tuscan vegetable-tanned leather duffle with Swiss Raccagni zippers and reinforced handles.',
    'Built for a lifetime of refined travel. Develops a rich, lustrous patina with every voyage. Features a dedicated padded laptop sleeve and waterproof shoe compartment.',
    '{"Leather": "Full-Grain Tuscan Vegetable-Tanned Cowhide", "Hardware": "Solid Brushed Brass & Raccagni Zips", "Dimensions": "52cm x 28cm x 26cm", "Volume": "38 Liters", "Care": "Condition with Leather Balm"}',
    34000.00, 28900.00, 11000.00, 8, 2, 1, 0, 1, 4.95, 0, 'active'
),

-- Product 14: Jewellery Polki Choker
(
    14, 8, 1,
    'The Rajkumari Heirloom Polki Choker Necklace',
    'rajkumari-heirloom-polki-choker-necklace',
    'YTY-JWL-CHK-014',
    '24K gold foil-gilded choker set with syndicate uncut polki diamonds, natural emeralds, and south sea pearls.',
    'Handcrafted in the Bikaner meenakari tradition. The reverse showcases exquisite multi-colored vitreous enamel depicting royal peacocks and lotus flowers.',
    '{"Setting": "24K Gold Foil Jadau Setting over Sterling Silver", "Stones": "Natural Syndicate Polki & Zambian Emeralds", "Beads": "Grade-AAA South Sea Pearls", "Includes": "Luxury Velvet Presentation Box & Certificate"}',
    54000.00, 46500.00, 19000.00, 4, 2, 1, 1, 1, 5.00, 0, 'active'
),

-- Product 15: Jewellery Chandbali Earrings
(
    15, 8, 1,
    'The Jharokha 24K Gold-Gilded Chandbali Earrings',
    'jharokha-24k-gold-gilded-chandbali-earrings',
    'YTY-JWL-EAR-015',
    'Archival crescent chandbalis handcrafted with uncut kundan stones, micro-pearl tassels, and ruby drops.',
    'A timeless tribute to Mughal architectural arches. Lightweight engineered post for comfortable extended wear during celebrations.',
    '{"Base": "92.5 Sterling Silver Plated in 24K Pure Gold", "Stones": "Uncut Kundan & Natural Cabochon Rubies", "Drop Length": "7.5 cm", "Weight": "28 grams per pair", "Care": "Keep away from perfumes"}',
    19500.00, 16200.00, 6000.00, 16, 4, 0, 1, 1, 4.80, 0, 'active'
),

-- Product 16: Accessories Pashmina Shawl
(
    16, 7, 3,
    'Kashmiri Pure Cashmere Pashmina Stole in Camel',
    'kashmiri-pure-cashmere-pashmina-stole-camel',
    'YTY-ACC-PSH-016',
    'Featherlight 100% handspun Changthangi cashmere stole with hand-needle sozni embroidery borders.',
    'Certified authentic GI-tagged Kashmiri Pashmina. Derived from the underfleece of high-altitude Himalayan goats, offering incomparable warmth and cloud-like lightness.',
    '{"Fiber": "100% Changthangi Mountain Goat Cashmere", "Weave": "Hand-Spun Diamond Weave (Chashm-e-Bulbul)", "Dimensions": "200cm x 70cm", "Origin": "Srinagar, Kashmir", "Care": "Professional Cashmere Dry Clean"}',
    21000.00, 17500.00, 6800.00, 12, 3, 1, 1, 1, 4.90, 0, 'active'
),

-- Product 17: Accessories Belt
(
    17, 7, 2,
    'The Atelier Monogram Reversible Leather Belt',
    'atelier-monogram-reversible-leather-belt',
    'YTY-ACC-BLT-017',
    '35mm reversible belt in black box calf and rich cognac leather with 24K gold-plated Yatiya buckle.',
    'Two essential luxury looks in one. The rotating buckle mechanism allows seamless switching between formal black and casual cognac leather.',
    '{"Leather": "French Box Calfskin & Cognac Grain Leather", "Buckle": "Solid Brass Plated in 24K Satin Gold", "Width": "35 mm", "Origin": "Handcrafted in Milan", "Care": "Wipe with Dry Cloth"}',
    11500.00, 9500.00, 3600.00, 22, 5, 0, 0, 1, 4.70, 0, 'active'
),

-- Product 18: Out of Stock Test Product (Kids Royal Ensemble)
(
    18, 3, 1,
    'Prince of Awadh Pure Silk Kurta Ensemble (Archived)',
    'prince-of-awadh-pure-silk-kurta-ensemble',
    'YTY-KID-KRT-018',
    'Limited edition miniature royal bandhgala and dhoti ensemble in raw Chanderi silk. Currently out of stock.',
    'Crafted with child-friendly soft silk seams and hypoallergenic lining. Hand-embroidered with miniature zari bootis for auspicious celebrations.',
    '{"Fabric": "100% Chanderi Silk with Organic Cotton Voile Lining", "Includes": "Kurta, Dhoti Pants, Dupatta", "Status": "Sold Out Archive Edition", "Care": "Gentle Dry Clean"}',
    14000.00, 11900.00, 4200.00, 0, 2, 0, 0, 0, 4.50, 0, 'active'
)
ON DUPLICATE KEY UPDATE 
    name=VALUES(name),
    selling_price=VALUES(selling_price),
    original_price=VALUES(original_price),
    stock_quantity=VALUES(stock_quantity),
    status=VALUES(status);

-- 3. Insert Product Images (Primary + Hover/Gallery Images)
INSERT INTO `product_images` (`product_id`, `image_url`, `alt_text`, `sort_order`, `is_primary`) VALUES
-- Product 1: Lehenga
(1, 'https://images.unsplash.com/photo-1583391733956-3750e0ff4e8b?auto=format&fit=crop&w=1000&q=80', 'Maharani Zardozi Velvet Lehenga Front View', 1, 1),
(1, 'https://images.unsplash.com/photo-1509631179647-0177331693ae?auto=format&fit=crop&w=1000&q=80', 'Maharani Zardozi Velvet Lehenga Editorial Back View', 2, 0),
(1, 'https://images.unsplash.com/photo-1539109136881-3be0616acf4b?auto=format&fit=crop&w=1000&q=80', 'Maharani Zardozi Hand Embroidery Detail', 3, 0),

-- Product 2: Banarasi Sari
(2, 'https://images.unsplash.com/photo-1610030469983-98e550d6193c?auto=format&fit=crop&w=1000&q=80', 'Royal Katan Banarasi Silk Sari Drape', 1, 1),
(2, 'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?auto=format&fit=crop&w=1000&q=80', 'Banarasi Silk Sari Pallu Detail', 2, 0),

-- Product 3: Anarkali
(3, 'https://images.unsplash.com/photo-1566174053879-31528523f8ae?auto=format&fit=crop&w=1000&q=80', 'Chanderi Silk Kalidar Anarkali Front Flare', 1, 1),
(3, 'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?auto=format&fit=crop&w=1000&q=80', 'Chanderi Silk Anarkali Dupatta Drape', 2, 0),

-- Product 4: Blazer
(4, 'https://images.unsplash.com/photo-1548624149-f7b3eb404a60?auto=format&fit=crop&w=1000&q=80', 'The Empress Structured Raw Silk Blazer Front', 1, 1),
(4, 'https://images.unsplash.com/photo-1490481651871-ab68de25d43d?auto=format&fit=crop&w=1000&q=80', 'Empress Silk Blazer Gold Button Close-up', 2, 0),

-- Product 5: Gown
(5, 'https://images.unsplash.com/photo-1445205170230-053b83016050?auto=format&fit=crop&w=1000&q=80', 'Aura Pleated Silk Chiffon Evening Gown Runway', 1, 1),
(5, 'https://images.unsplash.com/photo-1509631179647-0177331693ae?auto=format&fit=crop&w=1000&q=80', 'Aura Silk Chiffon Gown Back Draped Train', 2, 0),

-- Product 6: Men Bandhgala
(6, 'https://images.unsplash.com/photo-1507679799987-c73779587ccf?auto=format&fit=crop&w=1000&q=80', 'The Viceroy Bespoke Bandhgala Frontal Stance', 1, 1),
(6, 'https://images.unsplash.com/photo-1617137984095-74e4e5e3613f?auto=format&fit=crop&w=1000&q=80', 'The Viceroy Bandhgala Crest Buttons Detail', 2, 0),

-- Product 7: Men Sherwani
(7, 'https://images.unsplash.com/photo-1617137984095-74e4e5e3613f?auto=format&fit=crop&w=1000&q=80', 'The Nawab Raw Silk Wedding Sherwani Front', 1, 1),
(7, 'https://images.unsplash.com/photo-1507679799987-c73779587ccf?auto=format&fit=crop&w=1000&q=80', 'Nawab Sherwani Embroidery Texture', 2, 0),

-- Product 8: Men Kurta Set
(8, 'https://images.unsplash.com/photo-1602810318383-e386cc2a3ccf?auto=format&fit=crop&w=1000&q=80', 'Artisanal Raw Silk Kurta Set Model View', 1, 1),
(8, 'https://images.unsplash.com/photo-1507679799987-c73779587ccf?auto=format&fit=crop&w=1000&q=80', 'Raw Silk Texture & Mother of Pearl Buttons', 2, 0),

-- Product 9: Men Linen Shirt
(9, 'https://images.unsplash.com/photo-1602810318383-e386cc2a3ccf?auto=format&fit=crop&w=1000&q=80', 'Sartorial Pure Linen Spread Collar Shirt', 1, 1),
(9, 'https://images.unsplash.com/photo-1507679799987-c73779587ccf?auto=format&fit=crop&w=1000&q=80', 'Pure French Flax Linen Texture', 2, 0),

-- Product 10: Footwear Mojaris
(10, 'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?auto=format&fit=crop&w=1000&q=80', 'The Darbar Handcrafted Zardozi Mojaris Pair', 1, 1),
(10, 'https://images.unsplash.com/photo-1584917865442-de89df76afd3?auto=format&fit=crop&w=1000&q=80', 'Mojari Leather Insole and Dabka Embroidery', 2, 0),

-- Product 11: Footwear Stilettos
(11, 'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?auto=format&fit=crop&w=1000&q=80', 'The Versailles Sculpted Silk Satin Stilettos', 1, 1),
(11, 'https://images.unsplash.com/photo-1515562141207-7a88fb7ce338?auto=format&fit=crop&w=1000&q=80', 'Versailles Stiletto Golden Sculpted Pillar', 2, 0),

-- Product 12: Bag Minaudière
(12, 'https://images.unsplash.com/photo-1584917865442-de89df76afd3?auto=format&fit=crop&w=1000&q=80', 'The Noor Jewelled Brass Minaudière Clutch', 1, 1),
(12, 'https://images.unsplash.com/photo-1515562141207-7a88fb7ce338?auto=format&fit=crop&w=1000&q=80', 'Noor Clutch Mother of Pearl Inlay Open View', 2, 0),

-- Product 13: Bag Tote
(13, 'https://images.unsplash.com/photo-1584917865442-de89df76afd3?auto=format&fit=crop&w=1000&q=80', 'The Sovereign Italian Calfskin Weekender Bag', 1, 1),
(13, 'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?auto=format&fit=crop&w=1000&q=80', 'Sovereign Weekender Solid Brass Hardware', 2, 0),

-- Product 14: Polki Choker
(14, 'https://images.unsplash.com/photo-1515562141207-7a88fb7ce338?auto=format&fit=crop&w=1000&q=80', 'The Rajkumari Heirloom Polki Choker Necklace', 1, 1),
(14, 'https://images.unsplash.com/photo-1539109136881-3be0616acf4b?auto=format&fit=crop&w=1000&q=80', 'Rajkumari Choker Meenakari Enamel Back', 2, 0),

-- Product 15: Earrings
(15, 'https://images.unsplash.com/photo-1515562141207-7a88fb7ce338?auto=format&fit=crop&w=1000&q=80', 'The Jharokha 24K Gold-Gilded Chandbali Earrings', 1, 1),
(15, 'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?auto=format&fit=crop&w=1000&q=80', 'Jharokha Chandbalis Pearl Tassels Close-up', 2, 0),

-- Product 16: Pashmina Shawl
(16, 'https://images.unsplash.com/photo-1608256246200-53e635b5b65f?auto=format&fit=crop&w=1000&q=80', 'Kashmiri Pure Cashmere Pashmina Stole in Camel', 1, 1),
(16, 'https://images.unsplash.com/photo-1490481651871-ab68de25d43d?auto=format&fit=crop&w=1000&q=80', 'Hand-needle Sozni Embroidery Border', 2, 0),

-- Product 17: Belt
(17, 'https://images.unsplash.com/photo-1608256246200-53e635b5b65f?auto=format&fit=crop&w=1000&q=80', 'The Atelier Monogram Reversible Leather Belt', 1, 1),
(17, 'https://images.unsplash.com/photo-1584917865442-de89df76afd3?auto=format&fit=crop&w=1000&q=80', 'Reversible Rotating 24K Gold Monogram Buckle', 2, 0),

-- Product 18: Out of Stock Kids
(18, 'https://images.unsplash.com/photo-1622290291468-a28f7a7dc6a8?auto=format&fit=crop&w=1000&q=80', 'Prince of Awadh Pure Silk Kurta Ensemble Model', 1, 1)
ON DUPLICATE KEY UPDATE 
    image_url=VALUES(image_url),
    is_primary=VALUES(is_primary);

-- 4. Insert Variants (Sizes: XS, S, M, L, XL, XXL; Colors: Obsidian, Champagne, Ivory, Emerald, Crimson, etc.)
INSERT INTO `product_variants` (`product_id`, `sku`, `size`, `color`, `color_code`, `additional_price`, `stock_quantity`, `status`) VALUES
-- Product 1 Variants (Lehenga: S, M, L in Crimson & Champagne)
(1, 'YTY-WMN-LHG-001-S-CRIMSON', 'S', 'Royal Crimson', '#962D2D', 0.00, 3, 'active'),
(1, 'YTY-WMN-LHG-001-M-CRIMSON', 'M', 'Royal Crimson', '#962D2D', 0.00, 3, 'active'),
(1, 'YTY-WMN-LHG-001-L-CRIMSON', 'L', 'Royal Crimson', '#962D2D', 0.00, 2, 'active'),
(1, 'YTY-WMN-LHG-001-M-CHAMPAGNE', 'M', 'Royal Champagne', '#D6BC7A', 2000.00, 0, 'active'), -- Sold out size for testing

-- Product 2 Variants (Sari: Free Size in Champagne, Ivory, Gold)
(2, 'YTY-WMN-SRI-002-FS-CHAMPAGNE', 'Free Size', 'Royal Champagne', '#D6BC7A', 0.00, 8, 'active'),
(2, 'YTY-WMN-SRI-002-FS-IVORY', 'Free Size', 'Ivory Silk', '#F7F4ED', 0.00, 7, 'active'),

-- Product 3 Variants (Anarkali: XS, S, M, L, XL)
(3, 'YTY-WMN-AKL-003-XS-EMERALD', 'XS', 'Emerald Jade', '#1B4D3E', 0.00, 2, 'active'),
(3, 'YTY-WMN-AKL-003-S-EMERALD', 'S', 'Emerald Jade', '#1B4D3E', 0.00, 4, 'active'),
(3, 'YTY-WMN-AKL-003-M-EMERALD', 'M', 'Emerald Jade', '#1B4D3E', 0.00, 4, 'active'),
(3, 'YTY-WMN-AKL-003-L-EMERALD', 'L', 'Emerald Jade', '#1B4D3E', 0.00, 2, 'active'),

-- Product 4 Variants (Blazer: S, M, L, XL in Obsidian)
(4, 'YTY-WMN-BLZ-004-S-OBSIDIAN', 'S', 'Obsidian Black', '#1A1816', 0.00, 3, 'active'),
(4, 'YTY-WMN-BLZ-004-M-OBSIDIAN', 'M', 'Obsidian Black', '#1A1816', 0.00, 4, 'active'),
(4, 'YTY-WMN-BLZ-004-L-OBSIDIAN', 'L', 'Obsidian Black', '#1A1816', 0.00, 3, 'active'),

-- Product 5 Variants (Gown: S, M, L in Ivory)
(5, 'YTY-WMN-GWN-005-S-IVORY', 'S', 'Ivory Silk', '#F7F4ED', 0.00, 2, 'active'),
(5, 'YTY-WMN-GWN-005-M-IVORY', 'M', 'Ivory Silk', '#F7F4ED', 0.00, 3, 'active'),
(5, 'YTY-WMN-GWN-005-L-IVORY', 'L', 'Ivory Silk', '#F7F4ED', 0.00, 1, 'active'),

-- Product 6 Variants (Bandhgala: 38, 40, 42, 44 in Navy & Obsidian)
(6, 'YTY-MEN-BDG-006-38-NAVY', '38R', 'Midnight Navy', '#1E2A4A', 0.00, 3, 'active'),
(6, 'YTY-MEN-BDG-006-40-NAVY', '40R', 'Midnight Navy', '#1E2A4A', 0.00, 4, 'active'),
(6, 'YTY-MEN-BDG-006-42-NAVY', '42R', 'Midnight Navy', '#1E2A4A', 0.00, 3, 'active'),

-- Product 7 Variants (Sherwani: 38, 40, 42 in Ivory)
(7, 'YTY-MEN-SHR-007-38-IVORY', '38R', 'Ivory Gold', '#F7F4ED', 0.00, 2, 'active'),
(7, 'YTY-MEN-SHR-007-40-IVORY', '40R', 'Ivory Gold', '#F7F4ED', 0.00, 2, 'active'),
(7, 'YTY-MEN-SHR-007-42-IVORY', '42R', 'Ivory Gold', '#F7F4ED', 0.00, 1, 'active'),

-- Product 8 Variants (Kurta: S, M, L, XL in Sand Champagne)
(8, 'YTY-MEN-KRT-008-S-SAND', 'S', 'Sand Champagne', '#D6BC7A', 0.00, 5, 'active'),
(8, 'YTY-MEN-KRT-008-M-SAND', 'M', 'Sand Champagne', '#D6BC7A', 0.00, 6, 'active'),
(8, 'YTY-MEN-KRT-008-L-SAND', 'L', 'Sand Champagne', '#D6BC7A', 0.00, 5, 'active'),
(8, 'YTY-MEN-KRT-008-XL-SAND', 'XL', 'Sand Champagne', '#D6BC7A', 0.00, 4, 'active'),

-- Product 9 Variants (Linen Shirt: S, M, L, XL in White & Navy)
(9, 'YTY-MEN-SHT-009-S-WHITE', 'S', 'Optical White', '#FFFFFF', 0.00, 7, 'active'),
(9, 'YTY-MEN-SHT-009-M-WHITE', 'M', 'Optical White', '#FFFFFF', 0.00, 8, 'active'),
(9, 'YTY-MEN-SHT-009-L-WHITE', 'L', 'Optical White', '#FFFFFF', 0.00, 6, 'active'),
(9, 'YTY-MEN-SHT-009-XL-WHITE', 'XL', 'Optical White', '#FFFFFF', 0.00, 4, 'active'),

-- Product 10 Variants (Mojari: 40, 41, 42, 43, 44)
(10, 'YTY-FTW-JUT-010-40-GOLD', 'EU 40 / UK 7', 'Antique Gold', '#C6A15B', 0.00, 4, 'active'),
(10, 'YTY-FTW-JUT-010-41-GOLD', 'EU 41 / UK 8', 'Antique Gold', '#C6A15B', 0.00, 5, 'active'),
(10, 'YTY-FTW-JUT-010-42-GOLD', 'EU 42 / UK 9', 'Antique Gold', '#C6A15B', 0.00, 5, 'active'),
(10, 'YTY-FTW-JUT-010-43-GOLD', 'EU 43 / UK 10', 'Antique Gold', '#C6A15B', 0.00, 4, 'active'),

-- Product 11 Variants (Stilettos: 36, 37, 38, 39, 40)
(11, 'YTY-FTW-STL-011-37-GOLD', 'EU 37', 'Satin Gold', '#D6BC7A', 0.00, 5, 'active'),
(11, 'YTY-FTW-STL-011-38-GOLD', 'EU 38', 'Satin Gold', '#D6BC7A', 0.00, 5, 'active'),
(11, 'YTY-FTW-STL-011-39-GOLD', 'EU 39', 'Satin Gold', '#D6BC7A', 0.00, 4, 'active'),

-- Product 12 Variants (Clutch: One Size)
(12, 'YTY-BAG-CLT-012-OS-GOLD', 'One Size', 'Polished Brass Gold', '#C6A15B', 0.00, 15, 'active'),

-- Product 13 Variants (Weekender: One Size in Tan & Black)
(13, 'YTY-BAG-TOT-013-OS-TAN', 'One Size', 'Cognac Tan', '#8B5A2B', 0.00, 5, 'active'),
(13, 'YTY-BAG-TOT-013-OS-BLACK', 'One Size', 'Obsidian Black', '#1A1816', 0.00, 3, 'active'),

-- Product 14 Variants (Choker: One Size)
(14, 'YTY-JWL-CHK-014-OS-GOLD', 'One Size', '24K Gold Kundan', '#C6A15B', 0.00, 4, 'active'),

-- Product 15 Variants (Chandbali: One Size)
(15, 'YTY-JWL-EAR-015-OS-GOLD', 'One Size', '24K Gold Plated', '#C6A15B', 0.00, 16, 'active'),

-- Product 16 Variants (Pashmina: One Size)
(16, 'YTY-ACC-PSH-016-OS-CAMEL', 'One Size', 'Natural Camel', '#C19A6B', 0.00, 12, 'active'),

-- Product 17 Variants (Belt: 85, 90, 95, 100)
(17, 'YTY-ACC-BLT-017-90-BLACK', '90 cm', 'Black / Cognac', '#1A1816', 0.00, 8, 'active'),
(17, 'YTY-ACC-BLT-017-95-BLACK', '95 cm', 'Black / Cognac', '#1A1816', 0.00, 8, 'active'),
(17, 'YTY-ACC-BLT-017-100-BLACK', '100 cm', 'Black / Cognac', '#1A1816', 0.00, 6, 'active'),

-- Product 18 Variants (Out of Stock Item: 0 quantity)
(18, 'YTY-KID-KRT-018-2Y-GOLD', 'Age 2-3 Y', 'Royal Silk Gold', '#D6BC7A', 0.00, 0, 'active')
ON DUPLICATE KEY UPDATE 
    size=VALUES(size),
    color=VALUES(color),
    stock_quantity=VALUES(stock_quantity),
    status=VALUES(status);

