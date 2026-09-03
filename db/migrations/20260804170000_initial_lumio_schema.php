<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class InitialLumioSchema extends AbstractMigration
{
    public function up(): void
    {
        $this->execute("SET FOREIGN_KEY_CHECKS=0");

        if (!$this->hasTable('users')) {
            $this->execute(<<<'SQL'
CREATE TABLE `users` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(45) NOT NULL,
  `role` ENUM('zamestnanec', 'absolvent', 'student', 'stredoskolak', 'jine') NOT NULL,
  `faculty` ENUM('FAV', 'FDULS', 'FEK', 'FEL', 'FF', 'FPE', 'FPR', 'FST', 'FZS') NULL,
  `active` TINYINT NULL,
  `password` VARCHAR(256),
  `email` VARCHAR(100),
  `admin` TINYINT DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci
SQL);
        }

        if (!$this->hasTable('specialists')) {
            $this->execute(<<<'SQL'
CREATE TABLE `specialists` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(45) NOT NULL,
  `institution` VARCHAR(45) NULL,
  `residence` VARCHAR(45) NULL,
  `email` VARCHAR(45) NULL,
  `telephone` VARCHAR(45) NULL,
  `note` VARCHAR(100) NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci
SQL);
        }

        if (!$this->hasTable('events')) {
            $this->execute(<<<'SQL'
CREATE TABLE `events` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT NOT NULL,
  `datetime` DATETIME NULL,
  `image_path` VARCHAR(45) NOT NULL,
  `organiser` VARCHAR(45) NOT NULL,
  `organiser_id` INT NULL,
  `file_path` VARCHAR(45) NULL,
  `event_report_path` VARCHAR(45) NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_events_users1_idx` (`organiser_id`),
  CONSTRAINT `fk_events_users1`
    FOREIGN KEY (`organiser_id`)
    REFERENCES `users` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci
SQL);
        }

        if (!$this->hasTable('attendees')) {
            $this->execute(<<<'SQL'
CREATE TABLE `attendees` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `users_id` INT NOT NULL,
  `events_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_attendees_users1_idx` (`users_id`),
  INDEX `fk_attendees_events1_idx` (`events_id`),
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci
SQL);
        }

        if (!$this->hasTable('tags')) {
            $this->execute(<<<'SQL'
CREATE TABLE `tags` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `tag_name` VARCHAR(45) NOT NULL,
  `role_tag` TINYINT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci
SQL);
        }

        if (!$this->hasTable('tags_to_id')) {
            $this->execute(<<<'SQL'
CREATE TABLE `tags_to_id` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `tag_id` INT NOT NULL,
  `event_id` INT NULL,
  `specialist_id` INT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_tags_to_event_tags_idx` (`tag_id`),
  INDEX `fk_tags_to_event_specialists1_idx` (`specialist_id`),
  INDEX `fk_tags_to_event_events1_idx` (`event_id`),
  CONSTRAINT `fk_tags_to_event_tags`
    FOREIGN KEY (`tag_id`)
    REFERENCES `tags` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_tags_to_event_specialists1`
    FOREIGN KEY (`specialist_id`)
    REFERENCES `specialists` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_tags_to_event_events1`
    FOREIGN KEY (`event_id`)
    REFERENCES `events` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci
SQL);
        }

        $this->execute("SET FOREIGN_KEY_CHECKS=1");
    }

    public function down(): void
    {
        $this->output->writeln('Initial schema migration is intentionally irreversible.');
    }
}
