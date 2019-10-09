CREATE USER 'urd'@'%' IDENTIFIED BY 'urd';CREATE DATABASE IF NOT EXISTS `urd`;GRANT ALL PRIVILEGES ON `urd`.* TO 'urd'@'%';GRANT ALL PRIVILEGES ON `urd\_%`.* TO 'urd'@'%';

CREATE TABLE `urd`.`database_` (
  `name` varchar(30) COLLATE utf8_danish_ci NOT NULL DEFAULT '',
  `alias` varchar(30) COLLATE utf8_danish_ci DEFAULT NULL,
  `platform` varchar(50) COLLATE utf8_danish_ci DEFAULT NULL,
  `host` varchar(50) COLLATE utf8_danish_ci DEFAULT NULL,
  `port` int(4) DEFAULT NULL,
  `username` varchar(30) COLLATE utf8_danish_ci DEFAULT NULL,
  `password` varchar(30) COLLATE utf8_danish_ci DEFAULT NULL,
  `label` varchar(60) COLLATE utf8_danish_ci NOT NULL,
  `description` varchar(1000) COLLATE utf8_danish_ci DEFAULT NULL,
  `schema_` varchar(50) COLLATE utf8_danish_ci DEFAULT NULL,
  `date_format` varchar(10) COLLATE utf8_danish_ci DEFAULT NULL,
  `log` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_danish_ci;

INSERT INTO urd.database_ (name,alias,platform,host,port,username,password,label,description,schema_,date_format,log) 
VALUES
  ('urd',NULL,'mysql',NULL,NULL,NULL,NULL,'URD',NULL,'urd',NULL,false);


CREATE TABLE `urd`.`filter` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `schema_` varchar(30) COLLATE utf8_danish_ci DEFAULT NULL,
  `table_` varchar(50) COLLATE utf8_danish_ci NOT NULL DEFAULT '',
  `expression` varchar(1000) COLLATE utf8_danish_ci NOT NULL DEFAULT '',
  `label` varchar(50) COLLATE utf8_danish_ci NOT NULL DEFAULT '',
  `user_` varchar(30) COLLATE utf8_danish_ci DEFAULT NULL,
  `standard` tinyint(1) NOT NULL DEFAULT '0',
  `advanced` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_danish_ci;



CREATE TABLE `urd`.`format` (
  `schema_` varchar(30) COLLATE utf8_danish_ci NOT NULL,
  `table_` varchar(30) COLLATE utf8_danish_ci NOT NULL,
  `class` varchar(30) COLLATE utf8_danish_ci NOT NULL,
  `filter` varchar(250) COLLATE utf8_danish_ci NOT NULL,
  PRIMARY KEY (`schema_`,`table_`,`class`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_danish_ci;



CREATE TABLE `urd`.`message` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `time` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `user_` varchar(30) COLLATE utf8_danish_ci DEFAULT NULL,
  `type` varchar(30) COLLATE utf8_danish_ci DEFAULT NULL,
  `text` text COLLATE utf8_danish_ci NOT NULL,
  `file_` varchar(100) COLLATE utf8_danish_ci DEFAULT NULL,
  `line` int(11) DEFAULT NULL,
  `trace` text COLLATE utf8_danish_ci,
  `parameters` text COLLATE utf8_danish_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_danish_ci;



CREATE TABLE `urd`.`migration` (
  `version` varchar(255) COLLATE utf8_danish_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_danish_ci COMMENT='?';



CREATE TABLE `urd`.`organization` (
  `id` varchar(10) NOT NULL DEFAULT '',
  `name` varchar(200) NOT NULL,
  `parent` varchar(10) DEFAULT NULL,
  `leader` varchar(30) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;



CREATE TABLE `urd`.`role` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8_danish_ci NOT NULL DEFAULT '',
  `schema_` varchar(30) COLLATE utf8_danish_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_danish_ci;

INSERT INTO `urd`.`role` (`id`, `name`, `schema_`) VALUES (1, 'Admin', '*');


CREATE TABLE `urd`.`role_permission` (
  `role` int(11) unsigned NOT NULL,
  `schema_` varchar(30) COLLATE utf8_danish_ci NOT NULL DEFAULT '',
  `table_` varchar(30) COLLATE utf8_danish_ci NOT NULL DEFAULT '',
  `view_` tinyint(1) NOT NULL DEFAULT '0',
  `add_` tinyint(1) NOT NULL DEFAULT '0',
  `edit` tinyint(1) NOT NULL DEFAULT '0',
  `delete_` tinyint(1) NOT NULL DEFAULT '0',
  `admin` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`role`,`schema_`,`table_`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_danish_ci;

INSERT INTO urd.role_permission (role,schema_,table_,view_,add_,edit,delete_,admin) 
VALUES
  (1,'urd','*',true,true,true,true,true),
  (1,'*','*',true,false,false,false,true);


CREATE TABLE `urd`.`user_` (
  `id` varchar(30) COLLATE utf8_danish_ci NOT NULL,
  `name` varchar(50) COLLATE utf8_danish_ci NOT NULL,
  `email` varchar(50) COLLATE utf8_danish_ci DEFAULT NULL,
  `passord_disabled` varchar(12) COLLATE utf8_danish_ci DEFAULT NULL,
  `organization` varchar(10) COLLATE utf8_danish_ci DEFAULT NULL,
  `hash` varchar(255) COLLATE utf8_danish_ci DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_danish_ci;

INSERT INTO urd.user_ (id,name,email,passord_disabled,organization,hash,active) 
VALUES
  ('admin','Admin',NULL,NULL,NULL,'$2y$10$EzebOh8HLEq6WtX/OxDtzOIikL7/EQS5aQstb2J7jkCG4jynE2iIK',true);

CREATE TABLE `urd`.`user_role` (
  `user_` varchar(30) COLLATE utf8_danish_ci NOT NULL DEFAULT '',
  `schema_` varchar(30) COLLATE utf8_danish_ci NOT NULL DEFAULT '',
  `role` int(11) NOT NULL,
  PRIMARY KEY (`user_`,`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_danish_ci;

INSERT INTO urd.user_role (user_,schema_,role) 
VALUES
  ('admin','urd',1);
