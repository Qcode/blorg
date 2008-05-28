create table BlorgGadgetInstanceSettingValue (
	gadget_instance integer not null
		references BlorgGadgetInstance(id) on delete cascade,

	name varchar(255) not null,

	value_boolean boolean,
	value_date    timestamp,
	value_float   double precision,
	value_integer integer,
	value_string  varchar(255),
	value_text    text
);
