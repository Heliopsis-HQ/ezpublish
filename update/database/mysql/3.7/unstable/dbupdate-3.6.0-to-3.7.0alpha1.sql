UPDATE ezsite_data SET value='3.7.0alpha1' WHERE name='ezpublish-version';
UPDATE ezsite_data SET value='1' WHERE name='ezpublish-release';

ALTER TABLE ezorder ADD is_archived INT DEFAULT '0' NOT NULL ;
ALTER TABLE ezorder ADD INDEX ( is_archived ) ;


-- Improved Approval Workflow -- START --

UPDATE ezworkflow_event set data_text3=data_int1;

-- Improved Approval Workflow --  END  --
