<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
if($_GET["login"]=="yes" && $USER->IsAuthorized())
  LocalRedirect("/calls_analytic/");
?>
