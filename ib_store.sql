-- phpMyAdmin SQL Dump
-- version 5.2.2deb1+deb13u1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Nov 14, 2026 at 12:23 AM
-- Server version: 11.8.6-MariaDB-0+deb13u1 from Debian
-- PHP Version: 8.4.27

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ib_store`
--

-- --------------------------------------------------------

--
-- Table structure for table `accounts`
--

CREATE TABLE `accounts` (
  `id` bigint(20) NOT NULL,
  `registered` datetime NOT NULL DEFAULT current_timestamp(),
  `username` varchar(32) NOT NULL,
  `email` varchar(50) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT 0,
  `verified` tinyint(1) NOT NULL DEFAULT 0,
  `notes` varchar(256) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `account_billing`
--

CREATE TABLE `account_billing` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `account_id` bigint(20) UNSIGNED NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `company` varchar(255) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address_1` varchar(255) NOT NULL,
  `address_2` varchar(255) DEFAULT NULL,
  `city` varchar(100) NOT NULL,
  `province` varchar(100) NOT NULL,
  `postal_code` varchar(20) NOT NULL,
  `country` varchar(100) NOT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 1,
  `created` datetime NOT NULL DEFAULT current_timestamp(),
  `updated` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `account_shipping`
--

CREATE TABLE `account_shipping` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `account_id` bigint(20) UNSIGNED NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `company` varchar(150) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address_1` varchar(255) NOT NULL,
  `address_2` varchar(255) DEFAULT NULL,
  `city` varchar(100) NOT NULL,
  `province` varchar(100) NOT NULL,
  `postal_code` varchar(25) NOT NULL,
  `country` varchar(100) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created` datetime NOT NULL DEFAULT current_timestamp(),
  `updated` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(36) NOT NULL,
  `route` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `route`, `description`, `active`, `created`) VALUES
(4, 'PC Games', 'pc-games', 'PC Games', 1, '2026-11-05 10:00:04'),
(5, 'Educational Games', 'ed-games', 'Education Games', 1, '2026-11-07 07:05:44');

-- --------------------------------------------------------

--
-- Table structure for table `login_sessions`
--

CREATE TABLE `login_sessions` (
  `ip_address` varchar(40) DEFAULT NULL,
  `user_id` bigint(20) NOT NULL,
  `session_id` char(32) NOT NULL,
  `session_hash` char(32) NOT NULL,
  `updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` char(36) NOT NULL COMMENT 'public id used in urls',
  `account_id` bigint(20) UNSIGNED NOT NULL,
  `email` varchar(255) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `currency` char(3) NOT NULL DEFAULT 'USD',
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `shipping` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tax` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `has_physical` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = something needs shipping',
  `bill_first_name` varchar(100) NOT NULL,
  `bill_last_name` varchar(100) NOT NULL,
  `bill_company` varchar(255) DEFAULT NULL,
  `bill_phone` varchar(50) DEFAULT NULL,
  `bill_address_1` varchar(255) NOT NULL,
  `bill_address_2` varchar(255) DEFAULT NULL,
  `bill_city` varchar(100) NOT NULL,
  `bill_province` varchar(100) NOT NULL,
  `bill_postal_code` varchar(20) NOT NULL,
  `bill_country` varchar(100) NOT NULL,
  `ship_first_name` varchar(100) DEFAULT NULL,
  `ship_last_name` varchar(100) DEFAULT NULL,
  `ship_company` varchar(255) DEFAULT NULL,
  `ship_phone` varchar(50) DEFAULT NULL,
  `ship_address_1` varchar(255) DEFAULT NULL,
  `ship_address_2` varchar(255) DEFAULT NULL,
  `ship_city` varchar(100) DEFAULT NULL,
  `ship_province` varchar(100) DEFAULT NULL,
  `ship_postal_code` varchar(20) DEFAULT NULL,
  `ship_country` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL COMMENT 'customer order notes',
  `admin_notes` text DEFAULT NULL,
  `payment_method` varchar(30) DEFAULT NULL COMMENT 'filled in by the payment step',
  `payment_ref` varchar(255) DEFAULT NULL COMMENT 'gateway transaction id',
  `paid_at` datetime DEFAULT NULL,
  `created` datetime NOT NULL DEFAULT current_timestamp(),
  `updated` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'null if the product was deleted later',
  `sku` varchar(100) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `qty` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `digital` tinyint(1) NOT NULL DEFAULT 0,
  `download_url` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `account_id` bigint(20) UNSIGNED NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires` datetime NOT NULL,
  `created` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(10) UNSIGNED NOT NULL,
  `category_id` int(10) UNSIGNED NOT NULL,
  `sku` varchar(100) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL COMMENT 'for URLs',
  `con` tinyint(2) NOT NULL DEFAULT 0 COMMENT '0 New, 1 Open, 2 Used ',
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `stock` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `digital` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = no shipping, download_url is handed out after payment',
  `download_url` varchar(255) DEFAULT NULL,
  `keys_left` int(10) UNSIGNED DEFAULT NULL COMMENT 'unused keys in product_keys, null = the product has no keys',
  `use_files` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'digital: hand out the download files once paid',
  `use_keys` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'digital: hand out a key from product_keys once paid, sold out when none are left',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `newest` tinyint(1) NOT NULL DEFAULT 0,
  `featured` tinyint(1) NOT NULL DEFAULT 0,
  `badge` tinyint(2) NOT NULL DEFAULT 0 COMMENT '1 New, 2 Sale, 3 Disc',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `category_id`, `sku`, `name`, `slug`, `con`, `description`, `price`, `stock`, `digital`, `download_url`, `keys_left`, `use_files`, `use_keys`, `active`, `newest`, `featured`, `badge`, `created_at`, `updated_at`) VALUES
(7, 4, 'ib-000-000', 'Vibe City', 'vibe-city', 0, 'Build a city. Find your vibe.\n\nEvery great city starts with one road.\n\nVibe City is an indie city builder in the spirit of SimCity 2000. You start with an empty map and a modest budget. Before long you have glowing neighborhoods, busy industrial zones, a power grid that is mostly working, and a lot of decisions to answer for. Each building is detailed in isometric pixel style. Turn up the in-game radio and watch your town come to life one block at a time.\n\nPlan it. Grow it. Live in it.\n\nLay down roads and zone homes, shops and factories, then watch citizens move in. Run power lines across valleys, drill tunnels through hills, and throw bridges over lakes. Keep the water running, the lights on and the budget out of the red. Schools, hospitals, police and fire stations keep your people safe and happy. Skip them and they will let you know.\n\nBuild a quiet, tidy little town. Or keep going until your industrial district needs its own industrial district. Nobody here is judging. Well, the citizens might be.\n\nFeatures\n\n• Zone and grow: Set up residential, commercial and industrial districts that grow and change based on your choices.\n\n• Connect everything: Lay out roads, railways, bridges and tunnels over lakes, rivers and rolling hills.\n\n• Keep it running: Manage power, water and the budget, and staff the schools, hospitals, police and fire stations your city depends on.\n\n• Give it personality: Add gardens, statues, plazas and landmarks that make the city yours.\n\n• Hit the nightlife: Step into a neon-lit nightclub to play blackjack and slots and watch the dancers. Being mayor is hard work, and you\'ve earned a break.\n\n• Curate your own gallery: Walk through art galleries and choose what goes on the walls. Your city, your collection.\n\n• Weather the unexpected: Storms roll in, fires break out, and every so often some decidedly stranger visitors show up.\n\n• Soak in the atmosphere: Enjoy detailed isometric art, a neon-accented interface, and an in-game radio for every questionable planning decision.\n\n• Mod it: Add brand new buildings made by players, complete with their own art, upkeep, mini games and even visual novels. The free Vibe City Mod Kit has everything you need to make your own.\n\nFamiliar roots, its own personality.\n\nVibe City keeps the classic city builder feeling of turning a blank map into a living place. It also gives you reasons to stop and enjoy what you\'ve built: a night at the club, a stroll through the gallery, or just watching the traffic flow at sunset.\n\nMake it yours with your own pictures\n\nGive your galleries and clubs a personal touch. Add your own images to the folders below, then restart the game to see them displayed when you visit those buildings.\n\nWindows and macOS\n\n• Gallery: Documents/Vibe City/gallery/\n\n• Clubs: Documents/Vibe City/clubs/\n\nLinux\n\n• Gallery: /home/[user]/Documents/Vibe City/gallery/\n\n• Clubs: /home/[user]/Documents/Vibe City/clubs/\n\nReplace [user] with your Linux username. These folders are created the first time you run the game.\n\nSupported formats: Galleries accept PNG, JPG and WebP. Clubs accept GIF, WebP and PNG, including animated GIFs for custom dancers.\n\nYour pictures stay in your Documents folder, so updating the game leaves your collection intact.\n\nBuild your own buildings with mods\n\nWant something the city doesn\'t have yet? Vibe City supports mods. A mod adds a brand new building to your build menu, with its own art, price and upkeep. It can also use power and water, provide jobs, and pay money back into your city. Click the building and a mod can open its own mini game, an animated scene or a whole visual novel.\n\nTo install a mod, drop its folder into the mods folder and restart the game.\n\nWindows and macOS\n\n• Mods: Documents/Vibe City/mods/\n\nLinux\n\n• Mods: /home/[user]/Documents/Vibe City/mods/\n\nEach mod lives in its own folder with a mod.json file, its building art and an icon. The modding guide (MODDING.md) is copied into your mods folder, so the basics are right there.\n\nThe Vibe City Mod Kit\n\nReady to make your own? The free Vibe City Mod Kit is on GitHub:\n\nhttps://github.com/fswolf/vibe-city-modkit\n\n• Ready-to-copy templates: a simple building, a micro arcade game, a visual novel, an animation framework, and still-picture scenes with play buttons.\n\n• VC Novel Studio: a desktop editor for writing visual novels, with scene cards, a live preview, a scene map and automatic backups.\n\n• Sprite tools: check, slice and fix your building art so it sits perfectly on the tile.\n\n• Plain-language guides for every part, from mod.json to packing and sharing your mod.\n\nCopy a template into your mods folder, start the game, and you already have a working mod to build on. The kit is MIT licensed, so your mods are yours.\n\nWant to see what a mod can do? Try Neon Brew, a cozy cafe for your city. Inside, you can play Barista Rush: serve coffee to a cast of regulars before they run out of patience, and send your takings to the city budget.\n\nYour city is waiting. Welcome to Vibe City.\n\nYour purchase includes an [itch.io](https://fswolf.itch.io/) download key. Our servers are self-hosted, so we recommend downloading the game from [itch.io](https://fswolf.itch.io/).', 5.99, 0, 1, 'Vibe-City-1.0.6-x64.zip, Vibe-City-1.0.4-mac-universal.dmg, Vibe-City-1.0.6-x86_64.AppImage, neon-brew-mod.zip', NULL, 1, 1, 1, 0, 1, 0, '2026-11-05 10:01:33', '2026-11-13 23:16:52'),
(8, 4, 'ib-000-001', 'Vibe Copter', 'vibe-copter', 0, 'That bridge you built looks a little different through a helicopter windscreen.\n\nIn Vibe Copter, your Vibe City save becomes a place you can fly. Lift off from the pad, follow the river into town, and see how the neighbourhoods you laid out fit together from the pilot’s seat. The hills roll, the roads climb and dip with them, and the tower you squeezed onto that corner lot is now something you have to fly around.\n\nTake the calls. Mika on dispatch keeps the radio busy, and every call is a little story playing out somewhere in your city:\n\n* Fly a hurt resident to the hospital. They’re lying in the street with a small crowd gathered round; land close and they’ll limp over to the door.\n* Give someone a lift across town. Fares wave you down from the pavement.\n* Light up a getaway car with your searchlight and hold it there until the police box it in. The tighter the beam, the slower they go.\n* Catch a speeder and order them to pull over through the megaphone.\n* Fight fires. Drop the water bucket, dip it in the river, and dump it on the flames before the whole block goes up. The city’s fire engines roll in to help hold it back, but it takes your bucket to put it out.\n* Pull people to safety with the rescue harness. Someone’s stranded on a rooftop with nowhere to land, or treading water in the bay. Hover until they grab on, then set them down gently on solid ground.\n* Break up a riot. A mob takes over a junction, fists in the air, traffic backed up for blocks. Tear gas sends them running, but drop a canister on someone’s head and you’ve just made yourself a medevac call. Leave it too long and they’ll set the block on fire.\n\nEarn your wings. Every job counts toward your career, and your pilot’s career follows you from city to city. Climb from Rookie to Chief Pilot and pick up new gear along the way:\n\n* Rookie: searchlight\n* Pilot: megaphone\n* Senior Pilot: water bucket\n* Captain: rescue harness\n* Chief Pilot: tear gas\n\nHigher ranks get more calls at once, and they come in faster. Watch your fuel and damage gauges: the helipad is where you refuel and repair, and Rook at the hangar will have something to say about every dent. Wreck the helicopter and you’re buying a new one out of your pay.\n\nA living city. Traffic, trains and ships go about their day. Drawbridges ring their bells and lift for passing boats. The sun rises and sets over your skyline, windows light up after dark, and street lights glow along the roads in town. Weather rolls in on its own: rain, fog, and storms with lightning and wind that pushes you around. Land anywhere and climb out to walk around, just don’t stand in traffic.\n\nFly it your way. Chase cam, or sit in the cockpit with the instruments on the dash. Pull up the city map to plan a route or drop a waypoint. Set how long a day lasts, hold the weather on your favourite, or lock it to night for searchlight patrols. A lo-fi radio keeps you company between calls.\n\nBring your own city. Choose New Game → Import a Vibe City save, pick your city’s JSON file, and take off. If you just want to fly, start with the demo city. Your saves and career are kept in Documents/Vibe Copter, so they’re easy to find and back up.\n\nTwo things to check against your build before you paste it. “Lock it to night” assumes the day setting can hold at night, which matches what I know of the settings. The lo-fi radio line assumes the music ships with the game.\n\nYour purchase includes an [itch.io](https://fswolf.itch.io/) download key. Our servers are self-hosted, so we recommend downloading the game from [itch.io](https://fswolf.itch.io/).', 5.99, 0, 1, 'Vibe-Copter-0.9.0-win-x64.zip, Vibe-Copter-0.9.0-x86_64.AppImage', NULL, 1, 1, 1, 0, 1, 0, '2026-11-06 08:34:25', '2026-11-13 23:16:58'),
(9, 5, 'ib-000-002', 'Equation Strike', 'equation-strike', 0, '## Math is your weapon.\n\n**Equation Strike** is a top-down space shooter for kids aged 6 to 12, inspired by the math games of the 80s and 90s. Your blaster never runs dry, but the cannon that clears the screen and breaks the bosses\' shields only charges one way: solving problems.\n\nEnergy crystals drift through space with numbers on them. Read the problem, find the right crystal, and shoot it to power up. Miss, and the crystal fizzles while the right answer glows, so you see it and learn it. No buzzers. No shame. Just try again.\n\n### How it plays\n- **Math in the action, not on a pause screen.** You answer by flying and shooting.\n- **Calm patches between waves** give you room to think. Pressure builds, then lets up.\n- **Quietly adaptive.** The game notices what your child misses and brings it back in new ways, so a 6-year-old and an 11-year-old are both challenged.\n- **Eight planets, one skill each:** addition, subtraction, multiplication, division, fractions, decimals, negative numbers and early algebra.\n\n### Old-school on purpose\nA gritty 80s sci-fi world with real stakes, a dry-humoured ship computer and insect-like aliens that burst into goo. Fantasy violence only, and no blood. Your ship takes visible damage, and losing counts. Muted colours with no flashing, built to make kids think, not to grab their eyes.\n\n### What you won\'t find\n- No ads. Ever.\n- No in-app purchases, gems or loot boxes.\n- No subscriptions.\n- No streaks, nagging or \"come back tomorrow\" tricks.\n- No accounts and no data collected about your child.\n\n**Buy it once, it\'s yours.**\n\n---\n*Plays in the browser on Linux, Mac, Windows and Chromebooks.*', 3.99, 0, 0, NULL, NULL, 0, 0, 1, 1, 0, 4, '2026-11-12 04:49:21', '2026-11-13 06:31:18');

-- --------------------------------------------------------

--
-- Table structure for table `product_images`
--

CREATE TABLE `product_images` (
  `id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `image` varchar(255) NOT NULL,
  `sort_order` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_images`
--

INSERT INTO `product_images` (`id`, `product_id`, `image`, `sort_order`) VALUES
(8, 7, 'assets/images/items/vibe-city-2.png', 1),
(11, 8, 'assets/images/items/vibe-copter-1.png', 1),
(15, 7, 'assets/images/items/vibe-city-1.png', 2),
(16, 7, 'assets/images/items/vibe-city-3.png', 3),
(17, 7, 'assets/images/items/vibe-city-4.png', 4),
(18, 7, 'assets/images/items/vibe-city-5.png', 5),
(19, 7, 'assets/images/items/vibe-city-6.png', 6),
(20, 7, 'assets/images/items/vibe-city-7.png', 7),
(21, 7, 'assets/images/items/vibe-city-8.png', 8),
(22, 7, 'assets/images/items/vibe-city-9.png', 9),
(23, 7, 'assets/images/items/vibe-city-10.png', 10),
(25, 8, 'assets/images/items/vibe-copter-3.png', 2),
(26, 8, 'assets/images/items/vibe-copter-2.png', 3),
(27, 8, 'assets/images/items/vibe-copter-4.png', 4),
(29, 8, 'assets/images/items/vibe-copter-5.png', 5),
(31, 9, 'assets/images/items/equation-strike-2.png', 1),
(32, 8, 'assets/images/items/vibe-copter-6.png', 6),
(33, 8, 'assets/images/items/vibe-copter-7.png', 7);

-- --------------------------------------------------------

--
-- Table structure for table `product_keys`
--

CREATE TABLE `product_keys` (
  `id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `key_value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'an itch.io download key link, a steam key, any code. binary: keys are case sensitive',
  `order_item_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'the order line it is held for, null = unused',
  `created` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `store_info`
--

CREATE TABLE `store_info` (
  `id` int(10) UNSIGNED NOT NULL,
  `store_name` varchar(150) NOT NULL,
  `company_name` varchar(150) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address_line1` varchar(255) DEFAULT NULL,
  `address_line2` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state_province` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `currency` char(3) DEFAULT 'USD',
  `tax_rate` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT 'percent, 5.00 = 5%',
  `tax_home_only` tinyint(1) NOT NULL DEFAULT 0,
  `shipping_flat` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'per order, physical items only',
  `free_shipping_over` decimal(10,2) DEFAULT NULL COMMENT 'subtotal that waives shipping, null = never',
  `timezone` varchar(100) DEFAULT 'UTC',
  `logo` varchar(255) DEFAULT NULL,
  `favicon` varchar(255) DEFAULT NULL COMMENT 'tab icon, empty = the default bored face',
  `hero_image` varchar(255) DEFAULT NULL COMMENT 'empty = no hero on the home page',
  `hero_heading` varchar(150) DEFAULT NULL,
  `hero_text` varchar(255) DEFAULT NULL,
  `hero_button` varchar(50) DEFAULT NULL COMMENT 'button label, empty = no button',
  `hero_link` varchar(255) DEFAULT NULL COMMENT 'where the button goes, /items for the shop',
  `payments_off` varchar(255) NOT NULL DEFAULT '' COMMENT 'gateways switched off in the admin panel, comma list of names (btcpay,stripe)',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `store_info`
--

INSERT INTO `store_info` (`id`, `store_name`, `company_name`, `email`, `phone`, `address_line1`, `address_line2`, `city`, `state_province`, `postal_code`, `country`, `website`, `currency`, `tax_rate`, `tax_home_only`, `shipping_flat`, `free_shipping_over`, `timezone`, `logo`, `favicon`, `hero_image`, `hero_heading`, `hero_text`, `hero_button`, `hero_link`, `payments_off`, `created_at`, `updated_at`) VALUES
(1, 'iB Store', 'My Company', 'store@example.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'https://example.com/', 'CAD', 0.00, 0, 0.00, NULL, 'UTC', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '2026-06-22 23:50:34', '2026-11-13 23:59:25');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accounts`
--
ALTER TABLE `accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id` (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `account_billing`
--
ALTER TABLE `account_billing`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_account_id` (`account_id`);

--
-- Indexes for table `account_shipping`
--
ALTER TABLE `account_shipping`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_id` (`account_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`route`);

--
-- Indexes for table `login_sessions`
--
ALTER TABLE `login_sessions`
  ADD PRIMARY KEY (`user_id`),
  ADD KEY `session_id` (`session_id`),
  ADD KEY `session_hash` (`session_hash`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uuid` (`uuid`),
  ADD KEY `account_id` (`account_id`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`account_id`),
  ADD KEY `token_hash` (`token_hash`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD UNIQUE KEY `sku` (`sku`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `product_images`
--
ALTER TABLE `product_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `product_keys`
--
ALTER TABLE `product_keys`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `product_key` (`product_id`,`key_value`),
  ADD KEY `order_item_id` (`order_item_id`);

--
-- Indexes for table `store_info`
--
ALTER TABLE `store_info`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `accounts`
--
ALTER TABLE `accounts`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `account_billing`
--
ALTER TABLE `account_billing`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `account_shipping`
--
ALTER TABLE `account_shipping`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `product_images`
--
ALTER TABLE `product_images`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `product_keys`
--
ALTER TABLE `product_keys`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10001;

--
-- AUTO_INCREMENT for table `store_info`
--
ALTER TABLE `store_info`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`);

--
-- Constraints for table `product_images`
--
ALTER TABLE `product_images`
  ADD CONSTRAINT `product_images_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
