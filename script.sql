-- -----------------------------------------------------------------
-- SCHEMA ET LOGIQUE DE BASE DE DONNEES POUR PROJET E-COMMERCE
-- Fichier genere a partir de la fusion des schemas et de l'analyse UI.
-- -----------------------------------------------------------------

-- SCHEMA RELATIONNEL:
-- Users: Informations sur les utilisateurs (clients, admins).
-- Categories: Categories de produits.
-- Items: Informations sur les produits (incluant descriptions detaillees et stock niveau item).
-- Cart_Items: Articles actuellement dans le panier d'un utilisateur (simplication directe user-item).
-- Orders: Informations sur les commandes passées (incluant adresse de livraison figee).
-- Order_Items: Détails des articles inclus dans chaque commande (prix au moment de la commande).
-- Order_History: Log des changements de statut de commande (incluant annulations).
-- Favorites: Articles favoris des utilisateurs.
-- Deliveries: Informations de livraison specifiques a une commande.
-- Product_Images: Images additionnelles pour les produits.
-- Product_Reviews: Avis et evaluations des produits par les utilisateurs.
-- Product_Variations: Types de variations d'un produit (ex: Taille, Couleur).
-- Product_Variation_Options: Options spécifiques pour une variation (ex: Small, Large, Rouge, Bleu - peut contenir stock/prix specifique variation).
-- Item_Variation_Options: Lien entre Items et leurs Variation Options (si un item utilise un sous-ensemble d'options).


-- RELATIONS:
-- - Users 1 -- * Orders
-- - Users 1 -- * Cart_Items
-- - Users 1 -- * Favorites
-- - Users 1 -- * Product_Reviews
-- - Categories 1 -- * Items
-- - Items 1 -- * Cart_Items
-- - Items 1 -- * Order_Items
-- - Items 1 -- * Favorites
-- - Items 1 -- * Product_Images
-- - Items 1 -- * Product_Reviews
-- - Items 1 -- * Product_Variations
-- - Orders 1 -- * Order_Items
-- - Orders 1 -- 1 Deliveries (Unique constraint on order_id)
-- - Orders 1 -- * Order_History
-- - Product_Variations 1 -- * Product_Variation_Options
-- - Product_Variation_Options * -- * Item_Variation_Options (Lien)
-- - Items * -- * Item_Variation_Options (Lien)


-- -----------------------------------------------------------------
-- NETTOYAGE: Suppression des objets existants (pour re-exécuter le script facilement)
-- -----------------------------------------------------------------

-- Supprimer les triggers en premier car ils dépendent des tables/procédures
DROP TRIGGER IF EXISTS AfterOrderUpdate_LogCancelledOrder;
DROP TRIGGER IF EXISTS AfterOrderUpdate_RestoreStockOnCancel;
DROP TRIGGER IF EXISTS BeforeOrderItemInsert_CheckStock;
DROP TRIGGER IF EXISTS AfterOrderItemInsert_UpdateStock;

-- Supprimer les procédures stockées
DROP PROCEDURE IF EXISTS GetUserOrderHistory;
DROP PROCEDURE IF EXISTS FinalizeOrderFromCart;
DROP PROCEDURE IF EXISTS GetUserOrderDetails;

-- Supprimer les tables en respectant l'ordre des dépendances des clés étrangères
DROP TABLE IF EXISTS Item_Variation_Options;
DROP TABLE IF EXISTS Product_Variation_Options;
DROP TABLE IF EXISTS Product_Variations;
DROP TABLE IF EXISTS Product_Reviews;
DROP TABLE IF EXISTS Product_Images;
DROP TABLE IF EXISTS Deliveries;
DROP TABLE IF EXISTS Favorites;
DROP TABLE IF EXISTS Order_History;
DROP TABLE IF EXISTS Order_Items;
DROP TABLE IF EXISTS Orders;
DROP TABLE IF EXISTS Cart_Items; -- Table simplifiée
DROP TABLE IF EXISTS Items; -- Renommé depuis Products, avec attributs affinés
DROP TABLE IF EXISTS Categories;
DROP TABLE IF EXISTS Users; -- Renommé depuis Customers


-- -----------------------------------------------------------------
-- CREATION DES TABLES (AVEC ATTRIBUTS AFFINES SELON UI)
-- -----------------------------------------------------------------

-- Table for user information
CREATE TABLE Users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL, -- Stocker les mots de passe hashés! Utiliser des fonctions de hachage sécurisées (ex: bcrypt)
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

-- Table for product information (Affined based on UI)
CREATE TABLE Items (
    item_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    -- Added specific columns for accordion content visible on UI
    ingredients TEXT,
    how_to_use TEXT,
    shipping_returns_info TEXT, -- Added for potential per-item info (or category/global)
    price DECIMAL(10, 2) NOT NULL CHECK (price >= 0),
    stock INT DEFAULT 0 CHECK (stock >= 0), -- Stock at the item level (or base stock if variations manage stock)
    category_id INT,
    image_url VARCHAR(255), -- Path or URL to the main product image
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES Categories(category_id) ON DELETE SET NULL -- If category is deleted, items remain but category_id becomes NULL
);

-- Table for items in the shopping cart (Simplified: direct link from User to Item)
CREATE TABLE Cart_Items (
    user_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity INT NOT NULL CHECK (quantity > 0),
    PRIMARY KEY (user_id, item_id), -- Each user can have each item type once in the cart
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES Items(item_id) ON DELETE CASCADE -- If item is deleted, remove from carts
);


-- Table for customer orders (Affined based on UI/Checkout need)
CREATE TABLE Orders (
    order_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    order_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
    total DECIMAL(10, 2) NOT NULL DEFAULT 0.00 CHECK (total >= 0),
    shipping_address TEXT NOT NULL, -- ADDED: Store the shipping address for this specific order
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE
);

-- Table for items within an order (Same as previous merged script)
CREATE TABLE Order_Items (
    order_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity INT NOT NULL CHECK (quantity > 0),
    unit_price DECIMAL(10, 2) NOT NULL CHECK (unit_price >= 0), -- Price at the time of order
    PRIMARY KEY (order_id, item_id), -- Composite PK for items in an order
    FOREIGN KEY (order_id) REFERENCES Orders(order_id) ON DELETE CASCADE, -- If order deleted, delete order items
    FOREIGN KEY (item_id) REFERENCES Items(item_id) -- If item deleted, keep order history, but item_id will be broken unless ON DELETE SET NULL/RESTRICT
);

-- Table for tracking order history/cancellations (Same as previous merged script)
CREATE TABLE Order_History (
    history_id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT, -- Keep INT, potentially allow NULL if order is deleted later
    user_id INT, -- Keep INT, potentially allow NULL if user is deleted later
    status_change_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    old_status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled'),
    new_status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled'),
    reason TEXT, -- Optional reason for status change (e.g., cancellation reason)
    -- Decided against ON DELETE CASCADE/SET NULL for order_id/user_id here
    -- to truly keep a historical record even if the source order/user is purged.
);

-- Table for user's favorite items (Same as previous merged script)
CREATE TABLE Favorites (
    user_id INT NOT NULL,
    item_id INT NOT NULL,
    PRIMARY KEY (user_id, item_id),
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES Items(item_id) ON DELETE CASCADE
);

-- Table for delivery information (Same as previous merged script - useful for tracking the delivery separate from the order itself)
CREATE TABLE Deliveries (
    delivery_id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT UNIQUE NOT NULL, -- Assuming one delivery per order
    status ENUM('pending', 'in_transit', 'delivered', 'failed') DEFAULT 'pending', -- Added 'failed' status
    delivery_date DATETIME, -- Estimated or actual delivery date
    shipping_address TEXT NOT NULL, -- Store the address used for THIS specific delivery
    FOREIGN KEY (order_id) REFERENCES Orders(order_id) ON DELETE CASCADE
);

-- Optional table for multiple product images (Same as previous merged script)
CREATE TABLE Product_Images (
    image_id INT PRIMARY KEY AUTO_INCREMENT,
    item_id INT NOT NULL,
    image_url VARCHAR(255) NOT NULL,
    description VARCHAR(255), -- Optional description for the image
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES Items(item_id) ON DELETE CASCADE
);

-- Table for Product Reviews (Same as previous merged script)
CREATE TABLE Product_Reviews (
    review_id INT PRIMARY KEY AUTO_INCREMENT,
    item_id INT NOT NULL,
    user_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES Items(item_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE
);

-- Table for Product Variations (e.g., size, color) (Same as previous merged script)
CREATE TABLE Product_Variations (
    variation_id INT PRIMARY KEY AUTO_INCREMENT,
    item_id INT NOT NULL,
    name VARCHAR(100) NOT NULL, -- e.g., "Size", "Color"
    FOREIGN KEY (item_id) REFERENCES Items(item_id) ON DELETE CASCADE,
    UNIQUE (item_id, name) -- A product cannot have two variations with the same name
);

-- Table for Product Variation Options (e.g., "Small", "Large" for Size) (Same as previous merged script)
CREATE TABLE Product_Variation_Options (
    option_id INT PRIMARY KEY AUTO_INCREMENT,
    variation_id INT NOT NULL,
    value VARCHAR(100) NOT NULL, -- e.g., "Small", "Large", "Red", "Blue"
    stock INT DEFAULT 0 CHECK (stock >= 0), -- Stock for this specific option (if managing stock by variation)
    additional_price DECIMAL(10, 2) DEFAULT 0.00 CHECK (additional_price >= 0), -- Price difference for this option
    FOREIGN KEY (variation_id) REFERENCES Product_Variations(variation_id) ON DELETE CASCADE,
    UNIQUE (variation_id, value) -- A variation cannot have two options with the same value
);

-- Linking table for Items and their Variation Options (Same as previous merged script - keep if needed)
CREATE TABLE Item_Variation_Options (
    item_id INT NOT NULL,
    option_id INT NOT NULL,
    PRIMARY KEY (item_id, option_id),
    FOREIGN KEY (item_id) REFERENCES Items(item_id) ON DELETE CASCADE,
    FOREIGN KEY (option_id) REFERENCES Product_Variation_Options(option_id) ON DELETE CASCADE
);


-- -----------------------------------------------------------------
-- DEFINITION DES PROCEDURES STOCKEEES (ADAPTEES AUX ATTRIBUTS AFFINES)
-- -----------------------------------------------------------------

-- Changer le délimiteur pour permettre l'utilisation du point-virgule dans les procédures/triggers
DELIMITER //

-- Procedure 1: Affiche les détails d'une commande pour un utilisateur et le total (Adaptée pour inclure shipping_address)
CREATE PROCEDURE GetUserOrderDetails(
    IN p_user_id INT,
    IN p_order_id INT
)
BEGIN
    -- Vérifier si la commande appartient bien à l'utilisateur
    IF EXISTS (SELECT 1 FROM Orders WHERE order_id = p_order_id AND user_id = p_user_id) THEN
        -- Afficher les détails de la commande (ID, Date, Statut, Total, Shipping Address)
        SELECT
            o.order_id,
            o.order_date,
            o.status,
            o.total,
            o.shipping_address -- Added shipping address
        FROM
            Orders o
        WHERE
            o.order_id = p_order_id;

        -- Afficher les articles de la commande
        SELECT
            oi.item_id, -- Using item_id as part of PK in Order_Items, no separate order_item_id
            i.name AS item_name,
            oi.quantity,
            oi.unit_price AS price_per_item,
            (oi.quantity * oi.unit_price) AS line_item_total
        FROM
            Order_Items oi
        JOIN
            Items i ON oi.item_id = i.item_id
        WHERE
            oi.order_id = p_order_id;
    ELSE
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Order not found for this user.';
    END IF;
END //

-- Procedure 2: Finalise une commande à partir du panier et vide le panier (Adaptée pour prendre shipping_address en parametre)
CREATE PROCEDURE FinalizeOrderFromCart(
    IN p_user_id INT,
    IN p_shipping_address TEXT -- **ADDED**: Need shipping address as input for the order
)
BEGIN
    DECLARE v_order_id INT;
    DECLARE v_total DECIMAL(10, 2) DEFAULT 0.00;
    DECLARE v_cart_count INT DEFAULT 0;

    -- Vérifier si le panier n'est pas vide pour cet utilisateur
    SELECT COUNT(*) INTO v_cart_count FROM Cart_Items WHERE user_id = p_user_id;

    IF v_cart_count = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cart is empty. Cannot finalize order.';
    END IF;

    -- Commencer une transaction pour assurer l'atomicité
    START TRANSACTION;

    -- 1. Créer une nouvelle commande, incluant l'adresse de livraison
    INSERT INTO Orders (user_id, status, shipping_address) -- **MODIFIED**: Added shipping_address
    VALUES (p_user_id, 'pending', p_shipping_address); -- Statut initial 'pending'

    SET v_order_id = LAST_INSERT_ID();

    -- 2. Copier les articles du panier vers Order_Items et calculer le total
    -- Utiliser le prix actuel des produits (Items.price) au moment de la commande
    INSERT INTO Order_Items (order_id, item_id, quantity, unit_price)
    SELECT
        v_order_id,
        ci.item_id,
        ci.quantity,
        i.price -- Prendre le prix actuel de l'item
    FROM
        Cart_Items ci
    JOIN
        Items i ON ci.item_id = i.item_id
    WHERE
        ci.user_id = p_user_id;

    -- Les triggers AFTER INSERT ON Order_Items se déclencheront ici pour vérifier et décrémenter le stock.
    -- Si un trigger BEFORE INSERT échoue, la transaction sera rollbackée automatiquement.

    -- Calculer le montant total de la commande à partir des Order_Items insérés
    SELECT SUM(quantity * unit_price) INTO v_total
    FROM Order_Items
    WHERE order_id = v_order_id;

    -- 3. Mettre à jour le total et le statut de la commande (passer à 'processing' si la copie a réussi)
    UPDATE Orders
    SET total = v_total,
        status = 'processing' -- Marquer comme en cours de traitement après validation du stock
    WHERE order_id = v_order_id;

    -- 4. Vider le panier de l'utilisateur
    DELETE FROM Cart_Items WHERE user_id = p_user_id;

    -- Si tout s'est bien passé, valider la transaction
    COMMIT;

    -- Optionnel: Retourner l'ID de la nouvelle commande
    SELECT v_order_id AS new_order_id;

-- Si un trigger a signalé une erreur (ex: stock insuffisant), la transaction sera automatiquement rollbackée.
-- Vous pouvez ajouter des handlers spécifiques ici si vous voulez gérer différents types d'erreurs différemment.
END //

-- Procedure 3: Affiche l'historique des commandes d'un utilisateur (Adaptée pour inclure shipping_address)
CREATE PROCEDURE GetUserOrderHistory(
    IN p_user_id INT
)
BEGIN
    SELECT
        order_id,
        order_date,
        total,
        status,
        shipping_address -- Added shipping address to history view
    FROM
        Orders
    WHERE
        user_id = p_user_id
    ORDER BY
        order_date DESC;
END //

-- Rétablir le délimiteur par défaut
DELIMITER ;

-- -----------------------------------------------------------------
-- DEFINITION DES TRIGGERS (ADAPTES AUX NOMS DES TABLES/COLONNES)
-- -----------------------------------------------------------------

-- Trigger 1: Met automatiquement à jour le stock d’un item après l'ajout dans order_items
-- S'exécute APRÈS l'insertion réussie dans Order_Items.
DELIMITER //
CREATE TRIGGER AfterOrderItemInsert_UpdateStock
AFTER INSERT ON Order_Items
FOR EACH ROW
BEGIN
    -- Décrémenter le stock de l'item correspondant dans la table Items
    -- NOTE: Si vous utilisez le stock par variation (Product_Variation_Options.stock), ce trigger
    -- devrait être modifié pour mettre à jour le stock de l'option de variation correspondante.
    UPDATE Items
    SET stock = stock - NEW.quantity
    WHERE item_id = NEW.item_id;
END //
DELIMITER ;


-- Trigger 2: Empêche l’insertion d’un Order_Item si la quantité demandée dépasse le stock disponible.
-- S'exécute AVANT l'insertion dans Order_Items pour pouvoir l'annuler.
DELIMITER //
CREATE TRIGGER BeforeOrderItemInsert_CheckStock
BEFORE INSERT ON Order_Items
FOR EACH ROW
BEGIN
    DECLARE current_stock INT;

    -- Récupérer le stock actuel de l'item dans la table Items
    -- NOTE: Si vous utilisez le stock par variation (Product_Variation_Options.stock), ce trigger
    -- devrait être modifié pour vérifier le stock de l'option de variation correspondante.
    SELECT stock INTO current_stock
    FROM Items
    WHERE item_id = NEW.item_id;

    -- Vérifier si le stock est suffisant
    IF current_stock < NEW.quantity THEN
        -- Empêcher l'insertion en signalant une erreur SQL standard
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Insufficient stock available for this item.';
        -- Ceci fera échouer l'insertion et rollbackera la transaction parente (FinalizeOrderFromCart).
    END IF;
END //
DELIMITER ;


-- Trigger 3: Permet de restaurer le stock après l'annulation d'une commande.
-- S'exécute APRÈS la mise à jour d'une commande.
DELIMITER //
CREATE TRIGGER AfterOrderUpdate_RestoreStockOnCancel
AFTER UPDATE ON Orders
FOR EACH ROW
BEGIN
    -- Vérifier si le statut de la commande est passé à 'cancelled'
    IF OLD.status != 'cancelled' AND NEW.status = 'cancelled' THEN
        -- Pour chaque article de cette commande, augmenter le stock de l'item correspondant
        -- NOTE: Si vous utilisez le stock par variation (Product_Variation_Options.stock), ce trigger
        -- devrait être modifié pour restaurer le stock de l'option de variation correspondante.
        UPDATE Items i
        JOIN Order_Items oi ON i.item_id = oi.item_id
        SET i.stock = i.stock + oi.quantity
        WHERE oi.order_id = NEW.order_id;
    END IF;
END //
DELIMITER ;


-- Trigger 4: Permet de garder trace des commandes annulées (ou tout changement de statut vers 'cancelled') dans la table historique.
-- S'exécute APRÈS la mise à jour d'une commande.
DELIMITER //
CREATE TRIGGER AfterOrderUpdate_LogCancelledOrder
AFTER UPDATE ON Orders
FOR EACH ROW
BEGIN
    -- Vérifier si le statut de la commande est passé à 'cancelled'
    IF OLD.status != 'cancelled' AND NEW.status = 'cancelled' THEN
        -- Insérer un enregistrement dans la table Order_History pour cette annulation
        INSERT INTO Order_History (order_id, user_id, status_change_date, old_status, new_status, reason)
        VALUES (NEW.order_id, NEW.user_id, CURRENT_TIMESTAMP(), OLD.status, NEW.status, 'Order cancelled'); -- Vous pouvez rendre le 'reason' plus dynamique si vous l'avez
    END IF;
    -- Note: Si vous vouliez logger TOUS les changements de statut, vous enlèveriez la condition IF OLD.status != 'cancelled' AND NEW.status = 'cancelled'
    -- et ajusteriez la raison si nécessaire. La requête demandait spécifiquement de garder trace des commandes annulées.
END //
DELIMITER ;

-- -----------------------------------------------------------------
-- FIN DU SCRIPT - AUCUNE DONNEE D'EXEMPLE INCLUSE
-- -----------------------------------------------------------------