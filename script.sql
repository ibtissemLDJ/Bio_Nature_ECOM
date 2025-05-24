-- first verion i worked with 
SET default_storage_engine = InnoDB;

CREATE TABLE IF NOT EXISTS categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS items (
    item_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL CHECK (price >= 0),
    stock INT NOT NULL CHECK (stock >= 0),
    category_id INT,
    image_url VARCHAR(255),
    ingredients TEXT,
    how_to_use TEXT,
    shipping_returns_info TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS item_images (
    image_id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    image_url VARCHAR(255) NOT NULL,
    is_main BOOLEAN DEFAULT FALSE,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(item_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    profile_picture VARCHAR(255),
    is_admin BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS carts (
    cart_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS cart_items (
    cart_item_id INT AUTO_INCREMENT PRIMARY KEY,
    cart_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity INT NOT NULL CHECK (quantity >= 1),
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cart_id) REFERENCES carts(cart_id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(item_id) ON DELETE CASCADE,
    UNIQUE KEY unique_cart_item (cart_id, item_id)
);

CREATE TABLE IF NOT EXISTS orders (
    order_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    total_amount DECIMAL(10, 2) NOT NULL CHECK (total_amount >= 0),
    status ENUM('Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled') NOT NULL DEFAULT 'Pending',
    shipping_address TEXT,
    billing_address TEXT,
    payment_method VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS order_items (
    order_item_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity INT NOT NULL CHECK (quantity >= 1),
    price_at_order DECIMAL(10, 2) NOT NULL CHECK (price_at_order >= 0),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(item_id) ON DELETE RESTRICT,
    UNIQUE KEY unique_order_item (order_id, item_id)
);

CREATE TABLE IF NOT EXISTS favorites (
    favorite_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    item_id INT NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(item_id) ON DELETE CASCADE,
    UNIQUE KEY unique_favorite (user_id, item_id)
);

CREATE TABLE IF NOT EXISTS cancelled_orders_history (
    history_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    user_id INT NOT NULL,
    cancellation_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reason TEXT,
    cancelled_by VARCHAR(50),
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE RESTRICT,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE RESTRICT
);

DELIMITER $$

CREATE PROCEDURE get_order_details (IN p_order_id INT, IN p_user_id INT)
BEGIN
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
END $$

CREATE PROCEDURE finalize_order_from_cart (
    IN p_user_id INT,
    IN p_shipping_address TEXT,
    IN p_billing_address TEXT,
    IN p_payment_method VARCHAR(255)
)
BEGIN
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

END $$

CREATE PROCEDURE get_customer_order_history (IN p_user_id INT)
BEGIN
    SELECT
        order_id,
        order_date,
        total_amount,
        status
    FROM orders
    WHERE user_id = p_user_id
    ORDER BY order_date DESC;
END $$

CREATE TRIGGER before_insert_order_item
BEFORE INSERT ON order_items
FOR EACH ROW
BEGIN
    DECLARE available_stock INT;

    SELECT stock INTO available_stock
    FROM items
    WHERE item_id = NEW.item_id;

    IF NEW.quantity > available_stock THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Insufficient stock available for this item.';
    END IF;
END $$

CREATE TRIGGER after_insert_order_item
AFTER INSERT ON order_items
FOR EACH ROW
BEGIN
    UPDATE items
    SET stock = stock - NEW.quantity
    WHERE item_id = NEW.item_id;
END $$

CREATE TRIGGER before_update_order_status_cancelled
BEFORE UPDATE ON orders
FOR EACH ROW
BEGIN
    IF OLD.status != 'Cancelled' AND NEW.status = 'Cancelled' THEN
        UPDATE items i
        JOIN order_items oi ON i.item_id = oi.item_id
        SET i.stock = i.stock + oi.quantity
        WHERE oi.order_id = OLD.order_id;
    END IF;
END $$

CREATE TRIGGER after_update_order_status_cancelled
AFTER UPDATE ON orders
FOR EACH ROW
BEGIN
    IF OLD.status != 'Cancelled' AND NEW.status = 'Cancelled' THEN
        INSERT INTO cancelled_orders_history (order_id, user_id, cancellation_date)
        VALUES (OLD.order_id, OLD.user_id, NOW());
    END IF;
END $$
USE nescare; CREATE TABLE IF NOT EXISTS password_resets (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, token VARCHAR(64) NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, expires_at DATETIME NOT NULL, used BOOLEAN DEFAULT FALSE, FOREIGN KEY (user_id) REFERENCES users(user_id));"
DELIMITER ;