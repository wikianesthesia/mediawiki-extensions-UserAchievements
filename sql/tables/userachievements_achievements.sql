CREATE TABLE /*_*/userachievements_achievements (
  `achievement_id` VARBINARY(32) NOT NULL,
  `enabled` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`achievement_id`)
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/userachievements_achievements_achievement_id ON /*_*/userachievements_achievements (achievement_id);