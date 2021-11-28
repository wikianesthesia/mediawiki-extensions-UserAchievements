CREATE TABLE /*_*/userachievements_userbadges (
  `user_id` INT(10) NOT NULL DEFAULT 0,
  `achievement_id` VARBINARY(32) NOT NULL,
  `level` INT(11) NOT NULL DEFAULT 0,
  `achieved_time` BINARY(14),
  `awarded_by_user_id` INT(10) NOT NULL DEFAULT 0,
  `notified` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`user_id`, `achievement_id`, `level`)
) /*$wgDBTableOptions*/;