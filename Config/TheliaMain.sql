
# This is a fix for InnoDB in MySQL >= 4.1.x
# It "suspends judgement" for fkey relationships until are tables are set.
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- axcepta_scheme
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `axcepta_scheme`;

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

# This restores the fkey checks, after having unset them earlier
SET FOREIGN_KEY_CHECKS = 1;
