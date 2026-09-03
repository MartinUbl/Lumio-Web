-- Model: New Model    Version: 2.0

SET NAMES utf8mb4 COLLATE utf8mb4_czech_ci;
SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- -----------------------------------------------------
-- Schema lumio_db
-- -----------------------------------------------------

CREATE SCHEMA IF NOT EXISTS `lumio_db` DEFAULT CHARACTER SET utf8mb4 DEFAULT COLLATE utf8mb4_czech_ci;
USE `lumio_db`;


-- -----------------------------------------------------
-- Table `lumio_db`.`roles`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `roles` (
  `code` VARCHAR(32) NOT NULL,
  `label` VARCHAR(64) NOT NULL,
  PRIMARY KEY (`code`)
) ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `lumio_db`.`users`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(45) NOT NULL,
  `role` VARCHAR(32) NOT NULL,
  `faculty` ENUM('FAV', 'FDU', 'FEK', 'FEL', 'FF', 'FPE', 'FPR', 'FST', 'FZS') NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `password` VARCHAR(256) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `admin` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  INDEX `fk_users_roles1_idx` (`role` ASC) VISIBLE,
  CONSTRAINT `fk_users_roles1`
    FOREIGN KEY (`role`)
    REFERENCES `roles` (`code`)
    ON DELETE NO ACTION
    ON UPDATE CASCADE
) ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `lumio_db`.`experts`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `experts` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(90) NOT NULL,
  `institution` VARCHAR(90) NULL,
  `residence` VARCHAR(90) NULL,
  `email` VARCHAR(90) NULL,
  `telephone` VARCHAR(45) NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `note` VARCHAR(255) NULL,
  PRIMARY KEY (`id`)
) ENGINE = InnoDB;



-- -----------------------------------------------------
-- Table `lumio_db`.`events`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `events` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT NOT NULL,
  `datetime` DATETIME NOT NULL,
  `image_path` VARCHAR(255) NULL,
  `organiser` VARCHAR(90) NOT NULL,
  `organiser_id` INT NULL,
  `file_path` VARCHAR(255) NULL,
  `event_report_path` VARCHAR(255) NULL,
  `expert_id` INT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_events_users1_idx` (`organiser_id` ASC) VISIBLE,
  INDEX `fk_events_experts1_idx` (`expert_id` ASC) VISIBLE,
  CONSTRAINT `fk_events_users1`
    FOREIGN KEY (`organiser_id`)
    REFERENCES `users` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_events_experts1`
    FOREIGN KEY (`expert_id`)
    REFERENCES `experts` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `lumio_db`.`attendees`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `attendees` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `users_id` INT NOT NULL,
  `events_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_attendees_user_event` (`users_id`, `events_id`),
  INDEX `fk_attendees_users1_idx` (`users_id` ASC) VISIBLE,
  INDEX `fk_attendees_events1_idx` (`events_id` ASC) VISIBLE,
  CONSTRAINT `fk_attendees_users1`
    FOREIGN KEY (`users_id`)
    REFERENCES `users` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_attendees_events1`
    FOREIGN KEY (`events_id`)
    REFERENCES `events` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION
) ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `lumio_db`.`tags`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `tags` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `tag_name` VARCHAR(90) NOT NULL,
  `slug` VARCHAR(120) NOT NULL,
  `tag_type` ENUM('area', 'category') NOT NULL,
  `scope` ENUM('event', 'expert', 'both') NOT NULL DEFAULT 'both',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tags_slug` (`slug`),
  INDEX `idx_tags_type_scope_active` (`tag_type`, `scope`, `is_active`)
) ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `lumio_db`.`event_audience_roles`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `event_audience_roles` (
  `event_id` INT NOT NULL,
  `role` VARCHAR(32) NOT NULL,
  PRIMARY KEY (`event_id`, `role`),
  INDEX `fk_event_audience_roles_roles1_idx` (`role` ASC) VISIBLE,
  CONSTRAINT `fk_event_audience_roles_events1`
    FOREIGN KEY (`event_id`)
    REFERENCES `events` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_event_audience_roles_roles1`
    FOREIGN KEY (`role`)
    REFERENCES `roles` (`code`)
    ON DELETE NO ACTION
    ON UPDATE CASCADE
) ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `lumio_db`.`event_tags`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `event_tags` (
  `event_id` INT NOT NULL,
  `tag_id` INT NOT NULL,
  PRIMARY KEY (`event_id`, `tag_id`),
  INDEX `fk_event_tags_tags1_idx` (`tag_id` ASC) VISIBLE,
  CONSTRAINT `fk_event_tags_events1`
    FOREIGN KEY (`event_id`)
    REFERENCES `events` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_event_tags_tags1`
    FOREIGN KEY (`tag_id`)
    REFERENCES `tags` (`id`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `lumio_db`.`experts_tags`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `expert_tags` (
  `expert_id` INT NOT NULL,
  `tag_id` INT NOT NULL,
  PRIMARY KEY (`expert_id`, `tag_id`),
  INDEX `fk_expert_tags_tags1_idx` (`tag_id` ASC) VISIBLE,
  CONSTRAINT `fk_expert_tags_experts1`
    FOREIGN KEY (`expert_id`)
    REFERENCES `experts` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_expert_tags_tags1`
    FOREIGN KEY (`tag_id`)
    REFERENCES `tags` (`id`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS `password_resets` (
    `user_id` INT NOT NULL,
    `reset_code` VARCHAR(32) NOT NULL,
    PRIMARY KEY (`user_id`),
    CONSTRAINT `fk_password_resets_users1`
      FOREIGN KEY (`user_id`)
      REFERENCES `users` (`id`)
      ON DELETE NO ACTION
      ON UPDATE NO ACTION
) ENGINE = InnoDB;

SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;
