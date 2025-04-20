-- Table for user information
CREATE TABLE Users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL, -- Store hashed passwords
    role ENUM('customer', 'admin') DEFAULT 'customer',
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    profile_picture VARCHAR(255), -- Path or URL to profile picture
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table for product categories
CREATE TABLE Categories (
    category_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) UNIQUE NOT NULL
);

-- Table for product information
CREATE TABLE Items (
    item_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    stock INT DEFAULT 0,
    category_id INT,
    image_url VARCHAR(255), -- Path or URL to the main product image
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES Categories(category_id)
);

-- Table for the user's shopping cart
CREATE TABLE Carts (
    cart_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    creation_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(user_id)
);

-- Table for items in the shopping cart
CREATE TABLE Cart_Items (
    cart_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity INT NOT NULL,
    PRIMARY KEY (cart_id, item_id),
    FOREIGN KEY (cart_id) REFERENCES Carts(cart_id),
    FOREIGN KEY (item_id) REFERENCES Items(item_id)
);

-- Table for customer orders
CREATE TABLE Orders (
    order_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    order_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
    total DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (user_id) REFERENCES Users(user_id)
);

-- Table for items within an order
CREATE TABLE Order_Items (
    order_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10, 2) NOT NULL,
    PRIMARY KEY (order_id, item_id),
    FOREIGN KEY (order_id) REFERENCES Orders(order_id),
    FOREIGN KEY (item_id) REFERENCES Items(item_id)
);

-- Table for tracking order history/cancellations
CREATE TABLE Order_History (
    history_id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    user_id INT NOT NULL,
    status_change_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    old_status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled'),
    new_status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled'),
    reason TEXT, -- Optional reason for status change (e.g., cancellation reason)
    FOREIGN KEY (order_id) REFERENCES Orders(order_id),
    FOREIGN KEY (user_id) REFERENCES Users(user_id)
);

-- Table for user's favorite items
CREATE TABLE Favorites (
    user_id INT NOT NULL,
    item_id INT NOT NULL,
    PRIMARY KEY (user_id, item_id),
    FOREIGN KEY (user_id) REFERENCES Users(user_id),
    FOREIGN KEY (item_id) REFERENCES Items(item_id)
);

-- Table for delivery information
CREATE TABLE Deliveries (
    delivery_id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT UNIQUE NOT NULL, -- Assuming one delivery per order
    status ENUM('pending', 'in_transit', 'delivered') DEFAULT 'pending',
    delivery_date DATETIME,
    shipping_address TEXT, -- You might want a more detailed address structure
    FOREIGN KEY (order_id) REFERENCES Orders(order_id)
);

-- Optional table for multiple product images
CREATE TABLE Product_Images (
    image_id INT PRIMARY KEY AUTO_INCREMENT,
    item_id INT NOT NULL,
    image_url VARCHAR(255) NOT NULL,
    FOREIGN KEY (item_id) REFERENCES Items(item_id)
);
-- Table for Product Reviews
CREATE TABLE Product_Reviews (
    review_id INT PRIMARY KEY AUTO_INCREMENT,
    item_id INT NOT NULL,
    user_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES Items(item_id),
    FOREIGN KEY (user_id) REFERENCES Users(user_id)
);

-- Table for Product Variations (e.g., size, color)
CREATE TABLE Product_Variations (
    variation_id INT PRIMARY KEY AUTO_INCREMENT,
    item_id INT NOT NULL,
    name VARCHAR(100) NOT NULL, -- e.g., "Size", "Color"
    FOREIGN KEY (item_id) REFERENCES Items(item_id)
);

-- Table for Product Variation Options (e.g., "Small", "Large" for Size)
CREATE TABLE Product_Variation_Options (
    option_id INT PRIMARY KEY AUTO_INCREMENT,
    variation_id INT NOT NULL,
    value VARCHAR(100) NOT NULL, -- e.g., "Small", "Large", "Red", "Blue"
    stock INT DEFAULT 0,
    additional_price DECIMAL(10, 2) DEFAULT 0.00,
    FOREIGN KEY (variation_id) REFERENCES Product_Variations(variation_id)
);

-- Linking table for Items and their Variation Options
CREATE TABLE Item_Variation_Options (
    item_id INT NOT NULL,
    option_id INT NOT NULL,
    PRIMARY KEY (item_id, option_id),
    FOREIGN KEY (item_id) REFERENCES Items(item_id),
    FOREIGN KEY (option_id) REFERENCES Product_Variation_Options(option_id)
);

INSERT INTO Categories (name)
VALUES 
    ('Cleansers'),
    ('Moisturizers'),
    ('Serums'),
    ('Sunscreens'),
    ('Masks');
INSERT INTO Items (name, description, price, stock, category_id, image_url)
VALUES 
-- Cleanser
('Organic Green Tea Cleanser', 
 'A gentle facial cleanser with antioxidant-rich green tea and aloe vera.', 
 15.99, 50, 1, 
 'images/product1.png'),

-- Moisturizer
('Hydrating Rose Water Cream', 
 'Moisturizing cream made with rose water and shea butter for glowing skin.', 
 22.50, 30, 2, 
 'images/product2.png'),

-- Serum
('Vitamin C Brightening Serum', 
 'Concentrated serum with 15% Vitamin C for brighter and even-toned skin.', 
 29.99, 40, 3, 
 'images/product3.png'),

-- Sunscreen
('Mineral SPF 50 Sunscreen', 
 'Non-toxic, broad-spectrum mineral sunscreen with zinc oxide.', 
 19.95, 35, 4, 
 'images/product4.png'),

-- Mask
('Charcoal Detox Face Mask', 
 'Deep-cleansing face mask with activated charcoal and clay.', 
 17.75, 25, 5, 
 'images/product5.png');
