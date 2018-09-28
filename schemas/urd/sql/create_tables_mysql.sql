CREATE TABLE `database_` (
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



CREATE TABLE `filter` (
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



CREATE TABLE `format` (
  `schema_` varchar(30) COLLATE utf8_danish_ci NOT NULL,
  `table_` varchar(30) COLLATE utf8_danish_ci NOT NULL,
  `class` varchar(30) COLLATE utf8_danish_ci NOT NULL,
  `filter` varchar(250) COLLATE utf8_danish_ci NOT NULL,
  PRIMARY KEY (`schema_`,`table_`,`class`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_danish_ci;



CREATE TABLE `message` (
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



CREATE TABLE `migration` (
  `version` varchar(255) COLLATE utf8_danish_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_danish_ci COMMENT='?';



CREATE TABLE `organization` (
  `id` varchar(10) NOT NULL DEFAULT '',
  `name` varchar(200) NOT NULL,
  `parent` varchar(10) DEFAULT NULL,
  `leader` varchar(30) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;



CREATE TABLE `role` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8_danish_ci NOT NULL DEFAULT '',
  `schema_` varchar(30) COLLATE utf8_danish_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_danish_ci;



CREATE TABLE `role_permission` (
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



CREATE TABLE `user_` (
  `id` varchar(30) COLLATE utf8_danish_ci NOT NULL,
  `name` varchar(50) COLLATE utf8_danish_ci NOT NULL,
  `email` varchar(50) COLLATE utf8_danish_ci DEFAULT NULL,
  `passord_disabled` varchar(12) COLLATE utf8_danish_ci DEFAULT NULL,
  `organization` varchar(10) COLLATE utf8_danish_ci DEFAULT NULL,
  `hash` varchar(255) COLLATE utf8_danish_ci DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_danish_ci;



CREATE TABLE `user_role` (
  `user_` varchar(30) COLLATE utf8_danish_ci NOT NULL DEFAULT '',
  `schema_` varchar(30) COLLATE utf8_danish_ci NOT NULL DEFAULT '',
  `role` int(11) NOT NULL,
  PRIMARY KEY (`user_`,`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_danish_ci;

