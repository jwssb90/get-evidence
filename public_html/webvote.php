<?php;

include "lib/setup.php";

evidence_set_my_web_vote ($_REQUEST["variant_id"], $_REQUEST["url"], $_REQUEST["score"]);
$myvotes =& evidence_get_my_web_vote ($_REQUEST["variant_id"]);
$allvotes =& evidence_get_web_votes ($_REQUEST["variant_id"]);

header ("Content-type: application/json");
print json_encode (array ("my" => $myvotes, "all" => $allvotes));
