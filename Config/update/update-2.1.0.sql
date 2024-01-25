CREATE TABLE `axcepta_scheme`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `order_id` INTEGER,
    `name` VARCHAR(45) NOT NULL,
    `number` VARCHAR(19) NOT NULL,
    `brand` VARCHAR(33) NOT NULL,
    `expiry_date` VARCHAR(6) NOT NULL,
    `scheme_reference_id` VARCHAR(255) NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB;


