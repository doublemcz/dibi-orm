CREATE TABLE `users` (
	`id` SMALLINT(5) UNSIGNED NOT NULL AUTO_INCREMENT,
	`fullname` VARCHAR(50) NOT NULL COLLATE 'utf8_czech_ci',
	`createdAt` DATETIME NOT NULL,
	`updatedAt` DATETIME NULL DEFAULT NULL,
	`birthDate` DATE NULL DEFAULT NULL,
	PRIMARY KEY (`id`)
)
COLLATE='utf8_czech_ci'
ENGINE=InnoDB
AUTO_INCREMENT=79;

CREATE TABLE `users_log` (
	`id` SMALLINT(5) UNSIGNED NOT NULL AUTO_INCREMENT,
	`userId` SMALLINT(5) UNSIGNED NOT NULL,
	`text` VARCHAR(100) NOT NULL COLLATE 'utf8_czech_ci',
	`createdAt` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
	`type` VARCHAR(10) NOT NULL COLLATE 'utf8_czech_ci',
	PRIMARY KEY (`id`),
	INDEX `FK_users_log_users` (`userId`),
	CONSTRAINT `FK_users_log_users` FOREIGN KEY (`userId`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
)
COLLATE='utf8_czech_ci'
ENGINE=InnoDB
AUTO_INCREMENT=3;

CREATE TABLE `users_details` (
	`id` SMALLINT(5) UNSIGNED NOT NULL AUTO_INCREMENT,
	`userId` SMALLINT(5) UNSIGNED NOT NULL,
	`note` VARCHAR(50) NOT NULL COLLATE 'utf8_czech_ci',
	PRIMARY KEY (`id`),
	INDEX `FK_users_details_users` (`userId`),
	CONSTRAINT `FK_users_details_users` FOREIGN KEY (`userId`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
)
COLLATE='utf8_czech_ci'
ENGINE=InnoDB
AUTO_INCREMENT=79;

CREATE TABLE `joining_table` (
	`userId` SMALLINT UNSIGNED NOT NULL,
	`userLogId` SMALLINT UNSIGNED NOT NULL,
	PRIMARY KEY (`userId`, `userLogId`)
)
COLLATE='utf8_czech_ci'
ENGINE=InnoDB;


CREATE TABLE `cities` (
	`id` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`name` VARCHAR(50) NOT NULL,
	`population` MEDIUMINT UNSIGNED NOT NULL
)
COLLATE='utf8_czech_ci'
ENGINE=InnoDB
;

ALTER TABLE `users`
	ADD COLUMN `cityId` SMALLINT UNSIGNED NOT NULL DEFAULT '0' AFTER `birthDate`;

INSERT INTO `dibi-orm`.`cities` (`id`, `name`, `population`) VALUES (1, 'Prague', 1250000);
INSERT INTO `dibi-orm`.`cities` (`id`, `name`, `population`) VALUES (2, 'Mexico City', 10000000);