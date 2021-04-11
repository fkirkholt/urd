CREATE TABLE urd.database_ (
  name varchar(30) NOT NULL DEFAULT '',
  alias varchar(30),
  platform varchar(50),
  host varchar(50),
  port integer,
  username varchar(30),
  password varchar(30),
  label varchar(60) NOT NULL,
  description varchar(1000),
  schema_ varchar(50),
  date_format varchar(10),
  log boolean NOT NULL DEFAULT '0',
  PRIMARY KEY (name)
);

INSERT INTO database_ (name,alias,platform,host,port,username,password,label,description,schema_,date_format,log) 
VALUES
  ('urd',NULL,'mysql',NULL,NULL,NULL,NULL,'URD',NULL,'urd',NULL,false);


CREATE TABLE filter (
  id serial,
  schema_ varchar(30),
  table_ varchar(50)  NOT NULL DEFAULT '',
  expression varchar(1000)  NOT NULL DEFAULT '',
  label varchar(50)  NOT NULL DEFAULT '',
  user_ varchar(30),
  standard boolean NOT NULL DEFAULT '0',
  advanced boolean NOT NULL DEFAULT '0',
  PRIMARY KEY (id)
);



CREATE TABLE format (
  schema_ varchar(30)  NOT NULL,
  table_ varchar(30)  NOT NULL,
  class varchar(30)  NOT NULL,
  filter varchar(250)  NOT NULL,
  PRIMARY KEY (schema_,table_,class)
);



CREATE TABLE message (
  id serial,
  time timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  user_ varchar(30),
  type varchar(30),
  text text  NOT NULL,
  file_ varchar(100),
  line int,
  trace text ,
  parameters text ,
  PRIMARY KEY (id)
);


CREATE TABLE organization (
  id varchar(10) NOT NULL DEFAULT '',
  name varchar(200) NOT NULL,
  parent varchar(10),
  leader varchar(30),
  PRIMARY KEY (id)
);


CREATE TABLE role (
  id serial,
  name varchar(100)  NOT NULL DEFAULT '',
  schema_ varchar(30),
  PRIMARY KEY (id)
);

INSERT INTO role (id, name, schema_) VALUES (1, 'Admin', '*');


CREATE TABLE role_permission (
  role int NOT NULL,
  schema_ varchar(30)  NOT NULL DEFAULT '',
  table_ varchar(30)  NOT NULL DEFAULT '',
  view_ boolean NOT NULL DEFAULT false,
  add_ boolean NOT NULL DEFAULT false,
  edit boolean NOT NULL DEFAULT false,
  delete_ boolean NOT NULL DEFAULT false,
  admin boolean NOT NULL DEFAULT false,
  PRIMARY KEY (role,schema_,table_)
);

INSERT INTO role_permission (role,schema_,table_,view_,add_,edit,delete_,admin) 
VALUES
  (1,'urd','*',true,true,true,true,true),
  (1,'*','*',true,false,false,false,true);


CREATE TABLE user_ (
  id varchar(30)  NOT NULL,
  name varchar(50)  NOT NULL,
  email varchar(50),
  passord_disabled varchar(12),
  organization varchar(10),
  hash varchar(255),
  active boolean NOT NULL DEFAULT true,
  PRIMARY KEY (id)
);

INSERT INTO user_ (id,name,email,passord_disabled,organization,hash,active) 
VALUES
  ('admin','Admin',NULL,NULL,NULL,'$2y$10$EzebOh8HLEq6WtX/OxDtzOIikL7/EQS5aQstb2J7jkCG4jynE2iIK',true);

CREATE TABLE user_role (
  user_ varchar(30)  NOT NULL DEFAULT '',
  schema_ varchar(30)  NOT NULL DEFAULT '',
  role int NOT NULL,
  PRIMARY KEY (user_,role)
);

INSERT INTO user_role (user_,schema_,role) 
VALUES
  ('admin','urd',1);
