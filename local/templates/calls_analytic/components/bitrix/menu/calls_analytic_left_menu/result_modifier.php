<?if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

foreach ($arResult as $key => $value) {
  if(preg_match("/index/", $value["LINK"])) {
    $arResult[$key]["ICON"]="icon-home4";
  };
  if(preg_match("/calls_table.php$/", $value["LINK"])) {
    $arResult[$key]["ICON"]="icon-list";
  };
  if(preg_match("/calls_data_count.php/", $value["LINK"])) {
    $arResult[$key]["ICON"]="glyphicon glyphicon-time";
  };
  if(preg_match("/common_analytic.php/", $value["LINK"])) {
    $arResult[$key]["ICON"]="glyphicon glyphicon-align-justify";
  };
  if(preg_match("/calls_distribution.php/", $value["LINK"])) {
    $arResult[$key]["ICON"]="glyphicon glyphicon-adjust";
  };
  if(preg_match("/calls_stat.php/", $value["LINK"])) {
    $arResult[$key]["ICON"]="glyphicon glyphicon-list-alt";
  };
  if(preg_match("/calls_ratio.php/", $value["LINK"])) {
    $arResult[$key]["ICON"]="glyphicon glyphicon-signal";
  };
  if(preg_match("/integration/", $value["LINK"])) {
    $arResult[$key]["ICON"]="glyphicon glyphicon-move";
  };
}
?>
