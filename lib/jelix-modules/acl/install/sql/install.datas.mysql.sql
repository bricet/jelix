--

INSERT INTO `jacl_group` VALUES (1, 'administrateur', 0, NULL);
INSERT INTO `jacl_group` VALUES (2, 'inscrits', 1, NULL);

--

INSERT INTO `jacl_right_values` VALUES (1, 'acl~db.valgrp.truefalse.false', 1);
INSERT INTO `jacl_right_values` VALUES (2, 'acl~db.valgrp.truefalse.true', 1);
INSERT INTO `jacl_right_values` VALUES (1, 'acl~db.valgrp.crudl.list', 2);
INSERT INTO `jacl_right_values` VALUES (2, 'acl~db.valgrp.crudl.create', 2);
INSERT INTO `jacl_right_values` VALUES (4, 'acl~db.valgrp.crudl.read', 2);
INSERT INTO `jacl_right_values` VALUES (8, 'acl~db.valgrp.crudl.update', 2);
INSERT INTO `jacl_right_values` VALUES (16, 'acl~db.valgrp.crudl.delete', 2);
INSERT INTO `jacl_right_values` VALUES (1, 'acl~db.valgrp.yesno.no', 3);
INSERT INTO `jacl_right_values` VALUES (2, 'acl~db.valgrp.yesno.yes', 3);


--

INSERT INTO `jacl_right_values_group` VALUES (1, 'acl~db.valgrp.truefalse');
INSERT INTO `jacl_right_values_group` VALUES (2, 'acl~db.valgrp.crudl');
INSERT INTO `jacl_right_values_group` VALUES (3, 'acl~db.valgrp.yesno');




