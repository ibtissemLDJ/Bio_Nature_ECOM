-- exported from php my admin
-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 15, 2025 at 12:54 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `nescare`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `finalize_order_from_cart` (IN `p_user_id` INT, IN `p_shipping_address` TEXT, IN `p_billing_address` TEXT, IN `p_payment_method` VARCHAR(255))   BEGIN
    DECLARE v_cart_id INT;
    DECLARE v_total_amount DECIMAL(10, 2);
    DECLARE v_new_order_id INT;

    START TRANSACTION;

    SELECT cart_id INTO v_cart_id FROM carts WHERE user_id = p_user_id LIMIT 1;

    IF v_cart_id IS NULL OR NOT EXISTS (SELECT 1 FROM cart_items WHERE cart_id = v_cart_id) THEN
        ROLLBACK;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot finalize order: Cart is empty or invalid.';
    END IF;

    SELECT SUM(ci.quantity * i.price) INTO v_total_amount
    FROM cart_items ci
    JOIN items i ON ci.item_id = i.item_id
    WHERE ci.cart_id = v_cart_id;

    INSERT INTO orders (user_id, total_amount, status, shipping_address, billing_address, payment_method)
    VALUES (p_user_id, v_total_amount, 'Pending', p_shipping_address, p_billing_address, p_payment_method);

    SET v_new_order_id = LAST_INSERT_ID();

    INSERT INTO order_items (order_id, item_id, quantity, price_at_order)
    SELECT v_new_order_id, item_id, quantity, (SELECT price FROM items WHERE item_id = ci.item_id)
    FROM cart_items ci
    WHERE cart_id = v_cart_id;

    DELETE FROM cart_items WHERE cart_id = v_cart_id;

    COMMIT;

    SELECT v_new_order_id AS new_order_id;

END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `get_customer_order_history` (IN `p_user_id` INT)   BEGIN
    SELECT
        order_id,
        order_date,
        total_amount,
        status
    FROM orders
    WHERE user_id = p_user_id
    ORDER BY order_date DESC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `get_order_details` (IN `p_order_id` INT, IN `p_user_id` INT)   BEGIN
    SELECT
        o.order_id,
        o.user_id,
        o.order_date,
        o.total_amount,
        o.status,
        o.shipping_address,
        o.billing_address,
        o.payment_method,
        u.username AS customer_username,
        u.email AS customer_email
    FROM orders o
    JOIN users u ON o.user_id = u.user_id
    WHERE o.order_id = p_order_id AND o.user_id = p_user_id;

    SELECT
        oi.order_item_id,
        oi.item_id,
        i.name AS item_name,
        oi.quantity,
        oi.price_at_order,
        (oi.quantity * oi.price_at_order) AS item_subtotal
    FROM order_items oi
    JOIN items i ON oi.item_id = i.item_id
    WHERE oi.order_id = p_order_id;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `admin_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`admin_id`, `username`, `password`, `full_name`, `last_login`, `created_at`) VALUES
(1, 'admin', 'admin123', 'Admin User', '2025-05-14 22:39:28', '2025-05-07 20:44:47');

-- --------------------------------------------------------

--
-- Table structure for table `admin_todos`
--

CREATE TABLE `admin_todos` (
  `id` int(11) NOT NULL,
  `task` varchar(255) NOT NULL,
  `is_completed` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blog_posts`
--

CREATE TABLE `blog_posts` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `excerpt` text NOT NULL,
  `content` longtext NOT NULL,
  `featured_image` varchar(255) NOT NULL,
  `is_featured` tinyint(1) DEFAULT 0,
  `publish_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `blog_posts`
--

INSERT INTO `blog_posts` (`id`, `title`, `excerpt`, `content`, `featured_image`, `is_featured`, `publish_date`, `created_at`, `updated_at`) VALUES
(4, '10 Must-Have Organic Skincare Products for Glowing Skin', 'Discover our top picks for organic skincare that will give you a natural glow without harsh chemicals.', 'Achieving radiant skin doesn\'t require harsh chemicals. Our curated list of 10 organic skincare products includes nourishing cleansers, hydrating serums, and protective moisturizers. The Green Tea Cleanser gently removes impurities while antioxidant-rich. The Hyaluronic Acid Serum provides deep hydration, plumping fine lines. Don\'t forget our best-selling Rosehip Oil, packed with vitamins A and C to rejuvenate your complexion. All products are cruelty-free and sustainably sourced.', 'https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9?w=600&auto=format', 1, '2023-05-15', '2025-05-08 12:46:35', '2025-05-08 12:46:35'),
(5, 'The Science Behind Hyaluronic Acid: Why Your Skin Needs It', 'Learn how hyaluronic acid works its magic to keep your skin plump and hydrated all day long.', 'Hyaluronic acid is a skincare superstar that can hold up to 1000 times its weight in water. This powerful humectant works by drawing moisture from the environment into your skin, creating a plumping effect that reduces the appearance of fine lines. Our Advanced HA Serum combines low, medium, and high molecular weight hyaluronic acid for multi-depth hydration. Use it morning and night after cleansing for best results. Pair with our Ceramide Moisturizer to lock in hydration for up to 72 hours.', 'https://images.unsplash.com/photo-1571781926291-c477ebfd024b?w=600&auto=format', 0, '2023-06-02', '2025-05-08 12:46:35', '2025-05-08 12:46:35'),
(6, 'DIY Face Masks Using Ingredients From Your Kitchen', 'Pamper your skin with these easy-to-make face masks using natural ingredients you already have at home.', 'Before commercial skincare, people relied on kitchen ingredients for beautiful skin. Try our Honey & Yogurt Mask: mix 1 tbsp raw honey with 2 tbsp plain yogurt for a soothing, brightening treatment. For oily skin, combine 1 mashed banana with 1 tsp lemon juice. Our favorite exfoliating mask uses coffee grounds mixed with coconut oil. Always patch test first! While these DIY solutions are great occasionally, for consistent results we recommend our Professional-Grade Clay Mask with kaolin and bentonite clay.', 'https://images.unsplash.com/photo-1556228578-8c89e6adf883?w=600&auto=format', 1, '2023-06-18', '2025-05-08 12:46:35', '2025-05-08 12:46:35'),
(7, 'How to Build the Perfect Anti-Aging Routine in Your 30s', 'Start preventing signs of aging now with this simple yet effective skincare routine tailored for your 30s.', 'Your 30s are the perfect time to establish an anti-aging routine. Start with our Vitamin C Cleanser to brighten and protect. Follow with our Peptide Complex Serum to stimulate collagen production. The Retinol Night Cream (use 2-3 times weekly) accelerates cell turnover. Don\'t skip eye cream - our Caffeine Eye Serum reduces puffiness and dark circles. Finish with SPF 50+ sunscreen daily, even when cloudy. This 5-step routine takes just minutes but delivers visible results within weeks. Consistency is key!', 'https://images.unsplash.com/photo-1596755094514-f87e34085b2c?w=600&auto=format', 0, '2023-07-05', '2025-05-08 12:46:35', '2025-05-08 12:46:35'),
(8, 'The Truth About Mineral vs Chemical Sunscreens', 'We break down the differences between mineral and chemical sunscreens to help you choose what\'s best for your skin.', 'Mineral sunscreens (with zinc oxide or titanium dioxide) sit on skin\'s surface, physically blocking UV rays. They\'re ideal for sensitive skin but can leave a white cast. Chemical sunscreens absorb UV rays through chemical reactions. Our Mineral Defense SPF 50 offers broad-spectrum protection without irritation. For active lifestyles, our Clear Screen Chemical SPF absorbs quickly. Both types are effective when applied properly (1/4 tsp for face, reapplied every 2 hours). We recommend mineral for children and chemical for deeper skin tones.', 'https://images.unsplash.com/photo-1556228453-efd6c1ff04f6?w=600&auto=format', 0, '2023-07-22', '2025-05-08 12:46:35', '2025-05-08 12:46:35'),
(9, 'Nighttime Skincare Rituals for Your Best Skin Ever', 'Transform your skin while you sleep with these nighttime skincare practices and product recommendations.', 'Nighttime is when your skin repairs itself. Start with our Double Cleanse Method: first remove makeup with our Botanical Cleansing Oil, then cleanse with our Creamy Face Wash. Apply our Night Repair Serum with niacinamide and peptides. The Rich Renewal Cream locks in moisture with ceramides and squalane. For extra care, use our Jade Roller to boost circulation before bed. Silk pillowcases reduce friction that can cause wrinkles. Remember, consistency with your nighttime routine yields better results than any single expensive treatment!', 'https://images.unsplash.com/photo-1540555700478-4be289fbecef?w=600&auto=format', 1, '2023-08-10', '2025-05-08 12:46:35', '2025-05-08 12:46:35');

-- --------------------------------------------------------

--
-- Table structure for table `cancelled_orders_history`
--

CREATE TABLE `cancelled_orders_history` (
  `history_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `cancellation_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `reason` text DEFAULT NULL,
  `cancelled_by` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cancelled_orders_history`
--

INSERT INTO `cancelled_orders_history` (`history_id`, `order_id`, `user_id`, `cancellation_date`, `reason`, `cancelled_by`) VALUES
(1, 8, 3, '2025-05-08 18:50:15', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `carts`
--

CREATE TABLE `carts` (
  `cart_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `carts`
--

INSERT INTO `carts` (`cart_id`, `user_id`, `created_at`, `updated_at`) VALUES
(1, 3, '2025-05-07 06:58:20', '2025-05-07 06:58:20'),
(2, 4, '2025-05-08 10:43:59', '2025-05-08 10:43:59'),
(3, 5, '2025-05-12 08:10:23', '2025-05-12 08:10:23');

-- --------------------------------------------------------

--
-- Table structure for table `cart_items`
--

CREATE TABLE `cart_items` (
  `cart_item_id` int(11) NOT NULL,
  `cart_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL CHECK (`quantity` >= 1),
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cart_items`
--

INSERT INTO `cart_items` (`cart_item_id`, `cart_id`, `item_id`, `quantity`, `added_at`) VALUES
(11, 2, 6, 1, '2025-05-08 11:02:49'),
(12, 2, 1, 1, '2025-05-08 11:02:49');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `name`, `description`, `created_at`) VALUES
(2, 'mosturizer', 'a mostriser helps your skin to stay soft and hydrated and clean use it two times a day', '2025-05-07 21:52:55'),
(3, 'cleanser', 'every product in this categories is to clean the sckin the products are all bio made products deeloped with love and care for each single product ', '2025-05-13 17:56:55');

-- --------------------------------------------------------

--
-- Table structure for table `favorites`
--

CREATE TABLE `favorites` (
  `favorite_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `favorites`
--

INSERT INTO `favorites` (`favorite_id`, `user_id`, `item_id`, `added_at`) VALUES
(8, 3, 1, '2025-05-14 20:42:00'),
(9, 3, 3, '2025-05-14 20:42:02'),
(10, 3, 7, '2025-05-14 20:42:04');

-- --------------------------------------------------------

--
-- Table structure for table `items`
--

CREATE TABLE `items` (
  `item_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL CHECK (`price` >= 0),
  `stock` int(11) NOT NULL CHECK (`stock` >= 0),
  `category_id` int(11) DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `ingredients` text DEFAULT NULL,
  `how_to_use` text DEFAULT NULL,
  `shipping_returns_info` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `items`
--

INSERT INTO `items` (`item_id`, `name`, `description`, `price`, `stock`, `category_id`, `image_url`, `ingredients`, `how_to_use`, `shipping_returns_info`, `created_at`, `updated_at`) VALUES
(1, 'Hydrating Rose Face Mist', 'A refreshing botanical face mist enriched with organic rose water and aloe vera.', 14.99, 91, 3, 'images/product1.png', 'Rose Water, Aloe Vera, Glycerin, Vitamin E', 'Spray onto face after cleansing or throughout the day for a burst of hydration.', 'Free shipping on orders over $50. Returns accepted within 30 days of purchase.', '2025-05-06 13:29:32', '2025-05-14 22:11:27'),
(2, 'Revive Night Serum', 'A lightweight serum with jojoba oil and vitamin C that revitalizes skin overnight.', 24.99, 72, 3, 'images/product2.png', 'Jojoba Oil, Vitamin C, Hyaluronic Acid, Lavender Extract', 'Apply a few drops to cleansed face before bedtime. Gently massage in circular motions.', 'Standard shipping in 3-5 days. Returns within 14 days if unopened.', '2025-05-06 13:29:32', '2025-05-14 22:11:27'),
(3, 'Herbal Nourishing Shampoo', 'Sulfate-free shampoo infused with chamomile and argan oil for healthier hair.', 18.50, 113, 2, 'images/product3.png', 'Chamomile, Argan Oil, Coconut Derivatives, Green Tea', 'Apply to wet hair, massage into scalp, rinse thoroughly. Use 2-3 times a week.', 'Ships within 48 hours. 30-day return policy.', '2025-05-06 13:29:32', '2025-05-14 22:11:27'),
(4, 'Soothing Aloe Vera Gel', 'Pure aloe vera gel that calms irritated skin and soothes sunburn.', 9.99, 197, 3, 'images/product4.png', 'Aloe Vera, Vitamin E, Tea Tree Oil', 'Apply to affected area. Can be used after sun exposure or shaving.', 'Available for return within 30 days. Shipping takes 2-4 business days.', '2025-05-06 13:29:32', '2025-05-14 22:14:42'),
(5, 'Organic Lip Balm Trio', 'Set of 3 lip balms in rose, mint, and vanilla made from 100% organic ingredients.', 12.00, 88, 2, 'images/product5.png', 'Beeswax, Shea Butter, Coconut Oil, Essential Oils', 'Apply directly to lips as needed. Reapply frequently in dry conditions.', 'Free returns within 15 days. Ships nationwide.', '2025-05-06 13:29:32', '2025-05-14 22:09:24'),
(6, 'felipino creem ', 'this a mostrising creem from the felepine where we created it just for you \r\nbuy it now and get infinite hydrations ', 33.00, 17, 2, 'images/product7.png', 'uses  filipino herb and the mostrising creem from the bambo trees ', 'use it 3 times befor bad after waking up and one during the date', NULL, '2025-05-07 21:55:41', '2025-05-08 18:55:09'),
(7, 'gojum sirome ', 'Indulge your skin with Balerina Capochina, a luxurious bio moisturizer crafted from 100% natural and organic ingredients. Enriched with botanical extracts and nourishing oils, this lightweight yet deeply hydrating formula restores your skin’s natural glow, leaving it soft, supple, and radiant. Perfect for all skin types, including sensitive skin, our moisturizer is free from parabens, sulfates, and synthetic fragrances. Let nature pamper your skin with every use.', 200.00, 14, 2, 'images/product8.png', 'Aloe Vera Extract – soothes and hydrates the skin\r\n\r\nShea Butter (Organic) – deeply moisturizes and softens\r\n\r\nJojoba Oil – balances natural oils and adds smoothness\r\n\r\nRosehip Seed Oil – rich in antioxidants and promotes skin regeneration\r\n\r\nVitamin E – protects against free radicals and enhances skin repair\r\n\r\nChamomile Extract – calms irritated or sensitive skin\r\n\r\nLavender Essential Oil – adds a light fragrance and reduces inflammation\r\n\r\nGlycerin (Plant-based) – draws moisture into the skin for long-lasting hydration\r\n\r\nBeeswax (Natural) – locks in moisture and creates a protective barrier\r\n\r\nPurified Water – acts as a hydrating base', 'Apply a small amount of Balerina Capochina to clean, dry skin. Gently massage in upward, circular motions until fully absorbed. Use daily, morning and night, for best results. Ideal as a base before makeup or as part of your nighttime skincare routine.', NULL, '2025-05-08 18:38:31', '2025-05-14 22:14:42');

-- --------------------------------------------------------

--
-- Table structure for table `item_images`
--

CREATE TABLE `item_images` (
  `image_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `image_url` varchar(255) NOT NULL,
  `is_main` tinyint(1) DEFAULT 0,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `item_images`
--

INSERT INTO `item_images` (`image_id`, `item_id`, `image_url`, `is_main`, `uploaded_at`) VALUES
(1, 1, 'images/product1.png', 1, '2025-05-06 13:29:32'),
(2, 1, 'images/product11.png', 0, '2025-05-06 13:39:00'),
(3, 1, 'images/product12.png', 0, '2025-05-06 13:39:00'),
(4, 1, 'images/product13.png', 0, '2025-05-06 13:39:00'),
(5, 2, 'images/product21.png', 0, '2025-05-06 13:39:00'),
(6, 2, 'images/product22.png', 0, '2025-05-06 13:39:00'),
(7, 2, 'images/product23.png', 0, '2025-05-06 13:39:00'),
(8, 3, 'images/product31.png', 0, '2025-05-06 13:39:00'),
(9, 3, 'images/product32.png', 0, '2025-05-06 13:39:00'),
(10, 3, 'images/product33.png', 0, '2025-05-06 13:39:00'),
(11, 4, 'images/product41.png', 0, '2025-05-06 13:39:00'),
(12, 4, 'images/product42.png', 0, '2025-05-06 13:39:00'),
(13, 4, 'images/product43.png', 0, '2025-05-06 13:39:00'),
(14, 5, 'images/product51.png', 0, '2025-05-06 13:39:00'),
(15, 5, 'images/product52.png', 0, '2025-05-06 13:39:00'),
(16, 5, 'images/product53.png', 0, '2025-05-06 13:39:00'),
(17, 2, 'images/product2.png', 1, '2025-05-06 13:41:26'),
(18, 3, 'images/product3.png', 1, '2025-05-06 13:41:26'),
(19, 4, 'images/product4.png', 1, '2025-05-06 13:41:26'),
(20, 5, 'images/product5.png', 1, '2025-05-06 13:41:26');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `order_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `total_amount` decimal(10,2) NOT NULL CHECK (`total_amount` >= 0),
  `status` enum('Pending','Processing','Shipped','Delivered','Cancelled') NOT NULL DEFAULT 'Pending',
  `shipping_address` text DEFAULT NULL,
  `billing_address` text DEFAULT NULL,
  `payment_method` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`order_id`, `user_id`, `order_date`, `total_amount`, `status`, `shipping_address`, `billing_address`, `payment_method`, `created_at`) VALUES
(5, 3, '2025-05-07 20:31:53', 103.94, 'Delivered', 'Algiers, Alger , 16000, algeria', 'Algiers, Alger , 16000, algeria', 'Cash on Delivery', '2025-05-07 20:31:53'),
(6, 3, '2025-05-07 21:57:48', 43.00, 'Pending', 'Algiers, Alger , 16000, algeria', 'Algiers, Alger , 16000, algeria', 'Cash on Delivery', '2025-05-07 21:57:48'),
(7, 4, '2025-05-08 10:44:31', 57.99, 'Pending', 'Algiers, Alger , 16000, algeria', 'Algiers, Alger , 16000, algeria', 'Cash on Delivery', '2025-05-08 10:44:31'),
(8, 3, '2025-05-08 18:49:26', 412.00, 'Cancelled', 'Algiers, Alger , 16000, algeria', 'Algiers, Alger , 16000, algeria', 'Cash on Delivery', '2025-05-08 18:49:26'),
(9, 3, '2025-05-08 18:55:09', 43.00, 'Shipped', 'Algiers, Alger , 16000, algeria', 'Algiers, Alger , 16000, algeria', 'Cash on Delivery', '2025-05-08 18:55:09'),
(10, 3, '2025-05-08 19:23:55', 90.49, 'Delivered', 'Algiers, Alger , 16000, algeria', 'Algiers, Alger , 16000, algeria', 'Cash on Delivery', '2025-05-08 19:23:55'),
(12, 5, '2025-05-12 08:13:58', 400.00, 'Processing', 'JJJJJJJ, jjjjjjj, 35, algeria', 'JJJJJJJ, jjjjjjj, 35, algeria', 'Cash on Delivery', '2025-05-12 08:13:58'),
(13, 3, '2025-05-14 22:11:27', 783.94, 'Pending', 'Algiers, Alger , 16000, algeria', 'Algiers, Alger , 16000, algeria', 'Cash on Delivery', '2025-05-14 22:11:27'),
(14, 3, '2025-05-14 22:14:42', 209.99, 'Pending', 'Algiers, Alger , 16000, algeria', 'Algiers, Alger , 16000, algeria', 'Cash on Delivery', '2025-05-14 22:14:42');

--
-- Triggers `orders`
--
DELIMITER $$
CREATE TRIGGER `after_update_order_status_cancelled` AFTER UPDATE ON `orders` FOR EACH ROW BEGIN
    IF OLD.status != 'Cancelled' AND NEW.status = 'Cancelled' THEN
        INSERT INTO cancelled_orders_history (order_id, user_id, cancellation_date)
        VALUES (OLD.order_id, OLD.user_id, NOW());
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `before_update_order_status_cancelled` BEFORE UPDATE ON `orders` FOR EACH ROW BEGIN
    IF OLD.status != 'Cancelled' AND NEW.status = 'Cancelled' THEN
        UPDATE items i
        JOIN order_items oi ON i.item_id = oi.item_id
        SET i.stock = i.stock + oi.quantity
        WHERE oi.order_id = OLD.order_id;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `order_item_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL CHECK (`quantity` >= 1),
  `price_at_order` decimal(10,2) NOT NULL CHECK (`price_at_order` >= 0),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`order_item_id`, `order_id`, `item_id`, `quantity`, `price_at_order`, `created_at`) VALUES
(4, 5, 5, 2, 12.00, '2025-05-07 20:31:53'),
(5, 5, 1, 4, 14.99, '2025-05-07 20:31:53'),
(6, 5, 4, 2, 9.99, '2025-05-07 20:31:53'),
(7, 6, 6, 1, 33.00, '2025-05-07 21:57:48'),
(8, 7, 6, 1, 33.00, '2025-05-08 10:44:31'),
(9, 7, 1, 1, 14.99, '2025-05-08 10:44:31'),
(11, 8, 5, 1, 12.00, '2025-05-08 18:49:26'),
(12, 8, 7, 2, 200.00, '2025-05-08 18:49:26'),
(14, 9, 6, 1, 33.00, '2025-05-08 18:55:09'),
(15, 10, 2, 1, 24.99, '2025-05-08 19:23:55'),
(16, 10, 3, 3, 18.50, '2025-05-08 19:23:55'),
(17, 12, 7, 2, 200.00, '2025-05-12 08:13:58'),
(18, 13, 2, 2, 24.99, '2025-05-14 22:11:27'),
(19, 13, 3, 4, 18.50, '2025-05-14 22:11:27'),
(20, 13, 7, 3, 200.00, '2025-05-14 22:11:27'),
(21, 13, 1, 4, 14.99, '2025-05-14 22:11:27'),
(25, 14, 4, 1, 9.99, '2025-05-14 22:14:42'),
(26, 14, 7, 1, 200.00, '2025-05-14 22:14:42');

--
-- Triggers `order_items`
--
DELIMITER $$
CREATE TRIGGER `before_insert_order_item` BEFORE INSERT ON `order_items` FOR EACH ROW BEGIN
    DECLARE available_stock INT;
    DECLARE item_price DECIMAL(10,2);
    
    SELECT stock, price INTO available_stock, item_price
    FROM items
    WHERE item_id = NEW.item_id;
    
    IF NEW.quantity > available_stock THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Insufficient stock available for this item.';
    END IF;
    
    SET NEW.price_at_order = item_price;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `is_admin` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_banned` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `email`, `password_hash`, `profile_picture`, `is_admin`, `created_at`, `updated_at`, `is_active`, `is_banned`) VALUES
(3, 'besouma', 'Ibtissemladjici05@gmail.come', '$2y$10$zVPAt6LfHssDm1RFXqAv0O9FSnXEHzXN1Z8LK8EqqmcCZMv2NnyGC', NULL, 0, '2025-05-07 06:47:25', '2025-05-13 17:48:29', 1, 0),
(4, 'narimen', 'narimenladjici@gmail.com', '$2y$10$1/QeyhzC5XHcJ0PoJT1.0u1B59taoK3DUkUXloIyfQHzVQtPtdp9a', NULL, 0, '2025-05-08 10:43:51', '2025-05-13 17:48:29', 1, 0),
(5, 'lastarr', 'maneltifoura@gmail.com', '$2y$10$oDHkSsxiMnf0ugBM3ZLxi.6qQRIn0Lc3mrMMLwQD7B8WZYst74VGm', NULL, 0, '2025-05-12 08:10:16', '2025-05-13 17:48:29', 1, 0),
(6, 'use2', 'user2@gmail.com', '$2y$10$ejqQl/S4j90NY3woWAvv7OtkTWZRMDVh0qndLbU7q4RNzfCE9Toee', NULL, 0, '2025-05-14 18:32:35', '2025-05-14 18:32:48', 1, 0),
(7, 'user3', 'usere3@gmail.com', '$2y$10$7b64GDp0yt/HRjQQAD65wu5UDa/mXJGvaCooqFhd1sagsxBa7UwM2', NULL, 0, '2025-05-14 18:34:19', '2025-05-14 18:35:45', 1, 0),
(8, 'ibtissem', 'ibtissemladjici50@gmail.com', '$2y$10$DIk7wJJqGfS4wpYRBl6A6.UBtAjIbx5QKI.WqO1zRuV15g7Sr9RFG', NULL, 0, '2025-05-14 22:19:06', '2025-05-14 22:19:06', 1, 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `admin_todos`
--
ALTER TABLE `admin_todos`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `blog_posts`
--
ALTER TABLE `blog_posts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `cancelled_orders_history`
--
ALTER TABLE `cancelled_orders_history`
  ADD PRIMARY KEY (`history_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `carts`
--
ALTER TABLE `carts`
  ADD PRIMARY KEY (`cart_id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD PRIMARY KEY (`cart_item_id`),
  ADD UNIQUE KEY `unique_cart_item` (`cart_id`,`item_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `favorites`
--
ALTER TABLE `favorites`
  ADD PRIMARY KEY (`favorite_id`),
  ADD UNIQUE KEY `unique_favorite` (`user_id`,`item_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `item_images`
--
ALTER TABLE `item_images`
  ADD PRIMARY KEY (`image_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`order_item_id`),
  ADD UNIQUE KEY `unique_order_item` (`order_id`,`item_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `admin_todos`
--
ALTER TABLE `admin_todos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blog_posts`
--
ALTER TABLE `blog_posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `cancelled_orders_history`
--
ALTER TABLE `cancelled_orders_history`
  MODIFY `history_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `carts`
--
ALTER TABLE `carts`
  MODIFY `cart_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `cart_items`
--
ALTER TABLE `cart_items`
  MODIFY `cart_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `favorites`
--
ALTER TABLE `favorites`
  MODIFY `favorite_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `items`
--
ALTER TABLE `items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `item_images`
--
ALTER TABLE `item_images`
  MODIFY `image_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `order_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cancelled_orders_history`
--
ALTER TABLE `cancelled_orders_history`
  ADD CONSTRAINT `cancelled_orders_history_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`),
  ADD CONSTRAINT `cancelled_orders_history_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `carts`
--
ALTER TABLE `carts`
  ADD CONSTRAINT `carts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD CONSTRAINT `cart_items_ibfk_1` FOREIGN KEY (`cart_id`) REFERENCES `carts` (`cart_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`) ON DELETE CASCADE;

--
-- Constraints for table `favorites`
--
ALTER TABLE `favorites`
  ADD CONSTRAINT `favorites_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `favorites_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`) ON DELETE CASCADE;

--
-- Constraints for table `items`
--
ALTER TABLE `items`
  ADD CONSTRAINT `items_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE SET NULL;

--
-- Constraints for table `item_images`
--
ALTER TABLE `item_images`
  ADD CONSTRAINT `item_images_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
